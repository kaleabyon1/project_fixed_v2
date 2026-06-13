<?php

require_once 'auth_check.php';
require_admin();
require_once 'csrf.php';
require 'ids.php';

$upload_error = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $name  = trim($_POST['name'] ?? '');
    $price = $_POST['price'] ?? null;

    if ($name === '' || $price === null || !isset($_FILES['image'])) {
        $upload_error = "Missing required fields.";
    } else {

        $allowed = [
            'image/jpeg' => 'jpg',
            'image/png'  => 'png',
            'image/gif'  => 'gif',
            'image/webp' => 'webp',
        ];

        $tmp  = $_FILES['image']['tmp_name'];
        $size = $_FILES['image']['size'];

        if ($size > 5 * 1024 * 1024) {
            $upload_error = "Image too large (max 5 MB).";
        } else {

            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime  = finfo_file($finfo, $tmp);
            finfo_close($finfo);

            if (!isset($allowed[$mime])) {
                $upload_error = "Only JPG, PNG, GIF, or WEBP images are allowed.";
            } else {

                $ext         = $allowed[$mime];
                $safe_name   = bin2hex(random_bytes(8)) . '.' . $ext;
                $target_dir  = dirname(__DIR__) . "/uploads/";
                if (!is_dir($target_dir)) mkdir($target_dir, 0755, true);
                $target_file = $target_dir . $safe_name;

                if (move_uploaded_file($tmp, $target_file)) {
                    $db_image = $safe_name;
                    $sql  = "INSERT INTO products (name, price, image) VALUES (?, ?, ?)";
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param("sds", $name, $price, $db_image);
                    if (!$stmt->execute()) {
                        error_log("Add product failed: " . $stmt->error);
                        $upload_error = "Could not save product.";
                    }
                    $stmt->close();
                } else {
                    $upload_error = "Failed to save uploaded image.";
                }
            }
        }
    }
}

$result = $conn->query("SELECT * FROM products");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Products</title>
    <link rel="stylesheet" href="../../frontend/css/products.css">
</head>
<body>
    <header>
        <nav>
            <a href="../../frontend/index.html">Home</a>
            <a href="products.php">Manage Products</a>
            <a href="users.php">Manage Users</a>
        </nav>
    </header>

    <main>
        <h1>Manage Products</h1>
        <p>Manage and add products to your store.</p>

        <?php if ($upload_error): ?>
            <div style="color:white; background:#dc3545; padding:10px; border-radius:4px; margin-bottom:15px;">
                <?php echo htmlspecialchars($upload_error); ?>
            </div>
        <?php endif; ?>

        <section>
            <h2>Add Product</h2>
            <form action="products.php" method="POST" enctype="multipart/form-data">
                <?php echo csrf_field(); ?>

                <label for="name">Product Name:</label>
                <input type="text" id="name" name="name" required>

                <label for="price">Price:</label>
                <input type="number" id="price" name="price" step="0.01" required>

                <label for="image">Product Image:</label>
                <input type="file" id="image" name="image" accept="image/*" required>

                <button type="submit">Add Product</button>
            </form>
        </section>

        <section>
            <h2>Product List</h2>
            <div class="product-list">
                <?php if ($result && $result->num_rows > 0): ?>
                    <?php while ($product = $result->fetch_assoc()): ?>
                        <div class="product-item">
                            <img src="../uploads/<?php echo htmlspecialchars(basename($product['image'])); ?>" alt="<?php echo htmlspecialchars($product['name']); ?>">
                            <p>Name: <?php echo htmlspecialchars($product['name']); ?></p>
                            <p>Price: $<?php echo number_format($product['price'], 2); ?></p>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <p>No products found.</p>
                <?php endif; ?>
            </div>
        </section>
    </main>
</body>
</html>
