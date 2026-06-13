<?php
/* SECURITY FIX (CRITICAL — Missing Authentication):
 * The full order list (with totals) was readable by anyone. It is
 * now restricted to logged-in admins only. */
require_once 'auth_check.php';
require_admin_json();

include 'db_connect.php';
header('Content-Type: application/json');

$query = $conn->query("
    SELECT orders.id, orders.status AS status,
           SUM(order_items.quantity * products.price) AS total_price
    FROM orders
    JOIN order_items ON orders.id = order_items.order_id
    JOIN products ON order_items.product_id = products.id
    GROUP BY orders.id
    ORDER BY orders.created_at DESC
");

$orders = [];
if ($query) {
    while ($row = $query->fetch_assoc()) {
        $orders[] = $row;
    }
}

echo json_encode($orders);
$conn->close();
?>
