<?php
require_once __DIR__ . '/db_connect.php';

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$username = 'markgibaga';
$password = 'shsg113127';
$fullName = 'Mark Anthony Gibaga';
$email    = 'markgibaga@yahoo.com';

// 1. Hash password
$hash = password_hash($password, PASSWORD_BCRYPT);

// 2. Insert user
$stmt = $pdo->prepare(
    "INSERT INTO users (username, password_hash, full_name, email) VALUES (?, ?, ?, ?)"
);
if (!$stmt->execute([$username, $hash, $fullName, $email])) {
    die("User insert failed: " . implode(", ", $stmt->errorInfo()));
}
$userId = $pdo->lastInsertId();

// 3. Assign admin role
$stmt = $pdo->prepare(
    "INSERT INTO user_roles (user_id, role_id)
     SELECT ?, id FROM roles WHERE role_name = 'admin'"
);
if (!$stmt->execute([$userId])) {
    die("Role assignment failed: " . implode(", ", $stmt->errorInfo()));
}

echo "✅ Admin user '{$username}' created with ID {$userId}.";
