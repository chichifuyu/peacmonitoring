<?php
session_start();
require_once 'db_connect.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// Allow admins, assistant principals, or peac coordinators
$userRoles = $_SESSION['roles'] ?? [];
if (!in_array('admin', $userRoles) && !in_array('assistant principal', $userRoles) && !in_array('peac coordinator', $userRoles)) {
    die("Access denied");
}

// Fetch all class advisers
$adviserRoles = ['class adviser 11', 'class adviser 12'];
$rolePlaceholders = implode(',', array_fill(0, count($adviserRoles), '?'));

$sql = "SELECT u.id, u.full_name, u.username, r.role_name
        FROM users u
        JOIN user_roles ur ON ur.user_id = u.id
        JOIN roles r ON ur.role_id = r.id
        WHERE r.role_name IN ($rolePlaceholders)
        ORDER BY r.role_name, u.full_name";
$stmt = $pdo->prepare($sql);
$stmt->execute($adviserRoles);
$advisers = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch all dashboards, separate by grade level using flexible matching
$dashboards = $pdo->query("SELECT dashboard_id, dashboard_name, grade_level FROM dashboard ORDER BY grade_level, dashboard_name")
                  ->fetchAll(PDO::FETCH_ASSOC);

$dashboards11 = array_filter($dashboards, fn($d) => strpos(strtolower($d['grade_level']), '11') !== false);
$dashboards12 = array_filter($dashboards, fn($d) => strpos(strtolower($d['grade_level']), '12') !== false);

// Fetch all current assignments
$assignments = [];
$res = $pdo->query("SELECT adviser_user_id, dashboard_id FROM adviser_sections");
while ($row = $res->fetch()) {
    $assignments[$row['adviser_user_id']] = $row['dashboard_id'];
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pdo->beginTransaction();
    try {
        $pdo->exec("DELETE FROM adviser_sections");
        if (isset($_POST['assign']) && is_array($_POST['assign'])) {
            foreach ($_POST['assign'] as $adviserId => $dashboardId) {
                if ($dashboardId !== "") {
                    $ins = $pdo->prepare("INSERT INTO adviser_sections (adviser_user_id, dashboard_id) VALUES (?, ?)");
                    $ins->execute([$adviserId, $dashboardId]);
                }
            }
        }
        $pdo->commit();
        header("Location: manage_assignments.php?saved=1");
        exit;
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = "Failed to save assignments: " . htmlspecialchars($e->getMessage());
    }
}

// Split advisers by grade
$advisers11 = array_filter($advisers, fn($a) => strpos(strtolower($a['role_name']), '11') !== false);
$advisers12 = array_filter($advisers, fn($a) => strpos(strtolower($a['role_name']), '12') !== false);
?>
<!DOCTYPE html>
<html>
<head>
    <title>Manage Adviser Section</title>
    <style>
        body { font-family: Arial, sans-serif; padding:2em; }
        .tab-bar { display: flex; margin-bottom: 1em; }
        .tab-btn {
            padding: 0.6em 2em;
            background: #eee;
            border: none;
            border-bottom: 2px solid #bbb;
            cursor: pointer;
            font-size: 1.1em;
            outline: none;
            transition: background 0.2s;
        }
        .tab-btn.active {
            background: #fff;
            border-bottom: 3px solid #1e90ff;
            font-weight: bold;
        }
        .tab-content { display: none; }
        .tab-content.active { display: block; }
        table { border-collapse: collapse; margin:2em 0; width:100%; min-width: 400px;}
        th, td { border: 1px solid #ccc; padding: 8px; text-align: center;}
        th { background: #f4f4f4; }
        input[type="radio"] { transform: scale(1.2); }
        .success { color: green; }
        .error { color: red; }
        .btn { padding: 0.5em 1.2em; font-size: 1em; background: #1e90ff; color: #fff; border: none; border-radius: 4px; cursor: pointer;}
        .btn:disabled { background: #ccc; }
        .back-btn { background: #444; margin-bottom: 1.5em; margin-right: 1em;}
    </style>
</head>
<body>
    <a href="dashboard.php" class="btn back-btn">&larr; Back to Dashboard</a>
    <h1>Manage Adviser to Section</h1>
    <?php if (isset($_GET['saved'])): ?>
        <div class="success">Assignments saved!</div>
    <?php endif; ?>
    <?php if (!empty($error)): ?>
        <div class="error"><?= $error ?></div>
    <?php endif; ?>

    <div class="tab-bar">
        <button class="tab-btn active" data-tab="grade11">Grade 11</button>
        <button class="tab-btn" data-tab="grade12">Grade 12</button>
    </div>

    <form method="post">
        <!-- Grade 11 Tab -->
        <div class="tab-content active" id="tab-grade11">
            <table>
                <thead>
                    <tr>
                        <th>Adviser Name / Username</th>
                        <th>Role</th>
                        <?php foreach ($dashboards11 as $d): ?>
                            <th><?= htmlspecialchars($d['dashboard_name']) ?><br><small><?= htmlspecialchars($d['grade_level']) ?></small></th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($advisers11 as $adviser): ?>
                        <tr>
                            <td><?= htmlspecialchars($adviser['full_name']) ?><br>
                                <small><?= htmlspecialchars($adviser['username']) ?></small>
                            </td>
                            <td><?= htmlspecialchars($adviser['role_name']) ?></td>
                            <?php foreach ($dashboards11 as $d): ?>
                                <td>
                                    <input type="radio"
                                        name="assign[<?= $adviser['id'] ?>]"
                                        value="<?= htmlspecialchars($d['dashboard_id']) ?>"
                                        <?= (isset($assignments[$adviser['id']]) && $assignments[$adviser['id']] === $d['dashboard_id']) ? 'checked' : '' ?>
                                    >
                                </td>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <!-- Grade 12 Tab -->
        <div class="tab-content" id="tab-grade12">
            <table>
                <thead>
                    <tr>
                        <th>Adviser Name / Username</th>
                        <th>Role</th>
                        <?php foreach ($dashboards12 as $d): ?>
                            <th><?= htmlspecialchars($d['dashboard_name']) ?><br><small><?= htmlspecialchars($d['grade_level']) ?></small></th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($advisers12 as $adviser): ?>
                        <tr>
                            <td><?= htmlspecialchars($adviser['full_name']) ?><br>
                                <small><?= htmlspecialchars($adviser['username']) ?></small>
                            </td>
                            <td><?= htmlspecialchars($adviser['role_name']) ?></td>
                            <?php foreach ($dashboards12 as $d): ?>
                                <td>
                                    <input type="radio"
                                        name="assign[<?= $adviser['id'] ?>]"
                                        value="<?= htmlspecialchars($d['dashboard_id']) ?>"
                                        <?= (isset($assignments[$adviser['id']]) && $assignments[$adviser['id']] === $d['dashboard_id']) ? 'checked' : '' ?>
                                    >
                                </td>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <button class="btn" type="submit">Save Assignments</button>
    </form>
    <script>
        // Tab switching
        document.querySelectorAll('.tab-btn').forEach(function(btn) {
            btn.addEventListener('click', function() {
                document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
                document.querySelectorAll('.tab-content').forEach(tab => tab.classList.remove('active'));
                btn.classList.add('active');
                document.getElementById('tab-' + btn.dataset.tab).classList.add('active');
            });
        });
    </script>
</body>
</html>