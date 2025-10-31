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

// Allow both drivers and admins/supervisors to access this page
// Drivers see their own tracking, admins/supervisors see all drivers

// Handle location updates
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_location'])) {
    $latitude = (float)$_POST['latitude'];
    $longitude = (float)$_POST['longitude'];

    $conn = getDBConnection();

    // Update or insert current location for the driver
    $stmt = $conn->prepare("
        INSERT INTO driver_locations (driver_id, latitude, longitude, updated_at)
        VALUES (?, ?, ?, NOW())
        ON DUPLICATE KEY UPDATE latitude = VALUES(latitude), longitude = VALUES(longitude), updated_at = NOW()
    ");
    $stmt->execute([$_SESSION['user_id'], $latitude, $longitude]);

    echo json_encode(['status' => 'success']);
    exit;
}

// Handle AJAX request for real-time location updates
if (isset($_GET['action']) && $_GET['action'] === 'get_locations' && $role !== 'driver') {
    $conn = getDBConnection();

    try {
        $stmt = $conn->query("
            SELECT u.id, u.name, u.phone, dl.latitude, dl.longitude, dl.updated_at,
                   t.id as trip_id, t.status as trip_status, t.distance as trip_distance,
                   t.start_location_lat, t.start_location_lng, t.end_location_lat, t.end_location_lng
            FROM users u
            LEFT JOIN driver_locations dl ON u.id = dl.driver_id
            LEFT JOIN trips t ON u.id = t.driver_id AND t.status = 'in_progress'
            WHERE u.role = 'driver' AND (dl.latitude IS NOT NULL OR t.status = 'in_progress')
            ORDER BY dl.updated_at DESC
        ");
        $drivers = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode(['success' => true, 'drivers' => $drivers]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// Get current trip if any (for drivers) or all active trips (for admins/supervisors)
$current_trip = null;
$active_drivers = [];
$conn = getDBConnection();

if ($role === 'driver') {
    $stmt = $conn->prepare("SELECT * FROM trips WHERE driver_id = ? AND status = 'in_progress' ORDER BY created_at DESC LIMIT 1");
    $stmt->execute([$_SESSION['user_id']]);
    $current_trip = $stmt->fetch(PDO::FETCH_ASSOC);
} else {
    // For admins/supervisors, get all active drivers with their locations and trip distance
    try {
        $stmt = $conn->query("
            SELECT u.id, u.name, u.phone, dl.latitude, dl.longitude, dl.updated_at,
                   t.id as trip_id, t.status as trip_status, t.distance as trip_distance,
                   t.start_location_lat, t.start_location_lng, t.end_location_lat, t.end_location_lng
            FROM users u
            LEFT JOIN driver_locations dl ON u.id = dl.driver_id
            LEFT JOIN trips t ON u.id = t.driver_id AND t.status = 'in_progress'
            WHERE u.role = 'driver' AND (dl.latitude IS NOT NULL OR t.status = 'in_progress')
            ORDER BY dl.updated_at DESC
        ");
        $active_drivers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        // If driver_locations table doesn't exist yet
        $active_drivers = [];
    }
}

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=5.0, user-scalable=yes">
    <link rel="apple-touch-icon" sizes="76x76" href="./assets/img/apple-icon.png">
    <link rel="icon" type="image/png" href="./assets/img/favicon.png">
    <title>Live Location Tracking - Shreeji Link Employee Transportation System</title>
    <link rel="stylesheet" type="text/css" href="https://fonts.googleapis.com/css?family=Inter:300,400,500,600,700,900" />
    <link href="./assets/nucleo-icons.css" rel="stylesheet" />
    <link href="./assets/nucleo-svg.css" rel="stylesheet" />
    <script src="https://kit.fontawesome.com/42d5adcbca.js" crossorigin="anonymous"></script>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded:opsz,wght,FILL,GRAD@24,400,0,0" />
    <link id="pagestyle" href="./assets/material-dashboard.css?v=3.2.0" rel="stylesheet" />
    <script src="https://maps.googleapis.com/maps/api/js?key=AIzaSyAUnYgTLEnIHwXCE1A98gAGFXBrvQEv8aI&libraries=places,geometry"></script>
    <style>
        #tracking_map {
            height: 550px;
            width: 100%;
            border-radius: 8px;
            border: 1px solid #dee2e6;
        }

        /* Mobile and Tablet Responsive Styles */
        @media (max-width: 768px) {
            .sidenav {
                transform: translateX(-100%);
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

            .row {
                margin-left: -0.25rem;
                margin-right: -0.25rem;
            }

            .row>* {
                padding-left: 0.25rem;
                padding-right: 0.25rem;
            }

            .col-md-8,
            .col-md-4,
            .col-md-6,
            .col-md-3 {
                flex: 0 0 100%;
                max-width: 100%;
                margin-bottom: 1rem;
            }

            #tracking_map {
                height: 300px !important;
            }

            .location-info {
                padding: 15px !important;
            }

            .d-flex.align-items-center.justify-content-end {
                flex-direction: column;
                align-items: stretch !important;
            }

            .me-3.mb-2.mb-md-0 {
                margin-bottom: 0.5rem !important;
            }

            .input-group.input-group-sm {
                max-width: 100% !important;
            }

            .btn {
                padding: 0.5rem 1rem;
                font-size: 0.875rem;
            }

            .table-responsive {
                font-size: 0.875rem;
            }

            .table th,
            .table td {
                padding: 0.5rem;
            }

            .tracking-status {
                position: static !important;
                margin-top: 1rem;
            }

            .navbar-nav {
                flex-direction: row;
                justify-content: space-between;
                width: 100%;
            }

            .navbar-nav .nav-item {
                margin: 0 0.25rem;
            }
        }

        @media (max-width: 576px) {
            .container-fluid {
                padding-left: 0.25rem;
                padding-right: 0.25rem;
            }

            .location-info h4 {
                font-size: 1.25rem;
            }

            .location-info p {
                font-size: 0.875rem;
            }

            #tracking_map {
                height: 250px !important;
            }

            .card-body p-3 {
                padding: 1rem !important;
            }

            .form-control {
                padding: 0.5rem 0.75rem;
                font-size: 0.875rem;
            }

            .badge {
                font-size: 0.75rem;
                padding: 0.25rem 0.5rem;
            }

            .fixed-plugin {
                display: none;
            }
        }

        @media (min-width: 769px) and (max-width: 1024px) {
            .container-fluid {
                padding-left: 1rem;
                padding-right: 1rem;
            }

            .col-md-8 {
                flex: 0 0 66.666%;
                max-width: 66.666%;
            }

            .col-md-4 {
                flex: 0 0 33.333%;
                max-width: 33.333%;
            }

            #tracking_map {
                height: 400px !important;
            }

            .location-info {
                padding: 18px !important;
            }
        }

        .location-info {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
        }

        .tracking-status {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1000;
            background: rgba(255, 255, 255, 0.9);
            border-radius: 8px;
            padding: 15px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        .driver-marker {
            background-color: #e74c3c;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            border: 3px solid white;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
            font-size: 12px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.3);
        }
    </style>
</head>

<body class="g-sidenav-show bg-gray-100">
    <aside class="sidenav navbar navbar-vertical navbar-expand-xs border-radius-lg fixed-start ms-2 bg-white my-2" id="sidenav-main">
        <div class="sidenav-header">
            <i class="fas fa-times p-3 cursor-pointer text-dark opacity-5 position-absolute end-0 top-0 d-none d-xl-none" aria-hidden="true" id="iconSidenav"></i>
            <a class="navbar-brand px-4 py-3 m-0" href="#">
                <img src="./assets/img/logo-ct-dark.png" class="navbar-brand-img" width="26" height="26" alt="main_logo">
                <img src="./assets/images/logo.png" class="navbar-brand-img" width="26" height="26" alt="Shreeji Link Logo">
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
                <?php if ($role === 'driver'): ?>
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
                        <a class="nav-link active bg-gradient-dark text-white" href="geolocation_tracker.php">
                            <i class="material-symbols-rounded opacity-5">location_on</i>
                            <span class="nav-link-text ms-1">Live Tracking</span>
                        </a>
                    </li>
                <?php else: ?>
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
                        <a class="nav-link text-dark" href="vehicles.php">
                            <i class="material-symbols-rounded opacity-5">directions_car</i>
                            <span class="nav-link-text ms-1">Vehicles</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active bg-gradient-dark text-white" href="geolocation_tracker.php">
                            <i class="material-symbols-rounded opacity-5">location_on</i>
                            <span class="nav-link-text ms-1">Live Tracking</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link text-dark" href="attendance.php">
                            <i class="material-symbols-rounded opacity-5">event_available</i>
                            <span class="nav-link-text ms-1">Attendance</span>
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
                    <ol class="breadcrumb bg-transparent mb-0 pb-0 pt-1 px-0 me-sm-6 me-5">
                        <li class="breadcrumb-item text-sm"><a class="opacity-5 text-dark" href="javascript:;">Pages</a></li>
                        <li class="breadcrumb-item text-sm text-dark active" aria-current="page">Live Location Tracking</li>
                    </ol>
                    <h6 class="font-weight-bolder mb-0 text-primary">Live Location Tracking</h6>
                    <p class="text-muted mb-0">Welcome, <strong><?php echo htmlspecialchars($user['name']); ?></strong></p>
                </nav>
            </div>
        </nav>

        <div class="container-fluid py-2">
            <!-- Location Info Card -->
            <div class="location-info">
                <div class="row align-items-center">
                    <div class="col-md-8">
                        <h4 class="mb-1"><i class="fas fa-map-marker-alt me-2"></i>
                            <?php echo $role === 'driver' ? 'Live Location Tracking' : 'Driver Location Monitoring'; ?>
                        </h4>
                        <p class="mb-0 opacity-8">
                            <?php echo $role === 'driver' ? 'Your current location is being tracked in real-time for accurate trip monitoring.' : 'Monitor all active drivers\' locations in real-time.'; ?>
                        </p>
                    </div>
                    <div class="col-md-4 text-end">
                        <?php if ($role === 'driver'): ?>
                            <div class="d-flex align-items-center justify-content-end">
                                <div class="me-3">
                                    <i class="fas fa-circle text-success me-2"></i>
                                    <span id="tracking-status">Tracking Active</span>
                                </div>
                                <button id="start-tracking" class="btn btn-success btn-sm">
                                    <i class="fas fa-play me-1"></i>Start Auto Tracking
                                </button>
                                <button id="stop-tracking" class="btn btn-danger btn-sm d-none">
                                    <i class="fas fa-stop me-1"></i>Stop Tracking
                                </button>
                            </div>
                        <?php else: ?>
                            <div class="d-flex align-items-center justify-content-end flex-wrap">
                                <div class="me-3 mb-2 mb-md-0">
                                    <i class="fas fa-users text-info me-2"></i>
                                    <span><?php echo count($active_drivers); ?> Active Drivers</span>
                                </div>
                                <div class="input-group input-group-sm me-2 mb-2 mb-md-0" style="max-width: 250px;">
                                    <input type="text" class="form-control" id="driver-search" placeholder="Search drivers...">
                                    <button class="btn btn-outline-secondary" type="button" id="clear-search">
                                        <i class="fas fa-times"></i>
                                    </button>
                                </div>
                                <button id="refresh-locations" class="btn btn-light btn-sm">
                                    <i class="fas fa-sync me-1"></i>Refresh
                                </button>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Current Trip Status or Active Drivers List -->
            <?php if ($role === 'driver' && $current_trip): ?>
                <div class="row mb-4">
                    <div class="col-12">
                        <div class="card border rounded">
                            <div class="card-body p-3 bg-light">
                                <div class="d-flex align-items-center">
                                    <div class="avatar avatar-lg me-3 bg-gradient-success">
                                        <i class="material-symbols-rounded text-white">local_shipping</i>
                                    </div>
                                    <div class="flex-grow-1">
                                        <h6 class="mb-1">Active Trip in Progress</h6>
                                        <p class="text-sm text-muted mb-0">Trip #<?php echo $current_trip['id']; ?> - Started at <?php echo date('H:i', strtotime($current_trip['start_time'])); ?></p>
                                    </div>
                                    <div class="text-end">
                                        <span class="badge bg-gradient-success">In Progress</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php elseif ($role !== 'driver' && !empty($active_drivers)): ?>
                <div class="row mb-4">
                    <div class="col-12">
                        <div class="card border rounded">
                            <div class="card-header pb-0 p-3 bg-light">
                                <h6 class="mb-0 font-weight-bolder">
                                    <i class="fas fa-users text-primary me-2"></i>Active Drivers
                                </h6>
                            </div>
                            <div class="card-body p-3">
                                <div class="row" id="drivers-list">
                                    <?php foreach ($active_drivers as $driver): ?>
                                        <div class="col-md-6 col-lg-4 mb-3">
                                            <div class="card h-100 border">
                                                <div class="card-body p-3">
                                                    <div class="d-flex align-items-center mb-2">
                                                        <div class="avatar avatar-sm me-3 bg-gradient-<?php echo $driver['trip_status'] === 'in_progress' ? 'success' : 'secondary'; ?>">
                                                            <i class="material-symbols-rounded text-white text-xs">person</i>
                                                        </div>
                                                        <div class="flex-grow-1">
                                                            <h6 class="mb-0 text-sm"><?php echo htmlspecialchars($driver['name']); ?></h6>
                                                            <small class="text-muted"><?php echo htmlspecialchars($driver['phone']); ?></small>
                                                        </div>
                                                    </div>
                                                    <?php if ($driver['latitude'] && $driver['longitude']): ?>
                                                        <div class="text-xs text-muted mb-2">
                                                            <i class="fas fa-map-marker-alt me-1"></i>
                                                            <?php echo number_format($driver['latitude'], 4); ?>, <?php echo number_format($driver['longitude'], 4); ?>
                                                        </div>
                                                        <div class="text-xs text-muted">
                                                            <i class="fas fa-clock me-1"></i>
                                                            Updated: <?php echo $driver['updated_at'] ? date('H:i:s', strtotime($driver['updated_at'])) : 'Never'; ?>
                                                        </div>
                                                        <?php if ($driver['trip_status'] === 'in_progress' && $driver['trip_distance']): ?>
                                                            <div class="text-xs text-muted mt-1">
                                                                <i class="fas fa-route me-1"></i>
                                                                Distance: <?php echo number_format($driver['trip_distance'], 2); ?> km
                                                            </div>
                                                        <?php endif; ?>
                                                    <?php else: ?>
                                                        <div class="text-xs text-muted">
                                                            <i class="fas fa-exclamation-triangle me-1"></i>
                                                            Location not available
                                                        </div>
                                                    <?php endif; ?>
                                                    <?php if ($driver['trip_status'] === 'in_progress'): ?>
                                                        <div class="mt-2">
                                                            <span class="badge badge-sm bg-gradient-success">On Trip</span>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Map Container -->
            <div class="row">
                <div class="col-12">
                    <div class="card border rounded">
                        <div class="card-header pb-0 p-3 bg-light">
                            <h6 class="mb-0 font-weight-bolder">
                                <i class="fas fa-map-marked-alt text-primary me-2"></i>Live Location Map
                            </h6>
                        </div>
                        <div class="card-body p-3">
                            <div id="tracking_map" class="border rounded"></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Location Details -->
            <?php if ($role === 'driver'): ?>
                <div class="row mt-4">
                    <div class="col-md-6">
                        <div class="card border rounded h-100">
                            <div class="card-header pb-0 p-3 bg-light">
                                <h6 class="mb-0 font-weight-bolder">
                                    <i class="fas fa-info-circle text-info me-2"></i>Current Location
                                </h6>
                            </div>
                            <div class="card-body p-3">
                                <div class="row">
                                    <div class="col-6">
                                        <div class="form-group">
                                            <label class="form-control-label text-muted">Latitude</label>
                                            <input type="text" class="form-control" id="current-lat" readonly>
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <div class="form-group">
                                            <label class="form-control-label text-muted">Longitude</label>
                                            <input type="text" class="form-control" id="current-lng" readonly>
                                        </div>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label class="form-control-label text-muted">Address</label>
                                    <input type="text" class="form-control" id="current-address" readonly>
                                </div>
                                <div class="form-group">
                                    <label class="form-control-label text-muted">Last Updated</label>
                                    <input type="text" class="form-control" id="last-update" readonly>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card border rounded h-100">
                            <div class="card-header pb-0 p-3 bg-light">
                                <h6 class="mb-0 font-weight-bolder">
                                    <i class="fas fa-history text-warning me-2"></i>Tracking History
                                </h6>
                            </div>
                            <div class="card-body p-3">
                                <div id="location-history" class="list-group list-group-flush">
                                    <div class="text-center text-muted py-4">
                                        <i class="fas fa-clock fa-2x mb-2"></i>
                                        <p>Location updates will appear here</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <div class="row mt-4">
                    <div class="col-12">
                        <div class="card border rounded">
                            <div class="card-header pb-0 p-3 bg-light">
                                <h6 class="mb-0 font-weight-bolder">
                                    <i class="fas fa-chart-line text-success me-2"></i>Tracking Statistics
                                </h6>
                            </div>
                            <div class="card-body p-3">
                                <div class="row text-center">
                                    <div class="col-md-3">
                                        <div class="stat-card">
                                            <h3 class="text-primary"><?php echo count(array_filter($active_drivers, fn($d) => $d['latitude'])); ?></h3>
                                            <p class="text-muted mb-0">Drivers Online</p>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="stat-card">
                                            <h3 class="text-success"><?php echo count(array_filter($active_drivers, fn($d) => $d['trip_status'] === 'in_progress')); ?></h3>
                                            <p class="text-muted mb-0">On Active Trips</p>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="stat-card">
                                            <h3 class="text-warning"><?php echo count($active_drivers) - count(array_filter($active_drivers, fn($d) => $d['latitude'])); ?></h3>
                                            <p class="text-muted mb-0">Location Pending</p>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="stat-card">
                                            <h3 class="text-info"><?php echo count($active_drivers); ?></h3>
                                            <p class="text-muted mb-0">Total Drivers</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <!-- Tracking Status Widget -->
    <div class="tracking-status d-none">
        <div class="d-flex align-items-center">
            <i class="fas fa-circle text-success me-2"></i>
            <span>Tracking Active</span>
        </div>
        <div class="mt-2">
            <small class="text-muted">Last update: <span id="status-last-update">Never</span></small>
        </div>
    </div>

    <script src="./assets/js/core/popper.min.js"></script>
    <script src="./assets/js/core/bootstrap.min.js"></script>
    <script src="./assets/js/plugins/perfect-scrollbar.min.js"></script>
    <script src="./assets/js/plugins/smooth-scrollbar.min.js"></script>
    <script src="./assets/js/material-dashboard.min.js?v=3.2.0"></script>

    <script>
        let map;
        let marker;
        let watchId;
        let locationHistory = [];
        let driverMarkers = [];

        // Initialize Google Map
        function initMap() {
            const defaultLocation = {
                lat: 19.0760,
                lng: 72.8777
            }; // Mumbai

            // Create map
            map = new google.maps.Map(document.getElementById('tracking_map'), {
                zoom: <?php echo $role === 'driver' ? '15' : '10'; ?>,
                center: defaultLocation,
                mapTypeControl: false,
                streetViewControl: false,
                fullscreenControl: false,
                styles: [{
                    featureType: 'poi',
                    stylers: [{
                        visibility: 'off'
                    }]
                }]
            });

            <?php if ($role === 'driver'): ?>
                // For drivers: Immediately try to get and show current location
                console.log('Initializing driver location tracking...');
                if (navigator.geolocation) {
                    navigator.geolocation.getCurrentPosition(
                        (position) => {
                            const pos = {
                                lat: position.coords.latitude,
                                lng: position.coords.longitude
                            };
                            console.log('Got initial position:', pos);
                            map.setCenter(pos);
                            map.setZoom(18);
                            updateMarker(pos);
                            updateLocationInfo(pos);
                            reverseGeocode(pos);
                        },
                        (error) => {
                            console.error('Initial geolocation error:', error);
                            updateMarker(defaultLocation);
                            updateLocationInfo(defaultLocation);
                            reverseGeocode(defaultLocation);
                            alert('Unable to get your exact location. Please allow location access and refresh the page.');
                        }, {
                            enableHighAccuracy: true,
                            timeout: 15000,
                            maximumAge: 60000
                        }
                    );
                } else {
                    alert('Geolocation is not supported by this browser.');
                }
            <?php else: ?>
                // For admins/supervisors: Show all active drivers
                console.log('Initializing admin/supervisor view...');
                <?php if (!empty($active_drivers)): ?>
                    const bounds = new google.maps.LatLngBounds();
                    let hasValidLocations = false;

                    <?php foreach ($active_drivers as $index => $driver): ?>
                        <?php if ($driver['latitude'] && $driver['longitude']): ?>
                            hasValidLocations = true;
                            const driverPos = {
                                lat: parseFloat(<?php echo $driver['latitude']; ?>),
                                lng: parseFloat(<?php echo $driver['longitude']; ?>)
                            };
                            bounds.extend(driverPos);
                            console.log('Adding driver marker at:', driverPos);

                            const marker = new google.maps.Marker({
                                position: driverPos,
                                map: map,
                                icon: {
                                    url: 'data:image/svg+xml;charset=UTF-8,' + encodeURIComponent(`
                                        <svg width="40" height="40" viewBox="0 0 40 40" xmlns="http://www.w3.org/2000/svg">
                                            <circle cx="20" cy="20" r="18" fill="<?php echo $driver['trip_status'] === 'in_progress' ? '#e74c3c' : '#27ae60'; ?>" stroke="white" stroke-width="3"/>
                                            <text x="20" y="25" text-anchor="middle" fill="white" font-family="Arial" font-weight="bold" font-size="12"><?php echo substr($driver['name'], 0, 1); ?></text>
                                        </svg>
                                    `),
                                    scaledSize: new google.maps.Size(40, 40),
                                    anchor: new google.maps.Point(20, 20)
                                }
                            });

                            const infoWindow = new google.maps.InfoWindow({
                                content: `
                                    <div style="max-width: 200px;">
                                        <h6 style="margin: 0 0 5px 0;"><?php echo addslashes($driver['name']); ?></h6>
                                        <p style="margin: 0; font-size: 12px; color: #666;">
                                            Phone: <?php echo addslashes($driver['phone']); ?><br>
                                            Status: <?php echo $driver['trip_status'] === 'in_progress' ? 'On Trip' : 'Available'; ?><br>
                                            Updated: <?php echo $driver['updated_at'] ? date('H:i:s', strtotime($driver['updated_at'])) : 'Never'; ?><br>
                                            <?php if ($driver['trip_distance']): ?>
                                                Distance: <?php echo number_format($driver['trip_distance'], 2); ?> km<br>
                                            <?php endif; ?>
                                            <small>Lat: <?php echo $driver['latitude']; ?>, Lng: <?php echo $driver['longitude']; ?></small>
                                        </p>
                                    </div>
                                `
                            });

                            marker.addListener('click', () => {
                                infoWindow.open(map, marker);
                            });

                            driverMarkers.push(marker);
                        <?php endif; ?>
                    <?php endforeach; ?>

                    if (hasValidLocations) {
                        console.log('Fitting bounds for drivers');
                        map.fitBounds(bounds);
                    } else {
                        console.log('No valid driver locations, setting default view');
                        map.setCenter(defaultLocation);
                        map.setZoom(10);
                    }
                <?php else: ?>
                    console.log('No active drivers found');
                    map.setCenter(defaultLocation);
                    map.setZoom(10);
                <?php endif; ?>
            <?php endif; ?>
        }

        // Update map marker
        function updateMarker(position) {
            if (marker) {
                marker.setPosition(position);
            } else {
                marker = new google.maps.Marker({
                    position: position,
                    map: map,
                    icon: {
                        url: 'data:image/svg+xml;charset=UTF-8,' + encodeURIComponent(`
                            <svg width="40" height="40" viewBox="0 0 40 40" xmlns="http://www.w3.org/2000/svg">
                                <circle cx="20" cy="20" r="18" fill="#e74c3c" stroke="white" stroke-width="3"/>
                                <text x="20" y="25" text-anchor="middle" fill="white" font-family="Arial" font-weight="bold" font-size="12">D</text>
                            </svg>
                        `),
                        scaledSize: new google.maps.Size(40, 40),
                        anchor: new google.maps.Point(20, 20)
                    }
                });
            }
        }

        // Update location information display
        function updateLocationInfo(position) {
            document.getElementById('current-lat').value = position[0].toFixed(6);
            document.getElementById('current-lng').value = position[1].toFixed(6);
            document.getElementById('last-update').value = new Date().toLocaleString();

            // Send location to server
            sendLocationToServer(position);
        }

        // Reverse geocode to get address using Google Maps
        function reverseGeocode(position) {
            const geocoder = new google.maps.Geocoder();
            geocoder.geocode({
                location: position
            }, (results, status) => {
                if (status === 'OK' && results[0]) {
                    document.getElementById('current-address').value = results[0].formatted_address;
                } else {
                    console.error('Geocoder failed:', status);
                }
            });
        }

        // Send location to server
        function sendLocationToServer(position) {
            const formData = new FormData();
            formData.append('update_location', '1');
            formData.append('latitude', position[0]);
            formData.append('longitude', position[1]);

            fetch('geolocation_tracker.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'success') {
                        addToHistory(position);
                        document.getElementById('status-last-update').textContent = new Date().toLocaleTimeString();
                        console.log('Location updated successfully at', new Date().toLocaleString());
                    } else {
                        console.error('Failed to update location:', data);
                    }
                })
                .catch(error => console.error('Error sending location:', error));
        }

        // Add location to history
        function addToHistory(position) {
            const timestamp = new Date().toLocaleTimeString();
            locationHistory.unshift({
                lat: position[0],
                lng: position[1],
                time: timestamp
            });

            // Keep only last 10 entries
            if (locationHistory.length > 10) {
                locationHistory = locationHistory.slice(0, 10);
            }

            updateHistoryDisplay();
        }

        // Update history display
        function updateHistoryDisplay() {
            const historyContainer = document.getElementById('location-history');

            if (locationHistory.length === 0) return;

            historyContainer.innerHTML = locationHistory.map((entry, index) => `
                <div class="list-group-item px-0">
                    <div class="d-flex align-items-center">
                        <div class="avatar avatar-sm me-3 bg-gradient-${index === 0 ? 'success' : 'secondary'}">
                            <i class="material-symbols-rounded text-white text-xs">location_on</i>
                        </div>
                        <div class="flex-grow-1">
                            <h6 class="mb-0 text-sm">${entry.lat.toFixed(4)}, ${entry.lng.toFixed(4)}</h6>
                            <p class="text-xs text-muted mb-0">${entry.time}</p>
                        </div>
                    </div>
                </div>
            `).join('');
        }

        // Start location tracking
        function startTracking() {
            if (navigator.geolocation) {
                const options = {
                    enableHighAccuracy: true,
                    timeout: 20000,
                    maximumAge: 30000
                };

                // First get current position to center map
                navigator.geolocation.getCurrentPosition(
                    (position) => {
                        const pos = {
                            lat: position.coords.latitude,
                            lng: position.coords.longitude
                        };
                        console.log('Initial position obtained:', pos);
                        map.setCenter(pos);
                        map.setZoom(18);
                        updateMarker(pos);
                        updateLocationInfo(pos);
                        reverseGeocode(pos);

                        // Then start watching for position changes
                        watchId = navigator.geolocation.watchPosition(
                            (position) => {
                                const newPos = {
                                    lat: position.coords.latitude,
                                    lng: position.coords.longitude
                                };
                                console.log('Position updated:', newPos);
                                updateMarker(newPos);
                                updateLocationInfo(newPos);
                                reverseGeocode(newPos);
                                // Center map on new position for real-time tracking
                                map.setCenter(newPos);
                            },
                            (error) => {
                                console.error('Geolocation watch error:', error);
                                if (error.code === error.PERMISSION_DENIED) {
                                    alert('Location permission was revoked. Please refresh the page and allow location access.');
                                }
                            },
                            options
                        );

                        // Update UI
                        document.getElementById('start-tracking').classList.add('d-none');
                        document.getElementById('stop-tracking').classList.remove('d-none');
                        document.getElementById('tracking-status').textContent = 'Tracking Active';
                        document.querySelector('.tracking-status').classList.remove('d-none');

                        console.log('Location tracking started successfully');
                    },
                    (error) => {
                        console.error('Initial geolocation error:', error);
                        let errorMessage = 'Unable to retrieve your location. ';
                        switch (error.code) {
                            case error.PERMISSION_DENIED:
                                errorMessage += 'Please allow location access in your browser settings.';
                                break;
                            case error.POSITION_UNAVAILABLE:
                                errorMessage += 'Location information is unavailable.';
                                break;
                            case error.TIMEOUT:
                                errorMessage += 'Location request timed out.';
                                break;
                            default:
                                errorMessage += 'Please check your browser settings.';
                                break;
                        }
                        alert(errorMessage);
                    },
                    options
                );
            } else {
                alert('Geolocation is not supported by this browser.');
            }
        }

        // Stop location tracking
        function stopTracking() {
            if (watchId) {
                navigator.geolocation.clearWatch(watchId);
                watchId = null;
            }

            document.getElementById('start-tracking').classList.remove('d-none');
            document.getElementById('stop-tracking').classList.add('d-none');
            document.getElementById('tracking-status').textContent = 'Tracking Stopped';
        }

        // Event listeners
        <?php if ($role === 'driver'): ?>
            document.getElementById('start-tracking').addEventListener('click', startTracking);
            document.getElementById('stop-tracking').addEventListener('click', stopTracking);
        <?php else: ?>
            document.getElementById('refresh-locations').addEventListener('click', () => {
                location.reload();
            });

            // Add search functionality for drivers
            const driverSearch = document.getElementById('driver-search');
            const clearSearch = document.getElementById('clear-search');
            const driverCards = document.querySelectorAll('#drivers-list .col-md-6, #drivers-list .col-lg-4');

            function filterDrivers(searchTerm) {
                const term = searchTerm.toLowerCase();
                driverCards.forEach(card => {
                    const driverName = card.querySelector('h6').textContent.toLowerCase();
                    const driverPhone = card.querySelector('small')?.textContent.toLowerCase() || '';
                    const isVisible = driverName.includes(term) || driverPhone.includes(term);
                    card.style.display = isVisible ? '' : 'none';
                });
            }

            driverSearch.addEventListener('input', function() {
                filterDrivers(this.value);
            });

            clearSearch.addEventListener('click', function() {
                driverSearch.value = '';
                filterDrivers('');
                driverSearch.focus();
            });

            // Real-time location updates for admin view
            function updateDriverLocations() {
                fetch('geolocation_tracker.php?action=get_locations')
                    .then(response => response.json())
                    .then(data => {
                        if (data.success && data.drivers) {
                            updateDriverMarkers(data.drivers);
                            updateDriverList(data.drivers);
                        }
                    })
                    .catch(error => console.error('Error updating locations:', error));
            }

            function updateDriverMarkers(drivers) {
                // Clear existing driver markers
                driverMarkers.forEach(marker => marker.setMap(null));
                driverMarkers = [];

                const bounds = new google.maps.LatLngBounds();
                drivers.forEach((driver, index) => {
                    if (driver.latitude && driver.longitude) {
                        const driverPos = {
                            lat: parseFloat(driver.latitude),
                            lng: parseFloat(driver.longitude)
                        };
                        bounds.extend(driverPos);

                        const marker = new google.maps.Marker({
                            position: driverPos,
                            map: map,
                            icon: {
                                url: 'data:image/svg+xml;charset=UTF-8,' + encodeURIComponent(`
                                    <svg width="40" height="40" viewBox="0 0 40 40" xmlns="http://www.w3.org/2000/svg">
                                        <circle cx="20" cy="20" r="18" fill="${driver.trip_status === 'in_progress' ? '#e74c3c' : '#27ae60'}" stroke="white" stroke-width="3"/>
                                        <text x="20" y="25" text-anchor="middle" fill="white" font-family="Arial" font-weight="bold" font-size="12">${driver.name.charAt(0).toUpperCase()}</text>
                                    </svg>
                                `),
                                scaledSize: new google.maps.Size(40, 40),
                                anchor: new google.maps.Point(20, 20)
                            }
                        });

                        const infoWindow = new google.maps.InfoWindow({
                            content: `
                                <div style="max-width: 200px;">
                                    <h6 style="margin: 0 0 5px 0;">${driver.name}</h6>
                                    <p style="margin: 0; font-size: 12px; color: #666;">
                                        Phone: ${driver.phone}<br>
                                        Status: ${driver.trip_status === 'in_progress' ? 'On Trip' : 'Available'}<br>
                                        Updated: ${driver.updated_at ? new Date(driver.updated_at).toLocaleTimeString() : 'Never'}<br>
                                        ${driver.trip_distance ? `Distance: ${parseFloat(driver.trip_distance).toFixed(2)} km<br>` : ''}
                                        <small>Lat: ${driver.latitude}, Lng: ${driver.longitude}</small>
                                    </p>
                                </div>
                            `
                        });

                        marker.addListener('click', () => {
                            infoWindow.open(map, marker);
                        });

                        driverMarkers.push(marker);
                    }
                });

                if (driverMarkers.length > 0) {
                    map.fitBounds(bounds, {
                        padding: 50
                    });
                }
            }

            function updateDriverList(drivers) {
                const driversList = document.getElementById('drivers-list');
                driversList.innerHTML = drivers.map(driver => `
                    <div class="col-md-6 col-lg-4 mb-3">
                        <div class="card h-100 border">
                            <div class="card-body p-3">
                                <div class="d-flex align-items-center mb-2">
                                    <div class="avatar avatar-sm me-3 bg-gradient-${driver.trip_status === 'in_progress' ? 'success' : 'secondary'}">
                                        <i class="material-symbols-rounded text-white text-xs">person</i>
                                    </div>
                                    <div class="flex-grow-1">
                                        <h6 class="mb-0 text-sm">${driver.name}</h6>
                                        <small class="text-muted">${driver.phone}</small>
                                    </div>
                                </div>
                                ${driver.latitude && driver.longitude ? `
                                    <div class="text-xs text-muted mb-2">
                                        <i class="fas fa-map-marker-alt me-1"></i>
                                        ${parseFloat(driver.latitude).toFixed(4)}, ${parseFloat(driver.longitude).toFixed(4)}
                                    </div>
                                    <div class="text-xs text-muted">
                                        <i class="fas fa-clock me-1"></i>
                                        Updated: ${driver.updated_at ? new Date(driver.updated_at).toLocaleTimeString() : 'Never'}
                                    </div>
                                    ${driver.trip_distance ? `
                                        <div class="text-xs text-muted mt-1">
                                            <i class="fas fa-route me-1"></i>
                                            Distance: ${parseFloat(driver.trip_distance).toFixed(2)} km
                                        </div>
                                    ` : ''}
                                ` : `
                                    <div class="text-xs text-muted">
                                        <i class="fas fa-exclamation-triangle me-1"></i>
                                        Location not available
                                    </div>
                                `}
                                ${driver.trip_status === 'in_progress' ? `
                                    <div class="mt-2">
                                        <span class="badge badge-sm bg-gradient-success">On Trip</span>
                                    </div>
                                ` : ''}
                            </div>
                        </div>
                    </div>
                `).join('');
            }

            // Update locations every 30 seconds for real-time tracking
            setInterval(updateDriverLocations, 30000);

            // Initial update after 5 seconds
            setTimeout(updateDriverLocations, 5000);
        <?php endif; ?>

        // Initialize map when page loads
        window.addEventListener('load', function() {
            console.log('Page loaded, initializing map...');
            initMap();
        });

        // Handle window resize
        window.addEventListener('resize', function() {
            console.log('Window resized, triggering resize...');
            if (map) {
                google.maps.event.trigger(map, 'resize');
            }
        });

        // Auto-start tracking for drivers on page load
        <?php if ($role === 'driver'): ?>
            console.log('Setting up auto-tracking for driver...');
            // Auto-start tracking immediately for drivers
            setTimeout(() => {
                console.log('Auto-starting driver tracking...');
                startTracking();
            }, 1000); // Short delay to ensure map is loaded
        <?php endif; ?>
    </script>
</body>

</html>
