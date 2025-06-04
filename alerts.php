<?php
// alerts.php - Enhanced Alerts System with Engineer Integration
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

// Alert System for managing hazard alerts
class AlertSystem {
    private $conn;
    private $userId = 1; // Default user ID (replace with actual user authentication)
    
    public function __construct() {
        global $conn;
        $this->conn = $conn;
        if (!$conn) {
            throw new Exception("Database connection failed");
        }
    }
    
    // Get active alerts from the database
    public function getActiveAlerts($limit = 20) {
        $sql = "SELECT hr.*, 
                       u_approved.full_name as approved_by_name,
                       u_resolved.full_name as resolved_by_name,
                       u_resolved.specialization as resolved_by_specialization
                FROM hazard_reports hr 
                LEFT JOIN users u_approved ON hr.approved_by = u_approved.id 
                LEFT JOIN users u_resolved ON hr.resolved_by = u_resolved.id
                WHERE hr.status = 'approved' AND (hr.resolved = 0 OR hr.resolved IS NULL)
                ORDER BY 
                    CASE hr.severity 
                        WHEN 'critical' THEN 1 
                        WHEN 'high' THEN 2 
                        WHEN 'medium' THEN 3 
                        WHEN 'minor' THEN 4 
                    END,
                    hr.reported_at DESC 
                LIMIT ?";
                
        $stmt = $this->conn->prepare($sql);
        if (!$stmt) {
            return $this->getFallbackAlerts(false, $limit);
        }
        
        $stmt->bind_param("i", $limit);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $alerts = [];
        if ($result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                // Calculate a mock distance (in real app, calculate from user's location)
                $distance = $this->calculateMockDistance($row['latitude'], $row['longitude']);
                
                // Format the alert with additional data
                $alerts[] = [
                    'id' => $row['id'],
                    'title' => $this->generateAlertTitle($row['hazard_type'], $row['severity']),
                    'description' => $this->generateAlertDescription($row['hazard_type'], $row['severity']),
                    'address' => $row['address'],
                    'hazard_type' => $row['hazard_type'],
                    'severity' => $row['severity'],
                    'distance' => $distance,
                    'reported_at' => $row['reported_at'],
                    'approved_at' => $row['approved_at'],
                    'location' => [$row['latitude'], $row['longitude']],
                    'resolved' => false,
                    'image_path' => $row['image_path'],
                    'approved_by_name' => $row['approved_by_name'],
                    'reporter_name' => $row['reporter_name'] ?: 'Anonymous',
                    'urgency_score' => $this->calculateUrgencyScore($row['severity'], $row['reported_at'])
                ];
            }
        }
        
        return $alerts;
    }
    
    // Get resolved alerts with engineer information
    public function getResolvedAlerts($limit = 15) {
        $sql = "SELECT hr.*, 
                       u_approved.full_name as approved_by_name,
                       u_resolved.full_name as resolved_by_name,
                       u_resolved.specialization as resolved_by_specialization,
                       u_resolved.email as resolved_by_email
                FROM hazard_reports hr 
                LEFT JOIN users u_approved ON hr.approved_by = u_approved.id 
                LEFT JOIN users u_resolved ON hr.resolved_by = u_resolved.id
                WHERE hr.status = 'approved' AND hr.resolved = 1
                ORDER BY hr.resolved_at DESC 
                LIMIT ?";
                
        $stmt = $this->conn->prepare($sql);
        if (!$stmt) {
            return [];
        }
        
        $stmt->bind_param("i", $limit);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $resolvedAlerts = [];
        if ($result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $distance = $this->calculateMockDistance($row['latitude'], $row['longitude']);
                
                $resolvedAlerts[] = [
                    'id' => $row['id'],
                    'title' => $this->generateAlertTitle($row['hazard_type'], $row['severity']),
                    'description' => $this->generateAlertDescription($row['hazard_type'], $row['severity']),
                    'address' => $row['address'],
                    'hazard_type' => $row['hazard_type'],
                    'severity' => $row['severity'],
                    'distance' => $distance,
                    'reported_at' => $row['reported_at'],
                    'resolved_at' => $row['resolved_at'],
                    'location' => [$row['latitude'], $row['longitude']],
                    'resolved' => true,
                    'image_path' => $row['image_path'],
                    'approved_by_name' => $row['approved_by_name'],
                    'resolved_by_name' => $row['resolved_by_name'],
                    'resolved_by_specialization' => $row['resolved_by_specialization'],
                    'resolved_by_email' => $row['resolved_by_email'],
                    'resolution_notes' => $row['resolution_notes'],
                    'reporter_name' => $row['reporter_name'] ?: 'Anonymous',
                    'resolution_time' => $this->calculateResolutionTime($row['reported_at'], $row['resolved_at'])
                ];
            }
        }
        
        return $resolvedAlerts;
    }
    
    // Get all alerts (both active and resolved) with filters
    public function getAllAlertsWithFilters($statusFilter = 'all', $severityFilter = 'all', $typeFilter = 'all', $limit = 50) {
        $sql = "SELECT hr.*, 
                       u_approved.full_name as approved_by_name,
                       u_resolved.full_name as resolved_by_name,
                       u_resolved.specialization as resolved_by_specialization
                FROM hazard_reports hr 
                LEFT JOIN users u_approved ON hr.approved_by = u_approved.id 
                LEFT JOIN users u_resolved ON hr.resolved_by = u_resolved.id
                WHERE hr.status = 'approved'";
        
        $params = [];
        $types = "";
        
        if ($statusFilter === 'active') {
            $sql .= " AND (hr.resolved = 0 OR hr.resolved IS NULL)";
        } elseif ($statusFilter === 'resolved') {
            $sql .= " AND hr.resolved = 1";
        }
        
        if ($severityFilter !== 'all') {
            $sql .= " AND hr.severity = ?";
            $params[] = $severityFilter;
            $types .= "s";
        }
        
        if ($typeFilter !== 'all') {
            $sql .= " AND hr.hazard_type = ?";
            $params[] = $typeFilter;
            $types .= "s";
        }
        
        $sql .= " ORDER BY 
                    CASE WHEN hr.resolved = 0 OR hr.resolved IS NULL THEN 0 ELSE 1 END,
                    CASE hr.severity 
                        WHEN 'critical' THEN 1 
                        WHEN 'high' THEN 2 
                        WHEN 'medium' THEN 3 
                        WHEN 'minor' THEN 4 
                    END,
                    hr.reported_at DESC 
                  LIMIT ?";
        
        $params[] = $limit;
        $types .= "i";
        
        $stmt = $this->conn->prepare($sql);
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $result = $stmt->get_result();
        
        $alerts = [];
        while ($row = $result->fetch_assoc()) {
            $distance = $this->calculateMockDistance($row['latitude'], $row['longitude']);
            
            $alert = [
                'id' => $row['id'],
                'title' => $this->generateAlertTitle($row['hazard_type'], $row['severity']),
                'description' => $this->generateAlertDescription($row['hazard_type'], $row['severity']),
                'address' => $row['address'],
                'hazard_type' => $row['hazard_type'],
                'severity' => $row['severity'],
                'distance' => $distance,
                'reported_at' => $row['reported_at'],
                'location' => [$row['latitude'], $row['longitude']],
                'resolved' => (bool)$row['resolved'],
                'image_path' => $row['image_path'],
                'approved_by_name' => $row['approved_by_name'],
                'reporter_name' => $row['reporter_name'] ?: 'Anonymous'
            ];
            
            if ($row['resolved']) {
                $alert['resolved_at'] = $row['resolved_at'];
                $alert['resolved_by_name'] = $row['resolved_by_name'];
                $alert['resolved_by_specialization'] = $row['resolved_by_specialization'];
                $alert['resolution_notes'] = $row['resolution_notes'];
                $alert['resolution_time'] = $this->calculateResolutionTime($row['reported_at'], $row['resolved_at']);
            } else {
                $alert['urgency_score'] = $this->calculateUrgencyScore($row['severity'], $row['reported_at']);
            }
            
            $alerts[] = $alert;
        }
        
        return $alerts;
    }
    
    // Get alert statistics
    public function getAlertStatistics() {
        $sql = "SELECT 
                    COUNT(*) as total_reports,
                    SUM(CASE WHEN resolved = 0 OR resolved IS NULL THEN 1 ELSE 0 END) as active_alerts,
                    SUM(CASE WHEN resolved = 1 THEN 1 ELSE 0 END) as resolved_alerts,
                    SUM(CASE WHEN severity = 'critical' AND (resolved = 0 OR resolved IS NULL) THEN 1 ELSE 0 END) as critical_active,
                    SUM(CASE WHEN severity = 'high' AND (resolved = 0 OR resolved IS NULL) THEN 1 ELSE 0 END) as high_active,
                    AVG(CASE WHEN resolved = 1 THEN 
                        TIMESTAMPDIFF(HOUR, reported_at, resolved_at) 
                        ELSE NULL END) as avg_resolution_hours
                FROM hazard_reports 
                WHERE status = 'approved'";
        
        $result = $this->conn->query($sql);
        return $result ? $result->fetch_assoc() : [
            'total_reports' => 0,
            'active_alerts' => 0,
            'resolved_alerts' => 0,
            'critical_active' => 0,
            'high_active' => 0,
            'avg_resolution_hours' => 0
        ];
    }
    
    // Calculate urgency score based on severity and time
    private function calculateUrgencyScore($severity, $reportedAt) {
        $hoursAge = (time() - strtotime($reportedAt)) / 3600;
        
        $severityWeights = [
            'critical' => 4,
            'high' => 3,
            'medium' => 2,
            'minor' => 1
        ];
        
        $baseScore = $severityWeights[$severity] ?? 1;
        $timeMultiplier = min(2, 1 + ($hoursAge / 24)); // Increases urgency over time
        
        return round($baseScore * $timeMultiplier, 1);
    }
    
    // Calculate resolution time in human-readable format
    private function calculateResolutionTime($reportedAt, $resolvedAt) {
        $diff = strtotime($resolvedAt) - strtotime($reportedAt);
        
        if ($diff < 3600) {
            return round($diff / 60) . ' minutes';
        } elseif ($diff < 86400) {
            return round($diff / 3600, 1) . ' hours';
        } else {
            return round($diff / 86400, 1) . ' days';
        }
    }
    
    // Generate a title based on hazard type and severity
    private function generateAlertTitle($hazardType, $severity) {
        $hazardTypeName = ucwords(str_replace('_', ' ', $hazardType));
        
        switch ($severity) {
            case 'critical':
                return "🚨 Critical {$hazardTypeName} Alert";
            case 'high':
                return "⚠️ Major {$hazardTypeName} Reported";
            case 'medium':
                return "🔶 {$hazardTypeName} Warning";
            case 'minor':
                return "ℹ️ Minor {$hazardTypeName} Reported";
            default:
                return "📍 {$hazardTypeName} Alert";
        }
    }
    
    // Generate a description based on hazard type and severity
    private function generateAlertDescription($hazardType, $severity) {
        $descriptions = [
            'pothole' => [
                'critical' => 'Extremely large pothole causing severe vehicle damage. Road may be impassable.',
                'high' => 'Large pothole affecting multiple lanes. Drive with extreme caution.',
                'medium' => 'Significant pothole detected. Reduce speed and avoid if possible.',
                'minor' => 'Small pothole reported. Exercise normal caution while driving.'
            ],
            'road_crack' => [
                'critical' => 'Major structural crack across the entire road width. Potential road collapse risk.',
                'high' => 'Large crack affecting road stability. Avoid heavy vehicles.',
                'medium' => 'Visible crack extending across traffic lanes. Monitor for expansion.',
                'minor' => 'Minor surface crack detected. Regular monitoring recommended.'
            ],
            'flooding' => [
                'critical' => 'Severe flooding with water depth exceeding 30cm. Road completely impassable.',
                'high' => 'Significant flooding affecting multiple lanes. Seek alternative routes.',
                'medium' => 'Moderate flooding reported. Exercise extreme caution if crossing.',
                'minor' => 'Minor water accumulation on road surface. Reduce speed.'
            ],
            'fallen_tree' => [
                'critical' => 'Large tree completely blocking all lanes. Road closure in effect.',
                'high' => 'Tree blocking multiple lanes. Significant traffic disruption expected.',
                'medium' => 'Tree debris partially blocking traffic. Single lane may be passable.',
                'minor' => 'Small branches on roadway. Minor traffic impact.'
            ],
            'landslide' => [
                'critical' => 'Major landslide with complete road blockage. Area evacuation may be required.',
                'high' => 'Significant landslide affecting road stability. Avoid area completely.',
                'medium' => 'Minor landslide with debris on roadway. Proceed with extreme caution.',
                'minor' => 'Small rocks and debris from hillside. Monitor for additional activity.'
            ],
            'debris' => [
                'critical' => 'Large debris completely blocking traffic flow. Heavy equipment required for removal.',
                'high' => 'Significant debris affecting multiple lanes. Traffic control in place.',
                'medium' => 'Moderate debris scattered across roadway. Reduce speed significantly.',
                'minor' => 'Minor debris reported on road surface. Exercise normal caution.'
            ],
            'construction' => [
                'critical' => 'Emergency construction work with complete road closure. Use alternative routes.',
                'high' => 'Major construction affecting multiple lanes. Expect significant delays.',
                'medium' => 'Construction work with lane restrictions. Allow extra travel time.',
                'minor' => 'Minor construction activity. Minimal traffic impact expected.'
            ],
            'accident' => [
                'critical' => 'Major accident with complete road closure. Emergency services on scene.',
                'high' => 'Serious accident affecting multiple lanes. Traffic being diverted.',
                'medium' => 'Traffic accident with lane restrictions. Expect delays.',
                'minor' => 'Minor incident cleared but residual delays may occur.'
            ]
        ];
        
        return $descriptions[$hazardType][$severity] ?? "Road hazard reported in this area. Exercise appropriate caution.";
    }
    
    // Calculate mock distance for demo purposes
    private function calculateMockDistance($lat, $lng) {
        // In a real app, calculate distance from user's current location
        // For demo, return a random distance between 0.1 and 15 km
        return round(mt_rand(1, 150) / 10, 1);
    }
    
    // Fallback data if database query fails
    private function getFallbackAlerts($resolved, $limit) {
        return []; // Return empty array if database fails
    }
    
    // Get user alert preferences
    public function getUserPreferences() {
        // In a real app, fetch from user_preferences table
        return [
            'distance' => 10, // km
            'categories' => ['pothole', 'road_crack', 'flooding', 'fallen_tree', 'landslide', 'debris', 'construction', 'accident'],
            'severity' => ['minor', 'medium', 'high', 'critical'],
            'frequency' => 'immediate'
        ];
    }
    
    // Update user preferences
    public function updatePreferences($preferences) {
        // In a real app, save to user_preferences table
        return true;
    }
}

// Initialize Alert System
try {
    $alertSystem = new AlertSystem();
} catch (Exception $e) {
    error_log("AlertSystem initialization error: " . $e->getMessage());
    die("An error occurred while initializing the alert system. Please check database connection.");
}

// Handle form submission for preferences
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['distance-range'])) {
    $preferences = [
        'distance' => (int)$_POST['distance-range'],
        'categories' => $_POST['categories'] ?? [],
        'severity' => $_POST['severity'] ?? [],
        'frequency' => $_POST['frequency-select']
    ];
    $alertSystem->updatePreferences($preferences);
}

// Get filter parameters
$statusFilter = $_GET['status'] ?? 'all';
$severityFilter = $_GET['severity'] ?? 'all';
$typeFilter = $_GET['type'] ?? 'all';

// Get alerts based on current tab/filter
$activeAlerts = $alertSystem->getActiveAlerts(20);
$resolvedAlerts = $alertSystem->getResolvedAlerts(15);
$allAlerts = $alertSystem->getAllAlertsWithFilters($statusFilter, $severityFilter, $typeFilter, 50);
$statistics = $alertSystem->getAlertStatistics();

// Get user preferences
$preferences = $alertSystem->getUserPreferences();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RoadSense - Alerts & Notifications</title>
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

        /* Alert Cards */
        .alert-card {
            background-color: white;
            border-radius: 12px;
            box-shadow: var(--card-shadow);
            margin-bottom: 1.5rem;
            overflow: hidden;
            border-left: 5px solid var(--primary);
            transition: var(--transition);
            position: relative;
        }

        .alert-card:hover {
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
            transform: translateY(-3px);
        }

        .alert-card.resolved {
            border-left-color: var(--success);
            opacity: 0.85;
        }

        .alert-card.critical {
            border-left-color: #9b59b6;
            animation: pulse-critical 2s infinite;
        }

        .alert-card.high {
            border-left-color: var(--danger);
        }

        .alert-card.medium {
            border-left-color: var(--warning);
        }

        .alert-card.minor {
            border-left-color: var(--success);
        }

        @keyframes pulse-critical {
            0%, 100% { box-shadow: var(--card-shadow); }
            50% { box-shadow: 0 8px 25px rgba(155, 89, 182, 0.3); }
        }

        .alert-header {
            display: flex;
            justify-content: between;
            align-items: flex-start;
            padding: 1.25rem 1.5rem 0.75rem;
        }

        .alert-body {
            padding: 0 1.5rem 1rem;
        }

        .alert-footer {
            padding: 1rem 1.5rem;
            background-color: rgba(0, 0, 0, 0.02);
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-top: 1px solid rgba(0, 0, 0, 0.05);
        }

        .alert-title {
            font-weight: 600;
            margin-bottom: 0.5rem;
            font-size: 1.1rem;
            flex: 1;
        }

        .alert-meta {
            display: flex;
            align-items: center;
            margin-bottom: 0.75rem;
            color: var(--secondary-light);
            font-size: 0.9rem;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .alert-meta i {
            margin-right: 0.5rem;
            width: 16px;
            text-align: center;
        }

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
            animation: pulse-badge 1.5s infinite;
        }

        @keyframes pulse-badge {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.05); }
        }

        .distance-badge {
            background-color: rgba(23, 162, 184, 0.1);
            color: var(--info);
            padding: 0.35rem 0.75rem;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 500;
        }

        .urgency-badge {
            position: absolute;
            top: 1rem;
            right: 1rem;
            background: linear-gradient(45deg, var(--primary), var(--primary-dark));
            color: white;
            padding: 0.25rem 0.75rem;
            border-radius: 15px;
            font-size: 0.75rem;
            font-weight: 600;
        }

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

        .alert-map {
            height: 200px;
            border-radius: 8px;
            overflow: hidden;
            margin-top: 1rem;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
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

        /* Tabs */
        .nav-tabs {
            border-bottom: 2px solid #dee2e6;
            margin-bottom: 2rem;
        }

        .nav-tabs .nav-link {
            border: none;
            border-radius: 8px 8px 0 0;
            padding: 1rem 1.5rem;
            font-weight: 500;
            color: var(--secondary);
            transition: var(--transition);
        }

        .nav-tabs .nav-link:hover {
            border-color: transparent;
            background-color: rgba(255, 75, 75, 0.1);
        }

        .nav-tabs .nav-link.active {
            background-color: var(--primary);
            color: white;
            border-color: var(--primary);
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

            .alert-footer {
                flex-direction: column;
                gap: 1rem;
                align-items: flex-start;
            }

            .alert-actions {
                width: 100%;
            }

            .alert-actions .btn {
                width: 100%;
                margin-bottom: 0.5rem;
            }

            .filter-row {
                flex-direction: column;
            }

            .filter-group {
                min-width: 100%;
            }

            .stat-number {
                font-size: 2rem;
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
                    <li class="nav-item">
                        <a class="nav-link" href="my_reports.php"><i class="fas fa-flag me-1"></i> My Reports</a>
                    </li>
                    
                    <li class="nav-item active">
                        <a class="nav-link active" href="alerts.php"><i class="fas fa-bell me-1"></i> Alerts</a>
                    </li>
                </ul>
                <div class="d-flex align-items-center">
                    <div class="position-relative me-3">
                        <a href="#" class="btn btn-outline-light btn-sm" id="notificationsBtn">
                            <i class="fas fa-bell"></i>
                            <span class="notification-badge"><?php echo $statistics['active_alerts']; ?></span>
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
            <!-- Statistics Overview -->
            <div class="row mb-4">
                <div class="col-md-2 col-6">
                    <div class="stat-card bg-primary">
                        <div class="stat-icon">
                            <i class="fas fa-exclamation-triangle"></i>
                        </div>
                        <div class="stat-content">
                            <div class="stat-number"><?php echo $statistics['active_alerts']; ?></div>
                            <div class="stat-label">Active Alerts</div>
                        </div>
                    </div>
                </div>
                <div class="col-md-2 col-6">
                    <div class="stat-card bg-danger">
                        <div class="stat-icon">
                            <i class="fas fa-fire"></i>
                        </div>
                        <div class="stat-content">
                            <div class="stat-number"><?php echo $statistics['critical_active']; ?></div>
                            <div class="stat-label">Critical</div>
                        </div>
                    </div>
                </div>
                <div class="col-md-2 col-6">
                    <div class="stat-card bg-warning">
                        <div class="stat-icon">
                            <i class="fas fa-exclamation"></i>
                        </div>
                        <div class="stat-content">
                            <div class="stat-number"><?php echo $statistics['high_active']; ?></div>
                            <div class="stat-label">High Priority</div>
                        </div>
                    </div>
                </div>
                <div class="col-md-2 col-6">
                    <div class="stat-card bg-success">
                        <div class="stat-icon">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <div class="stat-content">
                            <div class="stat-number"><?php echo $statistics['resolved_alerts']; ?></div>
                            <div class="stat-label">Resolved</div>
                        </div>
                    </div>
                </div>
                <div class="col-md-2 col-6">
                    <div class="stat-card bg-info">
                        <div class="stat-icon">
                            <i class="fas fa-chart-line"></i>
                        </div>
                        <div class="stat-content">
                            <div class="stat-number"><?php echo $statistics['total_reports']; ?></div>
                            <div class="stat-label">Total Reports</div>
                        </div>
                    </div>
                </div>
                <div class="col-md-2 col-6">
                    <div class="stat-card bg-secondary">
                        <div class="stat-icon">
                            <i class="fas fa-clock"></i>
                        </div>
                        <div class="stat-content">
                            <div class="stat-number"><?php echo round($statistics['avg_resolution_hours'] ?: 0); ?>h</div>
                            <div class="stat-label">Avg Resolution</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Filter Section -->
            <div class="filter-section">
                <h5><i class="fas fa-filter me-2"></i>Filter Alerts</h5>
                <form method="GET" action="alerts.php">
                    <div class="filter-row">
                        <div class="filter-group">
                            <label>Status</label>
                            <select class="form-select" name="status">
                                <option value="all" <?php echo $statusFilter === 'all' ? 'selected' : ''; ?>>All Alerts</option>
                                <option value="active" <?php echo $statusFilter === 'active' ? 'selected' : ''; ?>>Active Only</option>
                                <option value="resolved" <?php echo $statusFilter === 'resolved' ? 'selected' : ''; ?>>Resolved Only</option>
                            </select>
                        </div>
                        <div class="filter-group">
                            <label>Severity</label>
                            <select class="form-select" name="severity">
                                <option value="all" <?php echo $severityFilter === 'all' ? 'selected' : ''; ?>>All Severities</option>
                                <option value="critical" <?php echo $severityFilter === 'critical' ? 'selected' : ''; ?>>Critical</option>
                                <option value="high" <?php echo $severityFilter === 'high' ? 'selected' : ''; ?>>High</option>
                                <option value="medium" <?php echo $severityFilter === 'medium' ? 'selected' : ''; ?>>Medium</option>
                                <option value="minor" <?php echo $severityFilter === 'minor' ? 'selected' : ''; ?>>Minor</option>
                            </select>
                        </div>
                        <div class="filter-group">
                            <label>Type</label>
                            <select class="form-select" name="type">
                                <option value="all" <?php echo $typeFilter === 'all' ? 'selected' : ''; ?>>All Types</option>
                                <option value="pothole" <?php echo $typeFilter === 'pothole' ? 'selected' : ''; ?>>Pothole</option>
                                <option value="road_crack" <?php echo $typeFilter === 'road_crack' ? 'selected' : ''; ?>>Road Crack</option>
                                <option value="flooding" <?php echo $typeFilter === 'flooding' ? 'selected' : ''; ?>>Flooding</option>
                                <option value="fallen_tree" <?php echo $typeFilter === 'fallen_tree' ? 'selected' : ''; ?>>Fallen Tree</option>
                                <option value="landslide" <?php echo $typeFilter === 'landslide' ? 'selected' : ''; ?>>Landslide</option>
                                <option value="debris" <?php echo $typeFilter === 'debris' ? 'selected' : ''; ?>>Road Debris</option>
                                <option value="construction" <?php echo $typeFilter === 'construction' ? 'selected' : ''; ?>>Construction</option>
                                <option value="accident" <?php echo $typeFilter === 'accident' ? 'selected' : ''; ?>>Accident</option>
                            </select>
                        </div>
                        <div class="filter-group">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-search me-1"></i>Apply Filters
                            </button>
                            <a href="alerts.php" class="btn btn-outline-secondary ms-2">
                                <i class="fas fa-undo me-1"></i>Reset
                            </a>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Tabs Navigation -->
            <ul class="nav nav-tabs" id="alertTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="active-tab" data-bs-toggle="tab" data-bs-target="#active-alerts" type="button" role="tab">
                        <i class="fas fa-exclamation-triangle me-2"></i>Active Alerts 
                        <span class="badge bg-danger ms-2"><?php echo count($activeAlerts); ?></span>
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="resolved-tab" data-bs-toggle="tab" data-bs-target="#resolved-alerts" type="button" role="tab">
                        <i class="fas fa-check-circle me-2"></i>Resolved Alerts 
                        <span class="badge bg-success ms-2"><?php echo count($resolvedAlerts); ?></span>
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="all-tab" data-bs-toggle="tab" data-bs-target="#all-alerts" type="button" role="tab">
                        <i class="fas fa-list me-2"></i>All Alerts 
                        <span class="badge bg-info ms-2"><?php echo count($allAlerts); ?></span>
                    </button>
                </li>
            </ul>

            <!-- Tab Content -->
            <div class="tab-content" id="alertTabsContent">
                <!-- Active Alerts Tab -->
                <div class="tab-pane fade show active" id="active-alerts" role="tabpanel">
                    <?php if (empty($activeAlerts)): ?>
                        <div class="empty-state">
                            <i class="fas fa-check-circle"></i>
                            <h4>No Active Alerts</h4>
                            <p>Great news! There are currently no active road hazard alerts in your area.</p>
                        </div>
                    <?php else: ?>
                        <div class="row">
                            <?php foreach ($activeAlerts as $alert): ?>
                                <div class="col-lg-6 col-xl-4">
                                    <div class="alert-card <?php echo strtolower($alert['severity']); ?>" data-alert-id="<?php echo $alert['id']; ?>">
                                        <?php if (isset($alert['urgency_score']) && $alert['urgency_score'] > 5): ?>
                                            <div class="urgency-badge">
                                                Urgency: <?php echo $alert['urgency_score']; ?>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <div class="alert-header">
                                            <div class="alert-title"><?php echo htmlspecialchars($alert['title']); ?></div>
                                            <span class="severity-badge severity-<?php echo strtolower($alert['severity']); ?>">
                                                <?php echo ucfirst($alert['severity']); ?>
                                            </span>
                                        </div>
                                        
                                        <div class="alert-body">
                                            <div class="alert-meta">
                                                <div><i class="fas fa-map-marker-alt"></i><?php echo htmlspecialchars($alert['address']); ?></div>
                                                <div><i class="fas fa-calendar-alt"></i><?php echo date('M d, Y g:i A', strtotime($alert['reported_at'])); ?></div>
                                                <div><i class="fas fa-user"></i><?php echo htmlspecialchars($alert['reporter_name']); ?></div>
                                            </div>
                                            
                                            <p class="mb-0"><?php echo htmlspecialchars($alert['description']); ?></p>
                                            
                                            <?php if ($alert['approved_by_name']): ?>
                                                <div class="engineer-info">
                                                    <h6><i class="fas fa-check-circle me-1"></i>Approved by Engineer</h6>
                                                    <div class="d-flex align-items-center">
                                                        <div class="engineer-avatar">
                                                            <?php echo strtoupper(substr($alert['approved_by_name'], 0, 2)); ?>
                                                        </div>
                                                        <div>
                                                            <strong><?php echo htmlspecialchars($alert['approved_by_name']); ?></strong>
                                                            <br><small class="text-muted">Approved on <?php echo date('M d, Y', strtotime($alert['approved_at'])); ?></small>
                                                        </div>
                                                    </div>
                                                </div>
                                            <?php endif; ?>
                                            
                                            <!-- Mini Map -->
                                            <div class="alert-map" id="map-<?php echo $alert['id']; ?>" data-lat="<?php echo $alert['location'][0]; ?>" data-lng="<?php echo $alert['location'][1]; ?>"></div>
                                        </div>
                                        
                                        <div class="alert-footer">
                                            <span class="distance-badge">
                                                <i class="fas fa-location-arrow me-1"></i> <?php echo number_format($alert['distance'], 1); ?> km away
                                            </span>
                                            <div class="alert-actions">
                                                <button class="btn btn-primary btn-sm view-on-map-btn" 
                                                        data-lat="<?php echo $alert['location'][0]; ?>" 
                                                        data-lng="<?php echo $alert['location'][1]; ?>" 
                                                        data-id="<?php echo $alert['id']; ?>">
                                                    <i class="fas fa-map me-1"></i> View on Map
                                                </button>
                                                <?php if ($alert['image_path']): ?>
                                                    <button class="btn btn-outline-info btn-sm ms-1" onclick="viewPhoto('<?php echo $alert['image_path']; ?>')">
                                                        <i class="fas fa-camera me-1"></i> Photo
                                                    </button>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- Resolved Alerts Tab -->
                <div class="tab-pane fade" id="resolved-alerts" role="tabpanel">
                    <?php if (empty($resolvedAlerts)): ?>
                        <div class="empty-state">
                            <i class="fas fa-tools"></i>
                            <h4>No Resolved Alerts</h4>
                            <p>No hazards have been resolved yet. Check back later for updates.</p>
                        </div>
                    <?php else: ?>
                        <div class="row">
                            <?php foreach ($resolvedAlerts as $alert): ?>
                                <div class="col-lg-6 col-xl-4">
                                    <div class="alert-card resolved" data-alert-id="<?php echo $alert['id']; ?>">
                                        <div class="alert-header">
                                            <div class="alert-title">
                                                <i class="fas fa-check-circle text-success me-2"></i>
                                                <?php echo htmlspecialchars($alert['title']); ?>
                                            </div>
                                            <span class="severity-badge severity-<?php echo strtolower($alert['severity']); ?>">
                                                <?php echo ucfirst($alert['severity']); ?>
                                            </span>
                                        </div>
                                        
                                        <div class="alert-body">
                                            <div class="alert-meta">
                                                <div><i class="fas fa-map-marker-alt"></i><?php echo htmlspecialchars($alert['address']); ?></div>
                                                <div><i class="fas fa-calendar-alt"></i><?php echo date('M d, Y g:i A', strtotime($alert['reported_at'])); ?></div>
                                                <div><i class="fas fa-check-double text-success"></i>Resolved on <?php echo date('M d, Y g:i A', strtotime($alert['resolved_at'])); ?></div>
                                                <div><i class="fas fa-clock"></i>Resolution time: <?php echo $alert['resolution_time']; ?></div>
                                            </div>
                                            
                                            <p class="mb-2"><?php echo htmlspecialchars($alert['description']); ?></p>
                                            
                                            <!-- Engineer Resolution Info -->
                                            <?php if ($alert['resolved_by_name']): ?>
                                                <div class="engineer-info">
                                                    <h6><i class="fas fa-tools me-1"></i>Resolved by Engineer</h6>
                                                    <div class="d-flex align-items-center mb-2">
                                                        <div class="engineer-avatar">
                                                            <?php echo strtoupper(substr($alert['resolved_by_name'], 0, 2)); ?>
                                                        </div>
                                                        <div>
                                                            <strong><?php echo htmlspecialchars($alert['resolved_by_name']); ?></strong>
                                                            <br><small class="text-muted">
                                                                <?php echo htmlspecialchars($alert['resolved_by_specialization'] ?: 'General Engineer'); ?>
                                                                <?php if ($alert['resolved_by_email']): ?>
                                                                    • <?php echo htmlspecialchars($alert['resolved_by_email']); ?>
                                                                <?php endif; ?>
                                                            </small>
                                                        </div>
                                                    </div>
                                                    <?php if ($alert['resolution_notes']): ?>
                                                        <div class="alert alert-light">
                                                            <strong>Resolution Notes:</strong><br>
                                                            <?php echo htmlspecialchars($alert['resolution_notes']); ?>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                            <?php endif; ?>
                                            
                                            <!-- Mini Map -->
                                            <div class="alert-map" id="map-resolved-<?php echo $alert['id']; ?>" data-lat="<?php echo $alert['location'][0]; ?>" data-lng="<?php echo $alert['location'][1]; ?>"></div>
                                        </div>
                                        
                                        <div class="alert-footer">
                                            <span class="distance-badge">
                                                <i class="fas fa-location-arrow me-1"></i> <?php echo number_format($alert['distance'], 1); ?> km away
                                            </span>
                                            <div class="alert-actions">
                                                <button class="btn btn-success btn-sm view-on-map-btn" 
                                                        data-lat="<?php echo $alert['location'][0]; ?>" 
                                                        data-lng="<?php echo $alert['location'][1]; ?>" 
                                                        data-id="<?php echo $alert['id']; ?>">
                                                    <i class="fas fa-map me-1"></i> View on Map
                                                </button>
                                                <?php if ($alert['image_path']): ?>
                                                    <button class="btn btn-outline-info btn-sm ms-1" onclick="viewPhoto('<?php echo $alert['image_path']; ?>')">
                                                        <i class="fas fa-camera me-1"></i> Photo
                                                    </button>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- All Alerts Tab -->
                <div class="tab-pane fade" id="all-alerts" role="tabpanel">
                    <div class="card">
                        <div class="card-header">
                            <h5><i class="fas fa-table me-2"></i>All Alerts Data Table</h5>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table id="alertsTable" class="table table-striped table-hover">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Type</th>
                                            <th>Severity</th>
                                            <th>Location</th>
                                            <th>Status</th>
                                            <th>Reported</th>
                                            <th>Engineer</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($allAlerts as $alert): ?>
                                        <tr>
                                            <td>#<?php echo $alert['id']; ?></td>
                                            <td><?php echo ucwords(str_replace('_', ' ', $alert['hazard_type'])); ?></td>
                                            <td>
                                                <span class="severity-badge severity-<?php echo strtolower($alert['severity']); ?>">
                                                    <?php echo ucfirst($alert['severity']); ?>
                                                </span>
                                            </td>
                                            <td><?php echo htmlspecialchars(substr($alert['address'], 0, 40)) . '...'; ?></td>
                                            <td>
                                                <?php if ($alert['resolved']): ?>
                                                    <span class="badge bg-success">
                                                        <i class="fas fa-check-circle me-1"></i>Resolved
                                                    </span>
                                                <?php else: ?>
                                                    <span class="badge bg-warning">
                                                        <i class="fas fa-exclamation-triangle me-1"></i>Active
                                                    </span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo date('M d, Y g:i A', strtotime($alert['reported_at'])); ?></td>
                                            <td>
                                                <?php if ($alert['resolved'] && isset($alert['resolved_by_name'])): ?>
                                                    <div class="d-flex align-items-center">
                                                        <div class="engineer-avatar me-2" style="width: 24px; height: 24px; font-size: 0.7rem;">
                                                            <?php echo strtoupper(substr($alert['resolved_by_name'], 0, 2)); ?>
                                                        </div>
                                                        <div>
                                                            <small><strong><?php echo htmlspecialchars($alert['resolved_by_name']); ?></strong></small>
                                                            <br><small class="text-muted"><?php echo htmlspecialchars($alert['resolved_by_specialization'] ?: 'General'); ?></small>
                                                        </div>
                                                    </div>
                                                <?php elseif ($alert['approved_by_name']): ?>
                                                    <small class="text-muted">
                                                        Approved by<br>
                                                        <strong><?php echo htmlspecialchars($alert['approved_by_name']); ?></strong>
                                                    </small>
                                                <?php else: ?>
                                                    <small class="text-muted">Pending assignment</small>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <div class="btn-group btn-group-sm">
                                                    <button class="btn btn-outline-primary view-on-map-btn" 
                                                            data-lat="<?php echo $alert['location'][0]; ?>" 
                                                            data-lng="<?php echo $alert['location'][1]; ?>" 
                                                            data-id="<?php echo $alert['id']; ?>"
                                                            title="View on Map">
                                                        <i class="fas fa-map"></i>
                                                    </button>
                                                    <?php if ($alert['image_path']): ?>
                                                        <button class="btn btn-outline-info" onclick="viewPhoto('<?php echo $alert['image_path']; ?>')" title="View Photo">
                                                            <i class="fas fa-camera"></i>
                                                        </button>
                                                    <?php endif; ?>
                                                    <button class="btn btn-outline-secondary" onclick="viewDetails(<?php echo $alert['id']; ?>)" title="View Details">
                                                        <i class="fas fa-info-circle"></i>
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

    <!-- Alert Details Modal -->
    <div class="modal fade" id="alertDetailsModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Alert Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="alertDetailsBody">
                    <!-- Content will be loaded here -->
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
            <p>© 2025 RoadSense - Smart Road Hazard Mapping System</p>
        </div>
    </footer>

    <!-- Bootstrap 5 & Popper JS -->
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.6/dist/umd/popper.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.min.js"></script>
    
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.4.min.js"></script>
    
    <!-- Leaflet JS -->
    <script src="https://unpkg.com/leaflet@1.9.3/dist/leaflet.js"></script>
    
    <!-- DataTables -->
    <script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"></script>
    
    <!-- Alerts Script -->
    <script>
        // Global variables
        let alertsData = <?php echo json_encode($allAlerts); ?>;
        let activeAlertsData = <?php echo json_encode($activeAlerts); ?>;
        let resolvedAlertsData = <?php echo json_encode($resolvedAlerts); ?>;
        
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize the maps
            initMaps();
            
            // Initialize DataTable
            initDataTable();
            
            // Initialize event listeners
            initEventListeners();
            
            // Auto-refresh every 5 minutes
            setInterval(refreshAlerts, 300000);
        });
        
        // Initialize all mini maps
        function initMaps() {
            // Initialize maps for active alerts
            activeAlertsData.forEach(alert => {
                setTimeout(() => initMap(`map-${alert.id}`, alert.location[0], alert.location[1], alert.severity), 100);
            });
            
            // Initialize maps for resolved alerts
            resolvedAlertsData.forEach(alert => {
                setTimeout(() => initMap(`map-resolved-${alert.id}`, alert.location[0], alert.location[1], alert.severity, true), 100);
            });
        }
        
        // Initialize individual map
        function initMap(mapId, lat, lng, severity, resolved = false) {
            const mapElement = document.getElementById(mapId);
            if (!mapElement || isNaN(lat) || isNaN(lng)) return;
            
            try {
                const map = L.map(mapId).setView([lat, lng], 15);
                
                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
                    maxZoom: 19
                }).addTo(map);
                
                // Create marker with severity-based color
                const color = getSeverityColor(severity, resolved);
                const icon = L.divIcon({
                    className: 'hazard-marker',
                    html: `<div style="background-color: ${color}; width: 20px; height: 20px; border-radius: 50%; border: 3px solid white; box-shadow: 0 0 10px rgba(0, 0, 0, 0.3);"></div>`,
                    iconSize: [20, 20],
                    iconAnchor: [10, 10]
                });
                
                const marker = L.marker([lat, lng], { icon: icon }).addTo(map);
                
                // Disable zoom and pan for mini maps
                map.dragging.disable();
                map.touchZoom.disable();
                map.doubleClickZoom.disable();
                map.scrollWheelZoom.disable();
                map.boxZoom.disable();
                map.keyboard.disable();
                
                // Add click event to open full map
                mapElement.style.cursor = 'pointer';
                mapElement.addEventListener('click', function() {
                    openFullMap(lat, lng);
                });
                
            } catch (error) {
                console.error('Error initializing map:', error);
                mapElement.innerHTML = '<div class="alert alert-warning">Could not load map</div>';
            }
        }
        
        // Get color based on severity
        function getSeverityColor(severity, resolved = false) {
            if (resolved) return '#28A745'; // Green for resolved
            
            switch (severity.toLowerCase()) {
                case 'critical': return '#9b59b6'; // Purple
                case 'high': return '#e74c3c'; // Red
                case 'medium': return '#f39c12'; // Orange
                case 'minor': return '#2ecc71'; // Green
                default: return '#3498db'; // Blue
            }
        }
        
        // Initialize DataTable
        function initDataTable() {
            $('#alertsTable').DataTable({
                responsive: true,
                order: [[5, 'desc']], // Sort by reported date desc
                pageLength: 25,
                language: {
                    search: "Search alerts:",
                    lengthMenu: "Show _MENU_ alerts per page",
                    info: "Showing _START_ to _END_ of _TOTAL_ alerts",
                    infoEmpty: "No alerts available",
                    infoFiltered: "(filtered from _MAX_ total alerts)"
                },
                columnDefs: [
                    { orderable: false, targets: [7] } // Disable sorting on Actions column
                ]
            });
        }
        
        // Initialize event listeners
        function initEventListeners() {
            // View on map buttons
            document.querySelectorAll('.view-on-map-btn').forEach(button => {
                button.addEventListener('click', function() {
                    const lat = parseFloat(this.getAttribute('data-lat'));
                    const lng = parseFloat(this.getAttribute('data-lng'));
                    const id = this.getAttribute('data-id');
                    
                    openFullMap(lat, lng, id);
                });
            });
            
            // Tab change events
            document.querySelectorAll('[data-bs-toggle="tab"]').forEach(tab => {
                tab.addEventListener('shown.bs.tab', function() {
                    // Reinitialize maps when tabs are shown
                    setTimeout(() => {
                        initMaps();
                    }, 100);
                });
            });
        }
        
        // Open full map in traffic hazard map page
        function openFullMap(lat, lng, hazardId = null) {
            let url = `traffic_hazard_map.php?lat=${lat}&lng=${lng}&zoom=16`;
            if (hazardId) {
                url += `&hazard_id=${hazardId}`;
            }
            window.open(url, '_blank');
        }
        
        // View photo in modal
        function viewPhoto(imagePath) {
            document.getElementById('modalPhoto').src = imagePath;
            new bootstrap.Modal(document.getElementById('photoModal')).show();
        }
        
        // View alert details
        function viewDetails(alertId) {
            const alert = alertsData.find(a => a.id == alertId);
            if (!alert) {
                showNotification('Error', 'Alert not found.', 'danger');
                return;
            }
            
            const modalBody = document.getElementById('alertDetailsBody');
            
            modalBody.innerHTML = `
                <div class="row">
                    <div class="col-md-6">
                        <h6>Alert Information</h6>
                        <table class="table table-borderless">
                            <tr><td><strong>Alert ID:</strong></td><td>#${alert.id}</td></tr>
                            <tr><td><strong>Type:</strong></td><td>${alert.hazard_type.replace(/_/g, ' ')}</td></tr>
                            <tr><td><strong>Severity:</strong></td><td><span class="severity-badge severity-${alert.severity.toLowerCase()}">${alert.severity}</span></td></tr>
                            <tr><td><strong>Status:</strong></td><td>${alert.resolved ? '<span class="badge bg-success">Resolved</span>' : '<span class="badge bg-warning">Active</span>'}</td></tr>
                            <tr><td><strong>Reported:</strong></td><td>${alert.reported_at}</td></tr>
                            <tr><td><strong>Reporter:</strong></td><td>${alert.reporter_name}</td></tr>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <h6>Location Information</h6>
                        <table class="table table-borderless">
                            <tr><td><strong>Address:</strong></td><td>${alert.address}</td></tr>
                            <tr><td><strong>Coordinates:</strong></td><td>${alert.location[0]}, ${alert.location[1]}</td></tr>
                            <tr><td><strong>Distance:</strong></td><td>${alert.distance} km away</td></tr>
                        </table>
                    </div>
                </div>
                <div class="row mt-3">
                    <div class="col-12">
                        <h6>Description</h6>
                        <div class="p-3 bg-light rounded">${alert.description}</div>
                    </div>
                </div>
                ${alert.approved_by_name ? `
                <div class="row mt-3">
                    <div class="col-12">
                        <h6>Approval Information</h6>
                        <div class="engineer-info">
                            <div class="d-flex align-items-center">
                                <div class="engineer-avatar me-3">
                                    ${alert.approved_by_name.substring(0, 2).toUpperCase()}
                                </div>
                                <div>
                                    <strong>Approved by: ${alert.approved_by_name}</strong>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                ` : ''}
                ${alert.resolved && alert.resolved_by_name ? `
                <div class="row mt-3">
                    <div class="col-12">
                        <h6>Resolution Information</h6>
                        <div class="engineer-info">
                            <div class="d-flex align-items-center mb-2">
                                <div class="engineer-avatar me-3">
                                    ${alert.resolved_by_name.substring(0, 2).toUpperCase()}
                                </div>
                                <div>
                                    <strong>Resolved by: ${alert.resolved_by_name}</strong>
                                    <br><small class="text-muted">${alert.resolved_by_specialization || 'General Engineer'}</small>
                                </div>
                            </div>
                            <p class="mb-1"><strong>Resolved on:</strong> ${alert.resolved_at}</p>
                            <p class="mb-1"><strong>Resolution time:</strong> ${alert.resolution_time}</p>
                            ${alert.resolution_notes ? `
                            <div class="alert alert-light mt-2">
                                <strong>Resolution Notes:</strong><br>
                                ${alert.resolution_notes}
                            </div>
                            ` : ''}
                        </div>
                    </div>
                </div>
                ` : ''}
                ${alert.image_path ? `
                <div class="row mt-3">
                    <div class="col-12">
                        <h6>Photo</h6>
                        <img src="${alert.image_path}" class="img-fluid rounded" style="max-height: 300px; cursor: pointer;" onclick="viewPhoto('${alert.image_path}')">
                    </div>
                </div>
                ` : ''}
            `;
            
            new bootstrap.Modal(document.getElementById('alertDetailsModal')).show();
        }
        
        // Refresh alerts data
        function refreshAlerts() {
            showLoading();
            
            fetch(window.location.href)
                .then(response => response.text())
                .then(html => {
                    // Parse the response and update counters
                    const parser = new DOMParser();
                    const doc = parser.parseFromString(html, 'text/html');
                    
                    // Update notification badge
                    const newBadge = doc.querySelector('.notification-badge');
                    if (newBadge) {
                        document.querySelector('.notification-badge').textContent = newBadge.textContent;
                    }
                    
                    hideLoading();
                    showNotification('Alerts Updated', 'Alert data has been refreshed.', 'info');
                })
                .catch(error => {
                    console.error('Error refreshing alerts:', error);
                    hideLoading();
                });
        }
        
        // Show/hide loading spinner
        function showLoading() {
            document.getElementById('loadingSpinner').style.display = 'flex';
        }
        
        function hideLoading() {
            document.getElementById('loadingSpinner').style.display = 'none';
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
        
        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            // R key to refresh
            if (e.key === 'r' && !e.ctrlKey && !e.metaKey) {
                e.preventDefault();
                refreshAlerts();
            }
        });
        
        // Initialize tooltips
        document.addEventListener('DOMContentLoaded', function() {
            // Bootstrap tooltips
            var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl);
            });
        });
        
        // Add real-time updates via EventSource (if supported)
        if (typeof(EventSource) !== "undefined") {
            // This would connect to a server-sent events endpoint for real-time updates
            // const eventSource = new EventSource('alerts_updates.php');
            // eventSource.onmessage = function(event) {
            //     const data = JSON.parse(event.data);
            //     handleRealTimeUpdate(data);
            // };
        }
        
        // Handle visibility change to pause/resume updates
        document.addEventListener('visibilitychange', function() {
            if (document.hidden) {
                // Page is hidden, could pause updates
                console.log('Page hidden - pausing updates');
            } else {
                // Page is visible, resume updates
                console.log('Page visible - resuming updates');
                refreshAlerts();
            }
        });
    </script>
</body>
</html>