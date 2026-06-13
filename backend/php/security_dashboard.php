<?php
// SECURITY: shared admin guard.
require_once 'auth_check.php';
require_admin();
require_once 'csrf.php';

include 'db_connect.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['unblock_ip'])) {
    // SECURITY FIX (CSRF): verify token before unblocking an IP.
    csrf_verify();
    $ip_to_unblock = $_POST['unblock_ip'];
    $stmt = $conn->prepare("DELETE FROM blocked_ips WHERE ip_address = ?");
    $stmt->bind_param("s", $ip_to_unblock);
    if ($stmt->execute()) {
        $message = "Success: IP $ip_to_unblock has been unblocked.";
    } else {
        $error = "Error: Could not unblock IP.";
    }
    $stmt->close();
}

$logs_query = "SELECT * FROM security_logs ORDER BY logged_at DESC LIMIT 20";
$logs_result = $conn->query($logs_query);

$blocks_query = "SELECT * FROM blocked_ips ORDER BY blocked_at DESC";
$blocks_result = $conn->query($blocks_query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Security Dashboard</title>
    <link rel="stylesheet" href="../../frontend/css/style.css">
    <link rel="stylesheet" href="../../frontend/css/security_dashboard.css">
</head>
<body>

    <header>
        <nav>
            <a href="../../frontend/index.html">Home</a>
            <a href="admin.php">Admin Panel</a>
            <a href="security_dashboard.php" style="color: #ff9999;">Security Dashboard</a>
        </nav>
    </header>

    <main class="dashboard-container">
        <h1>Security</h1>
        <p>Monitor detected threats and manage blocked users.</p>

        <?php if (isset($message)): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>

        <h2>Currently Blocked IPs</h2>
        <?php if ($blocks_result->num_rows > 0): ?>
            <table>
                <thead>
                    <tr>
                        <th>IP Address</th>
                        <th>Reason</th>
                        <th>Blocked At</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($row = $blocks_result->fetch_assoc()): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($row['ip_address']); ?></strong></td>
                            <td><?php echo htmlspecialchars($row['reason']); ?></td>
                            <td><?php echo htmlspecialchars($row['blocked_at']); ?></td>
                            <td>
                                <form method="POST" style="margin:0;">
                                    <?php echo csrf_field(); ?>
                                    <input type="hidden" name="unblock_ip" value="<?php echo htmlspecialchars($row['ip_address']); ?>">
                                    <button type="submit" class="btn-unblock">Unblock</button>
                                </form>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p>No active IP bans. Everyone is behaving!</p>
        <?php endif; ?>

        <h2>📜 Recent Security Incidents</h2>
        <?php if ($logs_result->num_rows > 0): ?>
            <table>
                <thead>
                    <tr>
                        <th>Time</th>
                        <th>IP Address</th>
                        <th>Attack Type</th>
                        <th>Payload (Evidence)</th>
                        <th>Page</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($log = $logs_result->fetch_assoc()): ?>
                        <?php 
                            $badgeClass = (stripos($log['attack_type'], 'SQL') !== false) ? 'badge-sql' : 'badge-xss';
                        ?>
                        <tr>
                            <td><?php echo htmlspecialchars($log['logged_at']); ?></td>
                            <td><?php echo htmlspecialchars($log['ip_address']); ?></td>
                            <td><span class="badge <?php echo $badgeClass; ?>"><?php echo htmlspecialchars($log['attack_type']); ?></span></td>
                            <td><code><?php echo htmlspecialchars($log['payload']); ?></code></td>
                            <td><?php echo htmlspecialchars($log['request_uri']); ?></td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p>No threats detected yet.</p>
        <?php endif; ?>

    </main>

</body>
</html>
<?php $conn->close(); ?>