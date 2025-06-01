<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || ($_SESSION['roles'][0] ?? '') !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Permission denied']);
    exit;
}

require_once 'db_connect.php';
$input = json_decode(file_get_contents('php://input'), true);
$dashboardId = trim($input['dashboard_id'] ?? '');
$gradeLevel = trim($input['grade_level'] ?? '');

if (!$dashboardId) {
    echo json_encode(['success' => false, 'message' => 'Dashboard ID required']);
    exit;
}

try {
    $stmt = $pdo->prepare("INSERT INTO dashboard (dashboard_id, dashboard_name, grade_level) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE dashboard_name=VALUES(dashboard_name), grade_level=VALUES(grade_level)");
    $stmt->execute([$dashboardId, $dashboardId, $gradeLevel]);
    echo json_encode(['success' => true, 'message' => 'Dashboard created']);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>