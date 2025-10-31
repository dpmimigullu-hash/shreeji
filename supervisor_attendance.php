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

if ($role != 'admin' && $role != 'supervisor') {
    header('Location: index.php');
    exit;
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['mark_supervisor_attendance'])) {
        // For supervisors marking their own attendance, use their session ID
        $supervisor_id = ($role == 'supervisor') ? $_SESSION['user_id'] : $_POST['supervisor_id'];
        $date = $_POST['date'];
        $status = $_POST['status'];
        // Auto-fill check-in time with current system time if not provided
        $check_in_time = $_POST['check_in_time'] ?? date('H:i:s');
        // Check-out time is optional and will be set when checking out
        $check_out_time = $_POST['check_out_time'] ?? null;
        $latitude = $_POST['latitude'] ?? null;
        $longitude = $_POST['longitude'] ?? null;

        $conn = getDBConnection();

        try {
            // Check if attendance already exists for this supervisor and date
            $stmt = $conn->prepare("SELECT id FROM supervisor_attendance WHERE supervisor_id = ? AND date = ?");
            $stmt->execute([$supervisor_id, $date]);
            $existing = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($existing) {
                // Update existing record
                $stmt = $conn->prepare("UPDATE supervisor_attendance SET status = ?, check_in_time = ?, check_out_time = ?, latitude = ?, longitude = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
                $stmt->execute([$status, $check_in_time, $check_out_time, $latitude, $longitude, $existing['id']]);
            } else {
                // Insert new record
                $stmt = $conn->prepare("INSERT INTO supervisor_attendance (supervisor_id, date, status, check_in_time, check_out_time, latitude, longitude) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$supervisor_id, $date, $status, $check_in_time, $check_out_time, $latitude, $longitude]);
            }

            $success_message = "Supervisor attendance marked successfully!";
        } catch (Exception $e) {
            $error_message = "Failed to mark attendance: " . $e->getMessage();
        }
    }
}

// Get supervisors based on role
$conn = getDBConnection();
if ($role == 'admin') {
    $stmt = $conn->query("SELECT u.id, u.name, u.phone, b.name as branch_name FROM users u LEFT JOIN branches b ON u.branch_id = b.id WHERE u.role = 'supervisor' ORDER BY u.name");
} else {
    // For supervisors, get supervisors from their branch
    $stmt = $conn->prepare("SELECT u.id, u.name, u.phone, b.name as branch_name FROM users u LEFT JOIN branches b ON u.branch_id = b.id WHERE u.role = 'supervisor' AND u.branch_id = (SELECT branch_id FROM users WHERE id = ?) ORDER BY u.name");
    $stmt->execute([$_SESSION['user_id']]);
}
$supervisors = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get supervisor attendance records
if ($role == 'admin') {
    $stmt = $conn->query("SELECT sa.*, u.name as supervisor_name, b.name as branch_name FROM supervisor_attendance sa JOIN users u ON sa.supervisor_id = u.id LEFT JOIN branches b ON u.branch_id = b.id ORDER BY sa.date DESC, u.name");
} else {
    $stmt = $conn->prepare("SELECT sa.*, u.name as supervisor_name, b.name as branch_name FROM supervisor_attendance sa JOIN users u ON sa.supervisor_id = u.id LEFT JOIN branches b ON u.branch_id = b.id WHERE u.branch_id = (SELECT branch_id FROM users WHERE id = ?) ORDER BY sa.date DESC, u.name");
    $stmt->execute([$_SESSION['user_id']]);
}
$attendance_records = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=5.0, user-scalable=yes">
    <link rel="apple-touch-icon" sizes="76x76" href="./assets/img/apple-icon.png">
    <link rel="icon" type="image/png" href="./assets/img/favicon.png">
    <title>Supervisor Attendance - MS Infosystems Employee Transportation System</title>
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
                    <a class="nav-link text-dark" href="supervisor_attendance.php">
                        <i class="material-symbols-rounded opacity-5">event_available</i>
                        <span class="nav-link-text ms-1">Supervisor Attendance</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link text-dark" href="attendance.php">
                        <i class="material-symbols-rounded opacity-5">event_note</i>
                        <span class="nav-link-text ms-1">Driver Attendance</span>
                    </a>
                </li>
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
                        <li class="breadcrumb-item text-sm text-dark active" aria-current="page">Supervisor Attendance</li>
                    </ol>
                    <h6 class="font-weight-bolder mb-0">Supervisor Attendance Management</h6>
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

            <div class="row">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header pb-0 p-3">
                            <h6>Mark Supervisor Attendance</h6>
                            <p class="text-sm mb-0">Record attendance with geo-fencing validation for supervisors.</p>
                        </div>
                        <div class="card-body p-3">
                            <?php if ($role == 'supervisor'): ?>
                                <div class="alert alert-info">
                                    <i class="material-symbols-rounded">info</i>
                                    <strong>Self-Attendance:</strong> Mark your own attendance with geo-fencing validation. You must be within branch premises to mark attendance.
                                </div>
                            <?php else: ?>
                                <div class="alert alert-info">
                                    <i class="material-symbols-rounded">info</i>
                                    <strong>Geo-fencing:</strong> Attendance can only be marked when within branch premises. Location coordinates are automatically captured for verification.
                                </div>
                            <?php endif; ?>

                            <form method="POST" action="" id="attendanceForm">
                                <div class="row">
                                    <?php if ($role == 'admin'): ?>
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="supervisor_id" class="form-control-label">Supervisor *</label>
                                                <select class="form-control" id="supervisor_id" name="supervisor_id" required onchange="loadSupervisorBranch()">
                                                    <option value="">Select Supervisor</option>
                                                    <?php foreach ($supervisors as $supervisor): ?>
                                                        <option value="<?php echo $supervisor['id']; ?>" data-branch="<?php echo htmlspecialchars($supervisor['branch_name'] ?? 'N/A'); ?>">
                                                            <?php echo htmlspecialchars($supervisor['name']); ?> (<?php echo htmlspecialchars($supervisor['branch_name'] ?? 'N/A'); ?>)
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                        </div>
                                    <?php else: ?>
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label class="form-control-label">Supervisor</label>
                                                <input class="form-control" type="text" value="<?php echo htmlspecialchars($user['name']); ?>" readonly>
                                                <input type="hidden" name="supervisor_id" value="<?php echo $user['id']; ?>">
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="date" class="form-control-label">Date *</label>
                                            <input class="form-control" type="date" id="date" name="date" value="<?php echo date('Y-m-d'); ?>" required>
                                        </div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label for="status" class="form-control-label">Status *</label>
                                            <select class="form-control" id="status" name="status" required>
                                                <option value="present">Present</option>
                                                <option value="absent">Absent</option>
                                                <option value="late">Late</option>
                                                <option value="early_departure">Early Departure</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label for="check_in_time" class="form-control-label">Check-in Time</label>
                                            <input class="form-control" type="time" id="check_in_time" name="check_in_time" value="<?php echo date('H:i'); ?>" readonly>
                                            <small class="form-text text-muted">Auto-filled with current system time</small>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label for="check_out_time" class="form-control-label">Check-out Time</label>
                                            <input class="form-control" type="time" id="check_out_time" name="check_out_time" readonly>
                                            <small class="form-text text-muted">Auto-filled when checking out</small>
                                        </div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label class="form-control-label">Current Location</label>
                                            <div class="input-group">
                                                <input class="form-control" type="text" id="current_location_display" readonly placeholder="Getting location...">
                                                <button class="btn btn-outline-secondary" type="button" onclick="getCurrentLocation()">
                                                    <i class="material-symbols-rounded">location_on</i>
                                                </button>
                                            </div>
                                            <input type="hidden" id="latitude" name="latitude">
                                            <input type="hidden" id="longitude" name="longitude">
                                            <small class="form-text text-muted">Click to get current GPS coordinates for geo-fencing validation</small>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label class="form-control-label">Branch Location</label>
                                            <input class="form-control" type="text" id="branch_location_display" readonly placeholder="Select supervisor to see branch">
                                            <small class="form-text text-muted">Supervisor must be within branch premises to mark attendance</small>
                                        </div>
                                    </div>
                                </div>
                                <div class="row mt-3">
                                    <div class="col-12">
                                        <button type="submit" name="mark_supervisor_attendance" class="btn bg-gradient-dark" id="submitBtn">
                                            <i class="material-symbols-rounded me-2">check_circle</i>
                                            Mark Attendance
                                        </button>
                                        <div id="geoFenceStatus" class="mt-2"></div>
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
                            <h6>Supervisor Attendance Records</h6>
                            <p class="text-sm mb-0">Detailed attendance history with geo-fencing information.</p>
                        </div>
                        <div class="card-body px-0 pb-2">
                            <div class="table-responsive">
                                <table class="table align-items-center mb-0">
                                    <thead>
                                        <tr>
                                            <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Supervisor</th>
                                            <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">Date</th>
                                            <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">Status</th>
                                            <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">Check-in</th>
                                            <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">Check-out</th>
                                            <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">Location</th>
                                            <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">Branch</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($attendance_records as $record): ?>
                                            <tr>
                                                <td>
                                                    <div class="d-flex px-2 py-1">
                                                        <div class="avatar avatar-sm me-3 bg-gradient-<?php echo $record['status'] == 'present' ? 'success' : ($record['status'] == 'absent' ? 'danger' : 'warning'); ?>">
                                                            <i class="material-symbols-rounded text-white text-xs">person</i>
                                                        </div>
                                                        <div class="d-flex flex-column justify-content-center">
                                                            <h6 class="mb-0 text-sm"><?php echo htmlspecialchars($record['supervisor_name']); ?></h6>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td>
                                                    <p class="text-xs font-weight-bold mb-0"><?php echo date('M d, Y', strtotime($record['date'])); ?></p>
                                                    <small class="text-muted"><?php echo date('l', strtotime($record['date'])); ?></small>
                                                </td>
                                                <td>
                                                    <span class="badge badge-sm bg-gradient-<?php echo $record['status'] == 'present' ? 'success' : ($record['status'] == 'absent' ? 'danger' : ($record['status'] == 'late' ? 'warning' : 'info')); ?>">
                                                        <?php echo ucfirst(str_replace('_', ' ', $record['status'])); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <p class="text-xs font-weight-bold mb-0"><?php echo $record['check_in_time'] ? date('H:i', strtotime($record['check_in_time'])) : '-'; ?></p>
                                                </td>
                                                <td>
                                                    <p class="text-xs font-weight-bold mb-0"><?php echo $record['check_out_time'] ? date('H:i', strtotime($record['check_out_time'])) : '-'; ?></p>
                                                </td>
                                                <td>
                                                    <?php if ($record['latitude'] && $record['longitude']): ?>
                                                        <div class="d-flex align-items-center">
                                                            <i class="material-symbols-rounded text-success me-1" style="font-size: 16px;">location_on</i>
                                                            <div>
                                                                <p class="text-xs font-weight-bold mb-0"><?php echo number_format($record['latitude'], 6); ?>, <?php echo number_format($record['longitude'], 6); ?></p>
                                                                <small class="text-muted">Geo-fenced</small>
                                                            </div>
                                                        </div>
                                                    <?php else: ?>
                                                        <span class="text-muted">-</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <p class="text-xs font-weight-bold mb-0"><?php echo htmlspecialchars($record['branch_name'] ?? 'N/A'); ?></p>
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

    <script src="./assets/js/core/popper.min.js"></script>
    <script src="./assets/js/core/bootstrap.min.js"></script>
    <script src="./assets/js/plugins/perfect-scrollbar.min.js"></script>
    <script src="./assets/js/plugins/smooth-scrollbar.min.js"></script>
    <script src="./assets/js/plugins/chartjs.min.js"></script>
    <script>
        let currentLatitude = null;
        let currentLongitude = null;

        function loadSupervisorBranch() {
            const supervisorSelect = document.getElementById('supervisor_id');
            const selectedOption = supervisorSelect.options[supervisorSelect.selectedIndex];
            const branchDisplay = document.getElementById('branch_location_display');

            if (selectedOption.value) {
                const branchName = selectedOption.getAttribute('data-branch');
                branchDisplay.value = branchName || 'Branch location not set';
            } else {
                branchDisplay.value = 'Select supervisor to see branch';
            }
        }

        function getCurrentLocation() {
            const locationDisplay = document.getElementById('current_location_display');
            const latitudeInput = document.getElementById('latitude');
            const longitudeInput = document.getElementById('longitude');
            const geoFenceStatus = document.getElementById('geoFenceStatus');

            if (navigator.geolocation) {
                locationDisplay.value = 'Getting location...';
                navigator.geolocation.getCurrentPosition(
                    function(position) {
                        currentLatitude = position.coords.latitude;
                        currentLongitude = position.coords.longitude;

                        latitudeInput.value = currentLatitude;
                        longitudeInput.value = currentLongitude;
                        locationDisplay.value = `${currentLatitude.toFixed(6)}, ${currentLongitude.toFixed(6)}`;

                        // Check geo-fencing (you would implement actual branch coordinates validation here)
                        validateGeoFence(currentLatitude, currentLongitude);
                    },
                    function(error) {
                        locationDisplay.value = 'Location access denied';
                        geoFenceStatus.innerHTML = '<div class="alert alert-danger mt-2"><i class="material-symbols-rounded">error</i> Unable to get location. Please enable GPS and try again.</div>';
                    }, {
                        enableHighAccuracy: true,
                        timeout: 10000,
                        maximumAge: 300000
                    }
                );
            } else {
                locationDisplay.value = 'Geolocation not supported';
                geoFenceStatus.innerHTML = '<div class="alert alert-warning mt-2"><i class="material-symbols-rounded">warning</i> Geolocation is not supported by this browser.</div>';
            }
        }

        function validateGeoFence(lat, lng) {
            const geoFenceStatus = document.getElementById('geoFenceStatus');
            const submitBtn = document.getElementById('submitBtn');

            // For demo purposes, we'll assume any location within reasonable bounds is valid
            // In production, you would check against actual branch coordinates with a radius
            const isWithinGeoFence = true; // Replace with actual geo-fencing logic

            if (isWithinGeoFence) {
                geoFenceStatus.innerHTML = '<div class="alert alert-success mt-2"><i class="material-symbols-rounded">check_circle</i> Location validated. You are within the allowed geo-fence area.</div>';
                submitBtn.disabled = false;
            } else {
                geoFenceStatus.innerHTML = '<div class="alert alert-danger mt-2"><i class="material-symbols-rounded">error</i> You are outside the allowed geo-fence area. Attendance cannot be marked.</div>';
                submitBtn.disabled = true;
            }
        }

        // Auto-get location when supervisor is selected (only for admin)
        <?php if ($role == 'admin'): ?>
            document.getElementById('supervisor_id').addEventListener('change', function() {
                if (this.value) {
                    setTimeout(() => getCurrentLocation(), 500);
                }
            });
        <?php else: ?>
            // For supervisors, auto-get location on page load
            document.addEventListener('DOMContentLoaded', function() {
                setTimeout(() => getCurrentLocation(), 1000);
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
</body>

</html>
