<?php
session_start();
require_once 'includes/config.php';
require_once 'includes/functions.php';

// Redirect to login if not authenticated
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Get dashboard data
$user = getUserById($_SESSION['user_id']);
$role = $user['role'];

$conn = getDBConnection();

switch ($role) {
    case 'admin':
        $totalTrips = getTotalTrips();
        $totalDrivers = getTotalDrivers();
        $totalCars = $conn->query("SELECT COUNT(*) FROM vehicles WHERE driver_id IS NOT NULL")->fetchColumn(); // Registered vehicles (assigned to drivers)
        $totalRevenue = getTotalRevenue();
        $activeTrips = $conn->query("SELECT COUNT(*) FROM trips WHERE status = 'in_progress'")->fetchColumn();
        $todayTrips = $conn->query("SELECT COUNT(*) FROM trips WHERE DATE(created_at) = CURDATE()")->fetchColumn();
        $todayRevenue = $conn->query("SELECT SUM(amount) FROM billing b JOIN trips t ON b.trip_id = t.id WHERE DATE(t.created_at) = CURDATE()")->fetchColumn() ?: 0;

        // Recent trips
        $recentTrips = $conn->query("SELECT t.*, u.name as driver_name, v.make, v.model FROM trips t LEFT JOIN users u ON t.driver_id = u.id LEFT JOIN vehicles v ON t.vehicle_id = v.id ORDER BY t.created_at DESC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);

        // Active drivers with current location
        try {
            $activeDrivers = $conn->query("SELECT u.name, u.phone, t.start_location_lat, t.start_location_lng, t.status as trip_status, dl.latitude, dl.longitude, dl.updated_at FROM users u LEFT JOIN trips t ON u.id = t.driver_id AND t.status = 'in_progress' LEFT JOIN driver_locations dl ON u.id = dl.driver_id WHERE u.role = 'driver' AND (t.status = 'in_progress' OR dl.latitude IS NOT NULL) ORDER BY dl.updated_at DESC, u.name LIMIT 10")->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            // Fallback if driver_locations table doesn't exist yet
            $activeDrivers = $conn->query("SELECT u.name, u.phone, t.start_location_lat, t.start_location_lng, t.status as trip_status FROM users u LEFT JOIN trips t ON u.id = t.driver_id AND t.status = 'in_progress' WHERE u.role = 'driver' ORDER BY u.name LIMIT 10")->fetchAll(PDO::FETCH_ASSOC);
        }

        // Add status field to drivers for map display
        foreach ($activeDrivers as &$driver) {
            $driver['status'] = $driver['trip_status'] ?: 'available';
        }

        break;
    case 'supervisor':
        $supervisorTrips = getSupervisorTrips($_SESSION['user_id']);
        $supervisorDrivers = getSupervisorDrivers($_SESSION['user_id']);
        $activeTrips = $conn->prepare("SELECT COUNT(*) FROM trips WHERE supervisor_id = ? AND status = 'in_progress'");
        $activeTrips->execute([$_SESSION['user_id']]);
        $activeTrips = $activeTrips->fetchColumn();

        // Supervisor's drivers with current status and location
        try {
            $stmt = $conn->prepare("SELECT u.name, u.phone, t.start_location_lat, t.start_location_lng, t.status as trip_status, dl.latitude, dl.longitude, dl.updated_at FROM users u LEFT JOIN trips t ON u.id = t.driver_id AND t.status = 'in_progress' LEFT JOIN driver_locations dl ON u.id = dl.driver_id WHERE u.role = 'driver' AND u.supervisor_id = ? ORDER BY dl.updated_at DESC, u.name");
            $stmt->execute([$_SESSION['user_id']]);
            $activeDrivers = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            // Fallback if driver_locations table doesn't exist
            $stmt = $conn->prepare("SELECT u.name, u.phone, t.start_location_lat, t.start_location_lng, t.status as trip_status FROM users u LEFT JOIN trips t ON u.id = t.driver_id AND t.status = 'in_progress' WHERE u.role = 'driver' AND u.supervisor_id = ? ORDER BY u.name");
            $stmt->execute([$_SESSION['user_id']]);
            $activeDrivers = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        break;
    case 'driver':
        $driverTrips = getDriverTrips($_SESSION['user_id']);
        $driverAttendance = getDriverAttendance($_SESSION['user_id']);
        $currentTrip = $conn->prepare("SELECT * FROM trips WHERE driver_id = ? AND status = 'in_progress' ORDER BY created_at DESC LIMIT 1");
        $currentTrip->execute([$_SESSION['user_id']]);
        $currentTrip = $currentTrip->fetch(PDO::FETCH_ASSOC);
        break;
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, user-scalable=yes">
    <link rel="apple-touch-icon" sizes="76x76" href="./assets/img/apple-icon.png">
    <link rel="icon" type="image/png" href="./assets/images/logo.png">
    <title>Shreeji - Employee Transportation System</title>
    <link rel="stylesheet" type="text/css" href="https://fonts.googleapis.com/css?family=Inter:300,400,500,600,700,900" />
    <link href="./assets/css/nucleo-icons.css" rel="stylesheet" />
    <link href="./assets/css/nucleo-svg.css" rel="stylesheet" />
    <script src="https://kit.fontawesome.com/42d5adcbca.js" crossorigin="anonymous"></script>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded:opsz,wght,FILL,GRAD@24,400,0,0" />
    <link id="pagestyle" href="./assets/material-dashboard.css?v=3.2.0" rel="stylesheet" />
    <link href="./assets/css/style.css" rel="stylesheet" />
    <style>
        /* Custom Modern Styling */
        :root {
            --primary-color: #1976D2;
            --secondary-color: #4CAF50;
            --tertiary-color: #FF9800;
            --error-color: #F44336;
            --surface-color: #FFFFFF;
            --background-color: #FAFAFA;
        }

        .modern-card {
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.9) 0%, rgba(255, 255, 255, 0.95) 100%);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
        }

        .modern-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 40px rgba(0, 0, 0, 0.15);
        }

        /* Enhanced Mobile Navigation */
        @media (max-width: 768px) {
            .sidenav {
                transform: translateX(-100%);
                width: 280px;
                position: fixed;
                top: 0;
                left: 0;
                height: 100vh;
                z-index: 1050;
                background: white;
                box-shadow: 0 0 20px rgba(0, 0, 0, 0.1);
                transition: transform 0.3s ease;
            }

            .sidenav.show {
                transform: translateX(0);
            }

            .main-content {
                margin-left: 0 !important;
                width: 100%;
            }

            /* Mobile overlay */
            .sidenav-overlay {
                display: none;
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background: rgba(0, 0, 0, 0.5);
                z-index: 1040;
            }

            .sidenav.show+.sidenav-overlay {
                display: block;
            }

            /* Mobile menu toggle */
            .mobile-menu-toggle {
                display: block !important;
                position: fixed;
                top: 15px;
                left: 15px;
                z-index: 1060;
                background: white;
                border: none;
                border-radius: 8px;
                padding: 10px;
                box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
                cursor: pointer;
            }

            .mobile-menu-toggle i {
                font-size: 20px;
                color: #666;
            }

            /* Hide desktop navbar on mobile */
            .navbar-main {
                display: none;
            }
        }

        .gradient-primary {
            background: linear-gradient(135deg, var(--primary-color) 0%, #0D47A1 100%);
        }

        .gradient-secondary {
            background: linear-gradient(135deg, var(--secondary-color) 0%, #2E7D32 100%);
        }

        .gradient-tertiary {
            background: linear-gradient(135deg, var(--tertiary-color) 0%, #E65100 100%);
        }

        .gradient-error {
            background: linear-gradient(135deg, var(--error-color) 0%, #B71C1C 100%);
        }

        .welcome-section {
            background: linear-gradient(135deg, rgba(25, 118, 210, 0.1) 0%, rgba(76, 175, 80, 0.05) 100%);
            border-radius: 16px;
            padding: 2rem;
            margin-bottom: 2rem;
        }

        .stat-card-modern {
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.95) 0%, rgba(255, 255, 255, 0.9) 100%);
            border-radius: 16px;
            padding: 1.5rem;
            height: 100%;
            transition: all 0.3s ease;
            border: 1px solid rgba(0, 0, 0, 0.05);
        }

        .stat-card-modern:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 32px rgba(0, 0, 0, 0.1);
        }

        .stat-icon-modern {
            width: 60px;
            height: 60px;
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            color: white;
            margin-left: auto;
        }

        .modern-table {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
        }

        .modern-avatar {
            width: 40px;
            height: 40px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
        }

        .status-badge-modern {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
        }

        .map-container-modern {
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
        }

        .sidebar-modern .nav-link {
            border-radius: 12px;
            margin: 4px 8px;
            transition: all 0.3s ease;
        }

        .sidebar-modern .nav-link:hover {
            background: rgba(25, 118, 210, 0.1);
            transform: translateX(4px);
        }

        .sidebar-modern .nav-link.active {
            background: linear-gradient(135deg, var(--primary-color) 0%, #0D47A1 100%);
            color: white;
            box-shadow: 0 4px 16px rgba(25, 118, 210, 0.3);
        }
    </style>
    <script src="https://maps.googleapis.com/maps/api/js?key=AIzaSyAUnYgTLEnIHwXCE1A98gAGFXBrvQEv8aI&libraries=places,geometry"></script>
</head>

<body class="g-sidenav-show bg-gray-100">
    <aside class="sidenav navbar navbar-vertical navbar-expand-xs border-radius-lg fixed-start ms-2 bg-white my-2 sidebar-modern" id="sidenav-main">
        <div class="sidenav-header">
            <i class="fas fa-times p-3 cursor-pointer text-dark opacity-5 position-absolute end-0 top-0 d-none d-xl-none" aria-hidden="true" id="iconSidenav"></i>
            <a class="navbar-brand px-4 py-3 m-0" href="#">
                <img src="C:\xampp\htdocs\EmployeeTransportationSystem\web\assets\images\logo.png" class="navbar-brand-img" width="26" height="26" alt="Shreeji Link Logo">
                <span class="ms-1 text-sm text-dark">Shreeji Link</span>
            </a>
        </div>
        <hr class="horizontal dark mt-0 mb-2">
        <div class="collapse navbar-collapse w-auto" id="sidenav-collapse-main">
            <ul class="navbar-nav">
                <li class="nav-item">
                    <a class="nav-link active bg-gradient-dark text-white" href="index.php">
                        <i class="material-symbols-rounded opacity-5">dashboard</i>
                        <span class="nav-link-text ms-1">Dashboard</span>
                    </a>
                </li>
                <?php if ($role == 'admin' || $role == 'supervisor'): ?>
                    <li class="nav-item">
                        <a class="nav-link text-dark" href="trips.php">
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
                        <a class="nav-link text-dark" href="clients.php">
                            <i class="material-symbols-rounded opacity-5">business</i>
                            <span class="nav-link-text ms-1">Clients</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link text-dark" href="vehicles.php">
                            <i class="material-symbols-rounded opacity-5">directions_car</i>
                            <span class="nav-link-text ms-1">Vehicles</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link text-dark" href="geolocation_tracker.php">
                            <i class="material-symbols-rounded opacity-5">location_on</i>
                            <span class="nav-link-text ms-1">Live Tracking</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link text-dark" href="supervisor_attendance.php">
                            <i class="material-symbols-rounded opacity-5">event_available</i>
                            <span class="nav-link-text ms-1">Attendance</span>
                        </a>
                    </li>
                    <?php if ($role == 'admin'): ?>
                        <li class="nav-item">
                            <a class="nav-link text-dark" href="supervisors.php">
                                <i class="material-symbols-rounded opacity-5">supervisor_account</i>
                                <span class="nav-link-text ms-1">Supervisors</span>
                            </a>
                        </li>
                    <?php endif; ?>
                <?php endif; ?>
                <?php if ($role == 'driver'): ?>
                    <li class="nav-item">
                        <a class="nav-link text-dark" href="trips.php">
                            <i class="material-symbols-rounded opacity-5">route</i>
                            <span class="nav-link-text ms-1">My Trips</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link text-dark" href="driver_trip_validation.php">
                            <i class="material-symbols-rounded opacity-5">verified</i>
                            <span class="nav-link-text ms-1">Trip Validation</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link text-dark" href="geolocation_tracker.php">
                            <i class="material-symbols-rounded opacity-5">location_on</i>
                            <span class="nav-link-text ms-1">Live Tracking</span>
                        </a>
                    </li>
                <?php endif; ?>
                <?php if ($role == 'admin'): ?>
                    <li class="nav-item">
                        <a class="nav-link text-dark" href="branches.php">
                            <i class="material-symbols-rounded opacity-5">business</i>
                            <span class="nav-link-text ms-1">Branches</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link text-dark" href="reports.php">
                            <i class="material-symbols-rounded opacity-5">analytics</i>
                            <span class="nav-link-text ms-1">Reports</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link text-dark" href="billing.php">
                            <i class="material-symbols-rounded opacity-5">receipt_long</i>
                            <span class="nav-link-text ms-1">Driver Billing</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link text-dark" href="whatsapp_admin.php">
                            <i class="material-symbols-rounded opacity-5">chat</i>
                            <span class="nav-link-text ms-1">WhatsApp Admin</span>
                        </a>
                    </li>
                    <!-- Client billing removed - will be implemented later -->
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

    <!-- Mobile Menu Toggle -->
    <button class="mobile-menu-toggle d-lg-none" id="mobileMenuToggle">
        <i class="material-symbols-rounded">menu</i>
    </button>

    <!-- Mobile Overlay -->
    <div class="sidenav-overlay d-lg-none" id="sidenavOverlay"></div>

    <main class="main-content position-relative max-height-vh-100 h-100 border-radius-lg">
        <nav class="navbar navbar-main navbar-expand-lg px-0 mx-3 shadow-none border-radius-xl d-none d-lg-block" id="navbarBlur" data-scroll="true">
            <div class="container-fluid py-1 px-3">
                <nav aria-label="breadcrumb">
                    <h6 class="font-weight-bolder mb-0 text-primary">Dashboard Overview</h6>
                </nav>
                <div class="collapse navbar-collapse mt-sm-0 mt-2 me-md-0 me-sm-4" id="navbar">
                    <ul class="navbar-nav d-flex align-items-center justify-content-end">
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
            <!-- Welcome Section -->
            <div class="row">
                <div class="col-12">
                    <div class="welcome-section modern-card">
                        <div class="row align-items-center">
                            <div class="col-md-8">
                                <h2 class="mb-2 text-primary font-weight-bold">Welcome back, <?php echo htmlspecialchars($user['name']); ?>!</h2>
                                <p class="text-muted mb-0 h5">
                                    <i class="material-symbols-rounded text-primary me-2">person</i>
                                    <?php echo ucfirst($role); ?> Dashboard
                                </p>
                                <p class="text-muted mt-2 mb-0">
                                    <i class="material-symbols-rounded text-secondary me-1">info</i>
                                    Manage your transportation operations efficiently
                                </p>
                            </div>
                            <div class="col-md-4 text-end">
                                <div class="stat-card-modern">
                                    <p class="text-sm mb-2 text-muted font-weight-bold">
                                        <i class="material-symbols-rounded text-info me-1">schedule</i>
                                        Current Time
                                    </p>
                                    <h4 class="mb-0 text-primary font-weight-bold" id="current-time"></h4>
                                    <small class="text-muted">Asia/Kolkata (IST)</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <?php if ($role == 'admin'): ?>
                <!-- Admin Dashboard -->
                <div class="row">
                    <!-- Total Trips Card -->
                    <div class="col-xl-3 col-sm-6 mb-xl-0 mb-4">
                        <div class="stat-card-modern modern-card">
                            <div class="d-flex align-items-center justify-content-between">
                                <div>
                                    <p class="text-sm mb-2 text-muted font-weight-bold">Total Trips</p>
                                    <h3 class="mb-1 font-weight-bolder text-primary"><?php echo $totalTrips; ?></h3>
                                    <p class="text-sm mb-0"><span class="text-success font-weight-bolder">+12%</span> than last month</p>
                                </div>
                                <div class="stat-icon-modern gradient-primary">
                                    <i class="material-symbols-rounded">route</i>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Active Drivers Card -->
                    <div class="col-xl-3 col-sm-6 mb-xl-0 mb-4">
                        <div class="stat-card-modern modern-card">
                            <div class="d-flex align-items-center justify-content-between">
                                <div>
                                    <p class="text-sm mb-2 text-muted font-weight-bold">Active Drivers</p>
                                    <h3 class="mb-1 font-weight-bolder text-primary"><?php echo $totalDrivers; ?></h3>
                                    <p class="text-sm mb-0"><span class="text-success font-weight-bolder">+8%</span> than last month</p>
                                </div>
                                <div class="stat-icon-modern gradient-secondary">
                                    <i class="material-symbols-rounded">people</i>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Total Cars Card -->
                    <div class="col-xl-3 col-sm-6 mb-xl-0 mb-4">
                        <div class="stat-card-modern modern-card">
                            <div class="d-flex align-items-center justify-content-between">
                                <div>
                                    <p class="text-sm mb-2 text-muted font-weight-bold">Registered Vehicles</p>
                                    <h3 class="mb-1 font-weight-bolder text-primary"><?php echo $totalCars; ?></h3>
                                    <p class="text-sm mb-0"><span class="text-success font-weight-bolder">+15%</span> than last month</p>
                                </div>
                                <div class="stat-icon-modern gradient-tertiary">
                                    <i class="material-symbols-rounded">directions_car</i>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Active Trips Card -->
                    <div class="col-xl-3 col-sm-6">
                        <div class="stat-card-modern modern-card">
                            <div class="d-flex align-items-center justify-content-between">
                                <div>
                                    <p class="text-sm mb-2 text-muted font-weight-bold">Active Trips</p>
                                    <h3 class="mb-1 font-weight-bolder text-primary"><?php echo $activeTrips; ?></h3>
                                    <p class="text-sm mb-0"><span class="text-success font-weight-bolder">+5%</span> than yesterday</p>
                                </div>
                                <div class="stat-icon-modern gradient-error">
                                    <i class="material-symbols-rounded">play_circle</i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row mt-4">
                    <!-- Revenue Overview Card -->
                    <div class="col-lg-7 mb-lg-0 mb-4">
                        <div class="modern-card h-100">
                            <div class="card-header pb-0 p-4 bg-light border-radius-lg">
                                <div class="d-flex justify-content-between align-items-center">
                                    <h5 class="mb-0 font-weight-bolder text-primary">
                                        <i class="material-symbols-rounded text-success me-2">trending_up</i>Revenue Overview
                                    </h5>
                                    <span class="status-badge-modern bg-gradient-success text-white">Live</span>
                                </div>
                            </div>
                            <div class="card-body p-4">
                                <div class="row">
                                    <div class="col-6">
                                        <div class="stat-card-modern h-100">
                                            <div class="d-flex align-items-center mb-3">
                                                <div class="stat-icon-modern gradient-primary me-3">
                                                    <i class="material-symbols-rounded">attach_money</i>
                                                </div>
                                                <div class="flex-grow-1">
                                                    <p class="text-sm mb-1 text-muted font-weight-bold">Total Revenue</p>
                                                    <h4 class="font-weight-bolder mb-0 text-primary">
                                                        ₹<?php echo number_format($totalRevenue, 2); ?>
                                                    </h4>
                                                </div>
                                            </div>
                                            <p class="text-xs mb-0"><span class="text-success font-weight-bolder">+12%</span> than last month</p>
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <div class="stat-card-modern h-100">
                                            <div class="d-flex align-items-center mb-3">
                                                <div class="stat-icon-modern gradient-tertiary me-3">
                                                    <i class="material-symbols-rounded">today</i>
                                                </div>
                                                <div class="flex-grow-1">
                                                    <p class="text-sm mb-1 text-muted font-weight-bold">Today's Revenue</p>
                                                    <h4 class="font-weight-bolder mb-0 text-primary">
                                                        ₹<?php echo number_format($todayRevenue, 2); ?>
                                                    </h4>
                                                </div>
                                            </div>
                                            <p class="text-xs mb-0"><span class="text-success font-weight-bolder">+8%</span> than yesterday</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Live Driver Tracking Card -->
                    <div class="col-lg-5">
                        <div class="modern-card h-100">
                            <div class="card-header pb-0 p-4 bg-light border-radius-lg">
                                <div class="d-flex justify-content-between align-items-center">
                                    <h5 class="mb-0 font-weight-bolder text-primary">
                                        <i class="material-symbols-rounded text-danger me-2">location_on</i>Live Driver Tracking
                                    </h5>
                                    <span class="status-badge-modern bg-gradient-error text-white">Real-time</span>
                                </div>
                            </div>
                            <div class="card-body p-4">
                                <div class="map-container-modern">
                                    <div id="dashboard_map" style="height: 300px; width: 100%; border-radius: 12px;"></div>
                                </div>
                                <div class="mt-3 text-center">
                                    <div class="d-flex align-items-center justify-content-center">
                                        <div class="modern-avatar gradient-primary me-2">
                                            <i class="material-symbols-rounded text-white text-sm">people</i>
                                        </div>
                                        <small class="text-muted font-weight-bold">
                                            <?php echo count($activeDrivers); ?> active drivers on map
                                        </small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row mt-4">
                    <!-- Recent Trips Card -->
                    <div class="col-lg-8 col-md-6 mb-md-0 mb-4">
                        <div class="modern-card h-100">
                            <div class="card-header pb-0 p-4 bg-light border-radius-lg">
                                <div class="d-flex justify-content-between align-items-center">
                                    <h5 class="mb-0 font-weight-bolder text-primary">
                                        <i class="material-symbols-rounded text-primary me-2">route</i>Recent Trips
                                    </h5>
                                    <a href="trips.php" class="btn btn-outline-primary btn-sm mb-0 font-weight-bold">
                                        <i class="material-symbols-rounded me-1">visibility</i>View All
                                    </a>
                                </div>
                            </div>
                            <div class="card-body p-0">
                                <div class="modern-table mx-4 my-4">
                                    <div class="table-responsive">
                                        <table class="table align-items-center mb-0">
                                            <thead class="bg-light">
                                                <tr>
                                                    <th class="text-uppercase text-secondary text-xs font-weight-bolder opacity-7 ps-4">Driver</th>
                                                    <th class="text-uppercase text-secondary text-xs font-weight-bolder opacity-7">Vehicle</th>
                                                    <th class="text-center text-uppercase text-secondary text-xs font-weight-bolder opacity-7">Status</th>
                                                    <th class="text-center text-uppercase text-secondary text-xs font-weight-bolder opacity-7 pe-4">Date</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach (array_slice($recentTrips, 0, 5) as $trip): ?>
                                                    <tr class="hover-row">
                                                        <td class="ps-4">
                                                            <div class="d-flex align-items-center py-2">
                                                                <div class="modern-avatar me-3 gradient-primary">
                                                                    <i class="material-symbols-rounded text-white text-sm">person</i>
                                                                </div>
                                                                <div class="d-flex flex-column justify-content-center">
                                                                    <h6 class="mb-0 text-sm font-weight-bold text-primary"><?php echo htmlspecialchars($trip['driver_name']); ?></h6>
                                                                </div>
                                                            </div>
                                                        </td>
                                                        <td>
                                                            <div class="d-flex align-items-center">
                                                                <div class="modern-avatar me-3 gradient-tertiary">
                                                                    <i class="material-symbols-rounded text-white text-sm">directions_car</i>
                                                                </div>
                                                                <p class="text-sm font-weight-bold mb-0 text-dark"><?php echo htmlspecialchars($trip['make'] . ' ' . $trip['model']); ?></p>
                                                            </div>
                                                        </td>
                                                        <td class="align-middle text-center">
                                                            <span class="status-badge-modern bg-gradient-<?php echo $trip['status'] == 'completed' ? 'success' : ($trip['status'] == 'in_progress' ? 'warning' : 'secondary'); ?> text-white">
                                                                <?php echo ucfirst($trip['status']); ?>
                                                            </span>
                                                        </td>
                                                        <td class="align-middle text-center pe-4">
                                                            <span class="text-secondary text-sm font-weight-bold"><?php echo date('M d, Y', strtotime($trip['created_at'])); ?></span>
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

                    <!-- Active Drivers Card -->
                    <div class="col-lg-4 col-md-6">
                        <div class="modern-card h-100">
                            <div class="card-header pb-0 p-4 bg-light border-radius-lg">
                                <div class="d-flex justify-content-between align-items-center">
                                    <h5 class="mb-0 font-weight-bolder text-primary">
                                        <i class="material-symbols-rounded text-success me-2">people</i>Active Drivers
                                    </h5>
                                    <span class="status-badge-modern bg-gradient-success text-white"><?php echo count($activeDrivers); ?></span>
                                </div>
                            </div>
                            <div class="card-body p-4">
                                <?php if (count($activeDrivers) > 0): ?>
                                    <div class="stat-card-modern">
                                        <?php foreach ($activeDrivers as $driver): ?>
                                            <div class="d-flex align-items-center mb-4 pb-3 border-bottom border-light">
                                                <div class="modern-avatar me-3 gradient-<?php echo $driver['trip_status'] == 'in_progress' ? 'warning' : 'secondary'; ?>">
                                                    <i class="material-symbols-rounded text-white">person</i>
                                                </div>
                                                <div class="d-flex flex-column flex-grow-1">
                                                    <h6 class="mb-2 text-sm font-weight-bold text-primary"><?php echo htmlspecialchars($driver['name']); ?></h6>
                                                    <div class="d-flex align-items-center flex-wrap">
                                                        <?php if ($driver['trip_status'] == 'in_progress'): ?>
                                                            <span class="status-badge-modern bg-gradient-success text-white me-2 mb-1">
                                                                <i class="material-symbols-rounded me-1 text-xs">play_circle</i>On Trip
                                                            </span>
                                                        <?php else: ?>
                                                            <span class="status-badge-modern bg-gradient-secondary text-white me-2 mb-1">
                                                                <i class="material-symbols-rounded me-1 text-xs">pause_circle</i>Available
                                                            </span>
                                                        <?php endif; ?>
                                                        <?php if ($driver['latitude'] && $driver['longitude']): ?>
                                                            <small class="text-muted ms-2 d-block w-100">
                                                                <i class="material-symbols-rounded me-1 text-xs">location_on</i>
                                                                Lat: <?php echo number_format($driver['latitude'], 4); ?>,
                                                                Lng: <?php echo number_format($driver['longitude'], 4); ?>
                                                            </small>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else: ?>
                                    <div class="stat-card-modern text-center">
                                        <div class="modern-avatar mx-auto mb-3 gradient-secondary" style="width: 80px; height: 80px;">
                                            <i class="material-symbols-rounded text-white" style="font-size: 32px;">person_off</i>
                                        </div>
                                        <h6 class="text-primary font-weight-bold mb-2">No Active Drivers</h6>
                                        <p class="text-sm text-muted">All drivers are currently offline or unavailable.</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

            <?php elseif ($role == 'supervisor'): ?>
                <!-- Supervisor Dashboard -->
                <div class="row">
                    <div class="col-xl-4 col-sm-6 mb-xl-0 mb-4">
                        <div class="card">
                            <div class="card-header p-2 ps-3">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <p class="text-sm mb-0 text-capitalize">My Trips</p>
                                        <h4 class="mb-0"><?php echo count($supervisorTrips); ?></h4>
                                    </div>
                                    <div class="icon icon-md icon-shape bg-gradient-dark shadow-dark shadow text-center border-radius-lg">
                                        <i class="material-symbols-rounded opacity-10">route</i>
                                    </div>
                                </div>
                            </div>
                            <hr class="dark horizontal my-0">
                            <div class="card-footer p-2 ps-3">
                                <p class="mb-0 text-sm"><span class="text-success font-weight-bolder">+8%</span> than last week</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-4 col-sm-6 mb-xl-0 mb-4">
                        <div class="card">
                            <div class="card-header p-2 ps-3">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <p class="text-sm mb-0 text-capitalize">My Drivers</p>
                                        <h4 class="mb-0"><?php echo count($supervisorDrivers); ?></h4>
                                    </div>
                                    <div class="icon icon-md icon-shape bg-gradient-dark shadow-dark shadow text-center border-radius-lg">
                                        <i class="material-symbols-rounded opacity-10">people</i>
                                    </div>
                                </div>
                            </div>
                            <hr class="dark horizontal my-0">
                            <div class="card-footer p-2 ps-3">
                                <p class="mb-0 text-sm"><span class="text-success font-weight-bolder">+12%</span> than last month</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-4 col-sm-6">
                        <div class="card">
                            <div class="card-header p-2 ps-3">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <p class="text-sm mb-0 text-capitalize">Active Trips</p>
                                        <h4 class="mb-0"><?php echo $activeTrips; ?></h4>
                                    </div>
                                    <div class="icon icon-md icon-shape bg-gradient-dark shadow-dark shadow text-center border-radius-lg">
                                        <i class="material-symbols-rounded opacity-10">play_circle</i>
                                    </div>
                                </div>
                            </div>
                            <hr class="dark horizontal my-0">
                            <div class="card-footer p-2 ps-3">
                                <p class="mb-0 text-sm"><span class="text-success font-weight-bolder">+5%</span> than yesterday</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row mt-4">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header pb-0 p-3">
                                <h6>My Drivers Status</h6>
                            </div>
                            <div class="card-body p-3">
                                <?php if (count($activeDrivers) > 0): ?>
                                    <div class="row">
                                        <?php foreach ($activeDrivers as $driver): ?>
                                            <div class="col-md-6 mb-3">
                                                <div class="d-flex align-items-center p-3 border-radius-lg bg-gray-100">
                                                    <div class="avatar avatar-md me-3">
                                                        <i class="material-symbols-rounded">person</i>
                                                    </div>
                                                    <div class="d-flex flex-column flex-grow-1">
                                                        <h6 class="mb-1 text-sm"><?php echo htmlspecialchars($driver['name']); ?></h6>
                                                        <p class="text-xs text-secondary mb-1"><?php echo htmlspecialchars($driver['phone']); ?></p>
                                                        <div class="d-flex align-items-center">
                                                            <?php if ($driver['trip_status'] == 'in_progress'): ?>
                                                                <span class="badge badge-sm bg-gradient-success me-2">On Trip</span>
                                                                <small class="text-xs text-muted">Location: <?php echo $driver['start_location_lat']; ?>, <?php echo $driver['start_location_lng']; ?></small>
                                                            <?php else: ?>
                                                                <span class="badge badge-sm bg-gradient-secondary">Available</span>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else: ?>
                                    <p class="text-sm text-muted">No drivers assigned to you at the moment.</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

            <?php elseif ($role == 'driver'): ?>
                <!-- Driver Dashboard -->
                <div class="row">
                    <div class="col-xl-4 col-sm-6 mb-xl-0 mb-4">
                        <div class="card">
                            <div class="card-header p-2 ps-3">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <p class="text-sm mb-0 text-capitalize">Total Trips</p>
                                        <h4 class="mb-0"><?php echo count($driverTrips); ?></h4>
                                    </div>
                                    <div class="icon icon-md icon-shape bg-gradient-dark shadow-dark shadow text-center border-radius-lg">
                                        <i class="material-symbols-rounded opacity-10">route</i>
                                    </div>
                                </div>
                            </div>
                            <hr class="dark horizontal my-0">
                            <div class="card-footer p-2 ps-3">
                                <p class="mb-0 text-sm"><span class="text-success font-weight-bolder">+10%</span> than last month</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-4 col-sm-6 mb-xl-0 mb-4">
                        <div class="card">
                            <div class="card-header p-2 ps-3">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <p class="text-sm mb-0 text-capitalize">Attendance</p>
                                        <h4 class="mb-0"><?php echo $driverAttendance['present_days']; ?>/<?php echo $driverAttendance['total_days']; ?></h4>
                                    </div>
                                    <div class="icon icon-md icon-shape bg-gradient-dark shadow-dark shadow text-center border-radius-lg">
                                        <i class="material-symbols-rounded opacity-10">event_available</i>
                                    </div>
                                </div>
                            </div>
                            <hr class="dark horizontal my-0">
                            <div class="card-footer p-2 ps-3">
                                <p class="mb-0 text-sm"><span class="text-success font-weight-bolder"><?php echo $driverAttendance['total_days'] > 0 ? round(($driverAttendance['present_days'] / $driverAttendance['total_days']) * 100, 1) : 0; ?>%</span> this month</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-4 col-sm-6">
                        <div class="card">
                            <div class="card-header p-2 ps-3">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <p class="text-sm mb-0 text-capitalize">Rating</p>
                                        <h4 class="mb-0">4.8</h4>
                                    </div>
                                    <div class="icon icon-md icon-shape bg-gradient-dark shadow-dark shadow text-center border-radius-lg">
                                        <i class="material-symbols-rounded opacity-10">star</i>
                                    </div>
                                </div>
                            </div>
                            <hr class="dark horizontal my-0">
                            <div class="card-footer p-2 ps-3">
                                <p class="mb-0 text-sm"><span class="text-success font-weight-bolder">+0.2</span> than last month</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row mt-4">
                    <div class="col-lg-6 mb-lg-0 mb-4">
                        <div class="card">
                            <div class="card-header pb-0 p-3">
                                <h6>Current Trip Status</h6>
                            </div>
                            <div class="card-body p-3">
                                <?php if ($currentTrip): ?>
                                    <div class="d-flex align-items-center mb-3">
                                        <div class="avatar avatar-lg me-3 bg-gradient-success">
                                            <i class="material-symbols-rounded text-white">play_circle</i>
                                        </div>
                                        <div class="d-flex flex-column">
                                            <h6 class="mb-1 text-sm">Trip In Progress</h6>
                                            <p class="text-xs text-secondary mb-1">Passengers: <?php echo $currentTrip['passenger_count']; ?></p>
                                            <p class="text-xs text-secondary mb-0">Started: <?php echo date('M d, Y H:i', strtotime($currentTrip['start_time'])); ?></p>
                                        </div>
                                    </div>
                                    <div class="progress-wrapper">
                                        <div class="progress-info">
                                            <div class="progress-percentage">
                                                <span class="text-xs font-weight-bold">Trip Progress</span>
                                            </div>
                                        </div>
                                        <div class="progress">
                                            <div class="progress-bar bg-gradient-success w-60" role="progressbar" aria-valuenow="60" aria-valuemin="0" aria-valuemax="100"></div>
                                        </div>
                                    </div>
                                <?php else: ?>
                                    <div class="d-flex align-items-center">
                                        <div class="avatar avatar-lg me-3 bg-gradient-secondary">
                                            <i class="material-symbols-rounded text-white">pause_circle</i>
                                        </div>
                                        <div class="d-flex flex-column">
                                            <h6 class="mb-1 text-sm">Available for Trips</h6>
                                            <p class="text-xs text-secondary mb-0">You are currently available for new assignments.</p>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-6">
                        <div class="card">
                            <div class="card-header pb-0 p-3">
                                <div class="d-flex justify-content-between">
                                    <h6>Recent Trips</h6>
                                    <a href="trips.php" class="btn btn-outline-primary btn-sm mb-0">View All</a>
                                </div>
                            </div>
                            <div class="card-body p-3">
                                <?php
                                $recentDriverTrips = array_slice($driverTrips, 0, 3);
                                if (count($recentDriverTrips) > 0):
                                    foreach ($recentDriverTrips as $trip):
                                ?>
                                        <div class="d-flex align-items-center mb-3">
                                            <div class="avatar avatar-sm me-3 bg-gradient-<?php echo $trip['status'] == 'completed' ? 'success' : ($trip['status'] == 'in_progress' ? 'warning' : 'secondary'); ?>">
                                                <i class="material-symbols-rounded text-white text-xs">route</i>
                                            </div>
                                            <div class="d-flex flex-column flex-grow-1">
                                                <h6 class="mb-0 text-sm">Trip #<?php echo $trip['id']; ?></h6>
                                                <p class="text-xs text-secondary mb-0"><?php echo date('M d, Y', strtotime($trip['created_at'])); ?> - <?php echo ucfirst($trip['status']); ?></p>
                                            </div>
                                            <div class="text-end">
                                                <span class="badge badge-sm bg-gradient-<?php echo $trip['status'] == 'completed' ? 'success' : ($trip['status'] == 'in_progress' ? 'warning' : 'secondary'); ?>">
                                                    <?php echo ucfirst($trip['status']); ?>
                                                </span>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <p class="text-sm text-muted">No trips completed yet.</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <div class="fixed-plugin">
            <a class="fixed-plugin-button text-dark position-fixed px-3 py-2">
                <i class="material-symbols-rounded py-2">settings</i>
            </a>
            <div class="card shadow-lg">
                <div class="card-header pb-0 pt-3">
                    <div class="float-start">
                        <h5 class="mt-3 mb-0">Shreeji Link Configurator</h5>
                        <p>See our dashboard options.</p>
                    </div>
                    <div class="float-end mt-4">
                        <button class="btn btn-link text-dark p-0 fixed-plugin-close-button">
                            <i class="material-symbols-rounded">clear</i>
                        </button>
                    </div>
                </div>
                <hr class="horizontal dark my-1">
                <div class="card-body pt-sm-3 pt-0">
                    <div>
                        <h6 class="mb-0">Sidebar Colors</h6>
                    </div>
                    <a href="javascript:void(0)" class="switch-trigger background-color">
                        <div class="badge-colors my-2 text-start">
                            <span class="badge filter bg-gradient-primary" data-color="primary" onclick="sidebarColor(this)"></span>
                            <span class="badge filter bg-gradient-dark active" data-color="dark" onclick="sidebarColor(this)"></span>
                            <span class="badge filter bg-gradient-info" data-color="info" onclick="sidebarColor(this)"></span>
                            <span class="badge filter bg-gradient-success" data-color="success" onclick="sidebarColor(this)"></span>
                            <span class="badge filter bg-gradient-warning" data-color="warning" onclick="sidebarColor(this)"></span>
                            <span class="badge filter bg-gradient-danger" data-color="danger" onclick="sidebarColor(this)"></span>
                        </div>
                    </a>
                    <div class="mt-3">
                        <h6 class="mb-0">Sidenav Type</h6>
                        <p class="text-sm">Choose between different sidenav types.</p>
                    </div>
                    <div class="d-flex">
                        <button class="btn bg-gradient-dark px-3 mb-2" data-class="bg-gradient-dark" onclick="sidebarType(this)">Dark</button>
                        <button class="btn bg-gradient-dark px-3 mb-2 ms-2" data-class="bg-transparent" onclick="sidebarType(this)">Transparent</button>
                        <button class="btn bg-gradient-dark px-3 mb-2  active ms-2" data-class="bg-white" onclick="sidebarType(this)">White</button>
                    </div>
                    <p class="text-sm d-xl-none d-block mt-2">You can change the sidenav type just on desktop view.</p>
                    <div class="mt-3 d-flex">
                        <h6 class="mb-0">Navbar Fixed</h6>
                        <div class="form-check form-switch ps-0 ms-auto my-auto">
                            <input class="form-check-input mt-1 ms-auto" type="checkbox" id="navbarFixed" onclick="navbarFixed(this)">
                        </div>
                    </div>
                    <hr class="horizontal dark my-3">
                    <div class="mt-2 d-flex">
                        <h6 class="mb-0">Light / Dark</h6>
                        <div class="form-check form-switch ps-0 ms-auto my-auto">
                            <input class="form-check-input mt-1 ms-auto" type="checkbox" id="dark-version" onclick="darkMode(this)">
                        </div>
                    </div>
                    <hr class="horizontal dark my-sm-4">
                    <a class="btn bg-gradient-info w-100" href="https://shreejilink.com">Visit Shreeji Link</a>
                    <div class="w-100 text-center">
                        <h6 class="mt-3">Employee Transportation System</h6>
                        <a href="https://shreejilink.com" class="btn btn-dark mb-0 me-2" target="_blank">
                            <i class="fab fa-globe me-1" aria-hidden="true"></i> Website
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <script src="./assets/js/core/popper.min.js"></script>
        <script src="./assets/js/core/bootstrap.min.js"></script>
        <script src="./assets/js/plugins/perfect-scrollbar.min.js"></script>
        <script src="./assets/js/plugins/smooth-scrollbar.min.js"></script>
        <script src="./assets/js/plugins/chartjs.min.js"></script>
        <script>
            // Update current time
            function updateTime() {
                const now = new Date();
                const timeString = now.toLocaleString('en-IN', {
                    timeZone: 'Asia/Kolkata',
                    hour12: true,
                    hour: '2-digit',
                    minute: '2-digit',
                    second: '2-digit'
                });
                const timeElement = document.getElementById('current-time');
                if (timeElement) {
                    timeElement.textContent = timeString;
                }
            }

            updateTime();
            setInterval(updateTime, 1000);

            // Initialize map for admin dashboard
            <?php if ($role == 'admin'): ?>

                let map;
                let markers = [];

                function initMap() {
                    const mapElement = document.getElementById('dashboard_map');
                    if (mapElement) {
                        // Default center (Mumbai)
                        const defaultLocation = {
                            lat: 19.0760,
                            lng: 72.8777
                        };

                        map = new google.maps.Map(mapElement, {
                            zoom: 12,
                            center: defaultLocation,
                            mapTypeControl: false,
                            streetViewControl: false,
                            fullscreenControl: false
                        });

                        // Add markers for active drivers
                        <?php foreach ($activeDrivers as $index => $driver): ?>
                            <?php
                            $lat = $driver['latitude'] ?: $driver['start_location_lat'];
                            $lng = $driver['longitude'] ?: $driver['start_location_lng'];
                            $isOnTrip = $driver['trip_status'] == 'in_progress';
                            $hasLocation = ($driver['latitude'] && $driver['longitude']) || ($driver['start_location_lat'] && $driver['start_location_lng']);
                            $driverNumber = $index + 1;
                            ?>
                            <?php if ($hasLocation): ?>
                                const driver<?php echo $driverNumber; ?>Pos = {
                                    lat: <?php echo $lat; ?>,
                                    lng: <?php echo $lng; ?>
                                };

                                const driver<?php echo $driverNumber; ?>Marker = new google.maps.Marker({
                                    position: driver<?php echo $driverNumber; ?>Pos,
                                    map: map,
                                    icon: {
                                        url: 'data:image/svg+xml;charset=UTF-8,' + encodeURIComponent(`
                                            <svg width="40" height="40" viewBox="0 0 40 40" xmlns="http://www.w3.org/2000/svg">
                                                <circle cx="20" cy="20" r="18" fill="<?php echo $isOnTrip ? '#e74c3c' : '#27ae60'; ?>" stroke="white" stroke-width="3"/>
                                                <text x="20" y="25" text-anchor="middle" fill="white" font-family="Arial" font-weight="bold" font-size="12"><?php echo $driverNumber; ?></text>
                                            </svg>
                                        `),
                                        scaledSize: new google.maps.Size(40, 40),
                                        anchor: new google.maps.Point(20, 20)
                                    }
                                });

                                const driver<?php echo $driverNumber; ?>InfoWindow = new google.maps.InfoWindow({
                                    content: `
                                        <div style="max-width: 200px;">
                                            <h6 style="margin: 0 0 5px 0;"><?php echo htmlspecialchars($driver['name']); ?></h6>
                                            <p style="margin: 0; font-size: 12px; color: #666;">
                                                Phone: <?php echo htmlspecialchars($driver['phone']); ?><br>
                                                Status: <?php echo $isOnTrip ? 'On Trip' : 'Available'; ?><br>
                                                <small>Last Location: Lat <?php echo number_format($lat, 6); ?>, Lng <?php echo number_format($lng, 6); ?><br>
                                                Updated: <?php echo $driver['updated_at'] ? date('M d, H:i', strtotime($driver['updated_at'])) : 'N/A'; ?></small>
                                            </p>
                                        </div>
                                    `
                                });

                                driver<?php echo $driverNumber; ?>Marker.addListener('click', () => {
                                    driver<?php echo $driverNumber; ?>InfoWindow.open(map, driver<?php echo $driverNumber; ?>Marker);
                                });

                                markers.push(driver<?php echo $driverNumber; ?>Marker);
                            <?php endif; ?>
                        <?php endforeach; ?>

                        // Fit bounds to show all markers
                        if (markers.length > 0) {
                            const bounds = new google.maps.LatLngBounds();
                            markers.forEach(marker => {
                                bounds.extend(marker.getPosition());
                            });
                            map.fitBounds(bounds, {
                                padding: 50
                            });
                        }
                    }
                }

                // Load map when page loads
                window.addEventListener('load', function() {
                    initMap();
                });
            <?php endif; ?>

            var win = navigator.platform.indexOf('Win') > -1;
            if (win && document.querySelector('#sidenav-scrollbar')) {
                var options = {
                    damping: '0.5'
                }
                Scrollbar.init(document.querySelector('#sidenav-scrollbar'), options);
            }
        </script>
        <script src="./assets/js/material-dashboard.min.js?v=3.2.0"></script>
        <script>
            // Initialize Material Dashboard
            if (document.getElementById('sidenav-main')) {
                const sidenav = document.getElementById('sidenav-main');
                const sidenavToggle = document.querySelector('[data-bs-toggle="sidenav"]');
                const mobileMenuToggle = document.getElementById('mobileMenuToggle');
                const sidenavOverlay = document.getElementById('sidenavOverlay');

                // Desktop sidenav toggle
                if (sidenavToggle) {
                    sidenavToggle.addEventListener('click', function() {
                        sidenav.classList.toggle('show');
                    });
                }

                // Mobile menu toggle
                if (mobileMenuToggle && sidenavOverlay) {
                    const toggleMobileMenu = function() {
                        sidenav.classList.toggle('show');
                        document.body.classList.toggle('sidenav-open');
                    };

                    mobileMenuToggle.addEventListener('click', toggleMobileMenu);
                    sidenavOverlay.addEventListener('click', toggleMobileMenu);

                    // Close mobile menu when clicking on nav links
                    const navLinks = sidenav.querySelectorAll('.nav-link');
                    navLinks.forEach(link => {
                        link.addEventListener('click', function() {
                            if (window.innerWidth <= 768) {
                                sidenav.classList.remove('show');
                                document.body.classList.remove('sidenav-open');
                            }
                        });
                    });
                }
            }

            // Initialize tooltips and popovers
            var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            var tooltipList = tooltipTriggerList.map(function(tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl);
            });

            var popoverTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="popover"]'));
            var popoverList = popoverTriggerList.map(function(popoverTriggerEl) {
                return new bootstrap.Popover(popoverTriggerEl);
            });
        </script>
</body>

</html>