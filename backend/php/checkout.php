<?php
/* ============================================================
 *  checkout.php  —  Alternate order endpoint
 * ------------------------------------------------------------
 *  SECURITY FIX (Low): now includes ids.php so it is covered by
 *  rate limiting and attack scanning like the other endpoints.
 *  Already used prepared statements (good); kept that.
 * ============================================================ */

session_start();
require 'ids.php';            // ids.php includes db_connect.php
header("Content-Type: application/json");

$data = json_decode(file_get_contents("php://input"), true);
if (!isset($data['cart']) || empty($data['cart'])) {
    echo json_encode(["status" => "error", "message" => "Cart is empty"]);
    exit;
}

// Total is calculated on the server, never trusted from the client.
$total_price = 0;
foreach ($data['cart'] as $item) {
    $total_price += ((float)($item['price'] ?? 0)) * ((int)($item['quantity'] ?? 0));
}

$stmt = $conn->prepare("INSERT INTO orders (total_price) VALUES (?)");
$stmt->bind_param("d", $total_price);
$stmt->execute();
$order_id = $stmt->insert_id;
$stmt->close();

$stmt = $conn->prepare("INSERT INTO order_items (order_id, product_id, quantity) VALUES (?, ?, ?)");
foreach ($data['cart'] as $item) {
    $pid = (int)($item['id'] ?? 0);
    $qty = (int)($item['quantity'] ?? 0);
    $stmt->bind_param("iii", $order_id, $pid, $qty);
    $stmt->execute();
}
$stmt->close();

echo json_encode(["status" => "success", "message" => "Order placed successfully"]);
$conn->close();
?>
