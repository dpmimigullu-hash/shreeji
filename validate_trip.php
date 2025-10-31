<?php
session_start();
require_once 'includes/config.php';
require_once 'includes/functions.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

$user = getUserById($_SESSION['user_id']);
$role = $user['role'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
    exit;
}

$action = $_POST['action'] ?? '';
$trip_id = (int)($_POST['trip_id'] ?? 0);
$provided_code = trim($_POST['code'] ?? '');

if (!$trip_id || !$provided_code) {
    echo json_encode(['success' => false, 'error' => 'Missing required parameters']);
    exit;
}

$conn = getDBConnection();

// Get trip details
$stmt = $conn->prepare("SELECT * FROM trips WHERE id = ?");
$stmt->execute([$trip_id]);
$trip = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$trip) {
    echo json_encode(['success' => false, 'error' => 'Trip not found']);
    exit;
}

// Check if user has permission to validate this trip
if ($role === 'driver' && $trip['driver_id'] != $_SESSION['user_id']) {
    echo json_encode(['success' => false, 'error' => 'Access denied']);
    exit;
}

try {
    if ($action === 'start_trip') {
        // Validate start OTP/QR code
        $valid_start = false;

        if ($trip['start_otp'] && $trip['start_otp'] === $provided_code) {
            $valid_start = true;
        } elseif ($trip['start_qr_code'] && $trip['start_qr_code'] === $provided_code) {
            $valid_start = true;
        }

        if ($valid_start) {
            // Start the trip
            $stmt = $conn->prepare("UPDATE trips SET status = 'in_progress', start_time = NOW() WHERE id = ?");
            $stmt->execute([$trip_id]);

            // Mark driver attendance as present
            markAttendance($trip['driver_id'], 'present');

            // Update driver status to active (on duty)
            $conn->prepare("UPDATE users SET status = 'active' WHERE id = ? AND role = 'driver'")->execute([$trip['driver_id']]);

            echo json_encode([
                'success' => true,
                'message' => 'Trip started successfully',
                'trip' => [
                    'id' => $trip['id'],
                    'status' => 'in_progress',
                    'start_time' => date('Y-m-d H:i:s')
                ]
            ]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Invalid start code']);
        }
    } elseif ($action === 'end_trip') {
        // Validate end OTP/QR code
        $valid_end = false;

        if ($trip['end_otp'] && $trip['end_otp'] === $provided_code) {
            $valid_end = true;
        } elseif ($trip['end_qr_code'] && $trip['end_qr_code'] === $provided_code) {
            $valid_end = true;
        }

        if ($valid_end) {
            // Get end location from POST data
            $end_lat = (float)($_POST['end_lat'] ?? 0);
            $end_lng = (float)($_POST['end_lng'] ?? 0);

            // Calculate distance
            $distance = calculateDistance($trip['start_location_lat'], $trip['start_location_lng'], $end_lat, $end_lng);

            // Get vehicle details for billing
            $stmt = $conn->prepare("SELECT seating_capacity FROM vehicles WHERE id = ?");
            $stmt->execute([$trip['vehicle_id']]);
            $vehicle = $stmt->fetch(PDO::FETCH_ASSOC);

            // Calculate bill
            $amount = calculateBill($distance, $vehicle['seating_capacity'] ?? 4);

            // Update trip
            $stmt = $conn->prepare("UPDATE trips SET status = 'completed', end_time = NOW(), end_location_lat = ?, end_location_lng = ?, distance = ? WHERE id = ?");
            $stmt->execute([$end_lat, $end_lng, $distance, $trip_id]);

            // Create billing record
            $stmt = $conn->prepare("INSERT INTO billing (trip_id, amount) VALUES (?, ?) ON DUPLICATE KEY UPDATE amount = ?");
            $stmt->execute([$trip_id, $amount, $amount]);

            // Update driver status to inactive (off duty)
            $conn->prepare("UPDATE users SET status = 'inactive' WHERE id = ? AND role = 'driver'")->execute([$trip['driver_id']]);

            echo json_encode([
                'success' => true,
                'message' => 'Trip completed successfully',
                'trip' => [
                    'id' => $trip['id'],
                    'status' => 'completed',
                    'end_time' => date('Y-m-d H:i:s'),
                    'distance' => $distance,
                    'amount' => $amount
                ]
            ]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Invalid end code']);
        }
    } else {
        echo json_encode(['success' => false, 'error' => 'Invalid action']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
}

