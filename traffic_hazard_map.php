<?php
// traffic_hazard_map.php - Enhanced with Navigation Redirect and Feedback System
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

// Get URL parameters for navigation from alerts
$target_lat = isset($_GET['lat']) ? floatval($_GET['lat']) : null;
$target_lng = isset($_GET['lng']) ? floatval($_GET['lng']) : null;
$target_hazard_id = isset($_GET['hazard_id']) ? intval($_GET['hazard_id']) : null;

// If specific hazard details are needed from URL
$target_hazard_data = null;
if ($target_hazard_id) {
    $hazard_sql = "SELECT id, hazard_type, severity, latitude, longitude, address, description, image_path, reported_at, status, resolved, resolved_at 
                   FROM hazard_reports WHERE id = ?";
    $hazard_stmt = $conn->prepare($hazard_sql);
    if ($hazard_stmt) {
        $hazard_stmt->bind_param("i", $target_hazard_id);
        $hazard_stmt->execute();
        $hazard_result = $hazard_stmt->get_result();
        if ($hazard_result->num_rows > 0) {
            $target_hazard_data = $hazard_result->fetch_assoc();
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
    <title>RoadSense - Hazards Map</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
    <!-- Leaflet CSS -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.3/dist/leaflet.css">
    <!-- DataTables CSS -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap5.min.css">
    <!-- Leaflet Marker Cluster CSS -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.4.1/dist/MarkerCluster.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.4.1/dist/MarkerCluster.Default.css">


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

        /* Map Container */
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

        /* Map Controls */
        .map-controls {
            position: absolute;
            top: 15px;
            right: 15px;
            z-index: 1000;
            background-color: white;
            border-radius: 8px;
            box-shadow: var(--card-shadow);
            padding: 15px;
        }

        .control-item {
            display: flex;
            align-items: center;
            margin-bottom: 10px;
            cursor: pointer;
        }

        .control-item:last-child {
            margin-bottom: 0;
        }

        .control-item input[type="checkbox"] {
            margin-right: 10px;
        }

        /* Filter Sidebar */
        .filter-sidebar {
            background-color: white;
            border-radius: 10px;
            box-shadow: var(--card-shadow);
            padding: 1.5rem;
        }

        .filter-sidebar h5 {
            margin-bottom: 1.25rem;
            color: var(--secondary);
            font-size: 1.2rem;
        }

        .filter-group {
            margin-bottom: 1.25rem;
        }

        .filter-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: var(--secondary);
        }

        .filter-group select,
        .filter-group input {
            width: 100%;
            padding: 0.5rem;
            border: 1px solid rgba(0, 0, 0, 0.1);
            border-radius: 6px;
            background-color: var(--light);
            color: var(--secondary);
        }

        .filter-actions {
            display: flex;
            gap: 10px;
            margin-top: 1.5rem;
        }

        /* Hazard Count Badge */
        .hazard-count {
            display: inline-block;
            padding: 0.25rem 0.5rem;
            background-color: var(--primary);
            color: white;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 500;
        }

        /* Severity Labels */
        .severity-label {
            display: inline-block;
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            font-size: 0.85rem;
            font-weight: 500;
        }

        .severity-minor {
            background-color: #D1FAE5;
            color: #065F46;
        }

        .severity-medium {
            background-color: #FEF3C7;
            color: #92400E;
        }

        .severity-high {
            background-color: #FEE2E2;
            color: #991B1B;
        }

        .severity-critical {
            background-color: #F8D7DA;
            color: #721C24;
        }

        /* Custom marker clusters */
        .custom-cluster {
            background-clip: padding-box;
            border-radius: 20px;
            text-align: center;
            font-weight: bold;
        }

        .custom-cluster div {
            background-color: var(--primary);
            color: white;
            width: 36px;
            height: 36px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.3);
            border: 2px solid white;
        }

        .custom-cluster-small div {
            background-color: rgba(255, 75, 75, 0.8);
        }

        .custom-cluster-medium div {
            background-color: rgba(242, 55, 55, 0.8);
        }

        .custom-cluster-large div {
            background-color: rgba(229, 57, 53, 0.8);
        }

        /* DataTables customization */
        .dataTables_wrapper {
            padding: 0;
        }

        .dataTables_filter input {
            border: 1px solid rgba(0, 0, 0, 0.1);
            border-radius: 6px;
            padding: 0.5rem;
        }

        .dataTables_length select {
            border: 1px solid rgba(0, 0, 0, 0.1);
            border-radius: 6px;
            padding: 0.25rem 0.5rem;
        }

        table.dataTable thead th {
            background-color: var(--light);
            color: var(--secondary);
            font-weight: 600;
            padding: 1rem;
        }

        table.dataTable tbody td {
            padding: 0.75rem 1rem;
            vertical-align: middle;
        }

        /* Custom Toggle Switch */
        .switch {
            position: relative;
            display: inline-block;
            width: 50px;
            height: 24px;
            margin-right: 10px;
        }

        .switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }

        .slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: #ccc;
            transition: .4s;
            border-radius: 24px;
        }

        .slider:before {
            position: absolute;
            content: "";
            height: 16px;
            width: 16px;
            left: 4px;
            bottom: 4px;
            background-color: white;
            transition: .4s;
            border-radius: 50%;
        }

        input:checked + .slider {
            background-color: var(--primary);
        }

        input:checked + .slider:before {
            transform: translateX(26px);
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
            <a class="nav-link" href="map_navigation.php">Live Navigation</a>
        </li>
        <li class="nav-item">
            <a class="nav-link active" href="traffic_hazard_map.php">Hazards Map</a>
        </li>
        <li class="ms-auto">
            <div class="traffic-controls">
                <div class="traffic-control-item" id="toggleTrafficFlow">
                    <i class="fas fa-traffic-light"></i> Traffic Flow
                </div>
                <div class="traffic-control-item" id="toggleIncidents">
                    <i class="fas fa-car-crash"></i> Incidents
                </div>
                <div class="traffic-control-item" id="toggleHeatmap">
                    <i class="fas fa-fire"></i> Heatmap
                </div>
                <div class="traffic-control-item" id="toggleMapStyle">
                    <i class="fas fa-adjust"></i> Map Style
                </div>
            </div>
        </li>
    </ul>

    <!-- Alert Navigation Banner (shown when coming from alerts) -->
    <?php if ($target_hazard_data): ?>
    <div class="alert alert-info alert-dismissible fade show m-0" id="navigationBanner">
        <div class="container-fluid">
            <div class="d-flex align-items-center">
                <i class="fas fa-map-marker-alt me-2"></i>
                <span>
                    <strong>Navigating to:</strong> 
                    <?php echo ucwords(str_replace('_', ' ', $target_hazard_data['hazard_type'])); ?> 
                    (<?php echo ucfirst($target_hazard_data['severity']); ?>) at 
                    <?php echo htmlspecialchars($target_hazard_data['address']); ?>
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
                        
                        <!-- Map Controls -->
                        <div class="map-controls">
                            <div class="control-item">
                                <label class="switch">
                                    <input type="checkbox" id="locateMe">
                                    <span class="slider"></span>
                                </label>
                                <label for="locateMe">Show My Location</label>
                            </div>
                            <div class="control-item">
                                <label class="switch">
                                    <input type="checkbox" id="showAll" checked>
                                    <span class="slider"></span>
                                </label>
                                <label for="showAll">Show All Hazards</label>
                            </div>
                            <div class="control-item">
                                <label class="switch">
                                    <input type="checkbox" id="showClusters">
                                    <span class="slider"></span>
                                </label>
                                <label for="showClusters">Cluster Markers</label>
                            </div>
                            <?php if ($target_hazard_data): ?>
                            <div class="control-item">
                                <button class="btn btn-sm btn-primary" id="focusTargetHazard">
                                    <i class="fas fa-crosshairs me-1"></i> Focus Target
                                </button>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <!-- Filter Options -->
                <div class="col-lg-4 mb-4">
                    <div class="filter-sidebar">
                        <h5><i class="fas fa-filter me-2"></i>Filter Hazards</h5>
                        
                        <div class="filter-group">
                            <label for="hazardType">Hazard Type</label>
                            <select id="hazardType" class="form-select">
                                <option value="">All Types</option>
                                <option value="pothole">Pothole</option>
                                <option value="road_crack">Road Crack</option>
                                <option value="flooding">Flooding</option>
                                <option value="fallen_tree">Fallen Tree</option>
                                <option value="landslide">Landslide</option>
                                <option value="debris">Road Debris</option>
                                <option value="construction">Construction Issue</option>
                                <option value="accident">Accident</option>
                                <option value="other">Other Hazard</option>
                            </select>
                        </div>
                        
                        <div class="filter-group">
                            <label for="severity">Severity Level</label>
                            <select id="severity" class="form-select">
                                <option value="">All Severities</option>
                                <option value="minor">Minor</option>
                                <option value="medium">Medium</option>
                                <option value="high">High</option>
                                <option value="critical">Critical</option>
                            </select>
                        </div>
                        
                        <div class="filter-group">
                            <label for="dateFilter">Date Reported</label>
                            <input type="date" id="dateFilter" class="form-control">
                        </div>
                        
                        <div class="filter-group">
                            <label for="timeFilter">Time of Day</label>
                            <select id="timeFilter" class="form-select">
                                <option value="">Any Time</option>
                                <option value="morning">Morning (6 AM - 12 PM)</option>
                                <option value="afternoon">Afternoon (12 PM - 6 PM)</option>
                                <option value="evening">Evening (6 PM - 12 AM)</option>
                                <option value="night">Night (12 AM - 6 AM)</option>
                            </select>
                        </div>
                        
                        <div class="filter-actions">
                            <button id="applyFilter" class="btn btn-primary">
                                <i class="fas fa-check me-1"></i>Apply Filter
                            </button>
                            <button id="resetFilter" class="btn btn-secondary">
                                <i class="fas fa-undo me-1"></i>Reset
                            </button>
                        </div>
                        
                        <hr>
                        
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <span class="hazard-count" id="hazardCount">0</span>
                                <span class="ms-2">hazards found</span>
                            </div>
                            <button class="btn btn-sm btn-outline-primary" id="refreshData">
                                <i class="fas fa-sync-alt me-1"></i>Refresh
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Hazard Reports Table -->
            <div class="row">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h4 class="mb-0"><i class="fas fa-exclamation-triangle me-2"></i>Hazard Reports</h4>
                            <div>
                                <button class="btn btn-sm btn-outline-primary" id="exportData">
                                    <i class="fas fa-download me-1"></i>Export Data
                                </button>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table id="hazardTable" class="table table-striped table-hover">
                                    <thead>
                                        <tr>
                                            <th>Hazard Type</th>
                                            <th>Severity</th>
                                            <th>Location</th>
                                            <th>Reported At</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <!-- Table will be populated via JavaScript -->
                                    </tbody>
                                </table>
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
    <footer class="text-center">
        <div class="container">
            <p>© 2025 RoadSense - Smart Road Hazard Mapping System</p>
        </div>
    </footer>

    <!-- Bootstrap 5 & Popper JS -->
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.6/dist/umd/popper.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.min.js"></script>
    
    <!-- jQuery (required for DataTables) -->
    <script src="https://code.jquery.com/jquery-3.6.4.min.js"></script>
    
    <!-- Leaflet JS -->
    <script src="https://unpkg.com/leaflet@1.9.3/dist/leaflet.js"></script>
    
    <!-- Leaflet Heat Map -->
    <script src="https://unpkg.com/leaflet.heat@0.2.0/dist/leaflet-heat.js"></script>
    
    <!-- Leaflet Marker Cluster -->
    <script src="https://unpkg.com/leaflet.markercluster@1.4.1/dist/leaflet.markercluster.js"></script>
    
    <!-- DataTables -->
    <script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"></script>
    
    <!-- Hazard Map Script -->
    <script>
        // TomTom API Key
        const apiKey = "QsG5BtRmhcKECyn9fcSPaDojqAyH440R";
        
        // Global variables
        let map = null;
        let hazardMarkers = [];
        let markersLayer = null;
        let clusterLayer = null;
        let heatmapLayer = null;
        let trafficLayers = { flow: null, incidents: null };
        let userLocationMarker = null;
        let hazardTable = null;
        let hazardData = [];
        let isDarkMode = false;
        let currentHazardId = null;
        let targetHazardMarker = null;
        
        // Navigation parameters from PHP
        const navigationTarget = {
            lat: <?php echo $target_lat ? $target_lat : 'null'; ?>,
            lng: <?php echo $target_lng ? $target_lng : 'null'; ?>,
            hazardId: <?php echo $target_hazard_id ? $target_hazard_id : 'null'; ?>,
            hazardData: <?php echo $target_hazard_data ? json_encode($target_hazard_data) : 'null'; ?>
        };
        
        // Initialize map
        document.addEventListener('DOMContentLoaded', initMap);
        
        function initMap() {
            // Show loading spinner
            showLoading();
            
            // Determine initial view based on navigation target
            let initialLat = 14.5995; // Default: Manila, Philippines
            let initialLng = 120.9842;
            let initialZoom = 7;
            
            if (navigationTarget.lat && navigationTarget.lng) {
                initialLat = navigationTarget.lat;
                initialLng = navigationTarget.lng;
                initialZoom = 15; // Zoom in when navigating to specific location
            }
            
            // Create map
            map = L.map('map').setView([initialLat, initialLng], initialZoom);
            
            // Add tile layer (default: light mode)
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
                maxZoom: 19
            }).addTo(map);
            
            // Initialize markers layer
            markersLayer = L.layerGroup().addTo(map);
            
            // Initialize marker cluster layer
            clusterLayer = L.markerClusterGroup({
                showCoverageOnHover: false,
                maxClusterRadius: 50,
                iconCreateFunction: function(cluster) {
                    const count = cluster.getChildCount();
                    
                    let size;
                    if (count < 10) size = 'small';
                    else if (count < 30) size = 'medium';
                    else size = 'large';
                    
                    return L.divIcon({
                        html: '<div><span>' + count + '</span></div>',
                        className: 'custom-cluster custom-cluster-' + size,
                        iconSize: L.point(40, 40)
                    });
                }
            });
            
            // Fetch hazard data
            fetchHazards();
            
            // Initialize UI controls
            initControls();
            
            // Initialize feedback form
            initFeedbackForm();
            
            // Handle navigation target
            if (navigationTarget.lat && navigationTarget.lng) {
                setTimeout(() => {
                    handleNavigationTarget();
                }, 1000); // Wait for hazards to load
            }
            
            // Handle navigation from alerts page
            setTimeout(() => {
                handleNavigationFromAlerts();
            }, 1000);
            
            // Hide loading spinner
            hideLoading();
        }
        
        // Handle navigation target from alerts
        function handleNavigationTarget() {
            if (!navigationTarget.lat || !navigationTarget.lng) return;
            
            // Create target marker with special styling
            const targetIcon = L.divIcon({
                className: 'target-hazard-marker',
                html: `<div style="
                    background-color: #FF4B4B; 
                    width: 24px; 
                    height: 24px; 
                    border-radius: 50%; 
                    border: 4px solid white; 
                    box-shadow: 0 0 20px rgba(255, 75, 75, 0.6);
                    animation: pulse 2s infinite;
                "></div>`,
                iconSize: [24, 24],
                iconAnchor: [12, 12]
            });
            
            // Add target marker
            targetHazardMarker = L.marker([navigationTarget.lat, navigationTarget.lng], {
                icon: targetIcon,
                zIndexOffset: 1000 // Ensure it appears on top
            }).addTo(map);
            
            // Create popup content for target hazard
            let popupContent = `
                <div class="target-hazard-popup">
                    <h5 class="mb-2 text-primary">
                        <i class="fas fa-crosshairs me-2"></i>Target Hazard
                    </h5>
            `;
            
            if (navigationTarget.hazardData) {
                const hazard = navigationTarget.hazardData;
                popupContent += `
                    <p><strong>Type:</strong> ${hazard.hazard_type}</p>
                    <p><strong>Severity:</strong> <span class="severity-${hazard.severity.toLowerCase()}">${hazard.severity}</span></p>
                    <p><strong>Location:</strong> ${hazard.address}</p>
                    <p><strong>Reported:</strong> ${hazard.reported_at}</p>
                    <div class="mt-2">
                        <button class="btn btn-sm btn-success me-1" onclick="navigateToHazard(${hazard.latitude}, ${hazard.longitude}, ${hazard.id})">
                            <i class="fas fa-route me-1"></i> Navigate
                        </button>
                        <button class="btn btn-sm btn-outline-secondary" onclick="showFeedbackModal(${hazard.id})">
                            <i class="fas fa-comment me-1"></i> Feedback
                        </button>
                    </div>
                `;
            } else {
                popupContent += `
                    <p><strong>Coordinates:</strong> ${navigationTarget.lat.toFixed(6)}, ${navigationTarget.lng.toFixed(6)}</p>
                    <div class="mt-2">
                        <button class="btn btn-sm btn-success" onclick="navigateToHazard(${navigationTarget.lat}, ${navigationTarget.lng})">
                            <i class="fas fa-route me-1"></i> Navigate Here
                        </button>
                    </div>
                `;
            }
            
            popupContent += `</div>`;
            
            targetHazardMarker.bindPopup(popupContent).openPopup();
            
            // Fly to the target location with animation
            map.flyTo([navigationTarget.lat, navigationTarget.lng], 16, {
                animate: true,
                duration: 2.0
            });
            
            // Show notification
            showNotification('Navigation Target', 'Map focused on the selected hazard location.', 'info');
        }
        
        // Function to handle navigation from alerts page
        function handleNavigationFromAlerts() {
            // Check if we have navigation parameters from alerts
            if (navigationTarget.lat && navigationTarget.lng) {
                // Show quick navigation option
                const quickNavDiv = document.createElement('div');
                quickNavDiv.className = 'alert alert-success mt-3';
                quickNavDiv.innerHTML = `
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <i class="fas fa-route me-2"></i>
                            <strong>Quick Navigation:</strong> Start navigation to this hazard location?
                        </div>
                        <button class="btn btn-sm btn-success" onclick="navigateToHazard(${navigationTarget.lat}, ${navigationTarget.lng}, ${navigationTarget.hazardId})">
                            <i class="fas fa-route me-1"></i> Start Navigation
                        </button>
                    </div>
                `;
                
                // Insert after the navigation banner or at the top of main content
                const banner = document.getElementById('navigationBanner');
                const mainContent = document.querySelector('.main-content .container-fluid');
                
                if (banner && banner.parentNode) {
                    banner.parentNode.insertBefore(quickNavDiv, banner.nextSibling);
                } else if (mainContent) {
                    mainContent.insertBefore(quickNavDiv, mainContent.firstChild);
                }
            }
        }
        
        // Focus on target hazard button
        function focusTargetHazard() {
            if (navigationTarget.lat && navigationTarget.lng) {
                map.flyTo([navigationTarget.lat, navigationTarget.lng], 16, {
                    animate: true,
                    duration: 1.5
                });
                
                if (targetHazardMarker) {
                    targetHazardMarker.openPopup();
                }
            }
        }
        
        // Navigate to hazard - redirects to map_navigation.php with auto route creation
        function navigateToHazard(lat, lng, hazardId = null) {
            // Build URL parameters for navigation
            const params = new URLSearchParams();
            params.set('nav_lat', lat);
            params.set('nav_lng', lng);
            
            // Add hazard ID if provided
            if (hazardId) {
                params.set('nav_hazard_id', hazardId);
            }
            
            // Try to find hazard data to pass additional information
            let hazard = null;
            if (hazardId) {
                hazard = hazardData.find(h => h.id == hazardId);
                
                // Check navigation target data as fallback
                if (!hazard && navigationTarget.hazardData && navigationTarget.hazardData.id == hazardId) {
                    hazard = navigationTarget.hazardData;
                }
            }
            
            // Add address if we have hazard data
            if (hazard && hazard.address) {
                params.set('nav_address', hazard.address);
            } else {
                // Use coordinates as fallback address
                params.set('nav_address', `Location at ${lat.toFixed(6)}, ${lng.toFixed(6)}`);
            }
            
            // Show loading notification
            showNotification('Navigation', 'Redirecting to navigation mode...', 'info', 2000);
            
            // Close any open popups
            if (map) {
                map.closePopup();
            }
            
            // Redirect to map navigation page with parameters
            const navigationUrl = `map_navigation.php?${params.toString()}`;
            window.location.href = navigationUrl;
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
        
        // Initialize UI controls
        function initControls() {
            // Toggle My Location
            document.getElementById('locateMe').addEventListener('change', function() {
                if (this.checked) {
                    showUserLocation();
                } else {
                    hideUserLocation();
                }
            });
            
            // Toggle Show All Hazards
            document.getElementById('showAll').addEventListener('change', function() {
                if (this.checked) {
                    showAllHazards();
                } else {
                    hideAllHazards();
                }
            });
            
            // Toggle Cluster Markers
            document.getElementById('showClusters').addEventListener('change', function() {
                if (this.checked) {
                    enableClustering();
                } else {
                    disableClustering();
                }
            });
            
            // Focus Target Hazard button
            const focusBtn = document.getElementById('focusTargetHazard');
            if (focusBtn) {
                focusBtn.addEventListener('click', focusTargetHazard);
            }
            
            // Apply Filters
            document.getElementById('applyFilter').addEventListener('click', function() {
                const hazardType = document.getElementById('hazardType').value;
                const severity = document.getElementById('severity').value;
                const dateFilter = document.getElementById('dateFilter').value;
                const timeFilter = document.getElementById('timeFilter').value;
                
                applyFilters(hazardType, severity, dateFilter, timeFilter);
            });
            
            // Reset Filters
            document.getElementById('resetFilter').addEventListener('click', function() {
                document.getElementById('hazardType').value = '';
                document.getElementById('severity').value = '';
                document.getElementById('dateFilter').value = '';
                document.getElementById('timeFilter').value = '';
                
                applyFilters('', '', '', '');
            });
            
            // Refresh Data
            document.getElementById('refreshData').addEventListener('click', function() {
                fetchHazards();
            });
            
            // Export Data
            document.getElementById('exportData').addEventListener('click', function() {
                exportHazardData();
            });
            
            // Traffic Flow Toggle
            document.getElementById('toggleTrafficFlow').addEventListener('click', function() {
                this.classList.toggle('active');
                toggleTrafficFlow();
            });
            
            // Traffic Incidents Toggle
            document.getElementById('toggleIncidents').addEventListener('click', function() {
                this.classList.toggle('active');
                toggleTrafficIncidents();
            });
            
            // Heatmap Toggle
            document.getElementById('toggleHeatmap').addEventListener('click', function() {
                this.classList.toggle('active');
                toggleHeatmap();
            });
            
            // Map Style Toggle
            document.getElementById('toggleMapStyle').addEventListener('click', function() {
                this.classList.toggle('active');
                toggleMapStyle();
            });
        }
        
        // Fetch hazards from API
        function fetchHazards() {
            showLoading();
            
            fetch('get_hazards.php')
                .then(response => response.json())
                .then(data => {
                    hazardData = data;
                    
                    // Update map markers
                    updateHazardMarkers(data);
                    
                    // Update data table
                    updateHazardTable(data);
                    
                    // Update hazard count
                    updateHazardCount(data.length);
                    
                    hideLoading();
                    showNotification('Data Loaded', `Successfully loaded ${data.length} hazard reports.`, 'success');
                })
                .catch(error => {
                    console.error('Error fetching hazard data:', error);
                    hideLoading();
                    showNotification('Data Error', 'Failed to load hazard data. Using mock data instead.', 'danger');
                    
                    // Use mock data as fallback for UI testing
                    const mockData = getMockHazardData();
                    hazardData = mockData;
                    updateHazardMarkers(mockData);
                    updateHazardTable(mockData);
                    updateHazardCount(mockData.length);
                });
        }
        
        // Update hazard markers on map
        function updateHazardMarkers(hazards) {
            // Clear existing markers
            markersLayer.clearLayers();
            clusterLayer.clearLayers();
            hazardMarkers = [];
            
            // Create heatmap data array
            const heatmapData = [];
            
            // Add new markers
            hazards.forEach(hazard => {
                if (!hazard.latitude || !hazard.longitude) return;
                
                const lat = parseFloat(hazard.latitude);
                const lng = parseFloat(hazard.longitude);
                
                // Create marker with custom icon
                const marker = L.marker([lat, lng], {
                    icon: createHazardIcon(hazard.severity)
                });
                
                // Add popup
                marker.bindPopup(createHazardPopup(hazard));
                
                // Store hazard id with marker
                marker.hazardId = hazard.id;
                
                // Add to markers array
                hazardMarkers.push(marker);
                
                // Add to heatmap data
                heatmapData.push([lat, lng, 0.5]); // Intensity value 0.5 (can be adjusted based on severity)
            });
            
            // Add markers to layer based on current selection
            if (document.getElementById('showClusters').checked) {
                // Add to cluster layer
                clusterLayer.clearLayers();
                hazardMarkers.forEach(marker => clusterLayer.addLayer(marker));
                map.addLayer(clusterLayer);
            } else {
                // Add to regular layer
                hazardMarkers.forEach(marker => markersLayer.addLayer(marker));
            }
            
            // Initialize heatmap layer
            if (heatmapLayer) {
                map.removeLayer(heatmapLayer);
            }
            
            heatmapLayer = L.heatLayer(heatmapData, {
                radius: 25,
                blur: 15,
                maxZoom: 17,
                gradient: {0.4: 'blue', 0.6: 'lime', 0.8: 'yellow', 1.0: 'red'}
            });
            
            // Add heatmap if toggled
            if (document.getElementById('toggleHeatmap').classList.contains('active')) {
                map.addLayer(heatmapLayer);
            }
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
        
        // Create hazard popup content with navigation
        function createHazardPopup(hazard) {
            return `
                <div class="hazard-popup">
                    <h5 class="mb-2">${hazard.hazard_type}</h5>
                    <p><strong>Severity:</strong> <span class="severity-${hazard.severity.toLowerCase()}">${hazard.severity}</span></p>
                    <p><strong>Location:</strong> ${hazard.address}</p>
                    <p><strong>Reported:</strong> ${hazard.reported_at}</p>
                    <div class="mt-2">
                        <button class="btn btn-sm btn-success me-1" onclick="navigateToHazard(${hazard.latitude}, ${hazard.longitude}, ${hazard.id})">
                            <i class="fas fa-route me-1"></i> Navigate
                        </button>
                        <button class="btn btn-sm btn-outline-secondary" onclick="showFeedbackModal(${hazard.id})">
                            <i class="fas fa-comment me-1"></i> Feedback
                        </button>
                    </div>
                </div>
            `;
        }
        
        // Show feedback modal
        function showFeedbackModal(hazardId) {
            currentHazardId = hazardId;
            
            // Find hazard data
            let hazard = hazardData.find(h => h.id == hazardId);
            
            // If not found in current data, check if it's the navigation target
            if (!hazard && navigationTarget.hazardData && navigationTarget.hazardData.id == hazardId) {
                hazard = navigationTarget.hazardData;
            }
            
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
            fetch('traffic_hazard_map.php', {
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
        
        // Update hazard data table with navigation buttons
        function updateHazardTable(hazards) {
            // Destroy existing DataTable if it exists
            if (hazardTable) {
                hazardTable.destroy();
            }
            
            // Clear table body
            document.querySelector('#hazardTable tbody').innerHTML = '';
            
            // Create rows for each hazard
            hazards.forEach(hazard => {
                const tr = document.createElement('tr');
                
                // Add special class if this is the target hazard
                if (navigationTarget.hazardId && hazard.id == navigationTarget.hazardId) {
                    tr.className = 'table-warning';
                }
                
                // Hazard Type column
                const tdType = document.createElement('td');
                tdType.textContent = hazard.hazard_type;
                tr.appendChild(tdType);
                
                // Severity column
                const tdSeverity = document.createElement('td');
                const severitySpan = document.createElement('span');
                severitySpan.className = `severity-label severity-${hazard.severity.toLowerCase()}`;
                severitySpan.textContent = hazard.severity;
                tdSeverity.appendChild(severitySpan);
                tr.appendChild(tdSeverity);
                
                // Location column
                const tdLocation = document.createElement('td');
                tdLocation.textContent = hazard.address;
                tr.appendChild(tdLocation);
                
                // Reported At column
                const tdReported = document.createElement('td');
                tdReported.textContent = hazard.reported_at;
                tr.appendChild(tdReported);
                
                // Actions column
                const tdActions = document.createElement('td');
                let actionsHTML = `
                    <button class="btn btn-sm btn-primary me-1" onclick="viewHazardOnMap(${hazard.id})" title="View on Map">
                        <i class="fas fa-eye"></i>
                    </button>
                    <button class="btn btn-sm btn-success me-1" onclick="navigateToHazard(${hazard.latitude}, ${hazard.longitude}, ${hazard.id})" title="Start Navigation">
                        <i class="fas fa-route"></i>
                    </button>
                    <button class="btn btn-sm btn-outline-secondary" onclick="showFeedbackModal(${hazard.id})" title="Submit Feedback">
                        <i class="fas fa-comment"></i>
                    </button>
                `;
                
                // Add target indicator if this is the target hazard
                if (navigationTarget.hazardId && hazard.id == navigationTarget.hazardId) {
                    actionsHTML = `
                        <span class="badge bg-primary me-2">
                            <i class="fas fa-crosshairs me-1"></i>Target
                        </span>
                    ` + actionsHTML;
                }
                
                tdActions.innerHTML = actionsHTML;
                tr.appendChild(tdActions);
                
                // Add row to table
                document.querySelector('#hazardTable tbody').appendChild(tr);
            });
            
            // Initialize DataTable
            hazardTable = new DataTable('#hazardTable', {
                responsive: true,
                language: {
                    search: "Search hazards:",
                    lengthMenu: "Show _MENU_ hazards per page",
                    info: "Showing _START_ to _END_ of _TOTAL_ hazards",
                    infoEmpty: "No hazards available",
                    infoFiltered: "(filtered from _MAX_ total hazards)"
                },
                order: [[3, 'desc']], // Sort by reported date desc
                pageLength: 10,
                drawCallback: function() {
                    // Scroll to target hazard if it exists
                    if (navigationTarget.hazardId) {
                        const targetRow = document.querySelector('.table-warning');
                        if (targetRow) {
                            targetRow.scrollIntoView({ behavior: 'smooth', block: 'center' });
                        }
                    }
                }
            });
        }
        
        // Update hazard count
        function updateHazardCount(count) {
            document.getElementById('hazardCount').textContent = count;
        }
        
        // Apply filters to hazard data
        function applyFilters(hazardType, severity, dateFilter, timeFilter) {
            // Clone the original hazard data
            let filteredData = [...hazardData];
            
            // Apply hazard type filter
            if (hazardType) {
                filteredData = filteredData.filter(hazard => 
                    hazard.hazard_type.toLowerCase() === hazardType.toLowerCase()
                );
            }
            
            // Apply severity filter
            if (severity) {
                filteredData = filteredData.filter(hazard => 
                    hazard.severity.toLowerCase() === severity.toLowerCase()
                );
            }
            
            // Apply date filter
            if (dateFilter) {
                filteredData = filteredData.filter(hazard => {
                    // Extract date part from "YYYY-MM-DD HH:MM:SS"
                    const hazardDate = hazard.reported_at.split(' ')[0];
                    return hazardDate === dateFilter;
                });
            }
            
            // Apply time filter
            if (timeFilter) {
                filteredData = filteredData.filter(hazard => {
                    // Extract time part from "YYYY-MM-DD HH:MM:SS"
                    const hazardTime = hazard.reported_at.split(' ')[1];
                    const hours = parseInt(hazardTime.split(':')[0], 10);
                    
                    switch (timeFilter) {
                        case 'morning':
                            return hours >= 6 && hours < 12;
                        case 'afternoon':
                            return hours >= 12 && hours < 18;
                        case 'evening':
                            return hours >= 18 && hours < 24;
                        case 'night':
                            return hours >= 0 && hours < 6;
                        default:
                            return true;
                    }
                });
            }
            
            // Update markers and table with filtered data
            updateHazardMarkers(filteredData);
            updateHazardTable(filteredData);
            updateHazardCount(filteredData.length);
            
            showNotification('Filters Applied', `Showing ${filteredData.length} hazards that match your filters.`, 'info');
        }
        
        // Show user location on map
        function showUserLocation() {
            if (navigator.geolocation) {
                navigator.geolocation.getCurrentPosition(
                    function(position) {
                        const lat = position.coords.latitude;
                        const lng = position.coords.longitude;
                        
                        // Create custom icon for user location
                        const userIcon = L.divIcon({
                            className: 'user-location-marker',
                            html: '<div style="background-color: #3498db; width: 20px; height: 20px; border-radius: 50%; border: 3px solid white; box-shadow: 0 0 10px rgba(0, 0, 0, 0.3);"></div>',
                            iconSize: [20, 20],
                            iconAnchor: [10, 10]
                        });
                        
                        // Add or update marker
                        if (userLocationMarker) {
                            userLocationMarker.setLatLng([lat, lng]);
                        } else {
                            userLocationMarker = L.marker([lat, lng], { icon: userIcon }).addTo(map);
                            userLocationMarker.bindPopup("Your Location").openPopup();
                        }
                        
                        // Pan to location if not navigating to target
                        if (!navigationTarget.lat || !navigationTarget.lng) {
                            map.setView([lat, lng], 14);
                        }
                        
                        showNotification('Location Found', 'Successfully located your position on the map.', 'success');
                    },
                    function(error) {
                        console.error('Geolocation error:', error);
                        showNotification('Location Error', 'Could not get your current location. Please check your browser settings.', 'danger');
                    },
                    { enableHighAccuracy: true, timeout: 10000 }
                );
            } else {
                showNotification('Browser Error', 'Geolocation is not supported by this browser.', 'danger');
                document.getElementById('locateMe').checked = false;
            }
        }
        
        // Hide user location marker
        function hideUserLocation() {
            if (userLocationMarker) {
                map.removeLayer(userLocationMarker);
                userLocationMarker = null;
            }
        }
        
        // Show all hazards
        function showAllHazards() {
            if (document.getElementById('showClusters').checked) {
                map.addLayer(clusterLayer);
            } else {
                map.addLayer(markersLayer);
            }
        }
        
        // Hide all hazards
        function hideAllHazards() {
            map.removeLayer(markersLayer);
            map.removeLayer(clusterLayer);
        }
        
        // Enable clustering
        function enableClustering() {
            // Remove individual markers
            markersLayer.clearLayers();
            map.removeLayer(markersLayer);
            
            // Add clusters
            clusterLayer.clearLayers();
            hazardMarkers.forEach(marker => clusterLayer.addLayer(marker));
            map.addLayer(clusterLayer);
        }
        
        // Disable clustering
        function disableClustering() {
            // Remove clusters
            map.removeLayer(clusterLayer);
            clusterLayer.clearLayers();
            
            // Add individual markers
            markersLayer.clearLayers();
            hazardMarkers.forEach(marker => markersLayer.addLayer(marker));
            map.addLayer(markersLayer);
        }
        
        // Toggle traffic flow layer
        function toggleTrafficFlow() {
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
        }
        
        // Toggle traffic incidents layer
        function toggleTrafficIncidents() {
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
        }
        
        // Toggle heatmap layer
        function toggleHeatmap() {
            if (heatmapLayer && map.hasLayer(heatmapLayer)) {
                map.removeLayer(heatmapLayer);
            } else if (heatmapLayer) {
                map.addLayer(heatmapLayer);
            }
        }
        
        // Toggle map style (dark/light)
        function toggleMapStyle() {
            isDarkMode = !isDarkMode;
            
            if (isDarkMode) {
                // Switch to dark mode
                L.tileLayer('https://{s}.basemaps.cartocdn.com/dark_all/{z}/{x}/{y}{r}.png', {
                    attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors &copy; <a href="https://carto.com/attributions">CARTO</a>',
                    maxZoom: 19
                }).addTo(map);
            } else {
                // Switch to light mode
                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
                    maxZoom: 19
                }).addTo(map);
            }
        }
        
        // View specific hazard on map with navigation
        function viewHazardOnMap(hazardId) {
            // Find hazard in data
            let hazard = hazardData.find(h => h.id == hazardId);
            
            // If not found in current data, check if it's the navigation target
            if (!hazard && navigationTarget.hazardData && navigationTarget.hazardData.id == hazardId) {
                hazard = navigationTarget.hazardData;
            }
            
            if (!hazard) {
                showNotification('Error', 'Hazard not found.', 'danger');
                return;
            }
            
            // Find corresponding marker
            const marker = hazardMarkers.find(m => m.hazardId == hazardId);
            
            if (marker) {
                // Fly to marker
                map.flyTo(marker.getLatLng(), 16, {
                    animate: true,
                    duration: 1.5
                });
                
                // Open popup with updated navigation button
                const popupContent = `
                    <div class="hazard-popup">
                        <h5 class="mb-2">${hazard.hazard_type}</h5>
                        <p><strong>Severity:</strong> <span class="severity-${hazard.severity.toLowerCase()}">${hazard.severity}</span></p>
                        <p><strong>Location:</strong> ${hazard.address}</p>
                        <p><strong>Reported:</strong> ${hazard.reported_at}</p>
                        <div class="mt-2">
                            <button class="btn btn-sm btn-success me-1" onclick="navigateToHazard(${hazard.latitude}, ${hazard.longitude}, ${hazard.id})">
                                <i class="fas fa-route me-1"></i> Navigate
                            </button>
                            <button class="btn btn-sm btn-outline-secondary" onclick="showFeedbackModal(${hazard.id})">
                                <i class="fas fa-comment me-1"></i> Feedback
                            </button>
                        </div>
                    </div>
                `;
                
                marker.bindPopup(popupContent).openPopup();
            } else if (targetHazardMarker && targetHazardMarker.hazardId == hazardId) {
                // If it's the target hazard marker
                map.flyTo(targetHazardMarker.getLatLng(), 16, {
                    animate: true,
                    duration: 1.5
                });
                targetHazardMarker.openPopup();
            } else {
                // Fallback if marker not found
                map.flyTo([hazard.latitude, hazard.longitude], 16, {
                    animate: true,
                    duration: 1.5
                });
                
                // Create temporary marker with navigation functionality
                const tempMarker = L.marker([hazard.latitude, hazard.longitude], {
                    icon: createHazardIcon(hazard.severity)
                }).addTo(map);
                
                const popupContent = `
                    <div class="hazard-popup">
                        <h5 class="mb-2">${hazard.hazard_type}</h5>
                        <p><strong>Severity:</strong> <span class="severity-${hazard.severity.toLowerCase()}">${hazard.severity}</span></p>
                        <p><strong>Location:</strong> ${hazard.address}</p>
                        <p><strong>Reported:</strong> ${hazard.reported_at}</p>
                        <div class="mt-2">
                            <button class="btn btn-sm btn-success me-1" onclick="navigateToHazard(${hazard.latitude}, ${hazard.longitude}, ${hazard.id})">
                                <i class="fas fa-route me-1"></i> Navigate
                            </button>
                            <button class="btn btn-sm btn-outline-secondary" onclick="showFeedbackModal(${hazard.id})">
                                <i class="fas fa-comment me-1"></i> Feedback
                            </button>
                        </div>
                    </div>
                `;
                
                tempMarker.bindPopup(popupContent).openPopup();
            }
        }
        
        // Export hazard data to CSV
        function exportHazardData() {
            // Get visible (filtered) data from table
            let visibleData = [];
            
            hazardTable.rows({ search: 'applied' }).every(function() {
                const rowData = this.data();
                visibleData.push({
                    hazard_type: rowData[0],
                    severity: rowData[1].replace(/<[^>]*>/g, ''), // Remove HTML tags
                    location: rowData[2],
                    reported_at: rowData[3]
                });
            });
            
            // Create CSV content
            let csvContent = "data:text/csv;charset=utf-8,";
            
            // Add headers
            csvContent += "Hazard Type,Severity,Location,Reported At\n";
            
            // Add rows
            visibleData.forEach(row => {
                csvContent += `"${row.hazard_type}","${row.severity}","${row.location}","${row.reported_at}"\n`;
            });
            
            // Create download link
            const encodedUri = encodeURI(csvContent);
            const link = document.createElement("a");
            link.setAttribute("href", encodedUri);
            link.setAttribute("download", "hazard_data.csv");
            document.body.appendChild(link);
            
            // Trigger download
            link.click();
            
            // Clean up
            document.body.removeChild(link);
            
            showNotification('Export Complete', `Successfully exported ${visibleData.length} hazard records to CSV.`, 'success');
        }
        
        // Get mock hazard data for testing
        function getMockHazardData() {
            let mockData = [
                {
                    id: 1,
                    hazard_type: "Pothole",
                    severity: "high",
                    latitude: 14.5995,
                    longitude: 120.9842,
                    address: "Manila City Center",
                    reported_at: "2025-04-20 09:30:00"
                },
                {
                    id: 2,
                    hazard_type: "Flooding",
                    severity: "critical",
                    latitude: 14.6, 
                    longitude: 121.0,
                    address: "Quezon City, EDSA",
                    reported_at: "2025-04-20 10:15:00"
                },
                {
                    id: 3,
                    hazard_type: "Road Crack",
                    severity: "medium",
                    latitude: 14.58,
                    longitude: 120.97,
                    address: "Taft Avenue, Manila",
                    reported_at: "2025-04-19 14:20:00"
                },
                {
                    id: 4,
                    hazard_type: "Fallen Tree",
                    severity: "high",
                    latitude: 14.55,
                    longitude: 121.02,
                    address: "Makati City, Ayala Avenue",
                    reported_at: "2025-04-19 08:45:00"
                },
                {
                    id: 5,
                    hazard_type: "Construction",
                    severity: "minor",
                    latitude: 14.52,
                    longitude: 120.99,
                    address: "Pasay City, MIA Road",
                    reported_at: "2025-04-18 16:30:00"
                }
            ];
            
            // If we have navigation target data, add it to mock data if not already present
            if (navigationTarget.hazardData && !mockData.find(h => h.id == navigationTarget.hazardData.id)) {
                mockData.unshift(navigationTarget.hazardData);
            }
            
            return mockData;
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
        
        // Add pulse animation for target marker
        const style = document.createElement('style');
        style.textContent = `
            @keyframes pulse {
                0% {
                    transform: scale(1);
                    opacity: 1;
                }
                50% {
                    transform: scale(1.2);
                    opacity: 0.7;
                }
                100% {
                    transform: scale(1);
                    opacity: 1;
                }
            }
            
            .target-hazard-panel {
                position: absolute;
                top: 60px;
                right: 15px;
                background: rgba(255, 75, 75, 0.9);
                color: white;
                border-radius: 8px;
                padding: 0;
                z-index: 1000;
                min-width: 280px;
                box-shadow: 0 4px 15px rgba(0, 0, 0, 0.3);
                backdrop-filter: blur(10px);
            }
            
            .panel-header {
                padding: 10px 15px;
                border-bottom: 1px solid rgba(255, 255, 255, 0.2);
                display: flex;
                justify-content: between;
                align-items: center;
            }
            
            .panel-body {
                padding: 15px;
            }
            
            .panel-actions {
                margin-top: 10px;
            }
            
            .panel-actions .btn {
                font-size: 0.8rem;
                padding: 0.25rem 0.5rem;
            }
            
            .severity-badge {
                padding: 0.25rem 0.5rem;
                border-radius: 12px;
                font-size: 0.75rem;
                font-weight: 500;
            }
            
            .severity-minor {
                background-color: rgba(40, 167, 69, 0.2);
                color: #28A745;
                border: 1px solid #28A745;
            }
            
            .severity-medium {
                background-color: rgba(255, 193, 7, 0.2);
                color: #FFC107;
                border: 1px solid #FFC107;
            }
            
            .severity-high {
                background-color: rgba(220, 53, 69, 0.2);
                color: #DC3545;
                border: 1px solid #DC3545;
            }
            
            .severity-critical {
                background-color: rgba(155, 89, 182, 0.2);
                color: #9b59b6;
                border: 1px solid #9b59b6;
            }
            
            .table-warning {
                background-color: rgba(255, 193, 7, 0.25) !important;
            }
            
            .severity-label {
                padding: 0.25rem 0.5rem;
                border-radius: 12px;
                font-size: 0.75rem;
                font-weight: 500;
            }
            
            @media (max-width: 768px) {
                .target-hazard-panel {
                    position: relative;
                    top: auto;
                    right: auto;
                    margin: 10px;
                    width: calc(100% - 20px);
                }
            }
        `;
        document.head.appendChild(style);
    </script>
</body>
</html>