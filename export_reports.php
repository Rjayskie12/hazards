<?php
// export_reports.php - Export hazard reports to CSV format
require_once 'db_connect.php';

// Simple session check for admin
session_start();
if (!isset($_SESSION['admin_logged_in'])) {
    http_response_code(403);
    die('Unauthorized access');
}

// Get filter parameters
$status_filter = $_GET['status'] ?? 'all';
$severity_filter = $_GET['severity'] ?? 'all';
$type_filter = $_GET['type'] ?? 'all';
$date_filter = $_GET['date'] ?? '';
$start_date = $_GET['start_date'] ?? '';
$end_date = $_GET['end_date'] ?? '';
$format = $_GET['format'] ?? 'csv';

// Build SQL query with filters
$sql = "SELECT id, hazard_type, severity, latitude, longitude, address, description, reporter_name, reporter_contact, reported_at, status, resolved, resolved_at 
        FROM hazard_reports WHERE 1=1";

$params = [];
$types = "";

if ($status_filter !== 'all') {
    if ($status_filter === 'resolved') {
        $sql .= " AND resolved = 1";
    } elseif ($status_filter === 'unresolved') {
        $sql .= " AND (resolved = 0 OR resolved IS NULL)";
    } else {
        $sql .= " AND status = ?";
        $params[] = $status_filter;
        $types .= "s";
    }
}

if ($severity_filter !== 'all') {
    $sql .= " AND severity = ?";
    $params[] = $severity_filter;
    $types .= "s";
}

if ($type_filter !== 'all') {
    $sql .= " AND hazard_type = ?";
    $params[] = $type_filter;
    $types .= "s";
}

if ($date_filter) {
    $sql .= " AND DATE(reported_at) = ?";
    $params[] = $date_filter;
    $types .= "s";
}

if ($start_date && $end_date) {
    $sql .= " AND DATE(reported_at) BETWEEN ? AND ?";
    $params[] = $start_date;
    $params[] = $end_date;
    $types .= "ss";
}

$sql .= " ORDER BY reported_at DESC";

// Execute query
$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

// Generate filename
$filename = 'hazard_reports_' . date('Y-m-d_H-i-s');

if ($format === 'csv') {
    // CSV Export
    $filename .= '.csv';
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');

    // Open output stream
    $output = fopen('php://output', 'w');

    // Write CSV headers
    $headers = [
        'ID',
        'Hazard Type',
        'Severity',
        'Latitude',
        'Longitude',
        'Address',
        'Description',
        'Reporter Name',
        'Reporter Contact',
        'Status',
        'Resolved',
        'Reported At',
        'Resolved At'
    ];

    fputcsv($output, $headers);

    // Write data rows
    while ($row = $result->fetch_assoc()) {
        $csvRow = [
            $row['id'],
            ucwords(str_replace('_', ' ', $row['hazard_type'])),
            ucfirst($row['severity']),
            $row['latitude'],
            $row['longitude'],
            $row['address'],
            $row['description'],
            $row['reporter_name'] ?: 'Anonymous',
            $row['reporter_contact'] ?: 'Not provided',
            ucfirst($row['status']),
            $row['resolved'] ? 'Yes' : 'No',
            $row['reported_at'],
            $row['resolved_at'] ?? 'N/A'
        ];
        
        fputcsv($output, $csvRow);
    }

    // Close output stream
    fclose($output);

} elseif ($format === 'pdf') {
    // For PDF export, you would need a PDF library like TCPDF or FPDF
    // For now, we'll just redirect to CSV export
    header('Location: export_reports.php?format=csv&' . http_build_query($_GET));
    exit;
    
} else {
    // JSON Export
    $filename .= '.json';
    header('Content-Type: application/json');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');

    $data = [];
    while ($row = $result->fetch_assoc()) {
        $data[] = [
            'id' => $row['id'],
            'hazard_type' => ucwords(str_replace('_', ' ', $row['hazard_type'])),
            'severity' => ucfirst($row['severity']),
            'latitude' => (float)$row['latitude'],
            'longitude' => (float)$row['longitude'],
            'address' => $row['address'],
            'description' => $row['description'],
            'reporter_name' => $row['reporter_name'] ?: 'Anonymous',
            'reporter_contact' => $row['reporter_contact'] ?: 'Not provided',
            'status' => ucfirst($row['status']),
            'resolved' => (bool)$row['resolved'],
            'reported_at' => $row['reported_at'],
            'resolved_at' => $row['resolved_at']
        ];
    }

    echo json_encode([
        'export_date' => date('Y-m-d H:i:s'),
        'total_records' => count($data),
        'filters_applied' => [
            'status' => $status_filter,
            'severity' => $severity_filter,
            'type' => $type_filter,
            'date' => $date_filter,
            'date_range' => $start_date && $end_date ? "$start_date to $end_date" : null
        ],
        'data' => $data
    ], JSON_PRETTY_PRINT);
}

$stmt->close();
$conn->close();
?>