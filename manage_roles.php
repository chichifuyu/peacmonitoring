<?php
require_once 'auth.php';
// Allow admins, or peac coordinators
$userRoles = $_SESSION['roles'] ?? [];
if (!in_array('admin', $userRoles)&& !in_array('peac coordinator', $userRoles)) {
    die("Access denied");
}

// Use your existing PDO connection
global $pdo;

// Load all users
$users = $pdo->query("SELECT id, username, full_name FROM users ORDER BY full_name")->fetchAll(PDO::FETCH_ASSOC);

// Load all roles, but exclude the old "class adviser" role if it still exists
$roles = $pdo->query("SELECT id, role_name FROM roles WHERE role_name NOT IN ('class adviser') ORDER BY role_name")->fetchAll(PDO::FETCH_ASSOC);

// Map existing user-role pairs
$userRolesStmt = $pdo->query("SELECT user_id, role_id FROM user_roles");
$userRoleMap = [];
foreach ($userRolesStmt as $row) {
    $userRoleMap[$row['user_id']][] = $row['role_id'];
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pdo->beginTransaction();
    try {
        // Loop over all users to ensure roles are updated/cleared for everyone
        foreach ($users as $user) {
            $userId = $user['id'];
            // Clear old roles for this user
            $stmt = $pdo->prepare("DELETE FROM user_roles WHERE user_id = ?");
            $stmt->execute([$userId]);

            // Insert new role if a radio button was selected
            if (!empty($_POST['roles'][$userId])) {
                $roleId = $_POST['roles'][$userId];
                $stmt = $pdo->prepare("INSERT INTO user_roles (user_id, role_id) VALUES (?, ?)");
                $stmt->execute([$userId, $roleId]);
            }
        }
        $pdo->commit();
        header("Location: manage_roles.php?success=1");
        exit;
    } catch (Exception $e) {
        $pdo->rollBack();
        echo "<p style='color: red;'>Error updating roles: " . htmlspecialchars($e->getMessage()) . "</p>";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Manage User Roles</title>
  <style>
    body { font-family: Arial, sans-serif; padding: 2rem; }
    table { border-collapse: collapse; width: 100%; margin-top: 1rem; }
    th, td { border: 1px solid #ccc; padding: 0.5rem; text-align: center; }
    th { background: #007bff; color: white; }
    tr:nth-child(even) { background: #f9f9f9; }
    .btn { padding: 0.5rem 1rem; background: #28a745; color: white; border: none; border-radius: 5px; cursor: pointer; }
    .btn:hover { background: #218838; }
  </style>
</head>
<body>
  <a href="dashboard.php" class="btn" style="background: #6c757d; text-decoration: none;">← Back to Dashboard</a>
  <h1>Manage User Roles</h1>

  <?php if (isset($_GET['success'])): ?>
    <p style="color: green;">Roles updated successfully!</p>
  <?php endif; ?>

  <form method="POST">
    <table>
      <thead>
        <tr>
          <th>User</th>
          <?php foreach ($roles as $role): ?>
            <th><?= htmlspecialchars($role['role_name']) ?></th>
          <?php endforeach; ?>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($users as $user): ?>
          <tr>
            <td><?= htmlspecialchars($user['full_name'] ?? $user['username']) ?></td>
            <?php foreach ($roles as $role): ?>
              <td>
                <input type="radio"
                       name="roles[<?= $user['id'] ?>]"
                       value="<?= $role['id'] ?>"
                       <?= in_array($role['id'], $userRoleMap[$user['id']] ?? []) ? 'checked' : '' ?>>
              </td>
            <?php endforeach; ?>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <br>
    <button type="submit" class="btn">Save Changes</button>
  </form>
</body>
</html>