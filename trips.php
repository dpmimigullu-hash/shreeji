<?php
session_start();
require_once 'includes/config.php';
require_once 'includes/functions.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user = getUserById($_SESSION['user_id']);
$role = $user['role'];

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['create_trip'])) {
        $driver_id = $_POST['driver_id'];
        $vehicle_registration_number = $_POST['vehicle_registration_number'];

        // Get vehicle_id from the selected option's data attribute
        $vehicle_id = $_POST['vehicle_id'] ?? null;
        if (!$vehicle_id) {
            // Fallback: try to get vehicle_id from driver data
            $conn_temp = getDBConnection();
            $stmt_temp = $conn_temp->prepare("SELECT assigned_vehicle_id FROM users WHERE id = ?");
            $stmt_temp->execute([$driver_id]);
            $driver_data = $stmt_temp->fetch(PDO::FETCH_ASSOC);
            $vehicle_id = $driver_data['assigned_vehicle_id'] ?? $driver_id; // fallback to driver_id
        }
        $passenger_count = (int)$_POST['passenger_count'];
        $start_location_name = trim($_POST['start_location_name']);
        $start_location_lat = (float)$_POST['start_location_lat'];
        $start_location_lng = (float)$_POST['start_location_lng'];
        $end_location_name = trim($_POST['end_location_name']);
        $end_location_lat = (float)$_POST['end_location_lat'];
        $end_location_lng = (float)$_POST['end_location_lng'];
        $supervisor_id = ($role == 'admin') ? $_POST['supervisor_id'] : $_SESSION['user_id'];
        $client_id = !empty($_POST['client_id']) ? $_POST['client_id'] : null;
        $trip_type = $_POST['trip_type'];
        $trip_date = $_POST['trip_date'] ?? date('Y-m-d');
        $scheduled_pickup_time = $_POST['scheduled_pickup_time'];
        $scheduled_drop_time = !empty($_POST['scheduled_drop_time']) ? $_POST['scheduled_drop_time'] : null;
        $estimated_duration = (float)$_POST['estimated_duration'];

        // Passenger information
        $first_passenger_name = trim($_POST['first_passenger_name'] ?? '');
        $first_passenger_phone = trim($_POST['first_passenger_phone'] ?? '');
        $first_passenger_address = trim($_POST['first_passenger_address'] ?? '');
        $last_passenger_name = trim($_POST['last_passenger_name'] ?? '');
        $last_passenger_phone = trim($_POST['last_passenger_phone'] ?? '');
        $last_passenger_address = trim($_POST['last_passenger_address'] ?? '');

        $start_otp = generateOTP();
        $end_otp = generateOTP();
        $start_qr = generateQRCode("START_" . $start_otp . "_" . $driver_id . "_" . time());
        $end_qr = generateQRCode("END_" . $end_otp . "_" . $driver_id . "_" . time());

        $conn = getDBConnection();

        // Prepare trip data for WhatsApp messaging
        $tripData = [
            'id' => null, // Will be set after insertion
            'start_otp' => $start_otp,
            'end_otp' => $end_otp,
            'start_qr_code' => $start_qr,
            'end_qr_code' => $end_qr,
            'first_passenger_name' => $first_passenger_name,
            'first_passenger_phone' => $first_passenger_phone,
            'first_passenger_address' => $first_passenger_address,
            'last_passenger_name' => $last_passenger_name,
            'last_passenger_phone' => $last_passenger_phone,
            'last_passenger_address' => $last_passenger_address,
            'scheduled_pickup_time' => $scheduled_pickup_time,
            'scheduled_drop_time' => $scheduled_drop_time,
            'trip_date' => $trip_date
        ];

        // Set driver status to active (on duty) - only if status column exists
        try {
            $conn->prepare("UPDATE users SET status = 'active' WHERE id = ? AND role = 'driver'")->execute([$driver_id]);
        } catch (PDOException $e) {
            // Status column might not exist in older schema, skip silently
        }
        try {
            $stmt = $conn->prepare("INSERT INTO trips (driver_id, vehicle_id, supervisor_id, client_id, passenger_count, start_location_name, start_location_lat, start_location_lng, end_location_name, end_location_lat, end_location_lng, start_otp, end_otp, start_qr_code, end_qr_code, trip_type, scheduled_pickup_time, scheduled_drop_time, first_passenger_name, first_passenger_phone, first_passenger_address, last_passenger_name, last_passenger_phone, last_passenger_address, trip_date, estimated_duration) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$driver_id, $vehicle_id, $supervisor_id, $client_id, $passenger_count, $start_location_name, $start_location_lat, $start_location_lng, $end_location_name, $end_location_lat, $end_location_lng, $start_otp, $end_otp, $start_qr, $end_qr, $trip_type, $scheduled_pickup_time, $scheduled_drop_time, $first_passenger_name, $first_passenger_phone, $first_passenger_address, $last_passenger_name, $last_passenger_phone, $last_passenger_address, $trip_date, $estimated_duration]);

            // Get the inserted trip ID
            $trip_id = $conn->lastInsertId();
            $tripData['id'] = $trip_id;

            // Send WhatsApp messages
            require_once 'includes/whatsapp.php';

            // Send start trip message to first passenger
            if (!empty($first_passenger_phone)) {
                $startMessageSent = sendStartTripWhatsApp($tripData);
                if (!$startMessageSent) {
                    error_log("Failed to send start trip WhatsApp message for trip ID: $trip_id");
                }
            }

            // Send end trip message to last passenger
            if (!empty($last_passenger_phone)) {
                $endMessageSent = sendEndTripWhatsApp($tripData);
                if (!$endMessageSent) {
                    error_log("Failed to send end trip WhatsApp message for trip ID: $trip_id");
                }
            }

            $success_message = "Trip created successfully! OTP and QR codes have been generated and sent via WhatsApp.";
        } catch (PDOException $e) {
            // Fallback for old schema without additional fields
            try {
                $stmt = $conn->prepare("INSERT INTO trips (driver_id, vehicle_id, supervisor_id, client_id, passenger_count, start_location_name, start_location_lat, start_location_lng, end_location_name, end_location_lat, end_location_lng, start_otp, end_otp, start_qr_code, end_qr_code, trip_date, pickup_time, estimated_duration) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$driver_id, $vehicle_id, $supervisor_id, $client_id, $passenger_count, $start_location_name, $start_location_lat, $start_location_lng, $end_location_name, $end_location_lat, $end_location_lng, $start_otp, $end_otp, $start_qr, $end_qr, $trip_date, $scheduled_pickup_time, $estimated_duration]);
                $success_message = "Trip created successfully!";
            } catch (PDOException $e2) {
                // Final fallback for very old schema - try with minimal fields
                try {
                    $stmt = $conn->prepare("INSERT INTO trips (driver_id, vehicle_id, supervisor_id, passenger_count, start_otp, end_otp, start_qr_code, end_qr_code) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$driver_id, $vehicle_id, $supervisor_id, $passenger_count, $start_otp, $end_otp, $start_qr, $end_qr]);
                    $success_message = "Trip created successfully!";
                } catch (PDOException $e3) {
                    $error_message = "Error creating trip: " . $e3->getMessage();
                }
            }
        } catch (Exception $e) {
            $error_message = "Error creating trip: " . $e->getMessage();
        }
    }
}

// Get trips based on role and branch
$conn = getDBConnection();
try {
    if ($role == 'admin') {
        $stmt = $conn->query("SELECT t.*, u.name as driver_name, v.make, v.model, s.name as supervisor_name, c.name as client_name, b.amount FROM trips t LEFT JOIN users u ON t.driver_id = u.id LEFT JOIN vehicles v ON t.vehicle_id = v.id LEFT JOIN users s ON t.supervisor_id = s.id LEFT JOIN clients c ON t.client_id = c.id LEFT JOIN billing b ON t.id = b.trip_id ORDER BY t.created_at DESC");
        $trips = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } elseif ($role == 'supervisor') {
        $stmt = $conn->prepare("SELECT t.*, u.name as driver_name, v.make, v.model, s.name as supervisor_name, c.name as client_name, b.amount FROM trips t LEFT JOIN users u ON t.driver_id = u.id LEFT JOIN vehicles v ON t.vehicle_id = v.id LEFT JOIN users s ON t.supervisor_id = s.id LEFT JOIN clients c ON t.client_id = c.id LEFT JOIN billing b ON t.id = b.trip_id WHERE t.supervisor_id = ? ORDER BY t.created_at DESC");
        $stmt->execute([$_SESSION['user_id']]);
        $trips = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $stmt = $conn->prepare("SELECT t.*, u.name as driver_name, v.make, v.model, s.name as supervisor_name, c.name as client_name, b.amount FROM trips t LEFT JOIN users u ON t.driver_id = u.id LEFT JOIN vehicles v ON t.vehicle_id = v.id LEFT JOIN users s ON t.supervisor_id = s.id LEFT JOIN clients c ON t.client_id = c.id LEFT JOIN billing b ON t.id = b.trip_id WHERE t.driver_id = ? ORDER BY t.created_at DESC");
        $stmt->execute([$_SESSION['user_id']]);
        $trips = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (PDOException $e) {
    $trips = [];
    $error_message = "Error loading trips: " . $e->getMessage();
}

// Get available drivers, cars, and clients
if ($role != 'driver') {
    if ($role == 'admin') {
        // Get drivers with their assigned cars from all branches
        try {
            $drivers = $conn->query("SELECT u.id, u.name, v.id as vehicle_id, v.make, v.model, v.license_plate, v.registration_number FROM users u LEFT JOIN vehicles v ON u.id = v.driver_id WHERE u.role = 'driver' AND (v.driver_id IS NOT NULL OR v.id IS NULL)")->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            // Fallback if registration_number column doesn't exist
            $drivers = $conn->query("SELECT u.id, u.name, v.id as vehicle_id, v.make, v.model, v.license_plate FROM users u LEFT JOIN vehicles v ON u.id = v.driver_id WHERE u.role = 'driver' AND (v.driver_id IS NOT NULL OR v.id IS NULL)")->fetchAll(PDO::FETCH_ASSOC);
        }
        $supervisors = $conn->query("SELECT id, name FROM users WHERE role = 'supervisor'")->fetchAll(PDO::FETCH_ASSOC);
        // Get clients
        try {
            $clients = $conn->query("SELECT id, name FROM clients WHERE status = 'active' ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            $clients = [];
        }
    } else {
        // Get drivers from this supervisor's branch with their assigned cars
        try {
            $stmt = $conn->prepare("SELECT u.id, u.name, v.id as vehicle_id, v.make, v.model, v.license_plate, v.registration_number FROM users u LEFT JOIN vehicles v ON u.id = v.driver_id WHERE u.role = 'driver' AND u.branch_id = (SELECT branch_id FROM users WHERE id = ?) AND (v.driver_id IS NOT NULL OR v.id IS NULL)");
            $stmt->execute([$_SESSION['user_id']]);
            $drivers = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            // Fallback if registration_number column doesn't exist or branch support not available
            $stmt = $conn->prepare("SELECT u.id, u.name, v.id as vehicle_id, v.make, v.model, v.license_plate FROM users u LEFT JOIN vehicles v ON u.id = v.driver_id WHERE u.role = 'driver' AND u.supervisor_id = ? AND (v.driver_id IS NOT NULL OR v.id IS NULL)");
            $stmt->execute([$_SESSION['user_id']]);
            $drivers = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
        // Get clients
        try {
            $clients = $conn->query("SELECT id, name FROM clients WHERE status = 'active' ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            $clients = [];
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, user-scalable=yes">
    <link rel="apple-touch-icon" sizes="76x76" href="./assets/img/apple-icon.png">
    <link rel="icon" type="image/png" href="./assets/images/logo.png">
    <title>Trips - Shreeji Link Employee Transportation System</title>
    <link rel="stylesheet" type="text/css" href="https://fonts.googleapis.com/css?family=Inter:300,400,500,600,700,900" />
    <link href="./assets/nucleo-icons.css" rel="stylesheet" />
    <link href="./assets/nucleo-svg.css" rel="stylesheet" />
    <script src="https://kit.fontawesome.com/42d5adcbca.js" crossorigin="anonymous"></script>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded:opsz,wght,FILL,GRAD@24,400,0,0" />
    <link id="pagestyle" href="./assets/material-dashboard.css?v=3.2.0" rel="stylesheet" />
    <script src="https://maps.googleapis.com/maps/api/js?key=AIzaSyAUnYgTLEnIHwXCE1A98gAGFXBrvQEv8aI&libraries=places,directions&callback=initGoogleMaps" async defer></script>
    <style>
        .ola-uber-input {
            border-radius: 8px !important;
            border: 2px solid #e0e0e0 !important;
            padding: 12px 16px !important;
            font-size: 16px !important;
            transition: all 0.2s ease !important;
            background: white !important;
            -webkit-appearance: none !important;
            appearance: none !important;
        }

        .ola-uber-input:focus {
            border-color: #1976d2 !important;
            box-shadow: 0 0 0 3px rgba(25, 118, 210, 0.1) !important;
            outline: none !important;
        }

        /* Prevent text size adjustment on mobile */
        * {
            -webkit-text-size-adjust: 100%;
            -ms-text-size-adjust: 100%;
        }

        /* Improve touch targets */
        button,
        .btn,
        a,
        input[type="submit"],
        input[type="button"] {
            min-height: 44px;
            min-width: 44px;
        }

        /* Better scrolling on mobile */
        .table-responsive {
            -webkit-overflow-scrolling: touch;
        }
    </style>
    <style>
        /* Ola/Uber-style input styling */
        .ola-uber-input {
            border-radius: 8px !important;
            border: 2px solid #e0e0e0 !important;
            padding: 12px 16px !important;
            font-size: 16px !important;
            transition: all 0.2s ease !important;
            background: white !important;
        }

        .ola-uber-input:focus {
            border-color: #1976d2 !important;
            box-shadow: 0 0 0 3px rgba(25, 118, 210, 0.1) !important;
            outline: none !important;
        }

        .ola-uber-input.ola-uber-active {
            border-color: #1976d2 !important;
            box-shadow: 0 0 0 3px rgba(25, 118, 210, 0.1) !important;
        }

        .ola-suggestions-container {
            border-radius: 0 0 12px 12px !important;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.12) !important;
            border: 1px solid #e0e0e0 !important;
            border-top: none !important;
            max-height: 320px !important;
            overflow-y: auto !important;
            background: white !important;
        }

        .suggestion-item {
            padding: 12px 16px !important;
            cursor: pointer !important;
            border-bottom: 1px solid #f0f0f0 !important;
            transition: all 0.2s ease !important;
            display: flex !important;
            align-items: center !important;
        }

        .suggestion-item:hover {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%) !important;
        }

        .suggestion-header {
            padding: 8px 16px !important;
            background: #f8f9fa !important;
            border-bottom: 1px solid #e9ecef !important;
            font-size: 12px !important;
            font-weight: 600 !important;
            color: #6c757d !important;
            text-transform: uppercase !important;
            letter-spacing: 0.5px !important;
        }

        /* Mobile and Tablet Responsive Styles */
        @media (max-width: 768px) {
            .sidenav {
                transform: translateX(-100%);
                width: 280px !important;
            }

            .sidenav.show {
                transform: translateX(0);
            }

            .main-content {
                margin-left: 0 !important;
            }

            .container-fluid {
                padding-left: 0.5rem;
                padding-right: 0.5rem;
            }

            .card {
                margin-bottom: 1rem;
            }

            .row {
                margin-left: -0.25rem;
                margin-right: -0.25rem;
            }

            .row>* {
                padding-left: 0.25rem;
                padding-right: 0.25rem;
            }

            .col-md-6,
            .col-md-3,
            .col-md-4 {
                flex: 0 0 100%;
                max-width: 100%;
                margin-bottom: 1rem;
            }

            .ola-uber-input {
                padding: 12px 14px !important;
                font-size: 16px !important;
                min-height: 44px !important;
            }

            .form-control-sm {
                padding: 0.75rem 0.875rem !important;
                font-size: 1rem !important;
                min-height: 44px !important;
            }

            .btn {
                padding: 0.75rem 1.5rem;
                font-size: 1rem;
                min-height: 44px;
                touch-action: manipulation;
            }

            .table-responsive {
                font-size: 0.875rem;
                -webkit-overflow-scrolling: touch;
            }

            .table th,
            .table td {
                padding: 0.75rem 0.5rem;
                min-width: 80px;
            }

            #trip_map {
                height: 300px !important;
                touch-action: pan-x pan-y;
            }

            .navbar-nav {
                flex-direction: row;
                justify-content: space-between;
                width: 100%;
            }

            .navbar-nav .nav-item {
                margin: 0 0.25rem;
            }

            /* Touch-friendly form elements */
            select.form-control {
                -webkit-appearance: none;
                -moz-appearance: none;
                appearance: none;
                background-image: url("data:image/svg+xml;charset=UTF-8,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3e%3cpolyline points='6,9 12,15 18,9'%3e%3c/polyline%3e%3c/svg%3e");
                background-repeat: no-repeat;
                background-position: right 0.75rem center;
                background-size: 1rem;
                padding-right: 2.5rem !important;
            }

            /* Improve button spacing */
            .btn-group {
                gap: 0.5rem;
            }

            /* Better spacing for form groups */
            .form-group {
                margin-bottom: 1.5rem;
            }

            /* Ensure labels are readable */
            .form-control-label {
                font-size: 0.9rem !important;
                margin-bottom: 0.5rem;
                display: block;
            }
        }

        @media (max-width: 576px) {
            .container-fluid {
                padding-left: 0.25rem;
                padding-right: 0.25rem;
            }

            .card-body {
                padding: 1rem;
            }

            .border.rounded {
                padding: 0.75rem !important;
            }

            .ola-uber-input {
                padding: 12px 14px !important;
                font-size: 16px !important;
                min-height: 48px !important;
            }

            .form-group {
                margin-bottom: 1rem;
            }

            .form-control-label {
                font-size: 0.9rem !important;
                margin-bottom: 0.5rem;
                display: block;
            }

            .btn-lg {
                padding: 0.875rem 1.75rem;
                font-size: 1.1rem;
                min-height: 48px;
                width: 100%;
                margin-bottom: 0.5rem;
            }

            .table {
                font-size: 0.85rem;
            }

            .badge {
                font-size: 0.8rem;
                padding: 0.375rem 0.75rem;
            }

            /* Stack form elements vertically on very small screens */
            .row.mb-3 .col-md-6 {
                margin-bottom: 1rem;
            }

            /* Make table more mobile-friendly */
            .table-responsive {
                border: none;
            }

            .table th,
            .table td {
                white-space: nowrap;
                vertical-align: middle;
            }

            /* Hide less important columns on mobile */
            .table th:nth-child(6),
            .table td:nth-child(6),
            .table th:nth-child(7),
            .table td:nth-child(7),
            .table th:nth-child(8),
            .table td:nth-child(8) {
                display: none;
            }

            /* Ensure touch targets are large enough */
            a.text-secondary,
            a.text-success,
            a.text-danger {
                padding: 0.5rem;
                display: inline-block;
                min-width: 44px;
                min-height: 44px;
                text-align: center;
            }

            /* Improve navbar on mobile */
            .navbar-toggler {
                border: none;
                padding: 0.5rem;
            }

            .navbar-toggler:focus {
                box-shadow: none;
            }
        }

        @media (min-width: 769px) and (max-width: 1024px) {
            .container-fluid {
                padding-left: 1rem;
                padding-right: 1rem;
            }

            .col-md-6 {
                flex: 0 0 50%;
                max-width: 50%;
            }

            .col-md-3 {
                flex: 0 0 33.333%;
                max-width: 33.333%;
            }

            .ola-uber-input {
                padding: 12px 16px !important;
                font-size: 16px !important;
            }

            #trip_map {
                height: 350px !important;
            }

            /* Tablet-specific adjustments */
            .table th,
            .table td {
                padding: 0.5rem 0.75rem;
            }

            .btn {
                padding: 0.625rem 1.25rem;
                font-size: 0.9rem;
            }
        }

        /* Large desktop screens */
        @media (min-width: 1200px) {
            .container-fluid {
                max-width: 1400px;
            }

            #trip_map {
                height: 400px !important;
            }

            .table th,
            .table td {
                padding: 0.75rem 1rem;
            }
        }

        /* High-resolution displays */
        @media (min-width: 1400px) {
            .container-fluid {
                max-width: 1600px;
            }

            .card-body {
                padding: 2rem;
            }

            #trip_map {
                height: 450px !important;
            }
        }
    </style>
</head>

<body class="g-sidenav-show bg-gray-100">
    <aside class="sidenav navbar navbar-vertical navbar-expand-xs border-radius-lg fixed-start ms-2 bg-white my-2" id="sidenav-main">
        <div class="sidenav-header">
            <i class="fas fa-times p-3 cursor-pointer text-dark opacity-5 position-absolute end-0 top-0 d-none d-xl-none" aria-hidden="true" id="iconSidenav"></i>
            <a class="navbar-brand px-4 py-3 m-0" href="#">
                <img src="./assets/images/logo.png" class="navbar-brand-img" width="26" height="26" alt="main_logo">
                <span class="ms-1 text-sm text-dark">Shreeji Link</span>
            </a>
        </div>
        <hr class="horizontal dark mt-0 mb-2">
        <div class="collapse navbar-collapse w-auto" id="sidenav-collapse-main">
            <ul class="navbar-nav">
                <li class="nav-item">
                    <a class="nav-link text-dark" href="index.php">
                        <i class="material-symbols-rounded opacity-5">dashboard</i>
                        <span class="nav-link-text ms-1">Dashboard</span>
                    </a>
                </li>
                <?php if ($role == 'admin' || $role == 'supervisor'): ?>
                    <li class="nav-item">
                        <a class="nav-link active bg-gradient-dark text-white" href="trips.php">
                            <i class="material-symbols-rounded opacity-5">route</i>
                            <span class="nav-link-text ms-1">Trips</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link text-dark" href="drivers.php">
                            <i class="material-symbols-rounded opacity-5">people</i>
                            <span class="nav-link-text ms-1">Drivers & Vehicles</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link text-dark" href="geolocation_tracker.php">
                            <i class="material-symbols-rounded opacity-5">location_on</i>
                            <span class="nav-link-text ms-1">Live Tracking</span>
                        </a>
                    </li>
                    <?php if ($role == 'admin'): ?>
                        <li class="nav-item">
                            <a class="nav-link text-dark" href="import_vehicles.php">
                                <i class="material-symbols-rounded opacity-5">upload_file</i>
                                <span class="nav-link-text ms-1">Import Vehicles</span>
                            </a>
                        </li>
                    <?php endif; ?>
                    <li class="nav-item">
                        <a class="nav-link text-dark" href="attendance.php">
                            <i class="material-symbols-rounded opacity-5">event_available</i>
                            <span class="nav-link-text ms-1">Attendance</span>
                        </a>
                    </li>
                <?php endif; ?>
                <?php if ($role == 'admin'): ?>
                    <li class="nav-item">
                        <a class="nav-link text-dark" href="reports.php">
                            <i class="material-symbols-rounded opacity-5">analytics</i>
                            <span class="nav-link-text ms-1">Reports</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link text-dark" href="billing.php">
                            <i class="material-symbols-rounded opacity-5">receipt_long</i>
                            <span class="nav-link-text ms-1">Billing</span>
                        </a>
                    </li>
                <?php endif; ?>
            </ul>
        </div>
        <div class="sidenav-footer position-absolute w-100 bottom-0">
            <div class="mx-3">
                <a class="btn btn-outline-dark mt-4 w-100" href="logout.php" type="button">
                    <i class="material-symbols-rounded">logout</i> Logout
                </a>
            </div>
        </div>
    </aside>

    <main class="main-content position-relative max-height-vh-100 h-100 border-radius-lg">
        <nav class="navbar navbar-main navbar-expand-lg px-0 mx-3 shadow-none border-radius-xl" id="navbarBlur" data-scroll="true">
            <div class="container-fluid py-1 px-3">
                <nav aria-label="breadcrumb">
                    <h6 class="font-weight-bolder mb-0 text-primary">Trip Management</h6>
                </nav>
                <div class="collapse navbar-collapse mt-sm-0 mt-2 me-md-0 me-sm-4" id="navbar">
                    <ul class="navbar-nav d-flex align-items-center justify-content-end">
                        <li class="nav-item d-flex align-items-center">
                            <span class="text-sm text-muted me-3">
                                Welcome, <strong><?php echo htmlspecialchars($user['name']); ?></strong>
                            </span>
                        </li>
                        <li class="nav-item d-xl-none ps-3 d-flex align-items-center">
                            <a href="javascript:;" class="nav-link text-body p-0" id="iconNavbarSidenav" data-bs-toggle="sidenav">
                                <div class="sidenav-toggler-inner">
                                    <i class="sidenav-toggler-line"></i>
                                    <i class="sidenav-toggler-line"></i>
                                    <i class="sidenav-toggler-line"></i>
                                </div>
                            </a>
                        </li>
                    </ul>
                </div>
            </div>
        </nav>

        <div class="container-fluid py-2">
            <?php if (isset($success_message)): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <strong>Success!</strong> <?php echo $success_message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if (isset($error_message)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <strong>Error!</strong> <?php echo $error_message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if ($role != 'driver'): ?>
                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header pb-0 p-3">
                                <h6>Create New Trip</h6>
                            </div>
                            <div class="card-body p-3">

                                <form method="POST" action="">
                                    <!-- Compact Form Layout -->
                                    <div class="row">
                                        <div class="col-12">
                                            <div class="card">
                                                <div class="card-body">
                                                    <!-- Row 1: Driver & Vehicle Selection -->
                                                    <div class="row mb-3">
                                                        <div class="col-12">
                                                            <div class="border rounded p-2 bg-light">
                                                                <div class="row">
                                                                    <div class="col-md-6">
                                                                        <div class="form-group mb-2">
                                                                            <label for="vehicle_registration_number" class="form-control-label small mb-1">
                                                                                <i class="material-symbols-rounded text-primary small me-1">directions_car</i>SELECT VEHICLE REGISTRATION NUMBER *
                                                                            </label>
                                                                            <select class="form-control form-control-sm" id="vehicle_registration_number" name="vehicle_registration_number" onchange="populateVehicleDriver()" required>
                                                                                <option value="">Select Vehicle Registration Number</option>
                                                                                <?php
                                                                                $conn = getDBConnection();
                                                                                try {
                                                                                    // Get vehicle registration numbers from users table (drivers)
                                                                                    $stmt = $conn->query("
                                                                                        SELECT u.id as driver_id, u.name as driver_name, u.phone as driver_phone,
                                                                                                u.vehicle_registration_number, u.vehicle_chassis_number,
                                                                                                u.vehicle_engine_number, u.vehicle_fuel_type,
                                                                                                u.assigned_vehicle_id
                                                                                        FROM users u
                                                                                        WHERE u.role = 'driver' AND u.vehicle_registration_number IS NOT NULL
                                                                                        AND u.vehicle_registration_number != ''
                                                                                        ORDER BY u.vehicle_registration_number
                                                                                    ");
                                                                                    $driver_vehicles = $stmt->fetchAll(PDO::FETCH_ASSOC);
                                                                                    foreach ($driver_vehicles as $vehicle): ?>
                                                                                        <option value="<?php echo htmlspecialchars($vehicle['vehicle_registration_number']); ?>"
                                                                                            data-driver-id="<?php echo $vehicle['driver_id']; ?>"
                                                                                            data-driver-name="<?php echo htmlspecialchars($vehicle['driver_name']); ?>"
                                                                                            data-driver-phone="<?php echo htmlspecialchars($vehicle['driver_phone']); ?>"
                                                                                            data-vehicle-reg="<?php echo htmlspecialchars($vehicle['vehicle_registration_number']); ?>"
                                                                                            data-vehicle-id="<?php echo $vehicle['assigned_vehicle_id'] ?? $vehicle['driver_id']; ?>"
                                                                                            data-vehicle-chassis="<?php echo htmlspecialchars($vehicle['vehicle_chassis_number'] ?? ''); ?>">
                                                                                            <?php echo htmlspecialchars($vehicle['vehicle_registration_number'] . ' (' . $vehicle['driver_name'] . ')'); ?>
                                                                                        </option>
                                                                                <?php endforeach;
                                                                                } catch (PDOException $e) {
                                                                                    echo '<option value="" disabled>Error loading vehicles: ' . htmlspecialchars($e->getMessage()) . '</option>';
                                                                                }
                                                                                ?>
                                                                            </select>
                                                                        </div>
                                                                    </div>
                                                                    <div class="col-md-6">
                                                                        <div class="form-group mb-2">
                                                                            <label for="driver_display" class="form-control-label small mb-1">
                                                                                <i class="material-symbols-rounded text-success small me-1">person</i>Driver Details<br><small class="text-muted">Assigned Driver *</small>
                                                                            </label>
                                                                            <input class="form-control form-control-sm" type="text" id="driver_display" readonly placeholder="Select a vehicle to see assigned driver">
                                                                            <input type="hidden" id="vehicle_registration_number_hidden" name="vehicle_registration_number_hidden">
                                                                            <input type="hidden" id="driver_id" name="driver_id">
                                                                            <input type="hidden" id="vehicle_id" name="vehicle_id">
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>

                                                    <!-- Row 2: Trip Details -->
                                                    <div class="row mb-3">
                                                        <div class="col-12">
                                                            <div class="border rounded p-2 bg-light">
                                                                <div class="row">
                                                                    <div class="col-md-3">
                                                                        <div class="form-group mb-2">
                                                                            <label for="trip_type" class="form-control-label small mb-1">
                                                                                <i class="material-symbols-rounded text-warning small me-1">swap_calls</i>Trip Type (Highlight) *
                                                                            </label>
                                                                            <select class="form-control form-control-sm bg-warning text-dark font-weight-bold" id="trip_type" name="trip_type" onchange="updateTripTimes()" required>
                                                                                <option value="pickup" class="bg-light">🚗 Pick Up</option>
                                                                                <option value="drop" class="bg-light">🏠 Drop</option>
                                                                                <option value="pickup_drop" class="bg-light">🔄 Pick Up & Drop</option>
                                                                            </select>
                                                                        </div>
                                                                    </div>
                                                                    <div class="col-md-3">
                                                                        <div class="form-group mb-2">
                                                                            <label for="passenger_count" class="form-control-label small mb-1">
                                                                                <i class="material-symbols-rounded text-success small me-1">group</i>Passengers *
                                                                            </label>
                                                                            <input class="form-control form-control-sm" type="number" id="passenger_count" name="passenger_count" min="1" required>
                                                                        </div>
                                                                    </div>
                                                                    <div class="col-md-3">
                                                                        <div class="form-group mb-2">
                                                                            <label for="client_id" class="form-control-label small mb-1">
                                                                                <i class="material-symbols-rounded text-info small me-1">business</i>Client (Optional)
                                                                            </label>
                                                                            <select class="form-control form-control-sm" id="client_id" name="client_id">
                                                                                <option value="">No Client Assigned</option>
                                                                                <?php foreach ($clients as $client): ?>
                                                                                    <option value="<?php echo $client['id']; ?>"><?php echo htmlspecialchars($client['name']); ?></option>
                                                                                <?php endforeach; ?>
                                                                            </select>
                                                                        </div>
                                                                    </div>
                                                                    <div class="col-md-3">
                                                                        <div class="form-group mb-2">
                                                                            <label for="trip_date" class="form-control-label small mb-1">
                                                                                <i class="material-symbols-rounded text-warning small me-1">calendar_today</i>Trip Date *
                                                                            </label>
                                                                            <input class="form-control form-control-sm" type="date" id="trip_date" name="trip_date" required>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>

                                                    <!-- Row 3: Administrative (Admin only) -->
                                                    <?php if ($role == 'admin'): ?>
                                                        <div class="row mb-3">
                                                            <div class="col-12">
                                                                <div class="border rounded p-2 bg-light">
                                                                    <div class="form-group mb-2">
                                                                        <label for="supervisor_id" class="form-control-label small mb-1">
                                                                            <i class="material-symbols-rounded text-warning small me-1">admin_panel_settings</i>Supervisor *
                                                                        </label>
                                                                        <select class="form-control form-control-sm" id="supervisor_id" name="supervisor_id" required>
                                                                            <option value="">Select Supervisor</option>
                                                                            <?php foreach ($supervisors as $supervisor): ?>
                                                                                <option value="<?php echo $supervisor['id']; ?>"><?php echo htmlspecialchars($supervisor['name']); ?></option>
                                                                            <?php endforeach; ?>
                                                                        </select>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    <?php endif; ?>

                                                    <!-- Row 4: Location Details -->
                                                    <div class="row mb-3">
                                                        <div class="col-12">
                                                            <div class="border rounded p-2 bg-light">
                                                                <div class="row">
                                                                    <div class="col-md-6">
                                                                        <div class="form-group mb-2">
                                                                            <label for="start_location_name" class="form-control-label small mb-1">
                                                                                <i class="material-symbols-rounded text-danger small me-1">location_on</i>Pickup Location (Search by Address) *
                                                                            </label>
                                                                            <input class="form-control form-control-sm ola-uber-input" type="text" id="start_location_name" name="start_location_name" placeholder="Enter pickup location" required autocomplete="off" style="color: #000 !important;">
                                                                            <input type="hidden" id="start_location_lat" name="start_location_lat">
                                                                            <input type="hidden" id="start_location_lng" name="start_location_lng">
                                                                        </div>
                                                                    </div>
                                                                    <div class="col-md-6">
                                                                        <div class="form-group mb-2">
                                                                            <label for="end_location_name" class="form-control-label small mb-1">
                                                                                <i class="material-symbols-rounded text-danger small me-1">location_on</i>Drop Location (Search by Address) *
                                                                            </label>
                                                                            <input class="form-control form-control-sm ola-uber-input" type="text" id="end_location_name" name="end_location_name" placeholder="Enter drop location" required autocomplete="off" style="color: #000 !important;">
                                                                            <input type="hidden" id="end_location_lat" name="end_location_lat">
                                                                            <input type="hidden" id="end_location_lng" name="end_location_lng">
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>

                                                    <!-- Row 5: Time Details -->
                                                    <div class="row mb-3">
                                                        <div class="col-12">
                                                            <div class="border rounded p-2 bg-light">
                                                                <div class="row">
                                                                    <div class="col-md-4">
                                                                        <div class="form-group mb-2">
                                                                            <label for="scheduled_pickup_time" class="form-control-label small mb-1">
                                                                                <i class="material-symbols-rounded text-primary small me-1">schedule</i>Scheduled Pickup Time *
                                                                            </label>
                                                                            <input class="form-control form-control-sm" type="time" id="scheduled_pickup_time" name="scheduled_pickup_time" step="900" required>
                                                                        </div>
                                                                    </div>
                                                                    <div class="col-md-4">
                                                                        <div class="form-group mb-2">
                                                                            <label for="scheduled_drop_time" class="form-control-label small mb-1">
                                                                                <i class="material-symbols-rounded text-danger small me-1">schedule</i>Scheduled Drop Time
                                                                            </label>
                                                                            <input class="form-control form-control-sm" type="time" id="scheduled_drop_time" name="scheduled_drop_time" step="900">
                                                                        </div>
                                                                    </div>
                                                                    <div class="col-md-4">
                                                                        <div class="form-group mb-2">
                                                                            <label for="estimated_duration" class="form-control-label small mb-1">
                                                                                <i class="material-symbols-rounded text-secondary small me-1">timer</i>Duration (hrs) *
                                                                            </label>
                                                                            <input class="form-control form-control-sm" type="number" id="estimated_duration" name="estimated_duration" min="0.5" step="0.5" required>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>

                                                    <!-- Row 6: Passenger Information -->
                                                    <div class="row mb-3">
                                                        <div class="col-12">
                                                            <div class="border rounded p-2 bg-light">
                                                                <h6 class="text-info mb-3"><i class="material-symbols-rounded me-1">group</i>Passenger Information (Required)</h6>
                                                                <div class="row">
                                                                    <div class="col-md-6">
                                                                        <div class="form-group mb-2">
                                                                            <label for="first_passenger_name" class="form-control-label small mb-1">
                                                                                <i class="material-symbols-rounded text-success small me-1">person</i>First Passenger Name *
                                                                            </label>
                                                                            <input class="form-control form-control-sm bg-success text-white font-weight-bold" type="text" id="first_passenger_name" name="first_passenger_name" placeholder="Name of first passenger to pickup" required style="color: white !important;">
                                                                        </div>
                                                                        <div class="form-group mb-2">
                                                                            <label for="first_passenger_phone" class="form-control-label small mb-1">
                                                                                <i class="material-symbols-rounded text-success small me-1">phone</i>First Passenger Phone *
                                                                            </label>
                                                                            <input class="form-control form-control-sm bg-success text-white font-weight-bold" type="tel" id="first_passenger_phone" name="first_passenger_phone" placeholder="Phone number for contact" required style="color: white !important;">
                                                                        </div>
                                                                        <div class="form-group mb-2">
                                                                            <label for="first_passenger_address" class="form-control-label small mb-1">
                                                                                <i class="material-symbols-rounded text-success small me-1">location_on</i> Pickup Address (Search by Address) *
                                                                            </label>
                                                                            <input class="form-control form-control-sm bg-success text-white font-weight-bold ola-uber-input" type="text" id="first_passenger_address" name="first_passenger_address" placeholder="Enter pickup address" required autocomplete="off" style="color: #000 !important;">
                                                                        </div>
                                                                    </div>
                                                                    <div class="col-md-6">
                                                                        <div class="form-group mb-2">
                                                                            <label for="last_passenger_name" class="form-control-label small mb-1">
                                                                                <i class="material-symbols-rounded text-danger small me-1">person</i>Last Passenger Name *
                                                                            </label>
                                                                            <input class="form-control form-control-sm bg-danger text-white font-weight-bold" type="text" id="last_passenger_name" name="last_passenger_name" placeholder="Name of last passenger to drop" required style="color: white !important;">
                                                                        </div>
                                                                        <div class="form-group mb-2">
                                                                            <label for="last_passenger_phone" class="form-control-label small mb-1">
                                                                                <i class="material-symbols-rounded text-danger small me-1">phone</i>Last Passenger Phone *
                                                                            </label>
                                                                            <input class="form-control form-control-sm bg-danger text-white font-weight-bold" type="tel" id="last_passenger_phone" name="last_passenger_phone" placeholder="Phone number for contact" required style="color: white !important;">
                                                                        </div>
                                                                        <div class="form-group mb-2">
                                                                            <label for="last_passenger_address" class="form-control-label small mb-1">
                                                                                <i class="material-symbols-rounded text-danger small me-1">location_on</i> Drop Address (Search by Address) *
                                                                            </label>
                                                                            <input class="form-control form-control-sm bg-danger text-white font-weight-bold ola-uber-input" type="text" id="last_passenger_address" name="last_passenger_address" placeholder="Enter drop address" required autocomplete="off" style="color: #000 !important;">
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>

                                                    <!-- Row 6: Map -->
                                                    <div class="row mb-3">
                                                        <div class="col-12">
                                                            <div class="border rounded p-2 bg-light">
                                                                <label class="form-control-label small mb-2">
                                                                    <i class="material-symbols-rounded text-info small me-1">map</i>Route Preview
                                                                </label>
                                                                <div id="trip_map" style="height: 350px; width: 100%; border-radius: 8px; border: 1px solid #dee2e6;"></div>
                                                                <div id="route-info" class="mt-2 p-2 bg-info text-white rounded" style="display: none;">
                                                                    <small><i class="material-symbols-rounded me-1">directions</i><span id="route-details"></span></small>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>

                                                    <!-- Row 7: Submit -->
                                                    <div class="row">
                                                        <div class="col-12">
                                                            <div class="text-center">
                                                                <button type="submit" name="create_trip" class="btn bg-gradient-dark btn-lg">
                                                                    <i class="material-symbols-rounded me-2">add</i>
                                                                    Create Trip
                                                                </button>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <div class="row mt-4">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header pb-0 p-3">
                            <h6>Trip List</h6>
                        </div>
                        <div class="card-body px-0 pb-2">
                            <div class="table-responsive">
                                <table class="table align-items-center mb-0">
                                    <thead>
                                        <tr>
                                            <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">ID</th>
                                            <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">Driver</th>
                                            <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">Vehicle</th>
                                            <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">Client</th>
                                            <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">Passengers</th>
                                            <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">Trip Date</th>
                                            <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">Status</th>
                                            <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">Start Time</th>
                                            <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">End Time</th>
                                            <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($trips as $trip): ?>
                                            <tr>
                                                <td>
                                                    <p class="text-xs font-weight-bold mb-0"><?php echo $trip['id']; ?></p>
                                                </td>
                                                <td>
                                                    <div class="d-flex px-2 py-1">
                                                        <div>
                                                            <i class="material-symbols-rounded text-secondary">person</i>
                                                        </div>
                                                        <div class="d-flex flex-column justify-content-center">
                                                            <h6 class="mb-0 text-sm"><?php echo htmlspecialchars($trip['driver_name']); ?></h6>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="d-flex px-2 py-1">
                                                        <div>
                                                            <i class="material-symbols-rounded text-secondary">directions_car</i>
                                                        </div>
                                                        <div class="d-flex flex-column justify-content-center">
                                                            <?php
                                                            $vehicle_info = 'N/A';
                                                            if (!empty($trip['make']) || !empty($trip['model']) || !empty($trip['license_plate'])) {
                                                                $vehicle_info = trim(($trip['make'] ?? '') . ' ' . ($trip['model'] ?? ''));
                                                                if (!empty($trip['license_plate'])) {
                                                                    $vehicle_info .= ' (' . $trip['license_plate'] . ')';
                                                                }
                                                                $vehicle_info = trim($vehicle_info);
                                                            }
                                                            ?>
                                                            <h6 class="mb-0 text-sm"><?php echo htmlspecialchars($vehicle_info); ?></h6>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="d-flex px-2 py-1">
                                                        <div>
                                                            <i class="material-symbols-rounded text-secondary">business</i>
                                                        </div>
                                                        <div class="d-flex flex-column justify-content-center">
                                                            <h6 class="mb-0 text-sm"><?php echo htmlspecialchars($trip['client_name'] ?? 'N/A'); ?></h6>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td>
                                                    <p class="text-xs font-weight-bold mb-0"><?php echo $trip['passenger_count']; ?></p>
                                                </td>
                                                <td>
                                                    <p class="text-xs font-weight-bold mb-0"><?php
                                                                                                $trip_date = $trip['trip_date'] ?? null;
                                                                                                if ($trip_date && $trip_date !== '0000-00-00' && $trip_date !== '' && strtotime($trip_date)) {
                                                                                                    echo date('d/m/Y', strtotime($trip_date));
                                                                                                } else {
                                                                                                    echo 'N/A';
                                                                                                }
                                                                                                ?></p>
                                                </td>
                                                <td>
                                                    <span class="badge badge-sm bg-gradient-<?php echo $trip['status'] == 'completed' ? 'success' : ($trip['status'] == 'in_progress' ? 'warning' : 'secondary'); ?>">
                                                        <?php echo ucfirst(str_replace('_', ' ', $trip['status'] ?? 'pending')); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <p class="text-xs font-weight-bold mb-0"><?php echo $trip['start_time'] ? date('H:i', strtotime($trip['start_time'])) : 'N/A'; ?></p>
                                                </td>
                                                <td>
                                                    <p class="text-xs font-weight-bold mb-0"><?php echo $trip['end_time'] ? date('H:i', strtotime($trip['end_time'])) : 'N/A'; ?></p>
                                                </td>
                                                <td class="align-middle">
                                                    <div class="d-flex flex-column">
                                                        <a href="trip_details.php?id=<?php echo $trip['id']; ?>" class="text-secondary font-weight-bold text-xs mb-1" data-toggle="tooltip" data-original-title="View Details">
                                                            View
                                                        </a>
                                                        <?php if ($trip['status'] == 'scheduled'): ?>
                                                            <a href="start_trip.php?id=<?php echo $trip['id']; ?>" class="text-success font-weight-bold text-xs mb-1" data-toggle="tooltip" data-original-title="Start Trip">
                                                                Start
                                                            </a>
                                                        <?php endif; ?>
                                                        <?php if ($trip['status'] == 'in_progress'): ?>
                                                            <a href="end_trip.php?id=<?php echo $trip['id']; ?>" class="text-danger font-weight-bold text-xs" data-toggle="tooltip" data-original-title="End Trip">
                                                                End
                                                            </a>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
    </main>

    <script src="./assets/js/core/popper.min.js"></script>
    <script src="./assets/js/core/bootstrap.min.js"></script>
    <script src="./assets/js/plugins/perfect-scrollbar.min.js"></script>
    <script src="./assets/js/plugins/smooth-scrollbar.min.js"></script>
    <script src="./assets/js/plugins/chartjs.min.js"></script>

    <script>
        let map;
        let marker;
        let geocoder;
        let directionsService;
        let directionsRenderer;
        let startAutocomplete;
        let endAutocomplete;
        let firstPassengerAutocomplete;
        let lastPassengerAutocomplete;

        // Mobile detection
        const isMobile = /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent) || window.innerWidth <= 768;

        function initGoogleMaps() {
            // Default center (India)
            const defaultLocation = {
                lat: 20.5937,
                lng: 78.9629
            };

            map = new google.maps.Map(document.getElementById('trip_map'), {
                zoom: isMobile ? 4 : 5,
                center: defaultLocation,
                mapTypeControl: !isMobile,
                streetViewControl: !isMobile,
                fullscreenControl: !isMobile,
                gestureHandling: isMobile ? 'cooperative' : 'auto',
                zoomControl: true,
                zoomControlOptions: {
                    position: google.maps.ControlPosition.RIGHT_CENTER
                }
            });

            geocoder = new google.maps.Geocoder();

            // Initialize Directions service and renderer
            directionsService = new google.maps.DirectionsService();
            directionsRenderer = new google.maps.DirectionsRenderer({
                map: map,
                suppressMarkers: true, // Suppress default markers since we add custom ones
                polylineOptions: {
                    strokeColor: '#1976d2',
                    strokeWeight: isMobile ? 4 : 5,
                    strokeOpacity: 0.8
                },
                preserveViewport: false // Allow map to adjust bounds to show the route
            });

            // Add click listener to place marker
            map.addListener('click', function(event) {
                placeMarker(event.latLng);
            });

            // Initialize Places Autocomplete
            const startInput = document.getElementById('start_location_name');
            const endInput = document.getElementById('end_location_name');
            const firstPassengerInput = document.getElementById('first_passenger_address');
            const lastPassengerInput = document.getElementById('last_passenger_address');

            if (startInput) {
                startAutocomplete = new google.maps.places.Autocomplete(startInput, {
                    componentRestrictions: {
                        country: 'in'
                    },
                    fields: ['formatted_address', 'geometry', 'place_id']
                });

                startAutocomplete.addListener('place_changed', function() {
                    const place = startAutocomplete.getPlace();
                    if (!place.geometry) {
                        console.log("No details available for input: '" + place.name + "'");
                        return;
                    }

                    // Store coordinates for route calculation
                    document.getElementById('start_location_lat').value = place.geometry.location.lat();
                    document.getElementById('start_location_lng').value = place.geometry.location.lng();

                    calculateRoute();
                });
            }

            if (endInput) {
                endAutocomplete = new google.maps.places.Autocomplete(endInput, {
                    componentRestrictions: {
                        country: 'in'
                    },
                    fields: ['formatted_address', 'geometry', 'place_id']
                });

                endAutocomplete.addListener('place_changed', function() {
                    const place = endAutocomplete.getPlace();
                    if (!place.geometry) {
                        console.log("No details available for input: '" + place.name + "'");
                        return;
                    }

                    // Store coordinates for route calculation
                    document.getElementById('end_location_lat').value = place.geometry.location.lat();
                    document.getElementById('end_location_lng').value = place.geometry.location.lng();

                    calculateRoute();
                });
            }

            if (firstPassengerInput) {
                firstPassengerAutocomplete = new google.maps.places.Autocomplete(firstPassengerInput, {
                    componentRestrictions: {
                        country: 'in'
                    },
                    fields: ['formatted_address', 'geometry', 'place_id']
                });

                firstPassengerAutocomplete.addListener('place_changed', function() {
                    const place = firstPassengerAutocomplete.getPlace();
                    if (place.geometry) {
                        // Store coordinates if needed for passenger pickup
                        console.log('First passenger location:', place.geometry.location.lat(), place.geometry.location.lng());
                    }
                });
            }

            if (lastPassengerInput) {
                lastPassengerAutocomplete = new google.maps.places.Autocomplete(lastPassengerInput, {
                    componentRestrictions: {
                        country: 'in'
                    },
                    fields: ['formatted_address', 'geometry', 'place_id']
                });

                lastPassengerAutocomplete.addListener('place_changed', function() {
                    const place = lastPassengerAutocomplete.getPlace();
                    if (place.geometry) {
                        // Store coordinates if needed for passenger drop
                        console.log('Last passenger location:', place.geometry.location.lat(), place.geometry.location.lng());
                    }
                });
            }

            // Add blur event listeners for manual address geocoding
            document.getElementById('start_location_name').addEventListener('blur', function() {
                geocodeAddress('start_location_name', 'start_location_lat', 'start_location_lng');
            });

            document.getElementById('end_location_name').addEventListener('blur', function() {
                geocodeAddress('end_location_name', 'end_location_lat', 'end_location_lng');
            });
        }

        function calculateRoute() {
            const startLat = document.getElementById('start_location_lat').value;
            const startLng = document.getElementById('start_location_lng').value;
            const endLat = document.getElementById('end_location_lat').value;
            const endLng = document.getElementById('end_location_lng').value;

            console.log('Calculating route with coordinates:', {
                startLat,
                startLng,
                endLat,
                endLng
            });

            if (!startLat || !startLng || !endLat || !endLng) {
                console.log('Missing coordinates, clearing directions');
                if (directionsRenderer) {
                    directionsRenderer.setDirections({
                        routes: []
                    });
                }
                // Clear route info
                const routeInfo = document.getElementById('route-info');
                if (routeInfo) {
                    routeInfo.style.display = 'none';
                }
                return;
            }

            const startLatNum = parseFloat(startLat);
            const startLngNum = parseFloat(startLng);
            const endLatNum = parseFloat(endLat);
            const endLngNum = parseFloat(endLng);

            if (isNaN(startLatNum) || isNaN(startLngNum) || isNaN(endLatNum) || isNaN(endLngNum)) {
                console.error('Invalid coordinate values');
                return;
            }

            const request = {
                origin: new google.maps.LatLng(startLatNum, startLngNum),
                destination: new google.maps.LatLng(endLatNum, endLngNum),
                travelMode: google.maps.TravelMode.DRIVING,
                unitSystem: google.maps.UnitSystem.METRIC,
                avoidTolls: false,
                avoidHighways: false,
                avoidFerries: false,
                optimizeWaypoints: false,
                provideRouteAlternatives: false
            };

            console.log('Sending directions request:', request);

            directionsService.route(request, function(response, status) {
                console.log('Directions response status:', status);

                if (status === google.maps.DirectionsStatus.OK) {
                    console.log('Route calculated successfully');
                    directionsRenderer.setDirections(response);

                    // Calculate estimated duration and distance
                    const route = response.routes[0];
                    const duration = route.legs[0].duration.value; // in seconds
                    const distance = route.legs[0].distance.value; // in meters
                    const distanceKm = (distance / 1000).toFixed(1); // convert to km
                    const hours = Math.ceil(duration / 3600); // round up to nearest hour

                    // Update estimated duration field
                    const durationField = document.getElementById('estimated_duration');
                    if (durationField) {
                        durationField.value = Math.max(hours, 0.5);
                    }

                    // Display route information
                    displayRouteInfo(distanceKm, Math.round(duration / 60)); // duration in minutes

                    // Fit map to route bounds
                    const bounds = new google.maps.LatLngBounds();
                    bounds.extend(route.legs[0].start_location);
                    bounds.extend(route.legs[0].end_location);
                    map.fitBounds(bounds);

                    // Add markers for start and end points
                    addRouteMarkers(startLatNum, startLngNum, endLatNum, endLngNum);
                } else {
                    console.error('Directions request failed:', status);
                    console.error('Request details:', request);

                    // Clear any existing directions
                    if (directionsRenderer) {
                        directionsRenderer.setDirections({
                            routes: []
                        });
                    }

                    // Still center map on the points even if route fails
                    const bounds = new google.maps.LatLngBounds();
                    bounds.extend(new google.maps.LatLng(startLatNum, startLngNum));
                    bounds.extend(new google.maps.LatLng(endLatNum, endLngNum));
                    map.fitBounds(bounds);

                    // Add markers even if route fails
                    addRouteMarkers(startLatNum, startLngNum, endLatNum, endLngNum);

                    // Show error message to user
                    console.error('Route calculation failed. Status:', status);
                    // Don't show alert for now, just log the error
                    // alert('Unable to calculate route. Please check the addresses and try again.');
                }
            });
        }

        function displayRouteInfo(distanceKm, durationMinutes) {
            const routeInfo = document.getElementById('route-info');
            const routeDetails = document.getElementById('route-details');

            if (routeDetails && routeInfo) {
                routeDetails.textContent = `Distance: ${distanceKm} km | Duration: ${durationMinutes} mins`;
                routeInfo.style.display = 'block';
            }
        }

        function addRouteMarkers(startLat, startLng, endLat, endLng) {
            // Clear existing markers
            if (window.routeMarkers) {
                window.routeMarkers.forEach(marker => marker.setMap(null));
            }
            window.routeMarkers = [];

            // Add start marker
            const startMarker = new google.maps.Marker({
                position: {
                    lat: startLat,
                    lng: startLng
                },
                map: map,
                title: 'Pickup Location',
                icon: {
                    url: 'data:image/svg+xml;charset=UTF-8,' + encodeURIComponent(`
                        <svg width="40" height="40" viewBox="0 0 40 40" xmlns="http://www.w3.org/2000/svg">
                            <circle cx="20" cy="20" r="18" fill="#4CAF50" stroke="white" stroke-width="3"/>
                            <text x="20" y="25" text-anchor="middle" fill="white" font-family="Arial" font-size="12" font-weight="bold">P</text>
                        </svg>
                    `),
                    scaledSize: new google.maps.Size(40, 40)
                },
                zIndex: 1000
            });

            // Add end marker
            const endMarker = new google.maps.Marker({
                position: {
                    lat: endLat,
                    lng: endLng
                },
                map: map,
                title: 'Drop Location',
                icon: {
                    url: 'data:image/svg+xml;charset=UTF-8,' + encodeURIComponent(`
                        <svg width="40" height="40" viewBox="0 0 40 40" xmlns="http://www.w3.org/2000/svg">
                            <circle cx="20" cy="20" r="18" fill="#F44336" stroke="white" stroke-width="3"/>
                            <text x="20" y="25" text-anchor="middle" fill="white" font-family="Arial" font-size="12" font-weight="bold">D</text>
                        </svg>
                    `),
                    scaledSize: new google.maps.Size(40, 40)
                },
                zIndex: 1000
            });

            window.routeMarkers = [startMarker, endMarker];
        }

        function searchAddress() {
            const address = document.getElementById('address_search').value;
            if (!address) {
                alert('Please enter an address');
                return;
            }

            geocoder.geocode({
                address: address
            }, function(results, status) {
                if (status === 'OK') {
                    const location = results[0].geometry.location;
                    map.setCenter(location);
                    map.setZoom(15);
                    placeMarker(location);
                    fillAddressFields(results[0]);
                } else {
                    alert('Address not found: ' + status);
                }
            });
        }

        function placeMarker(location) {
            // Remove existing marker
            if (marker) {
                marker.setMap(null);
            }

            // Create new marker
            marker = new google.maps.Marker({
                position: location,
                map: map,
                draggable: true
            });

            // Update form fields
            document.getElementById('latitude').value = location.lat();
            document.getElementById('longitude').value = location.lng();

            // Add drag listener to update coordinates
            marker.addListener('dragend', function(event) {
                document.getElementById('latitude').value = event.latLng.lat();
                document.getElementById('longitude').value = event.latLng.lng();
            });
        }

        function geocodeAddress(inputId, latId, lngId) {
            const address = document.getElementById(inputId).value;
            if (!address.trim()) {
                document.getElementById(latId).value = '';
                document.getElementById(lngId).value = '';
                calculateRoute();
                return;
            }

            geocoder.geocode({
                address: address
            }, function(results, status) {
                if (status === 'OK' && results[0]) {
                    const location = results[0].geometry.location;
                    document.getElementById(latId).value = location.lat();
                    document.getElementById(lngId).value = location.lng();
                    calculateRoute();
                } else {
                    console.log('Geocode was not successful for the following reason: ' + status);
                    document.getElementById(latId).value = '';
                    document.getElementById(lngId).value = '';
                    calculateRoute();
                }
            });
        }

        function fillAddressFields(place) {
            // Extract address components
            const addressComponents = place.address_components;
            let streetNumber = '';
            let streetName = '';
            let city = '';
            let state = '';
            let postalCode = '';

            addressComponents.forEach(component => {
                const types = component.types;
                if (types.includes('street_number')) {
                    streetNumber = component.long_name;
                }
                if (types.includes('route')) {
                    streetName = component.long_name;
                }
                if (types.includes('locality') || types.includes('administrative_area_level_2')) {
                    city = component.long_name;
                }
                if (types.includes('administrative_area_level_1')) {
                    state = component.long_name;
                }
                if (types.includes('postal_code')) {
                    postalCode = component.long_name;
                }
            });

            // Fill form fields
            const fullAddress = [streetNumber, streetName].filter(Boolean).join(' ') || place.formatted_address.split(',')[0];
            document.getElementById('address').value = fullAddress;
            document.getElementById('city').value = city;
            document.getElementById('state').value = state;
            document.getElementById('pincode').value = postalCode;
        }

        function debounce(func, wait) {
            let timeout;
            return function executedFunction(...args) {
                const later = () => {
                    clearTimeout(timeout);
                    func(...args);
                };
                clearTimeout(timeout);
                timeout = setTimeout(later, wait);
            };
        }

        // Add input event listeners for manual address entry
        if (startInput) {
            startInput.addEventListener('input', debounce(function() {
                if (!this.value.trim()) {
                    document.getElementById('start_location_lat').value = '';
                    document.getElementById('start_location_lng').value = '';
                    calculateRoute();
                }
            }, 500));
        }

        if (endInput) {
            endInput.addEventListener('input', debounce(function() {
                if (!this.value.trim()) {
                    document.getElementById('end_location_lat').value = '';
                    document.getElementById('end_location_lng').value = '';
                    calculateRoute();
                }
            }, 500));
        }


        function populateVehicleDriver() {
            const vehicleSelect = document.getElementById('vehicle_registration_number');
            const selectedOption = vehicleSelect.options[vehicleSelect.selectedIndex];
            const driverId = selectedOption.getAttribute('data-driver-id');
            const driverName = selectedOption.getAttribute('data-driver-name');
            const driverPhone = selectedOption.getAttribute('data-driver-phone');
            const vehicleReg = selectedOption.getAttribute('data-vehicle-reg');

            if (driverId && driverName) {
                document.getElementById('driver_display').value = `${driverName} (${driverPhone}) - ${vehicleReg}`;
                document.getElementById('vehicle_registration_number_hidden').value = vehicleReg;
                document.getElementById('driver_id').value = driverId;
                document.getElementById('vehicle_id').value = selectedOption.getAttribute('data-vehicle-id') || driverId;
            } else {
                document.getElementById('driver_display').value = 'No driver assigned';
                document.getElementById('vehicle_registration_number_hidden').value = '';
                document.getElementById('driver_id').value = '';
                document.getElementById('vehicle_id').value = '';
            }
        }

        function updateTripTimes() {
            const tripType = document.getElementById('trip_type').value;
            const dropTimeInput = document.getElementById('scheduled_drop_time');

            if (tripType === 'pickup') {
                dropTimeInput.required = false;
                dropTimeInput.value = '';
            } else {
                dropTimeInput.required = true;
            }
        }

        // Initialize when page loads
        document.addEventListener('DOMContentLoaded', function() {
            // Set default trip date to today
            const today = new Date().toISOString().split('T')[0];
            document.getElementById('trip_date').value = today;

            // Mobile-specific enhancements
            if (isMobile) {
                // Prevent zoom on input focus for iOS
                const inputs = document.querySelectorAll('input[type="text"], input[type="tel"], input[type="email"], input[type="number"], select, textarea');
                inputs.forEach(input => {
                    input.addEventListener('focus', function() {
                        this.setAttribute('inputmode', 'text');
                        this.style.fontSize = '16px'; // Prevent zoom on iOS
                    });
                    input.addEventListener('blur', function() {
                        this.style.fontSize = '';
                    });
                });

                // Improve touch interactions
                const buttons = document.querySelectorAll('.btn');
                buttons.forEach(btn => {
                    btn.addEventListener('touchstart', function() {
                        this.style.transform = 'scale(0.98)';
                    });
                    btn.addEventListener('touchend', function() {
                        this.style.transform = '';
                    });
                });

                // Handle orientation changes
                window.addEventListener('orientationchange', function() {
                    setTimeout(function() {
                        if (map) {
                            google.maps.event.trigger(map, 'resize');
                            // Recalculate route if it exists
                            if (directionsRenderer && directionsRenderer.getDirections() && directionsRenderer.getDirections().routes && directionsRenderer.getDirections().routes[0]) {
                                const bounds = new google.maps.LatLngBounds();
                                directionsRenderer.getDirections().routes[0].legs.forEach(leg => {
                                    bounds.extend(leg.start_location);
                                    bounds.extend(leg.end_location);
                                });
                                map.fitBounds(bounds);
                            }
                        }
                    }, 500);
                });

                // Improve scrolling on mobile
                document.body.style.overscrollBehavior = 'none';
                document.documentElement.style.overscrollBehavior = 'none';
            }

            // Handle window resize for responsive map
            window.addEventListener('resize', function() {
                if (map) {
                    setTimeout(function() {
                        google.maps.event.trigger(map, 'resize');
                        if (directionsRenderer && directionsRenderer.getDirections() && directionsRenderer.getDirections().routes && directionsRenderer.getDirections().routes[0]) {
                            const bounds = new google.maps.LatLngBounds();
                            directionsRenderer.getDirections().routes[0].legs.forEach(leg => {
                                bounds.extend(leg.start_location);
                                bounds.extend(leg.end_location);
                            });
                            map.fitBounds(bounds);
                        }
                    }, 300);
                }
            });
        });

        var win = navigator.platform.indexOf('Win') > -1;
        if (win && document.querySelector('#sidenav-scrollbar')) {
            var options = {
                damping: '0.5'
            }
            Scrollbar.init(document.querySelector('#sidenav-scrollbar'), options);
        }
    </script>
    <script src="./assets/js/material-dashboard.min.js?v=3.2.0"></script>
</body>

</html>