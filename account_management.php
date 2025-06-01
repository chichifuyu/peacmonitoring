<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success'=>false, 'message'=>"Not logged in"]);
    exit;
}
$userId = $_SESSION['user_id'];
require_once 'db_connect.php';

$data = json_decode(file_get_contents('php://input'), true);
$username = trim($data['username'] ?? '');
$current_password = $data['current_password'] ?? '';
$new_password = $data['new_password'] ?? '';

if (!$username) {
    echo json_encode(['success'=>false, 'message'=>"Username is required"]);
    exit;
}

try {
    if (!empty($new_password)) {
        // Check current password
        $stmt = $pdo->prepare("SELECT password FROM users WHERE id=?");
        $stmt->execute([$userId]);
        $row = $stmt->fetch();
        if (!$row || !password_verify($current_password, $row['password'])) {
            echo json_encode(['success'=>false, 'message'=>"Current password incorrect"]);
            exit;
        }
        // Update username and password
        $stmt = $pdo->prepare("UPDATE users SET username=?, password=? WHERE id=?");
        $stmt->execute([$username, password_hash($new_password, PASSWORD_DEFAULT), $userId]);
    } else {
        // Only update username
        $stmt = $pdo->prepare("UPDATE users SET username=? WHERE id=?");
        $stmt->execute([$username, $userId]);
    }
    $_SESSION['username'] = $username;
    echo json_encode(['success'=>true]);
} catch (Exception $e) {
    echo json_encode(['success'=>false, 'message'=>"DB error"]);
}