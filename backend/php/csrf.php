<?php
/* ============================================================
 *  csrf.php  —  Cross-Site Request Forgery (CSRF) protection
 * ------------------------------------------------------------
 *  WHAT IS CSRF (in one sentence):
 *  A malicious website tricks your logged-in browser into
 *  silently submitting a form to OUR site (e.g. "add product",
 *  "unblock IP") without you knowing.
 *
 *  HOW WE STOP IT:
 *  We put a secret random token inside every form. When the
 *  form comes back, we check the token matches the one stored
 *  in the session. A malicious site cannot read our token, so
 *  its forged request fails the check.
 *
 *  HOW TO USE:
 *   1) require_once __DIR__ . '/csrf.php';
 *   2) Inside each <form>:   echo csrf_field();
 *   3) When handling POST:   csrf_verify();   // stops if invalid
 * ============================================================ */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Returns the current session's CSRF token, creating one if needed.
 */
function csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        // random_bytes() is cryptographically secure (unlike rand()).
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Returns a ready-to-print hidden <input> for embedding in a form.
 */
function csrf_field(): string {
    $token = htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8');
    return '<input type="hidden" name="csrf_token" value="' . $token . '">';
}

/**
 * Verifies the submitted token. If it is missing or wrong, stop everything.
 * hash_equals() is used instead of === to prevent timing attacks.
 */
function csrf_verify(): void {
    $submitted = $_POST['csrf_token'] ?? '';
    if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $submitted)) {
        http_response_code(403);
        die("CSRF validation failed. Please reload the page and try again.");
    }
}
?>
