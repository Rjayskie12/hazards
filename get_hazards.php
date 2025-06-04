<?php
header('Content-Type: application/json');

$servername = "localhost";
$username = "root"; 
$password = ""; 
$dbname = "hazard_mapping";

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die(json_encode(["error" => "Connection failed: " . $conn->connect_error]));
}

// Check if status filter is provided
$statusFilter = isset($_GET['status']) ? $_GET['status'] : null;
$includeResolved = isset($_GET['include_resolved']) ? $_GET['include_resolved'] === 'true' : false;

// Base SQL query
$sql = "SELECT id, hazard_type, severity, latitude, longitude, address, image_path, reported_at, status, resolved, resolved_at 
        FROM hazard_reports";

// Add filters
$conditions = [];
$params = [];
$types = "";

if ($statusFilter) {
    // Filter by status
    $conditions[] = "status = ?";
    $params[] = $statusFilter;
    $types .= "s";
} else {
    // Default behavior - only show approved hazards
    $conditions[] = "status = 'approved'";
}

// Handle resolved filter
if (!$includeResolved) {
    // Exclude resolved hazards by default
    $conditions[] = "(resolved = 0 OR resolved IS NULL)";
}

// Build WHERE clause
if (!empty($conditions)) {
    $sql .= " WHERE " . implode(" AND ", $conditions);
}

// Add order
$sql .= " ORDER BY reported_at DESC";

// Execute query
if (!empty($params)) {
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
} else {
    $result = $conn->query($sql);
}

$hazards = [];
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $hazards[] = $row;
    }
}

echo json_encode($hazards);

if (isset($stmt)) {
    $stmt->close();
}
$conn->close();
?>