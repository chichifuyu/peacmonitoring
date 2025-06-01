<?php
/**
 * Audit Trail Logging Helper
 * 
 * Usage:
 *   require_once 'db_connect.php';
 *   require_once 'audit.php';
 *   log_audit($pdo, $user_id, $username, $action, $description);
 */

/**
 * Logs an action to the audit_trail table.
 * 
 * @param PDO    $pdo         PDO database connection
 * @param int    $user_id     User ID (or null if not available)
 * @param string $username    Username (or empty string if not available)
 * @param string $action      Action type (e.g., 'login', 'edit')
 * @param string $description Description/details of the action
 */
function log_audit($pdo, $user_id, $username, $action, $description) {
    $stmt = $pdo->prepare(
        "INSERT INTO audit_trail (user_id, username, action, description, created_at)
         VALUES (?, ?, ?, ?, NOW())"
    );
    $stmt->execute([
        $user_id,
        $username,
        $action,
        $description
    ]);
}
?>