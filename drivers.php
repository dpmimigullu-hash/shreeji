<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';
session_start();

// --- Validate user session ---
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$conn = getDBConnection();
$user = getUserById($_SESSION['user_id']);
if (!$user || !isset($user['role'])) {
    session_destroy();
    header('Location: login.php');
    exit;
}

$role = $user['role'];

// --- Handle driver deletion ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_driver'])) {
    try {
        $driver_id = (int)$_POST['delete_driver'];

        // Check if user has permission to delete
        if ($role != 'admin') {
            throw new Exception("Only admins can delete drivers.");
        }

        // Delete driver
        $stmt = $conn->prepare("DELETE FROM users WHERE id = ? AND role = 'driver'");
        $stmt->execute([$driver_id]);

        echo "<div class='success'>✅ Driver deleted successfully.</div>";
    } catch (PDOException $e) {
        echo "<div class='error'>❌ Database Error: " . htmlspecialchars($e->getMessage()) . "</div>";
    } catch (Exception $ex) {
        echo "<div class='error'>⚠️ Error: " . htmlspecialchars($ex->getMessage()) . "</div>";
    }
}

// --- Handle new driver registration ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['delete_driver'])) {
    try {
        // Basic account info
        $username = trim($_POST['username'] ?? '');
        $password = password_hash($_POST['password'] ?? '', PASSWORD_BCRYPT);
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $role = 'driver';
        $supervisor_id = $_POST['supervisor_id'] ?? null;
        $branch_id = $_POST['branch_id'] ?? null;

        // Vehicle and document details
        $license_number = $_POST['license_number'] ?? null;
        $license_photo = $_POST['license_photo'] ?? null;
        $driver_photo = $_POST['driver_photo'] ?? null;
        $kyc_documents = $_POST['kyc_documents'] ?? null;

        $bank_name = $_POST['bank_name'] ?? null;
        $bank_account_number = $_POST['bank_account_number'] ?? null;
        $bank_ifsc_code = $_POST['bank_ifsc_code'] ?? null;
        $bank_branch = $_POST['bank_branch'] ?? null;

        $assigned_vehicle_id = $_POST['assigned_vehicle_id'] ?? null;
        $vehicle_registration_number = $_POST['vehicle_registration_number'] ?? null;
        $vehicle_chassis_number = $_POST['vehicle_chassis_number'] ?? null;
        $vehicle_engine_number = $_POST['vehicle_engine_number'] ?? null;
        $vehicle_fuel_type = $_POST['vehicle_fuel_type'] ?? null;
        $vehicle_photo = $_POST['vehicle_photo'] ?? null;

        $pollution_certificate = $_POST['pollution_certificate'] ?? null;
        $pollution_valid_from = $_POST['pollution_valid_from'] ?? null;
        $pollution_valid_till = $_POST['pollution_valid_till'] ?? null;
        $pollution_issued_by = $_POST['pollution_issued_by'] ?? null;

        $registration_certificate = $_POST['registration_certificate'] ?? null;
        $registration_valid_from = $_POST['registration_valid_from'] ?? null;
        $registration_valid_till = $_POST['registration_valid_till'] ?? null;
        $registration_issued_by = $_POST['registration_issued_by'] ?? null;

        $road_tax = $_POST['road_tax'] ?? null;
        $road_tax_valid_from = $_POST['road_tax_valid_from'] ?? null;
        $road_tax_valid_till = $_POST['road_tax_valid_till'] ?? null;
        $road_tax_issued_by = $_POST['road_tax_issued_by'] ?? null;

        $fast_tag = $_POST['fast_tag'] ?? null;
        $fast_tag_valid_from = $_POST['fast_tag_valid_from'] ?? null;
        $fast_tag_valid_till = $_POST['fast_tag_valid_till'] ?? null;
        $fast_tag_issued_by = $_POST['fast_tag_issued_by'] ?? null;

        $insurance_policy_number = $_POST['insurance_policy_number'] ?? null;
        $insurance_expiry_date = $_POST['insurance_expiry_date'] ?? null;
        $insurance_provider = $_POST['insurance_provider'] ?? null;
        $insurance_type = $_POST['insurance_type'] ?? null;
        $insurance_document = $_POST['insurance_document'] ?? null;

        $remarks = $_POST['remarks'] ?? null;

        // ✅ Prepare the full 44-column insert query
        $stmt = $conn->prepare("
            INSERT INTO users (
                username, password, name, email, phone, role, supervisor_id, branch_id,
                license_number, license_photo, driver_photo, kyc_documents,
                bank_name, bank_account_number, bank_ifsc_code, bank_branch,
                assigned_vehicle_id, vehicle_registration_number, vehicle_chassis_number,
                vehicle_engine_number, vehicle_fuel_type, vehicle_photo,
                pollution_certificate, pollution_valid_from, pollution_valid_till, pollution_issued_by,
                registration_certificate, registration_valid_from, registration_valid_till, registration_issued_by,
                road_tax, road_tax_valid_from, road_tax_valid_till, road_tax_issued_by,
                fast_tag, fast_tag_valid_from, fast_tag_valid_till, fast_tag_issued_by,
                insurance_policy_number, insurance_expiry_date, insurance_provider, insurance_type, insurance_document, remarks
            ) VALUES (
                ?, ?, ?, ?, ?, ?, ?, ?,
                ?, ?, ?, ?,
                ?, ?, ?, ?,
                ?, ?, ?,
                ?, ?, ?,
                ?, ?, ?, ?,
                ?, ?, ?, ?,
                ?, ?, ?, ?,
                ?, ?, ?, ?,
                ?, ?, ?, ?, ?, ?
            )
        ");

        // ✅ Execute with all 44 parameters in the same order
        $stmt->execute([
            $username,
            $password,
            $name,
            $email,
            $phone,
            $role,
            $supervisor_id,
            $branch_id,
            $license_number,
            $license_photo,
            $driver_photo,
            $kyc_documents,
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
            $pollution_issued_by,
            $registration_certificate,
            $registration_valid_from,
            $registration_valid_till,
            $registration_issued_by,
            $road_tax,
            $road_tax_valid_from,
            $road_tax_valid_till,
            $road_tax_issued_by,
            $fast_tag,
            $fast_tag_valid_from,
            $fast_tag_valid_till,
            $fast_tag_issued_by,
            $insurance_policy_number,
            $insurance_expiry_date,
            $insurance_provider,
            $insurance_type,
            $insurance_document,
            $remarks
        ]);

        echo "<div class='success'>✅ Driver added successfully.</div>";
    } catch (PDOException $e) {
        echo "<div class='error'>❌ Database Error: " . htmlspecialchars($e->getMessage()) . "</div>";
    } catch (Exception $ex) {
        echo "<div class='error'>⚠️ Error: " . htmlspecialchars($ex->getMessage()) . "</div>";
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, user-scalable=yes">
    <title>Drivers & Vehicles Management - Shreeji Link Employee Transportation System</title>
    <link rel="icon" type="image/png" href="./assets/images/logo.png">
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

        /* Corporate Form Styling */
        .corporate-form {
            background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%);
            border: 1px solid #e9ecef;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
        }

        .corporate-form .form-section {
            background: #ffffff;
            border: 1px solid #e9ecef;
            border-radius: 6px;
            padding: 16px;
            margin-bottom: 16px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
        }

        .corporate-form .section-header {
            background: linear-gradient(135deg, #1976D2 0%, #1565C0 100%);
            color: white;
            padding: 10px 14px;
            border-radius: 4px 4px 0 0;
            margin: -16px -16px 16px -16px;
            display: flex;
            align-items: center;
            gap: 6px;
            font-weight: 600;
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .corporate-form .form-group {
            margin-bottom: 16px;
        }

        .corporate-form .form-label {
            font-weight: 600;
            color: #2c3e50;
            font-size: 13px;
            margin-bottom: 6px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .corporate-form .form-control {
            border: 2px solid #e9ecef;
            border-radius: 6px;
            padding: 10px 12px;
            font-size: 14px;
            transition: all 0.3s ease;
            background: #ffffff;
        }

        .corporate-form .form-control:focus {
            border-color: #1976D2;
            box-shadow: 0 0 0 3px rgba(25, 118, 210, 0.1);
            background: #ffffff;
        }

        .corporate-form .form-control::placeholder {
            color: #95a5a6;
            font-style: italic;
        }

        .corporate-form .btn {
            padding: 12px 24px;
            border-radius: 6px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border: none;
            transition: all 0.3s ease;
        }

        .corporate-form .btn-primary {
            background: linear-gradient(135deg, #1976D2 0%, #1565C0 100%);
            box-shadow: 0 4px 15px rgba(25, 118, 210, 0.3);
        }

        .corporate-form .btn-primary:hover {
            background: linear-gradient(135deg, #1565C0 0%, #0D47A1 100%);
            transform: translateY(-1px);
            box-shadow: 0 6px 20px rgba(25, 118, 210, 0.4);
        }

        .corporate-form .btn-outline-secondary {
            border: 2px solid #6c757d;
            color: #6c757d;
            background: transparent;
        }

        .corporate-form .btn-outline-secondary:hover {
            background: #6c757d;
            color: white;
            transform: translateY(-1px);
        }

        .corporate-form .document-card {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border: 1px solid #dee2e6;
            border-radius: 6px;
            padding: 12px;
            margin-bottom: 10px;
            transition: all 0.3s ease;
        }

        .corporate-form .document-card:hover {
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            transform: translateY(-1px);
        }

        .corporate-form .document-header {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 12px;
            font-weight: 600;
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
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

<body>
    <?php include 'includes/sidebar.php'; ?>

    <main class="main-content position-relative max-height-vh-100 h-100 border-radius-lg">
        <!-- Welcome Section -->
        <div class="row">
            <div class="col-12">
                <div class="welcome-section modern-card">
                    <div class="row align-items-center">
                        <div class="col-md-8">
                            <h2 class="mb-2 text-primary font-weight-bold">
                                <i class="material-symbols-rounded text-primary me-2">people</i>
                                Welcome back, <?php echo htmlspecialchars($user['name']); ?>!
                            </h2>
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

        <div class="container-fluid py-2">
            <?php if ($role == 'admin' || $role == 'supervisor'): ?>
                <!-- Quick Stats Cards -->
                <div class="row">
                    <!-- Total Trips Card -->
                    <div class="col-xl-3 col-sm-6 mb-xl-0 mb-4">
                        <div class="stat-card-modern modern-card">
                            <div class="d-flex align-items-center justify-content-between">
                                <div>
                                    <p class="text-sm mb-2 text-muted font-weight-bold">Total Drivers</p>
                                    <h3 class="mb-1 font-weight-bolder text-primary">
                                        <?php
                                        try {
                                            $stmt = $conn->query("SELECT COUNT(*) FROM users WHERE role = 'driver'");
                                            echo $stmt->fetchColumn();
                                        } catch (PDOException $e) {
                                            echo '0';
                                        }
                                        ?>
                                    </h3>
                                    <p class="text-sm mb-0"><span class="text-success font-weight-bolder">+12%</span> than last month</p>
                                </div>
                                <div class="stat-icon-modern gradient-primary">
                                    <i class="material-symbols-rounded">people</i>
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
                                    <h3 class="mb-1 font-weight-bolder text-primary">
                                        <?php
                                        try {
                                            $stmt = $conn->query("SELECT COUNT(*) FROM users WHERE role = 'driver' AND status = 'active'");
                                            echo $stmt->fetchColumn();
                                        } catch (PDOException $e) {
                                            echo '0';
                                        }
                                        ?>
                                    </h3>
                                    <p class="text-sm mb-0"><span class="text-success font-weight-bolder">+8%</span> than last month</p>
                                </div>
                                <div class="stat-icon-modern gradient-secondary">
                                    <i class="material-symbols-rounded">person_check</i>
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
                                    <h3 class="mb-1 font-weight-bolder text-primary">
                                        <?php
                                        try {
                                            $stmt = $conn->query("SELECT COUNT(*) FROM vehicles WHERE driver_id IS NOT NULL");
                                            echo $stmt->fetchColumn();
                                        } catch (PDOException $e) {
                                            echo '0';
                                        }
                                        ?>
                                    </h3>
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
                                    <p class="text-sm mb-2 text-muted font-weight-bold">Available Vehicles</p>
                                    <h3 class="mb-1 font-weight-bolder text-primary">
                                        <?php
                                        try {
                                            $stmt = $conn->query("SELECT COUNT(*) FROM vehicles WHERE driver_id IS NULL");
                                            echo $stmt->fetchColumn();
                                        } catch (PDOException $e) {
                                            echo '0';
                                        }
                                        ?>
                                    </h3>
                                    <p class="text-sm mb-0"><span class="text-success font-weight-bolder">+5%</span> than last month</p>
                                </div>
                                <div class="stat-icon-modern gradient-error">
                                    <i class="material-symbols-rounded">local_shipping</i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($role == 'admin' || $role == 'supervisor'): ?>
                <!-- Action Buttons -->
                <div class="row mb-4">
                    <div class="col-12">
                        <div class="d-flex flex-wrap gap-2 justify-content-center">
                            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addDriverModal">
                                <i class="fas fa-plus me-1"></i>Add New Driver
                            </button>
                            <a href="vehicles.php" class="btn btn-success">
                                <i class="fas fa-car me-1"></i>Manage Vehicles
                            </a>
                            <button class="btn btn-info" onclick="exportDrivers()">
                                <i class="fas fa-download me-1"></i>Export Data
                            </button>
                            <button class="btn btn-secondary" onclick="window.location.reload()">
                                <i class="fas fa-sync-alt me-1"></i>Refresh
                            </button>
                        </div>
                    </div>
                </div>
            <?php endif; ?>


            <!-- Drivers List -->
            <div class="row">
                <div class="col-12">
                    <div class="modern-card h-100">
                        <div class="card-header pb-0 p-4 bg-light border-radius-lg">
                            <div class="d-flex justify-content-between align-items-center">
                                <h5 class="mb-0 font-weight-bolder text-primary">
                                    <i class="material-symbols-rounded text-primary me-2">people</i>Registered Drivers
                                </h5>
                                <div class="d-flex gap-2 align-items-center">
                                    <span class="text-muted small d-none d-md-inline">
                                        <i class="material-symbols-rounded me-1">search</i>Search & Filter
                                    </span>
                                    <div class="vr d-none d-md-block"></div>
                                    <small class="text-muted fw-bold">
                                        <?php
                                        try {
                                            $stmt = $conn->query("SELECT COUNT(*) FROM users WHERE role = 'driver'");
                                            echo $stmt->fetchColumn();
                                        } catch (PDOException $e) {
                                            echo '0';
                                        }
                                        ?> Drivers
                                    </small>
                                </div>
                            </div>
                        </div>
                        <div class="card-body p-4">
                            <!-- Search and Filter Section -->
                            <div class="search-filter-section mb-4">
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <div class="position-relative">
                                            <i class="material-symbols-rounded position-absolute top-50 start-0 translate-middle-y ms-3 text-muted">search</i>
                                            <input type="text" id="searchInput" class="search-input form-control ps-5" placeholder="Search drivers by name...">
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <select id="statusFilter" class="filter-select form-select">
                                            <option value="">All Status</option>
                                            <option value="active">Active</option>
                                            <option value="inactive">Inactive</option>
                                        </select>
                                    </div>
                                    <div class="col-md-2">
                                        <button class="btn btn-primary w-100" onclick="window.location.reload()">
                                            <i class="material-symbols-rounded me-1">refresh</i>
                                            <span class="d-none d-sm-inline">Refresh</span>
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <!-- Drivers Grid/List -->
                            <div id="drivers-container">
                                <?php
                                try {
                                    $stmt = $conn->query("SELECT u.*, s.name as supervisor_name FROM users u LEFT JOIN users s ON u.supervisor_id = s.id WHERE u.role = 'driver' ORDER BY u.created_at DESC");
                                    $drivers = $stmt->fetchAll(PDO::FETCH_ASSOC);

                                    if (count($drivers) > 0) {
                                        foreach ($drivers as $driver) {
                                            $statusClass = isset($driver['status']) && $driver['status'] == 'active' ? 'success' : 'secondary';
                                            $statusText = isset($driver['status']) && $driver['status'] == 'active' ? 'Active' : 'Inactive';
                                            $initials = strtoupper(substr($driver['name'], 0, 2));
                                            $avatarColor = ['gradient-primary', 'gradient-success', 'gradient-warning', 'gradient-danger'][array_rand(['gradient-primary', 'gradient-success', 'gradient-warning', 'gradient-danger'])];
                                ?>
                                            <div class="driver-card fade-in driver-row" data-name="<?php echo htmlspecialchars(strtolower($driver['name'])); ?>" data-status="<?php echo htmlspecialchars($driver['status'] ?? 'active'); ?>">
                                                <div class="row align-items-center">
                                                    <!-- Driver Avatar and Basic Info -->
                                                    <div class="col-lg-4 col-md-5">
                                                        <div class="d-flex align-items-center">
                                                            <div class="driver-avatar <?php echo $avatarColor; ?>">
                                                                <?php echo $initials; ?>
                                                            </div>
                                                            <div class="driver-info ms-3">
                                                                <h6 class="mb-1"><?php echo htmlspecialchars($driver['name']); ?></h6>
                                                                <small class="text-muted">
                                                                    <i class="material-symbols-rounded me-1" style="font-size: 0.75rem;">alternate_email</i>
                                                                    @<?php echo htmlspecialchars($driver['username']); ?>
                                                                </small>
                                                            </div>
                                                        </div>
                                                    </div>

                                                    <!-- Contact Information -->
                                                    <div class="col-lg-3 col-md-4">
                                                        <div class="driver-contact-info">
                                                            <div class="mb-1">
                                                                <small class="text-muted d-block">
                                                                    <i class="material-symbols-rounded me-1" style="font-size: 0.75rem;">email</i>
                                                                    <?php echo htmlspecialchars($driver['email']); ?>
                                                                </small>
                                                            </div>
                                                            <div class="mb-1">
                                                                <small class="text-muted d-block">
                                                                    <i class="material-symbols-rounded me-1" style="font-size: 0.75rem;">phone</i>
                                                                    <?php echo htmlspecialchars($driver['phone']); ?>
                                                                </small>
                                                            </div>
                                                            <div>
                                                                <small class="text-muted d-block">
                                                                    <i class="material-symbols-rounded me-1" style="font-size: 0.75rem;">badge</i>
                                                                    <?php echo htmlspecialchars($driver['license_number']); ?>
                                                                </small>
                                                            </div>
                                                        </div>
                                                    </div>

                                                    <!-- Supervisor and Status -->
                                                    <div class="col-lg-2 col-md-3">
                                                        <div class="text-center">
                                                            <?php if (isset($driver['supervisor_name'])): ?>
                                                                <small class="text-muted d-block mb-2">
                                                                    <i class="material-symbols-rounded me-1" style="font-size: 0.75rem;">supervisor_account</i>
                                                                    <?php echo htmlspecialchars($driver['supervisor_name']); ?>
                                                                </small>
                                                            <?php endif; ?>
                                                            <span class="status-badge-modern bg-gradient-<?php echo $statusClass; ?> text-white">
                                                                <i class="material-symbols-rounded me-1" style="font-size: 0.75rem;">
                                                                    <?php echo $statusClass == 'success' ? 'check_circle' : 'radio_button_unchecked'; ?>
                                                                </i>
                                                                <?php echo $statusText; ?>
                                                            </span>
                                                        </div>
                                                    </div>

                                                    <!-- Action Buttons -->
                                                    <div class="col-lg-3 col-md-12">
                                                        <div class="action-buttons d-flex justify-content-end gap-2 mt-3 mt-md-0">
                                                            <button class="btn-modern btn-info-modern" onclick="viewDriver(<?php echo $driver['id']; ?>)" title="View Details">
                                                                <i class="material-symbols-rounded">visibility</i>
                                                                <span class="d-none d-lg-inline ms-1">View</span>
                                                            </button>
                                                            <button class="btn-modern btn-secondary-modern" onclick="editDriver(<?php echo $driver['id']; ?>)" title="Edit Driver">
                                                                <i class="material-symbols-rounded">edit</i>
                                                                <span class="d-none d-lg-inline ms-1">Edit</span>
                                                            </button>
                                                            <button class="btn-modern btn-secondary-modern" onclick="deleteDriver(<?php echo $driver['id']; ?>)" title="Delete Driver">
                                                                <i class="material-symbols-rounded">delete</i>
                                                                <span class="d-none d-lg-inline ms-1">Delete</span>
                                                            </button>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php
                                        }
                                    } else {
                                        ?>
                                        <div class="empty-state">
                                            <div class="empty-state-icon">
                                                <i class="material-symbols-rounded">people</i>
                                            </div>
                                            <h4>No Drivers Registered</h4>
                                            <p>Get started by adding your first driver to begin managing your transportation operations.</p>
                                            <button class="btn-modern btn-primary-modern" data-bs-toggle="modal" data-bs-target="#addDriverModal">
                                                <i class="material-symbols-rounded">person_add</i>
                                                <span>Add Your First Driver</span>
                                            </button>
                                        </div>
                                <?php
                                    }
                                } catch (PDOException $e) {
                                    echo '<div class="alert alert-danger">Error loading drivers: ' . htmlspecialchars($e->getMessage()) . '</div>';
                                }
                                ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        </div>

        <!-- Add Driver Modal -->
        <div class="modal fade" id="addDriverModal" tabindex="-1">
            <div class="modal-dialog modal-lg" style="max-width: 700px;">
                <div class="modal-content corporate-form">
                    <div class="modal-header" style="background: linear-gradient(135deg, #1976D2 0%, #1565C0 100%); color: white; border: none; padding: 16px 20px;">
                        <h5 class="modal-title font-weight-bolder" style="font-size: 18px;">
                            <i class="material-symbols-rounded me-2">person_add</i>Add New Driver
                        </h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <form method="POST" enctype="multipart/form-data">
                        <div class="modal-body" style="padding: 20px;">
                            <!-- Basic Information Section -->
                            <div class="form-section">
                                <div class="section-header">
                                    <i class="material-symbols-rounded">person</i>
                                    <span>Basic Information</span>
                                </div>
                                <div class="row">
                                    <div class="col-12 mb-2">
                                        <label class="form-label font-weight-semibold small">Username *</label>
                                        <input type="text" name="username" class="form-control form-control-sm" required placeholder="Enter username">
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-12 mb-2">
                                        <label class="form-label font-weight-semibold small">Password *</label>
                                        <input type="password" name="password" class="form-control form-control-sm" required placeholder="Enter password">
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-12 mb-2">
                                        <label class="form-label font-weight-semibold small">Full Name *</label>
                                        <input type="text" name="name" class="form-control form-control-sm" required placeholder="Enter full name">
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-12 mb-2">
                                        <label class="form-label font-weight-semibold small">Email *</label>
                                        <input type="email" name="email" class="form-control form-control-sm" required placeholder="Enter email address">
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-12 mb-2">
                                        <label class="form-label font-weight-semibold small">Phone</label>
                                        <input type="text" name="phone" class="form-control form-control-sm" placeholder="Enter phone number">
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-12 mb-2">
                                        <label class="form-label font-weight-semibold small">License Number *</label>
                                        <input type="text" name="license_number" class="form-control form-control-sm" required placeholder="Enter license number">
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-12 mb-2">
                                        <label class="form-label font-weight-semibold small">Supervisor *</label>
                                        <select name="supervisor_id" class="form-control form-control-sm" required>
                                            <option value="">Select Supervisor</option>
                                            <?php
                                            try {
                                                $stmt = $conn->query("SELECT id, name FROM users WHERE role = 'supervisor' ORDER BY name");
                                                $supervisors = $stmt->fetchAll(PDO::FETCH_ASSOC);
                                                foreach ($supervisors as $supervisor) {
                                                    echo '<option value="' . $supervisor['id'] . '">' . htmlspecialchars($supervisor['name']) . '</option>';
                                                }
                                            } catch (PDOException $e) {
                                                // Supervisors table might not exist
                                            }
                                            ?>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <!-- Documents Section -->
                            <div class="form-section">
                                <div class="section-header">
                                    <i class="material-symbols-rounded">description</i>
                                    <span>Documents</span>
                                </div>
                                <div class="row">
                                    <div class="col-12 mb-2">
                                        <label class="form-label font-weight-semibold small">License Photo</label>
                                        <input type="file" name="license_photo" class="form-control form-control-sm" accept="image/*">
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-12 mb-2">
                                        <label class="form-label font-weight-semibold small">Driver Photo</label>
                                        <input type="file" name="driver_photo" class="form-control form-control-sm" accept="image/*">
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-12 mb-2">
                                        <label class="form-label font-weight-semibold small">KYC Documents</label>
                                        <input type="file" name="kyc_documents[]" class="form-control form-control-sm" multiple accept="image/*,application/pdf">
                                    </div>
                                </div>
                            </div>

                            <!-- Bank Details Section -->
                            <div class="form-section">
                                <div class="section-header">
                                    <i class="material-symbols-rounded">account_balance</i>
                                    <span>Bank Details</span>
                                </div>
                                <div class="row">
                                    <div class="col-12 mb-2">
                                        <label class="form-label font-weight-semibold small">Bank Name</label>
                                        <input type="text" name="bank_name" class="form-control form-control-sm" placeholder="Enter bank name">
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-12 mb-2">
                                        <label class="form-label font-weight-semibold small">Account Number</label>
                                        <input type="text" name="bank_account_number" class="form-control form-control-sm" placeholder="Enter account number">
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-12 mb-2">
                                        <label class="form-label font-weight-semibold small">IFSC Code</label>
                                        <input type="text" name="bank_ifsc_code" class="form-control form-control-sm" placeholder="Enter IFSC code">
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-12 mb-2">
                                        <label class="form-label font-weight-semibold small">Branch</label>
                                        <input type="text" name="bank_branch" class="form-control form-control-sm" placeholder="Enter branch name">
                                    </div>
                                </div>
                            </div>

                            <!-- Vehicle Assignment Section -->
                            <div class="form-section">
                                <div class="section-header">
                                    <i class="material-symbols-rounded">directions_car</i>
                                    <span>Vehicle Assignment</span>
                                </div>
                                <div class="row">
                                    <div class="col-12 mb-2">
                                        <label class="form-label font-weight-semibold small">Vehicle Registration Number</label>
                                        <input type="text" name="vehicle_registration_number" class="form-control form-control-sm" placeholder="e.g., MH01AB1234">
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-12 mb-2">
                                        <label class="form-label font-weight-semibold small">Assign Vehicle</label>
                                        <select name="vehicle_id" class="form-control form-control-sm mb-2">
                                            <option value="">Select Vehicle (Optional)</option>
                                            <?php
                                            try {
                                                // Get all available vehicles from vehicles table with make, model, and capacity
                                                $stmt = $conn->query("
                                                SELECT v.id, v.make, v.model, v.seating_capacity, v.license_plate
                                                FROM vehicles v
                                                WHERE v.driver_id IS NULL OR v.driver_id = ''
                                                ORDER BY v.make, v.model
                                            ");
                                                $vehicles = $stmt->fetchAll(PDO::FETCH_ASSOC);

                                                if (count($vehicles) > 0) {
                                                    foreach ($vehicles as $vehicle) {
                                                        $vehicle_display = htmlspecialchars($vehicle['make'] . ' ' . $vehicle['model'] . ' (' . $vehicle['seating_capacity'] . ' seats) - ' . $vehicle['license_plate']);
                                                        echo '<option value="' . $vehicle['id'] . '">' . $vehicle_display . '</option>';
                                                    }
                                                } else {
                                                    echo '<option value="" disabled>No unassigned vehicles available</option>';
                                                }
                                            } catch (PDOException $e) {
                                                echo '<option value="" disabled>Database error: ' . htmlspecialchars($e->getMessage()) . '</option>';
                                            }
                                            ?>
                                        </select>
                                        <small class="text-muted d-block mb-2">Or enter vehicle registration number manually:</small>
                                        <input type="text" name="manual_vehicle_registration" class="form-control form-control-sm" placeholder="Enter vehicle registration number (e.g., MH01AB1234)">
                                    </div>
                                </div>
                            </div>

                            <!-- Vehicle Details Section -->
                            <div class="form-section">
                                <div class="section-header">
                                    <i class="material-symbols-rounded">build</i>
                                    <span>Vehicle Details</span>
                                </div>
                                <div class="row">
                                    <div class="col-12 mb-2">
                                        <label class="form-label font-weight-semibold small">Chassis Number</label>
                                        <input type="text" name="vehicle_chassis_number" class="form-control form-control-sm" placeholder="Vehicle chassis number">
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-12 mb-2">
                                        <label class="form-label font-weight-semibold small">Engine Number</label>
                                        <input type="text" name="vehicle_engine_number" class="form-control form-control-sm" placeholder="Vehicle engine number">
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-12 mb-2">
                                        <label class="form-label font-weight-semibold small">Fuel Type</label>
                                        <select name="vehicle_fuel_type" class="form-control form-control-sm">
                                            <option value="">Select Fuel Type</option>
                                            <option value="petrol">Petrol</option>
                                            <option value="diesel">Diesel</option>
                                            <option value="cng">CNG</option>
                                            <option value="electric">Electric</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-12 mb-2">
                                        <label class="form-label font-weight-semibold small">Vehicle Photo</label>
                                        <input type="file" name="vehicle_photos" class="form-control form-control-sm" accept="image/*">
                                    </div>
                                </div>
                            </div>

                            <!-- Vehicle Documents Section -->
                            <div class="form-section">
                                <div class="section-header">
                                    <i class="material-symbols-rounded">folder</i>
                                    <span>Vehicle Documents</span>
                                </div>

                                <!-- Pollution Certificate -->
                                <div class="document-card">
                                    <div class="document-header text-success">
                                        <i class="material-symbols-rounded">eco</i>
                                        <span>Pollution Certificate</span>
                                    </div>
                                    <div class="row">
                                        <div class="col-12 mb-2">
                                            <input type="file" name="pollution_certificate" class="form-control form-control-sm" accept="image/*,application/pdf" placeholder="Upload document">
                                        </div>
                                    </div>
                                    <div class="row">
                                        <div class="col-12 mb-2">
                                            <input type="date" name="pollution_valid_from" class="form-control form-control-sm" placeholder="Valid From">
                                        </div>
                                    </div>
                                    <div class="row">
                                        <div class="col-12 mb-2">
                                            <input type="date" name="pollution_valid_till" class="form-control form-control-sm" placeholder="Valid Till">
                                        </div>
                                    </div>
                                </div>

                                <!-- Registration Certificate -->
                                <div class="document-card">
                                    <div class="document-header text-primary">
                                        <i class="material-symbols-rounded">article</i>
                                        <span>Registration Certificate</span>
                                    </div>
                                    <div class="row">
                                        <div class="col-12 mb-2">
                                            <input type="file" name="registration_certificate" class="form-control form-control-sm" accept="image/*,application/pdf" placeholder="Upload document">
                                        </div>
                                    </div>
                                    <div class="row">
                                        <div class="col-12 mb-2">
                                            <input type="date" name="registration_valid_from" class="form-control form-control-sm" placeholder="Valid From">
                                        </div>
                                    </div>
                                    <div class="row">
                                        <div class="col-12 mb-2">
                                            <input type="date" name="registration_valid_till" class="form-control form-control-sm" placeholder="Valid Till">
                                        </div>
                                    </div>
                                </div>

                                <!-- Road Tax -->
                                <div class="document-card">
                                    <div class="document-header text-warning">
                                        <i class="material-symbols-rounded">toll</i>
                                        <span>Road Tax</span>
                                    </div>
                                    <div class="row">
                                        <div class="col-12 mb-2">
                                            <input type="file" name="road_tax" class="form-control form-control-sm" accept="image/*,application/pdf" placeholder="Upload document">
                                        </div>
                                    </div>
                                    <div class="row">
                                        <div class="col-12 mb-2">
                                            <input type="date" name="road_tax_valid_from" class="form-control form-control-sm" placeholder="Valid From">
                                        </div>
                                    </div>
                                    <div class="row">
                                        <div class="col-12 mb-2">
                                            <input type="date" name="road_tax_valid_till" class="form-control form-control-sm" placeholder="Valid Till">
                                        </div>
                                    </div>
                                </div>

                                <!-- Fast Tag -->
                                <div class="document-card">
                                    <div class="document-header text-info">
                                        <i class="material-symbols-rounded">credit_card</i>
                                        <span>Fast Tag</span>
                                    </div>
                                    <div class="row">
                                        <div class="col-12 mb-2">
                                            <input type="file" name="fast_tag" class="form-control form-control-sm" accept="image/*,application/pdf" placeholder="Upload document">
                                        </div>
                                    </div>
                                    <div class="row">
                                        <div class="col-12 mb-2">
                                            <input type="date" name="fast_tag_valid_from" class="form-control form-control-sm" placeholder="Valid From">
                                        </div>
                                    </div>
                                    <div class="row">
                                        <div class="col-12 mb-2">
                                            <input type="date" name="fast_tag_valid_till" class="form-control form-control-sm" placeholder="Valid Till">
                                        </div>
                                    </div>
                                </div>

                                <!-- Insurance -->
                                <div class="document-card">
                                    <div class="document-header text-danger">
                                        <i class="material-symbols-rounded">security</i>
                                        <span>Insurance</span>
                                    </div>
                                    <div class="row">
                                        <div class="col-12 mb-2">
                                            <input type="text" name="insurance_policy_number" class="form-control form-control-sm" placeholder="Policy Number">
                                        </div>
                                    </div>
                                    <div class="row">
                                        <div class="col-12 mb-2">
                                            <input type="text" name="insurance_provider" class="form-control form-control-sm" placeholder="Insurance Provider">
                                        </div>
                                    </div>
                                    <div class="row">
                                        <div class="col-12 mb-2">
                                            <input type="file" name="insurance_document" class="form-control form-control-sm" accept="image/*,application/pdf" placeholder="Upload document">
                                        </div>
                                    </div>
                                    <div class="row">
                                        <div class="col-12 mb-2">
                                            <input type="date" name="insurance_expiry_date" class="form-control form-control-sm" placeholder="Expiry Date">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer" style="background: #f8f9fa; border-top: 1px solid #dee2e6; padding: 16px 20px;">
                            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal" style="padding: 8px 16px; font-size: 14px;">
                                <i class="material-symbols-rounded me-1">close</i>Cancel
                            </button>
                            <button type="submit" class="btn btn-primary" style="padding: 8px 16px; font-size: 14px;">
                                <i class="material-symbols-rounded me-1">person_add</i>Add Driver
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <script src="./assets/js/core/popper.min.js"></script>
        <script src="./assets/js/core/bootstrap.min.js"></script>
        <script src="./assets/js/plugins/perfect-scrollbar.min.js"></script>
        <script src="./assets/js/plugins/smooth-scrollbar.min.js"></script>
        <script src="./assets/js/plugins/chartjs.min.js"></script>
        <script src="./assets/js/material-dashboard.min.js?v=3.2.0"></script>
        <script>
            // Mobile navigation toggle functionality
            document.addEventListener('DOMContentLoaded', function() {
                const navbarToggler = document.getElementById('mobile-nav-toggle');
                const navbarCollapse = document.getElementById('navbarNav');

                if (navbarToggler && navbarCollapse) {
                    navbarToggler.addEventListener('click', function(e) {
                        e.preventDefault();
                        navbarCollapse.classList.toggle('show');
                    });

                    // Close mobile menu when clicking on a link
                    const navLinks = navbarCollapse.querySelectorAll('.nav-link');
                    navLinks.forEach(link => {
                        link.addEventListener('click', function() {
                            navbarCollapse.classList.remove('show');
                        });
                    });

                    // Close mobile menu when clicking outside
                    document.addEventListener('click', function(e) {
                        if (!navbarCollapse.contains(e.target) && !navbarToggler.contains(e.target)) {
                            navbarCollapse.classList.remove('show');
                        }
                    });

                    // Close mobile menu on escape key
                    document.addEventListener('keydown', function(e) {
                        if (e.key === 'Escape' && navbarCollapse.classList.contains('show')) {
                            navbarCollapse.classList.remove('show');
                        }
                    });
                }
            });

            function viewDriver(id) {
                window.location.href = 'driver_details.php?id=' + id;
            }

            function editDriver(id) {
                window.location.href = 'driver_details.php?id=' + id + '&edit=1';
            }

            function deleteDriver(id) {
                if (confirm('Are you sure you want to delete this driver? This action cannot be undone.')) {
                    // Create a form to submit DELETE request
                    const form = document.createElement('form');
                    form.method = 'POST';
                    form.action = 'drivers.php';

                    const input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = 'delete_driver';
                    input.value = id;

                    form.appendChild(input);
                    document.body.appendChild(form);
                    form.submit();
                }
            }

            function exportDrivers() {
                window.location.href = 'export_drivers.php';
            }

            // Search and Filter Functionality
            document.getElementById('searchInput').addEventListener('input', function() {
                const searchTerm = this.value.toLowerCase();
                const rows = document.querySelectorAll('.driver-row');

                rows.forEach(row => {
                    const name = row.getAttribute('data-name');
                    const isVisible = name.includes(searchTerm);
                    row.style.display = isVisible ? '' : 'none';
                });
            });

            document.getElementById('statusFilter').addEventListener('change', function() {
                const statusFilter = this.value.toLowerCase();
                const rows = document.querySelectorAll('.driver-row');

                rows.forEach(row => {
                    const status = row.getAttribute('data-status');
                    const isVisible = !statusFilter || status === statusFilter;
                    row.style.display = isVisible ? '' : 'none';
                });
            });

            // Add some custom CSS for better styling
            const style = document.createElement('style');
            style.textContent = `
            .border-left-primary { border-left: 4px solid #1976D2 !important; }
            .border-left-success { border-left: 4px solid #4CAF50 !important; }
            .border-left-info { border-left: 4px solid #17a2b8 !important; }
            .border-left-warning { border-left: 4px solid #ffc107 !important; }
            .text-primary { color: #1976D2 !important; }
            .text-success { color: #4CAF50 !important; }
            .text-info { color: #17a2b8 !important; }
            .text-warning { color: #ffc107 !important; }
            .bg-primary { background-color: #1976D2 !important; }
            .bg-success { background-color: #4CAF50 !important; }
            .bg-info { background-color: #17a2b8 !important; }
            .bg-warning { background-color: #ffc107 !important; }
            .shadow { box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075) !important; }
            .avatar { border-radius: 50%; }
            .btn-group .btn { margin-right: 2px; }
            .btn-group .btn:last-child { margin-right: 0; }
        `;
            document.head.appendChild(style);
        </script>
</body>

</html>
?>