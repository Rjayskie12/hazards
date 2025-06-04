<?php
// stream_hazard_updates.php

header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('Connection: keep-alive');

// Store the last sent hazard ID
$lastSentHazardId = null;

// Keep the connection open and send updates only for new hazards
while (true) {
    require 'db_connect.php';
    
    // Fetch the latest hazard report from the database
    $result = $conn->query("SELECT * FROM hazard_reports ORDER BY id DESC LIMIT 1");

    if ($result && $result->num_rows > 0) {
        $report = $result->fetch_assoc();

        // Check if the latest hazard is a new one (based on ID)
        if ($lastSentHazardId !== $report['id']) {
            // Prepare the hazard data
            $hazardData = [
                'id' => $report['id'], // Include the hazard ID
                'hazard_type' => $report['hazard_type'],
                'severity' => $report['severity'],
                'latitude' => $report['latitude'],
                'longitude' => $report['longitude'],
                'address' => $report['address'],
                'image_path' => $report['image_path'],
            ];

            // Send the new hazard report as an SSE message
            echo "data: " . json_encode($hazardData) . "\n\n";
            flush(); // Ensure immediate delivery

            // Update the last sent hazard ID to prevent sending the same report again
            $lastSentHazardId = $report['id'];
        }
    }

    // Wait for a short period before checking for new reports again
    sleep(5); // Adjust this as needed
}
?>
