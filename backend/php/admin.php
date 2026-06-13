<?php

require_once 'auth_check.php';
require_admin();

require 'ids.php';

$user_count    = $conn->query("SELECT COUNT(*) as count FROM users")->fetch_assoc()['count'];
$product_count = $conn->query("SELECT COUNT(*) as count FROM products")->fetch_assoc()['count'];
$blocked_count = $conn->query("SELECT COUNT(*) as count FROM blocked_ips")->fetch_assoc()['count'];

$visitor_result = $conn->query("SELECT * FROM visitors ORDER BY visited_at DESC LIMIT 10");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>
    <link rel="stylesheet" href="../../frontend/css/style.css">
    <script src="../../frontend/js/admin_dashboard.js" defer></script>
    <style>
        .stats-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(200px,1fr)); gap:20px; margin-bottom:30px; }
        .stat-card { background:white; padding:20px; border-radius:8px; box-shadow:0 2px 5px rgba(0,0,0,.1); text-align:center; }
        .stat-card h3 { margin:0; color:#555; font-size:1rem; }
        .stat-card p  { font-size:2rem; font-weight:bold; margin:10px 0 0; color:#333; }
        .stat-card.danger p { color:#dc3545; }
        .visitor-table { width:100%; border-collapse:collapse; background:white; border-radius:8px; overflow:hidden; box-shadow:0 2px 5px rgba(0,0,0,.1); margin-top:10px; }
        .visitor-table th, .visitor-table td { padding:12px 15px; text-align:left; border-bottom:1px solid #ddd; }
        .visitor-table th { background-color:#f8f9fa; font-weight:bold; color:#555; }
    </style>
</head>
<body>
    <header>
        <nav>
            <a href="../../frontend/index.html">Home</a>
            <a href="products.php">Manage Products</a>
            <a href="security_dashboard.php" style="color:#ff9999;">Security Dashboard</a>
            <a href="logout.php" style="float:right;">Logout (<?php echo htmlspecialchars($_SESSION['username']); ?>)</a>
        </nav>
    </header>

    <main>
        <h1>Admin Dashboard</h1>
        <p>Welcome back, <strong><?php echo htmlspecialchars($_SESSION['username']); ?></strong>.</p>

        <section class="stats-grid">
            <div class="stat-card">
                <h3>Total Users</h3>
                <p><?php echo $user_count; ?></p>
            </div>
            <div class="stat-card">
                <h3>Products</h3>
                <p><?php echo $product_count; ?></p>
            </div>
            <div class="stat-card danger">
                <h3>Blocked IPs</h3>
                <p><?php echo $blocked_count; ?></p>
            </div>
        </section>

        <section style="margin-bottom:40px;">
            <h2>Live Visitor Log</h2>
            <table class="visitor-table">
                <thead><tr><th>ID</th><th>IP Address</th><th>Time Visited</th></tr></thead>
                <tbody>
                    <?php if ($visitor_result && $visitor_result->num_rows > 0): ?>
                        <?php while ($row = $visitor_result->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($row['id']); ?></td>
                                <td>
                                    <?php
                                    $ip = $row['ip_address'];
                                    if ($ip === '::1' || $ip === '127.0.0.1') {
                                        echo "<strong>" . htmlspecialchars($ip) . " (You)</strong>";
                                    } else {
                                        echo htmlspecialchars($ip);
                                    }
                                    ?>
                                </td>
                                <td><?php echo htmlspecialchars($row['visited_at']); ?></td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="3" style="text-align:center;">No visitors yet.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </section>

        <section>
            <h2>Add Product</h2>

            <form id="add-product-form" action="add_product.php" method="POST" enctype="multipart/form-data"
                  style="background:white; padding:20px; border-radius:8px; box-shadow:0 2px 5px rgba(0,0,0,.1);">
                <label for="name">Product Name:</label>
                <input type="text" id="name" name="name" required style="width:100%; padding:8px; margin-bottom:10px;">

                <label for="price">Price:</label>
                <input type="number" id="price" name="price" step="0.01" required style="width:100%; padding:8px; margin-bottom:10px;">

                <label for="image">Product Image:</label>
                <input type="file" id="image" name="image" accept="image/*" required style="margin-bottom:10px;"><br>

                <button type="submit" style="padding:10px 20px; background:#28a745; color:white; border:none; border-radius:4px; cursor:pointer;">
                    Add Product
                </button>
            </form>
        </section>

        <section>
            <h2>Recent Orders</h2>
            <div id="orders-container"><p>Loading orders...</p></div>
        </section>
    </main>
</body>
</html>
