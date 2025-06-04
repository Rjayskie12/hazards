<?php
// user_login.php - Regular User Login and Registration
session_start();
require_once 'db_connect.php';

$login_error = '';
$register_error = '';
$register_success = '';
$show_register = isset($_GET['register']) ? true : false;

// Redirect if already logged in
if (isset($_SESSION['user_logged_in']) && $_SESSION['user_role'] === 'user') {
    header('Location: my_reports.php');
    exit;
}

// Handle login
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'login') {
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    
    if (empty($email) || empty($password)) {
        $login_error = "Please enter both email and password";
    } else {
        // Check user credentials
        $sql = "SELECT id, username, email, password, full_name, phone, status 
                FROM users WHERE email = ? AND role = 'user'";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            
            // Verify password
            if (password_verify($password, $user['password'])) {
                // Check if account is active
                if ($user['status'] !== 'active') {
                    $login_error = "Your account has been deactivated. Please contact support.";
                } else {
                    // Login successful
                    $_SESSION['user_logged_in'] = true;
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['user_email'] = $user['email'];
                    $_SESSION['user_name'] = $user['full_name'];
                    $_SESSION['user_role'] = 'user';
                    
                    // Update last login time
                    $update_sql = "UPDATE users SET last_login = NOW() WHERE id = ?";
                    $update_stmt = $conn->prepare($update_sql);
                    $update_stmt->bind_param("i", $user['id']);
                    $update_stmt->execute();
                    
                    header('Location: my_reports.php');
                    exit;
                }
            } else {
                $login_error = "Invalid email or password";
            }
        } else {
            $login_error = "Invalid email or password";
        }
        $stmt->close();
    }
}

// Handle registration
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'register') {
    $full_name = trim($_POST['full_name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    
    // Validation
    if (empty($full_name) || empty($email) || empty($password)) {
        $register_error = "Please fill in all required fields";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $register_error = "Please enter a valid email address";
    } elseif (strlen($password) < 6) {
        $register_error = "Password must be at least 6 characters long";
    } elseif ($password !== $confirm_password) {
        $register_error = "Passwords do not match";
    } else {
        // Check if email already exists
        $check_sql = "SELECT id FROM users WHERE email = ?";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("s", $email);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows > 0) {
            $register_error = "An account with this email already exists";
        } else {
            // Create username from email
            $username = explode('@', $email)[0] . '_' . time();
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            
            // Insert new user
            $insert_sql = "INSERT INTO users (username, email, phone, password, full_name, role, status, created_at) VALUES (?, ?, ?, ?, ?, 'user', 'active', NOW())";
            $insert_stmt = $conn->prepare($insert_sql);
            $insert_stmt->bind_param("sssss", $username, $email, $phone, $hashed_password, $full_name);
            
            if ($insert_stmt->execute()) {
                $register_success = "Account created successfully! You can now login.";
                $show_register = false;
            } else {
                $register_error = "Failed to create account. Please try again.";
            }
            $insert_stmt->close();
        }
        $check_stmt->close();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Login - RoadSense</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        :root {
            --primary: #FF4B4B;
            --primary-dark: #E53935;
            --secondary: #2C3E50;
            --secondary-light: #34495E;
        }

        body {
            background: linear-gradient(135deg, var(--secondary) 0%, var(--secondary-light) 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            position: relative;
        }

        body::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1000 1000"><polygon fill="%23ffffff" fill-opacity="0.02" points="0,1000 1000,0 1000,1000"/></svg>');
            background-size: cover;
        }

        .login-container {
            position: relative;
            z-index: 1;
            width: 100%;
            max-width: 450px;
            margin: 2rem;
        }

        .login-card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.2);
            overflow: hidden;
            animation: slideUp 0.8s ease-out;
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .login-header {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            color: white;
            padding: 2.5rem 2rem;
            text-align: center;
            position: relative;
        }

        .login-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><circle cx="20" cy="20" r="2" fill="white" opacity="0.1"/><circle cx="80" cy="40" r="1" fill="white" opacity="0.1"/><circle cx="40" cy="80" r="1.5" fill="white" opacity="0.1"/></svg>');
        }

        .login-header-content {
            position: relative;
            z-index: 1;
        }

        .login-header i {
            font-size: 4rem;
            margin-bottom: 1rem;
            text-shadow: 0 2px 10px rgba(0, 0, 0, 0.2);
        }

        .login-header h3 {
            margin: 0 0 0.5rem 0;
            font-weight: 700;
            font-size: 1.5rem;
        }

        .login-header p {
            margin: 0;
            opacity: 0.9;
            font-size: 0.95rem;
        }

        .login-body {
            padding: 2.5rem 2rem;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-label {
            font-weight: 600;
            margin-bottom: 0.75rem;
            color: var(--secondary);
            font-size: 0.95rem;
        }

        .input-group-text {
            background-color: #f8f9fa;
            border-color: #dee2e6;
            color: var(--secondary);
        }

        .form-control {
            border-color: #dee2e6;
            padding: 0.875rem 1rem;
            font-size: 1rem;
            transition: all 0.3s ease;
        }

        .form-control:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 0.25rem rgba(255, 75, 75, 0.15);
            transform: translateY(-1px);
        }

        .input-group .form-control {
            border-left: none;
        }

        .input-group .form-control:focus {
            border-left: none;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            border: none;
            padding: 0.875rem 2rem;
            font-weight: 600;
            font-size: 1rem;
            border-radius: 10px;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(255, 75, 75, 0.3);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(255, 75, 75, 0.4);
            background: linear-gradient(135deg, var(--primary-dark) 0%, #d32f2f 100%);
        }

        .btn-outline-primary {
            color: var(--primary);
            border-color: var(--primary);
        }

        .btn-outline-primary:hover {
            background-color: var(--primary);
            border-color: var(--primary);
        }

        .alert-danger {
            background-color: #fdf2f2;
            border: 1px solid #fecaca;
            color: #991b1b;
            border-radius: 10px;
            padding: 1rem;
            font-size: 0.95rem;
        }

        .alert-success {
            background-color: #f0fdf4;
            border: 1px solid #bbf7d0;
            color: #15803d;
            border-radius: 10px;
            padding: 1rem;
            font-size: 0.95rem;
        }

        .tab-buttons {
            display: flex;
            margin-bottom: 2rem;
            background: #f8f9fa;
            border-radius: 10px;
            padding: 0.5rem;
        }

        .tab-button {
            flex: 1;
            background: none;
            border: none;
            padding: 0.75rem;
            border-radius: 8px;
            font-weight: 500;
            transition: all 0.3s ease;
            color: var(--secondary);
        }

        .tab-button.active {
            background: var(--primary);
            color: white;
            box-shadow: 0 2px 10px rgba(255, 75, 75, 0.3);
        }

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
            animation: fadeIn 0.4s ease-in-out;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .back-link {
            text-align: center;
            margin-top: 2rem;
        }

        .back-link a {
            color: #6c757d;
            text-decoration: none;
            font-size: 0.9rem;
            transition: all 0.3s ease;
        }

        .back-link a:hover {
            color: var(--primary);
            transform: translateY(-1px);
        }

        .footer-links {
            position: fixed;
            bottom: 2rem;
            left: 50%;
            transform: translateX(-50%);
            display: flex;
            gap: 2rem;
            z-index: 1;
        }

        .footer-link {
            color: rgba(255, 255, 255, 0.7);
            text-decoration: none;
            font-size: 0.9rem;
            transition: all 0.3s ease;
        }

        .footer-link:hover {
            color: white;
            transform: translateY(-2px);
        }

        /* Password strength indicator */
        .password-strength {
            height: 3px;
            margin-top: 0.5rem;
            border-radius: 2px;
            transition: all 0.3s ease;
        }

        .password-strength.weak {
            background-color: #dc3545;
            width: 33%;
        }

        .password-strength.medium {
            background-color: #ffc107;
            width: 66%;
        }

        .password-strength.strong {
            background-color: #28a745;
            width: 100%;
        }

        /* Responsive adjustments */
        @media (max-width: 576px) {
            .login-container {
                margin: 1rem;
            }
            
            .login-header {
                padding: 2rem 1.5rem;
            }
            
            .login-body {
                padding: 2rem 1.5rem;
            }
            
            .footer-links {
                bottom: 1rem;
                flex-direction: column;
                text-align: center;
                gap: 0.5rem;
            }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-card">
            <div class="login-header">
                <div class="login-header-content">
                    <i class="fas fa-user-circle"></i>
                    <h3>RoadSense User Portal</h3>
                    <p>Track your reports and contribute to road safety</p>
                </div>
            </div>
            
            <div class="login-body">
                <!-- Tab Buttons -->
                <div class="tab-buttons">
                    <button class="tab-button <?php echo !$show_register ? 'active' : ''; ?>" onclick="showTab('login')">
                        <i class="fas fa-sign-in-alt me-1"></i>Login
                    </button>
                    <button class="tab-button <?php echo $show_register ? 'active' : ''; ?>" onclick="showTab('register')">
                        <i class="fas fa-user-plus me-1"></i>Register
                    </button>
                </div>

                <!-- Login Tab -->
                <div class="tab-content <?php echo !$show_register ? 'active' : ''; ?>" id="loginTab">
                    <?php if ($login_error): ?>
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-triangle me-2"></i><?php echo htmlspecialchars($login_error); ?>
                        </div>
                    <?php endif; ?>

                    <?php if ($register_success): ?>
                        <div class="alert alert-success">
                            <i class="fas fa-check-circle me-2"></i><?php echo htmlspecialchars($register_success); ?>
                        </div>
                    <?php endif; ?>
                    
                    <form method="POST" id="loginForm">
                        <input type="hidden" name="action" value="login">
                        
                        <div class="form-group">
                            <label for="loginEmail" class="form-label">Email Address</label>
                            <div class="input-group">
                                <span class="input-group-text">
                                    <i class="fas fa-envelope"></i>
                                </span>
                                <input type="email" class="form-control" id="loginEmail" name="email" 
                                       placeholder="Enter your email" required autocomplete="email"
                                       value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="loginPassword" class="form-label">Password</label>
                            <div class="input-group">
                                <span class="input-group-text">
                                    <i class="fas fa-lock"></i>
                                </span>
                                <input type="password" class="form-control" id="loginPassword" name="password" 
                                       placeholder="Enter your password" required autocomplete="current-password">
                                <button type="button" class="input-group-text" id="toggleLoginPassword" style="cursor: pointer;">
                                    <i class="fas fa-eye" id="loginPasswordIcon"></i>
                                </button>
                            </div>
                        </div>
                        
                        <div class="form-group mb-4">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="rememberMe" name="remember_me">
                                <label class="form-check-label" for="rememberMe">
                                    Remember me on this device
                                </label>
                            </div>
                        </div>
                        
                        <button type="submit" class="btn btn-primary w-100" id="loginBtn">
                            <i class="fas fa-sign-in-alt me-2"></i>Login to My Account
                        </button>
                    </form>
                    
                    <div class="text-center mt-3">
                        <small class="text-muted">
                            Don't have an account? 
                            <a href="#" onclick="showTab('register')" class="text-primary">Create one here</a>
                        </small>
                    </div>
                </div>

                <!-- Register Tab -->
                <div class="tab-content <?php echo $show_register ? 'active' : ''; ?>" id="registerTab">
                    <?php if ($register_error): ?>
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-triangle me-2"></i><?php echo htmlspecialchars($register_error); ?>
                        </div>
                    <?php endif; ?>
                    
                    <form method="POST" id="registerForm">
                        <input type="hidden" name="action" value="register">
                        
                        <div class="form-group">
                            <label for="fullName" class="form-label">Full Name *</label>
                            <div class="input-group">
                                <span class="input-group-text">
                                    <i class="fas fa-user"></i>
                                </span>
                                <input type="text" class="form-control" id="fullName" name="full_name" 
                                       placeholder="Enter your full name" required
                                       value="<?php echo htmlspecialchars($_POST['full_name'] ?? ''); ?>">
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="registerEmail" class="form-label">Email Address *</label>
                            <div class="input-group">
                                <span class="input-group-text">
                                    <i class="fas fa-envelope"></i>
                                </span>
                                <input type="email" class="form-control" id="registerEmail" name="email" 
                                       placeholder="Enter your email" required
                                       value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="phone" class="form-label">Phone Number</label>
                            <div class="input-group">
                                <span class="input-group-text">
                                    <i class="fas fa-phone"></i>
                                </span>
                                <input type="tel" class="form-control" id="phone" name="phone" 
                                       placeholder="Enter your phone number (optional)"
                                       value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>">
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="registerPassword" class="form-label">Password *</label>
                            <div class="input-group">
                                <span class="input-group-text">
                                    <i class="fas fa-lock"></i>
                                </span>
                                <input type="password" class="form-control" id="registerPassword" name="password" 
                                       placeholder="Create a password" required minlength="6"
                                       oninput="checkPasswordStrength()">
                                <button type="button" class="input-group-text" id="toggleRegisterPassword" style="cursor: pointer;">
                                    <i class="fas fa-eye" id="registerPasswordIcon"></i>
                                </button>
                            </div>
                            <div class="password-strength" id="passwordStrength"></div>
                            <small class="text-muted">Password must be at least 6 characters long</small>
                        </div>
                        
                        <div class="form-group">
                            <label for="confirmPassword" class="form-label">Confirm Password *</label>
                            <div class="input-group">
                                <span class="input-group-text">
                                    <i class="fas fa-lock"></i>
                                </span>
                                <input type="password" class="form-control" id="confirmPassword" name="confirm_password" 
                                       placeholder="Confirm your password" required>
                            </div>
                        </div>
                        
                        <div class="form-group mb-4">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="agreeTerms" required>
                                <label class="form-check-label" for="agreeTerms">
                                    I agree to the <a href="#" class="text-primary">Terms of Service</a> and 
                                    <a href="#" class="text-primary">Privacy Policy</a>
                                </label>
                            </div>
                        </div>
                        
                        <button type="submit" class="btn btn-primary w-100" id="registerBtn">
                            <i class="fas fa-user-plus me-2"></i>Create Account
                        </button>
                    </form>
                    
                    <div class="text-center mt-3">
                        <small class="text-muted">
                            Already have an account? 
                            <a href="#" onclick="showTab('login')" class="text-primary">Login here</a>
                        </small>
                    </div>
                </div>

                <div class="back-link">
                    <a href="engineer_login.php">
                        <i class="fas fa-hard-hat me-1"></i>Engineer Login
                    </a>
                    <span class="mx-2">|</span>
                    <a href="login.php">
                        <i class="fas fa-shield-alt me-1"></i>Admin Login
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="footer-links">
        <a href="#" class="footer-link">
            <i class="fas fa-question-circle me-1"></i>Help & Support
        </a>
        <a href="#" class="footer-link">
            <i class="fas fa-shield-alt me-1"></i>Privacy Policy
        </a>
        <a href="#" class="footer-link">
            <i class="fas fa-file-contract me-1"></i>Terms of Service
        </a>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Tab switching
        function showTab(tabName) {
            // Update buttons
            document.querySelectorAll('.tab-button').forEach(btn => btn.classList.remove('active'));
            document.querySelectorAll('.tab-content').forEach(content => content.classList.remove('active'));
            
            // Show selected tab
            if (tabName === 'login') {
                document.querySelector('.tab-button:first-child').classList.add('active');
                document.getElementById('loginTab').classList.add('active');
            } else {
                document.querySelector('.tab-button:last-child').classList.add('active');
                document.getElementById('registerTab').classList.add('active');
            }
        }

        // Password visibility toggles
        document.getElementById('toggleLoginPassword').addEventListener('click', function() {
            const passwordField = document.getElementById('loginPassword');
            const passwordIcon = document.getElementById('loginPasswordIcon');
            
            if (passwordField.type === 'password') {
                passwordField.type = 'text';
                passwordIcon.classList.remove('fa-eye');
                passwordIcon.classList.add('fa-eye-slash');
            } else {
                passwordField.type = 'password';
                passwordIcon.classList.remove('fa-eye-slash');
                passwordIcon.classList.add('fa-eye');
            }
        });

        document.getElementById('toggleRegisterPassword').addEventListener('click', function() {
            const passwordField = document.getElementById('registerPassword');
            const passwordIcon = document.getElementById('registerPasswordIcon');
            
            if (passwordField.type === 'password') {
                passwordField.type = 'text';
                passwordIcon.classList.remove('fa-eye');
                passwordIcon.classList.add('fa-eye-slash');
            } else {
                passwordField.type = 'password';
                passwordIcon.classList.remove('fa-eye-slash');
                passwordIcon.classList.add('fa-eye');
            }
        });

        // Password strength checker
        function checkPasswordStrength() {
            const password = document.getElementById('registerPassword').value;
            const strengthBar = document.getElementById('passwordStrength');
            
            if (password.length === 0) {
                strengthBar.className = 'password-strength';
                return;
            }
            
            let strength = 0;
            
            // Check length
            if (password.length >= 6) strength++;
            if (password.length >= 8) strength++;
            
            // Check for different character types
            if (/[a-z]/.test(password)) strength++;
            if (/[A-Z]/.test(password)) strength++;
            if (/[0-9]/.test(password)) strength++;
            if (/[^A-Za-z0-9]/.test(password)) strength++;
            
            if (strength <= 2) {
                strengthBar.className = 'password-strength weak';
            } else if (strength <= 4) {
                strengthBar.className = 'password-strength medium';
            } else {
                strengthBar.className = 'password-strength strong';
            }
        }

        // Form submission with loading state
        document.getElementById('loginForm').addEventListener('submit', function() {
            const loginBtn = document.getElementById('loginBtn');
            const originalContent = loginBtn.innerHTML;
            
            loginBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Signing in...';
            loginBtn.disabled = true;
            
            // Re-enable button if form validation fails
            setTimeout(() => {
                if (loginBtn.disabled) {
                    loginBtn.innerHTML = originalContent;
                    loginBtn.disabled = false;
                }
            }, 10000);
        });

        document.getElementById('registerForm').addEventListener('submit', function(e) {
            const password = document.getElementById('registerPassword').value;
            const confirmPassword = document.getElementById('confirmPassword').value;
            
            if (password !== confirmPassword) {
                e.preventDefault();
                alert('Passwords do not match!');
                return false;
            }
            
            const registerBtn = document.getElementById('registerBtn');
            const originalContent = registerBtn.innerHTML;
            
            registerBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Creating Account...';
            registerBtn.disabled = true;
            
            // Re-enable button if form validation fails
            setTimeout(() => {
                if (registerBtn.disabled) {
                    registerBtn.innerHTML = originalContent;
                    registerBtn.disabled = false;
                }
            }, 10000);
        });

        // Real-time password confirmation validation
        document.getElementById('confirmPassword').addEventListener('input', function() {
            const password = document.getElementById('registerPassword').value;
            const confirmPassword = this.value;
            
            if (confirmPassword && password !== confirmPassword) {
                this.style.borderColor = '#dc3545';
                this.style.boxShadow = '0 0 0 0.25rem rgba(220, 53, 69, 0.25)';
            } else {
                this.style.borderColor = '#dee2e6';
                this.style.boxShadow = 'none';
            }
        });

        // Focus management for accessibility
        document.addEventListener('DOMContentLoaded', function() {
            // Focus on appropriate field based on active tab
            <?php if ($show_register): ?>
                document.getElementById('fullName').focus();
            <?php else: ?>
                document.getElementById('loginEmail').focus();
            <?php endif; ?>
        });

        // Show register tab if specified in URL
        <?php if ($show_register): ?>
            showTab('register');
        <?php endif; ?>
    </script>
</body>
</html>