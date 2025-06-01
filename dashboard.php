<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

require_once 'db_connect.php';
require_once 'audit.php';

$username = $_SESSION['username'] ?? 'guest';
$roles = $_SESSION['roles'] ?? [];
$primaryRole = $roles[0] ?? 'user';
$userId = $_SESSION['user_id'];
$current_time = date('Y-m-d H:i:s');

// PHASE LOGIC -- START
function getCurrentPhase($pdo) {
    $today = date('Y-m-d');
    $stmt = $pdo->prepare("SELECT phase_number FROM system_phases WHERE start_date <= ? AND end_date >= ? LIMIT 1");
    $stmt->execute([$today, $today]);
    $row = $stmt->fetch();
    return $row ? (int)$row['phase_number'] : null;
}

$currentPhase = getCurrentPhase($pdo);
// PHASE LOGIC -- END

// Editable fields by role (all possible fields)
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

$editableFieldsByRole = [
    'admin' => $fields,
    'assistant principal' => ['full_name', 'id_picture_2x2', 'sf9_grade10_report_card_photocopy', 'psa_birth_certificate_ap'],
    'class adviser 11' => ['lrn', 'first_name', 'middle_name', 'last_name', 'suffix', 'gender', 'birthdate'],
    'class adviser 12' => ['lrn', 'first_name', 'middle_name', 'last_name', 'suffix', 'gender', 'birthdate'],
    'pod head' => ['attendance_status_15_days', 'attendance_status_30_days'],
    'lis coordinator' => ['lis_remarks_1stsem', 'lis_remarks_2ndsem'],
    'accounting' => ['billing_remarks_1'],
    'peac coordinator' => ['peac_remarks', 'billing_remarks_2'],
    'audit' => ['billing_remarks_3'],
    'registrar office' => ['psa_birth_certificate_registrar', 'sf9_grade10_report_card_original', 'scanning_status_sf9', 'sf10_form137_original']
];

// Load editable fields by phase from DB
$editableFieldsByPhase = [];
$stmt = $pdo->query("SELECT DISTINCT phase_number FROM system_phases");
while ($row = $stmt->fetch()) {
    $pn = (int)$row['phase_number'];
    $fstmt = $pdo->prepare("SELECT field_name FROM phase_editable_fields WHERE phase_number=?");
    $fstmt->execute([$pn]);
    $editableFieldsByPhase[$pn] = array_column($fstmt->fetchAll(), 'field_name');
}
if ($currentPhase !== null && isset($editableFieldsByPhase[$currentPhase])) {
    $fieldsForPhase = $editableFieldsByPhase[$currentPhase];
    $fieldsForRole = $editableFieldsByRole[$primaryRole] ?? [];
    $editableFields = array_values(array_intersect($fieldsForPhase, $fieldsForRole));
} else {
    $editableFields = [];
}

$showRowButtons = !empty($editableFields) && in_array($primaryRole, ['admin', 'peac coordinator', 'class adviser 11', 'class adviser 12']);

function adviserCanAccess($pdo, $userId, $dashboardId) {
    $sql = "SELECT 1 FROM adviser_sections WHERE adviser_user_id = ? AND dashboard_id = ? LIMIT 1";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$userId, $dashboardId]);
    return $stmt->fetchColumn() !== false;
}

// ============= DASHBOARD ADD HANDLER ONLY ================
if (
    isset($_POST['dashboard_action'])
    && ($primaryRole === 'admin' || $primaryRole === 'peac coordinator')
    && $_POST['dashboard_action'] === 'add'
) {
    $dashboardName = trim($_POST['dashboard_name'] ?? '');
    $gradeLevel = strtoupper(trim($_POST['grade_level'] ?? ''));
    $strand = strtoupper(trim($_POST['strand'] ?? ''));

    // Helper: Generate dashboard_id from name, grade, and strand
    function generateDashboardId($dashboardName, $gradeLevel, $strand) {
        $id = strtolower(
            preg_replace('/[^a-z0-9]/i', '', $strand) .
            preg_replace('/[^0-9]/', '', $gradeLevel) .
            preg_replace('/\s+/', '', $dashboardName)
        );
        return $id;
    }

    if ($dashboardName && $gradeLevel && $strand) {
        $dashboardId = generateDashboardId($dashboardName, $gradeLevel, $strand);
        $stmt = $pdo->prepare("INSERT INTO dashboard (dashboard_id, dashboard_name, grade_level, strand) VALUES (?, ?, ?, ?)");
        $stmt->execute([$dashboardId, $dashboardName, $gradeLevel, $strand]);
        // Log dashboard creation
        log_audit($pdo, $userId, $username, 'add_dashboard', 'Added dashboard: ' . $dashboardName);
        header("Location: dashboard.php?dashboard_added=1");
        exit;
    }
}
// ============= END DASHBOARD ADD HANDLER ===============

if (isset($_GET['action'])) {
    header('Content-Type: application/json');
    $action = $_GET['action'];

    try {
        if ($action === 'getDashboards') {
            $dashboards = [];
            if ($primaryRole === 'class adviser 11' || $primaryRole === 'class adviser 12') {
                $sql = "SELECT d.dashboard_id, d.dashboard_name, d.grade_level, d.strand
                        FROM dashboard d
                        JOIN adviser_sections a ON d.dashboard_id = a.dashboard_id
                        WHERE a.adviser_user_id = ?
                        ORDER BY d.grade_level, d.strand, d.dashboard_name";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$userId]);
            } else {
                $sql = "SELECT dashboard_id, dashboard_name, grade_level, strand FROM dashboard ORDER BY grade_level, strand, dashboard_name";
                $stmt = $pdo->query($sql);
            }
            while ($row = $stmt->fetch()) {
                $dashboards[] = $row;
            }
            echo json_encode([
                'success' => true,
                'dashboards' => $dashboards,
                'timestamp' => $current_time,
                'user' => $username
            ]);
            exit;
        }

        if ($action === 'loadData') {
            $dashboardId = $_GET['dashboard_id'] ?? null;
            if (!$dashboardId) {
                throw new Exception("Missing dashboard_id parameter");
            }
            if ($primaryRole === 'class adviser 11' || $primaryRole === 'class adviser 12') {
                if (!adviserCanAccess($pdo, $userId, $dashboardId)) {
                    throw new Exception("Access denied: You are not assigned to this section.");
                }
            }
            $sql = "SELECT * FROM dashboard_rows WHERE dashboard_id = ? ORDER BY id";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$dashboardId]);
            $data = [];
            while ($row = $stmt->fetch()) {
                $data[] = $row;
            }
            echo json_encode([
                'success' => true,
                'data' => $data,
                'timestamp' => $current_time,
                'user' => $username
            ]);
            exit;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'saveData') {
            $input = json_decode(file_get_contents('php://input'), true);
            if (!isset($input['data']) || !isset($input['dashboard_id'])) {
                throw new Exception("Missing data or dashboard_id");
            }
            $data = $input['data'];
            $dashboardId = $input['dashboard_id'];

            if ($primaryRole === 'class adviser 11' || $primaryRole === 'class adviser 12') {
                if (!adviserCanAccess($pdo, $userId, $dashboardId)) {
                    throw new Exception("Access denied: You are not assigned to this section.");
                }
            }
            if ($currentPhase === null) {
                throw new Exception("Editing is not allowed: No active phase.");
            }

            $pdo->beginTransaction();
            $sqlCheck = "SELECT grade_level, strand, dashboard_name FROM dashboard WHERE dashboard_id = ?";
            $stmtCheck = $pdo->prepare($sqlCheck);
            $stmtCheck->execute([$dashboardId]);
            $rowCheck = $stmtCheck->fetch();
            $gradeNorm = strtoupper(trim($rowCheck['grade_level'] ?? ''));
            $strandNorm = strtoupper(trim($rowCheck['strand'] ?? ''));
            $dashboardName = $rowCheck['dashboard_name'] ?? $dashboardId;

            // Normalize on save as well
            $up = $pdo->prepare("REPLACE INTO dashboard (dashboard_id, dashboard_name, grade_level, strand) VALUES (?, ?, ?, ?)");
            $up->execute([$dashboardId, $dashboardName, $gradeNorm, $strandNorm]);

            $clearSql = "DELETE FROM dashboard_rows WHERE dashboard_id = ?";
            $clearStmt = $pdo->prepare($clearSql);
            if (!$clearStmt->execute([$dashboardId])) {
                throw new Exception("Failed to clear existing data");
            }

            $columns = $fields;
            $columnsList = implode(',', array_map(fn($f) => "`$f`", $columns));
            $placeholders = implode(',', array_fill(0, count($columns), '?'));
            $sql = "INSERT INTO dashboard_rows ($columnsList, dashboard_id) VALUES ($placeholders, ?)";
            $stmt = $pdo->prepare($sql);

            foreach ($data as $row) {
                $values = array_map(fn($field) => $row[$field] ?? '', $fields);
                $values[] = $dashboardId;
                if (!$stmt->execute($values)) {
                    throw new Exception("Insert failed");
                }
            }
            $pdo->commit();

            // --- Audit Trail Logging for Edit ---
            log_audit(
                $pdo,
                $userId,
                $username,
                'edit',
                'Edited dashboard: ' . $dashboardName
            );
            // --- End Audit Logging ---

            echo json_encode([
                'success' => true,
                'message' => 'Data saved successfully',
                'timestamp' => $current_time,
                'user' => $username,
                'rowCount' => count($data)
            ]);
            exit;
        }

    } catch (Exception $e) {
        if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
        error_log("API Error by user $username at $current_time: " . $e->getMessage());
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage(),
            'timestamp' => $current_time,
            'user' => $username
        ]);
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <title>PEAC Monitoring Dashboard</title>
    <link rel="stylesheet" href="dashboard.css" />
    <script>
        window.ALLOWED_GRADE = <?= json_encode(
            $primaryRole === 'class adviser 11' ? '11' : (
                $primaryRole === 'class adviser 12' ? '12' : 'all'
            )
        ) ?>;
        window.CURRENT_PHASE = <?= $currentPhase !== null ? $currentPhase : 'null' ?>;
    </script>
</head>
<body>
<noscript>
    <div style="background: #dc3545; color: white; padding: 1rem; text-align: center;">
        JavaScript is required to run this dashboard.
    </div>
</noscript>

<aside class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <h2>Sections</h2>
        <input type="text" id="sidebarSearchInput" class="sidebar-search" placeholder="Search sections...">
        <?php if ($primaryRole === 'admin' || $primaryRole === 'peac coordinator'): ?>
        <form method="post" style="margin-top:1em; display:flex; flex-direction:column; gap:0.3em;">
            <input type="text" name="dashboard_name" placeholder="New Section Name" required>
            <select name="grade_level" required>
                <option value="">Select Grade Level</option>
                <option value="11">11</option>
                <option value="12">12</option>
            </select>
            <select name="strand" required>
                <option value="">Select Strand</option>
                <option value="STEM">STEM</option>
                <option value="ABM">ABM</option>
                <option value="HUMSS">HUMSS</option>
                <option value="GAS">GAS</option>
                <option value="TVL">TVL</option>
            </select>
            <button type="submit" name="dashboard_action" value="add" style="background:#198754;color:white;border:none;padding:0.4em 1em;border-radius:4px;cursor:pointer;">Add Dashboard</button>
        </form>
        <?php endif; ?>
    </div>
    <nav class="sidebar-nav">
        <ul id="dashboardList">
            <!-- Sidebar is now fully rendered by JS or PHP -->
        </ul>
    </nav>
</aside>
<div class="sidebar-overlay" id="sidebarOverlay"></div>

<header class="top-bar">
    <button id="menuBtn" class="menu-btn">☰ Menu</button>
    <h1 id="pageTitle">PEAC MONITORING</h1>
    <div class="dropdown">
        <button class="settings-btn dropdown-toggle" id="dropdownBtn">
            <?= htmlspecialchars($primaryRole) ?> ▾
        </button>
        <div class="dropdown-menu" id="dropdownMenu">
            <a href="#" id="accountManagementBtn">Profile</a>
            <?php if (in_array($primaryRole, ['admin', 'peac coordinator'])): ?>
                <a href="admin_create_user.php">Create User</a>
                <a href="manage_roles.php">Manage User Roles</a>
                <a href="#" id="managePhaseFieldsBtn">Manage Phases & Fields</a>
                <a href="manage_assignments.php">Assign Adviser Sections</a>
            <?php elseif (in_array($primaryRole, ['assistant principal'])): ?>
                <a href="manage_assignments.php">Assign Adviser Sections</a>
            <?php endif; ?>
            <a href="frontpage.php">Audit Trail</a>
            <a href="logout.php" id="logoutBtn">Logout</a>
        </div>
    </div>
</header>

<main class="main-content">
    <div class="content-area">
        <h2 id="dashboardTitle" style="text-align: center; margin-bottom: 1.5rem;">No Dashboard Selected</h2>
        <div class="table-controls">
            <input type="text" id="tableSearchInput" placeholder="Search table...">
            <?php if (!empty($editableFields)): ?>
                <?php if ($primaryRole === 'admin' || $primaryRole === 'peac coordinator'): ?>
                    <button id="addRowButton">➕ Add Row</button>
                    <button id="deleteRowsButton">🗑️ Delete Selected</button>
                <?php endif; ?>
                <button id="saveChangesButton" type="button" disabled>Save</button>
            <?php endif; ?>
            <button id="printTableBtn" type="button">🖨️ Print Table</button>
        </div>
        <div class="table-wrapper">
            <div id="loadingIndicator" style="display:none; text-align:center; padding:1rem;">Loading data...</div>
            <div id="errorBox" style="display:none; color: var(--danger-color); padding: 1rem;"></div>
            <table>
                <thead>
                    <tr></tr>
                </thead>
                <tbody id="tableBody"></tbody>
            </table>
        </div>
    </div>
</main>

<div class="toast" id="toast"></div>

<?php if (in_array($primaryRole, ['admin', 'peac coordinator'])): ?>
<!-- Combined Modal for Phases and Editable Fields -->
<div id="phaseFieldsModal" class="modal" style="display:none; position:fixed; top:0; left:0; width:100vw; height:100vh; background:rgba(0,0,0,0.5); z-index:9999; align-items:center; justify-content:center;">
  <div style="background:#fff; padding:2em; border-radius:8px; max-width:700px; width:95vw; max-height:90vh; overflow-y:auto; position:relative;">
    <button id="closePhaseFieldsModal" style="position:absolute; top:12px; right:16px; font-size:1.2em;">✖</button>
    <h2>Manage Phases & Editable Fields</h2>
    <div id="phaseFieldsModalContent">Loading...</div>
    <!-- No Add Phase Button -->
  </div>
</div>
<?php endif; ?>

<!-- Account Management Modal -->
<div id="accountManagementModal" class="modal" style="display:none; position:fixed; top:0; left:0; width:100vw; height:100vh; background:rgba(0,0,0,0.5); z-index:9999; align-items:center; justify-content:center;">
  <div style="background:#fff; padding:2em; border-radius:8px; max-width:400px; width:95vw; max-height:90vh; overflow-y:auto; position:relative;">
    <button id="closeAccountManagementModal" style="position:absolute; top:12px; right:16px; font-size:1.2em;">✖</button>
    <h2>Account Management</h2>
    <form id="accountManagementForm">
      <label style="display:block; margin-bottom:1em;">
        Username<br>
        <input type="text" id="amUsername" name="username" required style="width:100%; padding:0.5em;" value="<?= htmlspecialchars($username) ?>">
      </label>
      <label style="display:block; margin-bottom:1em;">
        Current Password<br>
        <input type="password" id="amCurrentPassword" name="current_password" style="width:100%; padding:0.5em;">
      </label>
      <label style="display:block; margin-bottom:1em;">
        New Password<br>
        <input type="password" id="amNewPassword" name="new_password" style="width:100%; padding:0.5em;">
      </label>
      <label style="display:block; margin-bottom:1em;">
        Confirm New Password<br>
        <input type="password" id="amConfirmPassword" name="confirm_password" style="width:100%; padding:0.5em;">
      </label>
      <button type="submit" style="background:#198754;color:white;border:none;padding:0.6em 1.2em;border-radius:4px;cursor:pointer;">Save Changes</button>
      <div id="amFeedback" style="margin-top:1em;color:var(--danger-color);"></div>
    </form>
  </div>
</div>

<script>
    window.DASHBOARD_MODE = <?= !empty($editableFields) && $currentPhase !== null ? "'edit'" : "'view'" ?>;
    window.EDITABLE_FIELDS = <?= json_encode($editableFields) ?>;
    window.userRoles = <?= json_encode($roles) ?>;
    window.currentUser = {
        username: <?= json_encode($username) ?>,
        role: <?= json_encode($primaryRole) ?>,
        timestamp: <?= json_encode($current_time) ?>
    };
    window.SHOW_ROW_BUTTONS = <?= $showRowButtons ? 'true' : 'false' ?>;
</script>
<script src="dashboard.js"></script>
</body>
</html>