<?php
session_start();
include 'peacdb.php'; // Use your database connection file

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $full_names = $_POST['full_name'];
    $statuses = $_POST['status'];

    $stmt = $conn->prepare("UPDATE monitoring_data SET full_name=?, status=? WHERE id=?");

    for ($i = 0; $i < count($full_names); $i++) {
        $id = $i + 1; // Modify this logic based on your actual ID system
        $stmt->bind_param("ssi", $full_names[$i], $statuses[$i], $id);
        $stmt->execute();
    }

    $stmt->close();
    $conn->close();

    header("Location: dashboard.php?success=1");
    exit();
}
?>
