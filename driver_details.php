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
    header('Location: drivers.php');
    exit;
}

$driver_id = (int)$_GET['id'];
$is_edit = isset($_GET['edit']) && $_GET['edit'] == '1';

// Get driver details
$conn = getDBConnection();
$stmt = $conn->prepare("
    SELECT u.*, s.name as supervisor_name
    FROM users u
    LEFT JOIN users s ON u.supervisor_id = s.id
    WHERE u.id = ? AND u.role = 'driver'
");
$stmt->execute([$driver_id]);
$driver = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$driver) {
    header('Location: drivers.php');
    exit;
}

// Check if user has permission to view this driver
if ($role == 'supervisor' && $driver['supervisor_id'] != $_SESSION['user_id']) {
    header('Location: drivers.php');
    exit;
}

// Get driver's assigned car
$car = null;
try {
    $stmt = $conn->prepare("SELECT * FROM vehicles WHERE driver_id = ?");
    $stmt->execute([$driver_id]);
    $car = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Vehicles table might not exist or have different structure
    $car = null;
}

// Get driver's trips
$stmt = $conn->prepare("
    SELECT t.*, v.make, v.model, v.license_plate
    FROM trips t
    LEFT JOIN vehicles v ON t.vehicle_id = v.id
    WHERE t.driver_id = ?
    ORDER BY t.created_at DESC
    LIMIT 10
");
$stmt->execute([$driver_id]);
$trips = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get driver's attendance for current month
$currentMonth = date('Y-m');
$stmt = $conn->prepare("
    SELECT COUNT(*) as total_days,
           SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) as present_days
    FROM attendance
    WHERE driver_id = ? AND DATE_FORMAT(date, '%Y-%m') = ?
");
$stmt->execute([$driver_id, $currentMonth]);
$attendance = $stmt->fetch(PDO::FETCH_ASSOC);

// Handle driver update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_driver']) && $role == 'admin') {
    try {
        // Basic account info
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $supervisor_id = $_POST['supervisor_id'] ?? null;
        $license_number = trim($_POST['license_number'] ?? '');

        // Driver details
        $license_photo = isset($_FILES['license_photo']) ? uploadFile($_FILES['license_photo']) : $driver['license_photo'];
        $driver_photo = isset($_FILES['driver_photo']) ? uploadFile($_FILES['driver_photo']) : $driver['driver_photo'];
        $kyc_docs = isset($_FILES['kyc_documents']) ? uploadMultipleFiles($_FILES['kyc_documents']) : json_decode($driver['kyc_documents'], true);

        // Bank details
        $bank_name = trim($_POST['bank_name'] ?? '');
        $bank_account_number = trim($_POST['bank_account_number'] ?? '');
        $bank_ifsc_code = trim($_POST['bank_ifsc_code'] ?? '');
        $bank_branch = trim($_POST['bank_branch'] ?? '');

        // Vehicle details
        $assigned_vehicle_id = $_POST['vehicle_id'] ?? null;
        $vehicle_registration_number = trim($_POST['vehicle_registration_number'] ?? '');
        $vehicle_chassis_number = trim($_POST['vehicle_chassis_number'] ?? '');
        $vehicle_engine_number = trim($_POST['vehicle_engine_number'] ?? '');
        $vehicle_fuel_type = trim($_POST['vehicle_fuel_type'] ?? '');
        $vehicle_photo = isset($_FILES['vehicle_photos']) ? uploadFile($_FILES['vehicle_photos']) : $driver['vehicle_photo'];

        // Vehicle documents
        $pollution_certificate = isset($_FILES['pollution_certificate']) ? uploadFile($_FILES['pollution_certificate']) : $driver['pollution_certificate'];
        $pollution_valid_from = $_POST['pollution_valid_from'] ?? $driver['pollution_valid_from'];
        $pollution_valid_till = $_POST['pollution_valid_till'] ?? $driver['pollution_valid_till'];

        $registration_certificate = isset($_FILES['registration_certificate']) ? uploadFile($_FILES['registration_certificate']) : $driver['registration_certificate'];
        $registration_valid_from = $_POST['registration_valid_from'] ?? $driver['registration_valid_from'];
        $registration_valid_till = $_POST['registration_valid_till'] ?? $driver['registration_valid_till'];

        $road_tax = isset($_FILES['road_tax']) ? uploadFile($_FILES['road_tax']) : $driver['road_tax'];
        $road_tax_valid_from = $_POST['road_tax_valid_from'] ?? $driver['road_tax_valid_from'];
        $road_tax_valid_till = $_POST['road_tax_valid_till'] ?? $driver['road_tax_valid_till'];

        $fast_tag = isset($_FILES['fast_tag']) ? uploadFile($_FILES['fast_tag']) : $driver['fast_tag'];
        $fast_tag_valid_from = $_POST['fast_tag_valid_from'] ?? $driver['fast_tag_valid_from'];
        $fast_tag_valid_till = $_POST['fast_tag_valid_till'] ?? $driver['fast_tag_valid_till'];

        $insurance_document = isset($_FILES['insurance_document']) ? uploadFile($_FILES['insurance_document']) : $driver['insurance_document'];
        $insurance_policy_number = trim($_POST['insurance_policy_number'] ?? $driver['insurance_policy_number']);
        $insurance_expiry_date = $_POST['insurance_expiry_date'] ?? $driver['insurance_expiry_date'];
        $insurance_provider = trim($_POST['insurance_provider'] ?? $driver['insurance_provider']);

        if (!$supervisor_id) {
            throw new Exception("Supervisor selection is required.");
        }

        $stmt = $conn->prepare("
            UPDATE users SET
                name = ?, email = ?, phone = ?, supervisor_id = ?, license_number = ?,
                license_photo = ?, driver_photo = ?, kyc_documents = ?,
                bank_name = ?, bank_account_number = ?, bank_ifsc_code = ?, bank_branch = ?,
                assigned_vehicle_id = ?, vehicle_registration_number = ?, vehicle_chassis_number = ?,
                vehicle_engine_number = ?, vehicle_fuel_type = ?, vehicle_photo = ?,
                pollution_certificate = ?, pollution_valid_from = ?, pollution_valid_till = ?,
                registration_certificate = ?, registration_valid_from = ?, registration_valid_till = ?,
                road_tax = ?, road_tax_valid_from = ?, road_tax_valid_till = ?,
                fast_tag = ?, fast_tag_valid_from = ?, fast_tag_valid_till = ?,
                insurance_policy_number = ?, insurance_expiry_date = ?, insurance_provider = ?, insurance_document = ?
            WHERE id = ? AND role = 'driver'
        ");
        $stmt->execute([
            $name,
            $email,
            $phone,
            $supervisor_id,
            $license_number,
            $license_photo,
            $driver_photo,
            json_encode($kyc_docs),
            $bank_name,
            $bank_account_number,
            $bank_ifsc_code,
            $bank_branch,
            $assigned_vehicle_id,
            $vehicle_registration_number,
            $vehicle_chassis_number,
            $vehicle_engine_number,
            $vehicle_fuel_type,
            $vehicle_photo,
            $pollution_certificate,
            $pollution_valid_from,
            $pollution_valid_till,
            $registration_certificate,
            $registration_valid_from,
            $registration_valid_till,
            $road_tax,
            $road_tax_valid_from,
            $road_tax_valid_till,
            $fast_tag,
            $fast_tag_valid_from,
            $fast_tag_valid_till,
            $insurance_policy_number,
            $insurance_expiry_date,
            $insurance_provider,
            $insurance_document,
            $driver_id
        ]);

        // Redirect to refresh the page
        header('Location: driver_details.php?id=' . $driver_id);
        exit;
    } catch (PDOException $e) {
        $error = "Database Error: " . htmlspecialchars($e->getMessage());
    } catch (Exception $ex) {
        $error = "Error: " . htmlspecialchars($ex->getMessage());
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
    <title>Driver Details - MS Infosystems Employee Transportation System</title>
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
                        <a class="nav-link active bg-gradient-dark text-white" href="drivers.php">
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
                    <h6 class="font-weight-bolder mb-0">MS Infosystems - Employee Transportation System</h6>
                </nav>
            </div>
        </nav>

        <div class="container-fluid py-2">
            <?php if (isset($error)): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>

            <!-- Driver Information -->
            <div class="row">
                <div class="col-lg-8">
                    <div class="card">
                        <div class="card-header pb-0 p-3">
                            <h6>Driver Information</h6>
                        </div>
                        <div class="card-body p-3">
                            <?php if ($is_edit && $role == 'admin'): ?>
                                <form method="POST" enctype="multipart/form-data">
                                    <!-- Basic Information -->
                                    <h6>Basic Information</h6>
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label class="form-control-label">Driver ID</label>
                                                <input class="form-control" type="text" value="<?php echo $driver['id']; ?>" readonly>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label class="form-control-label">Username</label>
                                                <input class="form-control" type="text" value="<?php echo htmlspecialchars($driver['username']); ?>" readonly>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label class="form-control-label">Full Name *</label>
                                                <input class="form-control" type="text" name="name" value="<?php echo htmlspecialchars($driver['name']); ?>" required>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label class="form-control-label">Email *</label>
                                                <input class="form-control" type="email" name="email" value="<?php echo htmlspecialchars($driver['email']); ?>" required>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label class="form-control-label">Phone</label>
                                                <input class="form-control" type="tel" name="phone" value="<?php echo htmlspecialchars($driver['phone']); ?>">
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label class="form-control-label">License Number *</label>
                                                <input class="form-control" type="text" name="license_number" value="<?php echo htmlspecialchars($driver['license_number'] ?? ''); ?>" required>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label class="form-control-label">Supervisor *</label>
                                                <select name="supervisor_id" class="form-control" required>
                                                    <option value="">Select Supervisor</option>
                                                    <?php
                                                    try {
                                                        $stmt = $conn->prepare("SELECT id, name FROM users WHERE role = 'supervisor' ORDER BY name");
                                                        $stmt->execute();
                                                        $supervisors = $stmt->fetchAll(PDO::FETCH_ASSOC);
                                                        foreach ($supervisors as $supervisor) {
                                                            $selected = ($supervisor['id'] == $driver['supervisor_id']) ? 'selected' : '';
                                                            echo '<option value="' . $supervisor['id'] . '" ' . $selected . '>' . htmlspecialchars($supervisor['name']) . '</option>';
                                                        }
                                                    } catch (PDOException $e) {
                                                        // Supervisors table might not exist
                                                    }
                                                    ?>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label class="form-control-label">Registration Date</label>
                                                <input class="form-control" type="text" value="<?php echo date('M d, Y', strtotime($driver['created_at'])); ?>" readonly>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- File Uploads -->
                                    <h6 class="mt-4">Documents</h6>
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label class="form-control-label">License Photo</label>
                                                <input type="file" name="license_photo" class="form-control" accept="image/*">
                                                <?php if ($driver['license_photo']): ?>
                                                    <small class="text-muted">Current file: <?php echo basename($driver['license_photo']); ?></small>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label class="form-control-label">Driver Photo</label>
                                                <input type="file" name="driver_photo" class="form-control" accept="image/*">
                                                <?php if ($driver['driver_photo']): ?>
                                                    <small class="text-muted">Current file: <?php echo basename($driver['driver_photo']); ?></small>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label class="form-control-label">KYC Documents</label>
                                                <input type="file" name="kyc_documents[]" class="form-control" multiple accept="image/*,application/pdf">
                                                <?php if ($driver['kyc_documents']): ?>
                                                    <small class="text-muted">Current files: <?php echo count(json_decode($driver['kyc_documents'], true)); ?> files</small>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Bank Details -->
                                    <h6 class="mt-4">Bank Details</h6>
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label class="form-control-label">Bank Name</label>
                                                <input type="text" name="bank_name" class="form-control" value="<?php echo htmlspecialchars($driver['bank_name'] ?? ''); ?>">
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label class="form-control-label">Account Number</label>
                                                <input type="text" name="bank_account_number" class="form-control" value="<?php echo htmlspecialchars($driver['bank_account_number'] ?? ''); ?>">
                                            </div>
                                        </div>
                                    </div>
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label class="form-control-label">IFSC Code</label>
                                                <input type="text" name="bank_ifsc_code" class="form-control" value="<?php echo htmlspecialchars($driver['bank_ifsc_code'] ?? ''); ?>">
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label class="form-control-label">Branch</label>
                                                <input type="text" name="bank_branch" class="form-control" value="<?php echo htmlspecialchars($driver['bank_branch'] ?? ''); ?>">
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Vehicle Assignment -->
                                    <h6 class="mt-4">Vehicle Assignment</h6>
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label class="form-control-label">Vehicle Registration Number</label>
                                                <input type="text" name="vehicle_registration_number" class="form-control" value="<?php echo htmlspecialchars($driver['vehicle_registration_number'] ?? ''); ?>" placeholder="e.g., MH01AB1234">
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label class="form-control-label">Assign Vehicle</label>
                                                <select name="vehicle_id" class="form-control">
                                                    <option value="">Select Vehicle (Optional)</option>
                                                    <?php
                                                    try {
                                                        $stmt = $conn->prepare("SELECT id, make, model, license_plate FROM vehicles WHERE driver_id IS NULL OR driver_id = ? ORDER BY make, model");
                                                        $stmt->execute([$driver_id]);
                                                        $vehicles = $stmt->fetchAll(PDO::FETCH_ASSOC);
                                                        foreach ($vehicles as $vehicle) {
                                                            $selected = ($vehicle['id'] == $driver['assigned_vehicle_id']) ? 'selected' : '';
                                                            echo '<option value="' . $vehicle['id'] . '" ' . $selected . '>' . htmlspecialchars($vehicle['make'] . ' ' . $vehicle['model'] . ' (' . $vehicle['license_plate'] . ')') . '</option>';
                                                        }
                                                    } catch (PDOException $e) {
                                                        // Vehicles table might not exist
                                                    }
                                                    ?>
                                                </select>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Vehicle Details -->
                                    <h6 class="mt-4">Vehicle Details</h6>
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">Chassis Number</label>
                                            <input type="text" name="vehicle_chassis_number" class="form-control" value="<?php echo htmlspecialchars($driver['vehicle_chassis_number'] ?? ''); ?>" placeholder="Vehicle chassis number">
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">Engine Number</label>
                                            <input type="text" name="vehicle_engine_number" class="form-control" value="<?php echo htmlspecialchars($driver['vehicle_engine_number'] ?? ''); ?>" placeholder="Vehicle engine number">
                                        </div>
                                    </div>
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">Fuel Type</label>
                                            <select name="vehicle_fuel_type" class="form-control">
                                                <option value="">Select Fuel Type</option>
                                                <option value="petrol" <?php echo ($driver['vehicle_fuel_type'] ?? '') == 'petrol' ? 'selected' : ''; ?>>Petrol</option>
                                                <option value="diesel" <?php echo ($driver['vehicle_fuel_type'] ?? '') == 'diesel' ? 'selected' : ''; ?>>Diesel</option>
                                                <option value="cng" <?php echo ($driver['vehicle_fuel_type'] ?? '') == 'cng' ? 'selected' : ''; ?>>CNG</option>
                                            </select>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">Vehicle Photo</label>
                                            <input type="file" name="vehicle_photos" class="form-control" accept="image/*">
                                            <?php if ($driver['vehicle_photo']): ?>
                                                <small class="text-muted">Current file: <?php echo basename($driver['vehicle_photo']); ?></small>
                                            <?php endif; ?>
                                        </div>
                                    </div>

                                    <!-- Vehicle Documents -->
                                    <h6 class="mt-4">Vehicle Documents</h6>

                                    <!-- Pollution Certificate -->
                                    <div class="row mb-3">
                                        <div class="col-12">
                                            <h6 class="text-primary">Pollution Certificate</h6>
                                        </div>
                                        <div class="col-md-4 mb-3">
                                            <label class="form-label">Document</label>
                                            <input type="file" name="pollution_certificate" class="form-control" accept="image/*,application/pdf">
                                            <?php if ($driver['pollution_certificate']): ?>
                                                <small class="text-muted">Current file: <?php echo basename($driver['pollution_certificate']); ?></small>
                                            <?php endif; ?>
                                        </div>
                                        <div class="col-md-4 mb-3">
                                            <label class="form-label">Valid From</label>
                                            <input type="date" name="pollution_valid_from" class="form-control" value="<?php echo $driver['pollution_valid_from'] ?? ''; ?>">
                                        </div>
                                        <div class="col-md-4 mb-3">
                                            <label class="form-label">Valid Till</label>
                                            <input type="date" name="pollution_valid_till" class="form-control" value="<?php echo $driver['pollution_valid_till'] ?? ''; ?>">
                                        </div>
                                    </div>

                                    <!-- Registration Certificate -->
                                    <div class="row mb-3">
                                        <div class="col-12">
                                            <h6 class="text-success">Registration Certificate</h6>
                                        </div>
                                        <div class="col-md-4 mb-3">
                                            <label class="form-label">Document</label>
                                            <input type="file" name="registration_certificate" class="form-control" accept="image/*,application/pdf">
                                            <?php if ($driver['registration_certificate']): ?>
                                                <small class="text-muted">Current file: <?php echo basename($driver['registration_certificate']); ?></small>
                                            <?php endif; ?>
                                        </div>
                                        <div class="col-md-4 mb-3">
                                            <label class="form-label">Valid From</label>
                                            <input type="date" name="registration_valid_from" class="form-control" value="<?php echo $driver['registration_valid_from'] ?? ''; ?>">
                                        </div>
                                        <div class="col-md-4 mb-3">
                                            <label class="form-label">Valid Till</label>
                                            <input type="date" name="registration_valid_till" class="form-control" value="<?php echo $driver['registration_valid_till'] ?? ''; ?>">
                                        </div>
                                    </div>

                                    <!-- Road Tax -->
                                    <div class="row mb-3">
                                        <div class="col-12">
                                            <h6 class="text-warning">Road Tax</h6>
                                        </div>
                                        <div class="col-md-4 mb-3">
                                            <label class="form-label">Document</label>
                                            <input type="file" name="road_tax" class="form-control" accept="image/*,application/pdf">
                                            <?php if ($driver['road_tax']): ?>
                                                <small class="text-muted">Current file: <?php echo basename($driver['road_tax']); ?></small>
                                            <?php endif; ?>
                                        </div>
                                        <div class="col-md-4 mb-3">
                                            <label class="form-label">Valid From</label>
                                            <input type="date" name="road_tax_valid_from" class="form-control" value="<?php echo $driver['road_tax_valid_from'] ?? ''; ?>">
                                        </div>
                                        <div class="col-md-4 mb-3">
                                            <label class="form-label">Valid Till</label>
                                            <input type="date" name="road_tax_valid_till" class="form-control" value="<?php echo $driver['road_tax_valid_till'] ?? ''; ?>">
                                        </div>
                                    </div>

                                    <!-- Fast Tag -->
                                    <div class="row mb-3">
                                        <div class="col-12">
                                            <h6 class="text-info">Fast Tag</h6>
                                        </div>
                                        <div class="col-md-4 mb-3">
                                            <label class="form-label">Document</label>
                                            <input type="file" name="fast_tag" class="form-control" accept="image/*,application/pdf">
                                            <?php if ($driver['fast_tag']): ?>
                                                <small class="text-muted">Current file: <?php echo basename($driver['fast_tag']); ?></small>
                                            <?php endif; ?>
                                        </div>
                                        <div class="col-md-4 mb-3">
                                            <label class="form-label">Valid From</label>
                                            <input type="date" name="fast_tag_valid_from" class="form-control" value="<?php echo $driver['fast_tag_valid_from'] ?? ''; ?>">
                                        </div>
                                        <div class="col-md-4 mb-3">
                                            <label class="form-label">Valid Till</label>
                                            <input type="date" name="fast_tag_valid_till" class="form-control" value="<?php echo $driver['fast_tag_valid_till'] ?? ''; ?>">
                                        </div>
                                    </div>

                                    <!-- Insurance -->
                                    <div class="row mb-3">
                                        <div class="col-12">
                                            <h6 class="text-danger">Insurance</h6>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">Policy Number</label>
                                            <input type="text" name="insurance_policy_number" class="form-control" value="<?php echo htmlspecialchars($driver['insurance_policy_number'] ?? ''); ?>" placeholder="Insurance policy number">
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">Insurance Provider</label>
                                            <input type="text" name="insurance_provider" class="form-control" value="<?php echo htmlspecialchars($driver['insurance_provider'] ?? ''); ?>" placeholder="Insurance company name">
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">Document</label>
                                            <input type="file" name="insurance_document" class="form-control" accept="image/*,application/pdf">
                                            <?php if ($driver['insurance_document']): ?>
                                                <small class="text-muted">Current file: <?php echo basename($driver['insurance_document']); ?></small>
                                            <?php endif; ?>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">Expiry Date</label>
                                            <input type="date" name="insurance_expiry_date" class="form-control" value="<?php echo $driver['insurance_expiry_date'] ?? ''; ?>">
                                        </div>
                                    </div>

                                    <div class="row mt-3">
                                        <div class="col-12">
                                            <button type="submit" name="update_driver" class="btn btn-primary">Update Driver</button>
                                            <a href="driver_details.php?id=<?php echo $driver_id; ?>" class="btn btn-secondary">Cancel Edit</a>
                                        </div>
                                    </div>
                                </form>
                            <?php else: ?>
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label class="form-control-label">Driver ID</label>
                                            <input class="form-control" type="text" value="<?php echo $driver['id']; ?>" readonly>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label class="form-control-label">Full Name</label>
                                            <input class="form-control" type="text" value="<?php echo htmlspecialchars($driver['name']); ?>" readonly>
                                        </div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label class="form-control-label">Email</label>
                                            <input class="form-control" type="email" value="<?php echo htmlspecialchars($driver['email']); ?>" readonly>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label class="form-control-label">Phone</label>
                                            <input class="form-control" type="tel" value="<?php echo htmlspecialchars($driver['phone']); ?>" readonly>
                                        </div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label class="form-control-label">Supervisor</label>
                                            <input class="form-control" type="text" value="<?php echo htmlspecialchars($driver['supervisor_name'] ?? 'N/A'); ?>" readonly>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label class="form-control-label">License Number</label>
                                            <input class="form-control" type="text" value="<?php echo htmlspecialchars($driver['license_number'] ?? 'N/A'); ?>" readonly>
                                        </div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label class="form-control-label">Username</label>
                                            <input class="form-control" type="text" value="<?php echo htmlspecialchars($driver['username']); ?>" readonly>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label class="form-control-label">Registration Date</label>
                                            <input class="form-control" type="text" value="<?php echo date('M d, Y', strtotime($driver['created_at'])); ?>" readonly>
                                        </div>
                                    </div>
                                </div>

                                <!-- Vehicle Information Display -->
                                <h6 class="mt-4">Vehicle Information</h6>
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label class="form-control-label">Vehicle Registration Number</label>
                                            <input class="form-control" type="text" value="<?php echo htmlspecialchars($driver['vehicle_registration_number'] ?? 'N/A'); ?>" readonly>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label class="form-control-label">Chassis Number</label>
                                            <input class="form-control" type="text" value="<?php echo htmlspecialchars($driver['vehicle_chassis_number'] ?? 'N/A'); ?>" readonly>
                                        </div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label class="form-control-label">Engine Number</label>
                                            <input class="form-control" type="text" value="<?php echo htmlspecialchars($driver['vehicle_engine_number'] ?? 'N/A'); ?>" readonly>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label class="form-control-label">Fuel Type</label>
                                            <input class="form-control" type="text" value="<?php echo htmlspecialchars(ucfirst($driver['vehicle_fuel_type'] ?? 'N/A')); ?>" readonly>
                                        </div>
                                    </div>
                                </div>

                                <!-- Vehicle Documents Display -->
                                <h6 class="mt-4">Vehicle Documents</h6>

                                <!-- Pollution Certificate -->
                                <div class="row mb-3">
                                    <div class="col-12">
                                        <h6 class="text-primary">Pollution Certificate</h6>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label class="form-control-label">Valid From</label>
                                            <input class="form-control" type="text" value="<?php echo $driver['pollution_valid_from'] ? date('M d, Y', strtotime($driver['pollution_valid_from'])) : 'N/A'; ?>" readonly>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label class="form-control-label">Valid Till</label>
                                            <input class="form-control" type="text" value="<?php echo $driver['pollution_valid_till'] ? date('M d, Y', strtotime($driver['pollution_valid_till'])) : 'N/A'; ?>" readonly>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label class="form-control-label">Document</label>
                                            <?php if ($driver['pollution_certificate']): ?>
                                                <a href="<?php echo $driver['pollution_certificate']; ?>" target="_blank" class="btn btn-sm btn-outline-primary">View Document</a>
                                            <?php else: ?>
                                                <input class="form-control" type="text" value="No document uploaded" readonly>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>

                                <!-- Registration Certificate -->
                                <div class="row mb-3">
                                    <div class="col-12">
                                        <h6 class="text-success">Registration Certificate</h6>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label class="form-control-label">Valid From</label>
                                            <input class="form-control" type="text" value="<?php echo $driver['registration_valid_from'] ? date('M d, Y', strtotime($driver['registration_valid_from'])) : 'N/A'; ?>" readonly>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label class="form-control-label">Valid Till</label>
                                            <input class="form-control" type="text" value="<?php echo $driver['registration_valid_till'] ? date('M d, Y', strtotime($driver['registration_valid_till'])) : 'N/A'; ?>" readonly>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label class="form-control-label">Document</label>
                                            <?php if ($driver['registration_certificate']): ?>
                                                <a href="<?php echo $driver['registration_certificate']; ?>" target="_blank" class="btn btn-sm btn-outline-success">View Document</a>
                                            <?php else: ?>
                                                <input class="form-control" type="text" value="No document uploaded" readonly>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>

                                <!-- Road Tax -->
                                <div class="row mb-3">
                                    <div class="col-12">
                                        <h6 class="text-warning">Road Tax</h6>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label class="form-control-label">Valid From</label>
                                            <input class="form-control" type="text" value="<?php echo $driver['road_tax_valid_from'] ? date('M d, Y', strtotime($driver['road_tax_valid_from'])) : 'N/A'; ?>" readonly>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label class="form-control-label">Valid Till</label>
                                            <input class="form-control" type="text" value="<?php echo $driver['road_tax_valid_till'] ? date('M d, Y', strtotime($driver['road_tax_valid_till'])) : 'N/A'; ?>" readonly>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label class="form-control-label">Document</label>
                                            <?php if ($driver['road_tax']): ?>
                                                <a href="<?php echo $driver['road_tax']; ?>" target="_blank" class="btn btn-sm btn-outline-warning">View Document</a>
                                            <?php else: ?>
                                                <input class="form-control" type="text" value="No document uploaded" readonly>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>

                                <!-- Fast Tag -->
                                <div class="row mb-3">
                                    <div class="col-12">
                                        <h6 class="text-info">Fast Tag</h6>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label class="form-control-label">Valid From</label>
                                            <input class="form-control" type="text" value="<?php echo $driver['fast_tag_valid_from'] ? date('M d, Y', strtotime($driver['fast_tag_valid_from'])) : 'N/A'; ?>" readonly>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label class="form-control-label">Valid Till</label>
                                            <input class="form-control" type="text" value="<?php echo $driver['fast_tag_valid_till'] ? date('M d, Y', strtotime($driver['fast_tag_valid_till'])) : 'N/A'; ?>" readonly>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label class="form-control-label">Document</label>
                                            <?php if ($driver['fast_tag']): ?>
                                                <a href="<?php echo $driver['fast_tag']; ?>" target="_blank" class="btn btn-sm btn-outline-info">View Document</a>
                                            <?php else: ?>
                                                <input class="form-control" type="text" value="No document uploaded" readonly>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>

                                <!-- Insurance -->
                                <div class="row mb-3">
                                    <div class="col-12">
                                        <h6 class="text-danger">Insurance</h6>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label class="form-control-label">Policy Number</label>
                                            <input class="form-control" type="text" value="<?php echo htmlspecialchars($driver['insurance_policy_number'] ?? 'N/A'); ?>" readonly>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label class="form-control-label">Insurance Provider</label>
                                            <input class="form-control" type="text" value="<?php echo htmlspecialchars($driver['insurance_provider'] ?? 'N/A'); ?>" readonly>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label class="form-control-label">Expiry Date</label>
                                            <input class="form-control" type="text" value="<?php echo $driver['insurance_expiry_date'] ? date('M d, Y', strtotime($driver['insurance_expiry_date'])) : 'N/A'; ?>" readonly>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label class="form-control-label">Document</label>
                                            <?php if ($driver['insurance_document']): ?>
                                                <a href="<?php echo $driver['insurance_document']; ?>" target="_blank" class="btn btn-sm btn-outline-danger">View Document</a>
                                            <?php else: ?>
                                                <input class="form-control" type="text" value="No document uploaded" readonly>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                                <?php if ($role == 'admin'): ?>
                                    <div class="row mt-3">
                                        <div class="col-12">
                                            <a href="driver_details.php?id=<?php echo $driver_id; ?>&edit=1" class="btn btn-warning">Edit Driver</a>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <div class="col-lg-4">
                    <div class="card">
                        <div class="card-header pb-0 p-3">
                            <h6>Monthly Attendance</h6>
                        </div>
                        <div class="card-body p-3 text-center">
                            <div class="row">
                                <div class="col-6">
                                    <h4 class="text-primary"><?php echo $attendance['present_days'] ?? 0; ?></h4>
                                    <p class="text-sm text-muted">Present Days</p>
                                </div>
                                <div class="col-6">
                                    <h4 class="text-info"><?php echo $attendance['total_days'] ?? 0; ?></h4>
                                    <p class="text-sm text-muted">Total Days</p>
                                </div>
                            </div>
                            <div class="progress mt-3">
                                <div class="progress-bar bg-gradient-success" role="progressbar"
                                    style="width: <?php echo $attendance['total_days'] > 0 ? ($attendance['present_days'] / $attendance['total_days'] * 100) : 0; ?>%"
                                    aria-valuenow="<?php echo $attendance['total_days'] > 0 ? ($attendance['present_days'] / $attendance['total_days'] * 100) : 0; ?>"
                                    aria-valuemin="0" aria-valuemax="100"></div>
                            </div>
                            <p class="text-xs text-muted mt-2"><?php echo date('F Y'); ?> Attendance</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Assigned Car Information -->
            <?php if ($car): ?>
                <div class="row mt-4">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header pb-0 p-3">
                                <h6>Assigned Vehicle</h6>
                            </div>
                            <div class="card-body p-3">
                                <div class="row">
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label class="form-control-label">Make & Model</label>
                                            <input class="form-control" type="text" value="<?php echo htmlspecialchars($car['make'] . ' ' . $car['model']); ?>" readonly>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label class="form-control-label">License Plate</label>
                                            <input class="form-control" type="text" value="<?php echo htmlspecialchars($car['license_plate']); ?>" readonly>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label class="form-control-label">Year</label>
                                            <input class="form-control" type="text" value="<?php echo $car['year']; ?>" readonly>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label class="form-control-label">Seating Capacity</label>
                                            <input class="form-control" type="text" value="<?php echo $car['seating_capacity']; ?> Seater" readonly>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Recent Trips -->
            <div class="row mt-4">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header pb-0 p-3">
                            <h6>Recent Trips</h6>
                        </div>
                        <div class="card-body px-0 pb-2">
                            <div class="table-responsive">
                                <table class="table align-items-center mb-0">
                                    <thead>
                                        <tr>
                                            <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Trip ID</th>
                                            <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">Vehicle</th>
                                            <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">Passengers</th>
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
                                                    <p class="text-xs font-weight-bold mb-0"><?php echo htmlspecialchars($trip['make'] . ' ' . $trip['model']); ?></p>
                                                    <p class="text-xs text-secondary mb-0"><?php echo htmlspecialchars($trip['license_plate']); ?></p>
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
                                                <td class="align-middle">
                                                    <a href="trip_details.php?id=<?php echo $trip['id']; ?>" class="text-secondary font-weight-bold text-xs" data-toggle="tooltip" data-original-title="View Details">
                                                        View
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                        <?php if (empty($trips)): ?>
                                            <tr>
                                                <td colspan="7" class="text-center py-4">
                                                    <p class="text-sm text-muted">No trips found for this driver.</p>
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
