<?php
// login.php - Unified Login System for Admin and Engineers
session_start();
require_once 'db_connect.php';

$login_error = '';
$login_type = isset($_GET['type']) ? $_GET['type'] : 'admin'; // Default to admin

// Redirect if already logged in
if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    header('Location: admin.php');
    exit;
}

if (isset($_SESSION['engineer_logged_in']) && $_SESSION['engineer_logged_in'] === true) {
    header('Location: engineer_dashboard.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    $login_type = $_POST['login_type'];
    
    if (empty($username) || empty($password)) {
        $login_error = "Please enter both username and password";
    } else {
        if ($login_type === 'admin') {
            // Admin login - Check for hardcoded admin (as per admin.php) or database admin
            if ($username === 'dpwh_admin' && $password === 'admin123') {
                // Hardcoded admin from admin.php
                $_SESSION['admin_logged_in'] = true;
                $_SESSION['admin_username'] = $username;
                $_SESSION['admin_name'] = 'DPWH Administrator';
                $_SESSION['admin_role'] = 'admin';
                header('Location: admin.php');
                exit;
            } else {
                // Check database for admin users
                $sql = "SELECT id, username, password, full_name, email, status 
                        FROM users WHERE username = ? AND role = 'admin'";
                $stmt = $conn->prepare($sql);
                if ($stmt) {
                    $stmt->bind_param("s", $username);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    
                    if ($result->num_rows === 1) {
                        $admin = $result->fetch_assoc();
                        
                        // Verify password
                        if (password_verify($password, $admin['password'])) {
                            // Check if account is active
                            if ($admin['status'] !== 'active') {
                                $login_error = "Your account has been deactivated. Please contact the system administrator.";
                            } else {
                                // Login successful
                                $_SESSION['admin_logged_in'] = true;
                                $_SESSION['admin_id'] = $admin['id'];
                                $_SESSION['admin_username'] = $admin['username'];
                                $_SESSION['admin_name'] = $admin['full_name'];
                                $_SESSION['admin_role'] = 'admin';
                                $_SESSION['admin_email'] = $admin['email'];
                                
                                // Update last login time
                                $update_sql = "UPDATE users SET last_login = NOW() WHERE id = ?";
                                $update_stmt = $conn->prepare($update_sql);
                                if ($update_stmt) {
                                    $update_stmt->bind_param("i", $admin['id']);
                                    $update_stmt->execute();
                                    $update_stmt->close();
                                }
                                
                                header('Location: admin.php');
                                exit;
                            }
                        } else {
                            $login_error = "Invalid username or password";
                        }
                    } else {
                        $login_error = "Invalid username or password";
                    }
                    $stmt->close();
                } else {
                    $login_error = "Database error occurred. Please try again.";
                }
            }
        } else {
            // Engineer login - Exact same logic as engineer_login.php
            $sql = "SELECT id, username, password, full_name, email, specialization, status, assigned_latitude, assigned_longitude, coverage_radius_meters 
                    FROM users WHERE username = ? AND role = 'engineer'";
            $stmt = $conn->prepare($sql);
            
            if (!$stmt) {
                $login_error = "Database error: " . $conn->error;
            } else {
                $stmt->bind_param("s", $username);
                $stmt->execute();
                $result = $stmt->get_result();
                
                if ($result->num_rows === 1) {
                    $engineer = $result->fetch_assoc();
                    
                    // Verify password
                    if (password_verify($password, $engineer['password'])) {
                        // Check if account is active
                        if ($engineer['status'] !== 'active') {
                            $login_error = "Your account has been deactivated. Please contact the administrator.";
                        } else {
                            // Login successful - Same session variables as engineer_login.php
                            $_SESSION['engineer_logged_in'] = true;
                            $_SESSION['engineer_id'] = $engineer['id'];
                            $_SESSION['engineer_username'] = $engineer['username'];
                            $_SESSION['engineer_name'] = $engineer['full_name'];
                            $_SESSION['engineer_role'] = 'engineer';
                            $_SESSION['engineer_specialization'] = $engineer['specialization'];
                            $_SESSION['engineer_email'] = $engineer['email'];
                            $_SESSION['engineer_assigned_lat'] = $engineer['assigned_latitude'];
                            $_SESSION['engineer_assigned_lng'] = $engineer['assigned_longitude'];
                            $_SESSION['engineer_coverage_radius'] = $engineer['coverage_radius_meters'];
                            
                            // Update last login time
                            $update_sql = "UPDATE users SET last_login = NOW() WHERE id = ?";
                            $update_stmt = $conn->prepare($update_sql);
                            if ($update_stmt) {
                                $update_stmt->bind_param("i", $engineer['id']);
                                $update_stmt->execute();
                                $update_stmt->close();
                            }
                            
                            header('Location: engineer_dashboard.php');
                            exit;
                        }
                    } else {
                        $login_error = "Invalid username or password";
                    }
                } else {
                    $login_error = "Invalid username or password";
                }
                $stmt->close();
            }
        }
    }
}

// Function to get demo accounts from database (for display purposes only)
function getDemoAccounts($conn, $role) {
    if (!$conn) return [];
    
    $sql = "SELECT username, full_name, specialization FROM users WHERE role = ? AND status = 'active' ORDER BY created_at DESC LIMIT 5";
    $stmt = $conn->prepare($sql);
    if (!$stmt) return [];
    
    $stmt->bind_param("s", $role);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $accounts = [];
    while ($row = $result->fetch_assoc()) {
        $accounts[] = $row;
    }
    $stmt->close();
    return $accounts;
}

// Get demo accounts for display
$demo_admins = getDemoAccounts($conn, 'admin');
$demo_engineers = getDemoAccounts($conn, 'engineer');
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $login_type === 'admin' ? 'DPWH Admin Login' : 'Engineer Login'; ?> - RoadSense</title>
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

        /* Login Type Tabs */
        .login-type-tabs {
            display: flex;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 15px;
            margin: 1rem 0;
            overflow: hidden;
        }

        .login-type-tab {
            flex: 1;
            padding: 0.75rem 1rem;
            text-align: center;
            background: transparent;
            border: none;
            color: rgba(255, 255, 255, 0.7);
            cursor: pointer;
            transition: all 0.3s ease;
            font-weight: 500;
        }

        .login-type-tab.active {
            background: rgba(255, 255, 255, 0.2);
            color: white;
            transform: scale(1.02);
        }

        .login-type-tab:hover {
            background: rgba(255, 255, 255, 0.15);
            color: white;
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

        .btn-primary:active {
            transform: translateY(0);
        }

        .alert-danger {
            background-color: #fdf2f2;
            border: 1px solid #fecaca;
            color: #991b1b;
            border-radius: 10px;
            padding: 1rem;
            font-size: 0.95rem;
        }

        .alert-danger i {
            margin-right: 0.5rem;
        }

        .demo-info {
            background: linear-gradient(135deg, #e3f2fd 0%, #f3e5f5 100%);
            border: 1px solid #bbdefb;
            border-radius: 10px;
            padding: 1.25rem;
            margin-top: 1.5rem;
            font-size: 0.9rem;
        }

        .demo-info h6 {
            color: #1565c0;
            margin-bottom: 0.75rem;
            font-weight: 600;
        }

        .credential-item {
            background: white;
            padding: 0.75rem;
            border-radius: 6px;
            margin-bottom: 0.75rem;
            font-family: 'Courier New', monospace;
            font-size: 0.85rem;
            cursor: pointer;
            transition: all 0.3s ease;
            border: 1px solid transparent;
        }

        .credential-item:hover {
            transform: translateX(5px);
            border-color: var(--primary);
            background: #f8f9fa;
        }

        .credential-item:last-child {
            margin-bottom: 0;
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

        /* Loading animation */
        .btn-loading {
            position: relative;
            pointer-events: none;
        }

        .btn-loading::after {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 20px;
            height: 20px;
            margin: -10px 0 0 -10px;
            border: 2px solid transparent;
            border-top: 2px solid white;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
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

            .login-type-tab {
                font-size: 0.85rem;
                padding: 0.6rem 0.75rem;
            }
        }

        /* Focus visible for accessibility */
        .btn:focus-visible,
        .form-control:focus-visible {
            outline: 2px solid var(--primary);
            outline-offset: 2px;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-card">
            <div class="login-header">
                <div class="login-header-content">
                    <i class="fas <?php echo $login_type === 'admin' ? 'fa-shield-alt' : 'fa-hard-hat'; ?>"></i>
                    <h3 id="loginTitle">
                        <?php echo $login_type === 'admin' ? 'DPWH Admin Portal' : 'Engineer Portal'; ?>
                    </h3>
                    <p id="loginSubtitle">
                        <?php echo $login_type === 'admin' ? 'Department of Public Works and Highways' : 'Road Infrastructure Management System'; ?>
                    </p>

                    <!-- Login Type Tabs -->
                    <div class="login-type-tabs">
                        <button type="button" class="login-type-tab <?php echo $login_type === 'admin' ? 'active' : ''; ?>" 
                                onclick="switchLoginType('admin')">
                            <i class="fas fa-shield-alt me-1"></i>Admin
                        </button>
                        <button type="button" class="login-type-tab <?php echo $login_type === 'engineer' ? 'active' : ''; ?>" 
                                onclick="switchLoginType('engineer')">
                            <i class="fas fa-hard-hat me-1"></i>Engineer
                        </button>
                    </div>
                </div>
            </div>
            
            <div class="login-body">
                <?php if ($login_error): ?>
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-triangle"></i><?php echo htmlspecialchars($login_error); ?>
                    </div>
                <?php endif; ?>
                
                <form method="POST" id="loginForm">
                    <input type="hidden" name="login_type" id="loginType" value="<?php echo $login_type; ?>">
                    
                    <div class="form-group">
                        <label for="username" class="form-label" id="usernameLabel">
                            <?php echo $login_type === 'admin' ? 'Admin Username' : 'Engineer Username'; ?>
                        </label>
                        <div class="input-group">
                            <span class="input-group-text">
                                <i class="fas fa-user"></i>
                            </span>
                            <input type="text" class="form-control" id="username" name="username" 
                                   placeholder="Enter your username" required autocomplete="username"
                                   value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="password" class="form-label">Password</label>
                        <div class="input-group">
                            <span class="input-group-text">
                                <i class="fas fa-lock"></i>
                            </span>
                            <input type="password" class="form-control" id="password" name="password" 
                                   placeholder="Enter your password" required autocomplete="current-password">
                            <button type="button" class="input-group-text" id="togglePassword" style="cursor: pointer;">
                                <i class="fas fa-eye" id="passwordIcon"></i>
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
                        <i class="fas fa-sign-in-alt me-2"></i>
                        <span id="loginBtnText">
                            <?php echo $login_type === 'admin' ? 'Access Admin Panel' : 'Access Engineer Dashboard'; ?>
                        </span>
                    </button>
                </form>
                
                
                
                <div class="back-link">
                    <a href="index.php">
                        <i class="fas fa-arrow-left me-1"></i>Regular user?
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
            <i class="fas fa-book me-1"></i>User Manual
        </a>
        <a href="#" class="footer-link">
            <i class="fas fa-phone me-1"></i>Contact IT
        </a>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Current login type
        let currentLoginType = '<?php echo $login_type; ?>';

        // Switch between admin and engineer login
        function switchLoginType(type) {
            currentLoginType = type;
            
            // Update URL without refresh
            const newUrl = new URL(window.location);
            newUrl.searchParams.set('type', type);
            window.history.pushState({}, '', newUrl);
            
            // Update form
            document.getElementById('loginType').value = type;
            
            // Update tabs
            document.querySelectorAll('.login-type-tab').forEach(tab => {
                tab.classList.remove('active');
            });
            event.target.classList.add('active');
            
            // Update header content
            const icon = document.querySelector('.login-header i');
            const title = document.getElementById('loginTitle');
            const subtitle = document.getElementById('loginSubtitle');
            const usernameLabel = document.getElementById('usernameLabel');
            const loginBtnText = document.getElementById('loginBtnText');
            const demoTitle = document.getElementById('demoTitle');
            
            if (type === 'admin') {
                icon.className = 'fas fa-shield-alt';
                title.textContent = 'DPWH Admin Portal';
                subtitle.textContent = 'Department of Public Works and Highways';
                usernameLabel.textContent = 'Admin Username';
                loginBtnText.textContent = 'Access Admin Panel';
                demoTitle.textContent = 'Available Admin Accounts';
                
                document.getElementById('adminCredentials').style.display = 'block';
                document.getElementById('engineerCredentials').style.display = 'none';
            } else {
                icon.className = 'fas fa-hard-hat';
                title.textContent = 'Engineer Portal';
                subtitle.textContent = 'Road Infrastructure Management System';
                usernameLabel.textContent = 'Engineer Username';
                loginBtnText.textContent = 'Access Engineer Dashboard';
                demoTitle.textContent = 'Available Engineer Accounts';
                
                document.getElementById('adminCredentials').style.display = 'none';
                document.getElementById('engineerCredentials').style.display = 'block';
            }
            
            // Clear form
            document.getElementById('username').value = '';
            document.getElementById('password').value = '';
            
            // Clear any error messages
            const errorAlert = document.querySelector('.alert-danger');
            if (errorAlert) {
                errorAlert.remove();
            }
            
            // Add animation
            const card = document.querySelector('.login-card');
            card.style.transform = 'scale(0.98)';
            setTimeout(() => {
                card.style.transform = 'scale(1)';
            }, 150);
        }

        // Fill credentials when demo item is clicked (supports both username only and username+password)
        function fillCredentials(username, password) {
            document.getElementById('username').value = username;
            // Only fill password if it's provided
            if (password && password.trim() !== '') {
                document.getElementById('password').value = password;
            }
            
            // Add visual feedback
            const usernameField = document.getElementById('username');
            const passwordField = document.getElementById('password');
            
            usernameField.style.background = '#e8f5e8';
            if (password && password.trim() !== '') {
                passwordField.style.background = '#e8f5e8';
            }
            
            setTimeout(() => {
                usernameField.style.background = '';
                passwordField.style.background = '';
            }, 2000);
            
            // Focus on password field if username was filled and password wasn't
            if (!password || password.trim() === '') {
                document.getElementById('password').focus();
            }
        }

        // Toggle password visibility
        document.getElementById('togglePassword').addEventListener('click', function() {
            const passwordField = document.getElementById('password');
            const passwordIcon = document.getElementById('passwordIcon');
            
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

        // Form submission with loading state
        document.getElementById('loginForm').addEventListener('submit', function() {
            const loginBtn = document.getElementById('loginBtn');
            const originalContent = loginBtn.innerHTML;
            
            loginBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Signing in...';
            loginBtn.classList.add('btn-loading');
            
            // Re-enable button if form validation fails
            setTimeout(() => {
                if (loginBtn.classList.contains('btn-loading')) {
                    loginBtn.innerHTML = originalContent;
                    loginBtn.classList.remove('btn-loading');
                }
            }, 10000);
        });

        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            // Alt + A for Admin, Alt + E for Engineer
            if (e.altKey && e.key.toLowerCase() === 'a') {
                e.preventDefault();
                document.querySelector('.login-type-tab').click();
            } else if (e.altKey && e.key.toLowerCase() === 'e') {
                e.preventDefault();
                document.querySelectorAll('.login-type-tab')[1].click();
            }
        });

        // Focus management for accessibility
        document.addEventListener('DOMContentLoaded', function() {
            // Focus on username field
            document.getElementById('username').focus();
            
            // Add visual feedback for keyboard navigation
            const focusableElements = document.querySelectorAll('input, button, a, [tabindex]');
            
            focusableElements.forEach(element => {
                element.addEventListener('focus', function() {
                    this.style.outline = '2px solid var(--primary)';
                    this.style.outlineOffset = '2px';
                });
                
                element.addEventListener('blur', function() {
                    this.style.outline = '';
                    this.style.outlineOffset = '';
                });
            });
        });

        // Error message animation
        const errorAlert = document.querySelector('.alert-danger');
        if (errorAlert) {
            errorAlert.style.animation = 'shake 0.5s ease-in-out';
            
            // Add shake keyframes
            const style = document.createElement('style');
            style.textContent = `
                @keyframes shake {
                    0%, 100% { transform: translateX(0); }
                    25% { transform: translateX(-5px); }
                    75% { transform: translateX(5px); }
                }
            `;
            document.head.appendChild(style);
        }

        // Tab hover effects
        document.querySelectorAll('.login-type-tab').forEach(tab => {
            tab.addEventListener('mouseenter', function() {
                if (!this.classList.contains('active')) {
                    this.style.transform = 'scale(1.05)';
                }
            });
            
            tab.addEventListener('mouseleave', function() {
                if (!this.classList.contains('active')) {
                    this.style.transform = 'scale(1)';
                }
            });
        });

        // Check URL parameters on load
        document.addEventListener('DOMContentLoaded', function() {
            const urlParams = new URLSearchParams(window.location.search);
            const typeParam = urlParams.get('type');
            
            if (typeParam && typeParam !== currentLoginType) {
                const targetTab = document.querySelector(`.login-type-tab[onclick*="${typeParam}"]`);
                if (targetTab) {
                    targetTab.click();
                }
            }
        });
    </script>
</body>
</html>