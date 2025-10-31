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

if ($role == 'driver') {
    header('Location: index.php');
    exit;
}

if (!isset($_GET['id'])) {
    header('Location: trips.php');
    exit;
}

$trip_id = (int)$_GET['id'];

// Get trip details
$conn = getDBConnection();
$stmt = $conn->prepare("SELECT * FROM trips WHERE id = ?");
$stmt->execute([$trip_id]);
$trip = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$trip) {
    header('Location: trips.php');
    exit;
}

// Check if user has permission to start this trip
if ($role == 'supervisor' && $trip['supervisor_id'] != $_SESSION['user_id']) {
    header('Location: trips.php');
    exit;
}

// Handle start trip
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['start_trip'])) {
    $stmt = $conn->prepare("UPDATE trips SET status = 'in_progress', start_time = NOW() WHERE id = ?");
    $stmt->execute([$trip_id]);

    // Note: Driver attendance will be marked when passengers validate the OTP/QR code
    // This ensures the driver is only marked present when the trip actually starts with passengers

    header('Location: trips.php');
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=5.0, user-scalable=yes">
    <link rel="apple-touch-icon" sizes="76x76" href="./assets/img/apple-icon.png">
    <link rel="icon" type="image/png" href="./assets/img/favicon.png">
    <title>Start Trip - MS Infosystems Employee Transportation System</title>
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
                        <a class="nav-link active bg-gradient-dark text-white" href="trips.php">
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
                        <li class="breadcrumb-item text-sm"><a class="opacity-5 text-dark" href="trips.php">Trips</a></li>
                        <li class="breadcrumb-item text-sm text-dark active" aria-current="page">Start Trip</li>
                    </ol>
                    <h6 class="font-weight-bolder mb-0">Start Trip #<?php echo $trip['id']; ?> - <?php
                                                                                                    try {
                                                                                                        $stmt = $conn->prepare("SELECT name FROM drivers WHERE id = ?");
                                                                                                        $stmt->execute([$trip['driver_id']]);
                                                                                                        $driver = $stmt->fetch(PDO::FETCH_ASSOC);
                                                                                                        if (!$driver) {
                                                                                                            $stmt = $conn->prepare("SELECT name FROM users WHERE id = ?");
                                                                                                            $stmt->execute([$trip['driver_id']]);
                                                                                                            $driver = $stmt->fetch(PDO::FETCH_ASSOC);
                                                                                                        }
                                                                                                        echo htmlspecialchars($driver['name'] ?? 'Unknown Driver');
                                                                                                    } catch (PDOException $e) {
                                                                                                        echo 'Unknown Driver';
                                                                                                    }
                                                                                                    ?></h6>
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
                            <h6>Start Trip Confirmation</h6>
                            <p class="text-sm mb-0">Verify trip details and confirm the trip has started.</p>
                        </div>
                        <div class="card-body p-3">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="card h-100">
                                        <div class="card-header pb-0 p-3">
                                            <h6 class="mb-0">Trip Information</h6>
                                        </div>
                                        <div class="card-body p-3">
                                            <div class="form-group">
                                                <label class="form-control-label">Trip ID</label>
                                                <input class="form-control" type="text" value="<?php echo $trip['id']; ?>" readonly>
                                            </div>
                                            <div class="form-group">
                                                <label class="form-control-label">Driver</label>
                                                <?php
                                                // Get driver name from the new drivers table if available, fallback to users table
                                                try {
                                                    $stmt = $conn->prepare("SELECT name FROM drivers WHERE id = ?");
                                                    $stmt->execute([$trip['driver_id']]);
                                                    $driver = $stmt->fetch(PDO::FETCH_ASSOC);
                                                    if (!$driver) {
                                                        // Fallback to users table
                                                        $stmt = $conn->prepare("SELECT name FROM users WHERE id = ?");
                                                        $stmt->execute([$trip['driver_id']]);
                                                        $driver = $stmt->fetch(PDO::FETCH_ASSOC);
                                                    }
                                                } catch (PDOException $e) {
                                                    // Final fallback
                                                    $stmt = $conn->prepare("SELECT name FROM users WHERE id = ?");
                                                    $stmt->execute([$trip['driver_id']]);
                                                    $driver = $stmt->fetch(PDO::FETCH_ASSOC);
                                                }
                                                ?>
                                                <input class="form-control" type="text" value="<?php echo htmlspecialchars($driver['name'] ?? 'Unknown Driver'); ?>" readonly>
                                            </div>
                                            <div class="form-group">
                                                <label class="form-control-label">Vehicle</label>
                                                <?php
                                                $stmt = $conn->prepare("SELECT make, model, license_plate FROM vehicles WHERE id = ?");
                                                $stmt->execute([$trip['vehicle_id']]);
                                                $vehicle = $stmt->fetch(PDO::FETCH_ASSOC);
                                                ?>
                                                <input class="form-control" type="text" value="<?php echo htmlspecialchars(($vehicle['make'] ?? 'Unknown') . ' ' . ($vehicle['model'] ?? 'Vehicle') . ' (' . ($vehicle['license_plate'] ?? 'N/A') . ')'); ?>" readonly>
                                            </div>
                                            <div class="form-group">
                                                <label class="form-control-label">Passenger Count</label>
                                                <input class="form-control" type="text" value="<?php echo $trip['passenger_count']; ?>" readonly>
                                            </div>
                                            <div class="form-group">
                                                <label class="form-control-label">Start Location</label>
                                                <input class="form-control" type="text" value="<?php echo $trip['start_location_lat'] . ', ' . $trip['start_location_lng']; ?>" readonly>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="card h-100">
                                        <div class="card-header pb-0 p-3">
                                            <h6 class="mb-0">Trip Verification</h6>
                                        </div>
                                        <div class="card-body p-3">
                                            <?php if ($trip['start_otp']): ?>
                                                <div class="form-group">
                                                    <label class="form-control-label">Start OTP</label>
                                                    <div class="input-group">
                                                        <input class="form-control text-center" style="font-size: 24px; font-weight: bold; color: #e74c3c;" type="text" value="<?php echo $trip['start_otp']; ?>" readonly>
                                                        <button class="btn btn-outline-secondary" type="button" onclick="copyOTP()">Copy</button>
                                                    </div>
                                                    <small class="form-text text-muted">Share this OTP with passengers to verify trip start</small>
                                                </div>
                                            <?php else: ?>
                                                <div class="alert alert-warning">
                                                    <i class="material-symbols-rounded">warning</i>
                                                    <strong>No OTP Generated:</strong> OTP was not generated for this trip. Please contact support.
                                                </div>
                                            <?php endif; ?>

                                            <?php if ($trip['start_qr_code']): ?>
                                                <div class="form-group">
                                                    <label class="form-control-label">Start QR Code</label>
                                                    <div class="text-center">
                                                        <img src="https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=<?php echo urlencode($trip['start_qr_code']); ?>" alt="Start QR Code" class="img-fluid">
                                                    </div>
                                                    <small class="form-text text-muted">Passengers can scan this QR code to start the trip</small>
                                                </div>
                                            <?php else: ?>
                                                <div class="alert alert-warning">
                                                    <i class="material-symbols-rounded">warning</i>
                                                    <strong>No QR Code Generated:</strong> QR code was not generated for this trip. Please contact support.
                                                </div>
                                            <?php endif; ?>

                                            <div class="alert alert-info">
                                                <i class="material-symbols-rounded">info</i>
                                                <strong>Important:</strong> Share the OTP and QR code with passengers for trip verification. The trip will be marked as started once you confirm below.
                                            </div>

                                            <div class="row mt-3 mb-3">
                                                <div class="col-12">
                                                    <button type="button" class="btn bg-gradient-primary w-100 mb-2" onclick="sendOTPToPassenger()">
                                                        <i class="material-symbols-rounded me-2">send</i>Send OTP & QR Code to Passenger
                                                    </button>
                                                </div>
                                            </div>

                                            <form method="POST" action="">
                                                <div class="row">
                                                    <div class="col-6">
                                                        <button type="submit" name="start_trip" class="btn bg-gradient-success w-100">Confirm Trip Started</button>
                                                    </div>
                                                    <div class="col-6">
                                                        <a href="trips.php" class="btn bg-gradient-secondary w-100">Cancel</a>
                                                    </div>
                                                </div>
                                            </form>
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
    <script>
        function copyOTP() {
            const otpInput = document.querySelector('input[readonly]');
            otpInput.select();
            document.execCommand('copy');
            alert('OTP copied to clipboard!');
        }

        function sendOTPToPassenger() {
            const otp = '<?php echo $trip['start_otp']; ?>';
            const qrCode = '<?php echo $trip['start_qr_code']; ?>';
            const tripId = '<?php echo $trip['id']; ?>';
            const passengerPhone = '<?php echo $trip['first_passenger_phone']; ?>';

            // Create message content
            const message = `Trip #${tripId} Start Verification\n\nOTP: ${otp}\n\nQR Code: ${qrCode}\n\nPlease use this OTP or scan the QR code to start your trip.`;

            // Encode message for WhatsApp
            const encodedMessage = encodeURIComponent(message);

            if (passengerPhone) {
                // Open WhatsApp with the message
                const whatsappUrl = `https://wa.me/${passengerPhone.replace(/[^0-9]/g, '')}?text=${encodedMessage}`;
                window.open(whatsappUrl, '_blank');
            } else {
                // Fallback to clipboard if no phone number
                navigator.clipboard.writeText(message).then(function() {
                    alert('No passenger phone number found. OTP and QR code details copied to clipboard!\n\nYou can now share this with the passenger via WhatsApp, SMS, or email.');
                }).catch(function(err) {
                    // Fallback for older browsers
                    const textArea = document.createElement('textarea');
                    textArea.value = message;
                    document.body.appendChild(textArea);
                    textArea.select();
                    document.execCommand('copy');
                    document.body.removeChild(textArea);
                    alert('No passenger phone number found. OTP and QR code details copied to clipboard!\n\nYou can now share this with the passenger via WhatsApp, SMS, or email.');
                });
            }
        }
    </script>
    <script src="./assets/js/material-dashboard.min.js?v=3.2.0"></script>
</body>

</html>
