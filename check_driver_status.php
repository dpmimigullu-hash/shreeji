<?php
session_start();
require_once 'includes/config.php';
require_once 'includes/functions.php';

// Check if user is logged in and is a driver
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['isActive' => false, 'error' => 'Not authenticated']);
    exit;
}

$user = getUserById($_SESSION['user_id']);
$role = $user['role'];

if ($role !== 'driver') {
    echo json_encode(['isActive' => false, 'error' => 'Access denied']);
    exit;
}

$conn = getDBConnection();

try {
    // Check if driver has an active trip or is marked as active
    $stmt = $conn->prepare("
        SELECT
            CASE
                WHEN u.status = 'active' THEN 1
                WHEN t.status = 'in_progress' THEN 1
                ELSE 0
            END as is_active
        FROM users u
        LEFT JOIN trips t ON u.id = t.driver_id AND t.status = 'in_progress'
        WHERE u.id = ? AND u.role = 'driver'
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    echo json_encode(['isActive' => (bool)$result['is_active']]);
} catch (PDOException $e) {
    echo json_encode(['isActive' => false, 'error' => 'Database error: ' . $e->getMessage()]);
}

