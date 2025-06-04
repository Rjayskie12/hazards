<?php
// saved_routes.php - Traffic Hazard Mapping
require '../pages/db_connect.php'; // Database connection

// Fetch saved hazardous routes from database
$sql = "SELECT id, route_name, start_location, end_location, hazard_type, severity, latitude, longitude, last_updated 
        FROM hazard_routes ORDER BY last_updated DESC";
$result = $conn->query($sql);

$savedRoutes = [];
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $savedRoutes[] = $row;
    }
}

// Summary Data
$routesSummary = [
    'count' => count($savedRoutes),
    'most_hazardous' => $savedRoutes ? $savedRoutes[0]['hazard_type'] : 'N/A',
    'last_reported' => $savedRoutes ? $savedRoutes[0]['last_updated'] : 'N/A'
];

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>RoadSense - Saved Hazard Routes</title>
  <link rel="stylesheet" href="../styles/saved_routes.css">
  <link rel="stylesheet" href="https://unpkg.com/leaflet/dist/leaflet.css" />
</head>
<body>

<header class="top-nav">
  <div class="logo">RoadSense</div>
  <nav>
    <ul>
      <li><a href="map_navigation.php">Live Map</a></li>
      <li><a href="my_reports.php">My Reports</a></li>
      <li><a href="community.php">Community</a></li>
      <li><a href="alerts.php">Alerts</a></li>
    </ul>
  </nav>
  <div class="nav-actions">
    <input type="text" id="searchBox" placeholder="Search..." class="search-bar">
    <a href="hazard_reporting.php" class="btn-primary">Report Hazard</a>
  </div>
</header>

<section class="nav-tabs">
  <ul>
    <li><a href="live_traffic.php">Live Traffic</a></li>
    <li class="active"><a href="saved_routes.php">Hazard Routes</a></li>
  </ul>
</section>

<main class="saved-routes container">
  <div class="page-header">
    <h2>Saved Hazardous Routes</h2>
    <span class="routes-count">
      <?php echo $routesSummary['count']; ?> hazardous routes recorded
    </span>
  </div>

  <div class="main-content">
    <div class="map-section">
      <h2 class="map-title">Hazard Map</h2>
      <div id="map" style="height: 400px;"></div>
    </div>

    <aside class="summary-card">
      <h3>Hazard Summary</h3>
      <p><strong><?php echo $routesSummary['count']; ?></strong> total hazardous routes</p>
      <p>Most hazardous type: <strong><?php echo $routesSummary['most_hazardous']; ?></strong></p>
      <p>Last reported: <strong><?php echo $routesSummary['last_reported']; ?></strong></p>
      <div class="summary-actions">
        <button onclick="viewHazards()">View Hazards</button>
        <button onclick="filterHazards()">Filter Reports</button>
      </div>
    </aside>
  </div>

  <section class="saved-routes-list">
    <h2>Recent Hazardous Routes</h2>
    <label for="hazardFilter">Filter by Hazard Type:</label>
    <select id="hazardFilter" onchange="filterRoutes()">
      <option value="all">All</option>
      <option value="Pothole">Pothole</option>
      <option value="Flood">Flood</option>
      <option value="Accident">Accident</option>
    </select>

    <ul id="routeList">
      <?php foreach ($savedRoutes as $route): ?>
        <li class="route-item" data-hazard="<?php echo htmlspecialchars($route['hazard_type']); ?>">
          <div class="route-name"><?php echo htmlspecialchars($route['route_name']); ?></div>
          <div class="route-details">
            <?php echo htmlspecialchars($route['start_location']); ?> ➝ <?php echo htmlspecialchars($route['end_location']); ?>
            - <strong><?php echo htmlspecialchars($route['hazard_type']); ?></strong>
            (Severity: <span class="severity"><?php echo htmlspecialchars($route['severity']); ?></span>)
            - Last Updated: <span><?php echo htmlspecialchars($route['last_updated']); ?></span>
          </div>
          <button class="delete-route" onclick="deleteRoute(<?php echo $route['id']; ?>)">Delete</button>
        </li>
      <?php endforeach; ?>
    </ul>
  </section>

</main>

<script src="https://unpkg.com/leaflet/dist/leaflet.js"></script>
<script>
let map = L.map('map').setView([12.8797, 121.7740], 6); // Center on the Philippines

// Add OpenStreetMap tile layer
L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
  attribution: '© OpenStreetMap contributors'
}).addTo(map);

// Load hazard markers
let hazards = <?php echo json_encode($savedRoutes); ?>;

hazards.forEach(hazard => {
  let marker = L.marker([hazard.latitude, hazard.longitude]).addTo(map);
  marker.bindPopup(`<b>${hazard.route_name}</b><br>${hazard.hazard_type}<br>Severity: ${hazard.severity}`);
});

// Delete Route (AJAX)
function deleteRoute(routeId) {
  if (confirm("Are you sure you want to delete this route?")) {
    fetch('../pages/delete_route.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: `id=${routeId}`
    })
    .then(response => response.text())
    .then(result => {
      if (result === "success") {
        alert("Route deleted successfully!");
        location.reload();
      } else {
        alert("Failed to delete route.");
      }
    });
  }
}

// Filter Routes
function filterRoutes() {
  let filter = document.getElementById("hazardFilter").value;
  document.querySelectorAll(".route-item").forEach(item => {
    if (filter === "all" || item.dataset.hazard === filter) {
      item.style.display = "block";
    } else {
      item.style.display = "none";
    }
  });
}
</script>

</body>
</html>
