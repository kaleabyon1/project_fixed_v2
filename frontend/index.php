<?php

require __DIR__ . '/../backend/php/ids.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>E-commerce Platform</title>
    <link rel="stylesheet" href="css/style.css">
    <script src="js/load_products.js" defer></script>
</head>
<body>
    <header>
        <nav>
            <a href="index.php">Home</a>
            <a href="cart.html">Cart</a>

            <a href="../backend/php/login.php" style="color:red; font-weight:bold;">Admin Panel</a>
        </nav>
    </header>

    <main>
        <h1>Welcome to Our Store!</h1>
        <h2>Our Products</h2>
        <div id="product-list" class="product-grid"></div>
    </main>
</body>
</html>
