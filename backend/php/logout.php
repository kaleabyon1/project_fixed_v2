<?php
// backend/php/logout.php
session_start();

// 1. Destroy all session data (Remove the "Admin Pass")
session_unset();
session_destroy();

// 2. Redirect back to Login Page
header("Location: login.php");
exit();
?>