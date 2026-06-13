<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function is_admin(): bool {
    return isset($_SESSION['username'])
        && isset($_SESSION['role'])
        && $_SESSION['role'] === 'admin';
}

function require_admin(): void {
    if (!is_admin()) {
        header("Location: login.php");
        exit();
    }
}

function require_admin_json(): void {
    if (!is_admin()) {
        header("Content-Type: application/json");
        http_response_code(403);
        echo json_encode([
            "status"  => "error",
            "message" => "Access denied. Admin login required."
        ]);
        exit();
    }
}
?>
