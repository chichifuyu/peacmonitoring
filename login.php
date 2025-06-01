<?php
session_start();
require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/audit.php';

$error = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';

    try {
        $stmt = $pdo->prepare("SELECT id, username, password_hash FROM users WHERE username = ? AND is_active = 1");
        $stmt->execute([$username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($password, $user['password_hash'])) {
            session_regenerate_id(true);
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];

            // Fetch user roles
            $roleStmt = $pdo->prepare("SELECT r.role_name FROM roles r JOIN user_roles ur ON r.id = ur.role_id WHERE ur.user_id = ?");
            $roleStmt->execute([$user['id']]);
            $_SESSION['roles'] = $roleStmt->fetchAll(PDO::FETCH_COLUMN);

            // Assign primary role for RBAC checks (e.g., use the first role or check for 'admin' or 'peac coordinator')
            if (in_array('admin', $_SESSION['roles'])) {
                $_SESSION['role'] = 'admin';
            } elseif (in_array('peac coordinator', $_SESSION['roles'])) {
                $_SESSION['role'] = 'peac coordinator';
            } else {
                $_SESSION['role'] = $_SESSION['roles'][0] ?? '';
            }

            // Log this login in the audit trail
            log_audit(
                $pdo,
                $_SESSION['user_id'],
                $_SESSION['username'],
                'login',
                'User logged in.'
            );

            // Redirect based on role
                $dashboardRoles = [
                    'assistant principal', 'pod head', 'lis coordinator',
                    'accounting', 'peac coordinator', 'audit', 'registrar office', 'admin',
                    'class adviser 11', 'class adviser 12'
            ];
            // If user has any of the dashboardRoles, redirect to dashboard.php
            if (array_intersect($dashboardRoles, $_SESSION['roles'])) {
                header("Location: dashboard.php");
            } else {
                header("Location: frontpage.php");
            }
            exit;
        } else {
            $error = "Invalid username or password.";
        }
    } catch (PDOException $e) {
        $error = "Database error: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Login</title>
    <link rel="stylesheet" href="login_style.css">
</head>
<body>
    <div class="login-page">
        <div class="form">
            <img src="olivarezlogo.png" alt="Olivarez College Logo" class="logo">
            <h2>Login</h2>
            <?php if (!empty($error)): ?>
                <p class="error-message"><?= htmlspecialchars($error) ?></p>
            <?php endif; ?>
            <form action="login.php" method="post">
                <input type="text" name="username" placeholder="Username" required class="input-field">
                <input type="password" name="password" placeholder="Password" required class="input-field">
                <button type="submit" class="submit-btn">Login</button>
            </form>
        </div>
    </div>
</body>
</html>