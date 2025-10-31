<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user = getUserById($_SESSION['user_id']);
$role = $user['role'];

$conn = getDBConnection();

try {
    // Get all drivers with supervisor information
    $stmt = $conn->prepare("
        SELECT
            u.id,
            u.username,
            u.name,
            u.email,
            u.phone,
            u.license_number,
            u.created_at,
            s.name as supervisor_name,
            u.vehicle_registration_number,
            u.vehicle_chassis_number,
            u.vehicle_engine_number,
            u.vehicle_fuel_type,
            u.pollution_valid_till,
            u.registration_valid_till,
            u.road_tax_valid_till,
            u.fast_tag_valid_till,
            u.insurance_expiry_date,
            u.insurance_provider
        FROM users u
        LEFT JOIN users s ON u.supervisor_id = s.id
        WHERE u.role = 'driver'
        ORDER BY u.created_at DESC
    ");
    $stmt->execute();
    $drivers = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Set headers for CSV download
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=drivers_export_' . date('Y-m-d_H-i-s') . '.csv');

    // Create output stream
    $output = fopen('php://output', 'w');

    // Write CSV headers
    fputcsv($output, [
        'Driver ID',
        'Username',
        'Full Name',
        'Email',
        'Phone',
        'License Number',
        'Supervisor',
        'Vehicle Registration',
        'Chassis Number',
        'Engine Number',
        'Fuel Type',
        'Pollution Valid Till',
        'Registration Valid Till',
        'Road Tax Valid Till',
        'Fast Tag Valid Till',
        'Insurance Provider',
        'Insurance Expiry',
        'Status',
        'Registration Date'
    ]);

    // Write driver data
    foreach ($drivers as $driver) {
        fputcsv($output, [
            $driver['id'],
            $driver['username'],
            $driver['name'],
            $driver['email'],
            $driver['phone'],
            $driver['license_number'],
            $driver['supervisor_name'] ?? 'Not assigned',
            $driver['vehicle_registration_number'] ?? 'N/A',
            $driver['vehicle_chassis_number'] ?? 'N/A',
            $driver['vehicle_engine_number'] ?? 'N/A',
            $driver['vehicle_fuel_type'] ?? 'N/A',
            $driver['pollution_valid_till'] ? date('d/m/Y', strtotime($driver['pollution_valid_till'])) : 'N/A',
            $driver['registration_valid_till'] ? date('d/m/Y', strtotime($driver['registration_valid_till'])) : 'N/A',
            $driver['road_tax_valid_till'] ? date('d/m/Y', strtotime($driver['road_tax_valid_till'])) : 'N/A',
            $driver['fast_tag_valid_till'] ? date('d/m/Y', strtotime($driver['fast_tag_valid_till'])) : 'N/A',
            $driver['insurance_provider'] ?? 'N/A',
            $driver['insurance_expiry_date'] ? date('d/m/Y', strtotime($driver['insurance_expiry_date'])) : 'N/A',
            'active', // Default status since column may not exist
            date('d/m/Y', strtotime($driver['created_at']))
        ]);
    }

    fclose($output);
    exit;
} catch (PDOException $e) {
    // If there's an error, redirect back to drivers page with error
    header('Location: drivers.php?error=' . urlencode('Export failed: ' . $e->getMessage()));
    exit;
}

