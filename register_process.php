<?php
require 'db_connect.php';

// Check if form is submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Get user input
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $full_name = trim($_POST['full_name']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    
    // Validate input
    if (empty($username) || empty($email) || empty($full_name) || empty($password) || empty($confirm_password)) {
        header("Location: register.php?error=Please fill in all fields");
        exit();
    }
    
    // Validate email format
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        header("Location: register.php?error=Invalid email format");
        exit();
    }
    
    // Validate username (alphanumeric and underscore only)
    if (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
        header("Location: register.php?error=Username can only contain letters, numbers, and underscores");
        exit();
    }
    
    // Check username length
    if (strlen($username) < 3 || strlen($username) > 20) {
        header("Location: register.php?error=Username must be between 3 and 20 characters");
        exit();
    }
    
    // Validate password complexity
    if (strlen($password) < 8) {
        header("Location: register.php?error=Password must be at least 8 characters long");
        exit();
    }
    
    if (!preg_match('/[A-Z]/', $password) || !preg_match('/[a-z]/', $password) || !preg_match('/[0-9]/', $password)) {
        header("Location: register.php?error=Password must include uppercase, lowercase, and numbers");
        exit();
    }
    
    // Check if passwords match
    if ($password !== $confirm_password) {
        header("Location: register.php?error=Passwords do not match");
        exit();
    }
    
    // Check if username already exists
    $stmt = $conn->prepare("SELECT id FROM users WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $stmt->store_result();
    
    if ($stmt->num_rows > 0) {
        header("Location: register.php?error=Username already taken");
        exit();
    }
    $stmt->close();
    
    // Check if email already exists
    $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $stmt->store_result();
    
    if ($stmt->num_rows > 0) {
        header("Location: register.php?error=Email already registered");
        exit();
    }
    $stmt->close();
    
    // Hash password
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
    
    // Insert new user
    $stmt = $conn->prepare("INSERT INTO users (username, email, password, full_name) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("ssss", $username, $email, $hashed_password, $full_name);
    
    if ($stmt->execute()) {
        // Registration successful
        header("Location: login.php?success=Account created successfully! You can now login.");
        exit();
    } else {
        // Registration failed
        header("Location: register.php?error=Registration failed. Please try again later.");
        exit();
    }
    
    $stmt->close();
} else {
    // If someone tries to access this file directly
    header("Location: register.php");
    exit();
}

$conn->close();
?>
