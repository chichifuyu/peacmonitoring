<?php
require 'dbconnect_.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = $_POST['username'];
    $password = $_POST['password'];

    $stmt = $conn->prepare("SELECT id, username, password_hash, role FROM users WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows > 0) {
        $stmt->bind_result($id, $username, $hashed_password, $role);
        $stmt->fetch();
        
        if (password_verify($password, $hashed_password)) {
            session_start();
            $_SESSION['user_id'] = $id;
            $_SESSION['username'] = $username;
            $_SESSION['role'] = $role;
            header("Location: peacdashboard.php");
            exit();
        } else {
            $error = "Invalid password.";
        }
    } else {
        $error = "No user found with that username.";
    }
    $stmt->close();
}
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
    <style>
        body {
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            background-color: #eaf5ea;
            font-family: 'Arial', sans-serif;
        }
        .container {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0px 4px 6px rgba(0, 0, 0, 0.1);
            width: 350px;
            text-align: center;
        }
        .container img {
            width: 100px;
            margin-bottom: 15px;
        }
        .container h2 {
            color: #28a745;
            margin-bottom: 20px;
        }
        .container input {
            width: 100%;
            padding: 10px;
            margin: 10px 0;
            border: 1px solid #ccc;
            border-radius: 5px;
        }
        .container button {
            width: 100%;
            padding: 10px;
            background: #28a745;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
        }
        .container button:hover {
            background: #218838;
        }
        .container a {
            display: block;
            margin-top: 10px;
            color: #28a745;
            text-decoration: none;
        }
    </style>
</head>
<body>
    <div class="container">
        <img src="oclogo.jpg" alt="Olivarez College Logo">
        <h2>Login</h2>
        <?php if (isset($error)) echo "<p style='color: red;'>$error</p>"; ?>
        <form action="index.php" method="post">
            <input type="text" name="username" placeholder="Username" required>
            <input type="password" name="password" placeholder="Password" required>
            <button type="submit">Login</button>
            <a href="signup.php">Don't have an account? Sign up</a>
        </form>
    </div>
</body>
</html>
