<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode([
        "success" => false,
        "message" => "Unauthorized: Please login."
    ]);
    exit;
}

require 'db_connect.php';

if (!isset($_GET['id'])) {
    echo json_encode([
        "success" => false,
        "message" => "Missing section ID"
    ]);
    exit;
}

// Accept section_id as string (not int!)
$sectionId = $_GET['id'];
if (empty($sectionId)) {
    echo json_encode([
        "success" => false,
        "message" => "Invalid section ID"
    ]);
    exit;
}

try {
    // Fetch section (dashboard) name and grade_level
    $stmtInfo = $pdo->prepare("SELECT dashboard_name, grade_level FROM dashboard WHERE dashboard_id = ?");
    $stmtInfo->execute([$sectionId]);
    $sectionInfo = $stmtInfo->fetch(PDO::FETCH_ASSOC);

    if (!$sectionInfo) {
        echo json_encode([
            "success" => false,
            "message" => "Section not found."
        ]);
        exit;
    }

    // Fetch section rows
    $stmtRows = $pdo->prepare("SELECT * FROM dashboard_rows WHERE dashboard_id = ?");
    $stmtRows->execute([$sectionId]);
    $rows = $stmtRows->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        "success" => true,
        "dashboard_id" => $sectionId,
        "dashboard_name" => $sectionInfo['dashboard_name'],
        "grade_level"   => $sectionInfo['grade_level'],
        "rows" => $rows
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => "Error fetching section: " . $e->getMessage()
    ]);
}
?>