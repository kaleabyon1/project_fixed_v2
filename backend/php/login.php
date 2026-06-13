<?php
session_start();
require 'ids.php';
require_once 'csrf.php';

$error = "";

const BF_MAX_FAILS = 5;
const BF_WINDOW    = 900;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    csrf_verify();

    $conn->query("CREATE TABLE IF NOT EXISTS login_attempts (
        ip_address VARCHAR(45) NOT NULL PRIMARY KEY,
        fail_count INT NOT NULL DEFAULT 0,
        first_fail INT NOT NULL DEFAULT 0,
        last_fail  INT NOT NULL DEFAULT 0
    )");

    $now = time();

    $bf = $conn->prepare("SELECT fail_count, first_fail FROM login_attempts WHERE ip_address = ?");
    $bf->bind_param("s", $client_ip);
    $bf->execute();
    $bf_row = $bf->get_result()->fetch_assoc();
    $bf->close();

    $prior_fails = ($bf_row && ($now - (int)$bf_row['first_fail']) <= BF_WINDOW)
        ? (int)$bf_row['fail_count'] : 0;

    $email    = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';

    $stmt = $conn->prepare("SELECT * FROM users WHERE email = ?");

    if ($stmt) {
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        $generic_error = "Invalid email or password.";

        $bad_credentials = false;

        if ($result->num_rows > 0) {
            $user = $result->fetch_assoc();

            if (password_verify($password, $user['password'])) {
                if ($user['role'] === 'admin') {

                    $clr = $conn->prepare("DELETE FROM login_attempts WHERE ip_address = ?");
                    $clr->bind_param("s", $client_ip);
                    $clr->execute();
                    $clr->close();

                    session_regenerate_id(true);

                    $_SESSION['username'] = $user['username'];
                    $_SESSION['role']     = 'admin';
                    header("Location: admin.php");
                    exit();
                } else {

                    $error = "Access denied: this account does not have admin permissions.";
                }
            } else {
                $error = $generic_error;
                $bad_credentials = true;
            }
        } else {
            $error = $generic_error;
            $bad_credentials = true;
        }
        $stmt->close();

        if ($bad_credentials) {
            $new_fails = $prior_fails + 1;
            $first     = ($prior_fails === 0) ? $now : (int)$bf_row['first_fail'];

            $up = $conn->prepare(
                "INSERT INTO login_attempts (ip_address, fail_count, first_fail, last_fail)
                 VALUES (?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE fail_count = VALUES(fail_count),
                                         first_fail = VALUES(first_fail),
                                         last_fail  = VALUES(last_fail)"
            );
            $up->bind_param("siii", $client_ip, $new_fails, $first, $now);
            $up->execute();
            $up->close();

            if (is_whitelisted_ip($client_ip)) {
                $error = $generic_error;
            } else {
                if ($new_fails >= BF_MAX_FAILS) {

                    $clr = $conn->prepare("DELETE FROM login_attempts WHERE ip_address = ?");
                    $clr->bind_param("s", $client_ip);
                    $clr->execute();
                    $clr->close();

                    ban_ip($conn, $client_ip,
                           "Brute force: $new_fails failed admin login attempts",
                           "Brute Force Login");

                }

                $left  = BF_MAX_FAILS - $new_fails;
                $error = "Invalid email or password. "
                       . "($left attempt" . ($left === 1 ? "" : "s") . " left before your IP is blocked.)";
            }
        }
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
            <?php echo csrf_field(); ?>

            <label for="email">Admin Email:</label>
            <input type="email" id="email" name="email" required placeholder="admin@shop.com"><br>

            <label for="password">Password:</label>
            <input type="password" id="password" name="password" required><br>

            <button type="submit">Access Dashboard</button>
        </form>
    </div>
</body>
</html>
