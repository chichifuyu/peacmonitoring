<?php
require_once 'auth.php';
// Allow admins, or peac coordinators
$userRoles = $_SESSION['roles'] ?? [];
if (!in_array('admin', $userRoles)&& !in_array('peac coordinator', $userRoles)) {
    die("Access denied");
}
require_once 'db_connect.php';

$success = false;
$error = '';

// Fetch roles
$roles = $pdo->query("SELECT id, role_name FROM roles ORDER BY role_name")->fetchAll(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $fullName = $_POST['full_name'] ?? '';
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    $selectedRole = $_POST['roles'] ?? '';

    if (!$username || !$fullName || !$email || !$password || !$selectedRole) {
        $error = "All fields and a role selection are required.";
    } else {
        try {
            $hash = password_hash($password, PASSWORD_BCRYPT);
            $stmt = $pdo->prepare("INSERT INTO users (username, password_hash, full_name, email) VALUES (?, ?, ?, ?)");
            $stmt->execute([$username, $hash, $fullName, $email]);
            $userId = $pdo->lastInsertId();

            $roleStmt = $pdo->prepare("INSERT INTO user_roles (user_id, role_id) VALUES (?, ?)");
            $roleStmt->execute([$userId, (int)$selectedRole]);

            $success = true;
        } catch (PDOException $e) {
            $error = "Error: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Create User</title>
  <link rel="stylesheet" href="dashboard.css">
  <style>
    html {
      height: 100%;
    }

    body {
      margin: 0;
      padding: 0;
      min-height: 100vh;
      font-family: sans-serif;
      background-color: #f5f5f5;
      overflow-y: auto;
    }

    .container {
      max-width: 500px;
      margin: 4rem auto;
      background: #fff;
      padding: 2rem;
      border-radius: 10px;
      box-shadow: 0 4px 10px rgba(0,0,0,0.1);
    }

    h1 {
      text-align: center;
      margin-bottom: 1rem;
    }

    label {
      display: block;
      margin-top: 1rem;
      font-weight: bold;
    }

    input, select {
      width: 100%;
      padding: 0.5rem;
      margin-top: 0.25rem;
      border: 1px solid #ccc;
      border-radius: 6px;
      font-size: 1rem;
    }

    select {
      appearance: none;
      background-color: #fff;
      background-image: url('data:image/svg+xml;utf8,<svg fill="gray" height="24" viewBox="0 0 24 24" width="24" xmlns="http://www.w3.org/2000/svg"><path d="M7 10l5 5 5-5z"/></svg>');
      background-repeat: no-repeat;
      background-position-x: calc(100% - 0.75rem);
      background-position-y: center;
      padding-right: 2rem;
    }

    .alert {
      margin-top: 1rem;
      padding: 0.75rem;
      border-radius: 6px;
    }

    .success {
      background-color: #d4edda;
      color: #155724;
    }

    .error {
      background-color: #f8d7da;
      color: #721c24;
    }

    .btn {
      display: block;
      width: 100%;
      padding: 0.75rem;
      margin-top: 1rem;
      font-size: 1rem;
      border: none;
      border-radius: 6px;
      cursor: pointer;
      text-align: center;
      text-decoration: none;
      box-sizing: border-box;
    }

    .btn-primary {
      background-color: #007bff;
      color: white;
    }

    .btn-primary:hover {
      background-color: #0056b3;
    }

    .btn-secondary {
      background-color: #6c757d;
      color: white;
    }

    .btn-secondary:hover {
      background-color: #5a6268;
    }
  </style>
</head>
<body>
  <div class="container">
    <h1>Create New User</h1>

    <?php if ($success): ?>
      <div class="alert success">✅ User created successfully with assigned role.</div>
    <?php elseif ($error): ?>
      <div class="alert error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST">
      <label for="full_name">Full Name</label>
      <input type="text" name="full_name" id="full_name" required>

      <label for="username">Username</label>
      <input type="text" name="username" id="username" required>

      <label for="email">Email</label>
      <input type="email" name="email" id="email" required>

      <label for="password">Password</label>
      <input type="password" name="password" id="password" required>

      <label for="roles">Assign Role</label>
      <select name="roles" id="roles" required>
        <option value="" disabled selected>Select a role</option>
        <?php foreach ($roles as $role): ?>
          <option value="<?= $role['id'] ?>"><?= htmlspecialchars($role['role_name']) ?></option>
        <?php endforeach; ?>
      </select>

      <button type="submit" class="btn btn-primary">Create User</button>
      <a href="dashboard.php" class="btn btn-secondary">← Back to Dashboard</a>
    </form>
  </div>
</body>
</html>
