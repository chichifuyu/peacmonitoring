<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);

$host = 'localhost';
$db   = 'peacdb';
$user = 'root';
$pass = ''; 

$dsn = "mysql:host=$host;dbname=$db;charset=utf8mb4";

try {
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'DB connection failed: ' . $e->getMessage()
    ]);
    exit;
}
?>