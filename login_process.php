<?php
session_start();
require 'db_connect.php';

// Check if form is submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Get user input
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    
    // Validate input
    if (empty($username) || empty($password)) {
        header("Location: login.php?error=Please fill in all fields");
        exit();
    }
    
    // Check if username is email or username
    $isEmail = filter_var($username, FILTER_VALIDATE_EMAIL);
    
    // Prepare query based on input type
    if ($isEmail) {
        $sql = "SELECT id, username, email, password, full_name, role FROM users WHERE email = ?";
    } else {
        $sql = "SELECT id, username, email, password, full_name, role FROM users WHERE username = ?";
    }
    
    // Prepare and execute statement
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();
    
    // Check if user exists
    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();
        
        // Verify password
        if (password_verify($password, $user['password'])) {
            // Set session variables
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['email'] = $user['email'];
            $_SESSION['full_name'] = $user['full_name'];
            $_SESSION['role'] = $user['role'];
            
            // Update last login time
            $updateSql = "UPDATE users SET last_login = NOW() WHERE id = ?";
            $updateStmt = $conn->prepare($updateSql);
            $updateStmt->bind_param("i", $user['id']);
            $updateStmt->execute();
            $updateStmt->close();
            
            // Redirect to dashboard
            header("Location: index.php");
            exit();
        } else {
            // Invalid password
            header("Location: login.php?error=Invalid username or password");
            exit();
        }
    } else {
        // User not found
        header("Location: login.php?error=Invalid username or password");
        exit();
    }
    
    $stmt->close();
} else {
    // If someone tries to access this file directly
    header("Location: login.php");
    exit();
}

$conn->close();
?>
