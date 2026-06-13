<?php
/* ============================================================
 *  test.php  —  DISABLED (was an information-disclosure leak)
 * ------------------------------------------------------------
 *  ORIGINAL PROBLEM:
 *  This diagnostic page printed the database name, every table
 *  name, and row counts to ANY visitor. That hands an attacker
 *  a free map of your database structure.
 *
 *  FIX:
 *  Diagnostic output must never be public. The code has been
 *  removed. If you need to verify the DB during development,
 *  run a script locally and delete it before deploying.
 * ============================================================ */

http_response_code(404);
die("Not found.");
?>
