<?php

// Include database connection
require 'db_connect.php';

// Authentication Functions
function login($username, $password) {
    global $conn;
    $stmt = $conn->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();
        if (password_verify($password, $user['password_hash'])) {
            session_start();
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['role'] = $user['role'];
            return true;
        }
    }
    return false;
}

function check_role($required_role) {
    session_start();
    if (!isset($_SESSION['role']) || $_SESSION['role'] !== $required_role) {
        header("Location: unauthorized.php");
        exit;
    }
}

// Generate role buttons
function display_role_buttons() {
    echo '<button onclick="window.location.href=\'admin_dashboard.php\'">Admin</button>';
    echo '<button onclick="window.location.href=\'registrar_dashboard.php\'">Registrar</button>';
    echo '<button onclick="window.location.href=\'teacher_dashboard.php\'">Teacher</button>';
}

// CRUD Functions
// Create student
function create_student($data) {
    global $conn;
    $stmt = $conn->prepare("INSERT INTO students (full_name, section, grade_level, gender, birthdate, lrn) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("ssssss", $data['full_name'], $data['section'], $data['grade_level'], $data['gender'], $data['birthdate'], $data['lrn']);
    return $stmt->execute();
}

// Read students
function get_students() {
    global $conn;
    $result = $conn->query("SELECT * FROM students");
    return $result->fetch_all(MYSQLI_ASSOC);
}

// Update student
function update_student($id, $data) {
    global $conn;
    $stmt = $conn->prepare("UPDATE students SET full_name = ?, section = ?, grade_level = ?, gender = ?, birthdate = ?, lrn = ? WHERE id = ?");
    $stmt->bind_param("ssssssi", $data['full_name'], $data['section'], $data['grade_level'], $data['gender'], $data['birthdate'], $data['lrn'], $id);
    return $stmt->execute();
}

// Delete student
function delete_student($id) {
    global $conn;
    $stmt = $conn->prepare("DELETE FROM students WHERE id = ?");
    $stmt->bind_param("i", $id);
    return $stmt->execute();
}

?>
