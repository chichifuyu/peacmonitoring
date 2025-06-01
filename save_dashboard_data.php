<?php

header('Content-Type: application/json');
require 'db_connect.php';

$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['dashboard_id']) || !isset($input['data'])) {  // Update 'rows' to 'data'
    echo json_encode(["success" => false, "message" => "Invalid input"]);
    exit;
}

$dashboardId = $input['dashboard_id'];
$rows = $input['data'];  // Update 'rows' to 'data'

try {
    $pdo->beginTransaction();

    // (1) Save to dashboard table
    $up = $pdo->prepare("REPLACE INTO dashboard (dashboard_id, dashboard_name) VALUES (?, ?)");
    $up->execute([$dashboardId, $dashboardId]);

    // (2) Delete existing rows in dashboard_rows
    $del = $pdo->prepare("DELETE FROM dashboard_rows WHERE dashboard_id = ?");
    $del->execute([$dashboardId]);

    // (3) Insert new rows
    $fields = [
        'dashboard_id',
        'full_name', 'lrn', 'first_name', 'middle_name',
        'last_name', 'suffix', 'gender', 'birthdate',
        'attendance_status_15_days', 'lis_remarks',
        'attendance_status_30_days', 'billing_remarks_1',
        'peac_remarks', 'billing_remarks_2', 'billing_remarks_3',
        'id_picture_2x2', 'sf9_grade10_report_card_photocopy',
        'psa_birth_certificate_photocopy', 'sf9_grade10_report_card_original',
        'scanning_status_sf9', 'sf10_form137_original'
    ];
    $placeholders = implode(',', array_fill(0, count($fields), '?'));
    $sql = "INSERT INTO dashboard_rows (" . implode(',', $fields) . ") VALUES ($placeholders)";
    $ins = $pdo->prepare($sql);

    foreach ($rows as $row) {
        $values = array_merge([$dashboardId], array_map(function($v) {
            return $v === '' ? null : $v;
        }, $row));
        if (count($values) < count($fields)) {
            $values = array_pad($values, count($fields), null);
        }
        $ins->execute($values);
    }

    $pdo->commit();
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    $pdo->rollBack();
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error saving dashboard: ' . $e->getMessage()
    ]);
}
?>
