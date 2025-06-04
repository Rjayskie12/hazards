<?php
// engineer_dashboard.php - Enhanced with Feedback Management
require_once 'db_connect.php';

// Start session and check engineer authentication
session_start();

// Check if user is logged in and is an engineer
if (!isset($_SESSION['engineer_logged_in']) || $_SESSION['engineer_role'] !== 'engineer') {
    header('Location: login.php');
    exit;
}

// Get engineer details
$engineer_id = $_SESSION['engineer_id'];
$engineer_sql = "SELECT * FROM users WHERE id = ? AND role = 'engineer'";
$engineer_stmt = $conn->prepare($engineer_sql);
$engineer_stmt->bind_param("i", $engineer_id);
$engineer_stmt->execute();
$engineer_result = $engineer_stmt->get_result();
$engineer = $engineer_result->fetch_assoc();

if (!$engineer) {
    session_destroy();
    header('Location: login.php');
    exit;
}

// Handle AJAX requests for report status updates and feedback management
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    $action = $_POST['action'];
    
    if ($action === 'approve' || $action === 'reject' || $action === 'resolve' || $action === 'unresolve') {
        $report_id = intval($_POST['report_id']);
        
        // First, verify that this report is within the engineer's coverage area
        $verify_sql = "SELECT id, latitude, longitude FROM hazard_reports WHERE id = ?";
        $verify_stmt = $conn->prepare($verify_sql);
        $verify_stmt->bind_param("i", $report_id);
        $verify_stmt->execute();
        $verify_result = $verify_stmt->get_result();
        $report = $verify_result->fetch_assoc();
        
        if (!$report) {
            echo json_encode(['success' => false, 'message' => 'Report not found']);
            exit;
        }
        
        // Calculate distance between report and engineer's assigned location
        if ($engineer['assigned_latitude'] && $engineer['assigned_longitude']) {
            $distance = calculateDistance(
                $engineer['assigned_latitude'], 
                $engineer['assigned_longitude'],
                $report['latitude'], 
                $report['longitude']
            );
            
            // Check if report is within coverage radius
            if ($distance > $engineer['coverage_radius_meters']) {
                echo json_encode(['success' => false, 'message' => 'This report is outside your assigned coverage area']);
                exit;
            }
        }
        
        // Process the action
        if ($action === 'approve') {
            $sql = "UPDATE hazard_reports SET status = 'approved', approved_by = ?, approved_at = NOW() WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ii", $engineer_id, $report_id);
            
        } elseif ($action === 'reject') {
            $rejection_reason = $_POST['reason'] ?? 'No reason provided';
            $sql = "UPDATE hazard_reports SET status = 'rejected', approved_by = ?, approved_at = NOW(), rejection_reason = ? WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("isi", $engineer_id, $rejection_reason, $report_id);
            
        } elseif ($action === 'resolve') {
            $resolution_notes = $_POST['notes'] ?? '';
            $current_time = date('Y-m-d H:i:s');
            $sql = "UPDATE hazard_reports SET resolved = 1, resolved_at = ?, resolved_by = ?, resolution_notes = ? WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("sisi", $current_time, $engineer_id, $resolution_notes, $report_id);
            
        } elseif ($action === 'unresolve') {
            $sql = "UPDATE hazard_reports SET resolved = 0, resolved_at = NULL, resolved_by = NULL, resolution_notes = NULL WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $report_id);
        }
        
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => ucfirst($action) . ' action completed successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to ' . $action . ' report']);
        }
        $stmt->close();
        exit;
    }
    
    // Handle feedback status updates
    if ($action === 'update_feedback_status') {
        $feedback_id = intval($_POST['feedback_id']);
        $new_status = $_POST['status']; // 'reviewed', 'in_progress', 'resolved'
        $response_notes = $_POST['response_notes'] ?? '';
        
        // Verify the feedback belongs to a report in engineer's coverage area
        $verify_feedback_sql = "SELECT fr.*, hr.latitude, hr.longitude 
                               FROM feedback_reports fr 
                               JOIN hazard_reports hr ON fr.hazard_report_id = hr.id 
                               WHERE fr.id = ?";
        $verify_stmt = $conn->prepare($verify_feedback_sql);
        $verify_stmt->bind_param("i", $feedback_id);
        $verify_stmt->execute();
        $verify_result = $verify_stmt->get_result();
        $feedback_report = $verify_result->fetch_assoc();
        
        if (!$feedback_report) {
            echo json_encode(['success' => false, 'message' => 'Feedback not found']);
            exit;
        }
        
        // Check coverage area
        $distance = calculateDistance(
            $engineer['assigned_latitude'], 
            $engineer['assigned_longitude'],
            $feedback_report['latitude'], 
            $feedback_report['longitude']
        );
        
        if ($distance > $engineer['coverage_radius_meters']) {
            echo json_encode(['success' => false, 'message' => 'This feedback is outside your coverage area']);
            exit;
        }
        
        // Update feedback status
        $update_sql = "UPDATE feedback_reports SET status = ?, response_notes = ?, responded_by = ?, responded_at = NOW() WHERE id = ?";
        $update_stmt = $conn->prepare($update_sql);
        $update_stmt->bind_param("ssii", $new_status, $response_notes, $engineer_id, $feedback_id);
        
        if ($update_stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Feedback status updated successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to update feedback status']);
        }
        $update_stmt->close();
        exit;
    }
    
    // Update engineer profile
    if ($action === 'update_profile') {
        $full_name = $_POST['full_name'];
        $email = $_POST['email'];
        $phone = $_POST['phone'] ?? '';
        
        $sql = "UPDATE users SET full_name = ?, email = ?, phone = ? WHERE id = ? AND role = 'engineer'";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sssi", $full_name, $email, $phone, $engineer_id);
        
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Profile updated successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to update profile']);
        }
        $stmt->close();
        exit;
    }
}

// Handle logout
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: login.php');
    exit;
}

// Get current page
$current_page = $_GET['page'] ?? 'dashboard';

// Get filter parameters
$status_filter = $_GET['status'] ?? 'all';
$severity_filter = $_GET['severity'] ?? 'all';
$type_filter = $_GET['type'] ?? 'all';
$date_filter = $_GET['date'] ?? '';
$feedback_status_filter = $_GET['feedback_status'] ?? 'all';

// Function to calculate distance between two coordinates
function calculateDistance($lat1, $lon1, $lat2, $lon2) {
    $earth_radius = 6371000; // Earth's radius in meters
    
    $lat1_rad = deg2rad($lat1);
    $lon1_rad = deg2rad($lon1);
    $lat2_rad = deg2rad($lat2);
    $lon2_rad = deg2rad($lon2);
    
    $delta_lat = $lat2_rad - $lat1_rad;
    $delta_lon = $lon2_rad - $lon1_rad;
    
    $a = sin($delta_lat / 2) * sin($delta_lat / 2) +
         cos($lat1_rad) * cos($lat2_rad) *
         sin($delta_lon / 2) * sin($delta_lon / 2);
    
    $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
    
    return $earth_radius * $c;
}

// Get reports within engineer's coverage area
$reports_sql = "SELECT hr.*, u.full_name as approved_by_name, ur.full_name as resolved_by_name 
                FROM hazard_reports hr 
                LEFT JOIN users u ON hr.approved_by = u.id 
                LEFT JOIN users ur ON hr.resolved_by = ur.id 
                WHERE 1=1";

$params = [];
$types = "";

// Add filters
if ($status_filter !== 'all') {
    $reports_sql .= " AND hr.status = ?";
    $params[] = $status_filter;
    $types .= "s";
}

if ($severity_filter !== 'all') {
    $reports_sql .= " AND hr.severity = ?";
    $params[] = $severity_filter;
    $types .= "s";
}

if ($type_filter !== 'all') {
    $reports_sql .= " AND hr.hazard_type = ?";
    $params[] = $type_filter;
    $types .= "s";
}

if ($date_filter) {
    $reports_sql .= " AND DATE(hr.reported_at) = ?";
    $params[] = $date_filter;
    $types .= "s";
}

$reports_sql .= " ORDER BY hr.reported_at DESC";

$reports_stmt = $conn->prepare($reports_sql);
if (!empty($params)) {
    $reports_stmt->bind_param($types, ...$params);
}
$reports_stmt->execute();
$all_reports = $reports_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Filter reports by coverage area
$coverage_reports = [];
if ($engineer['assigned_latitude'] && $engineer['assigned_longitude']) {
    foreach ($all_reports as $report) {
        if ($report['latitude'] && $report['longitude']) {
            $distance = calculateDistance(
                $engineer['assigned_latitude'], 
                $engineer['assigned_longitude'],
                $report['latitude'], 
                $report['longitude']
            );
            
            if ($distance <= $engineer['coverage_radius_meters']) {
                $report['distance'] = round($distance);
                $coverage_reports[] = $report;
            }
        }
    }
}

$feedback_sql = "SELECT fr.*, hr.id as report_id, hr.hazard_type, hr.severity, hr.address, hr.latitude, hr.longitude,
                        ur.full_name as responded_by_name
                 FROM feedback_reports fr
                 JOIN hazard_reports hr ON fr.hazard_report_id = hr.id
                 LEFT JOIN users ur ON fr.responded_by = ur.id
                 WHERE 1=1";


$feedback_params = [];
$feedback_types = "";

// Add feedback status filter
if ($feedback_status_filter !== 'all') {
    $feedback_sql .= " AND fr.status = ?";
    $feedback_params[] = $feedback_status_filter;
    $feedback_types .= "s";
}

$feedback_sql .= " ORDER BY fr.created_at DESC";

$feedback_stmt = $conn->prepare($feedback_sql);
if (!empty($feedback_params)) {
    $feedback_stmt->bind_param($feedback_types, ...$feedback_params);
}
$feedback_stmt->execute();
$all_feedback = $feedback_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Filter feedback by engineer's coverage area
$coverage_feedback = [];
if ($engineer['assigned_latitude'] && $engineer['assigned_longitude']) {
    foreach ($all_feedback as $feedback) {
        if ($feedback['latitude'] && $feedback['longitude']) {
            $distance = calculateDistance(
                $engineer['assigned_latitude'], 
                $engineer['assigned_longitude'],
                $feedback['latitude'], 
                $feedback['longitude']
            );
            
            if ($distance <= $engineer['coverage_radius_meters']) {
                $feedback['distance'] = round($distance);
                $coverage_feedback[] = $feedback;
            }
        }
    }
}

// Get statistics for dashboard
$stats = [
    'total_reports' => count($coverage_reports),
    'pending' => count(array_filter($coverage_reports, function($r) { return $r['status'] === 'pending'; })),
    'approved' => count(array_filter($coverage_reports, function($r) { return $r['status'] === 'approved'; })),
    'resolved' => count(array_filter($coverage_reports, function($r) { return $r['resolved'] == 1; })),
    'critical' => count(array_filter($coverage_reports, function($r) { return $r['severity'] === 'critical'; })),
    'high' => count(array_filter($coverage_reports, function($r) { return $r['severity'] === 'high'; })),
    'total_feedback' => count($coverage_feedback),
    'pending_feedback' => count(array_filter($coverage_feedback, function($f) { return $f['status'] === 'pending'; })),
    'reviewed_feedback' => count(array_filter($coverage_feedback, function($f) { return $f['status'] === 'reviewed'; })),
    'resolved_feedback' => count(array_filter($coverage_feedback, function($f) { return $f['status'] === 'resolved'; }))
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Engineer Dashboard - RoadSense</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.3/dist/leaflet.css">
    
    <style>
        :root {
            --primary: #FF4B4B;
            --primary-dark: #E53935;
            --secondary: #2C3E50;
            --secondary-dark: #1A252F;
            --success: #28A745;
            --warning: #FFC107;
            --info: #17A2B8;
            --danger: #DC3545;
        }

        .sidebar {
            position: fixed;
            left: 0;
            top: 0;
            height: 100vh;
            width: 260px;
            background: linear-gradient(180deg, #2C3E50 0%, #34495E 100%);
            color: white;
            z-index: 1000;
            display: flex;
            flex-direction: column;
            box-shadow: 2px 0 10px rgba(0, 0, 0, 0.1);
        }

        .sidebar-header {
            padding: 1.5rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            background: rgba(0, 0, 0, 0.1);
        }

        .sidebar-brand {
            font-size: 1.25rem;
            font-weight: 600;
            color: white;
            text-decoration: none;
        }

        .sidebar-brand:hover {
            color: var(--primary);
        }

        .engineer-info {
            margin-top: 0.5rem;
            font-size: 0.85rem;
            opacity: 0.8;
        }

        .sidebar-menu {
            flex: 1;
            padding: 1rem 0;
        }

        .sidebar-menu .nav-link {
            color: rgba(255, 255, 255, 0.8);
            padding: 0.75rem 1.5rem;
            border-radius: 0;
            display: flex;
            align-items: center;
            transition: all 0.3s ease;
            position: relative;
        }

        .sidebar-menu .nav-link:hover {
            background: rgba(255, 255, 255, 0.1);
            color: white;
            transform: translateX(5px);
        }

        .sidebar-menu .nav-link.active {
            background: var(--primary);
            color: white;
            box-shadow: 0 2px 10px rgba(255, 75, 75, 0.3);
        }

        .sidebar-menu .nav-link.active::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            bottom: 0;
            width: 4px;
            background: white;
        }

        .sidebar-menu .nav-link i {
            width: 20px;
            margin-right: 12px;
            text-align: center;
        }

        .sidebar-footer {
            padding: 1rem 1.5rem;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            background: rgba(0, 0, 0, 0.1);
        }

        .main-content {
            margin-left: 260px;
            min-height: 100vh;
            background: #f8f9fa;
        }

        .topbar {
            background: white;
            padding: 1rem 2rem;
            border-bottom: 1px solid #dee2e6;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .content-area {
            padding: 2rem;
        }

        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.08);
            margin-bottom: 1.5rem;
            position: relative;
            overflow: hidden;
            transition: all 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.15);
        }

        .stat-card.bg-primary {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%) !important;
            color: white;
        }

        .stat-card.bg-warning {
            background: linear-gradient(135deg, #FFC107 0%, #FF8F00 100%) !important;
            color: #000;
        }

        .stat-card.bg-success {
            background: linear-gradient(135deg, #28A745 0%, #1E7E34 100%) !important;
            color: white;
        }

        .stat-card.bg-info {
            background: linear-gradient(135deg, #17A2B8 0%, #138496 100%) !important;
            color: white;
        }

        .stat-card.bg-danger {
            background: linear-gradient(135deg, #DC3545 0%, #C82333 100%) !important;
            color: white;
        }

        .stat-content {
            position: relative;
            z-index: 2;
        }

        .stat-number {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
            line-height: 1;
        }

        .stat-label {
            font-size: 0.9rem;
            opacity: 0.9;
            font-weight: 500;
        }

        .stat-icon {
            position: absolute;
            top: 1rem;
            right: 1rem;
            font-size: 3rem;
            opacity: 0.2;
        }

        .card {
            border: none;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.08);
            transition: all 0.3s ease;
        }

        .card:hover {
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.15);
        }

        .card-header {
            background: white;
            border-bottom: 1px solid #dee2e6;
            font-weight: 600;
            padding: 1.25rem 1.5rem;
        }

        .card-body {
            padding: 1.5rem;
        }

        .btn-group-sm > .btn {
            padding: 0.25rem 0.5rem;
            font-size: 0.75rem;
        }

        #coverageMap {
            height: 400px;
            border-radius: 8px;
        }

        .coverage-info {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 1rem;
        }

        .distance-badge {
            font-size: 0.75rem;
            padding: 0.25rem 0.5rem;
        }

        .priority-badge {
            position: absolute;
            top: -5px;
            right: -5px;
            background: var(--danger);
            color: white;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            font-size: 0.7rem;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .feedback-priority {
            border-left: 4px solid;
            padding-left: 1rem;
        }

        .feedback-priority.urgent {
            border-left-color: var(--danger);
        }

        .feedback-priority.important {
            border-left-color: var(--warning);
        }

        .feedback-priority.normal {
            border-left-color: var(--info);
        }

        .feedback-status-badge {
            font-size: 0.75rem;
            padding: 0.25rem 0.5rem;
        }

        .feedback-type-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 1rem;
        }

        .feedback-type-icon.status_update {
            background: rgba(40, 167, 69, 0.1);
            color: var(--success);
        }

        .feedback-type-icon.location_correction {
            background: rgba(255, 193, 7, 0.1);
            color: var(--warning);
        }

        .feedback-type-icon.additional_info {
            background: rgba(23, 162, 184, 0.1);
            color: var(--info);
        }

        .feedback-type-icon.general_comment {
            background: rgba(108, 117, 125, 0.1);
            color: #6c757d;
        }

        @media (max-width: 768px) {
            .sidebar {
                width: 0;
                overflow: hidden;
                transition: width 0.3s ease;
            }

            .sidebar.show {
                width: 260px;
            }

            .main-content {
                margin-left: 0;
            }

            .mobile-menu-btn {
                position: fixed;
                top: 1rem;
                left: 1rem;
                z-index: 1001;
                background: var(--primary);
                border: none;
                color: white;
                padding: 0.5rem;
                border-radius: 8px;
                box-shadow: 0 2px 10px rgba(0, 0, 0, 0.2);
            }
        }

        .table th {
            background-color: #f8f9fa;
            font-weight: 600;
            border-top: none;
        }

        .severity-badge {
            font-size: 0.75rem;
            padding: 0.25rem 0.5rem;
        }

        .status-badge {
            font-size: 0.75rem;
            padding: 0.25rem 0.5rem;
        }
    </style>
</head>
<body>
    <!-- Mobile Menu Button -->
    <button class="btn mobile-menu-btn d-md-none" onclick="toggleSidebar()">
        <i class="fas fa-bars"></i>
    </button>

    <!-- Sidebar -->
    <div class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <a href="?page=dashboard" class="sidebar-brand">
                <i class="fas fa-hard-hat me-2"></i>
                <span>Engineer Panel</span>
            </a>
            <div class="engineer-info">
                <i class="fas fa-user-circle me-1"></i>
                <?php echo htmlspecialchars($engineer['full_name']); ?>
                <br>
                <small><?php echo htmlspecialchars($engineer['specialization'] ?? 'General Engineering'); ?></small>
            </div>
        </div>
        
        <div class="sidebar-menu">
            <ul class="nav flex-column">
                <li class="nav-item">
                    <a class="nav-link <?php echo $current_page === 'dashboard' ? 'active' : ''; ?>" 
                       href="?page=dashboard">
                        <i class="fas fa-tachometer-alt"></i>
                        <span>Dashboard</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo $current_page === 'reports' ? 'active' : ''; ?>" 
                       href="?page=reports">
                        <i class="fas fa-exclamation-triangle"></i>
                        <span>My Area Reports</span>
                        <?php if ($stats['pending'] > 0): ?>
                            <span class="priority-badge"><?php echo $stats['pending']; ?></span>
                        <?php endif; ?>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo $current_page === 'feedback' ? 'active' : ''; ?>" 
                       href="?page=feedback">
                        <i class="fas fa-comments"></i>
                        <span>Feedback Management</span>
                        <?php if ($stats['pending_feedback'] > 0): ?>
                            <span class="priority-badge"><?php echo $stats['pending_feedback']; ?></span>
                        <?php endif; ?>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo $current_page === 'map' ? 'active' : ''; ?>" 
                       href="?page=map">
                        <i class="fas fa-map-marked-alt"></i>
                        <span>Coverage Map</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo $current_page === 'profile' ? 'active' : ''; ?>" 
                       href="?page=profile">
                        <i class="fas fa-user-cog"></i>
                        <span>My Profile</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="index.php" target="_blank">
                        <i class="fas fa-globe"></i>
                        <span>Public Site</span>
                    </a>
                </li>
            </ul>
        </div>
        
        <div class="sidebar-footer">
            <div class="d-grid">
                <a href="?logout=1" class="btn btn-outline-light btn-sm">
                    <i class="fas fa-sign-out-alt me-1"></i>Logout
                </a>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <div class="topbar">
            <div class="d-flex justify-content-between align-items-center">
                <h4 class="page-title mb-0">
                    <?php
                    switch($current_page) {
                        case 'dashboard': echo '<i class="fas fa-tachometer-alt me-2"></i>Dashboard'; break;
                        case 'reports': echo '<i class="fas fa-exclamation-triangle me-2"></i>Area Reports'; break;
                        case 'feedback': echo '<i class="fas fa-comments me-2"></i>Feedback Management'; break;
                        case 'map': echo '<i class="fas fa-map-marked-alt me-2"></i>Coverage Map'; break;
                        case 'profile': echo '<i class="fas fa-user-cog me-2"></i>My Profile'; break;
                        default: echo 'Dashboard';
                    }
                    ?>
                </h4>
                <div class="d-flex align-items-center">
                    <span class="text-muted me-3">
                        <i class="fas fa-map-marker-alt me-1"></i>
                        Coverage: <?php echo number_format($engineer['coverage_radius_meters'] / 1000, 1); ?>km radius
                    </span>
                    <span class="badge bg-success">
                        <i class="fas fa-circle me-1"></i>Online
                    </span>
                </div>
            </div>
        </div>

        <div class="content-area">
            <?php if ($current_page === 'dashboard'): ?>
                <!-- Dashboard Content -->
                <div class="row mb-4">
                    <div class="col-md-2">
                        <div class="stat-card bg-primary">
                            <div class="stat-icon">
                                <i class="fas fa-flag"></i>
                            </div>
                            <div class="stat-content">
                                <div class="stat-number"><?php echo $stats['total_reports']; ?></div>
                                <div class="stat-label">Total Reports</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="stat-card bg-warning">
                            <div class="stat-icon">
                                <i class="fas fa-clock"></i>
                            </div>
                            <div class="stat-content">
                                <div class="stat-number"><?php echo $stats['pending']; ?></div>
                                <div class="stat-label">Pending Review</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="stat-card bg-info">
                            <div class="stat-icon">
                                <i class="fas fa-check"></i>
                            </div>
                            <div class="stat-content">
                                <div class="stat-number"><?php echo $stats['approved']; ?></div>
                                <div class="stat-label">Approved</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="stat-card bg-success">
                            <div class="stat-icon">
                                <i class="fas fa-check-circle"></i>
                            </div>
                            <div class="stat-content">
                                <div class="stat-number"><?php echo $stats['resolved']; ?></div>
                                <div class="stat-label">Resolved</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="stat-card bg-danger">
                            <div class="stat-icon">
                                <i class="fas fa-exclamation-triangle"></i>
                            </div>
                            <div class="stat-content">
                                <div class="stat-number"><?php echo $stats['critical']; ?></div>
                                <div class="stat-label">Critical</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="stat-card bg-info">
                            <div class="stat-icon">
                                <i class="fas fa-comments"></i>
                            </div>
                            <div class="stat-content">
                                <div class="stat-number"><?php echo $stats['total_feedback']; ?></div>
                                <div class="stat-label">Total Feedback</div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-8">
                        <div class="card">
                            <div class="card-header">
                                <h5><i class="fas fa-chart-line me-2"></i>Recent Activity in My Area</h5>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-striped">
                                        <thead>
                                            <tr>
                                                <th>Date</th>
                                                <th>Type</th>
                                                <th>Severity</th>
                                                <th>Status</th>
                                                <th>Distance</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php 
                                            $recent_reports = array_slice($coverage_reports, 0, 10);
                                            foreach ($recent_reports as $report):
                                            ?>
                                            <tr>
                                                <td><?php echo date('M d, Y', strtotime($report['reported_at'])); ?></td>
                                                <td><?php echo ucwords(str_replace('_', ' ', $report['hazard_type'])); ?></td>
                                                <td>
                                                    <span class="badge severity-badge bg-<?php echo $report['severity'] === 'critical' ? 'danger' : ($report['severity'] === 'high' ? 'warning' : ($report['severity'] === 'medium' ? 'info' : 'success')); ?>">
                                                        <?php echo ucfirst($report['severity']); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <span class="badge status-badge bg-<?php echo $report['status'] === 'approved' ? 'success' : ($report['status'] === 'pending' ? 'warning' : 'danger'); ?>">
                                                        <?php echo ucfirst($report['status']); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <span class="badge distance-badge bg-secondary"><?php echo $report['distance']; ?>m</span>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card">
                            <div class="card-header">
                                <h5><i class="fas fa-comments me-2"></i>Recent Feedback</h5>
                            </div>
                            <div class="card-body">
                                <?php 
                                $recent_feedback = array_slice($coverage_feedback, 0, 5);
                                if (empty($recent_feedback)):
                                ?>
                                    <div class="text-center text-muted py-3">
                                        <i class="fas fa-comments fa-2x mb-2"></i>
                                        <p class="mb-0">No feedback available</p>
                                    </div>
                                <?php else: ?>
                                    <?php foreach ($recent_feedback as $feedback): ?>
                                        <div class="feedback-item mb-3 p-3 border rounded">
                                            <div class="d-flex">
                                                <div class="feedback-type-icon <?php echo $feedback['feedback_type']; ?>">
                                                    <i class="fas fa-<?php echo $feedback['feedback_type'] === 'status_update' ? 'flag-checkered' : ($feedback['feedback_type'] === 'location_correction' ? 'map-marker-alt' : ($feedback['feedback_type'] === 'additional_info' ? 'info-circle' : 'comment')); ?>"></i>
                                                </div>
                                                <div class="flex-grow-1">
                                                    <div class="d-flex justify-content-between align-items-start">
                                                        <h6 class="mb-1">Report #<?php echo $feedback['report_id']; ?></h6>
                                                        <span class="badge feedback-status-badge bg-<?php echo $feedback['status'] === 'pending' ? 'warning' : ($feedback['status'] === 'reviewed' ? 'info' : 'success'); ?>">
                                                            <?php echo ucfirst($feedback['status']); ?>
                                                        </span>
                                                    </div>
                                                    <p class="small text-muted mb-1"><?php echo ucwords(str_replace('_', ' ', $feedback['feedback_type'])); ?></p>
                                                    <p class="small mb-2"><?php echo htmlspecialchars(substr($feedback['message'], 0, 80)) . '...'; ?></p>
                                                    <small class="text-muted">
                                                        <i class="fas fa-clock me-1"></i>
                                                        <?php echo date('M d, g:i A', strtotime($feedback['created_at'])); ?>
                                                    </small>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                                
                                <div class="d-grid gap-2 mt-3">
                                    <a href="?page=feedback" class="btn btn-primary btn-sm">
                                        <i class="fas fa-comments me-1"></i>Manage All Feedback
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            <?php elseif ($current_page === 'feedback'): ?>
                <!-- Feedback Management Content -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5><i class="fas fa-filter me-2"></i>Filter Feedback</h5>
                    </div>
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <input type="hidden" name="page" value="feedback">
                            <div class="col-md-3">
                                <label class="form-label">Feedback Status</label>
                                <select class="form-select" name="feedback_status">
                                    <option value="all" <?php echo $feedback_status_filter === 'all' ? 'selected' : ''; ?>>All Status</option>
                                    <option value="pending" <?php echo $feedback_status_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                    <option value="reviewed" <?php echo $feedback_status_filter === 'reviewed' ? 'selected' : ''; ?>>Reviewed</option>
                                    <option value="in_progress" <?php echo $feedback_status_filter === 'in_progress' ? 'selected' : ''; ?>>In Progress</option>
                                    <option value="resolved" <?php echo $feedback_status_filter === 'resolved' ? 'selected' : ''; ?>>Resolved</option>
                                </select>
                            </div>
                            <div class="col-md-9">
                                <label class="form-label">&nbsp;</label>
                                <div>
                                    <button type="submit" class="btn btn-primary">Apply Filter</button>
                                    <a href="?page=feedback" class="btn btn-secondary">Clear</a>
                                    <span class="text-muted ms-3">
                                        <i class="fas fa-info-circle me-1"></i>
                                        Showing <?php echo count($coverage_feedback); ?> feedback items within your coverage area
                                    </span>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Feedback Statistics -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="stat-card bg-warning">
                            <div class="stat-content">
                                <div class="stat-number"><?php echo $stats['pending_feedback']; ?></div>
                                <div class="stat-label">Pending Feedback</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card bg-info">
                            <div class="stat-content">
                                <div class="stat-number"><?php echo $stats['reviewed_feedback']; ?></div>
                                <div class="stat-label">Reviewed</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card bg-success">
                            <div class="stat-content">
                                <div class="stat-number"><?php echo $stats['resolved_feedback']; ?></div>
                                <div class="stat-label">Resolved</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card bg-primary">
                            <div class="stat-content">
                                <div class="stat-number"><?php echo $stats['total_feedback']; ?></div>
                                <div class="stat-label">Total Feedback</div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5><i class="fas fa-comments me-2"></i>Feedback Management</h5>
                        <div>
                            <span class="badge bg-primary me-2"><?php echo count($coverage_feedback); ?> Total</span>
                            <?php if ($stats['pending_feedback'] > 0): ?>
                                <span class="badge bg-warning"><?php echo $stats['pending_feedback']; ?> Pending Review</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="card-body">
                        <?php if (empty($coverage_feedback)): ?>
                            <div class="text-center py-5">
                                <i class="fas fa-comments fa-4x text-muted mb-3"></i>
                                <h5 class="text-muted">No Feedback Available</h5>
                                <p class="text-muted">There is no feedback for reports in your coverage area yet.</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table id="feedbackTable" class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th>Report</th>
                                            <th>Feedback Type</th>
                                            <th>Message</th>
                                            <th>Reporter</th>
                                            <th>Status</th>
                                            <th>Date</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($coverage_feedback as $feedback): ?>
                                        <tr>
                                            <td>
                                                <strong>#<?php echo $feedback['report_id']; ?></strong>
                                                <br>
                                                <small class="text-muted">
                                                    <?php echo ucwords(str_replace('_', ' ', $feedback['hazard_type'])); ?>
                                                    <span class="badge bg-secondary ms-1"><?php echo $feedback['distance']; ?>m</span>
                                                </small>
                                            </td>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <div class="feedback-type-icon <?php echo $feedback['feedback_type']; ?> me-2" style="width: 30px; height: 30px; font-size: 0.8rem;">
                                                        <i class="fas fa-<?php echo $feedback['feedback_type'] === 'status_update' ? 'flag-checkered' : ($feedback['feedback_type'] === 'location_correction' ? 'map-marker-alt' : ($feedback['feedback_type'] === 'additional_info' ? 'info-circle' : 'comment')); ?>"></i>
                                                    </div>
                                                    <span><?php echo ucwords(str_replace('_', ' ', $feedback['feedback_type'])); ?></span>
                                                </div>
                                            </td>
                                            <td>
                                                <div class="feedback-message">
                                                    <?php echo htmlspecialchars(substr($feedback['message'], 0, 100)) . (strlen($feedback['message']) > 100 ? '...' : ''); ?>
                                                </div>
                                            </td>
                                            <td>
                                                <strong><?php echo htmlspecialchars($feedback['reporter_name'] ?: 'Anonymous'); ?></strong>
                                                <?php if ($feedback['reporter_contact']): ?>
                                                    <br><small class="text-muted"><?php echo htmlspecialchars($feedback['reporter_contact']); ?></small>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <span class="badge feedback-status-badge bg-<?php echo $feedback['status'] === 'pending' ? 'warning' : ($feedback['status'] === 'reviewed' ? 'info' : ($feedback['status'] === 'in_progress' ? 'primary' : 'success')); ?>">
                                                    <?php echo ucfirst(str_replace('_', ' ', $feedback['status'])); ?>
                                                </span>
                                                <?php if ($feedback['responded_by_name']): ?>
                                                    <br><small class="text-muted">By: <?php echo htmlspecialchars($feedback['responded_by_name']); ?></small>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo date('M d, Y g:i A', strtotime($feedback['created_at'])); ?></td>
                                            <td>
                                                <div class="btn-group btn-group-sm">
                                                    <button class="btn btn-outline-primary" onclick="viewFeedbackDetails(<?php echo $feedback['id']; ?>)" title="View Details">
                                                        <i class="fas fa-eye"></i>
                                                    </button>
                                                    
                                                    <?php if ($feedback['status'] === 'pending'): ?>
                                                        <button class="btn btn-success" onclick="updateFeedbackStatus(<?php echo $feedback['id']; ?>, 'reviewed')" title="Mark as Reviewed">
                                                            <i class="fas fa-check"></i>
                                                        </button>
                                                        <button class="btn btn-info" onclick="updateFeedbackStatus(<?php echo $feedback['id']; ?>, 'in_progress')" title="Mark as In Progress">
                                                            <i class="fas fa-cog"></i>
                                                        </button>
                                                    <?php endif; ?>
                                                    
                                                    <?php if (in_array($feedback['status'], ['reviewed', 'in_progress'])): ?>
                                                        <button class="btn btn-warning" onclick="updateFeedbackStatus(<?php echo $feedback['id']; ?>, 'resolved')" title="Mark as Resolved">
                                                            <i class="fas fa-check-double"></i>
                                                        </button>
                                                    <?php endif; ?>

                                                    <button class="btn btn-outline-info" onclick="viewReportLocation(<?php echo $feedback['latitude']; ?>, <?php echo $feedback['longitude']; ?>)" title="View Location">
                                                        <i class="fas fa-map-marker-alt"></i>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

            <?php elseif ($current_page === 'reports'): ?>
                <!-- Reports Management Content (existing content) -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5><i class="fas fa-filter me-2"></i>Filter Reports in My Area</h5>
                    </div>
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <input type="hidden" name="page" value="reports">
                            <div class="col-md-3">
                                <label class="form-label">Status</label>
                                <select class="form-select" name="status">
                                    <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>All Status</option>
                                    <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                    <option value="approved" <?php echo $status_filter === 'approved' ? 'selected' : ''; ?>>Approved</option>
                                    <option value="rejected" <?php echo $status_filter === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Severity</label>
                                <select class="form-select" name="severity">
                                    <option value="all" <?php echo $severity_filter === 'all' ? 'selected' : ''; ?>>All Severities</option>
                                    <option value="minor" <?php echo $severity_filter === 'minor' ? 'selected' : ''; ?>>Minor</option>
                                    <option value="medium" <?php echo $severity_filter === 'medium' ? 'selected' : ''; ?>>Medium</option>
                                    <option value="high" <?php echo $severity_filter === 'high' ? 'selected' : ''; ?>>High</option>
                                    <option value="critical" <?php echo $severity_filter === 'critical' ? 'selected' : ''; ?>>Critical</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Type</label>
                                <select class="form-select" name="type">
                                    <option value="all" <?php echo $type_filter === 'all' ? 'selected' : ''; ?>>All Types</option>
                                    <option value="pothole" <?php echo $type_filter === 'pothole' ? 'selected' : ''; ?>>Pothole</option>
                                    <option value="road_crack" <?php echo $type_filter === 'road_crack' ? 'selected' : ''; ?>>Road Crack</option>
                                    <option value="flooding" <?php echo $type_filter === 'flooding' ? 'selected' : ''; ?>>Flooding</option>
                                    <option value="fallen_tree" <?php echo $type_filter === 'fallen_tree' ? 'selected' : ''; ?>>Fallen Tree</option>
                                    <option value="landslide" <?php echo $type_filter === 'landslide' ? 'selected' : ''; ?>>Landslide</option>
                                    <option value="debris" <?php echo $type_filter === 'debris' ? 'selected' : ''; ?>>Road Debris</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Date</label>
                                <input type="date" class="form-control" name="date" value="<?php echo htmlspecialchars($date_filter); ?>">
                            </div>
                            <div class="col-12">
                                <button type="submit" class="btn btn-primary">Apply Filters</button>
                                <a href="?page=reports" class="btn btn-secondary">Clear</a>
                                <span class="text-muted ms-3">
                                    <i class="fas fa-info-circle me-1"></i>
                                    Showing <?php echo count($coverage_reports); ?> reports within your <?php echo number_format($engineer['coverage_radius_meters'] / 1000, 1); ?>km coverage area
                                </span>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5><i class="fas fa-table me-2"></i>Hazard Reports in My Area</h5>
                        <div>
                            <span class="badge bg-primary me-2"><?php echo count($coverage_reports); ?> Total</span>
                            <?php if ($stats['pending'] > 0): ?>
                                <span class="badge bg-warning"><?php echo $stats['pending']; ?> Pending Action</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table id="reportsTable" class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Type</th>
                                        <th>Severity</th>
                                        <th>Location</th>
                                        <th>Distance</th>
                                        <th>Status</th>
                                        <th>Reported</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($coverage_reports as $report): ?>
                                    <tr>
                                        <td>#<?php echo $report['id']; ?></td>
                                        <td><?php echo ucwords(str_replace('_', ' ', $report['hazard_type'])); ?></td>
                                        <td>
                                            <span class="badge severity-badge bg-<?php echo $report['severity'] === 'critical' ? 'danger' : ($report['severity'] === 'high' ? 'warning' : ($report['severity'] === 'medium' ? 'info' : 'success')); ?>">
                                                <?php echo ucfirst($report['severity']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo htmlspecialchars(substr($report['address'], 0, 40)) . '...'; ?></td>
                                        <td>
                                            <span class="badge distance-badge bg-secondary"><?php echo $report['distance']; ?>m</span>
                                        </td>
                                        <td>
                                            <span class="badge status-badge bg-<?php echo $report['status'] === 'approved' ? 'success' : ($report['status'] === 'pending' ? 'warning' : 'danger'); ?>">
                                                <?php echo ucfirst($report['status']); ?>
                                            </span>
                                            <?php if ($report['resolved']): ?>
                                                <br><small class="text-success"><i class="fas fa-check-circle me-1"></i>Resolved</small>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo date('M d, Y g:i A', strtotime($report['reported_at'])); ?></td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <button class="btn btn-outline-primary" onclick="viewDetails(<?php echo $report['id']; ?>)" title="View Details">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                                
                                                <?php if ($report['status'] === 'pending'): ?>
                                                    <button class="btn btn-success" onclick="approveReport(<?php echo $report['id']; ?>)" title="Approve">
                                                        <i class="fas fa-check"></i>
                                                    </button>
                                                    <button class="btn btn-danger" onclick="rejectReport(<?php echo $report['id']; ?>)" title="Reject">
                                                        <i class="fas fa-times"></i>
                                                    </button>
                                                <?php endif; ?>
                                                
                                                <?php if ($report['status'] === 'approved'): ?>
                                                    <?php if (!$report['resolved']): ?>
                                                        <button class="btn btn-warning" onclick="resolveReport(<?php echo $report['id']; ?>)" title="Mark as Resolved">
                                                            <i class="fas fa-check-double"></i>
                                                        </button>
                                                    <?php else: ?>
                                                        <button class="btn btn-outline-warning" onclick="unresolveReport(<?php echo $report['id']; ?>)" title="Mark as Unresolved">
                                                            <i class="fas fa-undo"></i>
                                                        </button>
                                                    <?php endif; ?>
                                                <?php endif; ?>

                                                <button class="btn btn-outline-info" onclick="viewOnMap(<?php echo $report['latitude']; ?>, <?php echo $report['longitude']; ?>)" title="View on Map">
                                                    <i class="fas fa-map-marker-alt"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

            <?php elseif ($current_page === 'map'): ?>
                <!-- Coverage Map Content (existing content) -->
                <div class="row">
                    <div class="col-md-8">
                        <div class="card">
                            <div class="card-header">
                                <h5><i class="fas fa-map me-2"></i>My Coverage Area & Reports</h5>
                            </div>
                            <div class="card-body">
                                <div id="coverageMap"></div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card">
                            <div class="card-header">
                                <h5><i class="fas fa-info-circle me-2"></i>Coverage Information</h5>
                            </div>
                            <div class="card-body">
                                <div class="coverage-info mb-3">
                                    <h6><i class="fas fa-user me-2"></i>Engineer Details</h6>
                                    <p class="mb-1"><strong>Name:</strong> <?php echo htmlspecialchars($engineer['full_name']); ?></p>
                                    <p class="mb-1"><strong>Specialization:</strong> <?php echo htmlspecialchars($engineer['specialization'] ?? 'General Engineering'); ?></p>
                                    <p class="mb-1"><strong>Status:</strong> <span class="badge bg-success">Active</span></p>
                                </div>

                                <div class="coverage-info mb-3">
                                    <h6><i class="fas fa-map-marker-alt me-2"></i>Assignment Area</h6>
                                    <p class="mb-1"><strong>Center Point:</strong><br>
                                        <?php echo number_format($engineer['assigned_latitude'], 6); ?>,<br>
                                        <?php echo number_format($engineer['assigned_longitude'], 6); ?>
                                    </p>
                                    <p class="mb-1"><strong>Coverage Radius:</strong><br>
                                        <?php echo number_format($engineer['coverage_radius_meters']); ?> meters 
                                        (<?php echo number_format($engineer['coverage_radius_meters'] / 1000, 1); ?> km)
                                    </p>
                                    <p class="mb-0"><strong>Coverage Area:</strong><br>
                                        ~<?php echo number_format(3.14159 * pow($engineer['coverage_radius_meters'] / 1000, 2), 1); ?> km²
                                    </p>
                                </div>

                                <div class="coverage-info">
                                    <h6><i class="fas fa-chart-bar me-2"></i>Area Statistics</h6>
                                    <div class="row text-center">
                                        <div class="col-6">
                                            <div class="stat-number text-primary"><?php echo $stats['total_reports']; ?></div>
                                            <div class="stat-label">Total Reports</div>
                                        </div>
                                        <div class="col-6">
                                            <div class="stat-number text-warning"><?php echo $stats['pending']; ?></div>
                                            <div class="stat-label">Pending</div>
                                        </div>
                                        <div class="col-6 mt-2">
                                            <div class="stat-number text-success"><?php echo $stats['resolved']; ?></div>
                                            <div class="stat-label">Resolved</div>
                                        </div>
                                        <div class="col-6 mt-2">
                                            <div class="stat-number text-info"><?php echo $stats['total_feedback']; ?></div>
                                            <div class="stat-label">Feedback</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            <?php elseif ($current_page === 'profile'): ?>
                <!-- Profile Management Content (existing content) -->
                <div class="row">
                    <div class="col-md-8">
                        <div class="card">
                            <div class="card-header">
                                <h5><i class="fas fa-user-edit me-2"></i>Edit Profile</h5>
                            </div>
                            <div class="card-body">
                                <form id="profileForm">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">Full Name</label>
                                                <input type="text" class="form-control" id="fullName" name="full_name" 
                                                       value="<?php echo htmlspecialchars($engineer['full_name']); ?>" required>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">Email</label>
                                                <input type="email" class="form-control" id="email" name="email" 
                                                       value="<?php echo htmlspecialchars($engineer['email'] ?? ''); ?>">
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">Phone Number</label>
                                                <input type="tel" class="form-control" id="phone" name="phone" 
                                                       value="<?php echo htmlspecialchars($engineer['phone'] ?? ''); ?>">
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">Username</label>
                                                <input type="text" class="form-control" 
                                                       value="<?php echo htmlspecialchars($engineer['username']); ?>" readonly>
                                                <small class="text-muted">Username cannot be changed</small>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                        <button type="button" class="btn btn-primary" onclick="updateProfile()">
                                            <i class="fas fa-save me-1"></i>Update Profile
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-4">
                        <div class="card">
                            <div class="card-header">
                                <h5><i class="fas fa-id-card me-2"></i>Account Information</h5>
                            </div>
                            <div class="card-body">
                                <div class="text-center mb-3">
                                    <i class="fas fa-user-circle fa-5x text-muted"></i>
                                </div>
                                
                                <table class="table table-borderless">
                                    <tr>
                                        <td><strong>Engineer ID:</strong></td>
                                        <td>#<?php echo $engineer['id']; ?></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Specialization:</strong></td>
                                        <td><?php echo htmlspecialchars($engineer['specialization'] ?? 'General'); ?></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Status:</strong></td>
                                        <td><span class="badge bg-success">Active</span></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Joined:</strong></td>
                                        <td><?php echo date('M d, Y', strtotime($engineer['created_at'])); ?></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Coverage:</strong></td>
                                        <td><?php echo number_format($engineer['coverage_radius_meters'] / 1000, 1); ?> km</td>
                                    </tr>
                                </table>

                                <div class="alert alert-info">
                                    <i class="fas fa-info-circle me-2"></i>
                                    <strong>Note:</strong> Your assignment area and specialization can only be changed by the administrator.
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Modals -->
    <!-- Report Details Modal -->
    <div class="modal fade" id="detailsModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Report Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="detailsModalBody">
                    <!-- Content will be loaded here -->
                </div>
            </div>
        </div>
    </div>

    <!-- Feedback Details Modal -->
    <div class="modal fade" id="feedbackDetailsModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Feedback Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="feedbackDetailsModalBody">
                    <!-- Content will be loaded here -->
                </div>
            </div>
        </div>
    </div>

    <!-- Feedback Status Update Modal -->
    <div class="modal fade" id="feedbackStatusModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Update Feedback Status</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="feedbackStatusId">
                    <input type="hidden" id="feedbackNewStatus">
                    
                    <div class="mb-3">
                        <label class="form-label">Response Notes</label>
                        <textarea class="form-control" id="feedbackResponseNotes" rows="3" 
                                  placeholder="Enter your response or notes about this feedback..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" onclick="confirmFeedbackStatusUpdate()">Update Status</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Rejection Modal (existing) -->
    <div class="modal fade" id="rejectionModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Reject Report</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Reason for Rejection</label>
                        <textarea class="form-control" id="rejectionReason" rows="3" 
                                  placeholder="Enter reason for rejecting this report..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-danger" onclick="confirmReject()">Reject Report</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Resolution Modal (existing) -->
    <div class="modal fade" id="resolutionModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Mark as Resolved</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Resolution Notes</label>
                        <textarea class="form-control" id="resolutionNotes" rows="3" 
                                  placeholder="Enter notes about how this hazard was resolved..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-success" onclick="confirmResolve()">Mark as Resolved</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.4.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://unpkg.com/leaflet@1.9.3/dist/leaflet.js"></script>

    <script>
        // Global variables
        let currentReportId = null;
        let currentFeedbackId = null;
        let coverageMap = null;
        
        // Engineer data from PHP
        const engineerData = {
            id: <?php echo $engineer['id']; ?>,
            name: "<?php echo htmlspecialchars($engineer['full_name']); ?>",
            lat: <?php echo $engineer['assigned_latitude']; ?>,
            lng: <?php echo $engineer['assigned_longitude']; ?>,
            radius: <?php echo $engineer['coverage_radius_meters']; ?>
        };

        // Reports and feedback data from PHP
        const reportsData = <?php echo json_encode($coverage_reports); ?>;
        const feedbackData = <?php echo json_encode($coverage_feedback); ?>;

        // Initialize page
        $(document).ready(function() {
            // Initialize DataTables
            if ($('#reportsTable').length) {
                $('#reportsTable').DataTable({
                    responsive: true,
                    order: [[6, 'desc']], // Sort by reported date desc
                    pageLength: 25,
                    language: {
                        search: "Search reports:",
                        lengthMenu: "Show _MENU_ reports per page",
                        info: "Showing _START_ to _END_ of _TOTAL_ reports in your area",
                        infoEmpty: "No reports in your coverage area",
                        infoFiltered: "(filtered from _MAX_ total reports)"
                    }
                });
            }

            if ($('#feedbackTable').length) {
                $('#feedbackTable').DataTable({
                    responsive: true,
                    order: [[5, 'desc']], // Sort by date desc
                    pageLength: 15,
                    language: {
                        search: "Search feedback:",
                        lengthMenu: "Show _MENU_ feedback items per page",
                        info: "Showing _START_ to _END_ of _TOTAL_ feedback items",
                        infoEmpty: "No feedback available",
                        infoFiltered: "(filtered from _MAX_ total feedback)"
                    }
                });
            }

            // Initialize coverage map if on map page
            <?php if ($current_page === 'map'): ?>
            initializeCoverageMap();
            <?php endif; ?>
        });

        // Feedback management functions
        function viewFeedbackDetails(feedbackId) {
            const feedback = feedbackData.find(f => f.id == feedbackId);
            
            if (!feedback) {
                showAlert('Error', 'Feedback not found.', 'danger');
                return;
            }
            
            const modalBody = document.getElementById('feedbackDetailsModalBody');
            
            modalBody.innerHTML = `
                <div class="row">
                    <div class="col-md-6">
                        <h6>Feedback Information</h6>
                        <table class="table table-borderless">
                            <tr><td><strong>Feedback ID:</strong></td><td>#${feedback.id}</td></tr>
                            <tr><td><strong>Report ID:</strong></td><td>#${feedback.report_id}</td></tr>
                            <tr><td><strong>Type:</strong></td><td>${feedback.feedback_type.replace(/_/g, ' ')}</td></tr>
                            <tr><td><strong>Status:</strong></td><td><span class="badge bg-warning">${feedback.status}</span></td></tr>
                            <tr><td><strong>Submitted:</strong></td><td>${feedback.created_at}</td></tr>
                            <tr><td><strong>Distance:</strong></td><td>${feedback.distance}m from your center</td></tr>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <h6>Reporter Information</h6>
                        <table class="table table-borderless">
                            <tr><td><strong>Name:</strong></td><td>${feedback.reporter_name || 'Anonymous'}</td></tr>
                            <tr><td><strong>Contact:</strong></td><td>${feedback.reporter_contact || 'Not provided'}</td></tr>
                        </table>
                        
                        <h6>Related Report</h6>
                        <table class="table table-borderless">
                            <tr><td><strong>Hazard Type:</strong></td><td>${feedback.hazard_type.replace(/_/g, ' ')}</td></tr>
                            <tr><td><strong>Severity:</strong></td><td>${feedback.severity}</td></tr>
                            <tr><td><strong>Address:</strong></td><td>${feedback.address}</td></tr>
                        </table>
                    </div>
                </div>
                
                <div class="row mt-3">
                    <div class="col-12">
                        <h6>Feedback Message</h6>
                        <div class="p-3 bg-light rounded">${feedback.message}</div>
                    </div>
                </div>
                
                ${feedback.response_notes ? `
                <div class="row mt-3">
                    <div class="col-12">
                        <h6>Response Notes</h6>
                        <div class="p-3 bg-info bg-opacity-10 rounded">${feedback.response_notes}</div>
                        ${feedback.responded_by_name ? `<small class="text-muted">Responded by: ${feedback.responded_by_name}</small>` : ''}
                    </div>
                </div>
                ` : ''}
                
                <div class="row mt-3">
                    <div class="col-12">
                        <div class="btn-group">
                            ${feedback.status === 'pending' ? `
                                <button class="btn btn-success btn-sm" onclick="updateFeedbackStatus(${feedback.id}, 'reviewed')">Mark as Reviewed</button>
                                <button class="btn btn-info btn-sm" onclick="updateFeedbackStatus(${feedback.id}, 'in_progress')">Mark as In Progress</button>
                            ` : ''}
                            ${feedback.status === 'reviewed' || feedback.status === 'in_progress' ? `
                                <button class="btn btn-warning btn-sm" onclick="updateFeedbackStatus(${feedback.id}, 'resolved')">Mark as Resolved</button>
                            ` : ''}
                            <button class="btn btn-outline-primary btn-sm" onclick="viewReportLocation(${feedback.latitude}, ${feedback.longitude})">View Location</button>
                        </div>
                    </div>
                </div>
            `;
            
            new bootstrap.Modal(document.getElementById('feedbackDetailsModal')).show();
        }

        function updateFeedbackStatus(feedbackId, newStatus) {
            currentFeedbackId = feedbackId;
            document.getElementById('feedbackStatusId').value = feedbackId;
            document.getElementById('feedbackNewStatus').value = newStatus;
            
            // Set modal title based on status
            const statusTitles = {
                'reviewed': 'Mark as Reviewed',
                'in_progress': 'Mark as In Progress',
                'resolved': 'Mark as Resolved'
            };
            
            document.querySelector('#feedbackStatusModal .modal-title').textContent = statusTitles[newStatus] || 'Update Status';
            
            new bootstrap.Modal(document.getElementById('feedbackStatusModal')).show();
        }

        function confirmFeedbackStatusUpdate() {
            const feedbackId = document.getElementById('feedbackStatusId').value;
            const newStatus = document.getElementById('feedbackNewStatus').value;
            const responseNotes = document.getElementById('feedbackResponseNotes').value;
            
            const formData = new FormData();
            formData.append('action', 'update_feedback_status');
            formData.append('feedback_id', feedbackId);
            formData.append('status', newStatus);
            formData.append('response_notes', responseNotes);
            
            fetch('engineer_dashboard.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showAlert('Success', data.message, 'success');
                    bootstrap.Modal.getInstance(document.getElementById('feedbackStatusModal')).hide();
                    setTimeout(() => {
                        window.location.reload();
                    }, 1500);
                } else {
                    showAlert('Error', data.message, 'danger');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showAlert('Error', 'An error occurred while updating feedback status.', 'danger');
            });
        }

        function viewReportLocation(lat, lng) {
            window.open(`?page=map&lat=${lat}&lng=${lng}`, '_blank');
        }

        // Report management functions (existing)
        function viewDetails(reportId) {
            const report = reportsData.find(r => r.id == reportId);
            
            if (!report) {
                showAlert('Error', 'Report not found.', 'danger');
                return;
            }
            
            const modalBody = document.getElementById('detailsModalBody');
            
            modalBody.innerHTML = `
                <div class="row">
                    <div class="col-md-6">
                        <h6>Basic Information</h6>
                        <table class="table table-borderless">
                            <tr><td><strong>Report ID:</strong></td><td>#${report.id}</td></tr>
                            <tr><td><strong>Type:</strong></td><td>${report.hazard_type.replace(/_/g, ' ')}</td></tr>
                            <tr><td><strong>Severity:</strong></td><td><span class="badge bg-warning">${report.severity}</span></td></tr>
                            <tr><td><strong>Status:</strong></td><td><span class="badge bg-info">${report.status}</span></td></tr>
                            <tr><td><strong>Distance:</strong></td><td>${report.distance}m from your center</td></tr>
                            <tr><td><strong>Reported:</strong></td><td>${report.reported_at}</td></tr>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <h6>Location Information</h6>
                        <table class="table table-borderless">
                            <tr><td><strong>Address:</strong></td><td>${report.address}</td></tr>
                            <tr><td><strong>Latitude:</strong></td><td>${report.latitude}</td></tr>
                            <tr><td><strong>Longitude:</strong></td><td>${report.longitude}</td></tr>
                        </table>
                    </div>
                </div>
                ${report.description ? `
                <div class="row mt-3">
                    <div class="col-12">
                        <h6>Description</h6>
                        <div class="p-3 bg-light rounded">${report.description}</div>
                    </div>
                </div>
                ` : ''}
                ${report.image_path ? `
                <div class="row mt-3">
                    <div class="col-12">
                        <h6>Photo</h6>
                        <img src="${report.image_path}" class="img-fluid rounded" style="max-height: 300px;">
                    </div>
                </div>
                ` : ''}
                ${report.approved_by_name ? `
                <div class="row mt-3">
                    <div class="col-12">
                        <h6>Approval Information</h6>
                        <p class="mb-0">Approved by: <strong>${report.approved_by_name}</strong></p>
                    </div>
                </div>
                ` : ''}
                ${report.resolved && report.resolved_by_name ? `
                <div class="row mt-3">
                    <div class="col-12">
                        <h6>Resolution Information</h6>
                        <p class="mb-1">Resolved by: <strong>${report.resolved_by_name}</strong></p>
                        <p class="mb-1">Resolved at: ${report.resolved_at}</p>
                        ${report.resolution_notes ? `<p class="mb-0">Notes: ${report.resolution_notes}</p>` : ''}
                    </div>
                </div>
                ` : ''}
            `;
            
            new bootstrap.Modal(document.getElementById('detailsModal')).show();
        }

        function approveReport(reportId) {
            if (confirm('Are you sure you want to approve this report?')) {
                updateReportStatus(reportId, 'approve');
            }
        }

        function rejectReport(reportId) {
            currentReportId = reportId;
            new bootstrap.Modal(document.getElementById('rejectionModal')).show();
        }

        function confirmReject() {
            const reason = document.getElementById('rejectionReason').value;
            if (!reason.trim()) {
                showAlert('Error', 'Please provide a reason for rejection.', 'warning');
                return;
            }
            
            updateReportStatus(currentReportId, 'reject', { reason: reason });
            bootstrap.Modal.getInstance(document.getElementById('rejectionModal')).hide();
        }

        function resolveReport(reportId) {
            currentReportId = reportId;
            new bootstrap.Modal(document.getElementById('resolutionModal')).show();
        }

        function confirmResolve() {
            const notes = document.getElementById('resolutionNotes').value;
            updateReportStatus(currentReportId, 'resolve', { notes: notes });
            bootstrap.Modal.getInstance(document.getElementById('resolutionModal')).hide();
        }

        function unresolveReport(reportId) {
            if (confirm('Are you sure you want to mark this report as unresolved?')) {
                updateReportStatus(reportId, 'unresolve');
            }
        }

        function updateReportStatus(reportId, action, data = {}) {
            const formData = new FormData();
            formData.append('action', action);
            formData.append('report_id', reportId);
            
            if (action === 'reject' && data.reason) {
                formData.append('reason', data.reason);
            }
            if (action === 'resolve' && data.notes) {
                formData.append('notes', data.notes);
            }
            
            fetch('engineer_dashboard.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showAlert('Success', data.message, 'success');
                    setTimeout(() => {
                        window.location.reload();
                    }, 1500);
                } else {
                    showAlert('Error', data.message, 'danger');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showAlert('Error', 'An error occurred while updating the report.', 'danger');
            });
        }

        function viewOnMap(lat, lng) {
            window.open(`?page=map&lat=${lat}&lng=${lng}`, '_blank');
        }

        // Profile management
        function updateProfile() {
            const form = document.getElementById('profileForm');
            const formData = new FormData(form);
            formData.append('action', 'update_profile');
            
            fetch('engineer_dashboard.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showAlert('Success', data.message, 'success');
                } else {
                    showAlert('Error', data.message, 'danger');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showAlert('Error', 'Failed to update profile.', 'danger');
            });
        }

        // Map initialization (existing code)
        function initializeCoverageMap() {
            coverageMap = L.map('coverageMap').setView([engineerData.lat, engineerData.lng], 13);
            
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
            }).addTo(coverageMap);

            // Add engineer's coverage circle
            L.circle([engineerData.lat, engineerData.lng], {
                radius: engineerData.radius,
                color: '#FF4B4B',
                fillColor: '#FF4B4B',
                fillOpacity: 0.2,
                weight: 2
            }).addTo(coverageMap);

            // Add center marker for engineer
            const engineerIcon = L.divIcon({
                className: 'engineer-marker',
                html: '<div style="background-color: #2C3E50; width: 20px; height: 20px; border-radius: 50%; border: 3px solid white; box-shadow: 0 0 10px rgba(0, 0, 0, 0.3);"></div>',
                iconSize: [20, 20],
                iconAnchor: [10, 10]
            });

            L.marker([engineerData.lat, engineerData.lng], { icon: engineerIcon })
                .bindPopup(`<strong>${engineerData.name}</strong><br>Coverage Center<br>Radius: ${(engineerData.radius/1000).toFixed(1)}km`)
                .addTo(coverageMap);

            // Add reports markers
            reportsData.forEach(report => {
                if (report.latitude && report.longitude) {
                    const marker = createReportMarker(report);
                    marker.addTo(coverageMap);
                }
            });

            setTimeout(() => {
                coverageMap.invalidateSize();
            }, 100);
        }

        function createReportMarker(report) {
            let color;
            switch (report.severity.toLowerCase()) {
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

            const icon = L.divIcon({
                className: 'hazard-marker',
                html: `<div style="background-color: ${color}; width: 18px; height: 18px; border-radius: 50%; border: 2px solid white; box-shadow: 0 0 10px rgba(0, 0, 0, 0.3);"></div>`,
                iconSize: [18, 18],
                iconAnchor: [9, 9]
            });

            const marker = L.marker([parseFloat(report.latitude), parseFloat(report.longitude)], {
                icon: icon
            });

            const popupContent = `
                <div class="hazard-popup">
                    <h6 class="mb-2">#${report.id} - ${report.hazard_type.replace(/_/g, ' ')}</h6>
                    <p class="mb-1"><strong>Severity:</strong> <span class="badge bg-warning">${report.severity}</span></p>
                    <p class="mb-1"><strong>Status:</strong> <span class="badge bg-info">${report.status}</span></p>
                    <p class="mb-1"><strong>Distance:</strong> ${report.distance}m</p>
                    <p class="mb-2"><strong>Address:</strong> ${report.address}</p>
                    <div class="btn-group btn-group-sm w-100">
                        <button class="btn btn-primary btn-sm" onclick="viewDetails(${report.id})">Details</button>
                        ${report.status === 'pending' ? 
                            `<button class="btn btn-success btn-sm" onclick="approveReport(${report.id})">Approve</button>` : 
                            (report.status === 'approved' && !report.resolved ? 
                                `<button class="btn btn-warning btn-sm" onclick="resolveReport(${report.id})">Resolve</button>` : ''
                            )
                        }
                    </div>
                </div>
            `;

            marker.bindPopup(popupContent);

            return marker;
        }

        // Mobile sidebar toggle
        function toggleSidebar() {
            document.getElementById('sidebar').classList.toggle('show');
        }

        // Utility functions
        function showAlert(title, message, type = 'info') {
            // Create alert element
            const alertDiv = document.createElement('div');
            alertDiv.className = `alert alert-${type} alert-dismissible fade show position-fixed`;
            alertDiv.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px;';
            alertDiv.innerHTML = `
                <strong>${title}:</strong> ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            `;
            
            document.body.appendChild(alertDiv);
            
            // Auto remove after 5 seconds
            setTimeout(() => {
                if (alertDiv && alertDiv.parentNode) {
                    alertDiv.remove();
                }
            }, 5000);
        }

        // Handle URL parameters for map view
        function handleMapParams() {
            const urlParams = new URLSearchParams(window.location.search);
            const lat = urlParams.get('lat');
            const lng = urlParams.get('lng');
            
            if (lat && lng && coverageMap) {
                coverageMap.setView([parseFloat(lat), parseFloat(lng)], 16);
                
                // Add temporary marker
                const tempMarker = L.marker([parseFloat(lat), parseFloat(lng)], {
                    icon: L.divIcon({
                        className: 'temp-marker',
                        html: '<div style="background-color: #FF4B4B; width: 25px; height: 25px; border-radius: 50%; border: 3px solid white; box-shadow: 0 0 15px rgba(255, 75, 75, 0.5); animation: pulse 2s infinite;"></div>',
                        iconSize: [25, 25],
                        iconAnchor: [12, 12]
                    })
                }).addTo(coverageMap);
                
                tempMarker.bindPopup("Selected Report Location").openPopup();
            }
        }

        // Call handleMapParams when on map page
        <?php if ($current_page === 'map'): ?>
        setTimeout(handleMapParams, 1000);
        <?php endif; ?>

        // Add pulse animation CSS
        const style = document.createElement('style');
        style.textContent = `
            @keyframes pulse {
                0% { transform: scale(1); opacity: 1; }
                50% { transform: scale(1.2); opacity: 0.7; }
                100% { transform: scale(1); opacity: 1; }
            }
        `;
        document.head.appendChild(style);

        // Auto-refresh data every 5 minutes
        setInterval(() => {
            if (document.visibilityState === 'visible') {
                // Subtle refresh without full page reload
                fetch('engineer_dashboard.php?ajax=1&page=' + '<?php echo $current_page; ?>')
                    .then(response => response.json())
                    .then(data => {
                        // Update counters or other dynamic content
                        console.log('Data refreshed');
                    })
                    .catch(error => {
                        console.log('Auto-refresh failed:', error);
                    });
            }
        }, 300000); // 5 minutes
    </script>
</body>
</html>