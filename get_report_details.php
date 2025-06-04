<?php
// get_report_details.php - Get detailed information about a specific hazard report
header('Content-Type: application/json');

require_once 'db_connect.php';

// Simple session check for admin
session_start();
if (!isset($_SESSION['admin_logged_in'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

// Check if report ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid report ID']);
    exit;
}

$report_id = intval($_GET['id']);

// Fetch report details from database
$sql = "SELECT id, hazard_type, severity, latitude, longitude, address, description, image_path, reported_at, status, resolved, resolved_at, reporter_name, reporter_contact 
        FROM hazard_reports 
        WHERE id = ?";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]);
    exit;
}

$stmt->bind_param("i", $report_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Report not found']);
    exit;
}

$report = $result->fetch_assoc();

// Format the response
$response = [
    'success' => true,
    'report' => [
        'id' => $report['id'],
        'hazard_type' => ucwords(str_replace('_', ' ', $report['hazard_type'])),
        'severity' => ucfirst($report['severity']),
        'latitude' => $report['latitude'],
        'longitude' => $report['longitude'],
        'address' => $report['address'],
        'description' => $report['description'],
        'image_path' => $report['image_path'],
        'reported_at' => date('M d, Y g:i A', strtotime($report['reported_at'])),
        'status' => ucfirst($report['status']),
        'resolved' => $report['resolved'],
        'resolved_at' => $report['resolved_at'] ? date('M d, Y g:i A', strtotime($report['resolved_at'])) : null,
        'reporter_name' => $report['reporter_name'] ?: 'Anonymous',
        'reporter_contact' => $report['reporter_contact'] ?: 'Not provided'
    ]
];

echo json_encode($response);

$stmt->close();
$conn->close();
?>