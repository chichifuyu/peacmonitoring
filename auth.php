<?php

session_start();
require_once __DIR__ . '/db_connect.php';

// Load user roles into session if not already loaded
if (!empty($_SESSION['user_id']) && !isset($_SESSION['roles'])) {
    try {
        $stmt = $pdo->prepare(
            "SELECT r.role_name
             FROM roles r
             JOIN user_roles ur ON r.id = ur.role_id
             WHERE ur.user_id = ?"
        );
        $stmt->execute([$_SESSION['user_id']]);
        $_SESSION['roles'] = $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
    } catch (\PDOException $e) {
        $_SESSION['roles'] = [];
    }
}

function isLoggedIn(): bool {
    return !empty($_SESSION['user_id']);
}

/**
 * Check if user has at least one of the given roles
 */
function hasRole(string|array $requiredRoles): bool {
    if (!isLoggedIn()) {
        return false;
    }

    $userRoles = $_SESSION['roles'] ?? [];

    foreach ((array)$requiredRoles as $role) {
        if (in_array($role, $userRoles, true)) {
            return true;
        }
    }

    return false;
}

/**
 * Enforce that user has at least one of the required roles or block access
 */
function requireRole(string|array $requiredRoles): void {
    if (!hasRole($requiredRoles)) {
        header('HTTP/1.1 403 Forbidden');
        echo '<h1>403 Forbidden</h1>';
        echo '<p>You do not have permission to access this page.</p>';
        exit;
    }
}

/**
 * Helper: for single-role systems, get the user's primary role
 */
function getPrimaryRole(): ?string {
    if (isLoggedIn() && !empty($_SESSION['roles'])) {
        // If using single-role assignment, just return the first role in array
        return $_SESSION['roles'][0];
    }
    return null;
}

/**
 * Check if user is a Grade 11 adviser
 */
function isGrade11Adviser(): bool {
    return hasRole(['class adviser 11', 'admin']);
}

/**
 * Check if user is a Grade 12 adviser
 */
function isGrade12Adviser(): bool {
    return hasRole(['class adviser 12', 'admin']);
}