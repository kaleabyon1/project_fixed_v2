<?php
require 'ids.php'; // ids.php already includes db_connect.php via require_once

/* SECURITY FIX (MEDIUM — Open CORS):
 * The old "Access-Control-Allow-Origin: *" let ANY website on the
 * internet call this endpoint. The frontend lives on the same origin,
 * so we restrict it to localhost (change to your real domain in prod). */
header("Access-Control-Allow-Origin: http://localhost");
header("Content-Type: application/json");

// Visitor logging via prepared statement (not SQL-injectable).
$user_ip  = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
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
