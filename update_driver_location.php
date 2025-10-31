<?php
session_start();
require_once 'includes/config.php';
require_once 'includes/functions.php';

// Enable CORS for API access
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    exit(0);
}

// Check if user is logged in and is a driver
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$user = getUserById($_SESSION['user_id']);
if ($user['role'] != 'driver') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Get JSON input
    $input = json_decode(file_get_contents('php://input'), true);

    if (!$input) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid JSON data']);
        exit;
    }

    $driverId = $input['driver_id'] ?? null;
    $latitude = $input['latitude'] ?? null;
    $longitude = $input['longitude'] ?? null;
    $accuracy = $input['accuracy'] ?? null;
    $tripId = $input['trip_id'] ?? null;

    // Validate required fields
    if (!$driverId || !is_numeric($latitude) || !is_numeric($longitude)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Missing or invalid required fields']);
        exit;
    }

    // Verify driver ID matches session
    if ($driverId != $_SESSION['user_id']) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Driver ID mismatch']);
        exit;
    }

    try {
        $conn = getDBConnection();

        // Create driver_locations table if it doesn't exist
        $conn->exec("CREATE TABLE IF NOT EXISTS driver_locations (
            id INT AUTO_INCREMENT PRIMARY KEY,
            driver_id INT NOT NULL,
            trip_id INT NULL,
            latitude DECIMAL(10, 8) NOT NULL,
            longitude DECIMAL(11, 8) NOT NULL,
            accuracy DECIMAL(6, 2) NULL,
            speed DECIMAL(5, 2) NULL,
            heading DECIMAL(5, 2) NULL,
            altitude DECIMAL(7, 2) NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_driver_id (driver_id),
            INDEX idx_trip_id (trip_id),
            INDEX idx_created_at (created_at),
            INDEX idx_location (latitude, longitude)
        )");

        // Insert location data
        $stmt = $conn->prepare("INSERT INTO driver_locations (driver_id, trip_id, latitude, longitude, accuracy) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$driverId, $tripId, $latitude, $longitude, $accuracy]);

        // Update driver's last known location in users table
        $stmt = $conn->prepare("UPDATE users SET last_latitude = ?, last_longitude = ?, last_location_update = NOW() WHERE id = ?");
        $stmt->execute([$latitude, $longitude, $driverId]);

        // If driver has an active trip, update trip location
        if ($tripId && is_numeric($tripId)) {
            // Update current trip location (optional - for real-time tracking)
            $stmt = $conn->prepare("UPDATE trips SET current_lat = ?, current_lng = ?, location_updated_at = NOW() WHERE id = ? AND status = 'in_progress'");
            $stmt->execute([$latitude, $longitude, $tripId]);
        }

        echo json_encode([
            'success' => true,
            'message' => 'Location updated successfully',
            'data' => [
                'location_id' => $conn->lastInsertId(),
                'timestamp' => date('Y-m-d H:i:s')
            ]
        ]);
    } catch (PDOException $e) {
        error_log('Location update error: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
} else {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
}

