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

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: vehicles.php');
    exit;
}

$vehicle_id = (int)$_GET['id'];

// Get vehicle details
$conn = getDBConnection();
$stmt = $conn->prepare("
    SELECT v.*, u.name as driver_name, u.phone as driver_phone
    FROM vehicles v
    LEFT JOIN users u ON v.driver_id = u.id
    WHERE v.id = ?
");
$stmt->execute([$vehicle_id]);
$vehicle = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$vehicle) {
    header('Location: vehicles.php');
    exit;
}

// Get vehicle's trip history
$stmt = $conn->prepare("
    SELECT t.*, u.name as driver_name
    FROM trips t
    LEFT JOIN users u ON t.driver_id = u.id
    WHERE t.vehicle_id = ?
    ORDER BY t.created_at DESC
    LIMIT 10
");
$stmt->execute([$vehicle_id]);
$trips = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=5.0, user-scalable=yes">
    <link rel="apple-touch-icon" sizes="76x76" href="./assets/img/apple-icon.png">
    <link rel="icon" type="image/png" href="./assets/img/favicon.png">
    <title>Vehicle Details - MS Infosystems Employee Transportation System</title>
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
                        <a class="nav-link active bg-gradient-dark text-white" href="vehicles.php">
                            <i class="material-symbols-rounded opacity-5">directions_car</i>
                            <span class="nav-link-text ms-1">Vehicles</span>
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
                        <li class="breadcrumb-item text-sm"><a class="opacity-5 text-dark" href="vehicles.php">Vehicles</a></li>
                        <li class="breadcrumb-item text-sm text-dark active" aria-current="page">Vehicle Details</li>
                    </ol>
                    <h6 class="font-weight-bolder mb-0"><?php echo htmlspecialchars($vehicle['make'] . ' ' . $vehicle['model']); ?> - Vehicle Details</h6>
                </nav>
            </div>
        </nav>

        <div class="container-fluid py-2">
            <!-- Car Information -->
            <div class="row">
                <div class="col-lg-8">
                    <div class="card">
                        <div class="card-header pb-0 p-3">
                            <h6>Vehicle Information</h6>
                        </div>
                        <div class="card-body p-3">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label class="form-control-label">Vehicle ID</label>
                                        <input class="form-control" type="text" value="<?php echo $vehicle['id']; ?>" readonly>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label class="form-control-label">Status</label>
                                        <input class="form-control" type="text" value="<?php echo ucfirst($vehicle['status']); ?>" readonly>
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label class="form-control-label">Make</label>
                                        <input class="form-control" type="text" value="<?php echo htmlspecialchars($vehicle['make']); ?>" readonly>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label class="form-control-label">Model</label>
                                        <input class="form-control" type="text" value="<?php echo htmlspecialchars($vehicle['model']); ?>" readonly>
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label class="form-control-label">Year</label>
                                        <input class="form-control" type="text" value="<?php echo $vehicle['year']; ?>" readonly>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label class="form-control-label">License Plate</label>
                                        <input class="form-control" type="text" value="<?php echo htmlspecialchars($vehicle['license_plate']); ?>" readonly>
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label class="form-control-label">Seating Capacity</label>
                                        <input class="form-control" type="text" value="<?php echo $vehicle['seating_capacity']; ?> Seater" readonly>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label class="form-control-label">Fuel Type</label>
                                        <input class="form-control" type="text" value="<?php echo ucfirst($vehicle['fuel_type'] ?? 'N/A'); ?>" readonly>
                                    </div>
                                </div>
                            </div>
                            <?php if ($vehicle['chassis_number'] || $vehicle['engine_number']): ?>
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label class="form-control-label">Chassis Number</label>
                                            <input class="form-control" type="text" value="<?php echo htmlspecialchars($vehicle['chassis_number'] ?? 'N/A'); ?>" readonly>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label class="form-control-label">Engine Number</label>
                                            <input class="form-control" type="text" value="<?php echo htmlspecialchars($vehicle['engine_number'] ?? 'N/A'); ?>" readonly>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <div class="col-lg-4">
                    <div class="card">
                        <div class="card-header pb-0 p-3">
                            <h6>Assigned Driver</h6>
                        </div>
                        <div class="card-body p-3">
                            <?php if ($vehicle['driver_name']): ?>
                                <div class="d-flex align-items-center mb-3">
                                    <div class="avatar avatar-lg me-3 bg-gradient-success">
                                        <i class="material-symbols-rounded text-white">person</i>
                                    </div>
                                    <div>
                                        <h6 class="mb-0"><?php echo htmlspecialchars($vehicle['driver_name']); ?></h6>
                                        <p class="text-sm text-muted mb-0"><?php echo htmlspecialchars($vehicle['driver_phone']); ?></p>
                                    </div>
                                </div>
                                <div class="text-center">
                                    <span class="badge bg-gradient-success">Assigned</span>
                                </div>
                            <?php else: ?>
                                <div class="text-center py-4">
                                    <i class="material-symbols-rounded text-muted" style="font-size: 48px;">person_off</i>
                                    <p class="text-muted mt-2">No driver assigned</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Trip History -->
            <div class="row mt-4">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header pb-0 p-3">
                            <h6>Trip History</h6>
                        </div>
                        <div class="card-body px-0 pb-2">
                            <div class="table-responsive">
                                <table class="table align-items-center mb-0">
                                    <thead>
                                        <tr>
                                            <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Trip ID</th>
                                            <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">Driver</th>
                                            <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">Passengers</th>
                                            <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">Status</th>
                                            <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">Start Time</th>
                                            <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">End Time</th>
                                            <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">Distance</th>
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
                                                    <p class="text-xs font-weight-bold mb-0"><?php echo $trip['passenger_count']; ?></p>
                                                </td>
                                                <td>
                                                    <span class="badge badge-sm bg-gradient-<?php echo $trip['status'] == 'completed' ? 'success' : ($trip['status'] == 'in_progress' ? 'warning' : 'secondary'); ?>">
                                                        <?php echo ucfirst($trip['status']); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <p class="text-xs font-weight-bold mb-0"><?php echo $trip['start_time'] ? date('M d, Y H:i', strtotime($trip['start_time'])) : '-'; ?></p>
                                                </td>
                                                <td>
                                                    <p class="text-xs font-weight-bold mb-0"><?php echo $trip['end_time'] ? date('M d, Y H:i', strtotime($trip['end_time'])) : '-'; ?></p>
                                                </td>
                                                <td>
                                                    <p class="text-xs font-weight-bold mb-0"><?php echo $trip['distance'] ? number_format($trip['distance'], 2) . ' km' : '-'; ?></p>
                                                </td>
                                                <td class="align-middle">
                                                    <a href="trip_details.php?id=<?php echo $trip['id']; ?>" class="text-secondary font-weight-bold text-xs" data-toggle="tooltip" data-original-title="View Details">
                                                        View
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                        <?php if (empty($trips)): ?>
                                            <tr>
                                                <td colspan="8" class="text-center py-4">
                                                    <p class="text-sm text-muted">No trips found for this vehicle.</p>
                                                </td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
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

