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

// Get supervisor ID from URL
$supervisor_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$edit_mode = isset($_GET['edit']) && $_GET['edit'] == '1';

if (!$supervisor_id) {
    header('Location: supervisors.php');
    exit;
}

// Get supervisor details
$conn = getDBConnection();
$stmt = $conn->prepare("SELECT u.*, b.name as branch_name FROM users u LEFT JOIN branches b ON u.branch_id = b.id WHERE u.id = ? AND u.role = 'supervisor'");
$stmt->execute([$supervisor_id]);
$supervisor = $stmt->fetch(PDO::FETCH_ASSOC);

// Initialize missing fields to prevent undefined array key warnings
$supervisor = array_merge([
    'address' => '',
    'bank_name' => '',
    'bank_account_number' => '',
    'bank_ifsc_code' => '',
    'bank_branch' => ''
], $supervisor ?? []);

if (!$supervisor) {
    header('Location: supervisors.php');
    exit;
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['update_supervisor'])) {
        $name = trim($_POST['name']);
        $email = trim($_POST['email']);
        $phone = isset($_POST['phone']) && !empty(trim($_POST['phone'])) ? trim($_POST['phone']) : '';
        $address = isset($_POST['address']) && !empty(trim($_POST['address'])) ? trim($_POST['address']) : '';
        $branch_id = isset($_POST['branch_id']) && !empty($_POST['branch_id']) ? $_POST['branch_id'] : '';
        $assign_multiple_branches = isset($_POST['assign_multiple_branches']) ? true : false;
        $additional_branches = isset($_POST['additional_branches']) && is_array($_POST['additional_branches']) ? $_POST['additional_branches'] : [];

        // Bank details - handle empty values properly
        $bank_name = isset($_POST['bank_name']) && !empty(trim($_POST['bank_name'])) ? trim($_POST['bank_name']) : '';
        $bank_account_number = isset($_POST['bank_account_number']) && !empty(trim($_POST['bank_account_number'])) ? trim($_POST['bank_account_number']) : '';
        $bank_ifsc_code = isset($_POST['bank_ifsc_code']) && !empty(trim($_POST['bank_ifsc_code'])) ? trim($_POST['bank_ifsc_code']) : '';
        $bank_branch = isset($_POST['bank_branch']) && !empty(trim($_POST['bank_branch'])) ? trim($_POST['bank_branch']) : '';

        // Handle file uploads
        $upload_dir = 'uploads/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }

        $supervisor_photo_path = isset($supervisor['driver_photo']) ? $supervisor['driver_photo'] : ''; // Keep existing photo
        $kyc_paths = isset($supervisor['kyc_documents']) && $supervisor['kyc_documents'] ? json_decode($supervisor['kyc_documents'], true) : [];

        // Upload new supervisor photo if provided
        if (isset($_FILES['supervisor_photo']) && $_FILES['supervisor_photo']['error'] == 0) {
            $supervisor_photo_path = $upload_dir . 'supervisor_' . time() . '_' . basename($_FILES['supervisor_photo']['name']);
            move_uploaded_file($_FILES['supervisor_photo']['tmp_name'], $supervisor_photo_path);
        }

        // Upload new KYC documents if provided
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
        $conn->beginTransaction();

        try {
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

                // First, remove existing additional branch assignments for this supervisor
                try {
                    $stmt = $conn->prepare("DELETE FROM supervisor_branches WHERE supervisor_id = ? AND branch_id != ?");
                    $stmt->execute([$supervisor_id, $branch_id]);
                } catch (PDOException $e) {
                    // Table might not exist yet, continue
                }

                // Insert new additional branch assignments
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
            } else {
                // If multiple branches is not selected, remove all additional assignments
                try {
                    $stmt = $conn->prepare("DELETE FROM supervisor_branches WHERE supervisor_id = ?");
                    $stmt->execute([$supervisor_id]);
                } catch (PDOException $e) {
                    // Table might not exist, continue
                }
            }

            // Update supervisor - try with all columns first, fallback progressively
            try {
                $stmt = $conn->prepare("UPDATE users SET name = ?, email = ?, phone = ?, branch_id = ?, address = ?, bank_name = ?, bank_account_number = ?, bank_ifsc_code = ?, bank_branch = ?, driver_photo = ?, kyc_documents = ? WHERE id = ?");
                $stmt->execute([$name, $email, $phone, $branch_id, $address, $bank_name, $bank_account_number, $bank_ifsc_code, $bank_branch, $supervisor_photo_path, json_encode($kyc_paths), $supervisor_id]);
            } catch (PDOException $e) {
                // If bank columns don't exist, try without bank details
                try {
                    $stmt = $conn->prepare("UPDATE users SET name = ?, email = ?, phone = ?, branch_id = ?, address = ?, driver_photo = ?, kyc_documents = ? WHERE id = ?");
                    $stmt->execute([$name, $email, $phone, $branch_id, $address, $supervisor_photo_path, json_encode($kyc_paths), $supervisor_id]);
                } catch (PDOException $e2) {
                    // If photo columns don't exist, try without them
                    try {
                        $stmt = $conn->prepare("UPDATE users SET name = ?, email = ?, phone = ?, branch_id = ?, address = ? WHERE id = ?");
                        $stmt->execute([$name, $email, $phone, $branch_id, $address, $supervisor_id]);
                    } catch (PDOException $e3) {
                        // If address column doesn't exist, try basic update
                        $stmt = $conn->prepare("UPDATE users SET name = ?, email = ?, phone = ?, branch_id = ? WHERE id = ?");
                        $stmt->execute([$name, $email, $phone, $branch_id, $supervisor_id]);
                    }
                }
            }

            $conn->commit();
            $success_message = "Supervisor updated successfully!";

            // Refresh supervisor data
            $stmt = $conn->prepare("SELECT u.*, b.name as branch_name FROM users u LEFT JOIN branches b ON u.branch_id = b.id WHERE u.id = ? AND u.role = 'supervisor'");
            $stmt->execute([$supervisor_id]);
            $supervisor = $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            $conn->rollBack();
            $error_message = "Update failed: " . $e->getMessage();
        }
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
    <title>Supervisor Details - MS Infosystems Employee Transportation System</title>
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
                    <a class="nav-link active bg-gradient-dark text-white" href="supervisors.php">
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
                    <h6 class="font-weight-bolder mb-0">MS Infosystems - Employee Transportation System</h6>
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
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6>Supervisor Information</h6>
                                    <p class="text-sm mb-0"><?php echo $edit_mode ? 'Edit supervisor details.' : 'View supervisor details.'; ?></p>
                                </div>
                                <div>
                                    <?php if (!$edit_mode): ?>
                                        <a href="supervisor_details.php?id=<?php echo $supervisor_id; ?>&edit=1" class="btn btn-primary btn-sm me-2">
                                            <i class="fas fa-edit"></i> Edit
                                        </a>
                                    <?php endif; ?>
                                    <a href="supervisors.php" class="btn btn-outline-primary btn-sm">
                                        <i class="fas fa-arrow-left"></i> Back to Supervisors
                                    </a>
                                </div>
                            </div>
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
                                                        <label for="username" class="form-control-label">Username</label>
                                                        <div class="border rounded p-2 bg-white">
                                                            <input class="form-control border-0 bg-transparent" type="text" id="username" name="username" value="<?php echo htmlspecialchars($supervisor['username']); ?>" readonly>
                                                        </div>
                                                    </div>
                                                </div>

                                                <?php if ($edit_mode): ?>
                                                    <!-- Personal Information Box -->
                                                    <div class="border rounded p-3 mb-3 bg-light">
                                                        <h6 class="text-primary mb-3"><i class="fas fa-id-card"></i> Personal Information</h6>
                                                        <div class="form-group">
                                                            <label for="name" class="form-control-label">Full Name *</label>
                                                            <div class="border rounded p-2 bg-white">
                                                                <input class="form-control border-0 bg-transparent" type="text" id="name" name="name" value="<?php echo htmlspecialchars($supervisor['name']); ?>" required>
                                                            </div>
                                                        </div>
                                                        <div class="form-group">
                                                            <label for="email" class="form-control-label">Email *</label>
                                                            <div class="border rounded p-2 bg-white">
                                                                <input class="form-control border-0 bg-transparent" type="email" id="email" name="email" value="<?php echo htmlspecialchars($supervisor['email']); ?>" required>
                                                            </div>
                                                        </div>
                                                        <div class="form-group">
                                                            <label for="phone" class="form-control-label">Phone</label>
                                                            <div class="border rounded p-2 bg-white">
                                                                <input class="form-control border-0 bg-transparent" type="tel" id="phone" name="phone" value="<?php echo htmlspecialchars($supervisor['phone'] ?? ''); ?>">
                                                            </div>
                                                        </div>
                                                        <div class="form-group">
                                                            <label for="address" class="form-control-label">Address</label>
                                                            <div class="border rounded p-2 bg-white">
                                                                <textarea class="form-control border-0 bg-transparent" id="address" name="address" rows="3"><?php echo htmlspecialchars($supervisor['address'] ?? ''); ?></textarea>
                                                            </div>
                                                        </div>
                                                    </div>

                                                    <!-- Branch Assignment Box -->
                                                    <div class="border rounded p-3 mb-3 bg-light">
                                                        <h6 class="text-primary mb-3"><i class="fas fa-building"></i> Branch Assignment</h6>
                                                        <div class="form-group">
                                                            <label for="branch_id" class="form-control-label">Primary Branch *</label>
                                                            <div class="border rounded p-2 bg-white">
                                                                <select class="form-control border-0 bg-transparent" id="branch_id" name="branch_id" required>
                                                                    <option value="">Select Primary Branch</option>
                                                                    <?php
                                                                    $conn = getDBConnection();
                                                                    try {
                                                                        $stmt = $conn->query("SELECT id, name FROM branches WHERE status = 'active' ORDER BY name");
                                                                        $branches = $stmt->fetchAll(PDO::FETCH_ASSOC);
                                                                        foreach ($branches as $branch): ?>
                                                                            <option value="<?php echo $branch['id']; ?>" <?php echo ($supervisor['branch_id'] == $branch['id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($branch['name']); ?></option>
                                                                    <?php endforeach;
                                                                    } catch (PDOException $e) {
                                                                        echo '<option value="" disabled>Run migration to enable branches</option>';
                                                                    }
                                                                    ?>
                                                                </select>
                                                            </div>
                                                            <small class="form-text text-muted">This will be the supervisor's primary branch</small>
                                                        </div>
                                                        <div class="form-group mt-3">
                                                            <label class="form-control-label">Assign to Multiple Branches</label>
                                                            <div class="border rounded p-2 bg-white">
                                                                <div class="form-check">
                                                                    <input class="form-check-input" type="checkbox" id="assign_multiple_branches" name="assign_multiple_branches" <?php echo (!empty($supervisor['additional_branch_ids'])) ? 'checked' : ''; ?>>
                                                                    <label class="form-check-label" for="assign_multiple_branches">
                                                                        Allow this supervisor to manage multiple branches
                                                                    </label>
                                                                </div>
                                                            </div>
                                                            <small class="form-text text-muted">Check this option to assign the supervisor to additional branches</small>
                                                        </div>
                                                        <div class="form-group mt-3" id="multiple_branches_section" style="display: <?php echo (!empty($supervisor['additional_branch_ids'])) ? 'block' : 'none'; ?>;">
                                                            <label for="additional_branches" class="form-control-label">Additional Branches</label>
                                                            <div class="border rounded p-2 bg-white">
                                                                <div id="branch_checkboxes">
                                                                    <?php
                                                                    if (isset($branches) && is_array($branches)) {
                                                                        $additional_branch_ids = isset($supervisor['additional_branch_ids']) ? explode(',', $supervisor['additional_branch_ids']) : [];
                                                                        foreach ($branches as $branch):
                                                                            $is_checked = in_array($branch['id'], $additional_branch_ids) && $branch['id'] != $supervisor['branch_id'];
                                                                    ?>
                                                                            <div class="form-check">
                                                                                <input class="form-check-input" type="checkbox" id="branch_<?php echo $branch['id']; ?>" name="additional_branches[]" value="<?php echo $branch['id']; ?>" <?php echo $is_checked ? 'checked' : ''; ?>>
                                                                                <label class="form-check-label" for="branch_<?php echo $branch['id']; ?>">
                                                                                    <?php echo htmlspecialchars($branch['name']); ?>
                                                                                </label>
                                                                            </div>
                                                                    <?php endforeach;
                                                                    }
                                                                    ?>
                                                                </div>
                                                            </div>
                                                            <small class="form-text text-muted">Check the boxes for additional branches this supervisor should manage</small>
                                                        </div>
                                                    </div>
                                                <?php else: ?>
                                                    <!-- View Mode - Display Information -->
                                                    <div class="border rounded p-3 mb-3 bg-light">
                                                        <h6 class="text-primary mb-3"><i class="fas fa-id-card"></i> Personal Information</h6>
                                                        <div class="row">
                                                            <div class="col-md-6">
                                                                <p><strong>Full Name:</strong> <?php echo htmlspecialchars($supervisor['name']); ?></p>
                                                                <p><strong>Email:</strong> <?php echo htmlspecialchars($supervisor['email']); ?></p>
                                                            </div>
                                                            <div class="col-md-6">
                                                                <p><strong>Phone:</strong> <?php echo htmlspecialchars($supervisor['phone'] ?: 'N/A'); ?></p>
                                                                <p><strong>Address:</strong> <?php echo htmlspecialchars($supervisor['address'] ?: 'N/A'); ?></p>
                                                            </div>
                                                        </div>
                                                    </div>

                                                    <div class="border rounded p-3 mb-3 bg-light">
                                                        <h6 class="text-primary mb-3"><i class="fas fa-building"></i> Branch Assignment</h6>
                                                        <p><strong>Primary Branch:</strong> <?php echo htmlspecialchars($supervisor['branch_name'] ?: 'N/A'); ?></p>
                                                        <?php if (isset($supervisor['additional_branch_names']) && !empty($supervisor['additional_branch_names'])): ?>
                                                            <p><strong>Additional Branches:</strong>
                                                                <?php
                                                                $additional_branches = explode(',', $supervisor['additional_branch_names']);
                                                                echo htmlspecialchars(implode(', ', $additional_branches));
                                                                ?>
                                                            </p>
                                                        <?php endif; ?>
                                                    </div>
                                                <?php endif; ?>
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
                                                <?php if ($edit_mode): ?>
                                                    <!-- Supervisor Documents Box -->
                                                    <div class="border rounded p-3 mb-3 bg-light">
                                                        <h6 class="text-primary mb-3"><i class="fas fa-file-image"></i> Supervisor Documents</h6>
                                                        <div class="form-group">
                                                            <label for="supervisor_photo" class="form-control-label">Supervisor Photograph</label>
                                                            <div class="border rounded p-2 bg-white">
                                                                <input class="form-control border-0 bg-transparent" type="file" id="supervisor_photo" name="supervisor_photo" accept="image/*">
                                                            </div>
                                                            <?php if (isset($supervisor['driver_photo']) && $supervisor['driver_photo']): ?>
                                                                <small class="form-text text-muted">Current photo: <a href="<?php echo htmlspecialchars($supervisor['driver_photo']); ?>" target="_blank">View</a></small>
                                                            <?php endif; ?>
                                                        </div>
                                                        <div class="form-group">
                                                            <label for="kyc_documents" class="form-control-label">KYC Documents</label>
                                                            <div class="border rounded p-2 bg-white">
                                                                <input class="form-control border-0 bg-transparent" type="file" id="kyc_documents" name="kyc_documents[]" accept="image/*,application/pdf" multiple>
                                                            </div>
                                                            <?php if (isset($supervisor['kyc_documents']) && $supervisor['kyc_documents']): ?>
                                                                <small class="form-text text-muted">Current documents: <?php echo count(json_decode($supervisor['kyc_documents'], true)); ?> files uploaded</small>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                <?php else: ?>
                                                    <!-- View Mode - Display Documents -->
                                                    <div class="border rounded p-3 mb-3 bg-light">
                                                        <h6 class="text-primary mb-3"><i class="fas fa-file-image"></i> Supervisor Documents</h6>
                                                        <div class="row">
                                                            <div class="col-md-6">
                                                                <p><strong>Photograph:</strong>
                                                                    <?php if (isset($supervisor['driver_photo']) && $supervisor['driver_photo']): ?>
                                                                        <a href="<?php echo htmlspecialchars($supervisor['driver_photo']); ?>" target="_blank">View Photo</a>
                                                                    <?php else: ?>
                                                                        Not uploaded
                                                                    <?php endif; ?>
                                                                </p>
                                                            </div>
                                                            <div class="col-md-6">
                                                                <p><strong>KYC Documents:</strong>
                                                                    <?php if (isset($supervisor['kyc_documents']) && $supervisor['kyc_documents']): ?>
                                                                        <?php echo count(json_decode($supervisor['kyc_documents'], true)); ?> files uploaded
                                                                    <?php else: ?>
                                                                        Not uploaded
                                                                    <?php endif; ?>
                                                                </p>
                                                            </div>
                                                        </div>
                                                    </div>
                                                <?php endif; ?>

                                                <?php if ($edit_mode): ?>
                                                    <!-- Bank Details Box -->
                                                    <div class="border rounded p-3 mb-3 bg-light">
                                                        <h6 class="text-primary mb-3"><i class="fas fa-university"></i> Bank Details</h6>
                                                        <div class="form-group">
                                                            <label for="bank_name" class="form-control-label">Bank Name</label>
                                                            <div class="border rounded p-2 bg-white">
                                                                <input class="form-control border-0 bg-transparent" type="text" id="bank_name" name="bank_name" value="<?php echo htmlspecialchars($supervisor['bank_name'] ?? ''); ?>">
                                                            </div>
                                                        </div>
                                                        <div class="form-group">
                                                            <label for="bank_account_number" class="form-control-label">Account Number</label>
                                                            <div class="border rounded p-2 bg-white">
                                                                <input class="form-control border-0 bg-transparent" type="text" id="bank_account_number" name="bank_account_number" value="<?php echo htmlspecialchars($supervisor['bank_account_number'] ?? ''); ?>">
                                                            </div>
                                                        </div>
                                                        <div class="form-group">
                                                            <label for="bank_ifsc_code" class="form-control-label">IFSC Code</label>
                                                            <div class="border rounded p-2 bg-white">
                                                                <input class="form-control border-0 bg-transparent" type="text" id="bank_ifsc_code" name="bank_ifsc_code" value="<?php echo htmlspecialchars($supervisor['bank_ifsc_code'] ?? ''); ?>">
                                                            </div>
                                                        </div>
                                                        <div class="form-group">
                                                            <label for="bank_branch" class="form-control-label">Bank Branch</label>
                                                            <div class="border rounded p-2 bg-white">
                                                                <input class="form-control border-0 bg-transparent" type="text" id="bank_branch" name="bank_branch" value="<?php echo htmlspecialchars($supervisor['bank_branch'] ?? ''); ?>">
                                                            </div>
                                                        </div>
                                                    </div>
                                                <?php else: ?>
                                                    <!-- View Mode - Display Bank Details -->
                                                    <div class="border rounded p-3 mb-3 bg-light">
                                                        <h6 class="text-primary mb-3"><i class="fas fa-university"></i> Bank Details</h6>
                                                        <div class="row">
                                                            <div class="col-md-6">
                                                                <p><strong>Bank Name:</strong> <?php echo htmlspecialchars($supervisor['bank_name'] ?: 'N/A'); ?></p>
                                                                <p><strong>Account Number:</strong> <?php echo htmlspecialchars($supervisor['bank_account_number'] ?: 'N/A'); ?></p>
                                                            </div>
                                                            <div class="col-md-6">
                                                                <p><strong>IFSC Code:</strong> <?php echo htmlspecialchars($supervisor['bank_ifsc_code'] ?: 'N/A'); ?></p>
                                                                <p><strong>Bank Branch:</strong> <?php echo htmlspecialchars($supervisor['bank_branch'] ?: 'N/A'); ?></p>
                                                            </div>
                                                        </div>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <?php if ($edit_mode): ?>
                                    <div class="row mt-3">
                                        <div class="col-12">
                                            <button type="submit" name="update_supervisor" class="btn bg-gradient-dark">
                                                <i class="fas fa-save"></i> Update Supervisor
                                            </button>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </form>
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
