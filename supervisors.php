<?php
session_start();
require_once 'includes/config.php';
require_once 'includes/functions.php';

// Check if user is logged in and has appropriate role
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

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['add_supervisor'])) {
        $username = trim($_POST['username']);
        $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
        $name = trim($_POST['name']);
        $email = trim($_POST['email']);
        $phone = isset($_POST['phone']) && !empty(trim($_POST['phone'])) ? trim($_POST['phone']) : '';
        $address = isset($_POST['address']) && !empty(trim($_POST['address'])) ? trim($_POST['address']) : '';
        $branch_id = isset($_POST['branch_id']) && !empty($_POST['branch_id']) ? $_POST['branch_id'] : '';
        $assign_multiple_branches = isset($_POST['assign_multiple_branches']) ? true : false;
        $additional_branches = isset($_POST['additional_branches']) && is_array($_POST['additional_branches']) ? $_POST['additional_branches'] : [];

        // Supervisor bank details - handle empty values properly
        $bank_name = isset($_POST['bank_name']) && !empty(trim($_POST['bank_name'])) ? trim($_POST['bank_name']) : '';
        $bank_account_number = isset($_POST['bank_account_number']) && !empty(trim($_POST['bank_account_number'])) ? trim($_POST['bank_account_number']) : '';
        $bank_ifsc_code = isset($_POST['bank_ifsc_code']) && !empty(trim($_POST['bank_ifsc_code'])) ? trim($_POST['bank_ifsc_code']) : '';
        $bank_branch = isset($_POST['bank_branch']) && !empty(trim($_POST['bank_branch'])) ? trim($_POST['bank_branch']) : '';

        // Handle file uploads
        $upload_dir = 'uploads/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }

        $supervisor_photo_path = '';
        $kyc_paths = [];

        // Upload supervisor photo
        if (isset($_FILES['supervisor_photo']) && $_FILES['supervisor_photo']['error'] == 0) {
            $supervisor_photo_path = $upload_dir . 'supervisor_' . time() . '_' . basename($_FILES['supervisor_photo']['name']);
            move_uploaded_file($_FILES['supervisor_photo']['tmp_name'], $supervisor_photo_path);
        }

        // Upload KYC documents
        if (isset($_FILES['kyc_documents'])) {
            foreach ($_FILES['kyc_documents']['tmp_name'] as $key => $tmp_name) {
                if ($_FILES['kyc_documents']['error'][$key] == 0) {
                    $kyc_path = $upload_dir . 'supervisor_kyc_' . time() . '_' . $key . '_' . basename($_FILES['kyc_documents']['name'][$key]);
                    move_uploaded_file($tmp_name, $kyc_path);
                    $kyc_paths[] = $kyc_path;
                }
            }
        }

        $conn = getDBConnection();

        // Check if username already exists
        $checkStmt = $conn->prepare("SELECT id FROM users WHERE username = ?");
        $checkStmt->execute([$username]);
        if ($checkStmt->fetch()) {
            throw new Exception("Username already exists. Please choose a different username.");
        }

        // Check if email already exists
        $checkStmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $checkStmt->execute([$email]);
        if ($checkStmt->fetch()) {
            throw new Exception("Email already exists. Please use a different email address.");
        }

        try {
            // Insert supervisor into users table - try with all columns first, fallback progressively
            try {
                $stmt = $conn->prepare("INSERT INTO users (username, password, name, email, phone, role, branch_id, bank_name, bank_account_number, bank_ifsc_code, bank_branch, driver_photo, kyc_documents) VALUES (?, ?, ?, ?, ?, 'supervisor', ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$username, $password, $name, $email, $phone, $branch_id, $bank_name, $bank_account_number, $bank_ifsc_code, $bank_branch, $supervisor_photo_path, json_encode($kyc_paths)]);
                $supervisor_id = $conn->lastInsertId();

                // Handle multiple branch assignments if selected
                if ($assign_multiple_branches && !empty($additional_branches)) {
                    // Create supervisor_branches table if it doesn't exist
                    try {
                        $conn->exec("CREATE TABLE IF NOT EXISTS supervisor_branches (
                            id INT AUTO_INCREMENT PRIMARY KEY,
                            supervisor_id INT NOT NULL,
                            branch_id INT NOT NULL,
                            assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                            FOREIGN KEY (supervisor_id) REFERENCES users(id) ON DELETE CASCADE,
                            FOREIGN KEY (branch_id) REFERENCES branches(id) ON DELETE CASCADE,
                            UNIQUE KEY unique_supervisor_branch (supervisor_id, branch_id)
                        )");
                    } catch (PDOException $e) {
                        // Table might already exist, continue
                    }

                    // Insert additional branch assignments
                    foreach ($additional_branches as $additional_branch_id) {
                        if ($additional_branch_id != $branch_id) { // Don't duplicate primary branch
                            try {
                                $stmt = $conn->prepare("INSERT INTO supervisor_branches (supervisor_id, branch_id) VALUES (?, ?)");
                                $stmt->execute([$supervisor_id, $additional_branch_id]);
                            } catch (PDOException $e) {
                                // Might be duplicate, continue
                            }
                        }
                    }
                }
            } catch (PDOException $e) {
                // If bank columns don't exist, try without bank details
                try {
                    $stmt = $conn->prepare("INSERT INTO users (username, password, name, email, phone, role, branch_id, driver_photo, kyc_documents) VALUES (?, ?, ?, ?, ?, 'supervisor', ?, ?, ?)");
                    $stmt->execute([$username, $password, $name, $email, $phone, $branch_id, $supervisor_photo_path, json_encode($kyc_paths)]);
                    $supervisor_id = $conn->lastInsertId();

                    // Handle multiple branch assignments if selected
                    if ($assign_multiple_branches && !empty($additional_branches)) {
                        // Create supervisor_branches table if it doesn't exist
                        try {
                            $conn->exec("CREATE TABLE IF NOT EXISTS supervisor_branches (
                                id INT AUTO_INCREMENT PRIMARY KEY,
                                supervisor_id INT NOT NULL,
                                branch_id INT NOT NULL,
                                assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                                FOREIGN KEY (supervisor_id) REFERENCES users(id) ON DELETE CASCADE,
                                FOREIGN KEY (branch_id) REFERENCES branches(id) ON DELETE CASCADE,
                                UNIQUE KEY unique_supervisor_branch (supervisor_id, branch_id)
                            )");
                        } catch (PDOException $e) {
                            // Table might already exist, continue
                        }

                        // Insert additional branch assignments
                        foreach ($additional_branches as $additional_branch_id) {
                            if ($additional_branch_id != $branch_id) { // Don't duplicate primary branch
                                try {
                                    $stmt = $conn->prepare("INSERT INTO supervisor_branches (supervisor_id, branch_id) VALUES (?, ?)");
                                    $stmt->execute([$supervisor_id, $additional_branch_id]);
                                } catch (PDOException $e) {
                                    // Might be duplicate, continue
                                }
                            }
                        }
                    }
                } catch (PDOException $e2) {
                    // If driver_photo column doesn't exist, try without photo columns
                    try {
                        $stmt = $conn->prepare("INSERT INTO users (username, password, name, email, phone, role, branch_id, kyc_documents) VALUES (?, ?, ?, ?, ?, 'supervisor', ?, ?)");
                        $stmt->execute([$username, $password, $name, $email, $phone, $branch_id, json_encode($kyc_paths)]);
                        $supervisor_id = $conn->lastInsertId();

                        // Handle multiple branch assignments if selected
                        if ($assign_multiple_branches && !empty($additional_branches)) {
                            // Create supervisor_branches table if it doesn't exist
                            try {
                                $conn->exec("CREATE TABLE IF NOT EXISTS supervisor_branches (
                                    id INT AUTO_INCREMENT PRIMARY KEY,
                                    supervisor_id INT NOT NULL,
                                    branch_id INT NOT NULL,
                                    assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                                    FOREIGN KEY (supervisor_id) REFERENCES users(id) ON DELETE CASCADE,
                                    FOREIGN KEY (branch_id) REFERENCES branches(id) ON DELETE CASCADE,
                                    UNIQUE KEY unique_supervisor_branch (supervisor_id, branch_id)
                                )");
                            } catch (PDOException $e) {
                                // Table might already exist, continue
                            }

                            // Insert additional branch assignments
                            foreach ($additional_branches as $additional_branch_id) {
                                if ($additional_branch_id != $branch_id) { // Don't duplicate primary branch
                                    try {
                                        $stmt = $conn->prepare("INSERT INTO supervisor_branches (supervisor_id, branch_id) VALUES (?, ?)");
                                        $stmt->execute([$supervisor_id, $additional_branch_id]);
                                    } catch (PDOException $e) {
                                        // Might be duplicate, continue
                                    }
                                }
                            }
                        }
                    } catch (PDOException $e3) {
                        // If kyc_documents column doesn't exist, try basic columns
                        try {
                            $stmt = $conn->prepare("INSERT INTO users (username, password, name, email, phone, role, branch_id) VALUES (?, ?, ?, ?, ?, 'supervisor', ?)");
                            $stmt->execute([$username, $password, $name, $email, $phone, $branch_id]);
                            $supervisor_id = $conn->lastInsertId();

                            // Handle multiple branch assignments if selected
                            if ($assign_multiple_branches && !empty($additional_branches)) {
                                // Create supervisor_branches table if it doesn't exist
                                try {
                                    $conn->exec("CREATE TABLE IF NOT EXISTS supervisor_branches (
                                        id INT AUTO_INCREMENT PRIMARY KEY,
                                        supervisor_id INT NOT NULL,
                                        branch_id INT NOT NULL,
                                        assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                                        FOREIGN KEY (supervisor_id) REFERENCES users(id) ON DELETE CASCADE,
                                        FOREIGN KEY (branch_id) REFERENCES branches(id) ON DELETE CASCADE,
                                        UNIQUE KEY unique_supervisor_branch (supervisor_id, branch_id)
                                    )");
                                } catch (PDOException $e) {
                                    // Table might already exist, continue
                                }

                                // Insert additional branch assignments
                                foreach ($additional_branches as $additional_branch_id) {
                                    if ($additional_branch_id != $branch_id) { // Don't duplicate primary branch
                                        try {
                                            $stmt = $conn->prepare("INSERT INTO supervisor_branches (supervisor_id, branch_id) VALUES (?, ?)");
                                            $stmt->execute([$supervisor_id, $additional_branch_id]);
                                        } catch (PDOException $e) {
                                            // Might be duplicate, continue
                                        }
                                    }
                                }
                            }
                        } catch (PDOException $e4) {
                            // If branch_id column doesn't exist, try without it
                            $stmt = $conn->prepare("INSERT INTO users (username, password, name, email, phone, role) VALUES (?, ?, ?, ?, ?, 'supervisor')");
                            $stmt->execute([$username, $password, $name, $email, $phone]);
                            $supervisor_id = $conn->lastInsertId();

                            // Handle multiple branch assignments if selected
                            if ($assign_multiple_branches && !empty($additional_branches)) {
                                // Create supervisor_branches table if it doesn't exist
                                try {
                                    $conn->exec("CREATE TABLE IF NOT EXISTS supervisor_branches (
                                        id INT AUTO_INCREMENT PRIMARY KEY,
                                        supervisor_id INT NOT NULL,
                                        branch_id INT NOT NULL,
                                        assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                                        FOREIGN KEY (supervisor_id) REFERENCES users(id) ON DELETE CASCADE,
                                        FOREIGN KEY (branch_id) REFERENCES branches(id) ON DELETE CASCADE,
                                        UNIQUE KEY unique_supervisor_branch (supervisor_id, branch_id)
                                    )");
                                } catch (PDOException $e) {
                                    // Table might already exist, continue
                                }

                                // Insert additional branch assignments
                                foreach ($additional_branches as $additional_branch_id) {
                                    if ($additional_branch_id != $branch_id) { // Don't duplicate primary branch
                                        try {
                                            $stmt = $conn->prepare("INSERT INTO supervisor_branches (supervisor_id, branch_id) VALUES (?, ?)");
                                            $stmt->execute([$supervisor_id, $additional_branch_id]);
                                        } catch (PDOException $e) {
                                            // Might be duplicate, continue
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }

            $success_message = "Supervisor registered successfully!";
        } catch (Exception $e) {
            $error_message = "Registration failed: " . $e->getMessage();
        }
    }
}

// Get supervisors with their branch assignments
$conn = getDBConnection();

// Check if supervisor_branches table exists
$tableExists = false;
try {
    $result = $conn->query("SHOW TABLES LIKE 'supervisor_branches'");
    $tableExists = $result->rowCount() > 0;
} catch (PDOException $e) {
    $tableExists = false;
}

if ($tableExists) {
    // Use complex query with multiple branches if table exists
    $stmt = $conn->query("
        SELECT u.*,
               b.name as branch_name,
               GROUP_CONCAT(DISTINCT sb.branch_id) as additional_branch_ids,
               GROUP_CONCAT(DISTINCT b2.name) as additional_branch_names
        FROM users u
        LEFT JOIN branches b ON u.branch_id = b.id
        LEFT JOIN supervisor_branches sb ON u.id = sb.supervisor_id
        LEFT JOIN branches b2 ON sb.branch_id = b2.id AND sb.branch_id != u.branch_id
        WHERE u.role = 'supervisor'
        GROUP BY u.id
        ORDER BY u.name
    ");
} else {
    // Use simple query if table doesn't exist yet
    $stmt = $conn->query("SELECT u.*, b.name as branch_name FROM users u LEFT JOIN branches b ON u.branch_id = b.id WHERE u.role = 'supervisor' ORDER BY u.name");
}

$supervisors = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=5.0, user-scalable=yes">
    <link rel="apple-touch-icon" sizes="76x76" href="./assets/img/apple-icon.png">
    <link rel="icon" type="image/png" href="./assets/img/favicon.png">
    <title>Supervisor Registration - Shreeji Link Employee Transportation System</title>
    <link rel="stylesheet" type="text/css" href="https://fonts.googleapis.com/css?family=Inter:300,400,500,600,700,900" />
    <link href="./assets/nucleo-icons.css" rel="stylesheet" />
    <link href="./assets/nucleo-svg.css" rel="stylesheet" />
    <script src="https://kit.fontawesome.com/42d5adcbca.js" crossorigin="anonymous"></script>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded:opsz,wght,FILL,GRAD@24,400,0,0" />
    <link id="pagestyle" href="./assets/material-dashboard.css?v=3.2.0" rel="stylesheet" />
    <style>
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

            .col-lg-6 {
                flex: 0 0 100%;
                max-width: 100%;
                margin-bottom: 1rem;
            }

            .card.h-100 {
                height: auto !important;
            }

            .border.rounded {
                padding: 0.75rem !important;
            }

            .form-group {
                margin-bottom: 0.75rem;
            }

            .form-control-label {
                font-size: 0.8rem !important;
                margin-bottom: 0.25rem;
            }

            .form-control {
                padding: 0.5rem 0.75rem;
                font-size: 0.875rem;
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

            .card-body {
                padding: 1rem;
            }

            .modal-dialog {
                margin: 0.5rem;
                max-width: calc(100vw - 1rem);
            }

            .modal-body {
                padding: 1rem;
            }

            .table {
                font-size: 0.8rem;
            }

            .table th,
            .table td {
                padding: 0.25rem;
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

            .col-lg-6 {
                flex: 0 0 50%;
                max-width: 50%;
            }

            .table-responsive {
                font-size: 0.9rem;
            }
        }
    </style>
</head>

<body class="g-sidenav-show bg-gray-100">
    <aside class="sidenav navbar navbar-vertical navbar-expand-xs border-radius-lg fixed-start ms-2 bg-white my-2" id="sidenav-main">
        <div class="sidenav-header">
            <i class="fas fa-times p-3 cursor-pointer text-dark opacity-5 position-absolute end-0 top-0 d-none d-xl-none" aria-hidden="true" id="iconSidenav"></i>
            <a class="navbar-brand px-4 py-3 m-0" href="#">

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
                    <a class="nav-link text-dark" href="supervisors.php">
                        <i class="material-symbols-rounded opacity-5">supervisor_account</i>
                        <span class="nav-link-text ms-1">Supervisors</span>
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
                    <a class="nav-link text-dark" href="attendance.php">
                        <i class="material-symbols-rounded opacity-5">event_available</i>
                        <span class="nav-link-text ms-1">Driver Attendance</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link text-dark" href="supervisor_attendance.php">
                        <i class="material-symbols-rounded opacity-5">event_note</i>
                        <span class="nav-link-text ms-1">Supervisor Attendance</span>
                    </a>
                </li>
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
                    <h6 class="font-weight-bolder mb-0 text-primary">Supervisor Management</h6>
                    <p class="text-muted mb-0">Welcome, <strong><?php echo htmlspecialchars($user['name']); ?></strong></p>
                </nav>
            </div>
        </nav>

        <div class="container-fluid py-2">

            <?php if (isset($success_message)): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fas fa-check-circle"></i> <?php echo $success_message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if (isset($error_message)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-triangle"></i> <?php echo $error_message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <div class="row mt-4">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header pb-0 p-3">
                            <h6>Register New Supervisor</h6>
                            <p class="text-sm mb-0">Complete supervisor registration with all required documents and information.</p>
                        </div>
                        <div class="card-body p-3">
                            <form method="POST" action="" enctype="multipart/form-data">
                                <div class="row">
                                    <!-- Supervisor Information Section -->
                                    <div class="col-lg-6">
                                        <div class="card h-100">
                                            <div class="card-header pb-0 p-3">
                                                <h6 class="mb-0">Supervisor Information</h6>
                                            </div>
                                            <div class="card-body p-3">
                                                <!-- Account Information Box -->
                                                <div class="border rounded p-3 mb-3 bg-light">
                                                    <h6 class="text-primary mb-3"><i class="fas fa-user-circle"></i> Account Information</h6>
                                                    <div class="form-group">
                                                        <label for="username" class="form-control-label">Username *</label>
                                                        <div class="border rounded p-2 bg-white">
                                                            <input class="form-control border-0 bg-transparent" type="text" id="username" name="username" required>
                                                        </div>
                                                    </div>
                                                    <div class="form-group">
                                                        <label for="password" class="form-control-label">Password *</label>
                                                        <div class="border rounded p-2 bg-white">
                                                            <input class="form-control border-0 bg-transparent" type="password" id="password" name="password" required>
                                                        </div>
                                                    </div>
                                                </div>

                                                <!-- Personal Information Box -->
                                                <div class="border rounded p-3 mb-3 bg-light">
                                                    <h6 class="text-primary mb-3"><i class="fas fa-id-card"></i> Personal Information</h6>
                                                    <div class="form-group">
                                                        <label for="name" class="form-control-label">Full Name *</label>
                                                        <div class="border rounded p-2 bg-white">
                                                            <input class="form-control border-0 bg-transparent" type="text" id="name" name="name" required>
                                                        </div>
                                                    </div>
                                                    <div class="form-group">
                                                        <label for="email" class="form-control-label">Email *</label>
                                                        <div class="border rounded p-2 bg-white">
                                                            <input class="form-control border-0 bg-transparent" type="email" id="email" name="email" required>
                                                        </div>
                                                    </div>
                                                    <div class="form-group">
                                                        <label for="phone" class="form-control-label">Phone *</label>
                                                        <div class="border rounded p-2 bg-white">
                                                            <input class="form-control border-0 bg-transparent" type="tel" id="phone" name="phone" required>
                                                        </div>
                                                    </div>
                                                    <div class="form-group">
                                                        <label for="address" class="form-control-label">Address</label>
                                                        <div class="border rounded p-2 bg-white">
                                                            <textarea class="form-control border-0 bg-transparent" id="address" name="address" rows="3"></textarea>
                                                        </div>
                                                    </div>
                                                </div>

                                                <!-- Branch Assignment Box -->
                                                <div class="border rounded p-3 mb-3 bg-light">
                                                    <h6 class="text-primary mb-3"><i class="fas fa-building"></i> Branch Assignment</h6>
                                                    <div class="form-group">
                                                        <label for="branch_id" class="form-control-label">Branch *</label>
                                                        <div class="border rounded p-2 bg-white">
                                                            <select class="form-control border-0 bg-transparent" id="branch_id" name="branch_id" required>
                                                                <option value="">Select Branch</option>
                                                                <?php
                                                                $conn = getDBConnection();
                                                                try {
                                                                    $stmt = $conn->query("SELECT id, name FROM branches WHERE status = 'active' ORDER BY name");
                                                                    $branches = $stmt->fetchAll(PDO::FETCH_ASSOC);
                                                                    foreach ($branches as $branch): ?>
                                                                        <option value="<?php echo $branch['id']; ?>"><?php echo htmlspecialchars($branch['name']); ?></option>
                                                                <?php endforeach;
                                                                } catch (PDOException $e) {
                                                                    echo '<option value="" disabled>Run migration to enable branches</option>';
                                                                }
                                                                ?>
                                                            </select>
                                                        </div>
                                                        <small class="form-text text-muted">Select the branch where this supervisor will be assigned</small>
                                                    </div>
                                                    <div class="form-group mt-3">
                                                        <label class="form-control-label">Assign to Multiple Branches</label>
                                                        <div class="border rounded p-2 bg-white">
                                                            <div class="form-check">
                                                                <input class="form-check-input" type="checkbox" id="assign_multiple_branches" name="assign_multiple_branches">
                                                                <label class="form-check-label" for="assign_multiple_branches">
                                                                    Allow this supervisor to manage multiple branches
                                                                </label>
                                                            </div>
                                                        </div>
                                                        <small class="form-text text-muted">Check this option to assign the supervisor to multiple branches</small>
                                                    </div>
                                                    <div class="form-group mt-3" id="multiple_branches_section" style="display: none;">
                                                        <label for="additional_branches" class="form-control-label">Additional Branches</label>
                                                        <div class="border rounded p-2 bg-white">
                                                            <div id="branch_checkboxes">
                                                                <?php
                                                                if (isset($branches) && is_array($branches)) {
                                                                    foreach ($branches as $branch): ?>
                                                                        <div class="form-check">
                                                                            <input class="form-check-input" type="checkbox" id="branch_<?php echo $branch['id']; ?>" name="additional_branches[]" value="<?php echo $branch['id']; ?>">
                                                                            <label class="form-check-label" for="branch_<?php echo $branch['id']; ?>">
                                                                                <?php echo htmlspecialchars($branch['name']); ?>
                                                                            </label>
                                                                        </div>
                                                                <?php endforeach;
                                                                }
                                                                ?>
                                                            </div>
                                                        </div>
                                                        <small class="form-text text-muted">Check the boxes for branches this supervisor should manage</small>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Documents & Bank Details Section -->
                                    <div class="col-lg-6">
                                        <div class="card h-100">
                                            <div class="card-header pb-0 p-3">
                                                <h6 class="mb-0">Documents & Bank Details</h6>
                                            </div>
                                            <div class="card-body p-3">
                                                <!-- Supervisor Documents Box -->
                                                <div class="border rounded p-3 mb-3 bg-light">
                                                    <h6 class="text-primary mb-3"><i class="fas fa-file-image"></i> Supervisor Documents</h6>
                                                    <div class="form-group">
                                                        <label for="supervisor_photo" class="form-control-label">Supervisor Photograph *</label>
                                                        <div class="border rounded p-2 bg-white">
                                                            <input class="form-control border-0 bg-transparent" type="file" id="supervisor_photo" name="supervisor_photo" accept="image/*" required>
                                                        </div>
                                                        <small class="form-text text-muted">Upload a clear photograph of the supervisor</small>
                                                    </div>
                                                    <div class="form-group">
                                                        <label for="kyc_documents" class="form-control-label">KYC Documents *</label>
                                                        <div class="border rounded p-2 bg-white">
                                                            <input class="form-control border-0 bg-transparent" type="file" id="kyc_documents" name="kyc_documents[]" accept="image/*,application/pdf" multiple required>
                                                        </div>
                                                        <small class="form-text text-muted">Upload Aadhaar, PAN, or other KYC documents (multiple files allowed)</small>
                                                    </div>
                                                </div>
</body>

</html>

<!-- Bank Details Box -->
<div class="border rounded p-3 mb-3 bg-light">
    <h6 class="text-primary mb-3"><i class="fas fa-university"></i> Bank Details</h6>
    <div class="form-group">
        <label for="bank_name" class="form-control-label">Bank Name *</label>
        <div class="border rounded p-2 bg-white">
            <input class="form-control border-0 bg-transparent" type="text" id="bank_name" name="bank_name" required>
        </div>
    </div>
    <div class="form-group">
        <label for="bank_account_number" class="form-control-label">Account Number *</label>
        <div class="border rounded p-2 bg-white">
            <input class="form-control border-0 bg-transparent" type="text" id="bank_account_number" name="bank_account_number" required>
        </div>
    </div>
    <div class="form-group">
        <label for="bank_ifsc_code" class="form-control-label">IFSC Code *</label>
        <div class="border rounded p-2 bg-white">
            <input class="form-control border-0 bg-transparent" type="text" id="bank_ifsc_code" name="bank_ifsc_code" required>
        </div>
    </div>
    <div class="form-group">
        <label for="bank_branch" class="form-control-label">Bank Branch *</label>
        <div class="border rounded p-2 bg-white">
            <input class="form-control border-0 bg-transparent" type="text" id="bank_branch" name="bank_branch" required>
        </div>
    </div>
</div>
</div>
</div>
</div>
</div>
<div class="row mt-3">
    <div class="col-12">
        <button type="submit" name="add_supervisor" class="btn bg-gradient-dark">
            <i class="fas fa-save"></i> Register Supervisor
        </button>
    </div>
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
                <h6>Registered Supervisors</h6>
            </div>
            <div class="card-body px-0 pb-2">
                <div class="table-responsive">
                    <table class="table align-items-center mb-0">
                        <thead>
                            <tr>
                                <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">ID</th>
                                <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">Name</th>
                                <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">Email</th>
                                <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">Phone</th>
                                <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">Branch</th>
                                <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">Status</th>
                                <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($supervisors as $supervisor): ?>
                                <tr>
                                    <td>
                                        <p class="text-xs font-weight-bold mb-0"><?php echo $supervisor['id']; ?></p>
                                    </td>
                                    <td>
                                        <div class="d-flex px-2 py-1">
                                            <div>
                                                <i class="material-symbols-rounded text-secondary">supervisor_account</i>
                                            </div>
                                            <div class="d-flex flex-column justify-content-center">
                                                <h6 class="mb-0 text-sm"><?php echo htmlspecialchars($supervisor['name']); ?></h6>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <p class="text-xs font-weight-bold mb-0"><?php echo htmlspecialchars($supervisor['email']); ?></p>
                                    </td>
                                    <td>
                                        <p class="text-xs font-weight-bold mb-0"><?php echo htmlspecialchars($supervisor['phone']); ?></p>
                                    </td>
                                    <td>
                                        <div class="d-flex flex-column">
                                            <p class="text-xs font-weight-bold mb-1"><?php echo htmlspecialchars($supervisor['branch_name'] ?? 'N/A'); ?></p>
                                            <?php if (isset($supervisor['additional_branch_names']) && !empty($supervisor['additional_branch_names'])): ?>
                                                <small class="text-muted">
                                                    <i class="fas fa-plus-circle text-primary"></i>
                                                    <?php
                                                    $additional_branches = explode(',', $supervisor['additional_branch_names']);
                                                    echo htmlspecialchars(implode(', ', array_slice($additional_branches, 0, 2)));
                                                    if (count($additional_branches) > 2) {
                                                        echo ' +' . (count($additional_branches) - 2) . ' more';
                                                    }
                                                    ?>
                                                </small>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge badge-sm bg-gradient-success">Active</span>
                                    </td>
                                    <td class="align-middle">
                                        <a href="supervisor_details.php?id=<?php echo $supervisor['id']; ?>" class="text-secondary font-weight-bold text-xs me-2" data-toggle="tooltip" data-original-title="View Details">
                                            <i class="material-symbols-rounded">visibility</i> View
                                        </a>
                                        <a href="supervisor_details.php?id=<?php echo $supervisor['id']; ?>&edit=1" class="text-primary font-weight-bold text-xs" data-toggle="tooltip" data-original-title="Edit Details">
                                            <i class="material-symbols-rounded">edit</i> Edit
                                        </a>
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
</div>
</main>

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
    var win = navigator.platform.indexOf('Win') > -1;
    if (win && document.querySelector('#sidenav-scrollbar')) {
        var options = {
            damping: '0.5'
        }
        Scrollbar.init(document.querySelector('#sidenav-scrollbar'), options);
    }

    // Handle multiple branches checkbox toggle
    document.getElementById('assign_multiple_branches').addEventListener('change', function() {
        const multipleBranchesSection = document.getElementById('multiple_branches_section');
        if (this.checked) {
            multipleBranchesSection.style.display = 'block';
        } else {
            multipleBranchesSection.style.display = 'none';
            // Clear checkbox selections when unchecked
            const checkboxes = document.querySelectorAll('#branch_checkboxes input[type="checkbox"]');
            checkboxes.forEach(checkbox => {
                checkbox.checked = false;
            });
        }
    });

    // Initialize on page load
    document.addEventListener('DOMContentLoaded', function() {
        const checkbox = document.getElementById('assign_multiple_branches');
        const multipleBranchesSection = document.getElementById('multiple_branches_section');

        if (checkbox && multipleBranchesSection) {
            if (checkbox.checked) {
                multipleBranchesSection.style.display = 'block';
            } else {
                multipleBranchesSection.style.display = 'none';
            }
        }
    });
</script>
<script src="./assets/js/material-dashboard.min.js?v=3.2.0"></script>
</body>

</html>
