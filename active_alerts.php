<?php
// active_alerts.php

// Example data (could be fetched from a database)
$activeAlerts = [
  [
    'alert_name' => 'Major Flooding',
    'location'   => 'Main Street Bridge',
    'severity'   => 'High',
    'time_ago'   => '10 min ago'
  ],
  [
    'alert_name' => 'Road Closure',
    'location'   => 'Highway 101 North',
    'severity'   => 'High',
    'time_ago'   => '25 min ago'
  ],
  [
    'alert_name' => 'Traffic Accident',
    'location'   => 'Oak Avenue & 5th St',
    'severity'   => 'Medium',
    'time_ago'   => '30 min ago'
  ],
  [
    'alert_name' => 'Construction',
    'location'   => 'Pine Street',
    'severity'   => 'Medium',
    'time_ago'   => '1 hour ago'
  ],
  [
    'alert_name' => 'Fallen Tree',
    'location'   => 'Maple Road',
    'severity'   => 'Medium',
    'time_ago'   => '1.5 hours ago'
  ]
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>RoadSense - Active Alerts</title>
  <!-- Link to your stylesheet -->
  <link rel="stylesheet" href="../styles/active_alerts.css">
</head>
<body>

<!-- TOP NAVIGATION (Row 1) -->
<header class="top-nav">
  <div class="logo">RoadSense</div>
  <nav>
    <ul>
      <li><a href="#">Map</a></li>
      <li><a href="#">My Reports</a></li>
      <li><a href="#">Community</a></li>
      <li><a href="#" class="active">Alerts</a></li>
    </ul>
  </nav>
  <div class="nav-actions">
    <input type="text" placeholder="Search..." class="search-bar">
    <a href="#" class="btn-secondary">Find Route</a>
    <a href="#" class="btn-primary">Report Hazard</a>
  </div>
</header>

<!-- NAV TABS (Row 2) -->
<section class="nav-tabs">
  <ul>
    <li><a href="#">All Alerts</a></li>
    <li class="active"><a href="#">Active</a></li>
    <li><a href="#">Resolved</a></li>
  </ul>
</section>

<!-- MAIN CONTENT: ACTIVE ALERTS -->
<main class="active-alerts-page container">
  <h1>Alerts Dashboard</h1>
  
  <!-- Alert Filters -->
  <div class="alert-filters">
    <h2>Alert Filters</h2>
    <div class="filter-row">
      <div class="filter-group">
        <label for="searchAlerts">Search Alerts</label>
        <input type="text" id="searchAlerts" placeholder="Search by location or type">
      </div>
      <div class="filter-group">
        <label for="alertType">Alert Type</label>
        <select id="alertType">
          <option value="">Select alert type</option>
          <option value="flood">Flood</option>
          <option value="construction">Construction</option>
          <option value="accident">Accident</option>
          <option value="other">Other</option>
        </select>
      </div>
      <div class="filter-group">
        <label for="severity">Severity</label>
        <select id="severity">
          <option value="">Select severity level</option>
          <option value="minor">Minor</option>
          <option value="medium">Medium</option>
          <option value="high">High</option>
          <option value="critical">Critical</option>
        </select>
      </div>
      <div class="filter-group">
        <label for="timeRange">Time Range</label>
        <select id="timeRange">
          <option value="">Select time range</option>
          <option value="1hr">Last 1 hour</option>
          <option value="6hr">Last 6 hours</option>
          <option value="12hr">Last 12 hours</option>
          <option value="24hr">Last 24 hours</option>
        </select>
      </div>
    </div>
    <div class="filter-buttons">
      <button class="apply-btn">Apply Filters</button>
      <button class="reset-btn">Reset</button>
    </div>
  </div>

  <!-- Active Alerts Table -->
  <section class="active-alerts">
    <h2>Active Alerts</h2>
    <table>
      <thead>
        <tr>
          <th>Alert Name</th>
          <th>Location</th>
          <th>Severity</th>
          <th>Time</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($activeAlerts as $alert): ?>
        <tr>
          <td><?php echo $alert['alert_name']; ?></td>
          <td><?php echo $alert['location']; ?></td>
          <td><?php echo $alert['severity']; ?></td>
          <td><?php echo $alert['time_ago']; ?></td>
          <td>
            <!-- Example icons or links for actions -->
            <a href="#" title="View"><span>👁</span></a>
            <a href="#" title="Edit"><span>✏</span></a>
            <a href="#" title="Delete"><span>🗑</span></a>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </section>
</main>

<!-- Optional JS -->
<script src="../scripts/active_alerts.js"></script>
</body>
</html>
