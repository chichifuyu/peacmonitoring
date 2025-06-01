<?php
session_start();
ini_set('display_errors', 1);
error_reporting(E_ALL);

$userRoles = $_SESSION['roles'] ?? [];
if (!in_array('admin', $userRoles) && !in_array('peac coordinator', $userRoles)) {
    die("Access denied");
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

$fields = [
    'full_name', 'lrn', 'first_name', 'middle_name',
    'last_name', 'suffix', 'gender', 'birthdate',
    'attendance_status_15_days', 'lis_remarks_1stsem',
    'attendance_status_30_days','lis_remarks_2ndsem', 'billing_remarks_1',
    'peac_remarks', 'billing_remarks_2', 'billing_remarks_3',
    'id_picture_2x2', 'sf9_grade10_report_card_photocopy',
    'psa_birth_certificate_ap', 'psa_birth_certificate_registrar',
    'sf9_grade10_report_card_original', 'scanning_status_sf9',
    'sf10_form137_original'
];

// Table for phase info
$pdo->exec("CREATE TABLE IF NOT EXISTS system_phases (
    phase_number INT PRIMARY KEY,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL
)");

// Table for editable fields per phase
$pdo->exec("CREATE TABLE IF NOT EXISTS phase_editable_fields (
    phase_number INT NOT NULL,
    field_name VARCHAR(64) NOT NULL,
    PRIMARY KEY (phase_number, field_name)
)");

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Fetch mapping and phase info
    $map = [];
    $phases = $pdo->query("SELECT * FROM system_phases ORDER BY phase_number ASC")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($phases as $phase) {
        $phaseNum = (int)$phase['phase_number'];
        $rows = $pdo->prepare("SELECT field_name FROM phase_editable_fields WHERE phase_number=?");
        $rows->execute([$phaseNum]);
        $map[$phaseNum] = array_column($rows->fetchAll(), 'field_name');
    }
    echo json_encode([
        'success' => true,
        'phases' => $phases,
        'fields' => $fields,
        'map' => $map
    ]);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);

    // Accept both old (action-based) and new (direct) requests for backward compatibility
    $phase_number = isset($input['phase']) ? intval($input['phase']) : (isset($input['phase_number']) ? intval($input['phase_number']) : 0);
    $start_date = $input['start_date'] ?? '';
    $end_date = $input['end_date'] ?? '';
    $fieldList = $input['fields'] ?? [];

    if ($phase_number && $start_date && $end_date) {
        // Insert or update phase info
        $stmt = $pdo->prepare("INSERT INTO system_phases (phase_number, start_date, end_date) VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE start_date=VALUES(start_date), end_date=VALUES(end_date)");
        $stmt->execute([$phase_number, $start_date, $end_date]);

        // Update field mapping
        $del = $pdo->prepare("DELETE FROM phase_editable_fields WHERE phase_number=?");
        $del->execute([$phase_number]);
        $ins = $pdo->prepare("INSERT INTO phase_editable_fields (phase_number, field_name) VALUES (?, ?)");
        foreach ($fieldList as $f) {
            if (in_array($f, $fields)) $ins->execute([$phase_number, $f]);
        }
        echo json_encode(['success' => true]);
        exit();
    }

    // Legacy support for action
    $action = $input['action'] ?? '';
    if ($action === 'save_phase') {
        $phase_number = intval($input['phase_number']);
        $start_date = $input['start_date'];
        $end_date = $input['end_date'];
        $fieldList = $input['fields'] ?? [];

        if (!$phase_number || !$start_date || !$end_date) {
            echo json_encode(['success' => false, 'message' => 'Invalid phase input']);
            exit();
        }

        // Insert or update phase info
        $stmt = $pdo->prepare("INSERT INTO system_phases (phase_number, start_date, end_date) VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE start_date=VALUES(start_date), end_date=VALUES(end_date)");
        $stmt->execute([$phase_number, $start_date, $end_date]);

        // Update field mapping
        $del = $pdo->prepare("DELETE FROM phase_editable_fields WHERE phase_number=?");
        $del->execute([$phase_number]);
        $ins = $pdo->prepare("INSERT INTO phase_editable_fields (phase_number, field_name) VALUES (?, ?)");
        foreach ($fieldList as $f) {
            if (in_array($f, $fields)) $ins->execute([$phase_number, $f]);
        }
        echo json_encode(['success' => true]);
        exit();
    }

    echo json_encode(['success' => false, 'message' => 'Invalid parameters']);
    exit();
}
?>