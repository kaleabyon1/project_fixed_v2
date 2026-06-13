<?php
/* SECURITY FIX (already had a check — now uses the shared helper):
 * The user list is admin-only. */
require_once 'auth_check.php';
require_admin();

include 'db_connect.php';
$users = $conn->query("SELECT * FROM users");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Users</title>
    <link rel="stylesheet" href="../../frontend/css/style.css">
</head>
<body>
    <header>
        <nav>
            <a href="../../frontend/index.html">Home</a>
            <a href="admin.php">Admin Dashboard</a>
        </nav>
    </header>
    <main>
        <h2>Manage Users</h2>
        <ul>
            <?php while ($user = $users->fetch_assoc()): ?>
                <li><?php echo htmlspecialchars($user['username']) . ' - ' . htmlspecialchars($user['email']); ?></li>
            <?php endwhile; ?>
        </ul>
    </main>
</body>
</html>
<?php $conn->close(); ?>
