<?php
// SECURITY FIX (Session): session_start() must be at the very top,
// before any output or logic — not buried inside the POST block.
session_start();
require_once 'csrf.php';

$error = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    include 'db_connect.php';

    // SECURITY FIX (CSRF): verify token before doing anything.
    csrf_verify();

    $username = trim($_POST['username'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $raw_pw   = $_POST['password'] ?? '';

    // SECURITY FIX (Weak Passwords): enforce a minimum length server-side.
    // Client-side checks can be bypassed, so this must live on the server.
    if (strlen($username) < 1 || strlen($email) < 1) {
        $error = "Username and email are required.";
    } else if (strlen($raw_pw) < 8) {
        $error = "Password must be at least 8 characters long.";
    } else {
        $password = password_hash($raw_pw, PASSWORD_DEFAULT);

        // Prepared statement prevents SQL injection.
        $sql  = "INSERT INTO users (username, email, password, role) VALUES (?, ?, ?, 'user')";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sss", $username, $email, $password);

        if ($stmt->execute()) {
            // New account = new privilege level, so regenerate the session ID.
            session_regenerate_id(true);
            $_SESSION['username'] = $username;
            $_SESSION['role']     = 'user';
            $stmt->close();
            $conn->close();
            header("Location: ../../frontend/index.html");
            exit();
        } else {
            // Don't leak raw DB errors to the user.
            error_log("Register failed: " . $conn->error);
            $error = "Registration failed. That email may already be in use.";
        }
        $stmt->close();
    }
    $conn->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register</title>
    <link rel="stylesheet" href="../../frontend/css/register.css">
</head>
<body>
    <h1>Register</h1>

    <?php if ($error): ?>
        <div style="color:white; background:#dc3545; padding:10px; border-radius:4px; text-align:center; margin-bottom:15px;">
            <?php echo htmlspecialchars($error); ?>
        </div>
    <?php endif; ?>

    <form action="register.php" method="POST">
        <?php echo csrf_field(); // SECURITY FIX (CSRF): hidden token ?>

        <label for="username">Username:</label>
        <input type="text" id="username" name="username" required><br>

        <label for="email">Email:</label>
        <input type="email" id="email" name="email" required><br>

        <label for="password">Password (min 8 characters):</label>
        <input type="password" id="password" name="password" minlength="8" required><br>

        <button type="submit">Register</button>
    </form>
    <br>
    <a href="../../frontend/index.html">Back to Home</a>
</body>
</html>
