<?php
/* ============================================================
 *  ids.php  —  Intrusion Detection + DoS/DDoS Shield
 * ------------------------------------------------------------
 *  Runs at the TOP of every public page. It does 4 things:
 *   1) Find the visitor's IP (safely — see security note below).
 *   2) Block IPs already on the ban list.
 *   3) Rate-limit each IP (burst = DoS, sustained = DDoS).
 *   4) Scan the request for attack patterns (SQLi, XSS, ...).
 * ============================================================ */

require_once __DIR__ . '/db_connect.php';

/* ---------- 1. Get the client IP ----------
 *  SECURITY FIX (was: CRITICAL — IDS bypass):
 *  The old code blindly trusted X-Forwarded-For / CF-Connecting-IP
 *  headers. Those headers can be FAKED by the attacker on every
 *  request, letting them dodge every ban and rate limit.
 *
 *  Headers may only be trusted if the request actually arrives
 *  THROUGH a proxy we control. On a direct XAMPP setup there is no
 *  proxy, so we use REMOTE_ADDR — the one value the attacker cannot
 *  spoof (it is set by the web server from the real TCP connection).
 *
 *  If you later put this behind Cloudflare/Nginx, add that proxy's
 *  IP to $trusted_proxies and the CF header will be honoured.
 */
function get_client_ip() {
    $trusted_proxies = [
        // e.g. '127.0.0.1', or your load-balancer / Cloudflare IP ranges
    ];

    $remote = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

    // Only consult forwarded headers if the direct connection is a trusted proxy.
    if (in_array($remote, $trusted_proxies, true)) {
        $headers = ['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP'];
        foreach ($headers as $h) {
            if (!empty($_SERVER[$h])) {
                // X-Forwarded-For can be "client, proxy1, proxy2" — take the first.
                $ip = trim(explode(',', $_SERVER[$h])[0]);
                if (filter_var($ip, FILTER_VALIDATE_IP)) return $ip;
            }
        }
    }

    return $remote;
}
$client_ip = get_client_ip();

/* A small helper so every block/alert page escapes output safely.
 * SECURITY FIX (was: MEDIUM — XSS): the IP/reason used to be printed
 * into HTML raw. We now run everything through htmlspecialchars().  */
function e($v) {
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

/* ---------- 2. Is this IP already banned? ---------- */
$check_block = $conn->prepare("SELECT id FROM blocked_ips WHERE ip_address = ?");
$check_block->bind_param("s", $client_ip);
$check_block->execute();
$check_block->store_result();
if ($check_block->num_rows > 0) {
    http_response_code(403);
    die("<div style='background:red;color:white;padding:20px;text-align:center;font-family:sans-serif;'>
            <h1>ACCESS DENIED</h1>
            <p>Your IP address (" . e($client_ip) . ") is blocked due to malicious activity.</p>
         </div>");
}
$check_block->close();

/* ---------- 3. Rate limiting (DoS + DDoS detection) ----------
 *    BURST : > 15 requests in  5 seconds  -> DoS  (one fast attacker)
 *    FLOOD : > 60 requests in 60 seconds  -> DDoS (sustained flood)
 */
$now          = time();
$burst_window = 5;   $burst_limit = 15;
$flood_window = 60;  $flood_limit = 60;

$stmt = $conn->prepare("SELECT request_count, window_start FROM rate_limits WHERE ip_address = ?");
$stmt->bind_param("s", $client_ip);
$stmt->execute();
$res = $stmt->get_result();
$stmt->close();

if ($row = $res->fetch_assoc()) {
    $elapsed   = $now - (int)$row['window_start'];
    $new_count = (int)$row['request_count'] + 1;

    if ($elapsed > $flood_window) {
        // Window expired -> reset counter.
        $u = $conn->prepare("UPDATE rate_limits SET request_count = 1, window_start = ? WHERE ip_address = ?");
        $u->bind_param("is", $now, $client_ip);
        $u->execute(); $u->close();
    } else if ($elapsed <= $burst_window && $new_count > $burst_limit) {
        ban_ip($conn, $client_ip, "DoS burst: $new_count requests in {$burst_window}s", "DoS Attack");
    } else if ($new_count > $flood_limit) {
        ban_ip($conn, $client_ip, "DDoS flood: $new_count requests in {$flood_window}s", "DDoS Attack");
    } else {
        $u = $conn->prepare("UPDATE rate_limits SET request_count = ? WHERE ip_address = ?");
        $u->bind_param("is", $new_count, $client_ip);
        $u->execute(); $u->close();
    }
} else {
    // First time we see this IP -> insert.
    $i = $conn->prepare("INSERT INTO rate_limits (ip_address, request_count, window_start) VALUES (?, 1, ?)");
    $i->bind_param("si", $client_ip, $now);
    $i->execute(); $i->close();
}

/* ---------- 4. Attack-pattern scanner ---------- */
$patterns = [
    'SQL Injection'     => '/(union\s+select|drop\s+table|insert\s+into|update\s+set|select\s+\*\s+from)/i',
    'XSS Attack'        => '/(<script\b|javascript:|onerror\s*=|onload\s*=)/i',
    'Path Traversal'    => '/(\.\.\/|\.\.\\\\)/',
    'Command Injection' => '/(cmd\.exe|whoami|net\s+user|systeminfo|shutdown|;\s*rm\s+-rf)/i',
    'Login Bypass'      => "/(' OR |'OR|--|#\s|1\s*=\s*1)/i",
];

$incoming = array_merge($_GET, $_POST);
foreach ($incoming as $value) {
    if (!is_string($value)) continue;
    foreach ($patterns as $attack_name => $regex) {
        if (preg_match($regex, $value)) {
            log_attack($conn, $client_ip, $attack_name, $value);
            ban_ip($conn, $client_ip, "Detected $attack_name", $attack_name);
            die("<div style='background:darkred;color:white;padding:20px;text-align:center;font-family:sans-serif;'>
                    <h1>SECURITY ALERT</h1>
                    <p>Malicious input detected: <strong>" . e($attack_name) . "</strong></p>
                 </div>");
        }
    }
}

/* ---------- Helper functions ---------- */
function log_attack($conn, $ip, $type, $payload) {
    $uri   = $_SERVER['REQUEST_URI'] ?? '';
    $agent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
    $s = $conn->prepare("INSERT INTO security_logs (ip_address, attack_type, payload, request_uri, user_agent) VALUES (?, ?, ?, ?, ?)");
    $s->bind_param("sssss", $ip, $type, $payload, $uri, $agent);
    $s->execute(); $s->close();
}

function ban_ip($conn, $ip, $reason, $type) {
    log_attack($conn, $ip, $type, $reason);
    $b = $conn->prepare("INSERT IGNORE INTO blocked_ips (ip_address, reason) VALUES (?, ?)");
    $b->bind_param("ss", $ip, $reason);
    $b->execute(); $b->close();

    $d = $conn->prepare("DELETE FROM rate_limits WHERE ip_address = ?");
    $d->bind_param("s", $ip);
    $d->execute(); $d->close();

    http_response_code(429);
    die("<div style='background:red;color:white;padding:20px;text-align:center;font-family:sans-serif;'>
            <h1>BLOCKED — " . e($type) . " DETECTED</h1>
            <p>" . e($reason) . "</p>
            <p>Your IP (" . e($ip) . ") has been banned.</p>
         </div>");
}
?>
