<?php

require_once __DIR__ . '/db_connect.php';
header('Content-Type: text/html; charset=utf-8');

$remote   = $_SERVER['REMOTE_ADDR'] ?? '';
$is_local = ($remote === '::1' || strpos($remote, '127.') === 0);

if (!$is_local) {
    http_response_code(403);
    die("<h1>Forbidden</h1><p>This tool can only be used from the server's own computer.</p>");
}

$cleared = [];
foreach (['blocked_ips', 'rate_limits', 'login_attempts'] as $table) {
    try {
        $conn->query("DELETE FROM `$table`");
        $cleared[] = $table;
    } catch (Throwable $e) {

    }
}
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>IDS Unblock</title>
    <style>
        body { font-family: sans-serif; max-width: 560px; margin: 60px auto; padding: 0 20px; }
        .ok  { background:#28a745; color:#fff; padding:16px 20px; border-radius:8px; }
        a.btn { display:inline-block; margin-top:20px; padding:10px 18px; background:#333;
                color:#fff; text-decoration:none; border-radius:6px; }
        code { background:#f1f1f1; padding:2px 6px; border-radius:4px; }
    </style>
</head>
<body>
    <div class="ok">
        <h1 style="margin-top:0;">✓ Unblocked</h1>
        <p>Cleared: <code><?php echo htmlspecialchars(implode(', ', $cleared)); ?></code></p>
    </div>
    <p>All IP bans, brute-force counters and rate-limit windows have been reset.
       You can browse the store again.</p>
    <a class="btn" href="../../frontend/index.php">← Back to the store</a>
</body>
</html>
