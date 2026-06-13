<?php
/* ============================================================
 *  reset_admin.php  —  DISABLED (was a critical vulnerability)
 * ------------------------------------------------------------
 *  ORIGINAL PROBLEM:
 *  This file used to reset the admin password to "1234" for
 *  ANYONE who opened it in a browser — no login required.
 *  That is a complete account takeover with a single click.
 *
 *  FIX:
 *  The dangerous code has been removed. Resetting an admin
 *  password should be done deliberately by a developer, not
 *  through a public web page. Use one of these instead:
 *    - phpMyAdmin to update the users table directly, or
 *    - a command-line script run on the server only.
 * ============================================================ */

http_response_code(404);
die("Not found.");
?>
