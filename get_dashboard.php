<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: application/json');
require 'db_connect.php'; // Ensure this path is correct for your project

session_start();
$userId = $_SESSION['user_id'] ?? null;
$roles = $_SESSION['roles'] ?? [];
$primaryRole = $roles[0] ?? '';

try {
    if (!$userId) {
        echo json_encode([
            "success" => false,
            "message" => "Not authenticated."
        ]);
        exit;
    }

    if ($primaryRole === 'class adviser 11' || $primaryRole === 'class adviser 12') {
        // Only show dashboards assigned to this adviser (sections assigned in adviser_sections)
        $stmt = $pdo->prepare("SELECT d.dashboard_id, d.dashboard_name, d.grade_level 
            FROM dashboard d
            INNER JOIN adviser_sections a ON d.dashboard_id = a.dashboard_id
            WHERE a.adviser_user_id = ?
            ORDER BY d.grade_level, d.dashboard_name");
        $stmt->execute([$userId]);
    } else {
        // Admin and others: all dashboards
        $stmt = $pdo->query("SELECT dashboard_id, dashboard_name, grade_level FROM dashboard ORDER BY grade_level, dashboard_name");
    }

    $dashboards = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if ($dashboards) {
        echo json_encode([
            "success" => true,
            "dashboards" => $dashboards
        ]);
    } else {
        echo json_encode([
            "success" => false,
            "message" => "No dashboards found."
        ]);
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => "Error fetching dashboards: " . $e->getMessage()
    ]);
}

?>