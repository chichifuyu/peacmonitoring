<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: application/json');
require 'db_connect.php';

session_start();
if (!isset($_SESSION['user_id']) || ($_SESSION['roles'][0] ?? '') !== 'admin') {
    echo json_encode(["success" => false, "message" => "Unauthorized"]);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['dashboard_id'])) {
    echo json_encode(["success" => false, "message" => "Dashboard ID missing"]);
    exit;
}

$dashboardId = $input['dashboard_id'];

try {
    // Delete in the correct order
    $stmt0 = $pdo->prepare("DELETE FROM adviser_sections WHERE dashboard_id = ?");
    $stmt0->execute([$dashboardId]);

    $stmt1 = $pdo->prepare("DELETE FROM dashboard_rows WHERE dashboard_id = ?");
    $stmt1->execute([$dashboardId]);

    $stmt2 = $pdo->prepare("DELETE FROM dashboard WHERE dashboard_id = ?");
    $stmt2->execute([$dashboardId]);

    echo json_encode(["success" => true, "message" => "Dashboard deleted."]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => "Error deleting: " . $e->getMessage()
    ]);
}
?>