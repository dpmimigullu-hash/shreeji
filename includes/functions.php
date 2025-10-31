<?php
require_once 'config.php';

// User functions
function getUserById($userId)
{
    $conn = getDBConnection();
    $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function authenticateUser($username, $password)
{
    $conn = getDBConnection();
    $stmt = $conn->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user && password_verify($password, $user['password'])) {
        return $user;
    }
    return false;
}

function getTotalTrips()
{
    $conn = getDBConnection();
    $stmt = $conn->query("SELECT COUNT(*) FROM trips");
    return $stmt->fetchColumn();
}

function getTotalDrivers()
{
    $conn = getDBConnection();
    $stmt = $conn->query("SELECT COUNT(*) FROM users WHERE role = 'driver'");
    return $stmt->fetchColumn();
}

function getTotalCars()
{
    $conn = getDBConnection();
    try {
        $stmt = $conn->query("SELECT COUNT(*) FROM vehicles WHERE driver_id IS NOT NULL");
        return $stmt->fetchColumn();
    } catch (PDOException $e) {
        // Fallback if vehicles table doesn't exist
        return 0;
    }
}

function getTotalRevenue()
{
    $conn = getDBConnection();
    $stmt = $conn->query("SELECT SUM(amount) FROM billing");
    return $stmt->fetchColumn();
}

function getSupervisorTrips($supervisorId)
{
    $conn = getDBConnection();
    $stmt = $conn->prepare("SELECT * FROM trips WHERE supervisor_id = ?");
    $stmt->execute([$supervisorId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getSupervisorDrivers($supervisorId)
{
    $conn = getDBConnection();
    $stmt = $conn->prepare("SELECT * FROM users WHERE role = 'driver' AND supervisor_id = ?");
    $stmt->execute([$supervisorId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getDriverTrips($driverId)
{
    $conn = getDBConnection();
    $stmt = $conn->prepare("SELECT * FROM trips WHERE driver_id = ?");
    $stmt->execute([$driverId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getDriverAttendance($driverId)
{
    $conn = getDBConnection();
    $currentMonth = date('Y-m');
    $stmt = $conn->prepare("SELECT COUNT(*) as total_days FROM attendance WHERE driver_id = ? AND DATE_FORMAT(date, '%Y-%m') = ?");
    $stmt->execute([$driverId, $currentMonth]);
    $totalDays = $stmt->fetchColumn();

    $stmt = $conn->prepare("SELECT COUNT(*) as present_days FROM attendance WHERE driver_id = ? AND DATE_FORMAT(date, '%Y-%m') = ? AND status = 'present'");
    $stmt->execute([$driverId, $currentMonth]);
    $presentDays = $stmt->fetchColumn();

    return ['total_days' => $totalDays, 'present_days' => $presentDays];
}

// Trip functions
function generateOTP()
{
    return rand(100000, 999999);
}

function generateQRCode($data)
{
    // Generate QR code using Google Charts API
    $size = '200x200';
    $encodedData = urlencode($data);
    $qrUrl = "https://chart.googleapis.com/chart?chs={$size}&cht=qr&chl={$encodedData}";

    // For now, return the URL - in production, you might want to save the image
    return $qrUrl;
}

function calculateDistance($lat1, $lon1, $lat2, $lon2)
{
    $earthRadius = 6371000; // meters

    $latDelta = deg2rad($lat2 - $lat1);
    $lonDelta = deg2rad($lon2 - $lon1);

    $a = sin($latDelta / 2) * sin($latDelta / 2) +
        cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
        sin($lonDelta / 2) * sin($lonDelta / 2);
    $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

    return $earthRadius * $c;
}

function isWithinGeofence($userLat, $userLon, $targetLat, $targetLon)
{
    $distance = calculateDistance($userLat, $userLon, $targetLat, $targetLon);
    return $distance <= GEOFENCE_RADIUS;
}

function calculateBill($distance, $seatingCapacity)
{
    $slabs = BILLING_SLABS;

    switch ($seatingCapacity) {
        case 4:
            $slab = $slabs['4_seater'];
            break;
        case 7:
            $slab = $slabs['7_seater'];
            break;
        case 13:
            $slab = $slabs['13_seater'];
            break;
        default:
            return 0;
    }

    if ($distance <= 20) {
        return $slab['0-20'];
    } elseif ($distance <= 40) {
        return $slab['21-40'];
    } elseif ($distance <= 60) {
        return $slab['41-60'];
    } elseif ($distance <= 80) {
        return $slab['61-80'];
    } else {
        // For distances over 80km, calculate additional cost
        $baseCost = $slab['61-80'];
        $extraKm = $distance - 80;
        $extraCost = $extraKm * 20; // Assuming 20 per extra km
        return $baseCost + $extraCost;
    }
}

// Attendance functions
function markAttendance($driverId, $status)
{
    $conn = getDBConnection();
    $date = date('Y-m-d');
    $stmt = $conn->prepare("INSERT INTO attendance (driver_id, date, status) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE status = ?");
    $stmt->execute([$driverId, $date, $status, $status]);
}

// Function to validate trip OTP/QR and mark attendance
function validateTripStart($tripId, $providedOtp)
{
    $conn = getDBConnection();
    $stmt = $conn->prepare("SELECT * FROM trips WHERE id = ? AND start_otp = ?");
    $stmt->execute([$tripId, $providedOtp]);
    $trip = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($trip) {
        // Mark driver as active (duty started)
        markAttendance($trip['driver_id'], 'present');
        return true;
    }
    return false;
}

function validateTripEnd($tripId, $providedOtp)
{
    $conn = getDBConnection();
    $stmt = $conn->prepare("SELECT * FROM trips WHERE id = ? AND end_otp = ?");
    $stmt->execute([$tripId, $providedOtp]);
    $trip = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($trip) {
        // Mark driver as inactive (duty ended)
        // Note: This would typically update the attendance status to 'completed' or similar
        // For now, we'll keep the existing 'present' status but could extend this
        return true;
    }
    return false;
}

// Report functions
function getDailyReport($date)
{
    $conn = getDBConnection();
    $stmt = $conn->prepare("
        SELECT t.*, u.name as driver_name, v.make, v.model, b.amount
        FROM trips t
        LEFT JOIN users u ON t.driver_id = u.id
        LEFT JOIN vehicles v ON t.vehicle_id = v.id
        LEFT JOIN billing b ON t.id = b.trip_id
        WHERE DATE(t.start_time) = ?
    ");
    $stmt->execute([$date]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getWeeklyReport($startDate, $endDate)
{
    $conn = getDBConnection();
    $stmt = $conn->prepare("
        SELECT DATE(t.start_time) as date, COUNT(*) as trips, SUM(t.distance) as total_distance, SUM(b.amount) as total_amount
        FROM trips t
        LEFT JOIN billing b ON t.id = b.trip_id
        WHERE DATE(t.start_time) BETWEEN ? AND ?
        GROUP BY DATE(t.start_time)
    ");
    $stmt->execute([$startDate, $endDate]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getMonthlyReport($month, $year)
{
    $conn = getDBConnection();
    $stmt = $conn->prepare("
        SELECT u.name as driver_name, COUNT(t.id) as trips, SUM(t.distance) as total_distance, SUM(b.amount) as total_amount
        FROM users u
        LEFT JOIN trips t ON u.id = t.driver_id
        LEFT JOIN billing b ON t.id = b.trip_id
        WHERE u.role = 'driver' AND MONTH(t.start_time) = ? AND YEAR(t.start_time) = ?
        GROUP BY u.id, u.name
    ");
    $stmt->execute([$month, $year]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// File upload functions
function uploadFile($file)
{
    if (!isset($file) || $file['error'] !== UPLOAD_ERR_OK) {
        return null;
    }

    $uploadDir = 'uploads/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    $fileName = uniqid() . '_' . basename($file['name']);
    $filePath = $uploadDir . $fileName;

    if (move_uploaded_file($file['tmp_name'], $filePath)) {
        return $filePath;
    }

    return null;
}

function uploadMultipleFiles($files)
{
    if (!isset($files) || !is_array($files['name'])) {
        return [];
    }

    $uploadedFiles = [];
    $fileCount = count($files['name']);

    for ($i = 0; $i < $fileCount; $i++) {
        if ($files['error'][$i] === UPLOAD_ERR_OK) {
            $file = [
                'name' => $files['name'][$i],
                'type' => $files['type'][$i],
                'tmp_name' => $files['tmp_name'][$i],
                'error' => $files['error'][$i],
                'size' => $files['size'][$i]
            ];

            $uploadedFile = uploadFile($file);
            if ($uploadedFile) {
                $uploadedFiles[] = $uploadedFile;
            }
        }
    }

    return $uploadedFiles;
}
