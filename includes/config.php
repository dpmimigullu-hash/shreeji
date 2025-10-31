<?php
// Database configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'employee_transportation');
define('DB_USER', 'root');
define('DB_PASS', '');

// Application configuration
define('APP_NAME', 'Employee Transportation System');
define('APP_URL', 'http://localhost/EmployeeTransportationSystem/web');

// Geo-fencing configuration
define('GEOFENCE_RADIUS', 50); // meters

// Billing slabs (from the provided slab.xlsx)
define('BILLING_SLABS', [
    '4_seater' => [
        '0-20' => 300,
        '21-40' => 560,
        '41-60' => 875,
        '61-80' => 1100
    ],
    '7_seater' => [
        '0-20' => 315,
        '21-40' => 680,
        '41-60' => 1040,
        '61-80' => 1400
    ],
    '13_seater' => [
        '0-20' => 315,
        '21-40' => 825,
        '41-60' => 1235,
        '61-80' => 1700
    ]
]);

// Connect to database
function getDBConnection()
{
    static $conn = null;
    if ($conn === null) {
        try {
            $conn = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
            $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch (PDOException $e) {
            die("Database connection failed: " . $e->getMessage());
        }
    }
    return $conn;
}
