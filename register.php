<?php
session_start();

// Redirect to dashboard if already logged in
if (isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

// Check for error messages
$error = isset($_GET['error']) ? $_GET['error'] : '';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - Hazard Reporting System</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Orbitron:wght@400;700&display=swap');

        :root {
            --color-primary: #ff3b3b;
            --color-secondary: #ffae42;
            --color-background: #121212;
            --color-text: #ffffff;
            --color-panel: #1e1e1e;
            --color-border: #ff3b3b;
            --color-hover: #ff5252;
            --color-active: #ff6b6b;
            --color-success: #4CAF50;
            --color-error: #ff3b3b;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Orbitron', sans-serif;
            background-color: var(--color-background);
            color: var(--color-text);
            line-height: 1.6;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            padding: 20px 0;
        }

        .container {
            background: var(--color-panel);
            padding: 30px;
            border-radius: 12px;
            width: 100%;
            max-width: 450px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.3);
            text-align: center;
        }

        .logo {
            margin-bottom: 20px;
            font-size: 2.5rem;
            color: var(--color-primary);
            text-shadow: 0 0 10px rgba(255, 59, 59, 0.5);
        }

        h2 {
            margin-bottom: 20px;
            color: var(--color-text);
        }

        .alert {
            padding: 10px 15px;
            margin-bottom: 20px;
            border-radius: 5px;
            text-align: center;
        }

        .alert-error {
            background-color: rgba(255, 59, 59, 0.2);
            border: 1px solid var(--color-error);
            color: var(--color-error);
        }

        label {
            display: block;
            text-align: left;
            margin: 10px 0 5px;
            font-weight: bold;
        }

        input {
            width: 100%;
            padding: 12px;
            margin-bottom: 15px;
            border: 1px solid #444;
            border-radius: 5px;
            transition: 0.2s ease-in-out;
            background-color: var(--color-background);
            color: var(--color-text);
        }

        input:focus {
            border-color: var(--color-primary);
            outline: none;
            box-shadow: 0 0 5px rgba(255, 59, 59, 0.3);
        }

        button {
            width: 100%;
            padding: 12px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
            transition: 0.3s;
            margin-top: 10px;
            background: var(--color-primary);
            color: var(--color-text);
            font-family: 'Orbitron', sans-serif;
        }

        button:hover {
            background: var(--color-hover);
            transform: translateY(-2px);
        }

        .password-requirements {
            text-align: left;
            margin-bottom: 15px;
            font-size: 0.8rem;
            color: #888;
        }

        .requirement {
            margin-bottom: 5px;
        }

        .form-footer {
            margin-top: 20px;
            font-size: 0.9rem;
            color: #888;
        }

        .form-footer a {
            color: var(--color-secondary);
            text-decoration: none;
            transition: 0.2s;
        }

        .form-footer a:hover {
            color: var(--color-hover);
            text-decoration: underline;
        }

        @media (max-width: 480px) {
            .container {
                width: 90%;
                padding: 20px;
            }
        }
    </style>
</head>
<body>

<div class="container">
    <div class="logo">🚨</div>
    <h2>Create an Account</h2>
    
    <?php if (!empty($error)): ?>
        <div class="alert alert-error">
            <?php echo htmlspecialchars($error); ?>
        </div>
    <?php endif; ?>
    
    <form action="register_process.php" method="POST" id="registerForm">
        <label for="username">Username</label>
        <input type="text" id="username" name="username" required autofocus>
        
        <label for="email">Email</label>
        <input type="email" id="email" name="email" required>
        
        <label for="full_name">Full Name</label>
        <input type="text" id="full_name" name="full_name" required>
        
        <label for="password">Password</label>
        <input type="password" id="password" name="password" required>
        
        <div class="password-requirements">
            <div class="requirement">• At least 8 characters long</div>
            <div class="requirement">• Contains uppercase and lowercase letters</div>
            <div class="requirement">• Contains at least one number</div>
        </div>
        
        <label for="confirm_password">Confirm Password</label>
        <input type="password" id="confirm_password" name="confirm_password" required>
        
        <button type="submit">Register</button>
    </form>
    
    <div class="form-footer">
        Already have an account? <a href="login.php">Login</a>
    </div>
</div>

<script>
document.getElementById('registerForm').addEventListener('submit', function(e) {
    const password = document.getElementById('password').value;
    const confirmPassword = document.getElementById('confirm_password').value;
    
    // Check password length
    if (password.length < 8) {
        e.preventDefault();
        alert('Password must be at least 8 characters long');
        return;
    }
    
    // Check for uppercase, lowercase, and numbers
    if (!/[A-Z]/.test(password) || !/[a-z]/.test(password) || !/[0-9]/.test(password)) {
        e.preventDefault();
        alert('Password must contain uppercase letters, lowercase letters, and numbers');
        return;
    }
    
    // Check if passwords match
    if (password !== confirmPassword) {
        e.preventDefault();
        alert('Passwords do not match');
        return;
    }
});
</script>

</body>
</html>
