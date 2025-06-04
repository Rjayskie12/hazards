<?php
// admin.php - Updated DPWH Admin Panel with Engineer Management and Report Monitoring
require_once 'db_connect.php';

// Simple authentication check
session_start();
if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: login.php');
    exit;
}

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    $action = $_POST['action'];
    
    // Create engineer account
    if ($action === 'create_engineer') {
        $username = $_POST['engineer_username'];
        $password = password_hash($_POST['engineer_password'], PASSWORD_DEFAULT);
        $full_name = $_POST['engineer_name'];
        $email = $_POST['engineer_email'] ?? '';
        $specialization = $_POST['engineer_specialization'];
        $assigned_lat = $_POST['engineer_lat'];
        $assigned_lng = $_POST['engineer_lng'];
        $coverage_radius = $_POST['coverage_radius'];
        
        // Check if username already exists
        $check_sql = "SELECT id FROM users WHERE username = ?";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("s", $username);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows > 0) {
            echo json_encode(['success' => false, 'message' => 'Username already exists']);
            exit;
        }
        
        $sql = "INSERT INTO users (username, email, specialization, assigned_latitude, assigned_longitude, coverage_radius_meters, status, password, full_name, role, created_at) VALUES (?, ?, ?, ?, ?, ?, 'active', ?, ?, 'engineer', NOW())";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sssddiss", $username, $email, $specialization, $assigned_lat, $assigned_lng, $coverage_radius, $password, $full_name);
        
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Engineer account created successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to create engineer account: ' . $conn->error]);
        }
        $stmt->close();
        exit;
    }
    
    // Update engineer account
    if ($action === 'update_engineer') {
        $engineer_id = intval($_POST['engineer_id']);
        $username = $_POST['engineer_username'];
        $full_name = $_POST['engineer_name'];
        $email = $_POST['engineer_email'] ?? '';
        $specialization = $_POST['engineer_specialization'];
        $assigned_lat = $_POST['engineer_lat'];
        $assigned_lng = $_POST['engineer_lng'];
        $coverage_radius = $_POST['coverage_radius'];
        $status = $_POST['engineer_status']; // This will now be properly handled
        
        // Check if username already exists for other users
        $check_sql = "SELECT id FROM users WHERE username = ? AND id != ?";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("si", $username, $engineer_id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows > 0) {
            echo json_encode(['success' => false, 'message' => 'Username already exists']);
            exit;
        }
        
        // Update engineer with status
        $sql = "UPDATE users SET username = ?, email = ?, specialization = ?, assigned_latitude = ?, assigned_longitude = ?, coverage_radius_meters = ?, status = ?, full_name = ?, updated_at = NOW() WHERE id = ? AND role = 'engineer'";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sssddiisi", $username, $email, $specialization, $assigned_lat, $assigned_lng, $coverage_radius, $status, $full_name, $engineer_id);
        
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Engineer account updated successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to update engineer account: ' . $conn->error]);
        }
        $stmt->close();
        exit;
    }
    
    // Delete engineer account
    if ($action === 'delete_engineer') {
        $engineer_id = intval($_POST['engineer_id']);
        
        $sql = "DELETE FROM users WHERE id = ? AND role = 'engineer'";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $engineer_id);
        
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Engineer account deleted successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to delete engineer account']);
        }
        $stmt->close();
        exit;
    }
    
    // Get engineer details for editing
    if ($action === 'get_engineer') {
        $engineer_id = intval($_POST['engineer_id']);
        
        $sql = "SELECT * FROM users WHERE id = ? AND role = 'engineer'";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $engineer_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($row = $result->fetch_assoc()) {
            echo json_encode(['success' => true, 'engineer' => $row]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Engineer not found']);
        }
        $stmt->close();
        exit;
    }
    
    // Toggle engineer status (active/inactive)
    if ($action === 'toggle_engineer_status') {
        $engineer_id = intval($_POST['engineer_id']);
        $new_status = $_POST['new_status']; // 'active' or 'inactive'
        
        // Validate status value
        if (!in_array($new_status, ['active', 'inactive'])) {
            echo json_encode(['success' => false, 'message' => 'Invalid status value']);
            exit;
        }
        
        // Check if engineer exists
        $check_sql = "SELECT id FROM users WHERE id = ? AND role = 'engineer'";
        $check_stmt = $conn->prepare($check_sql);
        if (!$check_stmt) {
            echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]);
            exit;
        }
        
        $check_stmt->bind_param("i", $engineer_id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows === 0) {
            echo json_encode(['success' => false, 'message' => 'Engineer not found']);
            exit;
        }
        $check_stmt->close();
        
        // Update the status
        $sql = "UPDATE users SET status = ? WHERE id = ? AND role = 'engineer'";
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]);
            exit;
        }
        
        $stmt->bind_param("si", $new_status, $engineer_id);
        
        if ($stmt->execute()) {
            if ($stmt->affected_rows > 0) {
                echo json_encode([
                    'success' => true, 
                    'message' => 'Engineer status updated to ' . $new_status . ' successfully',
                    'new_status' => $new_status
                ]);
            } else {
                echo json_encode(['success' => false, 'message' => 'No changes made. Engineer may not exist.']);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Database error: ' . $stmt->error]);
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
$engineer_filter = $_GET['engineer'] ?? 'all';

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

// Get all engineers
$engineers_sql = "SELECT * FROM users WHERE role = 'engineer' ORDER BY status DESC, full_name ASC";
$engineers_result = $conn->query($engineers_sql);
$engineers = $engineers_result->fetch_all(MYSQLI_ASSOC);

// Get all reports
$reports_sql = "SELECT hr.*, 
                u.full_name as approved_by_name, 
                ur.full_name as resolved_by_name 
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

// Filter reports by engineer assignment (40km radius)
$filtered_reports = [];
$engineer_assignments = [];

foreach ($all_reports as $report) {
    if ($report['latitude'] && $report['longitude']) {
        $assigned_engineers = [];
        
        // Check which engineers can handle this report (within 40km)
        foreach ($engineers as $engineer) {
            if ($engineer['assigned_latitude'] && $engineer['assigned_longitude'] && $engineer['status'] === 'active') {
                $distance = calculateDistance(
                    $engineer['assigned_latitude'], 
                    $engineer['assigned_longitude'],
                    $report['latitude'], 
                    $report['longitude']
                );
                
                // 40km = 40,000 meters
                if ($distance <= 40000) {
                    $assigned_engineers[] = [
                        'id' => $engineer['id'],
                        'name' => $engineer['full_name'],
                        'specialization' => $engineer['specialization'],
                        'distance' => round($distance / 1000, 1) // Convert to km
                    ];
                }
            }
        }
        
        // Apply engineer filter if specified
        if ($engineer_filter !== 'all') {
            $engineer_can_handle = false;
            foreach ($assigned_engineers as $assigned_eng) {
                if ($assigned_eng['id'] == $engineer_filter) {
                    $engineer_can_handle = true;
                    break;
                }
            }
            if (!$engineer_can_handle) {
                continue; // Skip this report if filtered engineer can't handle it
            }
        }
        
        $report['assigned_engineers'] = $assigned_engineers;
        $report['coverage_status'] = !empty($assigned_engineers) ? 'covered' : 'uncovered';
        $filtered_reports[] = $report;
        
        // Store engineer assignments for statistics
        foreach ($assigned_engineers as $eng) {
            if (!isset($engineer_assignments[$eng['id']])) {
                $engineer_assignments[$eng['id']] = 0;
            }
            $engineer_assignments[$eng['id']]++;
        }
    }
}

// Get statistics
$stats = [
    'total_reports' => count($filtered_reports),
    'covered_reports' => count(array_filter($filtered_reports, function($r) { return $r['coverage_status'] === 'covered'; })),
    'uncovered_reports' => count(array_filter($filtered_reports, function($r) { return $r['coverage_status'] === 'uncovered'; })),
    'pending' => count(array_filter($filtered_reports, function($r) { return $r['status'] === 'pending'; })),
    'approved' => count(array_filter($filtered_reports, function($r) { return $r['status'] === 'approved'; })),
    'resolved' => count(array_filter($filtered_reports, function($r) { return $r['resolved'] == 1; })),
    'critical' => count(array_filter($filtered_reports, function($r) { return $r['severity'] === 'critical'; })),
    'high' => count(array_filter($filtered_reports, function($r) { return $r['severity'] === 'high'; })),
    'total_engineers' => count($engineers),
    'active_engineers' => count(array_filter($engineers, function($e) { return $e['status'] === 'active'; })),
    'inactive_engineers' => count(array_filter($engineers, function($e) { return $e['status'] === 'inactive'; }))
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DPWH Admin Panel - RoadSense</title>
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
            width: 250px;
            background: #2C3E50;
            color: white;
            z-index: 1000;
            display: flex;
            flex-direction: column;
        }

        .sidebar-header {
            padding: 1.5rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .sidebar-brand {
            font-size: 1.25rem;
            font-weight: 600;
            color: white;
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
        }

        .sidebar-menu .nav-link:hover {
            background: rgba(255, 255, 255, 0.1);
            color: white;
        }

        .sidebar-menu .nav-link.active {
            background: #FF4B4B;
            color: white;
        }

        .sidebar-menu .nav-link i {
            width: 20px;
            margin-right: 10px;
        }

        .sidebar-footer {
            padding: 1rem 1.5rem;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
        }

        .main-content {
            margin-left: 250px;
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
            border-radius: 10px;
            padding: 1.5rem;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
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
            background: linear-gradient(135deg, #FF4B4B 0%, #E53935 100%) !important;
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
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }

        .stat-label {
            font-size: 0.9rem;
            opacity: 0.9;
        }

        .stat-icon {
            position: absolute;
            top: 1rem;
            right: 1rem;
            font-size: 2.5rem;
            opacity: 0.3;
        }

        .card {
            border: none;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }

        .card-header {
            background: white;
            border-bottom: 1px solid #dee2e6;
            font-weight: 600;
        }

        .btn-group-sm > .btn {
            padding: 0.25rem 0.5rem;
            font-size: 0.75rem;
        }

        #coverageMap, #locationMap {
            height: 300px;
            border-radius: 8px;
        }

        .engineer-status-badge {
            font-size: 0.75rem;
            padding: 0.25rem 0.5rem;
        }

        .coverage-info {
            font-size: 0.85rem;
            color: #6c757d;
        }

        .engineer-workload {
            font-size: 0.8rem;
            color: #495057;
        }

        .report-coverage-indicator {
            font-size: 0.75rem;
            padding: 0.25rem 0.5rem;
        }

        @media (max-width: 768px) {
            .sidebar {
                width: 0;
                overflow: hidden;
                transition: width 0.3s ease;
            }

            .sidebar.show {
                width: 250px;
            }

            .main-content {
                margin-left: 0;
            }
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="sidebar-header">
            <div class="sidebar-brand">
                <i class="fas fa-shield-alt me-2"></i>
                <span>DPWH Admin</span>
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
                    <a class="nav-link <?php echo $current_page === 'reports_monitoring' ? 'active' : ''; ?>" 
                       href="?page=reports_monitoring">
                        <i class="fas fa-chart-line"></i>
                        <span>Reports Monitoring</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo $current_page === 'engineers_management' ? 'active' : ''; ?>" 
                       href="?page=engineers_management">
                        <i class="fas fa-users-cog"></i>
                        <span>Engineers Management</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo $current_page === 'coverage_analysis' ? 'active' : ''; ?>" 
                       href="?page=coverage_analysis">
                        <i class="fas fa-map-marked-alt"></i>
                        <span>Coverage Analysis</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo $current_page === 'system_reports' ? 'active' : ''; ?>" 
                       href="?page=system_reports">
                        <i class="fas fa-file-alt"></i>
                        <span>System Reports</span>
                    </a>
                </li>
            </ul>
        </div>
        
        <div class="sidebar-footer">
            <div class="user-info mb-2">
                <i class="fas fa-user-circle me-2"></i>
                <span><?php echo htmlspecialchars($_SESSION['admin_username']); ?></span>
            </div>
            <a href="?logout=1" class="btn btn-outline-light btn-sm">
                <i class="fas fa-sign-out-alt me-1"></i>Logout
            </a>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <div class="topbar">
            <div class="d-flex justify-content-between align-items-center">
                <h4 class="page-title">
                    <?php
                    switch($current_page) {
                        case 'dashboard': echo 'System Overview Dashboard'; break;
                        case 'reports_monitoring': echo 'Reports Monitoring'; break;
                        case 'engineers_management': echo 'Engineers Management'; break;
                        case 'coverage_analysis': echo 'Coverage Analysis'; break;
                        case 'system_reports': echo 'System Reports'; break;
                        default: echo 'Dashboard';
                    }
                    ?>
                </h4>
                <div>
                    <a href="index.php" class="btn btn-outline-primary btn-sm me-2">
                        <i class="fas fa-globe me-1"></i>Public Site
                    </a>
                    <span class="text-muted">
                        Welcome, <?php echo htmlspecialchars($_SESSION['admin_name'] ?? $_SESSION['admin_username']); ?>
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
                        <div class="stat-card bg-success">
                            <div class="stat-icon">
                                <i class="fas fa-shield-alt"></i>
                            </div>
                            <div class="stat-content">
                                <div class="stat-number"><?php echo $stats['covered_reports']; ?></div>
                                <div class="stat-label">Covered Reports</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="stat-card bg-warning">
                            <div class="stat-icon">
                                <i class="fas fa-exclamation-triangle"></i>
                            </div>
                            <div class="stat-content">
                                <div class="stat-number"><?php echo $stats['uncovered_reports']; ?></div>
                                <div class="stat-label">Uncovered Reports</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="stat-card bg-info">
                            <div class="stat-icon">
                                <i class="fas fa-users"></i>
                            </div>
                            <div class="stat-content">
                                <div class="stat-number"><?php echo $stats['active_engineers']; ?></div>
                                <div class="stat-label">Active Engineers</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="stat-card bg-danger">
                            <div class="stat-icon">
                                <i class="fas fa-exclamation-circle"></i>
                            </div>
                            <div class="stat-content">
                                <div class="stat-number"><?php echo $stats['critical']; ?></div>
                                <div class="stat-label">Critical Reports</div>
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
                                <div class="stat-label">Pending Reports</div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-8">
                        <div class="card">
                            <div class="card-header">
                                <h5><i class="fas fa-chart-line me-2"></i>Recent System Activity</h5>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-striped">
                                        <thead>
                                            <tr>
                                                <th>Date</th>
                                                <th>Type</th>
                                                <th>Severity</th>
                                                <th>Coverage</th>
                                                <th>Status</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php 
                                            $recent_reports = array_slice($filtered_reports, 0, 10);
                                            foreach ($recent_reports as $report):
                                            ?>
                                            <tr>
                                                <td><?php echo date('M d, Y', strtotime($report['reported_at'])); ?></td>
                                                <td><?php echo ucwords(str_replace('_', ' ', $report['hazard_type'])); ?></td>
                                                <td>
                                                    <span class="badge bg-<?php echo $report['severity'] === 'critical' ? 'danger' : ($report['severity'] === 'high' ? 'warning' : ($report['severity'] === 'medium' ? 'info' : 'success')); ?>">
                                                        <?php echo ucfirst($report['severity']); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <span class="badge report-coverage-indicator bg-<?php echo $report['coverage_status'] === 'covered' ? 'success' : 'warning'; ?>">
                                                        <?php echo $report['coverage_status'] === 'covered' ? count($report['assigned_engineers']) . ' Engineers' : 'No Coverage'; ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <span class="badge bg-<?php echo $report['status'] === 'approved' ? 'success' : ($report['status'] === 'pending' ? 'warning' : 'danger'); ?>">
                                                        <?php echo ucfirst($report['status']); ?>
                                                    </span>
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
                                <h5><i class="fas fa-users-cog me-2"></i>Engineer Status</h5>
                            </div>
                            <div class="card-body">
                                <div class="row text-center mb-3">
                                    <div class="col-6">
                                        <div class="stat-number text-success"><?php echo $stats['active_engineers']; ?></div>
                                        <div class="stat-label">Active</div>
                                    </div>
                                    <div class="col-6">
                                        <div class="stat-number text-danger"><?php echo $stats['inactive_engineers']; ?></div>
                                        <div class="stat-label">Inactive</div>
                                    </div>
                                </div>
                                
                                <h6>Top Performing Engineers</h6>
                                <?php 
                                arsort($engineer_assignments);
                                $top_engineers = array_slice($engineer_assignments, 0, 5, true);
                                foreach ($top_engineers as $eng_id => $report_count):
                                    $eng_info = array_filter($engineers, function($e) use ($eng_id) { return $e['id'] == $eng_id; });
                                    $eng_info = reset($eng_info);
                                    if ($eng_info):
                                ?>
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <div>
                                        <strong><?php echo htmlspecialchars($eng_info['full_name']); ?></strong>
                                        <br><small class="text-muted"><?php echo htmlspecialchars($eng_info['specialization']); ?></small>
                                    </div>
                                    <span class="badge bg-primary"><?php echo $report_count; ?> reports</span>
                                </div>
                                <?php endif; endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>

            <?php elseif ($current_page === 'reports_monitoring'): ?>
                <!-- Reports Monitoring Content -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5><i class="fas fa-filter me-2"></i>Filter Reports</h5>
                    </div>
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <input type="hidden" name="page" value="reports_monitoring">
                            <div class="col-md-2">
                                <label class="form-label">Status</label>
                                <select class="form-select" name="status">
                                    <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>All Status</option>
                                    <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                    <option value="approved" <?php echo $status_filter === 'approved' ? 'selected' : ''; ?>>Approved</option>
                                    <option value="rejected" <?php echo $status_filter === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Severity</label>
                                <select class="form-select" name="severity">
                                    <option value="all" <?php echo $severity_filter === 'all' ? 'selected' : ''; ?>>All Severities</option>
                                    <option value="minor" <?php echo $severity_filter === 'minor' ? 'selected' : ''; ?>>Minor</option>
                                    <option value="medium" <?php echo $severity_filter === 'medium' ? 'selected' : ''; ?>>Medium</option>
                                    <option value="high" <?php echo $severity_filter === 'high' ? 'selected' : ''; ?>>High</option>
                                    <option value="critical" <?php echo $severity_filter === 'critical' ? 'selected' : ''; ?>>Critical</option>
                                </select>
                            </div>
                            <div class="col-md-2">
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
                            <div class="col-md-2">
                                <label class="form-label">Engineer</label>
                                <select class="form-select" name="engineer">
                                    <option value="all" <?php echo $engineer_filter === 'all' ? 'selected' : ''; ?>>All Engineers</option>
                                    <?php foreach ($engineers as $engineer): ?>
                                        <option value="<?php echo $engineer['id']; ?>" <?php echo $engineer_filter == $engineer['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($engineer['full_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Date</label>
                                <input type="date" class="form-control" name="date" value="<?php echo htmlspecialchars($date_filter); ?>">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">&nbsp;</label>
                                <div>
                                    <button type="submit" class="btn btn-primary">Apply</button>
                                    <a href="?page=reports_monitoring" class="btn btn-secondary">Clear</a>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5><i class="fas fa-table me-2"></i>Hazard Reports Monitoring</h5>
                        <div>
                            <span class="badge bg-primary me-2"><?php echo count($filtered_reports); ?> Total</span>
                            <span class="badge bg-success me-2"><?php echo $stats['covered_reports']; ?> Covered</span>
                            <span class="badge bg-warning"><?php echo $stats['uncovered_reports']; ?> Uncovered</span>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            <strong>Note:</strong> As an administrator, you can monitor all reports but cannot approve/reject them. 
                            Engineers are responsible for report validation within their assigned areas (40km radius).
                        </div>
                        
                        <div class="table-responsive">
                            <table id="reportsTable" class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Type</th>
                                        <th>Severity</th>
                                        <th>Location</th>
                                        <th>Coverage</th>
                                        <th>Status</th>
                                        <th>Reported</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($filtered_reports as $report): ?>
                                    <tr>
                                        <td>#<?php echo $report['id']; ?></td>
                                        <td><?php echo ucwords(str_replace('_', ' ', $report['hazard_type'])); ?></td>
                                        <td>
                                            <span class="badge bg-<?php echo $report['severity'] === 'critical' ? 'danger' : ($report['severity'] === 'high' ? 'warning' : ($report['severity'] === 'medium' ? 'info' : 'success')); ?>">
                                                <?php echo ucfirst($report['severity']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo htmlspecialchars(substr($report['address'], 0, 40)) . '...'; ?></td>
                                        <td>
                                            <?php if ($report['coverage_status'] === 'covered'): ?>
                                                <span class="badge bg-success report-coverage-indicator">
                                                    <?php echo count($report['assigned_engineers']); ?> Engineer(s)
                                                </span>
                                                <br>
                                                <small class="text-muted">
                                                    <?php 
                                                    $eng_names = array_map(function($e) { return $e['name']; }, array_slice($report['assigned_engineers'], 0, 2));
                                                    echo implode(', ', $eng_names);
                                                    if (count($report['assigned_engineers']) > 2) {
                                                        echo ' +' . (count($report['assigned_engineers']) - 2) . ' more';
                                                    }
                                                    ?>
                                                </small>
                                            <?php else: ?>
                                                <span class="badge bg-warning report-coverage-indicator">No Coverage</span>
                                                <br><small class="text-danger">Outside 40km range</small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?php echo $report['status'] === 'approved' ? 'success' : ($report['status'] === 'pending' ? 'warning' : 'danger'); ?>">
                                                <?php echo ucfirst($report['status']); ?>
                                            </span>
                                            <?php if ($report['resolved']): ?>
                                                <br><small class="text-success"><i class="fas fa-check-circle me-1"></i>Resolved</small>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo date('M d, Y g:i A', strtotime($report['reported_at'])); ?></td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <button class="btn btn-outline-primary" onclick="viewReportDetails(<?php echo $report['id']; ?>)" title="View Details">
                                                    <i class="fas fa-eye"></i>
                                                </button>
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

            <?php elseif ($current_page === 'engineers_management'): ?>
                <!-- Engineers Management Content -->
                <div class="card mb-4">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5><i class="fas fa-users-cog me-2"></i>Engineers Management</h5>
                        <button class="btn btn-primary" onclick="showCreateEngineerForm()">
                            <i class="fas fa-plus me-1"></i>Add New Engineer
                        </button>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped" id="engineersTable">
                                <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th>Username</th>
                                        <th>Email</th>
                                        <th>Specialization</th>
                                        <th>Coverage Area</th>
                                        <th>Workload</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($engineers as $engineer): ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo htmlspecialchars($engineer['full_name']); ?></strong>
                                            <br><small class="text-muted">ID: #<?php echo $engineer['id']; ?></small>
                                        </td>
                                        <td><?php echo htmlspecialchars($engineer['username']); ?></td>
                                        <td><?php echo htmlspecialchars($engineer['email'] ?? 'Not provided'); ?></td>
                                        <td>
                                            <span class="badge bg-info">
                                                <?php echo htmlspecialchars($engineer['specialization'] ?? 'General'); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($engineer['assigned_latitude'] && $engineer['assigned_longitude']): ?>
                                                <div class="coverage-info">
                                                    <strong>Center:</strong> <?php echo number_format($engineer['assigned_latitude'], 4); ?>, <?php echo number_format($engineer['assigned_longitude'], 4); ?>
                                                    <br><strong>Radius:</strong> <?php echo number_format($engineer['coverage_radius_meters'] / 1000, 1); ?> km
                                                    <br><strong>Area:</strong> ~<?php echo number_format(3.14159 * pow($engineer['coverage_radius_meters'] / 1000, 2), 1); ?> km²
                                                </div>
                                            <?php else: ?>
                                                <span class="text-warning">Not Assigned</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php 
                                            $workload = $engineer_assignments[$engineer['id']] ?? 0;
                                            $workload_color = $workload > 20 ? 'danger' : ($workload > 10 ? 'warning' : 'success');
                                            ?>
                                            <span class="badge bg-<?php echo $workload_color; ?> engineer-workload">
                                                <?php echo $workload; ?> reports
                                            </span>
                                            <?php if ($workload > 15): ?>
                                                <br><small class="text-danger">High workload</small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="form-check form-switch">
                                                <input class="form-check-input" type="checkbox" 
                                                       id="status_<?php echo $engineer['id']; ?>"
                                                       <?php echo $engineer['status'] === 'active' ? 'checked' : ''; ?>
                                                       onchange="toggleEngineerStatus(<?php echo $engineer['id']; ?>, this.checked)">
                                                <label class="form-check-label" for="status_<?php echo $engineer['id']; ?>">
                                                    <span class="badge engineer-status-badge bg-<?php echo $engineer['status'] === 'active' ? 'success' : 'secondary'; ?>">
                                                        <?php echo ucfirst($engineer['status']); ?>
                                                    </span>
                                                </label>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <button class="btn btn-outline-primary" onclick="editEngineer(<?php echo $engineer['id']; ?>)" title="Edit">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <button class="btn btn-outline-info" onclick="viewEngineerCoverage(<?php echo $engineer['id']; ?>)" title="View Coverage">
                                                    <i class="fas fa-map"></i>
                                                </button>
                                                <button class="btn btn-outline-danger" onclick="deleteEngineer(<?php echo $engineer['id']; ?>)" title="Delete">
                                                    <i class="fas fa-trash"></i>
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

            <?php elseif ($current_page === 'coverage_analysis'): ?>
                <!-- Coverage Analysis Content -->
                <div class="row">
                    <div class="col-md-8">
                        <div class="card">
                            <div class="card-header">
                                <h5><i class="fas fa-map me-2"></i>System Coverage Map</h5>
                            </div>
                            <div class="card-body">
                                <div id="coverageMap"></div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-4">
                        <div class="card">
                            <div class="card-header">
                                <h5><i class="fas fa-chart-pie me-2"></i>Coverage Statistics</h5>
                            </div>
                            <div class="card-body">
                                <div class="row text-center mb-4">
                                    <div class="col-6">
                                        <div class="stat-number text-success"><?php echo $stats['covered_reports']; ?></div>
                                        <div class="stat-label">Covered Reports</div>
                                    </div>
                                    <div class="col-6">
                                        <div class="stat-number text-warning"><?php echo $stats['uncovered_reports']; ?></div>
                                        <div class="stat-label">Uncovered Reports</div>
                                    </div>
                                </div>
                                
                                <div class="progress mb-3">
                                    <div class="progress-bar bg-success" style="width: <?php echo $stats['total_reports'] > 0 ? ($stats['covered_reports'] / $stats['total_reports']) * 100 : 0; ?>%"></div>
                                </div>
                                <p class="text-center">
                                    <strong><?php echo $stats['total_reports'] > 0 ? number_format(($stats['covered_reports'] / $stats['total_reports']) * 100, 1) : 0; ?>%</strong> Coverage Rate
                                </p>
                                
                                <hr>
                                
                                <h6>Coverage Issues</h6>
                                <?php if ($stats['uncovered_reports'] > 0): ?>
                                    <div class="alert alert-warning">
                                        <i class="fas fa-exclamation-triangle me-2"></i>
                                        <strong><?php echo $stats['uncovered_reports']; ?></strong> reports are outside the 40km coverage radius of all engineers.
                                    </div>
                                    <p class="small">Consider:</p>
                                    <ul class="small">
                                        <li>Adding engineers in uncovered areas</li>
                                        <li>Extending coverage radius</li>
                                        <li>Reassigning engineer locations</li>
                                    </ul>
                                <?php else: ?>
                                    <div class="alert alert-success">
                                        <i class="fas fa-check-circle me-2"></i>
                                        All reports are covered by at least one engineer!
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="card mt-3">
                            <div class="card-header">
                                <h5><i class="fas fa-users me-2"></i>Engineer Performance</h5>
                            </div>
                            <div class="card-body">
                                <?php 
                                arsort($engineer_assignments);
                                foreach ($engineer_assignments as $eng_id => $report_count):
                                    $eng_info = array_filter($engineers, function($e) use ($eng_id) { return $e['id'] == $eng_id; });
                                    $eng_info = reset($eng_info);
                                    if ($eng_info):
                                        $workload_percentage = $report_count > 0 ? min(($report_count / 25) * 100, 100) : 0;
                                ?>
                                <div class="mb-3">
                                    <div class="d-flex justify-content-between">
                                        <strong><?php echo htmlspecialchars($eng_info['full_name']); ?></strong>
                                        <span class="badge bg-primary"><?php echo $report_count; ?></span>
                                    </div>
                                    <div class="progress mt-1">
                                        <div class="progress-bar" style="width: <?php echo $workload_percentage; ?>%"></div>
                                    </div>
                                    <small class="text-muted"><?php echo htmlspecialchars($eng_info['specialization']); ?></small>
                                </div>
                                <?php endif; endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>

            <?php elseif ($current_page === 'system_reports'): ?>
                <!-- System Reports Content -->
                <div class="row mb-4">
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h5><i class="fas fa-chart-pie me-2"></i>Reports by Type</h5>
                            </div>
                            <div class="card-body">
                                <canvas id="typeChart" width="400" height="200"></canvas>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h5><i class="fas fa-chart-bar me-2"></i>Reports by Severity</h5>
                            </div>
                            <div class="card-body">
                                <canvas id="severityChart" width="400" height="200"></canvas>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5><i class="fas fa-download me-2"></i>Export System Reports</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <h6>Quick Exports</h6>
                                <div class="d-grid gap-2">
                                    <a href="export_reports.php?format=csv" class="btn btn-outline-primary">
                                        <i class="fas fa-file-csv me-2"></i>Export All Reports to CSV
                                    </a>
                                    <a href="export_reports.php?format=json" class="btn btn-outline-info">
                                        <i class="fas fa-file-code me-2"></i>Export All Reports to JSON
                                    </a>
                                    <a href="export_reports.php?status=pending" class="btn btn-outline-warning">
                                        <i class="fas fa-clock me-2"></i>Export Pending Reports
                                    </a>
                                    <a href="export_reports.php?coverage=uncovered" class="btn btn-outline-danger">
                                        <i class="fas fa-exclamation-triangle me-2"></i>Export Uncovered Reports
                                    </a>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <h6>Custom Export</h6>
                                <form action="export_reports.php" method="GET">
                                    <div class="mb-3">
                                        <label class="form-label">Date Range</label>
                                        <div class="row">
                                            <div class="col-6">
                                                <input type="date" class="form-control" name="start_date">
                                            </div>
                                            <div class="col-6">
                                                <input type="date" class="form-control" name="end_date">
                                            </div>
                                        </div>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Status</label>
                                        <select class="form-select" name="status">
                                            <option value="all">All Status</option>
                                            <option value="pending">Pending</option>
                                            <option value="approved">Approved</option>
                                            <option value="rejected">Rejected</option>
                                        </select>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Engineer</label>
                                        <select class="form-select" name="engineer">
                                            <option value="all">All Engineers</option>
                                            <?php foreach ($engineers as $engineer): ?>
                                                <option value="<?php echo $engineer['id']; ?>">
                                                    <?php echo htmlspecialchars($engineer['full_name']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-download me-1"></i>Export Custom
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Report Details Modal -->
    <div class="modal fade" id="reportDetailsModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Report Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="reportDetailsBody">
                    <!-- Content will be loaded here -->
                </div>
            </div>
        </div>
    </div>

    <!-- Engineer Management Modal -->
    <div class="modal fade" id="engineerModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="engineerModalTitle">Create Engineer Account</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="engineerForm">
                        <input type="hidden" id="engineerId" name="engineer_id">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Full Name *</label>
                                    <input type="text" class="form-control" id="engineerName" name="engineer_name" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Username *</label>
                                    <input type="text" class="form-control" id="engineerUsername" name="engineer_username" required>
                                </div>
                                
                                <div class="mb-3" id="passwordField">
                                    <label class="form-label">Password *</label>
                                    <input type="password" class="form-control" id="engineerPassword" name="engineer_password">
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Email</label>
                                    <input type="email" class="form-control" id="engineerEmail" name="engineer_email">
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Specialization</label>
                                    <select class="form-select" id="engineerSpecialization" name="engineer_specialization">
                                        <option value="general">General Engineering</option>
                                        <option value="roads">Road Construction</option>
                                        <option value="bridges">Bridge Engineering</option>
                                        <option value="drainage">Drainage Systems</option>
                                        <option value="traffic">Traffic Management</option>
                                    </select>
                                </div>
                                
                                <div class="mb-3" id="statusField" style="display: none;">
                                    <label class="form-label">Status</label>
                                    <select class="form-select" id="engineerStatus" name="engineer_status">
                                        <option value="active">Active</option>
                                        <option value="inactive">Inactive</option>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Assigned Address *</label>
                                    <div class="location-input-container">
                                        <input type="text" class="form-control" id="assignedAddress" name="assigned_address" 
                                               placeholder="Search for address..." required 
                                               oninput="searchAddresses(this.value)" 
                                               onkeydown="navigateAddressSuggestions(event)">
                                        <div id="addressSuggestions" class="location-suggestions"></div>
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <div class="col-6">
                                        <div class="mb-3">
                                            <label class="form-label">Latitude *</label>
                                            <input type="number" class="form-control" id="engineerLat" name="engineer_lat" 
                                                   step="any" required readonly>
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <div class="mb-3">
                                            <label class="form-label">Longitude *</label>
                                            <input type="number" class="form-control" id="engineerLng" name="engineer_lng" 
                                                   step="any" required readonly>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Coverage Radius (meters)</label>
                                    <input type="number" class="form-control" id="coverageRadius" name="coverage_radius" 
                                           value="5000" min="1000" max="50000" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Location Preview</label>
                                    <div id="locationMap"></div>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" onclick="saveEngineer()">
                        <i class="fas fa-save me-1"></i>Save Engineer
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.4.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://unpkg.com/leaflet@1.9.3/dist/leaflet.js"></script>

    <script>
        // TomTom API Key
        const apiKey = "QsG5BtRmhcKECyn9fcSPaDojqAyH440R";
        
        // Global variables
        let currentEngineerId = null;
        let coverageMap = null;
        let locationMap = null;
        let locationMarker = null;
        let coverageCircle = null;
        let addressSearchTimeout = null;
        let isEditMode = false;

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
                        info: "Showing _START_ to _END_ of _TOTAL_ reports",
                        infoEmpty: "No reports available",
                        infoFiltered: "(filtered from _MAX_ total reports)"
                    }
                });
            }

            if ($('#engineersTable').length) {
                $('#engineersTable').DataTable({
                    responsive: true,
                    order: [[6, 'desc']], // Sort by status (active first)
                    pageLength: 15
                });
            }

            // Initialize charts if on system reports page
            <?php if ($current_page === 'system_reports'): ?>
            initializeCharts();
            <?php endif; ?>

            // Initialize coverage map if on coverage analysis page
            <?php if ($current_page === 'coverage_analysis'): ?>
            initializeCoverageMap();
            <?php endif; ?>
        });

        // Engineer management functions
        function showCreateEngineerForm() {
            isEditMode = false;
            currentEngineerId = null;
            document.getElementById('engineerModalTitle').textContent = 'Create Engineer Account';
            document.getElementById('engineerForm').reset();
            document.getElementById('engineerId').value = '';
            document.getElementById('passwordField').style.display = 'block';
            document.getElementById('engineerPassword').required = true;
            document.getElementById('statusField').style.display = 'none';
            
            // Clear map
            if (locationMap) {
                locationMap.remove();
                locationMap = null;
            }
            
            new bootstrap.Modal(document.getElementById('engineerModal')).show();
            
            // Initialize location map after modal is shown
            setTimeout(() => {
                initializeLocationMap();
            }, 300);
        }

        function editEngineer(engineerId) {
            isEditMode = true;
            currentEngineerId = engineerId;
            document.getElementById('engineerModalTitle').textContent = 'Edit Engineer Account';
            document.getElementById('passwordField').style.display = 'none';
            document.getElementById('engineerPassword').required = false;
            document.getElementById('statusField').style.display = 'block';
            
            // Fetch engineer details
            const formData = new FormData();
            formData.append('action', 'get_engineer');
            formData.append('engineer_id', engineerId);
            
            fetch('admin.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const engineer = data.engineer;
                    
                    // Populate form fields
                    document.getElementById('engineerId').value = engineer.id;
                    document.getElementById('engineerName').value = engineer.full_name;
                    document.getElementById('engineerUsername').value = engineer.username;
                    document.getElementById('engineerEmail').value = engineer.email || '';
                    document.getElementById('engineerSpecialization').value = engineer.specialization || 'general';
                    document.getElementById('engineerStatus').value = engineer.status;
                    document.getElementById('engineerLat').value = engineer.assigned_latitude;
                    document.getElementById('engineerLng').value = engineer.assigned_longitude;
                    document.getElementById('coverageRadius').value = engineer.coverage_radius_meters;
                    
                    // Get address from coordinates using reverse geocoding
                    if (engineer.assigned_latitude && engineer.assigned_longitude) {
                        reverseGeocode(engineer.assigned_latitude, engineer.assigned_longitude)
                            .then(address => {
                                document.getElementById('assignedAddress').value = address;
                            });
                    }
                    
                    new bootstrap.Modal(document.getElementById('engineerModal')).show();
                    
                    // Initialize location map after modal is shown
                    setTimeout(() => {
                        initializeLocationMap();
                        if (engineer.assigned_latitude && engineer.assigned_longitude) {
                            updateLocationMap(engineer.assigned_latitude, engineer.assigned_longitude);
                        }
                    }, 300);
                } else {
                    showAlert('Error', data.message, 'danger');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showAlert('Error', 'Failed to load engineer details.', 'danger');
            });
        }

        function deleteEngineer(engineerId) {
            if (confirm('Are you sure you want to delete this engineer account? This action cannot be undone.')) {
                const formData = new FormData();
                formData.append('action', 'delete_engineer');
                formData.append('engineer_id', engineerId);
                
                fetch('admin.php', {
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
                    showAlert('Error', 'Failed to delete engineer account.', 'danger');
                });
            }
        }

        function toggleEngineerStatus(engineerId, isActive) {
            const newStatus = isActive ? 'active' : 'inactive';
            const statusToggle = document.getElementById(`status_${engineerId}`);
            const statusBadge = statusToggle.nextElementSibling.querySelector('.badge');
            
            // Disable the toggle while processing
            statusToggle.disabled = true;
            
            const formData = new FormData();
            formData.append('action', 'toggle_engineer_status');
            formData.append('engineer_id', engineerId);
            formData.append('new_status', newStatus);
            
            fetch('admin.php', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                return response.json();
            })
            .then(data => {
                console.log('Response:', data); // Debug log
                
                if (data.success) {
                    showAlert('Success', data.message, 'success');
                    
                    // Update the status badge
                    statusBadge.textContent = newStatus.charAt(0).toUpperCase() + newStatus.slice(1);
                    statusBadge.className = `badge engineer-status-badge bg-${newStatus === 'active' ? 'success' : 'secondary'}`;
                    
                    // Re-enable the toggle
                    statusToggle.disabled = false;
                } else {
                    showAlert('Error', data.message || 'Failed to update engineer status', 'danger');
                    // Revert the toggle
                    statusToggle.checked = !isActive;
                    statusToggle.disabled = false;
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showAlert('Error', `Network error: ${error.message}. Please check your connection and try again.`, 'danger');
                // Revert the toggle
                statusToggle.checked = !isActive;
                statusToggle.disabled = false;
            });
        }

        function saveEngineer() {
            const form = document.getElementById('engineerForm');
            const formData = new FormData(form);
            
            // Validate required fields
            if (!formData.get('engineer_name') || !formData.get('engineer_username') || 
                !formData.get('engineer_lat') || !formData.get('engineer_lng')) {
                showAlert('Validation Error', 'Please fill in all required fields.', 'warning');
                return;
            }
            
            if (!isEditMode && !formData.get('engineer_password')) {
                showAlert('Validation Error', 'Password is required for new accounts.', 'warning');
                return;
            }
            
            // Set action based on mode
            formData.append('action', isEditMode ? 'update_engineer' : 'create_engineer');
            
            fetch('admin.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showAlert('Success', data.message, 'success');
                    bootstrap.Modal.getInstance(document.getElementById('engineerModal')).hide();
                    setTimeout(() => {
                        window.location.reload();
                    }, 1500);
                } else {
                    showAlert('Error', data.message, 'danger');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showAlert('Error', 'Failed to save engineer account.', 'danger');
            });
        }

        function viewEngineerCoverage(engineerId) {
            window.open(`?page=coverage_analysis&engineer=${engineerId}`, '_blank');
        }

        // Report viewing functions
        function viewReportDetails(reportId) {
            // Find report in filtered data
            const report = <?php echo json_encode($filtered_reports); ?>.find(r => r.id == reportId);
            
            if (!report) {
                showAlert('Error', 'Report not found.', 'danger');
                return;
            }
            
            const modalBody = document.getElementById('reportDetailsBody');
            
            modalBody.innerHTML = `
                <div class="row">
                    <div class="col-md-6">
                        <h6>Basic Information</h6>
                        <table class="table table-borderless">
                            <tr><td><strong>Report ID:</strong></td><td>#${report.id}</td></tr>
                            <tr><td><strong>Type:</strong></td><td>${report.hazard_type.replace(/_/g, ' ')}</td></tr>
                            <tr><td><strong>Severity:</strong></td><td><span class="badge bg-warning">${report.severity}</span></td></tr>
                            <tr><td><strong>Status:</strong></td><td><span class="badge bg-info">${report.status}</span></td></tr>
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
                
                <div class="row mt-3">
                    <div class="col-12">
                        <h6>Coverage Information</h6>
                        ${report.coverage_status === 'covered' ? `
                            <div class="alert alert-success">
                                <strong>Covered by ${report.assigned_engineers.length} engineer(s):</strong>
                                <ul class="mb-0 mt-2">
                                    ${report.assigned_engineers.map(eng => `
                                        <li>${eng.name} (${eng.specialization}) - ${eng.distance}km away</li>
                                    `).join('')}
                                </ul>
                            </div>
                        ` : `
                            <div class="alert alert-warning">
                                <strong>No Coverage:</strong> This report is outside the 40km radius of all active engineers.
                            </div>
                        `}
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
            `;
            
            new bootstrap.Modal(document.getElementById('reportDetailsModal')).show();
        }

        function viewOnMap(lat, lng) {
            window.open(`?page=coverage_analysis&lat=${lat}&lng=${lng}`, '_blank');
        }

        // Address search using TomTom API
        function searchAddresses(query) {
            // Clear previous timeout
            if (addressSearchTimeout) {
                clearTimeout(addressSearchTimeout);
            }
            
            // Don't search for very short queries
            if (query.length < 3) {
                document.getElementById('addressSuggestions').style.display = 'none';
                return;
            }
            
            // Debounce the search
            addressSearchTimeout = setTimeout(() => {
                fetch(`https://api.tomtom.com/search/2/search/${encodeURIComponent(query)}.json?key=${apiKey}&limit=5&countrySet=PH`)
                    .then(response => response.json())
                    .then(data => {
                        const suggestionsDiv = document.getElementById('addressSuggestions');
                        suggestionsDiv.innerHTML = '';
                        
                        if (data.results && data.results.length > 0) {
                            data.results.forEach(result => {
                                const suggestion = document.createElement('div');
                                suggestion.className = 'suggestion-item';
                                suggestion.textContent = result.address.freeformAddress;
                                suggestion.onclick = function() {
                                    selectAddress(result.address.freeformAddress, result.position.lat, result.position.lon);
                                };
                                suggestionsDiv.appendChild(suggestion);
                            });
                            
                            suggestionsDiv.style.display = 'block';
                        } else {
                            suggestionsDiv.style.display = 'none';
                        }
                    })
                    .catch(error => {
                        console.error('Address search error:', error);
                        document.getElementById('addressSuggestions').style.display = 'none';
                    });
            }, 300);
        }

        function selectAddress(address, lat, lng) {
            document.getElementById('assignedAddress').value = address;
            document.getElementById('engineerLat').value = lat;
            document.getElementById('engineerLng').value = lng;
            document.getElementById('addressSuggestions').style.display = 'none';
            
            // Update location map
            updateLocationMap(lat, lng);
        }

        function navigateAddressSuggestions(event) {
            const suggestionsDiv = document.getElementById('addressSuggestions');
            const items = suggestionsDiv.querySelectorAll('.suggestion-item');
            
            if (items.length === 0 || suggestionsDiv.style.display === 'none') {
                return;
            }
            
            let focusedIndex = -1;
            items.forEach((item, index) => {
                if (item.classList.contains('focused')) {
                    focusedIndex = index;
                }
            });
            
            switch (event.key) {
                case 'ArrowDown':
                    event.preventDefault();
                    focusedIndex = Math.min(focusedIndex + 1, items.length - 1);
                    break;
                case 'ArrowUp':
                    event.preventDefault();
                    focusedIndex = Math.max(focusedIndex - 1, 0);
                    break;
                case 'Enter':
                    if (focusedIndex >= 0) {
                        event.preventDefault();
                        items[focusedIndex].click();
                        return;
                    }
                    break;
                case 'Escape':
                    event.preventDefault();
                    suggestionsDiv.style.display = 'none';
                    return;
            }
            
            // Update focused item
            items.forEach(item => item.classList.remove('focused'));
            if (focusedIndex >= 0) {
                items[focusedIndex].classList.add('focused');
            }
        }

        // Reverse geocoding function
        function reverseGeocode(lat, lng) {
            return fetch(`https://api.tomtom.com/search/2/reverseGeocode/${lat},${lng}.json?key=${apiKey}`)
                .then(response => response.json())
                .then(data => {
                    if (data.addresses && data.addresses.length > 0) {
                        return data.addresses[0].address.freeformAddress;
                    }
                    return `${lat.toFixed(6)}, ${lng.toFixed(6)}`;
                })
                .catch(error => {
                    console.error('Reverse geocoding error:', error);
                    return `${lat.toFixed(6)}, ${lng.toFixed(6)}`;
                });
        }

        // Map initialization functions
        function initializeCoverageMap() {
            coverageMap = L.map('coverageMap').setView([14.5995, 120.9842], 7);
            
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
            }).addTo(coverageMap);

            // Add engineer coverage areas
            const engineers = <?php echo json_encode($engineers); ?>;
            const reports = <?php echo json_encode($filtered_reports); ?>;
            
            engineers.forEach(engineer => {
                if (engineer.assigned_latitude && engineer.assigned_longitude && engineer.status === 'active') {
                    // Add coverage circle
                    L.circle([engineer.assigned_latitude, engineer.assigned_longitude], {
                        radius: engineer.coverage_radius_meters,
                        color: '#FF4B4B',
                        fillColor: '#FF4B4B',
                        fillOpacity: 0.1,
                        weight: 2
                    }).bindPopup(`
                        <strong>${engineer.full_name}</strong><br>
                        ${engineer.specialization}<br>
                        Coverage: ${(engineer.coverage_radius_meters/1000).toFixed(1)}km
                    `).addTo(coverageMap);
                    
                    // Add engineer marker
                    L.marker([engineer.assigned_latitude, engineer.assigned_longitude])
                        .bindPopup(`<strong>${engineer.full_name}</strong><br>${engineer.specialization}`)
                        .addTo(coverageMap);
                }
            });

            // Add report markers
            reports.forEach(report => {
                if (report.latitude && report.longitude) {
                    const color = report.coverage_status === 'covered' ? '#28A745' : '#FFC107';
                    
                    const marker = L.circleMarker([report.latitude, report.longitude], {
                        radius: 5,
                        color: color,
                        fillColor: color,
                        fillOpacity: 0.8
                    }).bindPopup(`
                        <strong>#${report.id} - ${report.hazard_type}</strong><br>
                        Severity: ${report.severity}<br>
                        Status: ${report.status}<br>
                        Coverage: ${report.coverage_status}
                    `).addTo(coverageMap);
                }
            });

            setTimeout(() => {
                coverageMap.invalidateSize();
            }, 100);
        }

        function initializeLocationMap() {
            if (locationMap) {
                locationMap.remove();
            }
            
            locationMap = L.map('locationMap').setView([14.5995, 120.9842], 13);
            
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
            }).addTo(locationMap);
            
            // Add click handler for map
            locationMap.on('click', function(e) {
                const lat = e.latlng.lat;
                const lng = e.latlng.lng;
                
                // Update form fields
                document.getElementById('engineerLat').value = lat;
                document.getElementById('engineerLng').value = lng;
                
                // Reverse geocode to get address
                reverseGeocode(lat, lng).then(address => {
                    document.getElementById('assignedAddress').value = address;
                });
                
                // Update map marker
                updateLocationMap(lat, lng);
            });
            
            setTimeout(() => {
                locationMap.invalidateSize();
            }, 100);
        }

        function updateLocationMap(lat, lng) {
            if (!locationMap) return;
            
            // Remove existing marker and circle
            if (locationMarker) {
                locationMap.removeLayer(locationMarker);
            }
            if (coverageCircle) {
                locationMap.removeLayer(coverageCircle);
            }
            
            // Add new marker
            locationMarker = L.marker([lat, lng]).addTo(locationMap);
            
            // Add coverage circle
            const radius = parseInt(document.getElementById('coverageRadius').value) || 5000;
            coverageCircle = L.circle([lat, lng], {
                radius: radius,
                color: '#FF4B4B',
                fillColor: '#FF4B4B',
                fillOpacity: 0.2
            }).addTo(locationMap);
            
            // Center map on location
            locationMap.setView([lat, lng], 13);
        }

        // Update coverage circle when radius changes
        document.addEventListener('DOMContentLoaded', function() {
            const radiusInput = document.getElementById('coverageRadius');
            if (radiusInput) {
                radiusInput.addEventListener('input', function() {
                    const lat = parseFloat(document.getElementById('engineerLat').value);
                    const lng = parseFloat(document.getElementById('engineerLng').value);
                    
                    if (lat && lng && coverageCircle) {
                        locationMap.removeLayer(coverageCircle);
                        coverageCircle = L.circle([lat, lng], {
                            radius: parseInt(this.value) || 5000,
                            color: '#FF4B4B',
                            fillColor: '#FF4B4B',
                            fillOpacity: 0.2
                        }).addTo(locationMap);
                    }
                });
            }
        });

        // Charts initialization for system reports page
        function initializeCharts() {
            // Get data from PHP
            const reports = <?php echo json_encode($filtered_reports); ?>;
            
            // Process data for charts
            const typeCounts = {};
            const severityCounts = {};
            
            reports.forEach(report => {
                // Count by type
                const type = report.hazard_type.replace(/_/g, ' ');
                typeCounts[type] = (typeCounts[type] || 0) + 1;
                
                // Count by severity
                severityCounts[report.severity] = (severityCounts[report.severity] || 0) + 1;
            });

            // Type Chart
            const typeData = {
                labels: Object.keys(typeCounts),
                datasets: [{
                    data: Object.values(typeCounts),
                    backgroundColor: [
                        '#FF4B4B', '#FFC107', '#17A2B8', '#28A745', '#6C757D',
                        '#E83E8C', '#FD7E14', '#20C997', '#6F42C1', '#DC3545'
                    ]
                }]
            };

            new Chart(document.getElementById('typeChart'), {
                type: 'pie',
                data: typeData,
                options: {
                    responsive: true,
                    plugins: {
                        legend: {
                            position: 'bottom'
                        }
                    }
                }
            });

            // Severity Chart
            const severityData = {
                labels: Object.keys(severityCounts),
                datasets: [{
                    data: Object.values(severityCounts),
                    backgroundColor: ['#28A745', '#FFC107', '#FF8C00', '#DC3545']
                }]
            };

            new Chart(document.getElementById('severityChart'), {
                type: 'doughnut',
                data: severityData,
                options: {
                    responsive: true,
                    plugins: {
                        legend: {
                            position: 'bottom'
                        }
                    }
                }
            });
        }

        // Hide address suggestions when clicking outside
        document.addEventListener('click', function(event) {
            const suggestionsDiv = document.getElementById('addressSuggestions');
            const addressInput = document.getElementById('assignedAddress');
            
            if (suggestionsDiv && addressInput && 
                !suggestionsDiv.contains(event.target) && 
                !addressInput.contains(event.target)) {
                suggestionsDiv.style.display = 'none';
            }
        });

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

        // Mobile sidebar toggle
        function toggleSidebar() {
            document.querySelector('.sidebar').classList.toggle('show');
        }

        // Add mobile menu button for small screens
        window.addEventListener('resize', function() {
            if (window.innerWidth <= 768) {
                if (!document.querySelector('.mobile-menu-btn')) {
                    const mobileBtn = document.createElement('button');
                    mobileBtn.className = 'btn btn-primary mobile-menu-btn position-fixed';
                    mobileBtn.style.cssText = 'top: 10px; left: 10px; z-index: 1001;';
                    mobileBtn.innerHTML = '<i class="fas fa-bars"></i>';
                    mobileBtn.onclick = toggleSidebar;
                    document.body.appendChild(mobileBtn);
                }
            } else {
                const mobileBtn = document.querySelector('.mobile-menu-btn');
                if (mobileBtn) {
                    mobileBtn.remove();
                }
            }
        });

        // Trigger resize event on load
        window.dispatchEvent(new Event('resize'));
    </script>

    <style>
        .location-input-container {
            position: relative;
        }

        .location-suggestions {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: white;
            border: 1px solid #ddd;
            border-top: none;
            border-radius: 0 0 4px 4px;
            max-height: 200px;
            overflow-y: auto;
            z-index: 1000;
            display: none;
        }

        .suggestion-item {
            padding: 10px;
            cursor: pointer;
            border-bottom: 1px solid #eee;
        }

        .suggestion-item:hover,
        .suggestion-item.focused {
            background-color: #f8f9fa;
        }

        .suggestion-item:last-child {
            border-bottom: none;
        }

        .form-check-input:checked {
            background-color: var(--success);
            border-color: var(--success);
        }

        .form-switch .form-check-input:focus {
            border-color: var(--success);
            outline: 0;
            box-shadow: 0 0 0 0.25rem rgba(40, 167, 69, 0.25);
        }

        .engineer-workload {
            font-size: 0.8rem;
        }

        .report-coverage-indicator {
            font-size: 0.75rem;
        }

        .coverage-info {
            font-size: 0.85rem;
            line-height: 1.4;
        }

        .stat-number {
            font-size: 1.8rem;
            font-weight: 700;
            line-height: 1;
        }

        .btn-group-sm .btn {
            padding: 0.2rem 0.4rem;
            font-size: 0.7rem;
        }

        @media (max-width: 576px) {
            .stat-card {
                margin-bottom: 1rem;
            }
            
            .stat-number {
                font-size: 1.5rem;
            }
            
            .stat-icon {
                font-size: 2rem;
            }
            
            .table-responsive {
                font-size: 0.85rem;
            }
            
            .btn-group-sm .btn {
                padding: 0.15rem 0.3rem;
                font-size: 0.65rem;
            }
        }
    </style>
</body>
</html>