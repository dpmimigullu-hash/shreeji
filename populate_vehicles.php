<?php
require_once 'includes/config.php';

try {
    $conn = getDBConnection();

    // Read vehicle data from text file
    $vehicleData = file_get_contents('vehicle_data.txt');
    $lines = explode("\n", trim($vehicleData));

    $inserted = 0;
    $skipped = 0;

    // Skip header line
    for ($i = 1; $i < count($lines); $i++) {
        $line = trim($lines[$i]);
        if (!empty($line)) {
            $parts = preg_split('/\t+/', $line);
            if (count($parts) >= 3) {
                $make = trim($parts[0]);
                $model = trim($parts[1]);
                $seatingCapacity = (int)trim($parts[2]);

                // Check if vehicle already exists
                $stmt = $conn->prepare("SELECT id FROM vehicles WHERE make = ? AND model = ?");
                $stmt->execute([$make, $model]);
                $exists = $stmt->fetch();

                if (!$exists) {
                    // Insert new vehicle
                    $stmt = $conn->prepare("INSERT INTO vehicles (make, model, seating_capacity, year, license_plate) VALUES (?, ?, ?, 2020, CONCAT('AUTO', LPAD(LAST_INSERT_ID() + 1, 6, '0')))");
                    $stmt->execute([$make, $model, $seatingCapacity]);
                    $inserted++;
                    echo "Inserted: $make $model ($seatingCapacity seats)<br>";
                } else {
                    $skipped++;
                    echo "Skipped (already exists): $make $model<br>";
                }
            }
        }
    }

    echo "<br><strong>Summary:</strong><br>";
    echo "Vehicles inserted: $inserted<br>";
    echo "Vehicles skipped: $skipped<br>";
    echo "Total processed: " . ($inserted + $skipped) . "<br>";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}

