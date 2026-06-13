<?php

require_once 'auth_check.php';
require_admin_json();

require 'ids.php';

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    header("Content-Type: application/json");

    $name  = trim($_POST['name'] ?? '');
    $price = $_POST['price'] ?? null;

    if ($name === '' || $price === null || !isset($_FILES['image'])) {
        echo json_encode(["status" => "error", "message" => "Missing required fields"]);
        exit;
    }

    $allowed = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/gif'  => 'gif',
        'image/webp' => 'webp',
    ];

    $tmp  = $_FILES["image"]["tmp_name"];
    $size = $_FILES["image"]["size"];

    if ($size > 5 * 1024 * 1024) {
        echo json_encode(["status" => "error", "message" => "Image too large (max 5 MB)"]);
        exit;
    }

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime  = finfo_file($finfo, $tmp);
    finfo_close($finfo);

    if (!isset($allowed[$mime])) {
        echo json_encode(["status" => "error", "message" => "Only JPG, PNG, GIF, or WEBP allowed"]);
        exit;
    }

    $ext         = $allowed[$mime];
    $safe_name   = bin2hex(random_bytes(8)) . '.' . $ext;
    $target_dir  = dirname(__DIR__) . "/uploads/";
    if (!is_dir($target_dir)) mkdir($target_dir, 0755, true);
    $target_file = $target_dir . $safe_name;

    if (move_uploaded_file($tmp, $target_file)) {
        $db_image_path = $safe_name;
        $sql  = "INSERT INTO products (name, price, image) VALUES (?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sds", $name, $price, $db_image_path);

        if ($stmt->execute()) {
            echo json_encode(["status" => "success", "message" => "Product added successfully"]);
        } else {
            error_log("Add product failed: " . $stmt->error);
            echo json_encode(["status" => "error", "message" => "Database error"]);
        }
        $stmt->close();
    } else {
        echo json_encode(["status" => "error", "message" => "Failed to move uploaded file"]);
    }
    $conn->close();
}
?>
