<?php

require 'ids.php';
header("Content-Type: application/json");

$data = json_decode(file_get_contents("php://input"), true);

if (!$data || !isset($data['name'], $data['address'], $data['payment'], $data['cart'])) {
    echo json_encode(["status" => "error", "message" => "Invalid order data"]);
    exit;
}

$name    = $data['name'];
$address = $data['address'];
$payment = $data['payment'];
$cart    = $data['cart'];

if (!is_array($cart) || count($cart) === 0) {
    echo json_encode(["status" => "error", "message" => "Cart is empty"]);
    exit;
}

$total_price = 0;
foreach ($cart as $item) {
    $price = (float)($item['price'] ?? 0);
    $qty   = (int)($item['quantity'] ?? 0);
    $total_price += ($price * $qty);
}

$sql  = "INSERT INTO orders (name, address, payment_method, total_price) VALUES (?, ?, ?, ?)";
$stmt = $conn->prepare($sql);
$stmt->bind_param("sssd", $name, $address, $payment, $total_price);

if ($stmt->execute()) {
    $orderId = $stmt->insert_id;
    $stmt->close();

    $item_sql  = "INSERT INTO order_items (order_id, product_id, quantity) VALUES (?, ?, ?)";
    $item_stmt = $conn->prepare($item_sql);

    foreach ($cart as $product) {
        $productId = (int)($product['id'] ?? 0);
        $quantity  = (int)($product['quantity'] ?? 0);
        $item_stmt->bind_param("iii", $orderId, $productId, $quantity);
        $item_stmt->execute();
    }
    $item_stmt->close();

    echo json_encode(["status" => "success", "message" => "Order placed successfully"]);
} else {
    error_log("Checkout failed: " . $conn->error);
    echo json_encode(["status" => "error", "message" => "Could not place order. Please try again."]);
}

$conn->close();
?>
