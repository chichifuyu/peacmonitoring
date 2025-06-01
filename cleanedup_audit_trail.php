<?php
// Run this script with PHP CLI or via cron every month
require_once __DIR__ . '/db_connect.php';

// Delete audit logs older than 1 month
$sql = "DELETE FROM audit_trail WHERE created_at < DATE_SUB(NOW(), INTERVAL 1 MONTH)";
$stmt = $pdo->prepare($sql);
$stmt->execute();

echo "Audit trail cleaned up: " . $stmt->rowCount() . " rows deleted.\n";
?>