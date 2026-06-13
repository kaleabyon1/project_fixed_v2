<?php

$servername = "127.0.0.1";
$username   = "root";
$password   = "";
$dbname     = "ecommerce";
$port       = 3306;

$conn = new mysqli($servername, $username, $password, $dbname, $port);

if ($conn->connect_error) {

    if (ob_get_level()) ob_end_clean();

    error_log("DB connection failed: " . $conn->connect_error);

    header("Content-Type: application/json");
    http_response_code(500);

    die(json_encode([
        "error"   => true,
        "message" => "Database connection failed. Please try again later."
    ]));
}
?>
