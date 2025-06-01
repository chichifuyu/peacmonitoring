<?php
session_start();
var_dump($_SESSION['roles']);
if (
    !isset($_SESSION['user_id']) ||
    (
        !in_array('admin', $_SESSION['roles'] ?? []) &&
        !in_array('peac coordinator', $_SESSION['roles'] ?? [])
    )
) {
    header('Location: login.php');
    exit();
}

define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'peacdb');

$pdo = new PDO(
    'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
    DB_USER,
    DB_PASS,
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

// Handle phase update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['phase_number'])) {
    $phase_number = (int)$_POST['phase_number'];
    $start_date = $_POST['start_date'];
    $end_date = $_POST['end_date'];
    $stmt = $pdo->prepare("UPDATE system_phases SET start_date=?, end_date=? WHERE phase_number=?");
    $stmt->execute([$start_date, $end_date, $phase_number]);
    header("Location: manage_phases.php?success=1");
    exit();
}

// Fetch all phases
$phases = $pdo->query("SELECT * FROM system_phases ORDER BY phase_number ASC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <title>Manage Phases</title>
    <style>
        body { font-family: sans-serif; margin: 2rem; }
        table { border-collapse: collapse; width: 60%; margin: 2rem 0; }
        th, td { border: 1px solid #aaa; padding: 0.5rem 1rem; text-align: center; }
        th { background: #f0f0f0; }
        input[type="date"] { padding: 0.2rem 0.5rem; }
        .success { color: green; margin-bottom: 1rem; }
        .actions { padding: 0.5rem; }
        .back { margin-top: 2rem; display: inline-block; }
        form { margin: 0; }
    </style>
</head>
<body>
    <h1>Manage Phase Dates</h1>
    <?php if (isset($_GET['success'])): ?>
        <div class="success">Phase dates updated successfully.</div>
    <?php endif; ?>
    <table>
        <thead>
            <tr>
                <th>Phase Number</th>
                <th>Start Date</th>
                <th>End Date</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($phases as $phase): ?>
                <tr>
                    <form method="post" action="manage_phases.php">
                        <td><?= htmlspecialchars($phase['phase_number']) ?></td>
                        <td>
                            <input type="date" name="start_date" value="<?= htmlspecialchars($phase['start_date']) ?>" required>
                        </td>
                        <td>
                            <input type="date" name="end_date" value="<?= htmlspecialchars($phase['end_date']) ?>" required>
                        </td>
                        <td class="actions">
                            <input type="hidden" name="phase_number" value="<?= htmlspecialchars($phase['phase_number']) ?>">
                            <button type="submit">Save</button>
                        </td>
                    </form>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <a href="dashboard.php" class="back">← Back to Dashboard</a>
</body>
</html>