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

if ($role == 'driver') {
    // Drivers can only view their own attendance
    $attendance = getDriverAttendance($_SESSION['user_id']);
} elseif ($role == 'supervisor') {
    // Supervisors can only view attendance records, not mark manually
    $conn = getDBConnection();
    $stmt = $conn->prepare("SELECT a.*, u.name as driver_name FROM attendance a LEFT JOIN users u ON a.driver_id = u.id WHERE u.supervisor_id = ? ORDER BY a.date DESC, u.name");
    $stmt->execute([$_SESSION['user_id']]);
    $attendance_records = $stmt->fetchAll(PDO::FETCH_ASSOC);
} else {
    // Admin can view all attendance and mark manually
    $conn = getDBConnection();
    $stmt = $conn->query("SELECT a.*, u.name as driver_name FROM attendance a LEFT JOIN users u ON a.driver_id = u.id ORDER BY a.date DESC, u.name");
    $attendance_records = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Handle attendance marking for admin only (supervisors cannot mark manually)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['mark_attendance']) && $role == 'admin') {
    $driver_id = $_POST['driver_id'];
    $date = $_POST['date'];
    $status = $_POST['status'];

    $conn = getDBConnection();
    $stmt = $conn->prepare("INSERT INTO attendance (driver_id, date, status) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE status = ?");
    $stmt->execute([$driver_id, $date, $status, $status]);
    header('Location: attendance.php');
    exit;
}

// Get drivers for marking attendance (only for admin)
if ($role == 'admin') {
    $conn = getDBConnection();
    $drivers = $conn->query("SELECT id, name FROM users WHERE role = 'driver'")->fetchAll(PDO::FETCH_ASSOC);
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=5.0, user-scalable=yes">
    <link rel="apple-touch-icon" sizes="76x76" href="./assets/img/apple-icon.png">
    <link rel="icon" type="image/png" href="./assets/img/favicon.png">
    <title>Attendance - MS Infosystems Employee Transportation System</title>
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
                <img src="./assets/img/logo-ct-dark.png" class="navbar-brand-img" width="26" height="26" alt="main_logo">
                <span class="ms-1 text-sm text-dark">MS Infosystems</span>
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
                        <a class="nav-link active bg-gradient-dark text-white" href="attendance.php">
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
                    <ol class="breadcrumb bg-transparent mb-0 pb-0 pt-1 px-0 me-sm-6 me-5">
                        <li class="breadcrumb-item text-sm"><a class="opacity-5 text-dark" href="javascript:;">Pages</a></li>
                        <li class="breadcrumb-item text-sm text-dark active" aria-current="page">Attendance</li>
                    </ol>
                    <h6 class="font-weight-bolder mb-0">Driver Attendance Management</h6>
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
            <?php if ($role == 'driver'): ?>
                <!-- Driver Attendance Dashboard -->
                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header pb-0 p-3">
                                <h6>Your Attendance Summary</h6>
                                <p class="text-sm mb-0">Monthly attendance overview for <?php echo date('F Y'); ?></p>
                            </div>
                            <div class="card-body p-3">
                                <div class="row text-center">
                                    <div class="col-md-3">
                                        <div class="stat-card">
                                            <div class="avatar avatar-xl mx-auto mb-3 bg-gradient-success">
                                                <i class="material-symbols-rounded text-white">check_circle</i>
                                            </div>
                                            <h4 class="text-success mb-1"><?php echo $attendance['present_days']; ?></h4>
                                            <p class="text-muted mb-0">Present Days</p>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="stat-card">
                                            <div class="avatar avatar-xl mx-auto mb-3 bg-gradient-info">
                                                <i class="material-symbols-rounded text-white">calendar_today</i>
                                            </div>
                                            <h4 class="text-info mb-1"><?php echo $attendance['total_days']; ?></h4>
                                            <p class="text-muted mb-0">Total Days</p>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="stat-card">
                                            <div class="avatar avatar-xl mx-auto mb-3 bg-gradient-warning">
                                                <i class="material-symbols-rounded text-white">trending_up</i>
                                            </div>
                                            <h4 class="text-warning mb-1"><?php echo $attendance['total_days'] > 0 ? round(($attendance['present_days'] / $attendance['total_days']) * 100, 1) : 0; ?>%</h4>
                                            <p class="text-muted mb-0">Attendance Rate</p>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="stat-card">
                                            <div class="avatar avatar-xl mx-auto mb-3 bg-gradient-primary">
                                                <i class="material-symbols-rounded text-white">local_shipping</i>
                                            </div>
                                            <h4 class="text-primary mb-1">
                                                <?php
                                                $conn = getDBConnection();
                                                $stmt = $conn->prepare("SELECT COUNT(*) FROM trips WHERE driver_id = ? AND MONTH(start_time) = MONTH(CURRENT_DATE()) AND YEAR(start_time) = YEAR(CURRENT_DATE())");
                                                $stmt->execute([$_SESSION['user_id']]);
                                                echo $stmt->fetchColumn();
                                                ?>
                                            </h4>
                                            <p class="text-muted mb-0">Trips This Month</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Recent Attendance Records -->
                <div class="row mt-4">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header pb-0 p-3">
                                <h6>Recent Attendance Records</h6>
                                <p class="text-sm mb-0">Your attendance history for the current month</p>
                            </div>
                            <div class="card-body px-0 pb-2">
                                <div class="table-responsive">
                                    <table class="table align-items-center mb-0">
                                        <thead>
                                            <tr>
                                                <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Date</th>
                                                <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">Status</th>
                                                <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">Trip Details</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php
                                            $conn = getDBConnection();
                                            $stmt = $conn->prepare("
                                                SELECT a.date, a.status, t.id as trip_id, t.start_time, t.end_time
                                                FROM attendance a
                                                LEFT JOIN trips t ON a.driver_id = t.driver_id AND DATE(a.date) = DATE(t.start_time)
                                                WHERE a.driver_id = ? AND MONTH(a.date) = MONTH(CURRENT_DATE()) AND YEAR(a.date) = YEAR(CURRENT_DATE())
                                                ORDER BY a.date DESC
                                                LIMIT 30
                                            ");
                                            $stmt->execute([$_SESSION['user_id']]);
                                            $recent_records = $stmt->fetchAll(PDO::FETCH_ASSOC);

                                            foreach ($recent_records as $record):
                                            ?>
                                                <tr>
                                                    <td>
                                                        <p class="text-xs font-weight-bold mb-0"><?php echo date('M d, Y', strtotime($record['date'])); ?></p>
                                                        <small class="text-muted"><?php echo date('l', strtotime($record['date'])); ?></small>
                                                    </td>
                                                    <td>
                                                        <span class="badge badge-sm bg-gradient-<?php echo $record['status'] == 'present' ? 'success' : ($record['status'] == 'absent' ? 'danger' : 'warning'); ?>">
                                                            <?php echo ucfirst($record['status']); ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <?php if ($record['trip_id']): ?>
                                                            <div class="d-flex align-items-center">
                                                                <i class="material-symbols-rounded text-success me-2" style="font-size: 16px;">local_shipping</i>
                                                                <div>
                                                                    <p class="text-xs font-weight-bold mb-0">Trip #<?php echo $record['trip_id']; ?></p>
                                                                    <small class="text-muted">
                                                                        <?php echo $record['start_time'] ? date('H:i', strtotime($record['start_time'])) : ''; ?>
                                                                        <?php echo $record['end_time'] ? ' - ' . date('H:i', strtotime($record['end_time'])) : ''; ?>
                                                                    </small>
                                                                </div>
                                                            </div>
                                                        <?php else: ?>
                                                            <span class="text-muted">-</span>
                                                        <?php endif; ?>
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
            <?php else: ?>
                <!-- Attendance Overview Dashboard -->
                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header pb-0 p-3">
                                <h6>Attendance Overview</h6>
                                <p class="text-sm mb-0">Monthly attendance statistics for all drivers</p>
                            </div>
                            <div class="card-body p-3">
                                <div class="row text-center">
                                    <?php
                                    $conn = getDBConnection();
                                    $currentMonth = date('Y-m');
                                    $totalDrivers = $role == 'admin' ? count($drivers) : count($attendance_records);

                                    // Total present days this month
                                    $stmt = $conn->prepare("SELECT COUNT(*) FROM attendance WHERE status = 'present' AND DATE_FORMAT(date, '%Y-%m') = ?");
                                    $stmt->execute([$currentMonth]);
                                    $totalPresent = $stmt->fetchColumn();

                                    // Total attendance records this month
                                    $stmt = $conn->prepare("SELECT COUNT(*) FROM attendance WHERE DATE_FORMAT(date, '%Y-%m') = ?");
                                    $stmt->execute([$currentMonth]);
                                    $totalRecords = $stmt->fetchColumn();

                                    // Average attendance rate
                                    $avgRate = $totalRecords > 0 ? round(($totalPresent / $totalRecords) * 100, 1) : 0;

                                    // Active drivers today
                                    $stmt = $conn->prepare("SELECT COUNT(DISTINCT driver_id) FROM attendance WHERE date = CURDATE() AND status = 'present'");
                                    $stmt->execute();
                                    $activeToday = $stmt->fetchColumn();
                                    ?>
                                    <div class="col-md-3">
                                        <div class="stat-card">
                                            <div class="avatar avatar-xl mx-auto mb-3 bg-gradient-success">
                                                <i class="material-symbols-rounded text-white">group</i>
                                            </div>
                                            <h4 class="text-success mb-1"><?php echo $totalDrivers; ?></h4>
                                            <p class="text-muted mb-0">Total Drivers</p>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="stat-card">
                                            <div class="avatar avatar-xl mx-auto mb-3 bg-gradient-info">
                                                <i class="material-symbols-rounded text-white">today</i>
                                            </div>
                                            <h4 class="text-info mb-1"><?php echo $activeToday; ?></h4>
                                            <p class="text-muted mb-0">Active Today</p>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="stat-card">
                                            <div class="avatar avatar-xl mx-auto mb-3 bg-gradient-warning">
                                                <i class="material-symbols-rounded text-white">calendar_view_month</i>
                                            </div>
                                            <h4 class="text-warning mb-1"><?php echo $totalPresent; ?></h4>
                                            <p class="text-muted mb-0">Present This Month</p>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="stat-card">
                                            <div class="avatar avatar-xl mx-auto mb-3 bg-gradient-primary">
                                                <i class="material-symbols-rounded text-white">analytics</i>
                                            </div>
                                            <h4 class="text-primary mb-1"><?php echo $avgRate; ?>%</h4>
                                            <p class="text-muted mb-0">Avg Attendance Rate</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Manual Attendance Marking (Admin Only) -->
                <?php if ($role == 'admin'): ?>
                    <div class="row mt-4">
                        <div class="col-12">
                            <div class="card">
                                <div class="card-header pb-0 p-3">
                                    <h6>Manual Attendance Management</h6>
                                    <p class="text-sm mb-0">Mark attendance for drivers manually (typically used for exceptions or corrections)</p>
                                </div>
                                <div class="card-body p-3">
                                    <div class="alert alert-info">
                                        <i class="material-symbols-rounded">info</i>
                                        <strong>Note:</strong> Attendance is primarily marked automatically when drivers validate trip OTP/QR codes. Use this form only for manual corrections or special cases.
                                    </div>
                                    <form method="POST" action="">
                                        <div class="row">
                                            <div class="col-md-4">
                                                <div class="form-group">
                                                    <label for="driver_id" class="form-control-label">Driver</label>
                                                    <select class="form-control" id="driver_id" name="driver_id" required>
                                                        <option value="">Select Driver</option>
                                                        <?php foreach ($drivers as $driver): ?>
                                                            <option value="<?php echo $driver['id']; ?>"><?php echo htmlspecialchars($driver['name']); ?></option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <div class="form-group">
                                                    <label for="date" class="form-control-label">Date</label>
                                                    <input class="form-control" type="date" id="date" name="date" value="<?php echo date('Y-m-d'); ?>" required>
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <div class="form-group">
                                                    <label for="status" class="form-control-label">Status</label>
                                                    <select class="form-control" id="status" name="status" required>
                                                        <option value="present">Present</option>
                                                        <option value="absent">Absent</option>
                                                        <option value="leave">Leave</option>
                                                    </select>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="row mt-3">
                                            <div class="col-12">
                                                <button type="submit" name="mark_attendance" class="btn bg-gradient-dark">Mark Attendance</button>
                                            </div>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Detailed Attendance Records -->
                <div class="row mt-4">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header pb-0 p-3">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6>Attendance Records</h6>
                                        <p class="text-sm mb-0">Detailed attendance history with trip correlations</p>
                                    </div>
                                    <div class="d-flex gap-2">
                                        <select class="form-control form-control-sm" id="monthFilter" style="width: auto;">
                                            <option value="">All Months</option>
                                            <?php for ($i = 0; $i < 12; $i++): ?>
                                                <option value="<?php echo date('Y-m', strtotime("-$i months")); ?>" <?php echo $i == 0 ? 'selected' : ''; ?>>
                                                    <?php echo date('F Y', strtotime("-$i months")); ?>
                                                </option>
                                            <?php endfor; ?>
                                        </select>
                                        <select class="form-control form-control-sm" id="statusFilter" style="width: auto;">
                                            <option value="">All Status</option>
                                            <option value="present">Present</option>
                                            <option value="absent">Absent</option>
                                            <option value="leave">Leave</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            <div class="card-body px-0 pb-2">
                                <div class="table-responsive">
                                    <table class="table align-items-center mb-0">
                                        <thead>
                                            <tr>
                                                <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Driver</th>
                                                <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">Date</th>
                                                <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">Status</th>
                                                <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">Trip Details</th>
                                                <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">Duration</th>
                                                <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody id="attendanceTableBody">
                                            <?php
                                            $conn = getDBConnection();
                                            $stmt = $conn->query("
                                                SELECT a.*, u.name as driver_name, u.phone,
                                                       t.id as trip_id, t.start_time, t.end_time, t.distance,
                                                       TIMESTAMPDIFF(MINUTE, t.start_time, t.end_time) as trip_duration
                                                FROM attendance a
                                                LEFT JOIN users u ON a.driver_id = u.id
                                                LEFT JOIN trips t ON a.driver_id = t.driver_id AND DATE(a.date) = DATE(t.start_time)
                                                WHERE u.role = 'driver'
                                                ORDER BY a.date DESC, u.name
                                                LIMIT 100
                                            ");
                                            $detailed_records = $stmt->fetchAll(PDO::FETCH_ASSOC);

                                            foreach ($detailed_records as $record):
                                            ?>
                                                <tr data-month="<?php echo date('Y-m', strtotime($record['date'])); ?>" data-status="<?php echo $record['status']; ?>">
                                                    <td>
                                                        <div class="d-flex px-2 py-1">
                                                            <div class="avatar avatar-sm me-3 bg-gradient-<?php echo $record['status'] == 'present' ? 'success' : ($record['status'] == 'absent' ? 'danger' : 'warning'); ?>">
                                                                <i class="material-symbols-rounded text-white text-xs">person</i>
                                                            </div>
                                                            <div class="d-flex flex-column justify-content-center">
                                                                <h6 class="mb-0 text-sm"><?php echo htmlspecialchars($record['driver_name']); ?></h6>
                                                                <small class="text-muted"><?php echo htmlspecialchars($record['phone']); ?></small>
                                                            </div>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <p class="text-xs font-weight-bold mb-0"><?php echo date('M d, Y', strtotime($record['date'])); ?></p>
                                                        <small class="text-muted"><?php echo date('l', strtotime($record['date'])); ?></small>
                                                    </td>
                                                    <td>
                                                        <span class="badge badge-sm bg-gradient-<?php echo $record['status'] == 'present' ? 'success' : ($record['status'] == 'absent' ? 'danger' : 'warning'); ?>">
                                                            <?php echo ucfirst($record['status']); ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <?php if ($record['trip_id']): ?>
                                                            <div class="d-flex align-items-center">
                                                                <i class="material-symbols-rounded text-info me-2" style="font-size: 16px;">local_shipping</i>
                                                                <div>
                                                                    <p class="text-xs font-weight-bold mb-0">Trip #<?php echo $record['trip_id']; ?></p>
                                                                    <small class="text-muted">
                                                                        <?php echo $record['start_time'] ? date('H:i', strtotime($record['start_time'])) : ''; ?>
                                                                        <?php echo $record['end_time'] ? ' - ' . date('H:i', strtotime($record['end_time'])) : ''; ?>
                                                                    </small>
                                                                </div>
                                                            </div>
                                                        <?php else: ?>
                                                            <span class="text-muted">-</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <?php if ($record['trip_duration']): ?>
                                                            <p class="text-xs font-weight-bold mb-0"><?php echo floor($record['trip_duration'] / 60); ?>h <?php echo $record['trip_duration'] % 60; ?>m</p>
                                                            <small class="text-muted"><?php echo number_format($record['distance'], 1); ?> km</small>
                                                        <?php else: ?>
                                                            <span class="text-muted">-</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <div class="d-flex gap-1">
                                                            <button class="btn btn-link text-secondary p-0" onclick="viewDriverDetails(<?php echo $record['driver_id']; ?>)">
                                                                <i class="material-symbols-rounded" style="font-size: 16px;">visibility</i>
                                                            </button>
                                                            <?php if ($record['trip_id']): ?>
                                                                <button class="btn btn-link text-info p-0" onclick="viewTripDetails(<?php echo $record['trip_id']; ?>)">
                                                                    <i class="material-symbols-rounded" style="font-size: 16px;">route</i>
                                                                </button>
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
                <a class="btn bg-gradient-info w-100" href="https://msinfosystems.co.in">Visit MS Infosystems</a>
                <div class="w-100 text-center">
                    <h6 class="mt-3">Employee Transportation System</h6>
                    <a href="https://msinfosystems.co.in" class="btn btn-dark mb-0 me-2" target="_blank">
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
        // Filter functionality
        document.getElementById('monthFilter').addEventListener('change', filterAttendance);
        document.getElementById('statusFilter').addEventListener('change', filterAttendance);

        function filterAttendance() {
            const monthFilter = document.getElementById('monthFilter').value;
            const statusFilter = document.getElementById('statusFilter').value;
            const rows = document.querySelectorAll('#attendanceTableBody tr');

            rows.forEach(row => {
                const rowMonth = row.getAttribute('data-month');
                const rowStatus = row.getAttribute('data-status');

                const monthMatch = !monthFilter || rowMonth === monthFilter;
                const statusMatch = !statusFilter || rowStatus === statusFilter;

                if (monthMatch && statusMatch) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        }

        function viewDriverDetails(driverId) {
            window.location.href = `driver_details.php?id=${driverId}`;
        }

        function viewTripDetails(tripId) {
            window.location.href = `trip_details.php?id=${tripId}`;
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
