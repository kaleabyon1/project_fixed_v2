<?php
require 'ids.php';

header("Content-Type: application/json");

$user_ip  = $client_ip ?? ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
$vis_stmt = $conn->prepare("INSERT INTO visitors (ip_address) VALUES (?)");
$vis_stmt->bind_param("s", $user_ip);
$vis_stmt->execute();
$vis_stmt->close();

$sql    = "SELECT id, name, price, image FROM products";
$result = $conn->query($sql);

$products = [];
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $products[] = $row;
    }
}
$conn->close();

echo json_encode($products);
?>
