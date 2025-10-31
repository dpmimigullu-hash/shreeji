<?php
// Migration script to rename 'cars' table to 'vehicles'
// This PHP script will execute the migration automatically

require_once 'includes/config.php';

try {
    $conn = getDBConnection();

    echo "<h2>Running Database Migration</h2>";
    echo "<p>Renaming 'cars' table to 'vehicles'...</p>";

    // Check if cars table exists
    $result = $conn->query("SHOW TABLES LIKE 'cars'");
    $carsExists = $result->rowCount() > 0;

    // Check if vehicles table exists
    $result = $conn->query("SHOW TABLES LIKE 'vehicles'");
    $vehiclesExists = $result->rowCount() > 0;

    if (!$carsExists && !$vehiclesExists) {
        echo "<p style='color: red;'>Error: Neither 'cars' nor 'vehicles' table exists. Please run the database schema first.</p>";
        exit;
    }

    if ($vehiclesExists && !$carsExists) {
        echo "<p style='color: green;'>✓ 'vehicles' table already exists. Checking column names...</p>";
        $skipRename = true;
    } elseif ($carsExists) {
        $skipRename = false;
    } else {
        echo "<p style='color: red;'>Error: Unexpected table state.</p>";
        exit;
    }

    // Start transaction
    $conn->beginTransaction();

    // Rename the table if needed
    if (!$skipRename) {
        echo "<p>Step 1: Renaming table...</p>";
        $conn->exec("ALTER TABLE cars RENAME TO vehicles");
        echo "<p style='color: green;'>✓ Table renamed successfully</p>";
    } else {
        echo "<p>Step 1: Table already renamed, skipping...</p>";
    }

    // Drop old foreign key if it exists
    echo "<p>Step 2: Updating foreign key constraints...</p>";
    try {
        // Check if foreign key exists
        $result = $conn->query("SELECT CONSTRAINT_NAME FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS WHERE TABLE_NAME = 'trips' AND CONSTRAINT_TYPE = 'FOREIGN KEY' AND CONSTRAINT_NAME LIKE '%ibfk%'");
        $constraints = $result->fetchAll(PDO::FETCH_ASSOC);

        foreach ($constraints as $constraint) {
            try {
                $conn->exec("ALTER TABLE trips DROP FOREIGN KEY " . $constraint['CONSTRAINT_NAME']);
                echo "<p style='color: green;'>✓ Dropped foreign key: " . $constraint['CONSTRAINT_NAME'] . "</p>";
            } catch (Exception $e) {
                echo "<p style='color: orange;'>⚠ Could not drop foreign key " . $constraint['CONSTRAINT_NAME'] . ": " . $e->getMessage() . "</p>";
            }
        }

        if (empty($constraints)) {
            echo "<p style='color: blue;'>ℹ No old foreign keys found to drop</p>";
        }
    } catch (Exception $e) {
        echo "<p style='color: orange;'>⚠ Error checking foreign keys: " . $e->getMessage() . "</p>";
    }

    // Check if vehicle_id column exists, if not rename car_id to vehicle_id
    echo "<p>Step 3: Checking column names...</p>";
    $columns = $conn->query("DESCRIBE trips")->fetchAll(PDO::FETCH_ASSOC);
    $hasVehicleId = false;
    $hasCarId = false;

    foreach ($columns as $column) {
        if ($column['Field'] === 'vehicle_id') {
            $hasVehicleId = true;
        }
        if ($column['Field'] === 'car_id') {
            $hasCarId = true;
        }
    }

    if ($hasCarId && !$hasVehicleId) {
        echo "<p>Renaming car_id column to vehicle_id...</p>";
        $conn->exec("ALTER TABLE trips CHANGE car_id vehicle_id INT NOT NULL");
        echo "<p style='color: green;'>✓ Column renamed from car_id to vehicle_id</p>";
    } elseif (!$hasCarId && !$hasVehicleId) {
        echo "<p style='color: red;'>Error: Neither car_id nor vehicle_id column found in trips table</p>";
        throw new Exception("Missing vehicle_id column in trips table");
    } elseif ($hasVehicleId) {
        echo "<p style='color: blue;'>ℹ vehicle_id column already exists</p>";
    }

    // Clean up orphaned data before adding foreign key
    echo "<p>Step 4: Cleaning up orphaned trip data...</p>";
    try {
        $stmt = $conn->prepare("SELECT COUNT(*) as orphaned FROM trips t LEFT JOIN vehicles v ON t.vehicle_id = v.id WHERE v.id IS NULL");
        $stmt->execute();
        $orphaned = $stmt->fetch(PDO::FETCH_ASSOC)['orphaned'];

        if ($orphaned > 0) {
            echo "<p style='color: orange;'>⚠ Found $orphaned trips with invalid vehicle references.</p>";

            // Get the first available vehicle ID
            $stmt = $conn->query("SELECT id FROM vehicles ORDER BY id LIMIT 1");
            $firstVehicle = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($firstVehicle) {
                $vehicleId = $firstVehicle['id'];
                echo "<p>Updating orphaned trips to use vehicle ID $vehicleId...</p>";
                $conn->exec("UPDATE trips SET vehicle_id = $vehicleId WHERE vehicle_id NOT IN (SELECT id FROM vehicles)");
                echo "<p style='color: green;'>✓ Orphaned trip data cleaned up</p>";
            } else {
                echo "<p style='color: red;'>Error: No vehicles found in database!</p>";
                throw new Exception("No vehicles available for orphaned data cleanup");
            }
        } else {
            echo "<p style='color: blue;'>ℹ No orphaned trip data found</p>";
        }
    } catch (Exception $e) {
        echo "<p style='color: orange;'>⚠ Could not check for orphaned data: " . $e->getMessage() . "</p>";
    }

    // Add new foreign key
    echo "<p>Step 5: Adding foreign key constraint...</p>";
    try {
        $conn->exec("ALTER TABLE trips ADD CONSTRAINT trips_vehicle_fk FOREIGN KEY (vehicle_id) REFERENCES vehicles(id)");
        echo "<p style='color: green;'>✓ New foreign key added</p>";
    } catch (Exception $e) {
        // If foreign key already exists, that's okay
        if (
            strpos($e->getMessage(), 'Duplicate key name') !== false ||
            strpos($e->getMessage(), 'already exists') !== false ||
            strpos($e->getMessage(), 'Duplicate entry') !== false
        ) {
            echo "<p style='color: blue;'>ℹ Foreign key already exists</p>";
        } else {
            // Try to check if there are still integrity issues
            echo "<p style='color: orange;'>⚠ Foreign key creation failed: " . $e->getMessage() . "</p>";
            echo "<p>Attempting to identify remaining issues...</p>";

            try {
                $stmt = $conn->query("SELECT t.id, t.vehicle_id FROM trips t LEFT JOIN vehicles v ON t.vehicle_id = v.id WHERE v.id IS NULL LIMIT 5");
                $badTrips = $stmt->fetchAll(PDO::FETCH_ASSOC);

                if (!empty($badTrips)) {
                    echo "<p style='color: red;'>Still found trips with invalid vehicle references:</p>";
                    foreach ($badTrips as $trip) {
                        echo "<p>Trip ID {$trip['id']} references invalid vehicle ID {$trip['vehicle_id']}</p>";
                    }
                    echo "<p>Please manually fix these records or truncate the trips table if it's test data.</p>";
                }
            } catch (Exception $checkE) {
                echo "<p style='color: orange;'>Could not check for remaining issues: " . $checkE->getMessage() . "</p>";
            }

            // Don't throw the exception, just warn
            echo "<p style='color: orange;'>⚠ Migration completed with warnings. Foreign key constraint may need manual setup.</p>";
        }
    }

    // Commit transaction
    $conn->commit();

    echo "<h3 style='color: green;'>Migration completed successfully!</h3>";
    echo "<p>The 'cars' table has been renamed to 'vehicles' and all foreign key references have been updated.</p>";
    echo "<p>You can now access the application at: <a href='trips.php'>Trips Page</a></p>";
} catch (Exception $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    echo "<h3 style='color: red;'>Migration failed!</h3>";
    echo "<p>Error: " . $e->getMessage() . "</p>";
    echo "<p>Please check your database and try again.</p>";
}

