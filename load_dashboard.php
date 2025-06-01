<?php
session_start();

if (isset($_GET['dashboard_id'])) {
    $_SESSION['dashboard_id'] = $_GET['dashboard_id'];
    header("Location: frontpage.php");
    exit;
} else {
    echo json_encode(['success' => false, 'message' => 'Dashboard ID not received from GET']);
    exit;
}
