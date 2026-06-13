<?php

require_once __DIR__ . '/db_connect.php';

function normalize_ip($ip) {
    if ($ip === '::1') {
        return '127.0.0.1';
    }
    if (stripos($ip, '::ffff:') === 0) {
        $v4 = substr($ip, 7);
        if (filter_var($v4, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return $v4;
        }
    }
    return $ip;
}

function is_whitelisted_ip($ip) {
    $whitelist = ['127.0.0.1', '::1'];
    return in_array($ip, $whitelist, true);
}

function is_loopback($ip) {
    return $ip === '::1' || strpos($ip, '127.') === 0;
}

function server_lan_ip() {

    if (function_exists('socket_create')) {
        $sock = @socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
        if ($sock !== false) {
            if (@socket_connect($sock, '8.8.8.8', 53)) {
                @socket_getsockname($sock, $ip);
                @socket_close($sock);
                if (!empty($ip) && filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)
                    && !is_loopback($ip)) {
                    return $ip;
                }
            } else {
                @socket_close($sock);
            }
        }
    }

    $host = gethostbyname(gethostname());
    if (filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) && !is_loopback($host)) {
        return $host;
    }
    return '127.0.0.1';
}

function get_client_ip() {
    $trusted_proxies = [

    ];

    $remote = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

    if (in_array($remote, $trusted_proxies, true)) {
        $headers = ['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP'];
        foreach ($headers as $h) {
            if (!empty($_SERVER[$h])) {

                $ip = trim(explode(',', $_SERVER[$h])[0]);
                if (filter_var($ip, FILTER_VALIDATE_IP)) return normalize_ip($ip);
            }
        }
    }

    return normalize_ip($remote);
}
$client_ip = get_client_ip();

if (is_loopback($client_ip)) {
    $client_ip = server_lan_ip();
}

function e($v) {
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

if (!is_whitelisted_ip($client_ip)) {
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
}

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

    $i = $conn->prepare("INSERT INTO rate_limits (ip_address, request_count, window_start) VALUES (?, 1, ?)");
    $i->bind_param("si", $client_ip, $now);
    $i->execute(); $i->close();
}

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

function log_attack($conn, $ip, $type, $payload) {
    $uri   = $_SERVER['REQUEST_URI'] ?? '';
    $agent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
    $s = $conn->prepare("INSERT INTO security_logs (ip_address, attack_type, payload, request_uri, user_agent) VALUES (?, ?, ?, ?, ?)");
    $s->bind_param("sssss", $ip, $type, $payload, $uri, $agent);
    $s->execute(); $s->close();
}

function ban_ip($conn, $ip, $reason, $type) {

    if (is_whitelisted_ip($ip)) {
        return;
    }
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
