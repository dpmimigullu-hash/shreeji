<?php
session_start();
require_once 'includes/config.php';
require_once 'includes/functions.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || getUserById($_SESSION['user_id'])['role'] != 'admin') {
    header('Location: login.php');
    exit;
}

$user = getUserById($_SESSION['user_id']);
$role = $user['role'] ?? 'admin';

$conn = getDBConnection();

// Handle export requests
if (isset($_GET['export'])) {
    $export_type = $_GET['export'];
    $start_date = $_GET['start_date'] ?? date('Y-m-01');
    $end_date = $_GET['end_date'] ?? date('Y-m-d');

    switch ($export_type) {
        case 'trips':
            exportTripsReport($start_date, $end_date);
            break;
        case 'drivers':
            exportDriversReport($start_date, $end_date);
            break;
        case 'vehicles':
            exportVehiclesReport($start_date, $end_date);
            break;
        case 'financial':
            exportFinancialReport($start_date, $end_date);
            break;
        case 'clients':
            exportClientsReport($start_date, $end_date);
            break;
    }
    exit;
}

$report_type = isset($_GET['type']) ? $_GET['type'] : 'dashboard';
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01');
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');
$period = isset($_GET['period']) ? $_GET['period'] : 'current_month';

$report_data = [];

// Generate report data based on type
switch ($report_type) {
    case 'dashboard':
        $report_data = getDashboardMetrics($start_date, $end_date);
        break;
    case 'trips':
        $report_data = getTripsReport($start_date, $end_date);
        break;
    case 'drivers':
        $report_data = getDriversReport($start_date, $end_date);
        break;
    case 'vehicles':
        $report_data = getVehiclesReport($start_date, $end_date);
        break;
    case 'financial':
        $report_data = getFinancialReport($start_date, $end_date);
        break;
    case 'clients':
        $report_data = getClientsReport($start_date, $end_date);
        break;
    case 'performance':
        $report_data = getPerformanceReport($start_date, $end_date);
        break;
}

// Helper functions for reports
function getDashboardMetrics($start_date, $end_date)
{
    global $conn;

    $metrics = [];

    // Total trips
    $stmt = $conn->prepare("SELECT COUNT(*) as total_trips FROM trips WHERE DATE(created_at) BETWEEN ? AND ?");
    $stmt->execute([$start_date, $end_date]);
    $metrics['total_trips'] = $stmt->fetchColumn();

    // Completed trips
    $stmt = $conn->prepare("SELECT COUNT(*) as completed_trips FROM trips WHERE status = 'completed' AND DATE(created_at) BETWEEN ? AND ?");
    $stmt->execute([$start_date, $end_date]);
    $metrics['completed_trips'] = $stmt->fetchColumn();

    // Total drivers
    $stmt = $conn->query("SELECT COUNT(*) as total_drivers FROM users WHERE role = 'driver'");
    $metrics['total_drivers'] = $stmt->fetchColumn();

    // Active drivers
    $stmt = $conn->query("SELECT COUNT(*) as active_drivers FROM users WHERE role = 'driver' AND status = 'active'");
    $metrics['active_drivers'] = $stmt->fetchColumn();

    // Total revenue
    $stmt = $conn->prepare("SELECT COALESCE(SUM(amount), 0) as total_revenue FROM billing WHERE DATE(created_at) BETWEEN ? AND ?");
    $stmt->execute([$start_date, $end_date]);
    $metrics['total_revenue'] = $stmt->fetchColumn();

    // Average trip distance
    $stmt = $conn->prepare("SELECT AVG(distance) as avg_distance FROM trips WHERE distance > 0 AND DATE(created_at) BETWEEN ? AND ?");
    $stmt->execute([$start_date, $end_date]);
    $metrics['avg_distance'] = round($stmt->fetchColumn(), 2);

    return $metrics;
}

function getTripsReport($start_date, $end_date)
{
    global $conn;

    $stmt = $conn->prepare("
        SELECT
            t.*,
            u.name as driver_name,
            v.make, v.model, v.license_plate,
            s.name as supervisor_name,
            c.name as client_name,
            b.amount
        FROM trips t
        LEFT JOIN users u ON t.driver_id = u.id
        LEFT JOIN vehicles v ON t.vehicle_id = v.id
        LEFT JOIN users s ON t.supervisor_id = s.id
        LEFT JOIN clients c ON t.client_id = c.id
        LEFT JOIN billing b ON t.id = b.trip_id
        WHERE DATE(t.created_at) BETWEEN ? AND ?
        ORDER BY t.created_at DESC
    ");
    $stmt->execute([$start_date, $end_date]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getDriversReport($start_date, $end_date)
{
    global $conn;

    $stmt = $conn->prepare("
        SELECT
            u.name,
            u.phone,
            u.license_number,
            COUNT(t.id) as total_trips,
            COALESCE(SUM(t.distance), 0) as total_distance,
            COALESCE(SUM(b.amount), 0) as total_revenue,
            AVG(t.distance) as avg_distance,
            s.name as supervisor_name
        FROM users u
        LEFT JOIN trips t ON u.id = t.driver_id AND DATE(t.created_at) BETWEEN ? AND ?
        LEFT JOIN billing b ON t.id = b.trip_id
        LEFT JOIN users s ON u.supervisor_id = s.id
        WHERE u.role = 'driver'
        GROUP BY u.id, u.name, u.phone, u.license_number, s.name
        ORDER BY total_trips DESC
    ");
    $stmt->execute([$start_date, $end_date]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getVehiclesReport($start_date, $end_date)
{
    global $conn;

    $stmt = $conn->prepare("
        SELECT
            v.make, v.model, v.license_plate, v.year,
            COUNT(t.id) as total_trips,
            COALESCE(SUM(t.distance), 0) as total_distance,
            COALESCE(SUM(b.amount), 0) as total_revenue,
            AVG(t.distance) as avg_distance,
            u.name as driver_name
        FROM vehicles v
        LEFT JOIN trips t ON v.id = t.vehicle_id AND DATE(t.created_at) BETWEEN ? AND ?
        LEFT JOIN billing b ON t.id = b.trip_id
        LEFT JOIN users u ON v.driver_id = u.id
        GROUP BY v.id, v.make, v.model, v.license_plate, v.year, u.name
        ORDER BY total_trips DESC
    ");
    $stmt->execute([$start_date, $end_date]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getFinancialReport($start_date, $end_date)
{
    global $conn;

    $stmt = $conn->prepare("
        SELECT
            DATE(b.created_at) as date,
            COUNT(b.id) as transactions,
            SUM(b.amount) as total_amount,
            AVG(b.amount) as avg_amount,
            c.name as client_name
        FROM billing b
        LEFT JOIN trips t ON b.trip_id = t.id
        LEFT JOIN clients c ON t.client_id = c.id
        WHERE DATE(b.created_at) BETWEEN ? AND ?
        GROUP BY DATE(b.created_at), c.name
        ORDER BY date DESC
    ");
    $stmt->execute([$start_date, $end_date]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getClientsReport($start_date, $end_date)
{
    global $conn;

    $stmt = $conn->prepare("
        SELECT
            c.name as client_name,
            c.email,
            c.phone,
            COUNT(t.id) as total_trips,
            COALESCE(SUM(t.distance), 0) as total_distance,
            COALESCE(SUM(b.amount), 0) as total_revenue,
            AVG(t.distance) as avg_distance
        FROM clients c
        LEFT JOIN trips t ON c.id = t.client_id AND DATE(t.created_at) BETWEEN ? AND ?
        LEFT JOIN billing b ON t.id = b.trip_id
        GROUP BY c.id, c.name, c.email, c.phone
        ORDER BY total_revenue DESC
    ");
    $stmt->execute([$start_date, $end_date]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getPerformanceReport($start_date, $end_date)
{
    global $conn;

    $stmt = $conn->prepare("
        SELECT
            u.name as driver_name,
            COUNT(t.id) as trips_completed,
            COALESCE(SUM(t.distance), 0) as total_distance,
            COALESCE(SUM(b.amount), 0) as total_revenue,
            AVG(t.distance) as avg_trip_distance,
            COUNT(CASE WHEN t.status = 'completed' THEN 1 END) as on_time_trips,
            ROUND(
                (COUNT(CASE WHEN t.status = 'completed' THEN 1 END) * 100.0 / COUNT(t.id)), 2
            ) as performance_rate
        FROM users u
        LEFT JOIN trips t ON u.id = t.driver_id AND DATE(t.created_at) BETWEEN ? AND ?
        LEFT JOIN billing b ON t.id = b.trip_id
        WHERE u.role = 'driver'
        GROUP BY u.id, u.name
        HAVING trips_completed > 0
        ORDER BY performance_rate DESC, total_revenue DESC
    ");
    $stmt->execute([$start_date, $end_date]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Export functions
function exportTripsReport($start_date, $end_date)
{
    global $conn;

    $data = getTripsReport($start_date, $end_date);

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=trips_report_' . $start_date . '_to_' . $end_date . '.csv');

    $output = fopen('php://output', 'w');
    fputcsv($output, ['Trip ID', 'Date', 'Driver', 'Vehicle', 'Client', 'Supervisor', 'Distance', 'Amount', 'Status', 'Start Time', 'End Time']);

    foreach ($data as $row) {
        fputcsv($output, [
            $row['id'],
            date('d/m/Y', strtotime($row['created_at'])),
            $row['driver_name'],
            $row['make'] . ' ' . $row['model'] . ' (' . $row['license_plate'] . ')',
            $row['client_name'] ?? 'N/A',
            $row['supervisor_name'] ?? 'N/A',
            $row['distance'] ?? 0,
            $row['amount'] ?? 0,
            $row['status'],
            $row['start_time'] ? date('H:i', strtotime($row['start_time'])) : 'N/A',
            $row['end_time'] ? date('H:i', strtotime($row['end_time'])) : 'N/A'
        ]);
    }
    fclose($output);
}

function exportDriversReport($start_date, $end_date)
{
    $data = getDriversReport($start_date, $end_date);

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=drivers_report_' . $start_date . '_to_' . $end_date . '.csv');

    $output = fopen('php://output', 'w');
    fputcsv($output, ['Driver Name', 'Phone', 'License', 'Supervisor', 'Total Trips', 'Total Distance', 'Total Revenue', 'Avg Distance']);

    foreach ($data as $row) {
        fputcsv($output, [
            $row['name'],
            $row['phone'],
            $row['license_number'],
            $row['supervisor_name'] ?? 'N/A',
            $row['total_trips'],
            $row['total_distance'],
            $row['total_revenue'],
            round($row['avg_distance'], 2)
        ]);
    }
    fclose($output);
}

function exportVehiclesReport($start_date, $end_date)
{
    $data = getVehiclesReport($start_date, $end_date);

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=vehicles_report_' . $start_date . '_to_' . $end_date . '.csv');

    $output = fopen('php://output', 'w');
    fputcsv($output, ['Vehicle', 'Year', 'Driver', 'Total Trips', 'Total Distance', 'Total Revenue', 'Avg Distance']);

    foreach ($data as $row) {
        fputcsv($output, [
            $row['make'] . ' ' . $row['model'] . ' (' . $row['license_plate'] . ')',
            $row['year'],
            $row['driver_name'] ?? 'Unassigned',
            $row['total_trips'],
            $row['total_distance'],
            $row['total_revenue'],
            round($row['avg_distance'], 2)
        ]);
    }
    fclose($output);
}

function exportFinancialReport($start_date, $end_date)
{
    $data = getFinancialReport($start_date, $end_date);

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=financial_report_' . $start_date . '_to_' . $end_date . '.csv');

    $output = fopen('php://output', 'w');
    fputcsv($output, ['Date', 'Client', 'Transactions', 'Total Amount', 'Average Amount']);

    foreach ($data as $row) {
        fputcsv($output, [
            date('d/m/Y', strtotime($row['date'])),
            $row['client_name'] ?? 'N/A',
            $row['transactions'],
            $row['total_amount'],
            round($row['avg_amount'], 2)
        ]);
    }
    fclose($output);
}

function exportClientsReport($start_date, $end_date)
{
    $data = getClientsReport($start_date, $end_date);

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=clients_report_' . $start_date . '_to_' . $end_date . '.csv');

    $output = fopen('php://output', 'w');
    fputcsv($output, ['Client Name', 'Email', 'Phone', 'Total Trips', 'Total Distance', 'Total Revenue', 'Avg Distance']);

    foreach ($data as $row) {
        fputcsv($output, [
            $row['client_name'],
            $row['email'],
            $row['phone'],
            $row['total_trips'],
            $row['total_distance'],
            $row['total_revenue'],
            round($row['avg_distance'], 2)
        ]);
    }
    fclose($output);
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=5.0, user-scalable=yes">
    <link rel="apple-touch-icon" sizes="76x76" href="./assets/img/apple-icon.png">
    <link rel="icon" type="image/png" href="./assets/images/logo.png">
    <title>Reports & Analytics - Shreeji Link Employee Transportation System</title>
    <link rel="stylesheet" type="text/css" href="https://fonts.googleapis.com/css?family=Inter:300,400,500,600,700,900" />
    <link href="./assets/nucleo-icons.css" rel="stylesheet" />
    <link href="./assets/nucleo-svg.css" rel="stylesheet" />
    <script src="https://kit.fontawesome.com/42d5adcbca.js" crossorigin="anonymous"></script>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded:opsz,wght,FILL,GRAD@24,400,0,0" />
    <link id="pagestyle" href="./assets/material-dashboard.css?v=3.2.0" rel="stylesheet" />
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
                        <a class="nav-link text-dark" href="geolocation_tracker.php">
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
                <?php if ($role == 'admin'): ?>
                    <li class="nav-item">
                        <a class="nav-link active bg-gradient-dark text-white" href="reports.php">
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
                    <ol class="breadcrumb bg-transparent mb-0 pb-0 pt-1 px-0 me-sm-6 me-5">
                        <li class="breadcrumb-item text-sm"><a class="opacity-5 text-dark" href="javascript:;">Pages</a></li>
                        <li class="breadcrumb-item text-sm text-dark active" aria-current="page">Reports</li>
                    </ol>
                    <h6 class="font-weight-bolder mb-0">Reports & Analytics</h6>
                </nav>
                <div class="collapse navbar-collapse mt-sm-0 mt-2 me-md-0 me-sm-4" id="navbar">
                    <div class="ms-md-auto pe-md-3 d-flex align-items-center">
                        <div class="input-group input-group-outline">
                            <label class="form-label">Type here...</label>
                            <input type="text" class="form-control">
                        </div>
                    </div>
                    <ul class="navbar-nav d-flex align-items-center justify-content-end">
                        <li class="nav-item d-flex align-items-center">
                            <a class="btn btn-outline-primary btn-sm mb-0 me-3" target="_blank" href="#">Online Builder</a>
                        </li>
                        <li class="mt-1">
                            <a class="github-button" href="https://github.com/creativetimofficial/material-dashboard" data-icon="octicon-star" data-size="large" data-show-count="true" aria-label="Star creativetimofficial/material-dashboard on GitHub">Star</a>
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
                        <li class="nav-item px-3 d-flex align-items-center">
                            <a href="javascript:;" class="nav-link text-body p-0">
                                <i class="material-symbols-rounded fixed-plugin-button-nav">settings</i>
                            </a>
                        </li>
                        <li class="nav-item dropdown pe-3 d-flex align-items-center">
                            <a href="javascript:;" class="nav-link text-body p-0" id="dropdownMenuButton" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="material-symbols-rounded">notifications</i>
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end px-2 py-3 me-sm-n4" aria-labelledby="dropdownMenuButton">
                                <li class="mb-2">
                                    <a class="dropdown-item border-radius-md" href="javascript:;">
                                        <div class="d-flex py-1">
                                            <div class="my-auto">
                                                <img src="./assets/img/team-2.jpg" class="avatar avatar-sm me-3" alt="team1">
                                            </div>
                                            <div class="d-flex flex-column justify-content-center">
                                                <h6 class="text-sm font-weight-normal mb-1">
                                                    <span class="font-weight-bold">New message</span> from Laur
                                                </h6>
                                                <p class="text-xs text-secondary mb-0">
                                                    <i class="fa fa-clock me-1"></i>
                                                    13 minutes ago
                                                </p>
                                            </div>
                                        </div>
                                    </a>
                                </li>
                                <li class="mb-2">
                                    <a class="dropdown-item border-radius-md" href="javascript:;">
                                        <div class="d-flex py-1">
                                            <div class="my-auto">
                                                <img src="./assets/img/small-logos/logo-spotify.svg" class="avatar avatar-sm bg-gradient-dark me-3">
                                            </div>
                                            <div class="d-flex flex-column justify-content-center">
                                                <h6 class="text-sm font-weight-normal mb-1">
                                                    <span class="font-weight-bold">New album</span> by Travis Scott
                                                </h6>
                                                <p class="text-xs text-secondary mb-0">
                                                    <i class="fa fa-clock me-1"></i>
                                                    1 day
                                                </p>
                                            </div>
                                        </div>
                                    </a>
                                </li>
                                <li>
                                    <a class="dropdown-item border-radius-md" href="javascript:;">
                                        <div class="d-flex py-1">
                                            <div class="my-auto">
                                                <img src="./assets/img/small-logos/logo-invision.svg" class="avatar avatar-sm bg-gradient-danger me-3">
                                            </div>
                                            <div class="d-flex flex-column justify-content-center">
                                                <h6 class="text-sm font-weight-normal mb-1">
                                                    Payment successfully completed
                                                </h6>
                                                <p class="text-xs text-secondary mb-0">
                                                    <i class="fa fa-clock me-1"></i>
                                                    2 days
                                                </p>
                                            </div>
                                        </div>
                                    </a>
                                </li>
                            </ul>
                        </li>
                        <li class="nav-item d-flex align-items-center">
                            <a href="#" class="nav-link text-body font-weight-bold px-0">
                                <i class="material-symbols-rounded">account_circle</i>
                            </a>
                        </li>
                    </ul>
                </div>
            </div>
        </nav>

        <div class="container-fluid py-2">
            <!-- Dashboard Metrics Cards -->
            <?php if ($report_type == 'dashboard'): ?>
                <div class="row mb-4">
                    <div class="col-xl-3 col-md-6 mb-3">
                        <div class="card border-left-primary shadow h-100 py-2">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Total Trips</div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $report_data['total_trips'] ?? 0; ?></div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-route fa-2x text-primary"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-xl-3 col-md-6 mb-3">
                        <div class="card border-left-success shadow h-100 py-2">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Completed Trips</div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $report_data['completed_trips'] ?? 0; ?></div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-check-circle fa-2x text-success"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-xl-3 col-md-6 mb-3">
                        <div class="card border-left-info shadow h-100 py-2">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-info text-uppercase mb-1">Total Revenue</div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800">₹<?php echo number_format($report_data['total_revenue'] ?? 0, 2); ?></div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-rupee-sign fa-2x text-info"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-xl-3 col-md-6 mb-3">
                        <div class="card border-left-warning shadow h-100 py-2">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Avg Distance</div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $report_data['avg_distance'] ?? 0; ?> km</div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-road fa-2x text-warning"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row mb-4">
                    <div class="col-xl-6 col-md-6 mb-3">
                        <div class="card border-left-secondary shadow h-100 py-2">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-secondary text-uppercase mb-1">Total Drivers</div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $report_data['total_drivers'] ?? 0; ?></div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-users fa-2x text-secondary"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-xl-6 col-md-6 mb-3">
                        <div class="card border-left-danger shadow h-100 py-2">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">Active Drivers</div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $report_data['active_drivers'] ?? 0; ?></div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-user-check fa-2x text-danger"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Report Filters -->
            <div class="row">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header pb-0 p-3 d-flex justify-content-between align-items-center">
                            <h6>Report Filters & Types</h6>
                            <div class="d-flex gap-2">
                                <?php if ($report_type != 'dashboard'): ?>
                                    <a href="?export=<?php echo $report_type; ?>&start_date=<?php echo $start_date; ?>&end_date=<?php echo $end_date; ?>" class="btn btn-success btn-sm">
                                        <i class="fas fa-download me-1"></i>Export CSV
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="card-body p-3">
                            <!-- Quick Date Range Buttons -->
                            <div class="row mb-3">
                                <div class="col-12">
                                    <label class="form-control-label mb-2">Quick Date Ranges:</label>
                                    <div class="d-flex flex-wrap gap-2">
                                        <button type="button" class="btn btn-outline-primary btn-sm" onclick="setDateRange('today')">Today</button>
                                        <button type="button" class="btn btn-outline-primary btn-sm" onclick="setDateRange('yesterday')">Yesterday</button>
                                        <button type="button" class="btn btn-outline-primary btn-sm" onclick="setDateRange('last7days')">Last 7 Days</button>
                                        <button type="button" class="btn btn-outline-primary btn-sm" onclick="setDateRange('last30days')">Last 30 Days</button>
                                        <button type="button" class="btn btn-outline-primary btn-sm" onclick="setDateRange('thisMonth')">This Month</button>
                                        <button type="button" class="btn btn-outline-primary btn-sm" onclick="setDateRange('lastMonth')">Last Month</button>
                                    </div>
                                </div>
                            </div>

                            <form method="GET" action="">
                                <div class="row">
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label for="type" class="form-control-label">Report Type</label>
                                            <select class="form-control" id="type" name="type">
                                                <option value="dashboard" <?php echo $report_type == 'dashboard' ? 'selected' : ''; ?>>📊 Dashboard Overview</option>
                                                <option value="trips" <?php echo $report_type == 'trips' ? 'selected' : ''; ?>>🚗 Trip Details</option>
                                                <option value="drivers" <?php echo $report_type == 'drivers' ? 'selected' : ''; ?>>👥 Driver Performance</option>
                                                <option value="vehicles" <?php echo $report_type == 'vehicles' ? 'selected' : ''; ?>>🚙 Vehicle Utilization</option>
                                                <option value="financial" <?php echo $report_type == 'financial' ? 'selected' : ''; ?>>💰 Financial Report</option>
                                                <option value="clients" <?php echo $report_type == 'clients' ? 'selected' : ''; ?>>🏢 Client Analysis</option>
                                                <option value="performance" <?php echo $report_type == 'performance' ? 'selected' : ''; ?>>📈 Performance Metrics</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label for="start_date" class="form-control-label">Start Date</label>
                                            <input class="form-control" type="date" id="start_date" name="start_date" value="<?php echo $start_date; ?>" required>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label for="end_date" class="form-control-label">End Date</label>
                                            <input class="form-control" type="date" id="end_date" name="end_date" value="<?php echo $end_date; ?>" required>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label class="form-control-label">&nbsp;</label>
                                            <div>
                                                <button type="submit" class="btn bg-gradient-dark w-100">
                                                    <i class="fas fa-search me-1"></i>Generate Report
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Report Results -->
            <?php if ($report_type != 'dashboard'): ?>
                <div class="row mt-4">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header pb-0 p-3 d-flex justify-content-between align-items-center">
                                <h6><?php
                                    $reportTitles = [
                                        'trips' => 'Trip Details Report',
                                        'drivers' => 'Driver Performance Report',
                                        'vehicles' => 'Vehicle Utilization Report',
                                        'financial' => 'Financial Report',
                                        'clients' => 'Client Analysis Report',
                                        'performance' => 'Performance Metrics Report'
                                    ];
                                    echo $reportTitles[$report_type] ?? ucfirst($report_type) . ' Report';
                                    ?> (<?php echo date('d/m/Y', strtotime($start_date)); ?> - <?php echo date('d/m/Y', strtotime($end_date)); ?>)</h6>
                                <small class="text-muted"><?php echo count($report_data); ?> records found</small>
                            </div>
                            <div class="card-body px-0 pb-2">
                                <div class="table-responsive">
                                    <?php if ($report_type == 'trips'): ?>
                                        <table class="table align-items-center mb-0">
                                            <thead>
                                                <tr>
                                                    <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Trip ID</th>
                                                    <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">Date</th>
                                                    <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">Driver</th>
                                                    <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">Vehicle</th>
                                                    <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">Client</th>
                                                    <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">Distance</th>
                                                    <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">Amount</th>
                                                    <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">Status</th>
                                                    <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">Start Time</th>
                                                    <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">End Time</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($report_data as $row): ?>
                                                    <tr>
                                                        <td>
                                                            <p class="text-xs font-weight-bold mb-0"><?php echo $row['id']; ?></p>
                                                        </td>
                                                        <td>
                                                            <p class="text-xs font-weight-bold mb-0"><?php echo date('d/m/Y', strtotime($row['created_at'])); ?></p>
                                                        </td>
                                                        <td>
                                                            <div class="d-flex px-2 py-1">
                                                                <div><i class="material-symbols-rounded text-secondary">person</i></div>
                                                                <div class="d-flex flex-column justify-content-center">
                                                                    <h6 class="mb-0 text-sm"><?php echo htmlspecialchars($row['driver_name']); ?></h6>
                                                                </div>
                                                            </div>
                                                        </td>
                                                        <td>
                                                            <div class="d-flex px-2 py-1">
                                                                <div><i class="material-symbols-rounded text-secondary">directions_car</i></div>
                                                                <div class="d-flex flex-column justify-content-center">
                                                                    <h6 class="mb-0 text-sm"><?php echo htmlspecialchars(($row['make'] ?? '') . ' ' . ($row['model'] ?? '') . ' (' . ($row['license_plate'] ?? 'N/A') . ')'); ?></h6>
                                                                </div>
                                                            </div>
                                                        </td>
                                                        <td>
                                                            <p class="text-xs font-weight-bold mb-0"><?php echo htmlspecialchars($row['client_name'] ?? 'N/A'); ?></p>
                                                        </td>
                                                        <td>
                                                            <p class="text-xs font-weight-bold mb-0"><?php echo $row['distance'] ?? 0; ?> km</p>
                                                        </td>
                                                        <td>
                                                            <p class="text-xs font-weight-bold mb-0">₹<?php echo number_format($row['amount'] ?? 0, 2); ?></p>
                                                        </td>
                                                        <td><span class="badge badge-sm bg-gradient-<?php echo $row['status'] == 'completed' ? 'success' : ($row['status'] == 'in_progress' ? 'warning' : 'secondary'); ?>"><?php echo ucfirst(str_replace('_', ' ', $row['status'] ?? 'pending')); ?></span></td>
                                                        <td>
                                                            <p class="text-xs font-weight-bold mb-0"><?php echo $row['start_time'] ? date('H:i', strtotime($row['start_time'])) : 'N/A'; ?></p>
                                                        </td>
                                                        <td>
                                                            <p class="text-xs font-weight-bold mb-0"><?php echo $row['end_time'] ? date('H:i', strtotime($row['end_time'])) : 'N/A'; ?></p>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    <?php elseif ($report_type == 'drivers'): ?>
                                        <table class="table align-items-center mb-0">
                                            <thead>
                                                <tr>
                                                    <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Driver</th>
                                                    <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">Phone</th>
                                                    <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">License</th>
                                                    <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">Supervisor</th>
                                                    <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">Total Trips</th>
                                                    <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">Total Distance</th>
                                                    <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">Total Revenue</th>
                                                    <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">Avg Distance</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($report_data as $row): ?>
                                                    <tr>
                                                        <td>
                                                            <div class="d-flex px-2 py-1">
                                                                <div><i class="material-symbols-rounded text-secondary">person</i></div>
                                                                <div class="d-flex flex-column justify-content-center">
                                                                    <h6 class="mb-0 text-sm"><?php echo htmlspecialchars($row['name']); ?></h6>
                                                                </div>
                                                            </div>
                                                        </td>
                                                        <td>
                                                            <p class="text-xs font-weight-bold mb-0"><?php echo htmlspecialchars($row['phone']); ?></p>
                                                        </td>
                                                        <td><code class="text-xs"><?php echo htmlspecialchars($row['license_number']); ?></code></td>
                                                        <td>
                                                            <p class="text-xs font-weight-bold mb-0"><?php echo htmlspecialchars($row['supervisor_name'] ?? 'Not assigned'); ?></p>
                                                        </td>
                                                        <td>
                                                            <p class="text-xs font-weight-bold mb-0"><?php echo $row['total_trips']; ?></p>
                                                        </td>
                                                        <td>
                                                            <p class="text-xs font-weight-bold mb-0"><?php echo number_format($row['total_distance'], 1); ?> km</p>
                                                        </td>
                                                        <td>
                                                            <p class="text-xs font-weight-bold mb-0">₹<?php echo number_format($row['total_revenue'], 2); ?></p>
                                                        </td>
                                                        <td>
                                                            <p class="text-xs font-weight-bold mb-0"><?php echo number_format($row['avg_distance'] ?? 0, 1); ?> km</p>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    <?php elseif ($report_type == 'vehicles'): ?>
                                        <table class="table align-items-center mb-0">
                                            <thead>
                                                <tr>
                                                    <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Vehicle</th>
                                                    <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">Year</th>
                                                    <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">Driver</th>
                                                    <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">Total Trips</th>
                                                    <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">Total Distance</th>
                                                    <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">Total Revenue</th>
                                                    <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">Avg Distance</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($report_data as $row): ?>
                                                    <tr>
                                                        <td>
                                                            <div class="d-flex px-2 py-1">
                                                                <div><i class="material-symbols-rounded text-secondary">directions_car</i></div>
                                                                <div class="d-flex flex-column justify-content-center">
                                                                    <h6 class="mb-0 text-sm"><?php echo htmlspecialchars(($row['make'] ?? '') . ' ' . ($row['model'] ?? '') . ' (' . ($row['license_plate'] ?? 'N/A') . ')'); ?></h6>
                                                                </div>
                                                            </div>
                                                        </td>
                                                        <td>
                                                            <p class="text-xs font-weight-bold mb-0"><?php echo $row['year'] ?? 'N/A'; ?></p>
                                                        </td>
                                                        <td>
                                                            <p class="text-xs font-weight-bold mb-0"><?php echo htmlspecialchars($row['driver_name'] ?? 'Unassigned'); ?></p>
                                                        </td>
                                                        <td>
                                                            <p class="text-xs font-weight-bold mb-0"><?php echo $row['total_trips']; ?></p>
                                                        </td>
                                                        <td>
                                                            <p class="text-xs font-weight-bold mb-0"><?php echo number_format($row['total_distance'], 1); ?> km</p>
                                                        </td>
                                                        <td>
                                                            <p class="text-xs font-weight-bold mb-0">₹<?php echo number_format($row['total_revenue'], 2); ?></p>
                                                        </td>
                                                        <td>
                                                            <p class="text-xs font-weight-bold mb-0"><?php echo number_format($row['avg_distance'] ?? 0, 1); ?> km</p>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    <?php elseif ($report_type == 'financial'): ?>
                                        <table class="table align-items-center mb-0">
                                            <thead>
                                                <tr>
                                                    <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Date</th>
                                                    <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">Client</th>
                                                    <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">Transactions</th>
                                                    <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">Total Amount</th>
                                                    <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">Average Amount</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($report_data as $row): ?>
                                                    <tr>
                                                        <td>
                                                            <p class="text-xs font-weight-bold mb-0"><?php echo date('d/m/Y', strtotime($row['date'])); ?></p>
                                                        </td>
                                                        <td>
                                                            <p class="text-xs font-weight-bold mb-0"><?php echo htmlspecialchars($row['client_name'] ?? 'N/A'); ?></p>
                                                        </td>
                                                        <td>
                                                            <p class="text-xs font-weight-bold mb-0"><?php echo $row['transactions']; ?></p>
                                                        </td>
                                                        <td>
                                                            <p class="text-xs font-weight-bold mb-0">₹<?php echo number_format($row['total_amount'], 2); ?></p>
                                                        </td>
                                                        <td>
                                                            <p class="text-xs font-weight-bold mb-0">₹<?php echo number_format($row['avg_amount'], 2); ?></p>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    <?php elseif ($report_type == 'clients'): ?>
                                        <table class="table align-items-center mb-0">
                                            <thead>
                                                <tr>
                                                    <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Client Name</th>
                                                    <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">Email</th>
                                                    <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">Phone</th>
                                                    <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">Total Trips</th>
                                                    <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">Total Distance</th>
                                                    <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">Total Revenue</th>
                                                    <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">Avg Distance</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($report_data as $row): ?>
                                                    <tr>
                                                        <td>
                                                            <div class="d-flex px-2 py-1">
                                                                <div><i class="material-symbols-rounded text-secondary">business</i></div>
                                                                <div class="d-flex flex-column justify-content-center">
                                                                    <h6 class="mb-0 text-sm"><?php echo htmlspecialchars($row['client_name']); ?></h6>
                                                                </div>
                                                            </div>
                                                        </td>
                                                        <td>
                                                            <p class="text-xs font-weight-bold mb-0"><?php echo htmlspecialchars($row['email']); ?></p>
                                                        </td>
                                                        <td>
                                                            <p class="text-xs font-weight-bold mb-0"><?php echo htmlspecialchars($row['phone']); ?></p>
                                                        </td>
                                                        <td>
                                                            <p class="text-xs font-weight-bold mb-0"><?php echo $row['total_trips']; ?></p>
                                                        </td>
                                                        <td>
                                                            <p class="text-xs font-weight-bold mb-0"><?php echo number_format($row['total_distance'], 1); ?> km</p>
                                                        </td>
                                                        <td>
                                                            <p class="text-xs font-weight-bold mb-0">₹<?php echo number_format($row['total_revenue'], 2); ?></p>
                                                        </td>
                                                        <td>
                                                            <p class="text-xs font-weight-bold mb-0"><?php echo number_format($row['avg_distance'] ?? 0, 1); ?> km</p>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    <?php elseif ($report_type == 'performance'): ?>
                                        <table class="table align-items-center mb-0">
                                            <thead>
                                                <tr>
                                                    <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Driver</th>
                                                    <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">Trips Completed</th>
                                                    <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">Total Distance</th>
                                                    <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">Total Revenue</th>
                                                    <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">Avg Trip Distance</th>
                                                    <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">On-Time Trips</th>
                                                    <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">Performance Rate</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($report_data as $row): ?>
                                                    <tr>
                                                        <td>
                                                            <div class="d-flex px-2 py-1">
                                                                <div><i class="material-symbols-rounded text-secondary">person</i></div>
                                                                <div class="d-flex flex-column justify-content-center">
                                                                    <h6 class="mb-0 text-sm"><?php echo htmlspecialchars($row['driver_name']); ?></h6>
                                                                </div>
                                                            </div>
                                                        </td>
                                                        <td>
                                                            <p class="text-xs font-weight-bold mb-0"><?php echo $row['trips_completed']; ?></p>
                                                        </td>
                                                        <td>
                                                            <p class="text-xs font-weight-bold mb-0"><?php echo number_format($row['total_distance'], 1); ?> km</p>
                                                        </td>
                                                        <td>
                                                            <p class="text-xs font-weight-bold mb-0">₹<?php echo number_format($row['total_revenue'], 2); ?></p>
                                                        </td>
                                                        <td>
                                                            <p class="text-xs font-weight-bold mb-0"><?php echo number_format($row['avg_trip_distance'] ?? 0, 1); ?> km</p>
                                                        </td>
                                                        <td>
                                                            <p class="text-xs font-weight-bold mb-0"><?php echo $row['on_time_trips']; ?></p>
                                                        </td>
                                                        <td>
                                                            <span class="badge badge-sm bg-gradient-<?php echo $row['performance_rate'] >= 80 ? 'success' : ($row['performance_rate'] >= 60 ? 'warning' : 'danger'); ?>">
                                                                <?php echo number_format($row['performance_rate'], 1); ?>%
                                                            </span>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <div class="fixed-plugin">
        <a class="fixed-plugin-button text-dark position-fixed px-3 py-2">
            <i class="material-symbols-rounded py-2">settings</i>
        </a>
        <div class="card shadow-lg">
            <div class="card-header pb-0 pt-3">
                <div class="float-start">
                    <h5 class="mt-3 mb-0">MS Infosystems Configurator</h5>
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
                <a class="btn bg-gradient-info w-100" href="https://shreejilinkcorporate.com">Visit Shreeji Link</a>
                <div class="w-100 text-center">
                    <h6 class="mt-3">Employee Transportation System</h6>
                    <a href="https://shreejilinkcorporate.com" class="btn btn-dark mb-0 me-2" target="_blank">
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
        // Set default date range to current month
        document.addEventListener('DOMContentLoaded', function() {
            const today = new Date();
            const firstDay = new Date(today.getFullYear(), today.getMonth(), 1);
            const lastDay = new Date(today.getFullYear(), today.getMonth() + 1, 0);

            const startDateInput = document.getElementById('start_date');
            const endDateInput = document.getElementById('end_date');

            if (startDateInput && endDateInput) {
                startDateInput.value = firstDay.toISOString().split('T')[0];
                endDateInput.value = lastDay.toISOString().split('T')[0];
            }
        });

        // Quick date range selectors
        function setDateRange(range) {
            const today = new Date();
            let startDate, endDate;

            switch (range) {
                case 'today':
                    startDate = endDate = today;
                    break;
                case 'yesterday':
                    startDate = endDate = new Date(today);
                    startDate.setDate(today.getDate() - 1);
                    endDate.setDate(today.getDate() - 1);
                    break;
                case 'last7days':
                    startDate = new Date(today);
                    startDate.setDate(today.getDate() - 7);
                    endDate = today;
                    break;
                case 'last30days':
                    startDate = new Date(today);
                    startDate.setDate(today.getDate() - 30);
                    endDate = today;
                    break;
                case 'thisMonth':
                    startDate = new Date(today.getFullYear(), today.getMonth(), 1);
                    endDate = new Date(today.getFullYear(), today.getMonth() + 1, 0);
                    break;
                case 'lastMonth':
                    startDate = new Date(today.getFullYear(), today.getMonth() - 1, 1);
                    endDate = new Date(today.getFullYear(), today.getMonth(), 0);
                    break;
            }

            document.getElementById('start_date').value = startDate.toISOString().split('T')[0];
            document.getElementById('end_date').value = endDate.toISOString().split('T')[0];
        }

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
