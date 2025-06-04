<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start session to check for logged-in user
session_start();
require 'db_connect.php';

// Check if user is logged in
$is_logged_in = isset($_SESSION['user_logged_in']) && $_SESSION['user_logged_in'] === true;
$user_name = $is_logged_in ? $_SESSION['user_name'] : '';
$user_email = $is_logged_in ? $_SESSION['user_email'] : '';
$user_id = $is_logged_in ? $_SESSION['user_id'] : null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Check if this is an AJAX request for offline reports
    $isOfflineReport = isset($_POST['isOfflineReport']) && $_POST['isOfflineReport'] === 'true';
    
    // Ensure the uploads directory exists
    $uploadDir = "uploads/";
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }

    // Validate required fields
    if (!isset($_POST['hazardType'], $_POST['severity'], $_POST['latitude'], $_POST['longitude'], $_POST['address'])) {
        $error = "❌ Error: Missing required fields.";
        if ($isOfflineReport) {
            echo json_encode(['success' => false, 'message' => $error]);
            exit;
        } else {
            die($error);
        }
    }

    $hazardType = $conn->real_escape_string($_POST['hazardType']);
    $severity = $conn->real_escape_string($_POST['severity']);
    $latitude = $conn->real_escape_string($_POST['latitude']);
    $longitude = $conn->real_escape_string($_POST['longitude']);
    $address = $conn->real_escape_string($_POST['address']);
    $description = isset($_POST['description']) ? $conn->real_escape_string($_POST['description']) : '';
    
    // Use logged-in user info or form inputs for reporter information
    if ($is_logged_in) {
        // If user is logged in, use their session information
        $reporterName = $user_name;
        $reporterContact = $user_email;
    } else {
        // If user is not logged in, use form inputs (optional fields)
        $reporterName = isset($_POST['reporterName']) ? $conn->real_escape_string($_POST['reporterName']) : 'Anonymous';
        $reporterContact = isset($_POST['reporterContact']) ? $conn->real_escape_string($_POST['reporterContact']) : '';
    }

    // File Upload Handling - Standard Upload
    $imagePath = '';
    
    if (isset($_FILES['photoUpload']) && $_FILES['photoUpload']['error'] === 0) {
        // Regular file upload processing
        $fileName = time() . '_' . basename($_FILES["photoUpload"]["name"]);
        $imagePath = $uploadDir . $fileName;

        // Ensure file is an image
        $fileType = strtolower(pathinfo($imagePath, PATHINFO_EXTENSION));
        $allowedTypes = ['jpg', 'jpeg', 'png', 'gif'];
        if (!in_array($fileType, $allowedTypes)) {
            $error = "❌ Error: Only JPG, JPEG, PNG, and GIF files are allowed.";
            if ($isOfflineReport) {
                echo json_encode(['success' => false, 'message' => $error]);
                exit;
            } else {
                die($error);
            }
        }

        // Check file size (max 5MB)
        if ($_FILES["photoUpload"]["size"] > 5000000) {
            $error = "❌ Error: File size should not exceed 5MB.";
            if ($isOfflineReport) {
                echo json_encode(['success' => false, 'message' => $error]);
                exit;
            } else {
                die($error);
            }
        }

        if (!move_uploaded_file($_FILES["photoUpload"]["tmp_name"], $imagePath)) {
            $error = "❌ Error: File upload failed.";
            if ($isOfflineReport) {
                echo json_encode(['success' => false, 'message' => $error]);
                exit;
            } else {
                die($error);
            }
        }
    } 
    // Base64 image handling for offline reports
    else if (isset($_POST['photoBase64']) && !empty($_POST['photoBase64'])) {
        $base64Image = $_POST['photoBase64'];
        // Extract the MIME type and decode
        list($type, $data) = explode(';', $base64Image);
        list(, $data) = explode(',', $data);
        $data = base64_decode($data);
        
        // Generate a filename and save
        $fileName = time() . '_offline_report.jpg';
        $imagePath = $uploadDir . $fileName;
        file_put_contents($imagePath, $data);
    } else {
        $error = "❌ Error: No photo provided.";
        if ($isOfflineReport) {
            echo json_encode(['success' => false, 'message' => $error]);
            exit;
        } else {
            die($error);
        }
    }

    // Insert report with user information
    $sql = "INSERT INTO hazard_reports (hazard_type, severity, latitude, longitude, address, image_path, description, status, reporter_name, reporter_contact, created_at, reported_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, 'pending', ?, ?, NOW(), NOW())";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        $error = "❌ Error: Database preparation failed - " . $conn->error;
        if ($isOfflineReport) {
            echo json_encode(['success' => false, 'message' => $error]);
            exit;
        } else {
            die($error);
        }
    }

    $stmt->bind_param("sssssssss", $hazardType, $severity, $latitude, $longitude, $address, $imagePath, $description, $reporterName, $reporterContact);

    if ($stmt->execute()) {
        $reportId = $conn->insert_id;
        
        if ($isOfflineReport) {
            echo json_encode([
                'success' => true, 
                'message' => 'Report submitted successfully! Your report ID is #' . $reportId . '. It will be reviewed by our team shortly.',
                'reportId' => $reportId
            ]);
            exit;
        } else {
            // Set success message and redirect based on login status
            $_SESSION['report_success'] = true;
            $_SESSION['report_id'] = $reportId;
            
            if ($is_logged_in) {
                // Redirect logged-in users to their reports page
                header("Location: my_reports.php?success=1&reportId=" . $reportId);
            } else {
                // Redirect anonymous users back to report form with success message
                header("Location: report_hazard.php?success=1&pending=1&reportId=" . $reportId);
            }
            exit();
        }
    } else {
        $error = "❌ Error: " . $stmt->error;
        if ($isOfflineReport) {
            echo json_encode(['success' => false, 'message' => $error]);
            exit;
        } else {
            die($error);
        }
    }

    $stmt->close();
    $conn->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RoadSense - Report Hazard</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
    <!-- Leaflet CSS -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.3/dist/leaflet.css">
    
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

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background-color: var(--background);
            color: var(--secondary);
            line-height: 1.6;
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

        /* Main Content Styling */
        .main-content {
            flex: 1;
            padding: 2rem;
        }

        .container {
            max-width: 800px;
            margin: 0 auto;
        }

        /* Card Styling */
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

        /* User status notice */
        .user-status-notice {
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 2rem;
            text-align: center;
        }

        .logged-in-notice {
            background-color: rgba(40, 167, 69, 0.1);
            border: 1px solid var(--success);
        }

        .logged-in-notice i {
            color: var(--success);
            font-size: 1.2rem;
            margin-bottom: 0.5rem;
        }

        .logged-in-notice h5 {
            color: var(--success);
            margin-bottom: 0.5rem;
        }

        .anonymous-notice {
            background-color: rgba(23, 162, 184, 0.1);
            border: 1px solid var(--info);
        }

        .anonymous-notice i {
            color: var(--info);
            font-size: 1.2rem;
            margin-bottom: 0.5rem;
        }

        .anonymous-notice h5 {
            color: var(--info);
            margin-bottom: 0.5rem;
        }

        .user-status-notice p {
            margin-bottom: 0;
            font-size: 0.9rem;
            color: var(--secondary);
        }

        /* Form Styling */
        .progress-container {
            width: 100%;
            margin-bottom: 2rem;
        }

        .progress {
            height: 8px;
            background-color: #e9ecef;
            border-radius: 4px;
            overflow: hidden;
        }

        .progress-bar {
            background-color: var(--primary);
            transition: width 0.4s ease;
        }

        .step-indicator {
            display: flex;
            justify-content: space-between;
            margin-top: 10px;
            position: relative;
        }

        .step {
            display: flex;
            flex-direction: column;
            align-items: center;
            position: relative;
            z-index: 1;
        }

        .step-number {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            background-color: #e9ecef;
            color: #6c757d;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            margin-bottom: 5px;
            transition: var(--transition);
        }

        .step.active .step-number {
            background-color: var(--primary);
            color: white;
        }

        .step.completed .step-number {
            background-color: var(--success);
            color: white;
        }

        .step-label {
            font-size: 0.8rem;
            color: #6c757d;
            text-align: center;
        }

        .step.active .step-label {
            color: var(--primary);
            font-weight: 600;
        }

        .step.completed .step-label {
            color: var(--success);
            font-weight: 600;
        }

        .form-step {
            display: none;
            animation: fadeIn 0.4s ease-in-out;
        }

        .form-step.active {
            display: block;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .form-label {
            font-weight: 500;
            margin-bottom: 0.5rem;
        }

        .form-control, .form-select {
            padding: 0.75rem;
            border: 1px solid rgba(0, 0, 0, 0.1);
            border-radius: 8px;
            transition: var(--transition);
        }

        .form-control:focus, .form-select:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 0.25rem rgba(255, 75, 75, 0.25);
        }

        /* Locked field styling */
        .form-control:read-only {
            background-color: rgba(40, 167, 69, 0.05);
            border-color: var(--success);
        }

        .locked-field-notice {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.85rem;
            color: var(--success);
            margin-top: 0.25rem;
        }

        /* Optional fields styling */
        .optional-section {
            background-color: rgba(0, 0, 0, 0.02);
            border-radius: 8px;
            padding: 1.5rem;
            margin-top: 1.5rem;
            border: 1px dashed rgba(0, 0, 0, 0.1);
        }

        .optional-section.hidden {
            display: none;
        }

        .optional-section h5 {
            font-size: 1.1rem;
            margin-bottom: 1rem;
            color: var(--secondary);
        }

        .optional-section p {
            font-size: 0.9rem;
            color: #6c757d;
            margin-bottom: 1.5rem;
        }

        /* Map Container */
        .map-container {
            height: 300px;
            width: 100%;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: var(--card-shadow);
            position: relative;
            margin-bottom: 1rem;
        }

        #map {
            height: 100%;
            width: 100%;
        }

        .map-instructions {
            position: absolute;
            top: 10px;
            left: 10px;
            right: 10px;
            background-color: rgba(0, 0, 0, 0.7);
            color: white;
            padding: 8px 12px;
            border-radius: 4px;
            font-size: 0.85rem;
            z-index: 1000;
            text-align: center;
        }

        /* Custom File Upload */
        .custom-file-upload {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            border: 2px dashed rgba(0, 0, 0, 0.1);
            border-radius: 8px;
            padding: 2rem;
            text-align: center;
            cursor: pointer;
            transition: var(--transition);
        }

        .custom-file-upload:hover {
            border-color: var(--primary);
            background-color: rgba(255, 75, 75, 0.05);
        }

        .custom-file-upload i {
            font-size: 2.5rem;
            color: var(--primary);
            margin-bottom: 1rem;
        }

        .custom-file-upload p {
            margin-bottom: 0;
            color: var(--secondary);
        }

        .custom-file-upload p.hint {
            font-size: 0.85rem;
            color: #6c757d;
            margin-top: 0.5rem;
        }

        #photoUpload {
            display: none;
        }

        #imagePreview {
            max-width: 100%;
            max-height: 200px;
            border-radius: 8px;
            margin-top: 1rem;
            display: none;
            box-shadow: var(--card-shadow);
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

        .btn-outline-primary {
            color: var(--primary);
            border-color: var(--primary);
        }

        .btn-outline-primary:hover, .btn-outline-primary:focus {
            background-color: var(--primary);
            color: white;
            box-shadow: 0 4px 10px rgba(229, 57, 53, 0.3);
        }

        .button-container {
            display: flex;
            justify-content: space-between;
            margin-top: 2rem;
        }

        /* Preview Styling */
        .preview-container {
            background-color: rgba(0, 0, 0, 0.02);
            border-radius: 8px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
        }

        .preview-item {
            margin-bottom: 1.5rem;
        }

        .preview-item:last-child {
            margin-bottom: 0;
        }

        .preview-label {
            font-weight: 600;
            margin-bottom: 0.5rem;
            color: var(--secondary);
        }

        .preview-value {
            padding: 0.75rem;
            background-color: white;
            border-radius: 6px;
            border: 1px solid rgba(0, 0, 0, 0.05);
        }

        .preview-image {
            width: 100%;
            border-radius: 8px;
            margin-top: 0.5rem;
            box-shadow: var(--card-shadow);
        }

        /* Offline sync notification */
        .connection-status {
            display: flex;
            align-items: center;
            margin-bottom: 1rem;
        }

        .status-indicator {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            margin-right: 0.5rem;
        }

        .online {
            background-color: var(--success);
            box-shadow: 0 0 5px var(--success);
        }

        .offline {
            background-color: var(--warning);
            box-shadow: 0 0 5px var(--warning);
        }

        .pending-badge {
            background-color: var(--warning);
            color: #000;
            border-radius: 50%;
            padding: 2px 6px;
            font-size: 0.7rem;
            margin-left: 5px;
            display: none;
        }

        .pending-reports {
            background-color: rgba(255, 193, 7, 0.1);
            border: 1px solid var(--warning);
            border-radius: 8px;
            padding: 1rem;
            margin-top: 1.5rem;
            display: none;
        }

        .pending-reports h4 {
            color: #856404;
            font-size: 1.1rem;
            margin-bottom: 1rem;
        }

        .pending-list {
            max-height: 200px;
            overflow-y: auto;
        }

        .pending-item {
            background-color: white;
            padding: 0.75rem;
            border-radius: 6px;
            margin-bottom: 0.5rem;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        }

        .sync-all-btn {
            width: 100%;
            margin-top: 1rem;
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

        /* Location button container */
        .location-controls {
            display: flex;
            gap: 10px;
            margin-bottom: 1rem;
        }

        .location-btn {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 0.75rem;
            border-radius: 6px;
            font-weight: 500;
            cursor: pointer;
            transition: var(--transition);
        }

        .location-btn.current-location {
            background-color: var(--warning);
            color: #000;
        }

        .location-btn.confirm-location {
            background-color: var(--success);
            color: white;
        }

        .location-btn:hover {
            opacity: 0.9;
            transform: translateY(-2px);
        }

        .location-btn.pulse {
            animation: pulse-btn 1.5s infinite;
        }

        .location-btn.confirmed {
            background-color: var(--success) !important;
            color: white !important;
        }

        @keyframes pulse-btn {
            0% {
                transform: scale(1);
                box-shadow: 0 0 0 0 rgba(40, 167, 69, 0.7);
            }
            70% {
                transform: scale(1.05);
                box-shadow: 0 0 0 10px rgba(40, 167, 69, 0);
            }
            100% {
                transform: scale(1);
                box-shadow: 0 0 0 0 rgba(40, 167, 69, 0);
            }
        }

        /* Success Message */
        .success-message {
            background-color: rgba(40, 167, 69, 0.1);
            border: 1px solid var(--success);
            color: var(--success);
            padding: 1rem;
            border-radius: 8px;
            margin-top: 1.5rem;
            text-align: center;
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

        /* Severity Tags */
        .severity-tag {
            display: inline-block;
            padding: 0.35rem 0.75rem;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 500;
            margin-right: 0.5rem;
            cursor: pointer;
            transition: var(--transition);
            border: 2px solid transparent;
        }

        .severity-tag.minor {
            background-color: rgba(40, 167, 69, 0.1);
            color: #28A745;
        }

        .severity-tag.medium {
            background-color: rgba(255, 193, 7, 0.1);
            color: #FFC107;
        }

        .severity-tag.high {
            background-color: rgba(255, 75, 75, 0.1);
            color: #FF4B4B;
        }

        .severity-tag.critical {
            background-color: rgba(220, 53, 69, 0.1);
            color: #DC3545;
        }

        .severity-tag.selected {
            border: 2px solid currentColor;
            font-weight: 600;
        }

        .severity-tag:hover {
            transform: translateY(-2px);
        }

        /* Responsive Adjustments */
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

            .button-container {
                flex-direction: column;
                gap: 1rem;
            }

            .button-container .btn {
                width: 100%;
            }

            .location-controls {
                flex-direction: column;
            }

            .step-label {
                display: none;
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
                    <?php if ($is_logged_in): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="my_reports.php"><i class="fas fa-flag me-1"></i> My Reports</a>
                    </li>
                    <?php endif; ?>
                    <li class="nav-item">
                        <a class="nav-link" href="alerts.php"><i class="fas fa-bell me-1"></i> Alerts</a>
                    </li>
                </ul>
                <div class="d-flex align-items-center">
                    <?php if ($is_logged_in): ?>
                        <div class="position-relative me-3">
                            <a href="#" class="btn btn-outline-light btn-sm" id="notificationsBtn">
                                <i class="fas fa-bell"></i>
                                <span class="notification-badge">3</span>
                            </a>
                        </div>
                        <a href="report_hazard.php" class="btn btn-primary active">
                            <i class="fas fa-plus-circle me-1"></i> Report Hazard
                        </a>
                        <div class="dropdown ms-3">
                            <button class="btn btn-outline-light btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                <i class="fas fa-user me-1"></i> <?php echo htmlspecialchars($user_name); ?>
                            </button>
                            <ul class="dropdown-menu">
                                <li><a class="dropdown-item" href="my_reports.php"><i class="fas fa-list me-2"></i>My Reports</a></li>
                                <li><a class="dropdown-item" href="#"><i class="fas fa-user-cog me-2"></i>Profile Settings</a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item" href="logout.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
                            </ul>
                        </div>
                    <?php else: ?>
                        <a href="user_login.php" class="btn btn-outline-light me-2">
                            <i class="fas fa-sign-in-alt me-1"></i> Login
                        </a>
                        <a href="report_hazard.php" class="btn btn-primary active">
                            <i class="fas fa-plus-circle me-1"></i> Report Hazard
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </nav>

    <!-- Main Content Area -->
    <div class="main-content">
        <div class="container">
            <!-- Loader Overlay -->
            <div class="spinner-overlay" id="loaderOverlay">
                <div class="spinner-border text-light" role="status" style="width: 3rem; height: 3rem;">
                    <span class="visually-hidden">Loading...</span>
                </div>
            </div>

            <!-- Toast Container -->
            <div class="toast-container" id="toastContainer">
                <!-- Toasts will be added here -->
            </div>

            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h3 class="mb-0"><i class="fas fa-exclamation-triangle me-2"></i>Report Road Hazard</h3>
                    <div class="connection-status">
                        <div id="statusIndicator" class="status-indicator"></div>
                        <span id="statusText">Checking...</span>
                        <span id="pendingBadge" class="pending-badge">0</span>
                    </div>
                </div>
                <div class="card-body">
                    <!-- User Status Notice -->
                    <?php if ($is_logged_in): ?>
                        <div class="user-status-notice logged-in-notice">
                            <i class="fas fa-user-check d-block"></i>
                            <h5>Logged in as <?php echo htmlspecialchars($user_name); ?></h5>
                            <p>Your reports will be automatically linked to your account and you can track their status in <a href="my_reports.php" class="text-decoration-none">My Reports</a>.</p>
                        </div>
                    <?php else: ?>
                        <div class="user-status-notice anonymous-notice">
                            <i class="fas fa-user-shield d-block"></i>
                            <h5>Anonymous Reporting</h5>
                            <p>No login required! You can report hazards anonymously. <a href="user_login.php" class="text-decoration-none">Login here</a> to track your reports and receive updates.</p>
                        </div>
                    <?php endif; ?>

                    <!-- Progress Bar -->
                    <div class="progress-container">
                        <div class="progress">
                            <div class="progress-bar" role="progressbar" style="width: 20%" id="progressBar"></div>
                        </div>
                        <div class="step-indicator">
                            <div class="step active" data-step="1">
                                <div class="step-number">1</div>
                                <div class="step-label">Details</div>
                            </div>
                            <div class="step" data-step="2">
                                <div class="step-number">2</div>
                                <div class="step-label">Location</div>
                            </div>
                            <div class="step" data-step="3">
                                <div class="step-number">3</div>
                                <div class="step-label">Photo</div>
                            </div>
                            <?php if (!$is_logged_in): ?>
                            <div class="step" data-step="4">
                                <div class="step-number">4</div>
                                <div class="step-label">Contact</div>
                            </div>
                            <div class="step" data-step="5">
                                <div class="step-number">5</div>
                                <div class="step-label">Review</div>
                            </div>
                            <?php else: ?>
                            <div class="step" data-step="4">
                                <div class="step-number">4</div>
                                <div class="step-label">Review</div>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Form -->
                    <form id="hazardForm" action="report_hazard.php" method="POST" enctype="multipart/form-data">
                        <!-- Step 1: Hazard Details -->
                        <div class="form-step active" id="step1">
                            <h4 class="mb-4">Hazard Details</h4>
                            
                            <div class="mb-3">
                                <label for="hazardType" class="form-label">Hazard Type</label>
                                <select id="hazardType" name="hazardType" class="form-select" required>
                                    <option value="">Select a type</option>
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

                            <div class="mb-3">
                                <label class="form-label">Severity Level</label>
                                <input type="hidden" id="severity" name="severity" required>
                                <div class="d-flex flex-wrap gap-2 mt-2">
                                    <div class="severity-tag minor" data-value="minor" onclick="selectSeverity('minor')" style="cursor: pointer;">
                                        <i class="fas fa-exclamation-circle me-1"></i> Minor
                                    </div>
                                    <div class="severity-tag medium" data-value="medium" onclick="selectSeverity('medium')" style="cursor: pointer;">
                                        <i class="fas fa-exclamation-circle me-1"></i> Medium
                                    </div>
                                    <div class="severity-tag high" data-value="high" onclick="selectSeverity('high')" style="cursor: pointer;">
                                        <i class="fas fa-exclamation-circle me-1"></i> High
                                    </div>
                                    <div class="severity-tag critical" data-value="critical" onclick="selectSeverity('critical')" style="cursor: pointer;">
                                        <i class="fas fa-exclamation-circle me-1"></i> Critical
                                    </div>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="description" class="form-label">Description (Optional)</label>
                                <textarea id="description" name="description" class="form-control" rows="4" placeholder="Describe the hazard in more detail..."></textarea>
                            </div>

                            <div class="button-container">
                                <div></div>
                                <button type="button" class="btn btn-primary" id="toStep2Btn">
                                    Next <i class="fas fa-arrow-right ms-1"></i>
                                </button>
                            </div>
                        </div>

                        <!-- Step 2: Location -->
                        <div class="form-step" id="step2">
                            <h4 class="mb-4">Hazard Location</h4>
                            
                            <div class="map-container">
                                <div id="map"></div>
                                <div class="map-instructions">
                                    <i class="fas fa-info-circle me-1"></i> Tap on the map to set the hazard location
                                </div>
                            </div>
                            
                            <div class="location-controls">
                                <button type="button" class="location-btn current-location" onclick="getUserLocation()" style="cursor: pointer;">
                                    <i class="fas fa-crosshairs"></i> Use My Location
                                </button>
                                <button type="button" class="location-btn confirm-location" onclick="confirmLocation()" style="cursor: pointer;">
                                    <i class="fas fa-check-circle"></i> Confirm Location
                                </button>
                            </div>

                            <div class="mb-3">
                                <label for="address" class="form-label">Address</label>
                                <input type="text" id="address" name="address" class="form-control" required readonly>
                                <input type="hidden" id="latitude" name="latitude">
                                <input type="hidden" id="longitude" name="longitude">
                            </div>

                            <div class="button-container">
                                <button type="button" class="btn btn-outline-primary" id="backToStep1Btn">
                                    <i class="fas fa-arrow-left me-1"></i> Previous
                                </button>
                                <button type="button" class="btn btn-primary" id="toStep3Btn">
                                    Next <i class="fas fa-arrow-right ms-1"></i>
                                </button>
                            </div>
                        </div>

                        <!-- Step 3: Photo Upload -->
                        <div class="form-step" id="step3">
                            <h4 class="mb-4">Hazard Photo</h4>
                            
                            <label for="photoUpload" class="custom-file-upload">
                                <i class="fas fa-camera"></i>
                                <p>Take a photo or upload from gallery</p>
                                <p class="hint">Click or tap here to select a file</p>
                                <input type="file" id="photoUpload" name="photoUpload" accept="image/*" capture="environment" required>
                            </label>
                            <img id="imagePreview" src="#" alt="Preview">

                            <div class="button-container">
                                <button type="button" class="btn btn-outline-primary" id="backToStep2Btn">
                                    <i class="fas fa-arrow-left me-1"></i> Previous
                                </button>
                                <button type="button" class="btn btn-primary" id="toStep4Btn">
                                    Next <i class="fas fa-arrow-right ms-1"></i>
                                </button>
                            </div>
                        </div>

                        <!-- Step 4: Contact Information (Only for anonymous users) -->
                        <?php if (!$is_logged_in): ?>
                        <div class="form-step" id="step4">
                            <h4 class="mb-4">Contact Information (Optional)</h4>
                            
                            <div class="optional-section">
                                <h5><i class="fas fa-user-circle me-2"></i>Optional Contact Details</h5>
                                <p>Providing your contact information is completely optional. If you do, we can send you updates about your report and its resolution status.</p>
                                
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="reporterName" class="form-label">Your Name (Optional)</label>
                                        <input type="text" id="reporterName" name="reporterName" class="form-control" placeholder="Enter your name">
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="reporterContact" class="form-label">Contact (Optional)</label>
                                        <input type="text" id="reporterContact" name="reporterContact" class="form-control" placeholder="Email or phone number">
                                    </div>
                                </div>
                                
                                <div class="d-flex align-items-center gap-2 mb-3">
                                    <input type="checkbox" id="stayAnonymous" class="form-check-input">
                                    <label for="stayAnonymous" class="form-check-label">
                                        I prefer to stay completely anonymous
                                    </label>
                                </div>
                            </div>

                            <div class="button-container">
                                <button type="button" class="btn btn-outline-primary" id="backToStep3Btn">
                                    <i class="fas fa-arrow-left me-1"></i> Previous
                                </button>
                                <button type="button" class="btn btn-primary" id="toStep5Btn">
                                    Next <i class="fas fa-arrow-right ms-1"></i>
                                </button>
                            </div>
                        </div>

                        <!-- Step 5: Review & Submit (Anonymous users) -->
                        <div class="form-step" id="step5">
                            <h4 class="mb-4">Review & Submit</h4>
                            
                            <div class="preview-container" id="reviewContainer">
                                <!-- Will be filled by JavaScript -->
                            </div>

                            <div class="button-container">
                                <button type="button" class="btn btn-outline-primary" id="backToStep4Btn">
                                    <i class="fas fa-arrow-left me-1"></i> Previous
                                </button>
                                <button type="submit" class="btn btn-primary" id="submitBtn">
                                    <i class="fas fa-paper-plane me-1"></i> Submit Report
                                </button>
                            </div>
                        </div>
                        <?php else: ?>
                        <!-- Step 4: Review & Submit (Logged in users) -->
                        <div class="form-step" id="step4">
                            <h4 class="mb-4">Review & Submit</h4>
                            
                            <div class="preview-container" id="reviewContainer">
                                <!-- Will be filled by JavaScript -->
                            </div>

                            <div class="button-container">
                                <button type="button" class="btn btn-outline-primary" id="backToStep3BtnLoggedIn">
                                    <i class="fas fa-arrow-left me-1"></i> Previous
                                </button>
                                <button type="submit" class="btn btn-primary" id="submitBtnLoggedIn">
                                    <i class="fas fa-paper-plane me-1"></i> Submit Report
                                </button>
                            </div>
                        </div>
                        <?php endif; ?>
                    </form>

                    <!-- Pending Reports Section -->
                    <div id="pendingReportsContainer" class="pending-reports">
                        <h4><i class="fas fa-clock me-2"></i>Pending Reports</h4>
                        <div class="pending-list" id="pendingList">
                            <!-- Will be filled by JavaScript -->
                        </div>
                        <button type="button" class="btn btn-warning sync-all-btn" id="syncAllBtn">
                            <i class="fas fa-sync me-1"></i> Sync All Reports
                        </button>
                    </div>

                    <?php if (isset($_GET['success'])): ?>
                        <div class="success-message">
                            <i class="fas fa-check-circle me-2"></i> 
                            Your hazard report has been submitted successfully!
                            <?php if (isset($_GET['reportId'])): ?>
                                <br><strong>Report ID: #<?php echo htmlspecialchars($_GET['reportId']); ?></strong>
                                <br><small>Your report is pending admin approval and will be visible on the map once approved.</small>
                                <?php if ($is_logged_in): ?>
                                    <br><a href="my_reports.php" class="btn btn-primary btn-sm mt-2">
                                        <i class="fas fa-list me-1"></i>View My Reports
                                    </a>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
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
    
    <script>
        // User login status
        const isLoggedIn = <?php echo $is_logged_in ? 'true' : 'false'; ?>;
        const userName = "<?php echo addslashes($user_name); ?>";
        const userEmail = "<?php echo addslashes($user_email); ?>";
        
        // Global variables that need to be accessible across functions
        let map = null;
        let marker = null;
        let locationConfirmed = false;
        let isOnline = navigator.onLine;
        let pendingReports = [];
        
        // Select severity function - completely rewritten for reliability
        window.selectSeverity = function(value) {
            console.log("selectSeverity called with:", value);
            
            const severityInput = document.getElementById("severity");
            if (!severityInput) {
                console.error("Severity input not found");
                return;
            }
            
            // Set the value
            severityInput.value = value;
            
            // Update UI - remove all selected classes first
            const allTags = document.querySelectorAll(".severity-tag");
            allTags.forEach(tag => {
                tag.classList.remove("selected");
            });
            
            // Add selected class to clicked tag
            const selectedTag = document.querySelector(`.severity-tag[data-value="${value}"]`);
            if (selectedTag) {
                selectedTag.classList.add("selected");
                console.log("Severity UI updated for:", value);
            } else {
                console.error("Selected tag not found for value:", value);
            }
        };
        
        // Confirm location function - made global and robust
        window.confirmLocation = function() {
            console.log("confirmLocation called");
            
            const latitudeField = document.getElementById("latitude");
            const longitudeField = document.getElementById("longitude");
            const confirmLocationBtn = document.getElementById("confirmLocationBtn");
            
            if (!latitudeField || !longitudeField) {
                console.error("Location fields not found");
                showToast("Error", "Location fields not found", "danger");
                return;
            }
            
            if (!latitudeField.value || !longitudeField.value) {
                console.log("No location selected");
                showToast("Location Error", "Please select a location on the map first", "warning");
                return;
            }
            
            locationConfirmed = true;
            if (confirmLocationBtn) {
                confirmLocationBtn.classList.remove("pulse");
                confirmLocationBtn.classList.add("confirmed");
            }
            
            showToast("Location Confirmed", "Hazard location has been confirmed", "success");
            console.log("Location confirmed:", latitudeField.value, longitudeField.value);
        };
        
        // Get user location function - made global
        window.getUserLocation = function() {
            console.log("getUserLocation called");
            
            const addressField = document.getElementById("address");
            
            if (navigator.geolocation) {
                // Show loading
                showLoading();
                if (addressField) {
                    addressField.value = "Getting your location...";
                }
                
                navigator.geolocation.getCurrentPosition(
                    // Success callback
                    function(position) {
                        const lat = position.coords.latitude;
                        const lng = position.coords.longitude;
                        
                        console.log("Location found:", lat, lng);
                        
                        if (map) {
                            map.setView([lat, lng], 16);
                            setMapMarker(lat, lng);
                        }
                        
                        hideLoading();
                    },
                    // Error callback
                    function(error) {
                        console.error("Geolocation error:", error);
                        if (addressField) {
                            addressField.value = "Could not get your location. Please select on map.";
                        }
                        hideLoading();
                        showToast("Location Error", "Could not get your current location. Please tap on the map to set location.", "warning");
                    },
                    // Options
                    {
                        enableHighAccuracy: true,
                        timeout: 10000,
                        maximumAge: 0
                    }
                );
            } else {
                if (addressField) {
                    addressField.value = "Geolocation is not supported by this browser.";
                }
                showToast("Browser Error", "Geolocation is not supported by your browser. Please tap on the map to set location.", "danger");
            }
        };
        
        // Set map marker function
        function setMapMarker(lat, lng) {
            const latitudeField = document.getElementById("latitude");
            const longitudeField = document.getElementById("longitude");
            const addressField = document.getElementById("address");
            const confirmLocationBtn = document.getElementById("confirmLocationBtn");
            
            // Update location fields
            if (latitudeField) latitudeField.value = lat;
            if (longitudeField) longitudeField.value = lng;
            
            // Update or create marker
            if (marker) {
                marker.setLatLng([lat, lng]);
            } else {
                // Create a custom icon
                const hazardIcon = L.divIcon({
                    className: 'hazard-marker',
                    html: `<div style="background-color: var(--primary); width: 20px; height: 20px; border-radius: 50%; border: 3px solid white; box-shadow: 0 0 10px rgba(0, 0, 0, 0.3);"></div>`,
                    iconSize: [20, 20],
                    iconAnchor: [10, 10]
                });
                
                marker = L.marker([lat, lng], { icon: hazardIcon }).addTo(map);
            }
            
            // If online, try to get the address
            if (isOnline) {
                reverseGeocode(lat, lng);
            } else {
                if (addressField) {
                    addressField.value = `Location at ${lat.toFixed(6)}, ${lng.toFixed(6)} (Offline)`;
                }
            }
            
            // Reset confirmation state
            locationConfirmed = false;
            if (confirmLocationBtn) {
                confirmLocationBtn.classList.remove("confirmed");
                confirmLocationBtn.classList.add("pulse");
            }
        }
        
        // Reverse geocode coordinates to address
        function reverseGeocode(lat, lng) {
            const addressField = document.getElementById("address");
            if (addressField) {
                addressField.value = "Getting address...";
            }
            
            fetch(`https://nominatim.openstreetmap.org/reverse?lat=${lat}&lon=${lng}&format=json`)
                .then(response => response.json())
                .then(data => {
                    if (addressField) {
                        addressField.value = data.display_name || `Location at ${lat.toFixed(6)}, ${lng.toFixed(6)}`;
                    }
                })
                .catch(error => {
                    console.error("Error getting address:", error);
                    if (addressField) {
                        addressField.value = `Location at ${lat.toFixed(6)}, ${lng.toFixed(6)}`;
                    }
                });
        }
        
        // Show toast notification function
        function showToast(title, message, type = 'info', duration = 5000) {
            const toastContainer = document.getElementById("toastContainer");
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
                if (document.getElementById(toastId)) {
                    document.getElementById(toastId).remove();
                }
            }, duration);
        }
        
        // Show/hide loading spinner functions
        function showLoading() {
            const loaderOverlay = document.getElementById("loaderOverlay");
            if (loaderOverlay) {
                loaderOverlay.style.display = "flex";
            }
        }
        
        function hideLoading() {
            const loaderOverlay = document.getElementById("loaderOverlay");
            if (loaderOverlay) {
                loaderOverlay.style.display = "none";
            }
        }
        
        document.addEventListener("DOMContentLoaded", function() {
            // Form elements
            const form = document.getElementById("hazardForm");
            const steps = document.querySelectorAll(".form-step");
            const stepIndicators = document.querySelectorAll(".step");
            const progressBar = document.getElementById("progressBar");
            const loaderOverlay = document.getElementById("loaderOverlay");
            const photoUpload = document.getElementById("photoUpload");
            const imagePreview = document.getElementById("imagePreview");
            
            // Navigation buttons - adjust based on login status
            const toStep2Btn = document.getElementById("toStep2Btn");
            const backToStep1Btn = document.getElementById("backToStep1Btn");
            const toStep3Btn = document.getElementById("toStep3Btn");
            const backToStep2Btn = document.getElementById("backToStep2Btn");
            const toStep4Btn = document.getElementById("toStep4Btn");
            const backToStep3Btn = document.getElementById("backToStep3Btn");
            
            // Logged in users have different step structure
            if (isLoggedIn) {
                const backToStep3BtnLoggedIn = document.getElementById("backToStep3BtnLoggedIn");
                const submitBtnLoggedIn = document.getElementById("submitBtnLoggedIn");
            } else {
                const toStep5Btn = document.getElementById("toStep5Btn");
                const backToStep4Btn = document.getElementById("backToStep4Btn");
                const submitBtn = document.getElementById("submitBtn");
            }
            
            // Location related elements
            const getCurrentLocationBtn = document.getElementById("getCurrentLocationBtn");
            const confirmLocationBtn = document.getElementById("confirmLocationBtn");
            const addressField = document.getElementById("address");
            const latitudeField = document.getElementById("latitude");
            const longitudeField = document.getElementById("longitude");
            
            // Contact related elements (only for anonymous users)
            if (!isLoggedIn) {
                const stayAnonymousCheckbox = document.getElementById("stayAnonymous");
                const reporterNameField = document.getElementById("reporterName");
                const reporterContactField = document.getElementById("reporterContact");
            }
            
            // Offline sync related elements
            const statusIndicator = document.getElementById("statusIndicator");
            const statusText = document.getElementById("statusText");
            const pendingBadge = document.getElementById("pendingBadge");
            const pendingReportsContainer = document.getElementById("pendingReportsContainer");
            const pendingList = document.getElementById("pendingList");
            const syncAllBtn = document.getElementById("syncAllBtn");
            
            // State variables - local to DOMContentLoaded
            let currentStep = 0;
            let totalSteps = isLoggedIn ? 4 : 5; // Adjust total steps based on login status
            
            // Initialize the application
            initApp();
            
            // Application Initialization
            function initApp() {
                updateConnectivityStatus();
                loadPendingReports();
                setupEventListeners();
                updateStepIndicators();
            }
            
            // Set up event listeners
            function setupEventListeners() {
                // Step navigation - adjust for login status
                toStep2Btn.addEventListener("click", function() {
                    if (validateStep(0)) {
                        currentStep = 1;
                        updateStepIndicators();
                        initializeMap();
                    }
                });
                
                backToStep1Btn.addEventListener("click", function() {
                    currentStep = 0;
                    updateStepIndicators();
                });
                
                toStep3Btn.addEventListener("click", function() {
                    if (validateStep(1)) {
                        currentStep = 2;
                        updateStepIndicators();
                    }
                });
                
                backToStep2Btn.addEventListener("click", function() {
                    currentStep = 1;
                    updateStepIndicators();
                });
                
                toStep4Btn.addEventListener("click", function() {
                    if (validateStep(2)) {
                        currentStep = 3;
                        updateStepIndicators();
                        if (isLoggedIn) {
                            populateReviewStep(); // For logged in users, step 4 is review
                        }
                    }
                });
                
                backToStep3Btn.addEventListener("click", function() {
                    currentStep = 2;
                    updateStepIndicators();
                });
                
                if (isLoggedIn) {
                    // Logged in users: step 4 is review & submit
                    document.getElementById("backToStep3BtnLoggedIn").addEventListener("click", function() {
                        currentStep = 2;
                        updateStepIndicators();
                    });
                } else {
                    // Anonymous users: step 5 is review & submit
                    document.getElementById("toStep5Btn").addEventListener("click", function() {
                        currentStep = 4;
                        updateStepIndicators();
                        populateReviewStep();
                    });
                    
                    document.getElementById("backToStep4Btn").addEventListener("click", function() {
                        currentStep = 3;
                        updateStepIndicators();
                    });
                }
                
                // Remove duplicate event listener code since we're using onclick now
                // Just keep the other necessary event listeners
                
                // Anonymous checkbox (only for anonymous users)
                if (!isLoggedIn) {
                    document.getElementById("stayAnonymous").addEventListener("change", function() {
                        const reporterNameField = document.getElementById("reporterName");
                        const reporterContactField = document.getElementById("reporterContact");
                        
                        if (this.checked) {
                            reporterNameField.value = "";
                            reporterContactField.value = "";
                            reporterNameField.disabled = true;
                            reporterContactField.disabled = true;
                        } else {
                            reporterNameField.disabled = false;
                            reporterContactField.disabled = false;
                        }
                    });
                }
                
                // Photo upload preview
                photoUpload.addEventListener("change", function() {
                    const file = this.files[0];
                    if (file) {
                        const reader = new FileReader();
                        reader.onload = function(e) {
                            imagePreview.src = e.target.result;
                            imagePreview.style.display = "block";
                        };
                        reader.readAsDataURL(file);
                    }
                });
                
                // Form submission
                form.addEventListener("submit", handleFormSubmit);
                
                // Sync button
                syncAllBtn.addEventListener("click", syncAllReports);
                
                // Network status events
                window.addEventListener("online", function() {
                    isOnline = true;
                    updateConnectivityStatus();
                    checkAndSyncReports();
                });
                
                window.addEventListener("offline", function() {
                    isOnline = false;
                    updateConnectivityStatus();
                });
            }
            
            // Update step indicators
            function updateStepIndicators() {
                // Update steps visibility
                steps.forEach((step, index) => {
                    step.classList.toggle("active", index === currentStep);
                });
                
                // Update step indicators
                stepIndicators.forEach((indicator, index) => {
                    if (index < currentStep) {
                        indicator.classList.add("completed");
                        indicator.classList.remove("active");
                    } else if (index === currentStep) {
                        indicator.classList.add("active");
                        indicator.classList.remove("completed");
                    } else {
                        indicator.classList.remove("active", "completed");
                    }
                });
                
                // Update progress bar
                progressBar.style.width = `${((currentStep + 1) / totalSteps) * 100}%`;
            }
            
            // Validate step data
            function validateStep(step) {
                switch(step) {
                    case 0: // Hazard details step
                        const hazardType = document.getElementById("hazardType").value;
                        const severity = document.getElementById("severity").value;
                        
                        if (!hazardType) {
                            showToast("Input Error", "Please select a hazard type", "warning");
                            return false;
                        }
                        if (!severity) {
                            showToast("Input Error", "Please select a severity level", "warning");
                            return false;
                        }
                        return true;
                        
                    case 1: // Location step
                        if (!locationConfirmed) {
                            showToast("Location Error", "Please confirm the location by clicking the 'Confirm Location' button", "warning");
                            return false;
                        }
                        return true;
                        
                    case 2: // Photo upload step
                        if (!photoUpload.files || !photoUpload.files[0]) {
                            showToast("Upload Error", "Please take or upload a photo of the hazard", "warning");
                            return false;
                        }
                        return true;
                        
                    default:
                        return true;
                }
            }
            
            // This is moved outside the DOMContentLoaded event
            
            // Initialize map
            function initializeMap() {
                if (map !== null) return;
                
                // Create map
                map = L.map('map').setView([14.5995, 120.9842], 13); // Default: Manila, Philippines
                
                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
                    maxZoom: 19
                }).addTo(map);
                
                // Add click handler - use global function
                map.on('click', function(e) {
                    setMapMarker(e.latlng.lat, e.latlng.lng);
                });
                
                // Try to get user's location
                getUserLocation();
                
                // Refresh map size after initialization
                setTimeout(() => {
                    map.invalidateSize();
                }, 100);
            }
            
            // Remove these functions since they're now global
            // getUserLocation, setMapMarker, reverseGeocode, confirmLocation, showToast, showLoading, hideLoading are now global
            
            // Populate review step
            function populateReviewStep() {
                const hazardTypeElement = document.getElementById("hazardType");
                const severityElement = document.getElementById("severity");
                const descriptionElement = document.getElementById("description");
                
                const hazardTypeText = hazardTypeElement.options[hazardTypeElement.selectedIndex].text;
                const severityText = document.querySelector(`.severity-tag[data-value="${severityElement.value}"]`).textContent.trim();
                
                // Contact information
                let contactInfo = "";
                if (isLoggedIn) {
                    contactInfo = `${userName} (${userEmail})`;
                } else {
                    const reporterNameElement = document.getElementById("reporterName");
                    const reporterContactElement = document.getElementById("reporterContact");
                    const stayAnonymousElement = document.getElementById("stayAnonymous");
                    
                    if (stayAnonymousElement.checked || (!reporterNameElement.value && !reporterContactElement.value)) {
                        contactInfo = "Anonymous Report";
                    } else {
                        contactInfo = "";
                        if (reporterNameElement.value) {
                            contactInfo += `Name: ${reporterNameElement.value}`;
                        }
                        if (reporterContactElement.value) {
                            contactInfo += contactInfo ? `, Contact: ${reporterContactElement.value}` : `Contact: ${reporterContactElement.value}`;
                        }
                    }
                }
                
                const reviewContainer = document.getElementById("reviewContainer");
                reviewContainer.innerHTML = `
                    <div class="preview-item">
                        <div class="preview-label">Hazard Type</div>
                        <div class="preview-value">${hazardTypeText}</div>
                    </div>
                    <div class="preview-item">
                        <div class="preview-label">Severity</div>
                        <div class="preview-value">
                            <span class="severity-tag ${severityElement.value}" style="cursor: default;">${severityText}</span>
                        </div>
                    </div>
                    <div class="preview-item">
                        <div class="preview-label">Location</div>
                        <div class="preview-value">${addressField.value}</div>
                    </div>
                    <div class="preview-item">
                        <div class="preview-label">Description</div>
                        <div class="preview-value">${descriptionElement.value || "No description provided"}</div>
                    </div>
                    <div class="preview-item">
                        <div class="preview-label">Reporter Information</div>
                        <div class="preview-value">${contactInfo}</div>
                    </div>
                    <div class="preview-item">
                        <div class="preview-label">Photo</div>
                        <img src="${imagePreview.src}" class="preview-image" alt="Hazard Photo">
                    </div>
                `;
            }
            
            // Handle form submission
            function handleFormSubmit(e) {
                e.preventDefault();
                
                // Clear contact fields if staying anonymous (for non-logged in users)
                if (!isLoggedIn) {
                    const stayAnonymousCheckbox = document.getElementById("stayAnonymous");
                    const reporterNameField = document.getElementById("reporterName");
                    const reporterContactField = document.getElementById("reporterContact");
                    
                    if (stayAnonymousCheckbox.checked) {
                        reporterNameField.value = "";
                        reporterContactField.value = "";
                    }
                }
                
                // If online, submit normally
                if (isOnline) {
                    showLoading();
                    this.submit();
                    return;
                }
                
                // If offline, store locally
                storeReportLocally();
            }
            
            // Store report locally for offline mode
            function storeReportLocally() {
                showLoading();
                
                // Get form data
                const hazardType = document.getElementById("hazardType").value;
                const hazardTypeText = document.getElementById("hazardType").options[document.getElementById("hazardType").selectedIndex].text;
                const severity = document.getElementById("severity").value;
                const latitude = latitudeField.value;
                const longitude = longitudeField.value;
                const address = addressField.value;
                const description = document.getElementById("description").value;
                
                // Get reporter info based on login status
                let reporterName, reporterContact;
                if (isLoggedIn) {
                    reporterName = userName;
                    reporterContact = userEmail;
                } else {
                    const stayAnonymousCheckbox = document.getElementById("stayAnonymous");
                    reporterName = stayAnonymousCheckbox.checked ? "" : document.getElementById("reporterName").value;
                    reporterContact = stayAnonymousCheckbox.checked ? "" : document.getElementById("reporterContact").value;
                }
                
                // Get photo as base64
                const photoFile = photoUpload.files[0];
                const reader = new FileReader();
                
                reader.onload = function(e) {
                    const photoBase64 = e.target.result;
                    
                    // Create report object
                    const report = {
                        id: Date.now().toString(),
                        hazardType: hazardType,
                        hazardTypeText: hazardTypeText,
                        severity: severity,
                        latitude: latitude,
                        longitude: longitude,
                        address: address,
                        description: description,
                        reporterName: reporterName,
                        reporterContact: reporterContact,
                        photoBase64: photoBase64,
                        timestamp: Date.now(),
                        isLoggedIn: isLoggedIn,
                        userName: userName,
                        userEmail: userEmail
                    };
                    
                    // Add to pending reports
                    pendingReports.push(report);
                    
                    // Save to localStorage
                    localStorage.setItem("pendingHazardReports", JSON.stringify(pendingReports));
                    
                    // Update UI
                    updatePendingReportsUI();
                    
                    // Reset form
                    form.reset();
                    imagePreview.style.display = "none";
                    currentStep = 0;
                    updateStepIndicators();
                    locationConfirmed = false;
                    
                    if (!isLoggedIn) {
                        document.getElementById("reporterName").disabled = false;
                        document.getElementById("reporterContact").disabled = false;
                    }
                    
                    hideLoading();
                    
                    showToast("Report Saved", "Your report has been saved locally and will be uploaded when you're back online.", "success");
                };
                
                reader.onerror = function() {
                    hideLoading();
                    showToast("Error", "Error reading the image file. Please try again.", "danger");
                };
                
                reader.readAsDataURL(photoFile);
            }
            
            // Update connectivity status
            function updateConnectivityStatus() {
                statusIndicator.className = isOnline ? "status-indicator online" : "status-indicator offline";
                statusText.textContent = isOnline ? "Online" : "Offline";
                
                // Update badge and pending reports visibility
                updatePendingReportsUI();
            }
            
            // Load pending reports from localStorage
            function loadPendingReports() {
                const storedReports = localStorage.getItem("pendingHazardReports");
                if (storedReports) {
                    pendingReports = JSON.parse(storedReports);
                    updatePendingReportsUI();
                }
            }
            
            // Update pending reports UI
            function updatePendingReportsUI() {
                const count = pendingReports.length;
                
                // Update badge
                pendingBadge.textContent = count;
                pendingBadge.style.display = count > 0 ? "inline" : "none";
                
                // Update pending reports container
                pendingReportsContainer.style.display = count > 0 ? "block" : "none";
                
                // Clear and populate the list
                pendingList.innerHTML = "";
                pendingReports.forEach((report, index) => {
                    const date = new Date(report.timestamp);
                    const formattedDate = date.toLocaleDateString() + " " + date.toLocaleTimeString();
                    
                    const item = document.createElement("div");
                    item.className = "pending-item";
                    item.innerHTML = `
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <strong>${report.hazardTypeText}</strong>
                                <span class="text-muted ms-2">(${report.severity})</span>
                                ${report.isLoggedIn ? `<span class="badge bg-success ms-2">Logged In</span>` : ''}
                            </div>
                            <small>${formattedDate}</small>
                        </div>
                        <div class="text-truncate mt-1 text-muted">${report.address}</div>
                        <div class="mt-1">
                            <small class="text-info">
                                ${report.reporterName ? `Reporter: ${report.reporterName}` : 'Anonymous Report'}
                            </small>
                        </div>
                    `;
                    pendingList.appendChild(item);
                });
                
                // Update sync button state
                syncAllBtn.disabled = !isOnline || count === 0;
            }
            
            // Check and sync reports when back online
            function checkAndSyncReports() {
                if (pendingReports.length > 0) {
                    showToast("Connection Restored", `You're back online! You have ${pendingReports.length} pending reports to sync.`, "info");
                }
            }
            
            // Sync all reports
            function syncAllReports() {
                if (!isOnline) {
                    showToast("Sync Error", "Cannot sync reports while offline", "warning");
                    return;
                }
                
                if (pendingReports.length === 0) {
                    showToast("Sync Error", "No pending reports to sync", "warning");
                    return;
                }
                
                showLoading();
                
                // Create a copy of the reports to process
                const reportsToSync = [...pendingReports];
                let successCount = 0;
                let failCount = 0;
                
                // Process each report sequentially
                const processNextReport = (index) => {
                    if (index >= reportsToSync.length) {
                        // All done
                        hideLoading();
                        localStorage.setItem("pendingHazardReports", JSON.stringify(pendingReports));
                        updatePendingReportsUI();
                        showToast("Sync Complete", `Synced ${successCount} reports successfully, ${failCount} failed.`, "success");
                        return;
                    }
                    
                    const report = reportsToSync[index];
                    sendOfflineReport(report)
                        .then(success => {
                            if (success) {
                                // Remove from pending reports
                                pendingReports = pendingReports.filter(r => r.id !== report.id);
                                successCount++;
                            } else {
                                failCount++;
                            }
                            // Process next report
                            processNextReport(index + 1);
                        })
                        .catch(() => {
                            failCount++;
                            processNextReport(index + 1);
                        });
                };
                
                // Start processing
                processNextReport(0);
            }
            
            // Send offline report to server
            function sendOfflineReport(report) {
                return new Promise((resolve, reject) => {
                    const formData = new FormData();
                    
                    // Add report data
                    formData.append("hazardType", report.hazardType);
                    formData.append("severity", report.severity);
                    formData.append("latitude", report.latitude);
                    formData.append("longitude", report.longitude);
                    formData.append("address", report.address);
                    formData.append("description", report.description || "");
                    formData.append("reporterName", report.reporterName || "");
                    formData.append("reporterContact", report.reporterContact || "");
                    formData.append("photoBase64", report.photoBase64);
                    formData.append("isOfflineReport", "true");
                    
                    // Send the request
                    fetch("report_hazard.php", {
                        method: "POST",
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        resolve(data.success);
                    })
                    .catch(error => {
                        console.error("Error syncing report:", error);
                        reject(error);
                    });
                });
            }
            
            // Remove duplicate toast and loading functions since they're now global
        });
    </script>
</body>
</html>