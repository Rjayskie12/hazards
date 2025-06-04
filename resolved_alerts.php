<?php
// resolved_alerts.php

// Example data for "Last 30 Days"
$last30Days = [
  [
    'hazard' => 'Pothole',
    'location' => 'Main Street & 5th Ave',
    'resolvedTime' => 'Resolved 2 days ago',
    'handledBy' => 'City Maintenance'
  ],
  [
    'hazard' => 'Fallen Tree',
    'location' => 'Park Road',
    'resolvedTime' => 'Resolved 5 days ago',
    'handledBy' => 'Emergency Services'
  ],
  [
    'hazard' => 'Road Closure',
    'location' => 'Highway 101 North',
    'resolvedTime' => 'Resolved 1 week ago',
    'handledBy' => 'Highway Department'
  ],
  [
    'hazard' => 'Traffic Light Outage',
    'location' => 'Downtown Intersection',
    'resolvedTime' => 'Resolved 2 weeks ago',
    'handledBy' => 'City Utilities'
  ]
];

// Example data for "Previous Month"
$previousMonth = [
  [
    'hazard' => 'Flooding',
    'location' => 'River Road',
    'resolvedTime' => 'Resolved 1 month ago',
    'handledBy' => 'Public Works'
  ],
  [
    'hazard' => 'Construction',
    'location' => 'Main Highway',
    'resolvedTime' => 'Resolved 1 month ago',
    'handledBy' => 'Construction Company'
  ],
  [
    'hazard' => 'Rockslide on Road',
    'location' => 'Mountain Pass',
    'resolvedTime' => 'Resolved 6 weeks ago',
    'handledBy' => 'Highway Patrol'
  ]
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>RoadSense - Resolved Alerts</title>
  <!-- Link to your stylesheet -->
  <link rel="stylesheet" href="../styles/resolved_alerts.css">
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
    <li><a href="#">Active</a></li>
    <li class="active"><a href="#">Resolved</a></li>
  </ul>
  <!-- Optional Filter button -->
  <button class="filter-btn">Filter</button>
</section>

<!-- MAIN CONTENT: RESOLVED ALERTS -->
<main class="resolved-alerts-page container">
  <h1>Resolved Alerts</h1>
  <p class="page-description">View previously reported hazards that have been resolved</p>

  <!-- Last 30 Days Section -->
  <section class="last-30-days">
    <h2>Last 30 Days</h2>
    <ul>
      <?php foreach ($last30Days as $alert): ?>
      <li>
        <div class="hazard-info">
          <span class="hazard-name"><?php echo $alert['hazard']; ?></span>
          <span class="hazard-location"><?php echo $alert['location']; ?></span>
        </div>
        <div class="resolved-meta">
          <span><?php echo $alert['resolvedTime']; ?></span> - 
          <strong><?php echo $alert['handledBy']; ?></strong>
        </div>
      </li>
      <?php endforeach; ?>
    </ul>
  </section>

  <!-- Previous Month Section -->
  <section class="previous-month">
    <h2>Previous Month</h2>
    <ul>
      <?php foreach ($previousMonth as $alert): ?>
      <li>
        <div class="hazard-info">
          <span class="hazard-name"><?php echo $alert['hazard']; ?></span>
          <span class="hazard-location"><?php echo $alert['location']; ?></span>
        </div>
        <div class="resolved-meta">
          <span><?php echo $alert['resolvedTime']; ?></span> - 
          <strong><?php echo $alert['handledBy']; ?></strong>
        </div>
      </li>
      <?php endforeach; ?>
    </ul>
  </section>

  <button class="load-more">Load More</button>
</main>

<!-- Optional JS -->
<script src="../scripts/resolved_alerts.js"></script>
</body>
</html>
