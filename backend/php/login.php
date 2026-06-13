<?php
session_start(); // must be at top, before any output
require 'ids.php';
require_once 'csrf.php';

$error = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // SECURITY FIX (CSRF): reject forged cross-site submissions.
    csrf_verify();

    $email    = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';

    $sql  = "SELECT * FROM users WHERE email = ?";
    $stmt = $conn->prepare($sql);

    if ($stmt) {
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        // SECURITY FIX (User Enumeration):
        // The old code said "User not found" vs "Invalid password",
        // which let attackers discover which emails exist. We now use
        // ONE generic message for every failure case.
        $generic_error = "Invalid email or password.";

        if ($result->num_rows > 0) {
            $user = $result->fetch_assoc();

            if (password_verify($password, $user['password'])) {
                if ($user['role'] === 'admin') {

                    // SECURITY FIX (Session Fixation):
                    // Generate a brand-new session ID at the moment of login
                    // so any ID an attacker may have planted becomes useless.
                    session_regenerate_id(true);

                    $_SESSION['username'] = $user['username'];
                    $_SESSION['role']     = 'admin';
                    header("Location: admin.php");
                    exit();
                } else {
                    // Don't reveal that the password was correct for a non-admin.
                    $error = "Access denied: this account does not have admin permissions.";
                }
            } else {
                $error = $generic_error;
            }
        } else {
            $error = $generic_error;
        }
        $stmt->close();
    } else {
        $error = "A server error occurred. Please try again later.";
    }

    $conn->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../../frontend/css/login.css">
    <title>Admin Login</title>
    <style>
        body { border-top: 4px solid #dc3545; }
        h1   { color: #dc3545; }
    </style>
</head>
<body>
    <a href="../../frontend/index.html" class="back-home">← Back to Shop</a>

    <div class="login-container">
        <h1>Admin Panel</h1>

        <?php if ($error): ?>
            <div class="error" style="color:white; background:#dc3545; padding:10px; border-radius:4px; text-align:center; margin-bottom:15px;">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <form action="login.php" method="POST">
            <?php echo csrf_field(); // SECURITY FIX (CSRF): hidden token ?>

            <label for="email">Admin Email:</label>
            <input type="email" id="email" name="email" required placeholder="admin@shop.com"><br>

            <label for="password">Password:</label>
            <input type="password" id="password" name="password" required><br>

            <button type="submit">Access Dashboard</button>
        </form>
    </div>
</body>
</html>
