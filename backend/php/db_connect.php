<?php
/* ============================================================
 *  db_connect.php  —  Database connection
 * ------------------------------------------------------------
 *  SECURITY FIX (was: Information Disclosure):
 *  The old version sent the raw MySQL error ($conn->connect_error)
 *  back to the browser. That can leak the server version, socket
 *  path, and other internals to an attacker. We now log the real
 *  detail server-side and show the user a generic message only.
 * ============================================================ */

$servername = "127.0.0.1";   // 127.0.0.1 forces TCP — avoids the Windows XAMPP "localhost" hang
$username   = "root";
$password   = "";
$dbname     = "ecommerce";
$port       = 3306;

$conn = new mysqli($servername, $username, $password, $dbname, $port);

if ($conn->connect_error) {
    // Discard any buffered output so we can send a clean response.
    if (ob_get_level()) ob_end_clean();

    // Log the REAL error for developers only (check your PHP error log).
    error_log("DB connection failed: " . $conn->connect_error);

    header("Content-Type: application/json");
    http_response_code(500);

    // Generic message to the user — no internal details leaked.
    die(json_encode([
        "error"   => true,
        "message" => "Database connection failed. Please try again later."
    ]));
}
?>
