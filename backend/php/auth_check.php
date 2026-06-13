<?php
/* ============================================================
 *  auth_check.php  —  Reusable admin authentication guard
 * ------------------------------------------------------------
 *  WHY THIS FILE EXISTS:
 *  Several pages (admin.php, products.php, users.php, etc.)
 *  all need the SAME check: "is this person a logged-in admin?"
 *  Instead of copy-pasting that check into every file (and
 *  risking forgetting it on one page — which was the original
 *  vulnerability), we write it ONCE here and include it.
 *
 *  HOW TO USE (put this at the very top of any admin-only page):
 *      require_once __DIR__ . '/auth_check.php';
 *      require_admin();              // for normal HTML pages
 *  or
 *      require_admin_json();         // for API endpoints that return JSON
 * ============================================================ */

// Make sure a session exists before we read from it.
// session_start() is safe to call once; guard against "already started".
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Returns true only if the current visitor is a logged-in admin.
 */
function is_admin(): bool {
    return isset($_SESSION['username'])
        && isset($_SESSION['role'])
        && $_SESSION['role'] === 'admin';
}

/**
 * For HTML pages: if not an admin, bounce to the login page and stop.
 */
function require_admin(): void {
    if (!is_admin()) {
        header("Location: login.php");
        exit();
    }
}

/**
 * For JSON API endpoints: if not an admin, return a clean JSON 403 and stop.
 */
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
