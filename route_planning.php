<?php
// route_planning.php


?>






</style>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>RoadSense - Route Planning</title>

    

    <!-- Leaflet CSS & Routing Plugins -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet/dist/leaflet.css" />
    <link rel="stylesheet" href="https://unpkg.com/leaflet-routing-machine/dist/leaflet-routing-machine.css" />

    <!-- Leaflet JS & Routing -->
    <script src="https://unpkg.com/leaflet@1.7.1/dist/leaflet.js"></script>
    <script src="https://unpkg.com/leaflet-routing-machine@3.2.12/dist/leaflet-routing-machine.js"></script>
    <link rel="stylesheet" href="../styles/route_planning.css">

    <style>
          /* Importing Orbitron font for a tech-inspired look */
@import url('https://fonts.googleapis.com/css2?family=Orbitron:wght@400;700&display=swap');

/* Root variables for consistent theming */
:root {
    --color-primary: #ff3b3b; /* Hazard red */
    --color-secondary: #ffae42; /* Warning amber */
    --color-background: #121212; /* Dark background */
    --color-text: #ffffff; /* White text */
    --color-panel: #1e1e1e; /* Dark panel */
    --color-border: #ff3b3b; /* Red border */
    --color-hover: #ff5252; /* Lighter red for hover effects */
    --color-active: #ff6b6b; /* Even lighter red for active states */
}

/* Resetting default margin and padding */
* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

/* Body styling */
body {
    font-family: 'Orbitron', sans-serif;
    background-color: var(--color-background);
    color: var(--color-text);
    line-height: 1.6;
}

/* Header navigation styling */
.top-nav {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1rem 2rem;
    background-color: var(--color-panel);
    border-bottom: 3px solid var(--color-border);
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.5);
}

.logo {
    font-size: 1.8rem;
    font-weight: 700;
    color: var(--color-primary);
    text-shadow: 0 0 10px var(--color-primary);
}

nav ul {
    list-style: none;
    display: flex;
}

nav ul li {
    margin-left: 1.5rem;
}

nav ul li a {
    text-decoration: none;
    color: var(--color-text);
    font-weight: 400;
    transition: color 0.3s ease;
}

nav ul li a:hover {
    color: var(--color-primary);
}

.nav-actions {
    display: flex;
    align-items: center;
}

.nav-actions .btn-primary {
    background-color: var(--color-primary);
    color: var(--color-text);
    padding: 0.5rem 1rem;
    border: none;
    border-radius: 5px;
    margin-left: 1rem;
    cursor: pointer;
    transition: background-color 0.3s ease;
}

.nav-actions .btn-primary:hover {
    background-color: var(--color-hover);
}

/* Navigation tabs styling */
.nav-tabs {
    background-color: var(--color-panel);
    padding: 0.5rem 2rem;
    border-bottom: 3px solid var(--color-border);
}

.nav-tabs ul {
    list-style: none;
    display: flex;
}

.nav-tabs ul li {
    margin-right: 1.5rem;
}

.nav-tabs ul li a {
    text-decoration: none;
    color: var(--color-text);
    padding: 0.5rem 1rem;
    display: block;
    border-radius: 5px 5px 0 0;
    transition: background-color 0.3s ease, color 0.3s ease;
}

.nav-tabs ul li a:hover,
.nav-tabs ul li.active a {
    background-color: var(--color-primary);
    color: var(--color-text);
}

        

        .map-filter-wrapper {
            display: flex;
            gap: 20px;
            margin-bottom: 20px;
        }

        .map-container {
            flex: 3;
            height: 600px;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            position: relative;
        }

        #map {
            width: 100%;
            height: 100%;
        }
             
/* Main container styling */
.container {
    display: flex;
    flex-direction: column;
    padding: 2rem;
}
/* Map and filter wrapper */
.map-filter-wrapper {
    display: flex;
    flex-wrap: wrap;
    gap: 2rem;
}

/* Map container styling */
.map-container {
    flex: 2;
    min-height: 500px;
    background-color: var(--color-panel);
    border: 1px solid var(--color-border);
    border-radius: 10px;
    overflow: hidden;
    position: relative;
    box-shadow: 0 0 15px rgba(255, 59, 59, 0.5);
}





/* Filter container styling */
.filter-container {
    flex: 1;
    background-color: var(--color-panel);
    padding: 1.5rem;
    border-radius: 10px;
    border: 1px solid var(--color-border);
    box-shadow: 0 0 15px rgba(255, 59, 59, 0.5);
    
}

/* Title for filter container */
.filter-container h3 {
    margin-bottom: 1rem;
    color: var(--color-primary);
}

/* Label styling for inputs */
.filter-container label {
    display: block;
    margin: 0.5rem 0;
    font-weight: 700;
}

/* Input and button styles */
.filter-container input[type="text"],
.filter-container input[type="date"] {
    width: 100%;
    height:45px;
    padding: 0.5rem;
    border-radius: 5px;
    border: 1px solid var(--color-border);
    background-color: var(--color-background);
    color: var(--color-text);
}
/* Suggestions Dropdown */
.suggestions {
    position: absolute;
    width: 100%;
    background: var(--color-background);
    border: 1px solid var(--color-border);
    border-radius: 5px;
    max-height: 200px;
    overflow-y: auto;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.2);
    z-index: 1000;
    display: none;
    font-family: 'Roboto', sans-serif;  /* Unique font */
    font-size: 16px;  /* Set a readable font size */
    letter-spacing: 1px;  /* Slightly increase letter spacing */
}

/* Individual suggestion items */
.suggestions div {
    padding: 10px;
    cursor: pointer;
    transition: background-color 0.3s ease-in-out;
    font-weight: 500;  /* Medium weight for readability */
    color: var(--color-text);  /* Default text color */
}

/* Hover effect for suggestion items */
.suggestions div:hover {
    background: var(--color-primary);
    color: white;
    border-radius: 5px;  /* Optional: Add border-radius for smoother hover transition */
}

/* Route Preferences */
.route-preferences {
    display: flex;
    gap: 15px;
    justify-content: center;
    margin: 1rem 0;
}

.preference-box {
    padding: 15px;
    background-color: var(--color-background);
    border-radius: 8px;
    cursor: pointer;
    transition: background-color 0.3s ease;
}

.preference-box:hover {
    background-color: var(--color-primary);
    color: white;
}

/* ETA Box */
.eta-box {
    padding: 15px;
    background-color: var(--color-background);
    border-radius: 8px;
    display: flex;
    justify-content: center;
    font-size: 20px;
    font-weight: bold;
}

.eta-box #eta {
    color: var(--color-primary);
}

/* Button styling */
.btn-findroute {
    width: 100%;
    padding: 0.75rem;
    margin-top: 1rem;
    background-color: var(--color-primary);
    color: var(--color-text);
    border: none;
    border-radius: 5px;
    cursor: pointer;
    font-weight: 700;
    transition: background-color 0.3s ease;
}

.btn-findroute:hover {
    background-color: var(--color-hover);
}


        .suggestions {
    position: absolute;
    background: black;
    color: white; /* Ensure text is readable */
    border: 1px solid #ccc;
    max-height: 150px;
    overflow-y: auto;
    width: 50%;
    display: none;
    z-index: 1000;

    top: 40%; /* Position it right below the input field */
    left: 50;
    margin-top: 5px; /* Adds small gap between input and suggestions */
    border-radius: 5px; /* Rounded corners for better UI */
}

.suggestion-item {
    padding: 10px;
    cursor: pointer;
    border-bottom: 1px solid #555; /* Adjust contrast */
}

.suggestion-item:hover {
    background: #333; /* Darker hover effect */
}

.your-text-class {
  color: black !important;
}

/* Route Preferences */
.route-preferences {
    display: flex;
    justify-content: space-between;
    margin-bottom: 15px;
}

.preference-box {
    padding: 10px 20px;
    background: #eee;
    border-radius: 5px;
    cursor: pointer;
    font-size: 16px;
    text-align: center;
    transition: background 0.3s;
}

.preference-box:hover {
    background: #ccc;
}

/* Active Route Preference */
.preference-box.active {
    background: #007bff;
    color: white;
}
/* Layout Styling */
.layout {
    display: flex;
    gap: 20px;
    align-items: flex-start;
}



/* Recent Routes Section */
.recent-routes-section {
    background-color: var(--color-panel);
    padding: 1.5rem;
    border-radius: 10px;
    border: 1px solid var(--color-border);
    box-shadow: 0 0 15px rgba(255, 59, 59, 0.5);
    margin-top: 1.5rem;
    font-family: 'Poppins', sans-serif; /* Unique font */
}

.recent-routes-section h4 {
    margin-bottom: 1rem;
    color: var(--color-primary);
    font-size: 1.25rem;
    font-weight: 600; /* Bold weight for the title */
}

.recent-routes-section ul {
    list-style-type: none;
    padding: 0;
    margin-bottom: 1rem;
}

.recent-routes-section ul li {
    padding: 12px;
    background-color: var(--color-background);
    margin-bottom: 10px;
    border-radius: 8px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
    transition: background-color 0.3s ease;
    font-family: 'Poppins', sans-serif;
}

.recent-routes-section ul li:hover {
    background-color: var(--color-hover);
}

/* Updated Button Style */
.recent-routes-section button {
    width: auto;
    padding: 0.5rem 1.25rem; /* Smaller padding */
    margin-top: 0.75rem;
    background-color: var(--color-secondary);
    color: var(--color-text);
    border: none;
    border-radius: 30px; /* Slightly rounded button */
    cursor: pointer;
    font-weight: 600; /* Bold font weight */
    font-family: 'Poppins', sans-serif;
    letter-spacing: 0.5px; /* Slight letter spacing */
    transition: background-color 0.3s ease, transform 0.2s ease;
    text-align: center;
    font-size: 1rem; /* Smaller font size */
}

.recent-routes-section button:hover {
    background-color: var(--color-hover);
    transform: translateY(-2px); /* Slight lift on hover */
}

/* Secondary button styling */
.recent-routes-section .btn-secondary {
    width: auto;
    margin-right: 1rem;
    padding: 0.5rem 1.25rem; /* Smaller padding */
    display: inline-block;
    border-radius: 30px; /* Rounded edges */
    box-shadow: 0px 4px 6px rgba(0, 0, 0, 0.1); /* Soft shadow */
    font-size: 1rem; /* Adjust font size */
    text-align: center;
}

.recent-routes-section .btn-secondary:last-child {
    margin-right: 0;
}

.recent-routes-section .btn-secondary:active {
    transform: translateY(2px); /* Button depression effect on click */
}










/* ETA Box */
.eta-box {
    background: #f8f9fa; /* Light background */
    padding: 10px;
    border: 2px solid #007bff; /* Blue border */
    border-radius: 5px;
    font-size: 1.2rem;
    font-weight: bold;
    color: #333;
    text-align: center;
    margin-top: 10px;
    width: 100%; /* Full width */
}

.btn-findroute {
    display: inline-block;
    width:100%;
    text-align:center;
    padding: 12px 20px;
    background: #ff3b3b; /* Primary Blue */
    color: white;
    font-size: 16px;
    font-weight: bold;
    text-transform: uppercase;
    text-decoration: none;
    border-radius: 6px;
    transition: background 0.3s ease, transform 0.2s ease;
    box-shadow: 0 0 15px rgba(255, 59, 59, 0.5);
}

.btn-findroute:hover {
    background: #ff3b3b; /* Darker Blue */
    transform: translateY(-2px);
    box-shadow: 0 5px 10px rgba(138, 6, 6, 0.4);
}

.btn-findroute:active {
    transform: translateY(1px);
    box-shadow: 0 2px 4px rgba(169, 5, 5, 0.3);
}


    </style>
</head>
<body>

<header class="top-nav">
  <div class="logo">RoadSense</div>
  <nav>
    <ul>
      <li><a href="map_navigation.php">Map</a></li>
      <li><a href="my_reports.php">My Reports</a></li>
      <li><a href="community.php">Community</a></li>
      <li><a href="alerts.php">Alerts</a></li>
    </ul>
  </nav>
  <div class="nav-actions">
    
    <a href="report_hazard.php" class="btn-primary">Report Hazard</a>
  </div>
</header>

<section class="nav-tabs">
  <ul>
    <li class="active"><a href="route_planning.php">Route Planning</a></li>
    <li><a href="map_navigation.php">Live Navigation</a></li>
    <li><a href="traffic_hazard_map.php">Hazards</a></li>
  </ul>
</section>

<div class="container">
    <div class="layout">
        <!-- Map Container -->
        <div class="map-container">
            <div id="map"></div>
        </div>

        <!-- Filter Section -->
<div class="filter-container">
    <div class="input-row">
        <input type="text" id="startingPoint" placeholder="Enter Starting Point" onkeyup="fetchLocationSuggestions('startingPoint', 'startSuggestions')">
        <button onclick="getCurrentLocation('startingPoint')">📍</button>
        <div id="startSuggestions" class="suggestions"></div>
    </div>

    <div class="input-row">
        <input type="text" id="destination" placeholder="Enter Destination" onkeyup="fetchLocationSuggestions('destination', 'destSuggestions')">
        <button onclick="getCurrentLocation('destination')">📍</button>
        <div id="destSuggestions" class="suggestions"></div>
    </div>

    <!-- Route Preferences -->
    <h4>Choose Route Type:</h4>
    <div class="route-preferences">
        <div class="preference-box" onclick="setRoutePreference('car')">🚗 Car</div>
        <div class="preference-box" onclick="setRoutePreference('bike')">🚲 Bike</div>
        <div class="preference-box" onclick="setRoutePreference('foot')">🚶 Walk</div>
    </div>

    <h4>ETA:</h4>
    <div class="eta-box">
        <span id="eta">--</span>
    </div>

    <br>
    <a href="#" class="btn-findroute">Find Route</a>
</div>

    </div>

    <div class="recent-routes-section">
    <h4>Recent Routes:</h4>
    <ul id="recentRoutes"></ul>
    <button class="btn-secondary" onclick="saveCurrentRoute()">Save Route</button>
    <button class="btn-secondary" onclick="clearRecentRoutes()">Clear All Routes</button>
</div>




    
</div>



<script>
    const tomtomApiKey = "QsG5BtRmhcKECyn9fcSPaDojqAyH440R"; // Replace with your TomTom API Key

// Initialize Map
const map = L.map("map").setView([11.5764, 125.499], 13); // Borongan City Default View

// Map Tile Layer
L.tileLayer(
  "https://{s}.tile.jawg.io/jawg-dark/{z}/{x}/{y}{r}.png?access-token=vhOq0nEYSHnKm05CuCQZ7ZoxC1iKtiep270GdaA1mRAmmKMGRKWfJgiaPQyExW7O",
  {
    attribution: '&copy; <a href="https://www.jawg.io/">Jawg Maps</a> contributors',
  }
).addTo(map);

let control; // Store route control
let routePreference = "car"; // Default route preference
let markers = { startingPoint: null, destination: null };

// Function to Set Route Preference
function setRoutePreference(preference) {
    routePreference = preference;

    // Remove active class from all buttons
    document.querySelectorAll(".preference-box").forEach((box) => {
        box.classList.remove("active");
    });

    // Add active class to clicked button
    event.target.classList.add("active");
}

function findRoute() {
    let startLat = markers["startingPoint"] ? markers["startingPoint"].getLatLng().lat : null;
    let startLon = markers["startingPoint"] ? markers["startingPoint"].getLatLng().lng : null;
    let endLat = markers["destination"] ? markers["destination"].getLatLng().lat : null;
    let endLon = markers["destination"] ? markers["destination"].getLatLng().lng : null;

    if (!startLat || !startLon || !endLat || !endLon) {
        return alert("Please enter both starting point and destination.");
    }

    let travelModeMap = {
        "car": "car",
        "bike": "bicycle",
        "foot": "pedestrian"
    };
    let travelMode = travelModeMap[routePreference];

    let routeUrl = `https://api.tomtom.com/routing/1/calculateRoute/${startLat},${startLon}:${endLat},${endLon}/json?key=${tomtomApiKey}&travelMode=${travelMode}&traffic=true`;

    fetch(routeUrl)
        .then(response => response.json())
        .then(data => {
            if (!data.routes || data.routes.length === 0) return alert("No route found.");

            let route = data.routes[0];
            let eta = Math.ceil(route.summary.travelTimeInSeconds / 60);
            let routeCoordinates = route.legs[0].points.map(point => [point.latitude, point.longitude]);

            document.getElementById("eta").innerText = `${eta} min`;

            // Remove existing route
            if (control) map.removeControl(control);

            // Add new route
            control = L.Routing.control({
                waypoints: [L.latLng(startLat, startLon), L.latLng(endLat, endLon)],
                createMarker: () => null,
                lineOptions: {
                    styles: [{ color: "#00A8FF", weight: 5 }]
                }
            }).addTo(map);

            // Save to recent routes with date & time
            saveRecentRoute(startLat, startLon, endLat, endLon);
        })
        .catch(() => alert("Failed to get route."));
}

function saveRecentRoute(startLat, startLon, endLat, endLon) {
    let startText = document.getElementById("startingPoint").value;
    let endText = document.getElementById("destination").value;

    if (!startText || !endText) {
        alert("Please enter both start and destination points.");
        return;
    }

    let currentTime = new Date();
    let formattedTime = currentTime.toLocaleString();

    let newRoute = {
        start: startText,
        end: endText,
        startLat,
        startLon,
        endLat,
        endLon,
        routeType: routePreference,
        time: formattedTime
    };

    let recentRoutes = JSON.parse(localStorage.getItem("recentRoutes")) || [];

    // Add new route
    recentRoutes.unshift(newRoute);
    recentRoutes = recentRoutes.slice(0, 5); // Keep last 5 routes

    localStorage.setItem("recentRoutes", JSON.stringify(recentRoutes));

    displayRecentRoutes();
}

// **Save route only when the user clicks 'Save Route'**
function saveCurrentRoute() {
    let startText = document.getElementById("startingPoint").value;
    let endText = document.getElementById("destination").value;

    if (!startText || !endText) {
        alert("Please enter both start and destination points.");
        return;
    }

    let startLat = 0; // Replace with actual coordinates
    let startLon = 0;
    let endLat = 0;
    let endLon = 0;

    saveRecentRoute(startLat, startLon, endLat, endLon);
}

// **Display recent routes with delete buttons**
function displayRecentRoutes() {
    let recentRoutes = JSON.parse(localStorage.getItem("recentRoutes")) || [];
    let list = document.getElementById("recentRoutes");
    list.innerHTML = "";

    recentRoutes.forEach((route, index) => {
        let li = document.createElement("li");
        li.innerHTML = `
            <span onclick="loadRecentRoute(${index})">
                <strong>${route.start} ➝ ${route.end}</strong> <br>
                <small>${route.routeType.toUpperCase()} - ${route.time}</small>
            </span>
            <button class="delete-btn" onclick="deleteRoute(${index})">❌</button>
        `;
        list.appendChild(li);
    });
}

// **Load a recent route and set markers on the map**
function loadRecentRoute(index) {
    let recentRoutes = JSON.parse(localStorage.getItem("recentRoutes")) || [];
    let route = recentRoutes[index];

    if (!route) return;

    document.getElementById("startingPoint").value = route.start;
    document.getElementById("destination").value = route.end;

    if (markers["startingPoint"]) map.removeLayer(markers["startingPoint"]);
    if (markers["destination"]) map.removeLayer(markers["destination"]);

    markers["startingPoint"] = L.marker([route.startLat, route.startLon]).addTo(map);
    markers["destination"] = L.marker([route.endLat, route.endLon]).addTo(map);

    findRoute();
}

// **Delete a specific route**
function deleteRoute(index) {
    let recentRoutes = JSON.parse(localStorage.getItem("recentRoutes")) || [];
    recentRoutes.splice(index, 1);
    localStorage.setItem("recentRoutes", JSON.stringify(recentRoutes));
    displayRecentRoutes();
}

// **Clear all saved routes**
function clearRecentRoutes() {
    if (confirm("Are you sure you want to clear all saved routes?")) {
        localStorage.removeItem("recentRoutes");
        displayRecentRoutes();
    }
}

// **Load routes on page start**
document.addEventListener("DOMContentLoaded", displayRecentRoutes);



// Update Marker on Map
function updateMarker(inputFieldId, lat, lon, name) {
  if (markers[inputFieldId]) {
    map.removeLayer(markers[inputFieldId]);
  }
  markers[inputFieldId] = L.marker([lat, lon]).addTo(map).bindPopup(`<b>${name}</b>`).openPopup();
}

// Fetch Location Suggestions
function fetchLocationSuggestions(inputId, suggestionsId) {
  let query = document.getElementById(inputId).value;
  let suggestionsBox = document.getElementById(suggestionsId);
  if (query.length < 2) return (suggestionsBox.style.display = "none");

  fetch(`https://api.tomtom.com/search/2/search/${query}.json?key=${tomtomApiKey}&limit=5`)
    .then((response) => response.json())
    .then((data) => {
      suggestionsBox.innerHTML = "";
      if (data.results.length === 0) return (suggestionsBox.style.display = "none");

      data.results.forEach((location) => {
        let name = location.address.freeformAddress;
        let lat = location.position.lat;
        let lon = location.position.lon;

        let div = document.createElement("div");
        div.innerText = name;
        div.classList.add("suggestion-item");
        div.onclick = function () {
          document.getElementById(inputId).value = name;
          suggestionsBox.style.display = "none";
          updateMarker(inputId, lat, lon, name);
        };

        suggestionsBox.appendChild(div);
      });

      suggestionsBox.style.display = "block";
    })
    .catch(() => (suggestionsBox.style.display = "none"));
}

// Get Current Location
function getCurrentLocation(inputFieldId) {
  if (!navigator.geolocation) return alert("Geolocation not supported.");
  navigator.geolocation.getCurrentPosition(
    (position) => {
      let lat = position.coords.latitude;
      let lon = position.coords.longitude;

      fetch(`https://api.tomtom.com/search/2/reverseGeocode/${lat},${lon}.json?key=${tomtomApiKey}`)
        .then((response) => response.json())
        .then((data) => {
          let locationName = data.addresses[0].address.freeformAddress;
          document.getElementById(inputFieldId).value = locationName;
          updateMarker(inputFieldId, lat, lon, locationName);
        })
        .catch(() => alert("Failed to fetch location name."));
    },
    () => alert("Location access denied.")
  );
}



function updateMarker(inputFieldId, lat, lon, name) {
    if (markers[inputFieldId]) {
        map.removeLayer(markers[inputFieldId]);
    }
    markers[inputFieldId] = L.marker([lat, lon]).addTo(map).bindPopup(`<b>${name}</b>`).openPopup();
    map.setView([lat, lon], 15); // Center map to the new location
}


// Event Listener for 'Find Route' Button
document.querySelector(".btn-findroute").addEventListener("click", findRoute);


    // Close suggestions when clicking outside
    document.addEventListener("click", function (event) {
        let startSuggestions = document.getElementById("startSuggestions");
        let destSuggestions = document.getElementById("destSuggestions");
        let startInput = document.getElementById("startingPoint");
        let destInput = document.getElementById("destination");

        if (!startSuggestions.contains(event.target) && event.target !== startInput) {
            startSuggestions.style.display = "none";
        }
        if (!destSuggestions.contains(event.target) && event.target !== destInput) {
            destSuggestions.style.display = "none";
        }
    });
</script>


</body>
</html>

