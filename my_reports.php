<?php
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
    
    // Check if hazard report belongs to current user (based on reporter_name matching user's full_name)
    $check_sql = "SELECT id FROM hazard_reports WHERE id = ? AND (reporter_name = ? OR reporter_contact = ?)";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("iss", $hazard_report_id, $user_name, $_SESSION['user_email']);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid hazard report or access denied.']);
        exit;
    }
    
    // Insert feedback
    $sql = "INSERT INTO feedback_reports (hazard_report_id, feedback_type, message, reporter_name, reporter_contact, status, created_at) 
            VALUES (?, ?, ?, ?, ?, 'pending', NOW())";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("issss", $hazard_report_id, $feedback_type, $message, $reporter_name, $reporter_contact);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Feedback submitted successfully! Thank you for the update.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to submit feedback. Please try again.']);
    }
    
    $stmt->close();
    exit;
}

// Handle report deletion - only allow user's own reports
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_report') {
    $report_id = intval($_POST['report_id']);
    
    // Check if user owns this report and it's still pending
    $check_sql = "SELECT id, status FROM hazard_reports WHERE id = ? AND (reporter_name = ? OR reporter_contact = ?)";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("iss", $report_id, $user_name, $_SESSION['user_email']);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows === 0) {
        $_SESSION['error_message'] = 'Report not found or access denied.';
    } else {
        $report = $check_result->fetch_assoc();
        if ($report['status'] === 'pending') {
            // Delete the report
            $delete_sql = "DELETE FROM hazard_reports WHERE id = ? AND (reporter_name = ? OR reporter_contact = ?)";
            $delete_stmt = $conn->prepare($delete_sql);
            $delete_stmt->bind_param("iss", $report_id, $user_name, $_SESSION['user_email']);
            
            if ($delete_stmt->execute()) {
                $_SESSION['success_message'] = 'Report deleted successfully.';
            } else {
                $_SESSION['error_message'] = 'Failed to delete report.';
            }
            $delete_stmt->close();
        } else {
            $_SESSION['error_message'] = 'Cannot delete reports that have been reviewed or approved.';
        }
    }
    $check_stmt->close();
    
    header('Location: my_reports.php');
    exit;
}

// Get filter parameters
$status_filter = $_GET['status'] ?? 'all';
$severity_filter = $_GET['severity'] ?? 'all';
$type_filter = $_GET['type'] ?? 'all';
$date_filter = $_GET['date'] ?? '';

// Build SQL query for user's reports (filter by reporter_name or reporter_contact matching logged-in user)
$sql = "SELECT hr.*, 
               u_approved.full_name as approved_by_name,
               u_resolved.full_name as resolved_by_name,
               u_resolved.specialization as resolved_by_specialization,
               u_resolved.email as resolved_by_email
        FROM hazard_reports hr 
        LEFT JOIN users u_approved ON hr.approved_by = u_approved.id 
        LEFT JOIN users u_resolved ON hr.resolved_by = u_resolved.id
        WHERE (hr.reporter_name = ? OR hr.reporter_contact = ?)";

$params = [$user_name, $_SESSION['user_email']];
$types = "ss";

// Apply filters
if ($status_filter !== 'all') {
    if ($status_filter === 'pending') {
        $sql .= " AND hr.status = 'pending'";
    } elseif ($status_filter === 'approved') {
        $sql .= " AND hr.status = 'approved' AND (hr.resolved = 0 OR hr.resolved IS NULL)";
    } elseif ($status_filter === 'resolved') {
        $sql .= " AND hr.status = 'approved' AND hr.resolved = 1";
    } elseif ($status_filter === 'rejected') {
        $sql .= " AND hr.status = 'rejected'";
    }
}

if ($severity_filter !== 'all') {
    $sql .= " AND hr.severity = ?";
    $params[] = $severity_filter;
    $types .= "s";
}

if ($type_filter !== 'all') {
    $sql .= " AND hr.hazard_type = ?";
    $params[] = $type_filter;
    $types .= "s";
}

if ($date_filter) {
    $sql .= " AND DATE(hr.reported_at) = ?";
    $params[] = $date_filter;
    $types .= "s";
}

$sql .= " ORDER BY hr.reported_at DESC";

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
$user_reports = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Get statistics for user's reports only
$stats_sql = "SELECT 
                COUNT(*) as total_reports,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_reports,
                SUM(CASE WHEN status = 'approved' AND (resolved = 0 OR resolved IS NULL) THEN 1 ELSE 0 END) as approved_reports,
                SUM(CASE WHEN status = 'approved' AND resolved = 1 THEN 1 ELSE 0 END) as resolved_reports,
                SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected_reports
              FROM hazard_reports 
              WHERE (reporter_name = ? OR reporter_contact = ?)";

$stats_stmt = $conn->prepare($stats_sql);
$stats_stmt->bind_param("ss", $user_name, $_SESSION['user_email']);
$stats_stmt->execute();
$stats_result = $stats_stmt->get_result();
$stats = $stats_result->fetch_assoc();
$stats_stmt->close();

// Get feedback for user's reports only
$feedback_sql = "SELECT fr.*, hr.hazard_type, hr.address 
                 FROM feedback_reports fr 
                 JOIN hazard_reports hr ON fr.hazard_report_id = hr.id 
                 WHERE (hr.reporter_name = ? OR hr.reporter_contact = ?)
                 ORDER BY fr.created_at DESC 
                 LIMIT 10";

$feedback_stmt = $conn->prepare($feedback_sql);
$feedback_stmt->bind_param("ss", $user_name, $_SESSION['user_email']);
$feedback_stmt->execute();
$feedback_result = $feedback_stmt->get_result();
$recent_feedback = $feedback_result->fetch_all(MYSQLI_ASSOC);
$feedback_stmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RoadSense - My Reports</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
    <!-- DataTables CSS -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap5.min.css">
    
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

        /* Main Content Area */
        .main-content {
            flex: 1;
            padding: 2rem;
        }

        /* Statistics Cards */
        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: var(--card-shadow);
            margin-bottom: 1.5rem;
            position: relative;
            overflow: hidden;
            transition: var(--transition);
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
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

        /* Cards and Containers */
        .card {
            border: none;
            border-radius: 12px;
            box-shadow: var(--card-shadow);
            transition: var(--transition);
            margin-bottom: 1.5rem;
            overflow: hidden;
        }

        .card:hover {
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
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

        /* Report Cards */
        .report-card {
            background-color: white;
            border-radius: 12px;
            box-shadow: var(--card-shadow);
            margin-bottom: 1.5rem;
            overflow: hidden;
            border-left: 5px solid var(--primary);
            transition: var(--transition);
        }

        .report-card:hover {
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
            transform: translateY(-3px);
        }

        .report-card.pending {
            border-left-color: var(--warning);
        }

        .report-card.approved {
            border-left-color: var(--info);
        }

        .report-card.resolved {
            border-left-color: var(--success);
        }

        .report-card.rejected {
            border-left-color: var(--danger);
        }

        .report-header {
            display: flex;
            justify-content: between;
            align-items: flex-start;
            padding: 1.25rem 1.5rem 0.75rem;
        }

        .report-body {
            padding: 0 1.5rem 1rem;
        }

        .report-footer {
            padding: 1rem 1.5rem;
            background-color: rgba(0, 0, 0, 0.02);
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-top: 1px solid rgba(0, 0, 0, 0.05);
            flex-wrap: wrap;
            gap: 1rem;
        }

        .report-title {
            font-weight: 600;
            margin-bottom: 0.5rem;
            font-size: 1.1rem;
            flex: 1;
        }

        .report-meta {
            display: flex;
            align-items: center;
            margin-bottom: 0.75rem;
            color: var(--secondary-light);
            font-size: 0.9rem;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .report-meta i {
            margin-right: 0.5rem;
            width: 16px;
            text-align: center;
        }

        /* Status badges */
        .status-badge {
            padding: 0.35rem 0.75rem;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 500;
            text-transform: capitalize;
        }

        .status-pending {
            background-color: rgba(255, 193, 7, 0.1);
            color: var(--warning);
        }

        .status-approved {
            background-color: rgba(23, 162, 184, 0.15);
            color: var(--info);
        }

        .status-resolved {
            background-color: rgba(40, 167, 69, 0.1);
            color: var(--success);
        }

        .status-rejected {
            background-color: rgba(220, 53, 69, 0.15);
            color: var(--danger);
        }

        /* Severity badges */
        .severity-badge {
            padding: 0.35rem 0.75rem;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 500;
            text-transform: capitalize;
        }

        .severity-minor {
            background-color: rgba(40, 167, 69, 0.1);
            color: var(--success);
        }

        .severity-medium {
            background-color: rgba(255, 193, 7, 0.1);
            color: var(--warning);
        }

        .severity-high {
            background-color: rgba(220, 53, 69, 0.15);
            color: var(--danger);
        }

        .severity-critical {
            background-color: rgba(155, 89, 182, 0.15);
            color: #9b59b6;
        }

        /* Engineer info */
        .engineer-info {
            background-color: rgba(23, 162, 184, 0.1);
            border-radius: 8px;
            padding: 0.75rem;
            margin-top: 0.75rem;
            border-left: 3px solid var(--info);
        }

        .engineer-info h6 {
            margin-bottom: 0.5rem;
            color: var(--info);
            font-size: 0.9rem;
        }

        .engineer-avatar {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: var(--info);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            font-size: 0.8rem;
            margin-right: 0.75rem;
        }

        /* Filter Section */
        .filter-section {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: var(--card-shadow);
            margin-bottom: 2rem;
        }

        .filter-row {
            display: flex;
            gap: 1rem;
            align-items: end;
            flex-wrap: wrap;
        }

        .filter-group {
            flex: 1;
            min-width: 200px;
        }

        .filter-group label {
            font-weight: 500;
            margin-bottom: 0.5rem;
            color: var(--secondary);
        }

        /* Buttons */
        .btn {
            border-radius: 8px;
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
            box-shadow: 0 4px 15px rgba(229, 57, 53, 0.3);
        }

        .btn-outline-primary {
            color: var(--primary);
            border-color: var(--primary);
        }

        .btn-outline-primary:hover {
            background-color: var(--primary);
            border-color: var(--primary);
            color: white;
        }

        /* Welcome banner */
        .welcome-banner {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            color: white;
            border-radius: 12px;
            padding: 2rem;
            margin-bottom: 2rem;
            position: relative;
            overflow: hidden;
        }

        .welcome-banner::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><circle cx="20" cy="20" r="2" fill="white" opacity="0.1"/><circle cx="80" cy="40" r="1" fill="white" opacity="0.1"/><circle cx="40" cy="80" r="1.5" fill="white" opacity="0.1"/></svg>');
        }

        .welcome-content {
            position: relative;
            z-index: 1;
        }

        /* Alert messages */
        .alert {
            border-radius: 8px;
            margin-bottom: 1.5rem;
        }

        /* Empty state */
        .empty-state {
            text-align: center;
            padding: 3rem 1rem;
            color: var(--secondary-light);
        }

        .empty-state i {
            font-size: 4rem;
            margin-bottom: 1rem;
            opacity: 0.3;
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
            border-bottom: 2px solid #dee2e6;
        }

        table.dataTable tbody td {
            padding: 0.75rem 1rem;
            vertical-align: middle;
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
            border-radius: 8px;
            overflow: hidden;
            margin-bottom: 10px;
            min-width: 350px;
            max-width: 400px;
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

            .welcome-banner {
                padding: 1.5rem;
            }

            .stat-number {
                font-size: 2rem;
            }

            .report-footer {
                flex-direction: column;
                align-items: flex-start;
            }

            .filter-row {
                flex-direction: column;
            }

            .filter-group {
                min-width: 100%;
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
                    <li class="nav-item">
                        <a class="nav-link" href="map_navigation.php"><i class="fas fa-map-marked-alt me-1"></i> Map</a>
                    </li>
                    <li class="nav-item active">
                        <a class="nav-link active" href="my_reports.php"><i class="fas fa-flag me-1"></i> My Reports</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="alerts.php"><i class="fas fa-bell me-1"></i> Alerts</a>
                    </li>
                </ul>
                <div class="d-flex align-items-center">
                    <div class="position-relative me-3">
                        <a href="#" class="btn btn-outline-light btn-sm" id="notificationsBtn">
                            <i class="fas fa-bell"></i>
                            <span class="notification-badge"><?php echo count($recent_feedback); ?></span>
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

    <!-- Main Content Area -->
    <div class="main-content">
        <div class="container-fluid">
            <!-- Welcome Banner -->
            <div class="welcome-banner">
                <div class="welcome-content">
                    <div class="row align-items-center">
                        <div class="col-md-8">
                            <h2><i class="fas fa-user-check me-2"></i>Welcome back, <?php echo htmlspecialchars($user_name); ?>!</h2>
                            <p class="mb-0">Track your hazard reports, monitor their status, and stay updated on road safety improvements.</p>
                        </div>
                        <div class="col-md-4 text-md-end">
                            <a href="report_hazard.php" class="btn btn-light btn-lg">
                                <i class="fas fa-plus-circle me-2"></i>Report New Hazard
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Alert Messages -->
            <?php if (isset($_SESSION['success_message'])): ?>
                <div class="alert alert-success alert-dismissible fade show">
                    <i class="fas fa-check-circle me-2"></i><?php echo htmlspecialchars($_SESSION['success_message']); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php unset($_SESSION['success_message']); ?>
            <?php endif; ?>

            <?php if (isset($_SESSION['error_message'])): ?>
                <div class="alert alert-danger alert-dismissible fade show">
                    <i class="fas fa-exclamation-triangle me-2"></i><?php echo htmlspecialchars($_SESSION['error_message']); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php unset($_SESSION['error_message']); ?>
            <?php endif; ?>

            <!-- Statistics Overview -->
            <div class="row mb-4">
                <div class="col-md-2 col-6">
                    <div class="stat-card bg-primary">
                        <div class="stat-icon">
                            <i class="fas fa-clipboard-list"></i>
                        </div>
                        <div class="stat-content">
                            <div class="stat-number"><?php echo $stats['total_reports']; ?></div>
                            <div class="stat-label">Total Reports</div>
                        </div>
                    </div>
                </div>
                <div class="col-md-2 col-6">
                    <div class="stat-card bg-warning">
                        <div class="stat-icon">
                            <i class="fas fa-clock"></i>
                        </div>
                        <div class="stat-content">
                            <div class="stat-number"><?php echo $stats['pending_reports']; ?></div>
                            <div class="stat-label">Pending Review</div>
                        </div>
                    </div>
                </div>
                <div class="col-md-2 col-6">
                    <div class="stat-card bg-info">
                        <div class="stat-icon">
                            <i class="fas fa-check"></i>
                        </div>
                        <div class="stat-content">
                            <div class="stat-number"><?php echo $stats['approved_reports']; ?></div>
                            <div class="stat-label">Approved</div>
                        </div>
                    </div>
                </div>
                <div class="col-md-2 col-6">
                    <div class="stat-card bg-success">
                        <div class="stat-icon">
                            <i class="fas fa-check-double"></i>
                        </div>
                        <div class="stat-content">
                            <div class="stat-number"><?php echo $stats['resolved_reports']; ?></div>
                            <div class="stat-label">Resolved</div>
                        </div>
                    </div>
                </div>
                <div class="col-md-2 col-6">
                    <div class="stat-card bg-danger">
                        <div class="stat-icon">
                            <i class="fas fa-times"></i>
                        </div>
                        <div class="stat-content">
                            <div class="stat-number"><?php echo $stats['rejected_reports']; ?></div>
                            <div class="stat-label">Rejected</div>
                        </div>
                    </div>
                </div>
                <div class="col-md-2 col-6">
                    <div class="stat-card bg-secondary">
                        <div class="stat-icon">
                            <i class="fas fa-comments"></i>
                        </div>
                        <div class="stat-content">
                            <div class="stat-number"><?php echo count($recent_feedback); ?></div>
                            <div class="stat-label">Recent Feedback</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Filter Section -->
            <div class="filter-section">
                <h5><i class="fas fa-filter me-2"></i>Filter My Reports</h5>
                <form method="GET" action="my_reports.php">
                    <div class="filter-row">
                        <div class="filter-group">
                            <label>Status</label>
                            <select class="form-select" name="status">
                                <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>All Status</option>
                                <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending Review</option>
                                <option value="approved" <?php echo $status_filter === 'approved' ? 'selected' : ''; ?>>Approved</option>
                                <option value="resolved" <?php echo $status_filter === 'resolved' ? 'selected' : ''; ?>>Resolved</option>
                                <option value="rejected" <?php echo $status_filter === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                            </select>
                        </div>
                        <div class="filter-group">
                            <label>Severity</label>
                            <select class="form-select" name="severity">
                                <option value="all" <?php echo $severity_filter === 'all' ? 'selected' : ''; ?>>All Severities</option>
                                <option value="critical" <?php echo $severity_filter === 'critical' ? 'selected' : ''; ?>>Critical</option>
                                <option value="high" <?php echo $severity_filter === 'high' ? 'selected' : ''; ?>>High</option>
                                <option value="medium" <?php echo $severity_filter === 'medium' ? 'selected' : ''; ?>>Medium</option>
                                <option value="minor" <?php echo $severity_filter === 'minor' ? 'selected' : ''; ?>>Minor</option>
                            </select>
                        </div>
                        <div class="filter-group">
                            <label>Type</label>
                            <select class="form-select" name="type">
                                <option value="all" <?php echo $type_filter === 'all' ? 'selected' : ''; ?>>All Types</option>
                                <option value="pothole" <?php echo $type_filter === 'pothole' ? 'selected' : ''; ?>>Pothole</option>
                                <option value="road_crack" <?php echo $type_filter === 'road_crack' ? 'selected' : ''; ?>>Road Crack</option>
                                <option value="flooding" <?php echo $type_filter === 'flooding' ? 'selected' : ''; ?>>Flooding</option>
                                <option value="fallen_tree" <?php echo $type_filter === 'fallen_tree' ? 'selected' : ''; ?>>Fallen Tree</option>
                                <option value="landslide" <?php echo $type_filter === 'landslide' ? 'selected' : ''; ?>>Landslide</option>
                                <option value="debris" <?php echo $type_filter === 'debris' ? 'selected' : ''; ?>>Road Debris</option>
                                <option value="construction" <?php echo $type_filter === 'construction' ? 'selected' : ''; ?>>Construction</option>
                                <option value="accident" <?php echo $type_filter === 'accident' ? 'selected' : ''; ?>>Accident</option>
                            </select>
                        </div>
                        <div class="filter-group">
                            <label>Date</label>
                            <input type="date" class="form-control" name="date" value="<?php echo htmlspecialchars($date_filter); ?>">
                        </div>
                        <div class="filter-group">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-search me-1"></i>Apply Filters
                            </button>
                            <a href="my_reports.php" class="btn btn-outline-secondary ms-2">
                                <i class="fas fa-undo me-1"></i>Reset
                            </a>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Reports Section -->
            <div class="row">
                <div class="col-lg-8">
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h4 class="mb-0"><i class="fas fa-list me-2"></i>My Hazard Reports</h4>
                            <span class="badge bg-primary"><?php echo count($user_reports); ?> Reports</span>
                        </div>
                        <div class="card-body">
                            <?php if (empty($user_reports)): ?>
                                <div class="empty-state">
                                    <i class="fas fa-clipboard-list"></i>
                                    <h4>No Reports Found</h4>
                                    <p>You haven't submitted any hazard reports yet, or no reports match your current filters.</p>
                                    <a href="report_hazard.php" class="btn btn-primary">
                                        <i class="fas fa-plus-circle me-1"></i>Submit Your First Report
                                    </a>
                                </div>
                            <?php else: ?>
                                <?php foreach ($user_reports as $report): ?>
                                    <div class="report-card <?php echo strtolower($report['status'] === 'approved' && $report['resolved'] ? 'resolved' : $report['status']); ?>">
                                        <div class="report-header">
                                            <div class="report-title">
                                                <?php echo ucwords(str_replace('_', ' ', $report['hazard_type'])); ?> Report
                                                <?php if ($report['status'] === 'pending'): ?>
                                                    <span class="status-badge status-pending">Pending Review</span>
                                                <?php elseif ($report['status'] === 'approved' && !$report['resolved']): ?>
                                                    <span class="status-badge status-approved">Approved</span>
                                                <?php elseif ($report['status'] === 'approved' && $report['resolved']): ?>
                                                    <span class="status-badge status-resolved">Resolved</span>
                                                <?php elseif ($report['status'] === 'rejected'): ?>
                                                    <span class="status-badge status-rejected">Rejected</span>
                                                <?php endif; ?>
                                            </div>
                                            <span class="severity-badge severity-<?php echo strtolower($report['severity']); ?>">
                                                <?php echo ucfirst($report['severity']); ?>
                                            </span>
                                        </div>
                                        
                                        <div class="report-body">
                                            <div class="report-meta">
                                                <div><i class="fas fa-map-marker-alt"></i><?php echo htmlspecialchars($report['address']); ?></div>
                                                <div><i class="fas fa-calendar-alt"></i><?php echo date('M d, Y g:i A', strtotime($report['reported_at'])); ?></div>
                                                <div><i class="fas fa-hashtag"></i>Report #<?php echo $report['id']; ?></div>
                                            </div>
                                            
                                            <?php if ($report['description']): ?>
                                                <p class="mb-2"><?php echo htmlspecialchars($report['description']); ?></p>
                                            <?php endif; ?>
                                            
                                            <?php if ($report['status'] === 'approved' && $report['approved_by_name']): ?>
                                                <div class="engineer-info">
                                                    <h6><i class="fas fa-user-check me-1"></i>Approved by Engineer</h6>
                                                    <div class="d-flex align-items-center">
                                                        <div class="engineer-avatar">
                                                            <?php echo strtoupper(substr($report['approved_by_name'], 0, 2)); ?>
                                                        </div>
                                                        <div>
                                                            <strong><?php echo htmlspecialchars($report['approved_by_name']); ?></strong>
                                                            <br><small class="text-muted">Approved on <?php echo date('M d, Y', strtotime($report['approved_at'])); ?></small>
                                                        </div>
                                                    </div>
                                                </div>
                                            <?php endif; ?>
                                            
                                            <?php if ($report['resolved'] && $report['resolved_by_name']): ?>
                                                <div class="engineer-info">
                                                    <h6><i class="fas fa-tools me-1"></i>Resolved by Engineer</h6>
                                                    <div class="d-flex align-items-center mb-2">
                                                        <div class="engineer-avatar">
                                                            <?php echo strtoupper(substr($report['resolved_by_name'], 0, 2)); ?>
                                                        </div>
                                                        <div>
                                                            <strong><?php echo htmlspecialchars($report['resolved_by_name']); ?></strong>
                                                            <br><small class="text-muted">
                                                                <?php echo htmlspecialchars($report['resolved_by_specialization'] ?: 'General Engineer'); ?>
                                                                <?php if ($report['resolved_by_email']): ?>
                                                                    • <?php echo htmlspecialchars($report['resolved_by_email']); ?>
                                                                <?php endif; ?>
                                                            </small>
                                                        </div>
                                                    </div>
                                                    <p class="mb-1"><strong>Resolved on:</strong> <?php echo date('M d, Y g:i A', strtotime($report['resolved_at'])); ?></p>
                                                    <?php if ($report['resolution_notes']): ?>
                                                        <div class="alert alert-light mt-2">
                                                            <strong>Resolution Notes:</strong><br>
                                                            <?php echo htmlspecialchars($report['resolution_notes']); ?>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                            <?php endif; ?>
                                            
                                            <?php if ($report['status'] === 'rejected' && $report['rejection_reason']): ?>
                                                <div class="alert alert-danger mt-2">
                                                    <strong>Rejection Reason:</strong><br>
                                                    <?php echo htmlspecialchars($report['rejection_reason']); ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <div class="report-footer">
                                            <div class="d-flex align-items-center gap-3">
                                                <button class="btn btn-primary btn-sm" onclick="viewOnMap(<?php echo $report['latitude']; ?>, <?php echo $report['longitude']; ?>)">
                                                    <i class="fas fa-map me-1"></i> View on Map
                                                </button>
                                                <?php if ($report['image_path']): ?>
                                                    <button class="btn btn-outline-info btn-sm" onclick="viewPhoto('<?php echo $report['image_path']; ?>')">
                                                        <i class="fas fa-camera me-1"></i> View Photo
                                                    </button>
                                                <?php endif; ?>
                                                <button class="btn btn-outline-secondary btn-sm" onclick="showFeedbackModal(<?php echo $report['id']; ?>)">
                                                    <i class="fas fa-comment me-1"></i> Add Feedback
                                                </button>
                                                <?php if ($report['status'] === 'pending'): ?>
                                                    <button class="btn btn-outline-danger btn-sm" onclick="deleteReport(<?php echo $report['id']; ?>)">
                                                        <i class="fas fa-trash me-1"></i> Delete
                                                    </button>
                                                <?php endif; ?>
                                            </div>
                                            <div class="text-muted small">
                                                <i class="fas fa-eye me-1"></i> Report ID: #<?php echo $report['id']; ?>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <!-- Sidebar -->
                <div class="col-lg-4">
                    <!-- Recent Feedback -->
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="fas fa-comments me-2"></i>Recent Feedback</h5>
                        </div>
                        <div class="card-body">
                            <?php if (empty($recent_feedback)): ?>
                                <div class="text-center text-muted">
                                    <i class="fas fa-comment-slash fa-2x mb-2"></i>
                                    <p class="mb-0">No feedback received yet.</p>
                                </div>
                            <?php else: ?>
                                <?php foreach ($recent_feedback as $feedback): ?>
                                    <div class="border-bottom pb-2 mb-2">
                                        <div class="d-flex justify-content-between align-items-start">
                                            <h6 class="mb-1"><?php echo ucwords(str_replace('_', ' ', $feedback['feedback_type'])); ?></h6>
                                            <small class="text-muted"><?php echo date('M d', strtotime($feedback['created_at'])); ?></small>
                                        </div>
                                        <p class="small mb-1"><?php echo htmlspecialchars(substr($feedback['message'], 0, 80)) . (strlen($feedback['message']) > 80 ? '...' : ''); ?></p>
                                        <small class="text-muted">
                                            <i class="fas fa-map-marker-alt me-1"></i>
                                            <?php echo ucwords(str_replace('_', ' ', $feedback['hazard_type'])); ?> at <?php echo htmlspecialchars(substr($feedback['address'], 0, 30)) . '...'; ?>
                                        </small>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Quick Actions -->
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="fas fa-bolt me-2"></i>Quick Actions</h5>
                        </div>
                        <div class="card-body">
                            <div class="d-grid gap-2">
                                <a href="report_hazard.php" class="btn btn-primary">
                                    <i class="fas fa-plus-circle me-2"></i>Report New Hazard
                                </a>
                                <a href="map_navigation.php" class="btn btn-outline-primary">
                                    <i class="fas fa-map-marked-alt me-2"></i>View Live Map
                                </a>
                                <a href="alerts.php" class="btn btn-outline-secondary">
                                    <i class="fas fa-bell me-2"></i>Check Alerts
                                </a>
                                <button class="btn btn-outline-info" onclick="exportReports()">
                                    <i class="fas fa-download me-2"></i>Export My Reports
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Report Status Guide -->
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="fas fa-info-circle me-2"></i>Status Guide</h5>
                        </div>
                        <div class="card-body">
                            <div class="mb-2">
                                <span class="status-badge status-pending">Pending</span>
                                <small class="d-block text-muted">Report is under review by our engineers</small>
                            </div>
                            <div class="mb-2">
                                <span class="status-badge status-approved">Approved</span>
                                <small class="d-block text-muted">Report verified and marked for resolution</small>
                            </div>
                            <div class="mb-2">
                                <span class="status-badge status-resolved">Resolved</span>
                                <small class="d-block text-muted">Hazard has been fixed by our team</small>
                            </div>
                            <div class="mb-2">
                                <span class="status-badge status-rejected">Rejected</span>
                                <small class="d-block text-muted">Report could not be verified or processed</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Photo Modal -->
    <div class="modal fade" id="photoModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Hazard Photo</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body text-center">
                    <img id="modalPhoto" src="" class="img-fluid" alt="Hazard Photo">
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
                        
                        <!-- Report Information Display -->
                        <div class="alert alert-info mb-4">
                            <h6 class="alert-heading mb-2">
                                <i class="fas fa-info-circle me-2"></i>Report Information
                            </h6>
                            <div id="reportInfo">
                                <strong>Type:</strong> <span id="reportTypeDisplay">-</span><br>
                                <strong>Severity:</strong> <span id="reportSeverityDisplay">-</span><br>
                                <strong>Location:</strong> <span id="reportLocationDisplay">-</span><br>
                                <strong>Reported:</strong> <span id="reportDateDisplay">-</span>
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
                                      placeholder="Please provide detailed feedback about this report..." required></textarea>
                            <div class="form-text">
                                <span id="feedbackHint">Tell us about this report.</span>
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
                                               placeholder="Enter your name (optional)" value="<?php echo htmlspecialchars($user_name); ?>">
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
    
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.4.min.js"></script>
    
    <!-- DataTables -->
    <script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"></script>
    
    <!-- My Reports Script -->
    <script>
        // Global variables
        let userReports = <?php echo json_encode($user_reports); ?>;
        let currentReportId = null;
        
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize feedback form
            initFeedbackForm();
            
            // Initialize tooltips
            initTooltips();
        });
        
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
                    // Restore user name
                    reporterName.value = '<?php echo addslashes($user_name); ?>';
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
            
            document.getElementById('feedbackHint').textContent = hints[type] || 'Tell us about this report.';
        }
        
        // Show feedback modal
        function showFeedbackModal(reportId) {
            currentReportId = reportId;
            
            // Find report data
            const report = userReports.find(r => r.id == reportId);
            
            if (!report) {
                showNotification('Error', 'Report not found.', 'danger');
                return;
            }
            
            // Populate report information
            document.getElementById('feedbackHazardId').value = reportId;
            document.getElementById('reportTypeDisplay').textContent = report.hazard_type.replace(/_/g, ' ');
            document.getElementById('reportSeverityDisplay').textContent = report.severity;
            document.getElementById('reportLocationDisplay').textContent = report.address;
            document.getElementById('reportDateDisplay').textContent = report.reported_at;
            
            // Reset form
            document.getElementById('feedbackForm').reset();
            document.getElementById('feedbackHazardId').value = reportId;
            document.getElementById('reporterName').value = '<?php echo addslashes($user_name); ?>';
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
            fetch('my_reports.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showNotification('Feedback Submitted', data.message, 'success');
                    bootstrap.Modal.getInstance(document.getElementById('feedbackModal')).hide();
                    
                    // Refresh page after a short delay to show updated feedback
                    setTimeout(() => {
                        window.location.reload();
                    }, 2000);
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
        
        // View photo in modal
        function viewPhoto(imagePath) {
            document.getElementById('modalPhoto').src = imagePath;
            new bootstrap.Modal(document.getElementById('photoModal')).show();
        }
        
        // View report location on map
        function viewOnMap(lat, lng) {
            // Redirect to traffic hazard map with the specific location
            const url = `traffic_hazard_map.php?lat=${lat}&lng=${lng}&zoom=16`;
            window.open(url, '_blank');
        }
        
        // Delete report
        function deleteReport(reportId) {
            if (!confirm('Are you sure you want to delete this report? This action cannot be undone.')) {
                return;
            }
            
            showLoading();
            
            const formData = new FormData();
            formData.append('action', 'delete_report');
            formData.append('report_id', reportId);
            
            fetch('my_reports.php', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                hideLoading();
                // Reload page to show updated reports
                window.location.reload();
            })
            .catch(error => {
                console.error('Error deleting report:', error);
                hideLoading();
                showNotification('Delete Error', 'Failed to delete report. Please try again.', 'danger');
            });
        }
        
        // Export reports to CSV
        function exportReports() {
            if (userReports.length === 0) {
                showNotification('Export Error', 'No reports to export.', 'warning');
                return;
            }
            
            // Create CSV content
            let csvContent = "data:text/csv;charset=utf-8,";
            
            // Add headers
            csvContent += "Report ID,Type,Severity,Status,Location,Description,Reported Date,Approved Date,Resolved Date\n";
            
            // Add rows
            userReports.forEach(report => {
                const status = report.resolved ? 'Resolved' : (report.status === 'approved' ? 'Approved' : report.status);
                csvContent += `"${report.id}","${report.hazard_type}","${report.severity}","${status}","${report.address}","${report.description || ''}","${report.reported_at}","${report.approved_at || ''}","${report.resolved_at || ''}"\n`;
            });
            
            // Create download link
            const encodedUri = encodeURI(csvContent);
            const link = document.createElement("a");
            link.setAttribute("href", encodedUri);
            link.setAttribute("download", "my_hazard_reports.csv");
            document.body.appendChild(link);
            
            // Trigger download
            link.click();
            
            // Clean up
            document.body.removeChild(link);
            
            showNotification('Export Complete', `Successfully exported ${userReports.length} reports to CSV.`, 'success');
        }
        
        // Initialize tooltips
        function initTooltips() {
            // Bootstrap tooltips
            var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl);
            });
        }
        
        // Function to show toast notifications
        function showNotification(title, message, type = 'info', duration = 5000) {
            const toastContainer = document.querySelector('.toast-container');
            if (!toastContainer) return;
            
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
                const toastElement = document.getElementById(toastId);
                if (toastElement) {
                    toastElement.remove();
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
        
        // Auto-refresh notifications every 5 minutes
        setInterval(() => {
            // Refresh notification badge count
            fetch('get_user_notifications.php')
                .then(response => response.json())
                .then(data => {
                    if (data.count !== undefined) {
                        const badge = document.querySelector('.notification-badge');
                        if (badge) {
                            badge.textContent = data.count;
                            if (data.count > 0) {
                                badge.style.display = 'flex';
                            } else {
                                badge.style.display = 'none';
                            }
                        }
                    }
                })
                .catch(error => {
                    console.error('Error fetching notifications:', error);
                });
        }, 300000); // 5 minutes
        
        // Handle visibility change to refresh data when user returns
        document.addEventListener('visibilitychange', function() {
            if (!document.hidden) {
                // Page is visible, refresh data
                setTimeout(() => {
                    window.location.reload();
                }, 1000);
            }
        });
        
        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            // Ctrl/Cmd + R to refresh
            if ((e.ctrlKey || e.metaKey) && e.key === 'r') {
                e.preventDefault();
                window.location.reload();
            }
            
            // Ctrl/Cmd + N to create new report
            if ((e.ctrlKey || e.metaKey) && e.key === 'n') {
                e.preventDefault();
                window.location.href = 'report_hazard.php';
            }
        });
        
        // Add click tracking for analytics (if needed)
        document.addEventListener('click', function(e) {
            const target = e.target.closest('button, a');
            if (target) {
                const action = target.textContent.trim();
                // Could send analytics data here
                console.log('User action:', action);
            }
        });
        
        // Initialize page
        document.addEventListener('DOMContentLoaded', function() {
            // Show welcome message for first-time users
            if (userReports.length === 0 && !localStorage.getItem('welcome_shown')) {
                setTimeout(() => {
                    showNotification(
                        'Welcome to RoadSense!', 
                        'Start by reporting your first road hazard to help make our roads safer for everyone.', 
                        'info', 
                        8000
                    );
                    localStorage.setItem('welcome_shown', 'true');
                }, 2000);
            }
            
            // Highlight recently updated reports
            const urlParams = new URLSearchParams(window.location.search);
            const highlightId = urlParams.get('highlight');
            if (highlightId) {
                const reportCard = document.querySelector(`[data-report-id="${highlightId}"]`);
                if (reportCard) {
                    reportCard.style.border = '2px solid var(--primary)';
                    reportCard.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    
                    setTimeout(() => {
                        reportCard.style.border = '';
                    }, 5000);
                }
            }
        });
    </script>
</body>
</html>