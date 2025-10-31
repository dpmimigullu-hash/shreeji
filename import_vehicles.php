<?php
session_start();
require_once 'includes/config.php';
require_once 'includes/functions.php';

// Check if user is logged in and has admin role
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user = getUserById($_SESSION['user_id']);
$role = $user['role'];

if ($role != 'admin') {
    header('Location: index.php');
    exit;
}

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['import_vehicles'])) {
    if (isset($_FILES['vehicle_file']) && $_FILES['vehicle_file']['error'] == 0) {
        $file_content = file_get_contents($_FILES['car_file']['tmp_name']);
        $lines = explode("\n", $file_content);

        $imported_count = 0;
        $skipped_count = 0;

        $conn = getDBConnection();

        // Skip header row
        for ($i = 1; $i < count($lines); $i++) {
            $line = trim($lines[$i]);
            if (!empty($line) && !str_contains($line, '---')) {
                $parts = preg_split('/\t+/', $line);
                if (count($parts) >= 3) {
                    $brand = trim($parts[0]);
                    $model = trim($parts[1]);
                    $seating_capacity = (int)trim($parts[2]);

                    if (!empty($brand) && !empty($model) && $seating_capacity > 0) {
                        // Check if vehicle already exists
                        $stmt = $conn->prepare("SELECT id FROM vehicles WHERE make = ? AND model = ?");
                        $stmt->execute([$brand, $model]);
                        $exists = $stmt->fetch();

                        if (!$exists) {
                            // Insert new vehicle
                            $stmt = $conn->prepare("INSERT INTO vehicles (make, model, seating_capacity, year, license_plate) VALUES (?, ?, ?, 2020, CONCAT('AUTO', LPAD(LAST_INSERT_ID() + 1, 6, '0')))");
                            $stmt->execute([$brand, $model, $seating_capacity]);
                            $imported_count++;
                        } else {
                            $skipped_count++;
                        }
                    }
                }
            }
        }

        if ($imported_count > 0) {
            $message = "Successfully imported $imported_count vehicles. $skipped_count vehicles were skipped (already exist).";
        } else {
            $message = "No new vehicles were imported. $skipped_count vehicles already exist in the database.";
        }
    } else {
        $error = "Please select a valid file to import.";
    }
}

// Get current vehicle count
$conn = getDBConnection();
$stmt = $conn->query("SELECT COUNT(*) FROM vehicles");
$total_vehicles = $stmt->fetchColumn();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=5.0, user-scalable=yes">
    <link rel="apple-touch-icon" sizes="76x76" href="./assets/img/apple-icon.png">
    <link rel="icon" type="image/png" href="./assets/img/favicon.png">
    <title>Import Vehicles - MS Infosystems Employee Transportation System</title>
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
                        <li class="breadcrumb-item text-sm text-dark active" aria-current="page">Import Vehicles</li>
                    </ol>
                    <h6 class="font-weight-bolder mb-0">Import Vehicle Data from Excel</h6>
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
            <div class="row">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header pb-0 p-3">
                            <h6>Import Vehicle Data</h6>
                            <p class="text-sm mb-0">Upload vehicle data from Excel file to populate the database with vehicle makes, models, and seating capacities.</p>
                            <?php if (!empty($message)): ?>
                                <div class="alert alert-success mt-3">
                                    <i class="material-symbols-rounded">check_circle</i>
                                    <?php echo $message; ?>
                                </div>
                            <?php endif; ?>
                            <?php if (!empty($error)): ?>
                                <div class="alert alert-danger mt-3">
                                    <i class="material-symbols-rounded">error</i>
                                    <?php echo $error; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="card-body p-3">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="card h-100">
                                        <div class="card-header pb-0 p-3">
                                            <h6 class="mb-0">Current Database Status</h6>
                                        </div>
                                        <div class="card-body p-3">
                                            <div class="d-flex align-items-center mb-3">
                                                <div class="text-center">
                                                    <i class="material-symbols-rounded text-info" style="font-size: 48px;">directions_car</i>
                                                </div>
                                                <div class="ms-3">
                                                    <h4 class="mb-0"><?php echo $total_vehicles; ?></h4>
                                                    <p class="text-sm text-secondary mb-0">Total Vehicles in Database</p>
                                                </div>
                                            </div>
                                            <p class="text-sm text-muted">Vehicles are used for driver registration and trip assignments.</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="card h-100">
                                        <div class="card-header pb-0 p-3">
                                            <h6 class="mb-0">Upload Vehicle Data</h6>
                                        </div>
                                        <div class="card-body p-3">
                                            <form method="POST" action="" enctype="multipart/form-data">
                                                <div class="form-group">
                                                    <label for="vehicle_file" class="form-control-label">Select Vehicle Data File</label>
                                                    <input class="form-control" type="file" id="vehicle_file" name="vehicle_file" accept=".xlsx,.xls,.csv,.txt,.tsv" required>
                                                    <small class="form-text text-muted">Upload Excel (.xlsx, .xls), CSV (.csv), or tab-separated (.txt, .tsv) file with columns: Brand, Model, Seat Capacity</small>
                                                </div>
                                                <div class="form-group mt-3">
                                                    <button type="submit" name="import_cars" class="btn bg-gradient-success w-100">
                                                        <i class="material-symbols-rounded">upload</i>
                                                        Import Vehicles
                                                    </button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="row mt-4">
                                <div class="col-12">
                                    <div class="card">
                                        <div class="card-header pb-0 p-3">
                                            <h6>Sample File Format</h6>
                                        </div>
                                        <div class="card-body p-3">
                                            <p class="text-sm mb-3">Your file should contain the following columns in order:</p>
                                            <div class="table-responsive">
                                                <table class="table table-sm">
                                                    <thead>
                                                        <tr>
                                                            <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Brand</th>
                                                            <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Model</th>
                                                            <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Seat Capacity</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <tr>
                                                            <td>Maruti Suzuki</td>
                                                            <td>Alto K10</td>
                                                            <td>4</td>
                                                        </tr>
                                                        <tr>
                                                            <td>Toyota</td>
                                                            <td>Innova Crysta</td>
                                                            <td>7</td>
                                                        </tr>
                                                        <tr>
                                                            <td>Force</td>
                                                            <td>Traveller 3350</td>
                                                            <td>13</td>
                                                        </tr>
                                                    </tbody>
                                                </table>
                                            </div>
                                            <div class="alert alert-info mt-3">
                                                <i class="material-symbols-rounded">info</i>
                                                <strong>Note:</strong> Duplicate entries (same Brand + Model combination) will be skipped during import.
                                            </div>
                                        </div>
                                    </div>
                                </div>
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
    <script src="./assets/js/material-dashboard.min.js?v=3.2.0"></script>
</body>

</html>

