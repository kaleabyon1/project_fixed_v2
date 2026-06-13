<?php
/* SECURITY FIX (CRITICAL — Missing Authentication):
 * Anyone could previously change any order's status. We now require
 * an admin session before this endpoint will do anything. */
require_once 'auth_check.php';
require_admin_json();

require 'ids.php';            // ids.php already includes db_connect.php
header('Content-Type: application/json');

$data = json_decode(file_get_contents("php://input"), true);

if (!isset($data['order_id']) || !isset($data['status'])) {
    echo json_encode(["status" => "error", "message" => "Missing required fields"]);
    exit;
}

$order_id = (int)$data['order_id'];
$status   = $data['status'];

// Optional but recommended: restrict status to known-good values.
$allowed_statuses = ['pending', 'processing', 'shipped', 'delivered', 'cancelled'];
if (!in_array($status, $allowed_statuses, true)) {
    echo json_encode(["status" => "error", "message" => "Invalid status value"]);
    exit;
}

$stmt = $conn->prepare("UPDATE orders SET status = ? WHERE id = ?");
$stmt->bind_param("si", $status, $order_id);

if ($stmt->execute()) {
    echo json_encode(["status" => "success", "message" => "Order updated successfully"]);
} else {
    echo json_encode(["status" => "error", "message" => "Failed to update order"]);
}

$stmt->close();
$conn->close();
?>
