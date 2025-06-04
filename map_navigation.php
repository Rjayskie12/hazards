<?php
// map_navigation.php - Enhanced with Auto Route Creation from Hazard Navigation
// my_reports.php - User Reports Dashboard with Status Tracking and Feedback
session_start();
require_once 'db_connect.php';

// Check if user is logged in
if (!isset($_SESSION['user_logged_in']) || $_SESSION['user_role'] !== 'user') {
    header('Location: index.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'];

// Handle feedback submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'submit_feedback') {
    header('Content-Type: application/json');
    
    $hazard_report_id = intval($_POST['hazard_report_id']);
    $feedback_type = $_POST['feedback_type'];
    $message = trim($_POST['message']);
    $reporter_name = trim($_POST['reporter_name']) ?: 'Anonymous';
    $reporter_contact = trim($_POST['reporter_contact']);
    
    // Validate required fields
    if (!$hazard_report_id || !$feedback_type || !$message) {
        echo json_encode(['success' => false, 'message' => 'Please fill in all required fields.']);
        exit;
    }
    
    // Check if hazard report exists
    $check_sql = "SELECT id FROM hazard_reports WHERE id = ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("i", $hazard_report_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid hazard report.']);
        exit;
    }
    
    // Insert feedback
    $sql = "INSERT INTO feedback_reports (hazard_report_id, feedback_type, message, reporter_name, reporter_contact, status, created_at) 
            VALUES (?, ?, ?, ?, ?, 'pending', NOW())";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("issss", $hazard_report_id, $feedback_type, $message, $reporter_name, $reporter_contact);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Feedback submitted successfully! Thank you for helping improve road safety.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to submit feedback. Please try again.']);
    }
    
    $stmt->close();
    exit;
}

// Get navigation parameters from URL (from hazard map navigation)
$nav_to_lat = isset($_GET['nav_lat']) ? floatval($_GET['nav_lat']) : null;
$nav_to_lng = isset($_GET['nav_lng']) ? floatval($_GET['nav_lng']) : null;
$nav_hazard_id = isset($_GET['nav_hazard_id']) ? intval($_GET['nav_hazard_id']) : null;
$nav_address = isset($_GET['nav_address']) ? htmlspecialchars($_GET['nav_address']) : '';

// Get hazard details if navigating to a specific hazard
$nav_hazard_data = null;
if ($nav_hazard_id) {
    $hazard_sql = "SELECT id, hazard_type, severity, latitude, longitude, address, description, image_path, reported_at, status, resolved, resolved_at 
                   FROM hazard_reports WHERE id = ?";
    $hazard_stmt = $conn->prepare($hazard_sql);
    if ($hazard_stmt) {
        $hazard_stmt->bind_param("i", $nav_hazard_id);
        $hazard_stmt->execute();
        $hazard_result = $hazard_stmt->get_result();
        if ($hazard_result->num_rows > 0) {
            $nav_hazard_data = $hazard_result->fetch_assoc();
        }
        $hazard_stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RoadSense - Live Navigation</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
    <!-- Leaflet CSS -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.3/dist/leaflet.css">
    <!-- Leaflet Routing Machine CSS -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet-routing-machine@3.2.12/dist/leaflet-routing-machine.css">


    <style>
        :root {
            --primary: #FF4B4B;
            --primary-dark: #E53935;
            --primary-light: #FF7676;
            --secondary: #2C3E50;
            --secondary-dark: #1A252F;
            --secondary-light: #34495E;
            --success: #28A745;
            --danger: #DC3545;
            --warning: #FFC107;
            --info: #17A2B8;
            --light: #F8F9FA;
            --dark: #212529;
            --background: #f2f2f2;
            --card-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            --transition: all 0.3s ease;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background-color: var(--background);
            color: var(--secondary);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        h1, h2, h3, h4, h5, h6 {
            font-family: 'Montserrat', sans-serif;
            font-weight: 600;
        }

        /* Navbar Styling */
        .navbar {
            background-color: var(--secondary);
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.15);
            padding: 1rem 2rem;
        }

        .navbar-brand {
            font-family: 'Montserrat', sans-serif;
            font-weight: 700;
            font-size: 1.6rem;
            color: white;
        }

        .navbar-brand span {
            color: var(--primary);
        }

        .navbar-nav .nav-link {
            color: rgba(255, 255, 255, 0.8);
            font-weight: 500;
            font-size: 0.95rem;
            padding: 0.5rem 1rem;
            transition: var(--transition);
        }

        .navbar-nav .nav-link:hover,
        .navbar-nav .nav-link.active {
            color: white;
        }

        .navbar-nav .nav-item.active .nav-link {
            color: var(--primary);
            font-weight: 600;
        }

        /* Secondary Navigation (Tabs) */
        .secondary-nav {
            background-color: var(--secondary-light);
            border-radius: 0;
            padding: 0.5rem 2rem;
        }

        .secondary-nav .nav-link {
            color: rgba(255, 255, 255, 0.7);
            font-weight: 500;
            border-radius: 0;
            padding: 0.6rem 1.2rem;
            margin-right: 0.25rem;
            transition: var(--transition);
        }

        .secondary-nav .nav-link:hover {
            color: white;
            background-color: rgba(255, 255, 255, 0.1);
        }

        .secondary-nav .nav-link.active {
            color: white;
            background-color: var(--primary);
            font-weight: 600;
        }

        /* Main Content Area */
        .main-content {
            flex: 1;
            padding: 2rem;
        }

        /* Cards and Containers */
        .card {
            border: none;
            border-radius: 10px;
            box-shadow: var(--card-shadow);
            transition: var(--transition);
            margin-bottom: 1.5rem;
            overflow: hidden;
        }

        .card:hover {
            box-shadow: 0 6px 15px rgba(0, 0, 0, 0.15);
            transform: translateY(-3px);
        }

        .card-header {
            background-color: white;
            border-bottom: 1px solid rgba(0, 0, 0, 0.05);
            padding: 1.25rem 1.5rem;
            font-weight: 600;
        }

        .card-body {
            padding: 1.5rem;
        }

        /* Buttons */
        .btn {
            border-radius: 6px;
            padding: 0.5rem 1.25rem;
            font-weight: 500;
            transition: var(--transition);
        }

        .btn-primary {
            background-color: var(--primary);
            border-color: var(--primary);
        }

        .btn-primary:hover, .btn-primary:focus {
            background-color: var(--primary-dark);
            border-color: var(--primary-dark);
            box-shadow: 0 4px 10px rgba(229, 57, 53, 0.3);
        }

        .btn-secondary {
            background-color: var(--secondary);
            border-color: var(--secondary);
        }

        .btn-secondary:hover, .btn-secondary:focus {
            background-color: var(--secondary-dark);
            border-color: var(--secondary-dark);
            box-shadow: 0 4px 10px rgba(44, 62, 80, 0.3);
        }

        /* Map-specific styles */
        .map-container {
            height: 70vh;
            width: 100%;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: var(--card-shadow);
            position: relative;
        }

        #map {
            height: 100%;
            width: 100%;
        }

        .filter-sidebar {
            border-radius: 10px;
            box-shadow: var(--card-shadow);
            padding: 1.5rem;
            background-color: white;
        }

        /* Map controls */
        .map-controls {
            position: absolute;
            top: 15px;
            right: 15px;
            z-index: 999;
            background-color: white;
            border-radius: 8px;
            box-shadow: var(--card-shadow);
            padding: 10px;
        }

        .control-item {
            display: flex;
            align-items: center;
            margin-bottom: 8px;
            cursor: pointer;
        }

        .control-item:last-child {
            margin-bottom: 0;
        }

        .control-item label {
            margin-left: 8px;
            margin-bottom: 0;
            font-size: 0.9rem;
            cursor: pointer;
        }

        /* Route controls */
        .route-panel {
            background-color: white;
            border-radius: 10px;
            box-shadow: var(--card-shadow);
            padding: 1.5rem;
            height: 100%;
        }

        .route-panel h3 {
            margin-bottom: 1.5rem;
            color: var(--secondary);
            font-size: 1.4rem;
        }

        .route-input-group {
            position: relative;
            margin-bottom: 1.25rem;
        }

        .route-input-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: var(--secondary);
        }

        .route-input-group .input-with-icon {
            position: relative;
        }

        .route-input-group .input-with-icon input {
            padding-left: 40px;
            padding-right: 40px;
        }

        .route-input-group .input-icon {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--secondary);
            font-size: 1.1rem;
        }

        .route-input-group .location-btn {
            position: absolute;
            right: 5px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: var(--primary);
            font-size: 1.2rem;
            cursor: pointer;
            transition: var(--transition);
        }

        .route-input-group .location-btn:hover {
            color: var(--primary-dark);
        }

        /* Suggestions dropdown */
        .suggestions {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background-color: white;
            border-radius: 0 0 8px 8px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            max-height: 200px;
            overflow-y: auto;
            z-index: 10;
            display: none;
        }

        .suggestion-item {
            padding: 10px 15px;
            cursor: pointer;
            transition: var(--transition);
            font-size: 0.9rem;
        }

        .suggestion-item:hover {
            background-color: var(--light);
        }

        /* Travel modes */
        .travel-modes {
            display: flex;
            margin-bottom: 1.25rem;
            gap: 10px;
        }

        .travel-mode {
            flex: 1;
            padding: 10px;
            text-align: center;
            background-color: var(--light);
            border-radius: 8px;
            cursor: pointer;
            transition: var(--transition);
            font-weight: 500;
        }

        .travel-mode:hover {
            background-color: rgba(255, 75, 75, 0.1);
        }

        .travel-mode.active {
            background-color: var(--primary);
            color: white;
        }

        .travel-mode i {
            font-size: 1.2rem;
            margin-bottom: 5px;
            display: block;
        }

        /* ETA display */
        .eta-display {
            background-color: var(--light);
            border-radius: 8px;
            padding: 15px;
            text-align: center;
            margin-bottom: 1.5rem;
            border-left: 4px solid var(--primary);
        }

        .eta-display .eta-label {
            font-size: 0.9rem;
            color: var(--secondary);
            margin-bottom: 5px;
        }

        .eta-display .eta-value {
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--primary);
        }

        /* Info boxes */
        .info-box {
            background-color: var(--light);
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 1rem;
        }

        .info-box h5 {
            font-size: 1rem;
            margin-bottom: 10px;
            color: var(--secondary);
        }

        .info-box p {
            margin-bottom: 10px;
            font-size: 0.95rem;
        }

        /* Recent routes */
        .recent-routes {
            margin-top: 1.5rem;
        }

        .recent-routes h4 {
            font-size: 1.2rem;
            margin-bottom: 1rem;
            color: var(--secondary);
        }

        .route-item {
            background-color: white;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 10px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.05);
            cursor: pointer;
            transition: var(--transition);
        }

        .route-item:hover {
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
            transform: translateY(-2px);
        }

        .route-item .route-title {
            font-weight: 600;
            font-size: 1rem;
            margin-bottom: 5px;
            color: var(--secondary);
        }

        .route-item .route-meta {
            font-size: 0.85rem;
            color: var(--secondary-light);
        }

        /* Hazard warning */
        .hazard-warning {
            position: absolute;
            bottom: 20px;
            left: 50%;
            transform: translateX(-50%);
            background-color: rgba(220, 53, 69, 0.9);
            color: white;
            padding: 10px 20px;
            border-radius: 30px;
            box-shadow: 0 4px 15px rgba(220, 53, 69, 0.3);
            font-weight: 500;
            z-index: 900;
            display: none;
        }

        .hazard-warning i {
            margin-right: 8px;
        }

        /* Traffic controls */
        .traffic-controls {
            display: flex;
            gap: 10px;
            justify-content: center;
            margin-bottom: 15px;
        }

        .traffic-control-item {
            display: flex;
            align-items: center;
            background-color: white;
            border-radius: 30px;
            padding: 8px 15px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
            transition: var(--transition);
            cursor: pointer;
        }

        .traffic-control-item:hover {
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.15);
        }

        .traffic-control-item i {
            margin-right: 8px;
            color: var(--secondary);
        }

        .traffic-control-item.active {
            background-color: var(--primary);
            color: white;
        }

        .traffic-control-item.active i {
            color: white;
        }

        /* Toast Notifications */
        .toast-container {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 9999;
        }

        .custom-toast {
            background-color: white;
            border-left: 4px solid var(--primary);
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            border-radius: 6px;
            overflow: hidden;
            margin-bottom: 10px;
            min-width: 300px;
            max-width: 350px;
        }

        .toast-header {
            background-color: white;
            border-bottom: 1px solid rgba(0, 0, 0, 0.05);
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0.75rem 1rem;
        }

        .toast-header strong {
            font-size: 1rem;
            color: var(--secondary);
        }

        .toast-body {
            padding: 1rem;
            font-size: 0.9rem;
        }

        /* Loading spinner */
        .spinner-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: rgba(0, 0, 0, 0.5);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 9999;
            display: none;
        }

        /* Notification badge */
        .notification-badge {
            position: absolute;
            top: -8px;
            right: -8px;
            background-color: var(--danger);
            color: white;
            border-radius: 50%;
            width: 18px;
            height: 18px;
            font-size: 0.7rem;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        /* Responsive adjustments */
        @media (max-width: 768px) {
            .navbar {
                padding: 0.75rem 1rem;
            }

            .navbar-brand {
                font-size: 1.4rem;
            }

            .main-content {
                padding: 1rem;
            }

            .map-container {
                height: 50vh;
            }

            .secondary-nav {
                padding: 0.25rem 1rem;
            }

            .secondary-nav .nav-link {
                padding: 0.5rem 0.75rem;
                font-size: 0.9rem;
            }

            .travel-modes {
                flex-wrap: wrap;
            }

            .travel-mode {
                min-width: calc(50% - 5px);
                margin-bottom: 10px;
            }
        }
    </style>
    
    <style>
        /* Feedback Modal Styles */
        .feedback-modal .modal-header {
            background-color: var(--primary);
            color: white;
        }

        .feedback-modal .modal-header .btn-close {
            filter: invert(1);
        }

        .feedback-type-selector {
            display: flex;
            gap: 10px;
            margin-bottom: 1rem;
        }

        .feedback-type-option {
            flex: 1;
            padding: 10px;
            text-align: center;
            background-color: var(--light);
            border: 2px solid transparent;
            border-radius: 8px;
            cursor: pointer;
            transition: var(--transition);
            font-weight: 500;
        }

        .feedback-type-option:hover {
            background-color: rgba(255, 75, 75, 0.1);
            border-color: var(--primary);
        }

        .feedback-type-option.selected {
            background-color: var(--primary);
            color: white;
            border-color: var(--primary-dark);
        }

        .feedback-type-option i {
            font-size: 1.2rem;
            margin-bottom: 5px;
            display: block;
        }

        /* Toast Notifications */
        .toast-container {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 9999;
        }

        .custom-toast {
            background-color: white;
            border-left: 4px solid var(--primary);
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            border-radius: 6px;
            overflow: hidden;
            margin-bottom: 10px;
            min-width: 300px;
            max-width: 350px;
        }

        .toast-header {
            background-color: white;
            border-bottom: 1px solid rgba(0, 0, 0, 0.05);
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0.75rem 1rem;
        }

        .toast-header strong {
            font-size: 1rem;
            color: var(--secondary);
        }

        .toast-body {
            padding: 1rem;
            font-size: 0.9rem;
        }

        /* Loading Spinner */
        .spinner-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: rgba(0, 0, 0, 0.5);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 9999;
            display: none;
        }

        /* Notification badge */
        .notification-badge {
            position: absolute;
            top: -8px;
            right: -8px;
            background-color: var(--danger);
            color: white;
            border-radius: 50%;
            width: 18px;
            height: 18px;
            font-size: 0.7rem;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        /* Responsive adjustments */
        @media (max-width: 768px) {
            .navbar {
                padding: 0.75rem 1rem;
            }

            .navbar-brand {
                font-size: 1.4rem;
            }

            .main-content {
                padding: 1rem;
            }

            .map-container {
                height: 50vh;
            }

            .secondary-nav {
                padding: 0.25rem 1rem;
            }

            .secondary-nav .nav-link {
                padding: 0.5rem 0.75rem;
                font-size: 0.9rem;
            }
            
            .traffic-controls {
                flex-wrap: wrap;
            }
            
            .traffic-control-item {
                font-size: 0.8rem;
                padding: 6px 10px;
            }

            .travel-modes {
                grid-template-columns: repeat(2, 1fr);
            }

            .feedback-type-selector {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <!-- Top Navigation Bar -->
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="index.php"><i class="fas fa-exclamation-triangle me-2"></i>Road<span>Sense</span></a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarMain">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarMain">
                <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                    <li class="nav-item active">
                        <a class="nav-link active" href="map_navigation.php"><i class="fas fa-map-marked-alt me-1"></i> Map</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="my_reports.php"><i class="fas fa-flag me-1"></i> My Reports</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="alerts.php"><i class="fas fa-bell me-1"></i> Alerts</a>
                    </li>
                </ul>
                <div class="d-flex align-items-center">
                    <div class="position-relative me-3">
                        <a href="#" class="btn btn-outline-light btn-sm" id="notificationsBtn">
                            <i class="fas fa-bell"></i>
                            <span class="notification-badge">3</span>
                        </a>
                    </div>
                    <a href="report_hazard.php" class="btn btn-primary">
                        <i class="fas fa-plus-circle me-1"></i> Report Hazard
                    </a>
                    <div class="dropdown ms-3">
                        <button class="btn btn-outline-light btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown">
                            <i class="fas fa-user me-1"></i> <?php echo htmlspecialchars($user_name); ?>
                        </button>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="#"><i class="fas fa-user-cog me-2"></i>Profile Settings</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="logout.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </nav>

    <!-- Secondary Navigation (Tabs) -->
    <ul class="nav nav-tabs secondary-nav">
        <li class="nav-item">
            <a class="nav-link active" href="map_navigation.php">Live Navigation</a>
        </li>
        <li class="nav-item">
            <a class="nav-link" href="traffic_hazard_map.php">Hazards Map</a>
        </li>
        <li class="ms-auto">
            <div class="traffic-controls">
                <div class="traffic-control-item" id="toggleTrafficFlow">
                    <i class="fas fa-traffic-light"></i> Traffic Flow
                </div>
                <div class="traffic-control-item" id="toggleIncidents">
                    <i class="fas fa-car-crash"></i> Incidents
                </div>
                <div class="traffic-control-item" id="findBestRoute">
                    <i class="fas fa-route"></i> Best Route
                </div>
            </div>
        </li>
    </ul>

    <!-- Navigation Alert Banner (when navigating to hazard) -->
    <?php if ($nav_to_lat && $nav_to_lng): ?>
    <div class="alert alert-warning alert-dismissible fade show m-0" id="navigationAlert">
        <div class="container-fluid">
            <div class="d-flex align-items-center">
                <i class="fas fa-route me-2"></i>
                <span>
                    <strong>Navigation Mode:</strong> 
                    Creating route to 
                    <?php if ($nav_hazard_data): ?>
                        <?php echo ucwords(str_replace('_', ' ', $nav_hazard_data['hazard_type'])); ?> 
                        (<?php echo ucfirst($nav_hazard_data['severity']); ?>) at 
                        <?php echo htmlspecialchars($nav_hazard_data['address']); ?>
                    <?php else: ?>
                        <?php echo $nav_address ?: 'selected location'; ?>
                    <?php endif; ?>
                </span>
                <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Main Content Area -->
    <div class="main-content">
        <div class="container-fluid">
            <div class="row">
                <!-- Map Container -->
                <div class="col-lg-8 mb-4">
                    <div class="map-container">
                        <div id="map"></div>
                        
                        <!-- Hazard Warning Alert -->
                        <div class="hazard-warning" id="hazardMessage">
                            <i class="fas fa-exclamation-triangle"></i>
                            <span id="hazardText">Pothole reported 50m ahead</span>
                        </div>
                    </div>
                </div>
                
                <!-- Navigation Controls -->
                <div class="col-lg-4 mb-4">
                    <div class="route-panel">
                        <h3><i class="fas fa-map-signs me-2"></i>Plan Your Route</h3>
                        
                        <!-- Destination Input -->
                        <div class="route-input-group">
                            <label for="destination">Destination</label>
                            <div class="input-with-icon">
                                <i class="fas fa-map-marker-alt input-icon"></i>
                                <input type="text" class="form-control" id="destination" placeholder="Search location..." oninput="searchLocations()" onkeydown="navigateSuggestions(event)">
                                <button class="location-btn" onclick="getCurrentLocation('destination')" title="Use current location">
                                    <i class="fas fa-crosshairs"></i>
                                </button>
                            </div>
                            <div id="suggestions" class="suggestions"></div>
                        </div>
                        
                        <!-- Travel Mode Selection -->
                        <label>Travel Mode</label>
                        <div class="travel-modes">
                            <div class="travel-mode active" data-mode="driving" onclick="setTravelMode('driving')">
                                <i class="fas fa-car"></i>
                                Driving
                            </div>
                            <div class="travel-mode" data-mode="walking" onclick="setTravelMode('walking')">
                                <i class="fas fa-walking"></i>
                                Walking
                            </div>
                            <div class="travel-mode" data-mode="bicycling" onclick="setTravelMode('bicycling')">
                                <i class="fas fa-bicycle"></i>
                                Cycling
                            </div>
                            <div class="travel-mode" data-mode="transit" onclick="setTravelMode('transit')">
                                <i class="fas fa-bus"></i>
                                Transit
                            </div>
                        </div>
                        
                        <!-- Set Destination Button -->
                        <button class="btn btn-primary w-100 mb-4" onclick="setDestination()">
                            <i class="fas fa-search-location me-2"></i>Set Destination
                        </button>
                        
                        <!-- ETA Display -->
                        <div class="eta-display">
                            <div class="eta-label">Estimated Time of Arrival</div>
                            <div class="eta-value" id="eta">N/A</div>
                        </div>
                        
                        <!-- Route Info -->
                        <div class="row">
                            <div class="col-md-6">
                                <div class="info-box">
                                    <h5><i class="fas fa-info-circle me-2"></i>Traffic Status</h5>
                                    <p id="trafficStatus">Loading...</p>
                                    <button class="btn btn-secondary btn-sm w-100" onclick="fetchTrafficData()">
                                        <i class="fas fa-sync-alt me-1"></i>Update
                                    </button>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="info-box">
                                    <h5><i class="fas fa-road me-2"></i>Current Route</h5>
                                    <p id="currentRoute">None selected</p>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Start Navigation Button -->
                        <button id="startNavigation" class="btn btn-primary w-100 mt-3" onclick="startNavigation()">
                            <i class="fas fa-play-circle me-2"></i>Start Navigation
                        </button>
                    </div>
                </div>
            </div>
            
            <!-- Recent Routes Section -->
            <div class="row">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h4 class="mb-0"><i class="fas fa-history me-2"></i>Recent Routes</h4>
                            <div>
                                <button class="btn btn-sm btn-secondary me-2" onclick="saveCurrentRoute()">
                                    <i class="fas fa-save me-1"></i>Save Route
                                </button>
                                <button class="btn btn-sm btn-outline-danger" onclick="clearRecentRoutes()">
                                    <i class="fas fa-trash-alt me-1"></i>Clear All
                                </button>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="row" id="recentRoutes">
                                <!-- Recent routes will be dynamically added here -->
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Feedback Modal -->
   <div class="modal fade feedback-modal" id="feedbackModal" tabindex="-1" aria-labelledby="feedbackModalLabel" aria-hidden="true">
       <div class="modal-dialog modal-lg">
           <div class="modal-content">
               <div class="modal-header">
                   <h5 class="modal-title" id="feedbackModalLabel">
                       <i class="fas fa-comment-dots me-2"></i>Submit Feedback
                   </h5>
                   <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
               </div>
               <div class="modal-body">
                   <form id="feedbackForm">
                       <input type="hidden" id="feedbackHazardId" name="hazard_report_id">
                       
                       <!-- Hazard Information Display -->
                       <div class="alert alert-info mb-4">
                           <h6 class="alert-heading mb-2">
                               <i class="fas fa-info-circle me-2"></i>Hazard Information
                           </h6>
                           <div id="hazardInfo">
                               <strong>Type:</strong> <span id="hazardTypeDisplay">-</span><br>
                               <strong>Severity:</strong> <span id="hazardSeverityDisplay">-</span><br>
                               <strong>Location:</strong> <span id="hazardLocationDisplay">-</span><br>
                               <strong>Reported:</strong> <span id="hazardDateDisplay">-</span>
                           </div>
                       </div>
                       
                       <!-- Feedback Type Selection -->
                       <div class="mb-4">
                           <label class="form-label">
                               <strong>Feedback Type <span class="text-danger">*</span></strong>
                           </label>
                           <input type="hidden" id="feedbackType" name="feedback_type" required>
                           <div class="feedback-type-selector">
                               <div class="feedback-type-option" data-type="status_update">
                                   <i class="fas fa-flag-checkered"></i>
                                   Status Update
                               </div>
                               <div class="feedback-type-option" data-type="location_correction">
                                   <i class="fas fa-map-marker-alt"></i>
                                   Location Fix
                               </div>
                               <div class="feedback-type-option" data-type="additional_info">
                                   <i class="fas fa-info-circle"></i>
                                   Add Details
                               </div>
                               <div class="feedback-type-option" data-type="general_comment">
                                   <i class="fas fa-comment"></i>
                                   Comment
                               </div>
                           </div>
                           <small class="form-text text-muted">
                               Please select the type of feedback you want to provide.
                           </small>
                       </div>
                       
                       <!-- Feedback Message -->
                       <div class="mb-4">
                           <label for="feedbackMessage" class="form-label">
                               <strong>Your Feedback <span class="text-danger">*</span></strong>
                           </label>
                           <textarea class="form-control" id="feedbackMessage" name="message" rows="4" 
                                     placeholder="Please provide detailed feedback about this hazard..." required></textarea>
                           <div class="form-text">
                               <span id="feedbackHint">Tell us about this hazard.</span>
                               <span class="float-end">
                                   <span id="charCount">0</span>/500 characters
                               </span>
                           </div>
                       </div>
                       
                       <!-- Reporter Information (Optional) -->
                       <div class="card bg-light">
                           <div class="card-header bg-transparent">
                               <h6 class="mb-0">
                                   <i class="fas fa-user me-2"></i>Contact Information 
                                   <small class="text-muted">(Optional)</small>
                               </h6>
                           </div>
                           <div class="card-body">
                               <p class="small text-muted mb-3">
                                   Providing your contact information is optional but helps us follow up if needed.
                               </p>
                               <div class="row">
                                   <div class="col-md-6 mb-3">
                                       <label for="reporterName" class="form-label">Your Name</label>
                                       <input type="text" class="form-control" id="reporterName" name="reporter_name" 
                                              placeholder="Enter your name (optional)">
                                   </div>
                                   <div class="col-md-6 mb-3">
                                       <label for="reporterContact" class="form-label">Contact Info</label>
                                       <input type="text" class="form-control" id="reporterContact" name="reporter_contact" 
                                              placeholder="Email or phone (optional)">
                                   </div>
                               </div>
                               <div class="form-check">
                                   <input class="form-check-input" type="checkbox" id="stayAnonymous">
                                   <label class="form-check-label" for="stayAnonymous">
                                       I prefer to remain anonymous
                                   </label>
                               </div>
                           </div>
                       </div>
                   </form>
               </div>
               <div class="modal-footer">
                   <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                       <i class="fas fa-times me-1"></i>Cancel
                   </button>
                   <button type="button" class="btn btn-primary" id="submitFeedbackBtn">
                       <i class="fas fa-paper-plane me-1"></i>Submit Feedback
                   </button>
               </div>
           </div>
       </div>
   </div>

   <!-- Toast Notification Container -->
   <div class="toast-container">
       <!-- Notifications will be dynamically added here -->
   </div>

   <!-- Loading Spinner Overlay -->
   <div class="spinner-overlay" id="loadingSpinner">
       <div class="spinner-border text-light" role="status" style="width: 3rem; height: 3rem;">
           <span class="visually-hidden">Loading...</span>
       </div>
   </div>

   <!-- Footer -->
   <footer class="text-center py-3">
       <div class="container">
           <p class="mb-0">© 2025 RoadSense - Smart Road Hazard Mapping System</p>
       </div>
   </footer>

   <!-- Bootstrap 5 & Popper JS -->
   <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.6/dist/umd/popper.min.js"></script>
   <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.min.js"></script>
   
   <!-- Leaflet JS -->
   <script src="https://unpkg.com/leaflet@1.9.3/dist/leaflet.js"></script>
   
   <!-- Leaflet Routing Machine JS -->
   <script src="https://unpkg.com/leaflet-routing-machine@3.2.12/dist/leaflet-routing-machine.js"></script>
   
   <!-- Navigation Script -->
   <script>
       // TomTom API Key
       const apiKey = "QsG5BtRmhcKECyn9fcSPaDojqAyH440R";
       
       // Global variables
       let userLocation = [14.5995, 120.9842]; // Default: Manila
       let destinationLocation = null;
       let map = null;
       let routeControl = null;
       let markers = [];
       let hazardMarkers = [];
       let trafficLayers = { flow: null, incidents: null };
       let travelMode = 'driving';
       let isNavigating = false;
       let currentHazardId = null;
       let hazardData = [];
       
       // Navigation parameters from PHP
       const navigationParams = {
           lat: <?php echo $nav_to_lat ? $nav_to_lat : 'null'; ?>,
           lng: <?php echo $nav_to_lng ? $nav_to_lng : 'null'; ?>,
           hazardId: <?php echo $nav_hazard_id ? $nav_hazard_id : 'null'; ?>,
           address: <?php echo $nav_address ? '"' . addslashes($nav_address) . '"' : 'null'; ?>,
           hazardData: <?php echo $nav_hazard_data ? json_encode($nav_hazard_data) : 'null'; ?>
       };
       
       // Initialize map when document is loaded
       document.addEventListener('DOMContentLoaded', function() {
           initMap();
           initFeedbackForm();
           
           // Handle automatic navigation if parameters are provided
           if (navigationParams.lat && navigationParams.lng) {
               setTimeout(() => {
                   handleAutoNavigation();
               }, 1000); // Wait for map to fully initialize
           }
       });
       
       // Handle automatic navigation from hazard map
       function handleAutoNavigation() {
           // Set destination from navigation parameters
           destinationLocation = [navigationParams.lat, navigationParams.lng];
           
           // Set destination address
           let address = navigationParams.address;
           if (navigationParams.hazardData) {
               address = `${navigationParams.hazardData.hazard_type} at ${navigationParams.hazardData.address}`;
           }
           document.getElementById('destination').value = address || `Location at ${navigationParams.lat.toFixed(6)}, ${navigationParams.lng.toFixed(6)}`;
           
           // Automatically set destination and create route
           setDestination();
           
           // Show notification
           showNotification('Auto Navigation', 'Route automatically created to selected hazard location.', 'success');
           
           // Add hazard data to local storage for reference
           if (navigationParams.hazardData) {
               hazardData.push(navigationParams.hazardData);
           }
       }
       
       // Initialize map
       function initMap() {
           // Determine initial view
           let initialLat = userLocation[0];
           let initialLng = userLocation[1];
           let initialZoom = 13;
           
           // If navigating to specific location, center between user and destination
           if (navigationParams.lat && navigationParams.lng) {
               initialLat = (userLocation[0] + navigationParams.lat) / 2;
               initialLng = (userLocation[1] + navigationParams.lng) / 2;
               initialZoom = 11;
           }
           
           // Create map instance
           map = L.map('map').setView([initialLat, initialLng], initialZoom);
           
           // Add tile layer
           L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
               attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
               maxZoom: 19
           }).addTo(map);
           
           // Initialize routing control
           routeControl = L.Routing.control({
               waypoints: [],
               routeWhileDragging: true,
               lineOptions: {
                   styles: [{ color: '#FF4B4B', opacity: 0.8, weight: 6 }]
               },
               createMarker: function() { return null; } // We'll create custom markers
           }).addTo(map);
           
           // Try to get user's current location
           getUserCurrentLocation();
           
           // Load hazards
           fetchHazards();
           
           // Display recent routes
           displayRecentRoutes();
           
           // Initialize traffic controls
           initTrafficControls();
       }
       
       // Initialize feedback form
       function initFeedbackForm() {
           // Feedback type selection
           document.querySelectorAll('.feedback-type-option').forEach(option => {
               option.addEventListener('click', function() {
                   // Remove selected class from all options
                   document.querySelectorAll('.feedback-type-option').forEach(opt => {
                       opt.classList.remove('selected');
                   });
                   
                   // Add selected class to clicked option
                   this.classList.add('selected');
                   
                   // Set hidden input value
                   document.getElementById('feedbackType').value = this.dataset.type;
                   
                   // Update feedback hint
                   updateFeedbackHint(this.dataset.type);
               });
           });
           
           // Character counter for feedback message
           document.getElementById('feedbackMessage').addEventListener('input', function() {
               const charCount = this.value.length;
               document.getElementById('charCount').textContent = charCount;
               
               // Change color based on character count
               const charCountElement = document.getElementById('charCount');
               if (charCount > 500) {
                   charCountElement.style.color = 'var(--danger)';
                   this.value = this.value.substring(0, 500); // Limit to 500 characters
               } else if (charCount > 400) {
                   charCountElement.style.color = 'var(--warning)';
               } else {
                   charCountElement.style.color = 'var(--secondary)';
               }
           });
           
           // Anonymous checkbox functionality
           document.getElementById('stayAnonymous').addEventListener('change', function() {
               const reporterName = document.getElementById('reporterName');
               const reporterContact = document.getElementById('reporterContact');
               
               if (this.checked) {
                   reporterName.value = '';
                   reporterContact.value = '';
                   reporterName.disabled = true;
                   reporterContact.disabled = true;
               } else {
                   reporterName.disabled = false;
                   reporterContact.disabled = false;
               }
           });
           
           // Submit feedback button
           document.getElementById('submitFeedbackBtn').addEventListener('click', submitFeedback);
       }
       
       // Update feedback hint based on type
       function updateFeedbackHint(type) {
           const hints = {
               'status_update': 'Has this hazard been fixed or changed? Let us know the current status.',
               'location_correction': 'Is the location marker incorrect? Provide the correct location details.',
               'additional_info': 'Share additional information that might be helpful about this hazard.',
               'general_comment': 'Share your thoughts or experiences related to this hazard.'
           };
           
           document.getElementById('feedbackHint').textContent = hints[type] || 'Tell us about this hazard.';
       }
       
       // Show feedback modal
       function showFeedbackModal(hazardId) {
           currentHazardId = hazardId;
           
           // Find hazard data
           const hazard = hazardData.find(h => h.id == hazardId);
           
           if (!hazard) {
               showNotification('Error', 'Hazard not found.', 'danger');
               return;
           }
           
           // Populate hazard information
           document.getElementById('feedbackHazardId').value = hazardId;
           document.getElementById('hazardTypeDisplay').textContent = hazard.hazard_type;
           document.getElementById('hazardSeverityDisplay').textContent = hazard.severity;
           document.getElementById('hazardLocationDisplay').textContent = hazard.address;
           document.getElementById('hazardDateDisplay').textContent = hazard.reported_at;
           
           // Reset form
           document.getElementById('feedbackForm').reset();
           document.getElementById('feedbackHazardId').value = hazardId;
           document.querySelectorAll('.feedback-type-option').forEach(opt => {
               opt.classList.remove('selected');
           });
           document.getElementById('charCount').textContent = '0';
           
           // Show modal
           new bootstrap.Modal(document.getElementById('feedbackModal')).show();
       }
       
       // Submit feedback
       function submitFeedback() {
           const form = document.getElementById('feedbackForm');
           const formData = new FormData(form);
           
           // Validate required fields
           if (!formData.get('feedback_type')) {
               showNotification('Validation Error', 'Please select a feedback type.', 'warning');
               return;
           }
           
           if (!formData.get('message').trim()) {
               showNotification('Validation Error', 'Please provide your feedback message.', 'warning');
               return;
           }
           
           // Clear contact info if staying anonymous
           if (document.getElementById('stayAnonymous').checked) {
               formData.set('reporter_name', '');
               formData.set('reporter_contact', '');
           }
           
           // Add action parameter
           formData.append('action', 'submit_feedback');
           
           // Show loading state
           const submitBtn = document.getElementById('submitFeedbackBtn');
           const originalText = submitBtn.innerHTML;
           submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Submitting...';
           submitBtn.disabled = true;
           
           // Submit feedback
           fetch('map_navigation.php', {
               method: 'POST',
               body: formData
           })
           .then(response => response.json())
           .then(data => {
               if (data.success) {
                   showNotification('Feedback Submitted', data.message, 'success');
                   bootstrap.Modal.getInstance(document.getElementById('feedbackModal')).hide();
               } else {
                   showNotification('Submission Error', data.message, 'danger');
               }
           })
           .catch(error => {
               console.error('Error submitting feedback:', error);
               showNotification('Network Error', 'Failed to submit feedback. Please try again.', 'danger');
           })
           .finally(() => {
               // Restore button state
               submitBtn.innerHTML = originalText;
               submitBtn.disabled = false;
           });
       }
       
       // Get user's current location
       function getUserCurrentLocation() {
           if (navigator.geolocation) {
               navigator.geolocation.getCurrentPosition(
                   function(position) {
                       userLocation = [position.coords.latitude, position.coords.longitude];
                       map.setView(userLocation, 13);
                       
                       // Add marker for user location
                       addUserLocationMarker();
                       
                       // If we have navigation parameters, recalculate route with accurate user location
                       if (navigationParams.lat && navigationParams.lng && destinationLocation) {
                           setTimeout(() => {
                               setDestination();
                           }, 500);
                       }
                       
                       // Check for nearby hazards
                       if (isNavigating) {
                           checkNearbyHazards();
                       }
                   },
                   function(error) {
                       console.error("Geolocation error:", error);
                       showNotification("Location Error", "Could not get your current location. Using default location.", "warning");
                   },
                   { enableHighAccuracy: true, timeout: 10000 }
               );
               
               // Watch position for real-time updates during navigation
               if (isNavigating) {
                   navigator.geolocation.watchPosition(
                       function(position) {
                           userLocation = [position.coords.latitude, position.coords.longitude];
                           
                           // Update user marker
                           updateUserLocationMarker();
                           
                           // Check for nearby hazards
                           checkNearbyHazards();
                       },
                       function(error) {
                           console.error("Watch position error:", error);
                       },
                       { enableHighAccuracy: true, timeout: 10000 }
                   );
               }
           } else {
               showNotification("Browser Error", "Geolocation is not supported by this browser.", "danger");
           }
       }
       
       // Add marker for user's location
       function addUserLocationMarker() {
           // Create a custom icon for user location
           const userIcon = L.divIcon({
               className: 'user-location-marker',
               html: '<div style="background-color: #3498db; width: 20px; height: 20px; border-radius: 50%; border: 3px solid white; box-shadow: 0 0 10px rgba(0, 0, 0, 0.3);"></div>',
               iconSize: [20, 20],
               iconAnchor: [10, 10]
           });
           
           // Create marker
           const userMarker = L.marker(userLocation, { icon: userIcon }).addTo(map);
           userMarker.bindPopup("Your Location").openPopup();
           
           // Add to markers array
           markers.push({ type: 'user', marker: userMarker });
       }
       
       // Update user location marker
       function updateUserLocationMarker() {
           // Find user marker in markers array
           const userMarkerObj = markers.find(m => m.type === 'user');
           
           if (userMarkerObj) {
               userMarkerObj.marker.setLatLng(userLocation);
           } else {
               addUserLocationMarker();
           }
       }
       
       // Set travel mode
       function setTravelMode(mode) {
           travelMode = mode;
           
           // Update UI
           document.querySelectorAll('.travel-mode').forEach(el => {
               el.classList.remove('active');
           });
           
           document.querySelector(`.travel-mode[data-mode="${mode}"]`).classList.add('active');
           
           // Recalculate route if destination is set
           if (destinationLocation) {
               fetchETA();
           }
       }
       
       // Search locations using TomTom API
       function searchLocations() {
           const query = document.getElementById('destination').value;
           const suggestionsDiv = document.getElementById('suggestions');
           
           if (query.length < 3) {
               suggestionsDiv.style.display = "none";
               return;
           }
           
           // Show loading indicator
           document.getElementById('destination').classList.add('loading');
           
           // Fetch suggestions from TomTom API
           fetch(`https://api.tomtom.com/search/2/search/${encodeURIComponent(query)}.json?key=${apiKey}&limit=5`)
               .then(response => response.json())
               .then(data => {
                   suggestionsDiv.innerHTML = "";
                   
                   if (data.results && data.results.length > 0) {
                       data.results.forEach(result => {
                           const suggestion = document.createElement("div");
                           suggestion.className = "suggestion-item";
                           suggestion.innerHTML = result.address.freeformAddress;
                           suggestion.onclick = function() {
                               document.getElementById('destination').value = result.address.freeformAddress;
                               destinationLocation = [result.position.lat, result.position.lon];
                               suggestionsDiv.style.display = "none";
                           };
                           suggestionsDiv.appendChild(suggestion);
                       });
                       
                       suggestionsDiv.style.display = "block";
                   } else {
                       suggestionsDiv.style.display = "none";
                   }
                   
                   // Remove loading indicator
                   document.getElementById('destination').classList.remove('loading');
               })
               .catch(error => {
                   console.error("Error fetching locations:", error);
                   showNotification("Search Error", "Failed to fetch location suggestions.", "danger");
                   document.getElementById('destination').classList.remove('loading');
               });
       }
       
       // Navigate through suggestions with keyboard
       function navigateSuggestions(event) {
           const suggestionsDiv = document.getElementById('suggestions');
           const items = suggestionsDiv.querySelectorAll('.suggestion-item');
           
           if (items.length === 0 || suggestionsDiv.style.display === "none") {
               return;
           }
           
           // Find currently focused item
           const focusedItem = suggestionsDiv.querySelector('.suggestion-item.focused');
           let focusedIndex = -1;
           
           if (focusedItem) {
               focusedIndex = Array.from(items).indexOf(focusedItem);
           }
           
           // Handle different key presses
           switch (event.key) {
               case "ArrowDown":
                   event.preventDefault();
                   focusedIndex = Math.min(focusedIndex + 1, items.length - 1);
                   break;
               case "ArrowUp":
                   event.preventDefault();
                   focusedIndex = Math.max(focusedIndex - 1, 0);
                   break;
               case "Enter":
                   if (focusedIndex >= 0) {
                       event.preventDefault();
                       items[focusedIndex].click();
                       return;
                   }
                   break;
               case "Escape":
                   event.preventDefault();
                   suggestionsDiv.style.display = "none";
                   return;
               default:
                   return;
           }
           
           // Update focused item
           items.forEach(item => item.classList.remove('focused'));
           
           if (focusedIndex >= 0) {
               items[focusedIndex].classList.add('focused');
               items[focusedIndex].scrollIntoView({ block: 'nearest' });
           }
       }
       
       // Get current location for a destination field
       function getCurrentLocation(fieldId) {
           showLoading();
           
           if (navigator.geolocation) {
               navigator.geolocation.getCurrentPosition(
                   function(position) {
                       const lat = position.coords.latitude;
                       const lng = position.coords.longitude;
                       
                       // Reverse geocode to get address
                       fetch(`https://api.tomtom.com/search/2/reverseGeocode/${lat},${lng}.json?key=${apiKey}`)
                           .then(response => response.json())
                           .then(data => {
                               if (data.addresses && data.addresses.length > 0) {
                                   const address = data.addresses[0].address.freeformAddress;
                                   document.getElementById(fieldId).value = address;
                                   
                                   if (fieldId === 'destination') {
                                       destinationLocation = [lat, lng];
                                   }
                                   
                                   hideLoading();
                               } else {
                                   throw new Error("No address found");
                               }
                           })
                           .catch(error => {
                               console.error("Reverse geocoding error:", error);
                               document.getElementById(fieldId).value = `${lat.toFixed(6)}, ${lng.toFixed(6)}`;
                               
                               if (fieldId === 'destination') {
                                   destinationLocation = [lat, lng];
                               }
                               
                               hideLoading();
                               showNotification("Address Error", "Could not get address for this location.", "warning");
                           });
                   },
                   function(error) {
                       console.error("Geolocation error:", error);
                       hideLoading();
                       showNotification("Location Error", "Could not get your current location.", "danger");
                   },
                   { enableHighAccuracy: true, timeout: 10000 }
               );
           } else {
               hideLoading();
               showNotification("Browser Error", "Geolocation is not supported by this browser.", "danger");
           }
       }
       
       // Set destination and calculate route
       function setDestination() {
           if (!destinationLocation) {
               showNotification("Input Error", "Please select a destination first.", "warning");
               return;
           }
           
           showLoading();
           
           // Create destination marker if it doesn't exist
           const destMarkerObj = markers.find(m => m.type === 'destination');
           
           if (destMarkerObj) {
               destMarkerObj.marker.setLatLng(destinationLocation);
           } else {
               // Create a custom icon for destination
               const destIcon = L.divIcon({
                   className: 'destination-marker',
                   html: '<div style="background-color: var(--primary); width: 20px; height: 20px; border-radius: 50%; border: 3px solid white; box-shadow: 0 0 10px rgba(0, 0, 0, 0.3);"></div>',
                   iconSize: [20, 20],
                   iconAnchor: [10, 10]
               });
               
               // Create marker
               const destMarker = L.marker(destinationLocation, { icon: destIcon }).addTo(map);
               destMarker.bindPopup("Destination: " + document.getElementById('destination').value).openPopup();
               
               // Add to markers array
               markers.push({ type: 'destination', marker: destMarker });
           }
           
           // Set waypoints for routing
           routeControl.setWaypoints([
               L.latLng(userLocation[0], userLocation[1]),
               L.latLng(destinationLocation[0], destinationLocation[1])
           ]);
           
           // Fit map to show both points
           const bounds = L.latLngBounds([
               L.latLng(userLocation[0], userLocation[1]),
               L.latLng(destinationLocation[0], destinationLocation[1])
           ]);
           map.fitBounds(bounds, { padding: [50, 50] });
           
           // Update current route display
           document.getElementById('currentRoute').innerText = "Route to " + document.getElementById('destination').value;
           
           // Fetch ETA and traffic data
           fetchETA();
           fetchTrafficData();
           
           hideLoading();
       }
       
       // Fetch estimated time of arrival
       function fetchETA() {
           if (!destinationLocation) return;
           
           // Map travel modes to TomTom API modes
           const travelModeMap = {
               'driving': 'car',
               'walking': 'pedestrian',
               'bicycling': 'bicycle',
               'transit': 'bus'
           };
           
           const tomtomMode = travelModeMap[travelMode] || 'car';
           
           fetch(`https://api.tomtom.com/routing/1/calculateRoute/${userLocation[0]},${userLocation[1]}:${destinationLocation[0]},${destinationLocation[1]}/json?key=${apiKey}&travelMode=${tomtomMode}`)
               .then(response => response.json())
               .then(data => {
                   if (data.routes && data.routes.length > 0) {
                       const etaInSeconds = data.routes[0].summary.travelTimeInSeconds;
                       const distance = data.routes[0].summary.lengthInMeters;
                       
                       // Format ETA
                       let etaFormatted;
                       if (etaInSeconds < 60) {
                           etaFormatted = `${etaInSeconds} seconds`;
                       } else if (etaInSeconds < 3600) {
                           const minutes = Math.floor(etaInSeconds / 60);
                           etaFormatted = `${minutes} min`;
                       } else {
                           const hours = Math.floor(etaInSeconds / 3600);
                           const minutes = Math.floor((etaInSeconds % 3600) / 60);
                           etaFormatted = `${hours} hr ${minutes} min`;
                       }
                       
                       // Format distance
                       let distanceFormatted;
                       if (distance < 1000) {
                           distanceFormatted = `${distance} m`;
                       } else {
                           distanceFormatted = `${(distance / 1000).toFixed(1)} km`;
                       }
                       
                       // Update ETA display
                       document.getElementById('eta').innerHTML = `${etaFormatted} <small>(${distanceFormatted})</small>`;
                   } else {
                       document.getElementById('eta').innerText = "N/A";
                       showNotification("Route Error", "Could not calculate ETA for this route.", "warning");
                   }
               })
               .catch(error => {
                   console.error("ETA calculation error:", error);
                   document.getElementById('eta').innerText = "N/A";
                   showNotification("API Error", "Failed to fetch ETA information.", "danger");
               });
       }
       
       // Fetch traffic data
       function fetchTrafficData() {
           if (!destinationLocation) return;
           
           document.getElementById('trafficStatus').innerText = "Checking traffic...";
           
           fetch(`https://api.tomtom.com/traffic/services/4/flowSegmentData/absolute/10/json?key=${apiKey}&point=${userLocation[0]},${userLocation[1]}`)
               .then(response => response.json())
               .then(data => {
                   if (data.flowSegmentData) {
                       const currentSpeed = data.flowSegmentData.currentSpeed;
                       const freeFlowSpeed = data.flowSegmentData.freeFlowSpeed;
                       
                       // Calculate congestion level
                       const congestionLevel = Math.round((1 - (currentSpeed / freeFlowSpeed)) * 100);
                       
                       let trafficStatus;
                       let statusClass;
                       
                       if (congestionLevel < 10) {
                           trafficStatus = "Clear roads";
                           statusClass = "text-success";
                       } else if (congestionLevel < 30) {
                           trafficStatus = "Light traffic";
                           statusClass = "text-info";
                       } else if (congestionLevel < 50) {
                           trafficStatus = "Moderate traffic";
                           statusClass = "text-warning";
                       } else {
                           trafficStatus = "Heavy congestion";
                           statusClass = "text-danger";
                       }
                       
                       document.getElementById('trafficStatus').innerHTML = `
                           <span class="${statusClass}"><strong>${trafficStatus}</strong></span><br>
                           Current speed: ${currentSpeed} km/h<br>
                           Normal speed: ${freeFlowSpeed} km/h
                       `;
                   } else {
                       document.getElementById('trafficStatus').innerText = "No traffic data available";
                   }
               })
               .catch(error => {
                   console.error("Traffic data error:", error);
                   document.getElementById('trafficStatus').innerText = "Failed to get traffic data";
               });
       }
       
       // Start navigation mode
       function startNavigation() {
           if (!destinationLocation) {
               showNotification("Navigation Error", "Please set a destination first.", "warning");
               return;
           }
           
           isNavigating = true;
           
           // Update UI
           document.getElementById('startNavigation').innerHTML = '<i class="fas fa-stop-circle me-2"></i>Stop Navigation';
           document.getElementById('startNavigation').classList.remove('btn-primary');
           document.getElementById('startNavigation').classList.add('btn-danger');
           document.getElementById('startNavigation').onclick = stopNavigation;
           
           // Zoom to user location
           map.setView(userLocation, 18);
           
           // Fetch hazards again to ensure we have the latest data
           fetchHazards();
           
           // Start checking for nearby hazards
           checkNearbyHazards();
           
           // Start watching user position
           if (navigator.geolocation) {
               navigator.geolocation.watchPosition(
                   function(position) {
                       userLocation = [position.coords.latitude, position.coords.longitude];
                       updateUserLocationMarker();
                       checkNearbyHazards();
                   },
                   function(error) {
                       console.error("Watch position error:", error);
                   },
                   { enableHighAccuracy: true, timeout: 10000 }
               );
           }
           
           showNotification("Navigation Started", "Follow the route to your destination. Watch for hazard warnings.", "success");
       }
       
       // Stop navigation
       function stopNavigation() {
           isNavigating = false;
           
           // Update UI
           document.getElementById('startNavigation').innerHTML = '<i class="fas fa-play-circle me-2"></i>Start Navigation';
           document.getElementById('startNavigation').classList.remove('btn-danger');
           document.getElementById('startNavigation').classList.add('btn-primary');
           document.getElementById('startNavigation').onclick = startNavigation;
           
           // Hide hazard warning
           document.getElementById('hazardMessage').style.display = 'none';
           
           showNotification("Navigation Stopped", "Navigation mode has been turned off.", "info");
       }
       
       // Fetch hazards from the database
       function fetchHazards() {
           fetch("get_hazards.php")
               .then(response => response.json())
               .then(data => {
                   hazardData = data;
                   
                   // Clear old hazard markers
                   hazardMarkers.forEach(marker => map.removeLayer(marker));
                   hazardMarkers = [];
                   
                   // Add new markers
                   data.forEach(hazard => {
                       if (!hazard.latitude || !hazard.longitude) return;
                       
                       // Create marker
                       const hazardIcon = createHazardIcon(hazard.severity);
                       const marker = L.marker([parseFloat(hazard.latitude), parseFloat(hazard.longitude)], {
                           icon: hazardIcon
                       }).addTo(map);
                       
                       // Add popup
                       marker.bindPopup(createHazardPopup(hazard));
                       
                       // Store hazard id with marker
                       marker.hazardId = hazard.id;
                       
                       // Add to hazardMarkers array
                       hazardMarkers.push(marker);
                   });
               })
               .catch(error => {
                   console.error("Error fetching hazards:", error);
                   showNotification("Data Error", "Failed to fetch hazard data.", "danger");
                   
                   // Add mock hazards for demonstration if real data fails
                   addMockHazards();
               });
       }
       
       // Add mock hazards for demonstration
       function addMockHazards() {
           // Only add if we have user location
           if (!userLocation) return;
           
           // Create mock hazards around user location
           const mockHazards = [
               {
                   id: 1,
                   hazard_type: "Pothole",
                   severity: "medium",
                   latitude: userLocation[0] + 0.001,
                   longitude: userLocation[1] + 0.001,
                   address: "Near your location",
                   reported_at: "2025-04-20 10:30:00"
               },
               {
                   id: 2,
                   hazard_type: "Flooding",
                   severity: "high",
                   latitude: userLocation[0] - 0.002,
                   longitude: userLocation[1] + 0.003,
                   address: "Road ahead",
                   reported_at: "2025-04-20 11:15:00"
               },
               {
                   id: 3,
                   hazard_type: "Road Debris",
                   severity: "minor",
                   latitude: userLocation[0] + 0.003,
                   longitude: userLocation[1] - 0.001,
                   address: "Side street",
                   reported_at: "2025-04-20 09:45:00"
               }
           ];
           
           // Add navigation hazard to mock data if it exists
           if (navigationParams.hazardData && !mockHazards.find(h => h.id == navigationParams.hazardData.id)) {
               mockHazards.push(navigationParams.hazardData);
           }
           
           // Set hazardData for feedback functionality
           hazardData = mockHazards;
           
           // Add mock hazards to map
           mockHazards.forEach(hazard => {
               const hazardIcon = createHazardIcon(hazard.severity);
               const marker = L.marker([parseFloat(hazard.latitude), parseFloat(hazard.longitude)], {
                   icon: hazardIcon
               }).addTo(map);
               
               marker.bindPopup(createHazardPopup(hazard));
               marker.hazardId = hazard.id;
               hazardMarkers.push(marker);
           });
       }
       
       // Create hazard icon based on severity
       function createHazardIcon(severity) {
           let color;
           
           switch (severity.toLowerCase()) {
               case 'critical':
                   color = '#9b59b6'; // Purple
                   break;
               case 'high':
                   color = '#e74c3c'; // Red
                   break;
               case 'medium':
                   color = '#f39c12'; // Orange
                   break;
               case 'minor':
                   color = '#2ecc71'; // Green
                   break;
               default:
                   color = '#3498db'; // Blue
           }
           
           return L.divIcon({
               className: 'hazard-marker',
               html: `<div style="background-color: ${color}; width: 18px; height: 18px; border-radius: 50%; border: 2px solid white; box-shadow: 0 0 10px rgba(0, 0, 0, 0.3);"></div>`,
               iconSize: [18, 18],
               iconAnchor: [9, 9]
           });
       }
       
       // Create hazard popup content
       function createHazardPopup(hazard) {
           return `
               <div class="hazard-popup">
                   <h5 class="mb-2">${hazard.hazard_type}</h5>
                   <p><strong>Severity:</strong> <span class="severity-${hazard.severity.toLowerCase()}">${hazard.severity}</span></p>
                   <p><strong>Location:</strong> ${hazard.address}</p>
                   <p><strong>Reported:</strong> ${hazard.reported_at}</p>
                   <div class="mt-2">
                       <button class="btn btn-sm btn-primary me-1" onclick="navigateToHazard(${hazard.latitude}, ${hazard.longitude})">Navigate</button>
                       <button class="btn btn-sm btn-outline-secondary" onclick="showFeedbackModal(${hazard.id})">Feedback</button>
                   </div>
               </div>
           `;
       }
       
       // Check for nearby hazards during navigation
       function checkNearbyHazards() {
           if (!isNavigating) return;
           
           const hazardMessageEl = document.getElementById('hazardMessage');
           const hazardTextEl = document.getElementById('hazardText');
           
           let nearestHazard = null;
           let minDistance = Infinity;
           let nearbyHazards = {};
           
           hazardMarkers.forEach(marker => {
               const markerLatLng = marker.getLatLng();
               const distance = calculateDistance(
                   userLocation[0], userLocation[1],
                   markerLatLng.lat, markerLatLng.lng
               );
               
               // Check for hazards within 100 meters
               if (distance <= 100) {
                   // Get hazard type from popup content
                   const popupContent = marker._popup._content;
                   const hazardType = popupContent.match(/<h5 class="mb-2">(.*?)<\/h5>/)[1];
                   
                   // Count hazards by type
                   if (!nearbyHazards[hazardType]) {
                       nearbyHazards[hazardType] = 1;
                   } else {
                       nearbyHazards[hazardType]++;
                   }
                   
                   // Track nearest hazard
                   if (distance < minDistance) {
                       minDistance = distance;
                       nearestHazard = {
                           type: hazardType,
                           distance: Math.round(distance)
                       };
                   }
               }
           });
           
           // Show warning if there are nearby hazards
           if (nearestHazard) {
               let warningText = `${nearestHazard.type} (${nearestHazard.distance}m ahead)`;
               
               // Add summary of all nearby hazards
               if (Object.keys(nearbyHazards).length > 1) {
                   warningText += ' | Also nearby: ';
                   Object.keys(nearbyHazards).forEach((type, index) => {
                       if (type !== nearestHazard.type) {
                           warningText += `${nearbyHazards[type]} ${type}${index < Object.keys(nearbyHazards).length - 2 ? ', ' : ''}`;
                       }
                   });
               }
               
               hazardTextEl.innerText = warningText;
               hazardMessageEl.style.display = 'block';
               
               // Speak warning if browser supports it and it's a new or different hazard
               if ('speechSynthesis' in window) {
                   const speech = new SpeechSynthesisUtterance(`Warning! ${nearestHazard.type} ${nearestHazard.distance} meters ahead.`);
                   speech.rate = 1;
                   window.speechSynthesis.speak(speech);
               }
           } else {
               hazardMessageEl.style.display = 'none';
           }
       }
       
       // Calculate distance between two coordinates in meters
       function calculateDistance(lat1, lon1, lat2, lon2) {
           const R = 6371e3; // Earth radius in meters
           const φ1 = lat1 * Math.PI / 180;
           const φ2 = lat2 * Math.PI / 180;
           const Δφ = (lat2 - lat1) * Math.PI / 180;
           const Δλ = (lon2 - lon1) * Math.PI / 180;
           
           const a = Math.sin(Δφ / 2) * Math.sin(Δφ / 2) +
                    Math.cos(φ1) * Math.cos(φ2) *
                    Math.sin(Δλ / 2) * Math.sin(Δλ / 2);
           
           const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
           
           return R * c; // Distance in meters
       }
       
       // Navigate to a hazard
       function navigateToHazard(lat, lng) {
           // Set as destination
           destinationLocation = [lat, lng];
           document.getElementById('destination').value = "Selected Hazard Location";
           
           // Calculate route
           setDestination();
           
           // Close popup
           map.closePopup();
       }
       
       // Save current route to local storage
       function saveCurrentRoute() {
           if (!destinationLocation) {
               showNotification("Save Error", "No active route to save.", "warning");
               return;
           }
           
           const routeInfo = {
               id: Date.now(),
               start: "My Location",
               startCoords: userLocation,
               end: document.getElementById('destination').value,
               endCoords: destinationLocation,
               travelMode: travelMode,
               timestamp: new Date().toISOString()
           };
           
           // Get existing routes from local storage
           let recentRoutes = JSON.parse(localStorage.getItem('recentRoutes')) || [];
           
           // Add new route at the beginning
           recentRoutes.unshift(routeInfo);
           
           // Keep only the last 10 routes
           recentRoutes = recentRoutes.slice(0, 10);
           
           // Save back to local storage
           localStorage.setItem('recentRoutes', JSON.stringify(recentRoutes));
           
           // Update display
           displayRecentRoutes();
           
           showNotification("Route Saved", "Your route has been saved successfully.", "success");
       }
       
       // Display recent routes from local storage
       function displayRecentRoutes() {
           const recentRoutesContainer = document.getElementById('recentRoutes');
           const recentRoutes = JSON.parse(localStorage.getItem('recentRoutes')) || [];
           
           if (recentRoutes.length === 0) {
               recentRoutesContainer.innerHTML = '<div class="col-12"><p class="text-muted">No saved routes yet. Use the "Save Route" button to save your routes.</p></div>';
               return;
           }
           
           // Clear container
           recentRoutesContainer.innerHTML = '';
           
           // Add routes
           recentRoutes.forEach((route, index) => {
               const date = new Date(route.timestamp);
               const formattedDate = `${date.toLocaleDateString()} ${date.toLocaleTimeString()}`;
               
               const routeEl = document.createElement('div');
               routeEl.className = 'col-md-6 col-lg-4 mb-3';
               routeEl.innerHTML = `
                   <div class="route-item" onclick="loadSavedRoute(${index})">
                       <div class="route-title">
                           <i class="fas fa-map-marker-alt me-1"></i> ${route.end}
                       </div>
                       <div class="route-meta">
                           <div><i class="fas fa-${getTravelModeIcon(route.travelMode)} me-1"></i> ${getTravelModeName(route.travelMode)}</div>
                           <div><i class="fas fa-clock me-1"></i> ${formattedDate}</div>
                       </div>
                   </div>
               `;
               
               recentRoutesContainer.appendChild(routeEl);
           });
       }
       
       // Get icon for travel mode
       function getTravelModeIcon(mode) {
           switch (mode) {
               case 'walking': return 'walking';
               case 'bicycling': return 'bicycle';
               case 'transit': return 'bus';
               default: return 'car';
           }
       }
       
       // Get name for travel mode
       function getTravelModeName(mode) {
           switch (mode) {
               case 'walking': return 'Walking';
               case 'bicycling': return 'Cycling';
               case 'transit': return 'Transit';
               default: return 'Driving';
           }
       }
       
       // Load a saved route
       function loadSavedRoute(index) {
           const recentRoutes = JSON.parse(localStorage.getItem('recentRoutes')) || [];
           if (!recentRoutes[index]) return;
           
           const route = recentRoutes[index];
           
           // Set destination
           document.getElementById('destination').value = route.end;
           destinationLocation = route.endCoords;
           
           // Set travel mode
           setTravelMode(route.travelMode);
           
           // Calculate route
           setDestination();
           
           showNotification("Route Loaded", `Route to ${route.end} has been loaded.`, "success");
       }
       
       // Clear all saved routes
       function clearRecentRoutes() {
           if (confirm("Are you sure you want to delete all saved routes?")) {
               localStorage.removeItem('recentRoutes');
               displayRecentRoutes();
               showNotification("Routes Cleared", "All saved routes have been deleted.", "info");
           }
       }
       
       // Initialize traffic layer toggles
       function initTrafficControls() {
           // Traffic flow toggle
           document.getElementById('toggleTrafficFlow').addEventListener('click', function() {
               this.classList.toggle('active');
               
               if (trafficLayers.flow) {
                   map.removeLayer(trafficLayers.flow);
                   trafficLayers.flow = null;
               } else {
                   trafficLayers.flow = L.tileLayer(
                       `https://{s}.api.tomtom.com/traffic/map/4/tile/flow/relative0/{z}/{x}/{y}.png?key=${apiKey}`,
                       {
                           maxZoom: 22,
                           subdomains: ['a', 'b', 'c', 'd'],
                           opacity: 0.7
                       }
                   ).addTo(map);
               }
           });
           
           // Traffic incidents toggle
           document.getElementById('toggleIncidents').addEventListener('click', function() {
               this.classList.toggle('active');
               
               if (trafficLayers.incidents) {
                   map.removeLayer(trafficLayers.incidents);
                   trafficLayers.incidents = null;
               } else {
                   trafficLayers.incidents = L.tileLayer(
                       `https://{s}.api.tomtom.com/traffic/map/4/tile/incidents/s1/{z}/{x}/{y}.png?key=${apiKey}`,
                       {
                           maxZoom: 22,
                           subdomains: ['a', 'b', 'c', 'd'],
                           opacity: 0.7
                       }
                   ).addTo(map);
               }
           });
           
           // Best route toggle
           document.getElementById('findBestRoute').addEventListener('click', function() {
               if (!destinationLocation) {
                   showNotification("Route Error", "Please set a destination first.", "warning");
                   return;
               }
               
               showLoading();
               this.classList.add('active');
               
               // Fetch incidents along route
               fetch(`https://api.tomtom.com/traffic/services/4/incidentDetails/json?key=${apiKey}&bbox=${Math.min(userLocation[1], destinationLocation[1])},${Math.min(userLocation[0], destinationLocation[0])},${Math.max(userLocation[1], destinationLocation[1])},${Math.max(userLocation[0], destinationLocation[0])}`)
                   .then(response => response.json())
                   .then(data => {
                       if (data.incidents && data.incidents.length > 0) {
                           // Find significant incidents
                           const significantIncidents = data.incidents.filter(incident => 
                               incident.properties.iconCategory === 'accidentsAndIncidents' || 
                               incident.properties.magnitudeOfDelay === 'major'
                           );
                           
                           if (significantIncidents.length > 0) {
                               // Call TomTom API to calculate alternative route avoiding these incidents
                               let avoidAreas = significantIncidents.map(incident => {
                                   const coords = incident.geometry.coordinates;
                                   return `${coords[1]},${coords[0]}`;
                               }).join(':');
                               
                               return fetch(`https://api.tomtom.com/routing/1/calculateRoute/${userLocation[0]},${userLocation[1]}:${destinationLocation[0]},${destinationLocation[1]}/json?key=${apiKey}&avoid=incidents&traffic=true`);
                           }
                       }
                       
                       // If no incidents, just use normal route calculation
                       return fetch(`https://api.tomtom.com/routing/1/calculateRoute/${userLocation[0]},${userLocation[1]}:${destinationLocation[0]},${destinationLocation[1]}/json?key=${apiKey}&traffic=true`);
                   })
                   .then(response => response.json())
                   .then(data => {
                       if (data.routes && data.routes.length > 0) {
                           // Update route
                           const route = data.routes[0];
                           
                           // Clear existing route
                           routeControl.setWaypoints([
                               L.latLng(userLocation[0], userLocation[1]),
                               L.latLng(destinationLocation[0], destinationLocation[1])
                           ]);
                           
                           // Update ETA
                           const etaInSeconds = route.summary.travelTimeInSeconds;
                           let etaFormatted;
                           
                           if (etaInSeconds < 60) {
                               etaFormatted = `${etaInSeconds} seconds`;
                           } else if (etaInSeconds < 3600) {
                               const minutes = Math.floor(etaInSeconds / 60);
                               etaFormatted = `${minutes} min`;
                           } else {
                               const hours = Math.floor(etaInSeconds / 3600);
                               const minutes = Math.floor((etaInSeconds % 3600) / 60);
                               etaFormatted = `${hours} hr ${minutes} min`;
                           }
                           
                           document.getElementById('eta').innerText = etaFormatted;
                           
                           showNotification("Best Route", "Found the optimal route avoiding traffic incidents.", "success");
                       } else {
                           showNotification("Route Error", "Could not calculate alternative route.", "warning");
                       }
                       
                       hideLoading();
                       document.getElementById('findBestRoute').classList.remove('active');
                   })
                   .catch(error => {
                       console.error("Best route error:", error);
                       hideLoading();
                       document.getElementById('findBestRoute').classList.remove('active');
                       showNotification("API Error", "Failed to calculate best route.", "danger");
                   });
           });
       }
       
       // Function to show toast notifications
       function showNotification(title, message, type = 'info', duration = 5000) {
           const toastContainer = document.querySelector('.toast-container');
           const toastId = 'toast-' + Date.now();
           
           // Create toast element
           const toast = document.createElement('div');
           toast.className = 'custom-toast';
           toast.id = toastId;
           
           // Border color based on type
           let borderColor = 'var(--primary)';
           let icon = 'info-circle';
           
           switch(type) {
               case 'success':
                   borderColor = 'var(--success)';
                   icon = 'check-circle';
                   break;
               case 'warning':
                   borderColor = 'var(--warning)';
                   icon = 'exclamation-triangle';
                   break;
               case 'danger':
                   borderColor = 'var(--danger)';
                   icon = 'times-circle';
                   break;
               case 'info':
                   borderColor = 'var(--info)';
                   icon = 'info-circle';
                   break;
           }
           
           toast.style.borderLeftColor = borderColor;
           
           // Create toast content
           toast.innerHTML = `
               <div class="toast-header">
                   <i class="fas fa-${icon} me-2" style="color: ${borderColor}"></i>
                   <strong>${title}</strong>
                   <button type="button" class="btn-close ms-auto" onclick="document.getElementById('${toastId}').remove()"></button>
               </div>
               <div class="toast-body">${message}</div>
           `;
           
           // Add to container
           toastContainer.appendChild(toast);
           
           // Auto remove after duration
           setTimeout(() => {
               if (document.getElementById(toastId)) {
                   document.getElementById(toastId).remove();
               }
           }, duration);
       }
       
       // Show/hide loading spinner
       function showLoading() {
           document.getElementById('loadingSpinner').style.display = 'flex';
       }
       
       function hideLoading() {
           document.getElementById('loadingSpinner').style.display = 'none';
       }
   </script>
</body>
</html>