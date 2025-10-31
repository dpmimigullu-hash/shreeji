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

// Check if user has permission to end this trip
if ($role == 'supervisor' && $trip['supervisor_id'] != $_SESSION['user_id']) {
    header('Location: trips.php');
    exit;
}

// Handle end trip
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['end_trip'])) {
    $end_lat = (float)$_POST['end_lat'];
    $end_lng = (float)$_POST['end_lng'];
    $driver_advance = (float)($_POST['driver_advance'] ?? 0);
    $fuel_amount = (float)($_POST['fuel_amount'] ?? 0);

    // Calculate distance in kilometers (convert from meters)
    $distance_meters = calculateDistance($trip['start_location_lat'], $trip['start_location_lng'], $end_lat, $end_lng);
    $distance = $distance_meters / 1000; // Convert to kilometers

    // Get car details for billing
    $stmt = $conn->prepare("SELECT seating_capacity FROM vehicles WHERE id = ?");
    $stmt->execute([$trip['vehicle_id']]);
    $car = $stmt->fetch(PDO::FETCH_ASSOC);

    // Calculate base bill
    $base_amount = calculateBill($distance, $car['seating_capacity']);

    // Calculate fuel surcharge (4% if fuel amount is entered)
    $fuel_surcharge = 0;
    if ($fuel_amount > 0) {
        $fuel_surcharge = $fuel_amount * 0.04; // 4% surcharge
    }

    // Calculate final amount (base + fuel + surcharge - advance)
    $final_amount = $base_amount + $fuel_amount + $fuel_surcharge - $driver_advance;

    // Ensure final amount is not negative
    $final_amount = max(0, $final_amount);

    // Update trip with billing information - handle missing columns gracefully
    try {
        $stmt = $conn->prepare("UPDATE trips SET status = 'completed', end_time = NOW(), end_location_lat = ?, end_location_lng = ?, distance = ?, driver_advance = ?, fuel_amount = ?, fuel_surcharge = ? WHERE id = ?");
        $stmt->execute([$end_lat, $end_lng, $distance, $driver_advance, $fuel_amount, $fuel_surcharge, $trip_id]);
    } catch (PDOException $e) {
        // Fallback for older schema without billing columns
        try {
            $stmt = $conn->prepare("UPDATE trips SET status = 'completed', end_time = NOW(), end_location_lat = ?, end_location_lng = ?, distance = ? WHERE id = ?");
            $stmt->execute([$end_lat, $end_lng, $distance, $trip_id]);
        } catch (PDOException $e2) {
            // Final fallback for very old schema
            $stmt = $conn->prepare("UPDATE trips SET status = 'completed', end_time = NOW(), end_location_lat = ?, end_location_lng = ? WHERE id = ?");
            $stmt->execute([$end_lat, $end_lng, $trip_id]);
        }
    }

    // Create billing record with detailed breakdown - handle missing columns gracefully
    try {
        $stmt = $conn->prepare("INSERT INTO billing (trip_id, amount, base_amount, fuel_amount, fuel_surcharge, driver_advance, final_amount) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$trip_id, $final_amount, $base_amount, $fuel_amount, $fuel_surcharge, $driver_advance, $final_amount]);
    } catch (PDOException $e) {
        // Fallback for older billing table schema
        try {
            $stmt = $conn->prepare("INSERT INTO billing (trip_id, amount) VALUES (?, ?)");
            $stmt->execute([$trip_id, $final_amount]);
        } catch (PDOException $e2) {
            // If billing table doesn't exist, skip billing creation
            error_log("Billing table not available: " . $e2->getMessage());
        }
    }

    // Note: Driver attendance will be marked as inactive when passengers validate the end OTP/QR code
    // This ensures the driver is only marked inactive when the trip actually ends with passenger validation

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
    <title>End Trip - MS Infosystems Employee Transportation System</title>
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
                    <h6 class="font-weight-bolder mb-0">MS Infosystems - Employee Transportation System</h6>
                </nav>
                <div class="collapse navbar-collapse mt-sm-0 mt-2 me-md-0 me-sm-4" id="navbar">
                    <ul class="navbar-nav d-flex align-items-center justify-content-end">
                        <li class="nav-item d-xl-none ps-3 d-flex align-items-center">
                            <a href="javascript:;" class="nav-link text-body p-0" id="iconNavbarSidenav" data-bs-toggle="sidenav">
                                <div class="sidenav-toggler-inner">
                                    <i class="sidenav-toggler-line"></i>
                                    <i class="sidenav-toggler-line"></i>
                                    <i class="sidenav-toggler-line"></i>
                                </div>
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
                            <h6>End Trip Confirmation</h6>
                            <p class="text-sm mb-0">Confirm the trip has been completed and calculate final billing.</p>
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
                                                $stmt = $conn->prepare("SELECT name FROM users WHERE id = ?");
                                                $stmt->execute([$trip['driver_id']]);
                                                $driver = $stmt->fetch(PDO::FETCH_ASSOC);
                                                ?>
                                                <input class="form-control" type="text" value="<?php echo htmlspecialchars($driver ? $driver['name'] : 'Driver not found'); ?>" readonly>
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
                                                <label class="form-control-label">Start Time</label>
                                                <input class="form-control" type="text" value="<?php echo $trip['start_time'] ? date('Y-m-d H:i:s', strtotime($trip['start_time'])) : 'Not started'; ?>" readonly>
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
                                            <?php if ($trip['end_otp']): ?>
                                                <div class="form-group">
                                                    <label class="form-control-label">End OTP</label>
                                                    <div class="input-group">
                                                        <input class="form-control text-center" style="font-size: 24px; font-weight: bold; color: #e74c3c;" type="text" value="<?php echo $trip['end_otp']; ?>" readonly>
                                                        <button class="btn btn-outline-secondary" type="button" onclick="copyOTP()">Copy</button>
                                                    </div>
                                                    <small class="form-text text-muted">Share this OTP with passengers to verify trip completion</small>
                                                </div>
                                            <?php else: ?>
                                                <div class="alert alert-warning">
                                                    <i class="material-symbols-rounded">warning</i>
                                                    <strong>No End OTP Available</strong><br>
                                                    This trip was created before OTP functionality was implemented. Please contact support.
                                                </div>
                                            <?php endif; ?>

                                            <div class="form-group">
                                                <label class="form-control-label">End QR Code</label>
                                                <div class="text-center p-3 bg-light rounded">
                                                    <div class="qr-code-container border rounded shadow-sm" style="display: inline-block; background: white; padding: 20px;">
                                                        <?php
                                                        // Generate QR code for end trip verification
                                                        $qrData = "END_TRIP_" . $trip_id . "_" . time() . "_" . ($trip['end_otp'] ?? 'NO_OTP');
                                                        $qrCodeUrl = "https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=" . urlencode($qrData);

                                                        // Debug output
                                                        echo "<!-- QR Data: $qrData -->\n";
                                                        echo "<!-- QR URL: $qrCodeUrl -->\n";
                                                        ?>
                                                        <img src="<?php echo htmlspecialchars($qrCodeUrl); ?>"
                                                            alt="End QR Code"
                                                            id="end-qr-code"
                                                            class="img-fluid rounded"
                                                            style="width: 200px; height: 200px; border: 1px solid #dee2e6;"
                                                            onload="console.log('QR Code loaded successfully')"
                                                            onerror="console.error('QR Code failed to load:', this.src); handleQRError(this)">
                                                        <div id="qr-error" style="display: none; color: #dc3545; font-size: 14px; padding: 20px;">
                                                            <i class="material-symbols-rounded">error</i><br>
                                                            QR Code failed to load<br>
                                                            <small>Check console for details</small>
                                                        </div>
                                                        <div id="qr-loading" style="display: none; color: #007bff; font-size: 14px; padding: 20px;">
                                                            <i class="material-symbols-rounded">hourglass_top</i><br>
                                                            Generating QR code...
                                                        </div>
                                                    </div>
                                                    <div class="mt-3">
                                                        <p class="text-primary font-weight-bold mb-1">🔍 Scan this QR code to verify trip completion</p>
                                                        <small class="text-muted">Passengers can scan this QR code to end the trip</small>
                                                        <div class="mt-2">
                                                            <button type="button" class="btn btn-sm btn-outline-success" onclick="generateNewQRCode()">
                                                                <i class="material-symbols-rounded">qr_code</i> Generate New QR
                                                            </button>
                                                            <button type="button" class="btn btn-sm btn-outline-primary" onclick="refreshQRCode()">
                                                                <i class="material-symbols-rounded">refresh</i> Refresh
                                                            </button>
                                                            <button type="button" class="btn btn-sm btn-outline-info" onclick="testQRCode()">
                                                                <i class="material-symbols-rounded">bug_report</i> Test QR
                                                            </button>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            <form method="POST" action="">
                                                <div class="form-group">
                                                    <label for="end_lat" class="form-control-label">Actual End Latitude</label>
                                                    <input class="form-control" type="number" step="any" id="end_lat" name="end_lat" value="<?php echo $trip['end_location_lat'] ?: ''; ?>" required>
                                                </div>
                                                <div class="form-group">
                                                    <label for="end_lng" class="form-control-label">Actual End Longitude</label>
                                                    <input class="form-control" type="number" step="any" id="end_lng" name="end_lng" value="<?php echo $trip['end_location_lng'] ?: ''; ?>" required>
                                                </div>
                                                <div class="form-group">
                                                    <button type="button" class="btn btn-outline-info" onclick="getCurrentLocation()">Use Current Location</button>
                                                    <button type="button" class="btn btn-outline-success" onclick="autoFillLocation()">Auto Fill Location</button>
                                                </div>

                                                <!-- Driver Billing Section -->
                                                <div class="border rounded p-3 bg-light mt-4">
                                                    <h6 class="text-info mb-3"><i class="material-symbols-rounded me-2">account_balance_wallet</i>Driver Billing Information</h6>

                                                    <div class="form-group">
                                                        <label for="driver_advance" class="form-control-label">
                                                            <i class="material-symbols-rounded text-success me-1">payments</i>Driver Advance Amount (₹)
                                                        </label>
                                                        <input class="form-control" type="number" step="0.01" id="driver_advance" name="driver_advance" placeholder="0.00" min="0">
                                                        <small class="form-text text-muted">Amount paid to driver as advance (will be deducted from final bill)</small>
                                                    </div>

                                                    <div class="form-group">
                                                        <label for="fuel_amount" class="form-control-label">
                                                            <i class="material-symbols-rounded text-warning me-1">local_gas_station</i>Fuel Amount (₹)
                                                        </label>
                                                        <input class="form-control" type="number" step="0.01" id="fuel_amount" name="fuel_amount" placeholder="0.00" min="0">
                                                        <small class="form-text text-muted">Fuel cost for the trip (4% surcharge will be added if fuel is entered)</small>
                                                    </div>

                                                    <!-- Billing Preview -->
                                                    <div id="billing-preview" class="mt-3 p-3 bg-white rounded border" style="display: none;">
                                                        <h6 class="text-primary mb-2">Billing Preview</h6>
                                                        <div id="billing-details"></div>
                                                    </div>
                                                </div>

                                                <div class="row mt-3">
                                                    <div class="col-6">
                                                        <button type="submit" name="end_trip" class="btn bg-gradient-success w-100">Confirm Trip Ended</button>
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

        function getCurrentLocation() {
            if (navigator.geolocation) {
                navigator.geolocation.getCurrentPosition(function(position) {
                    document.getElementById('end_lat').value = position.coords.latitude;
                    document.getElementById('end_lng').value = position.coords.longitude;
                }, function(error) {
                    alert('Error getting location: ' + error.message);
                });
            } else {
                alert('Geolocation is not supported by this browser.');
            }
        }

        function autoFillLocation() {
            console.log('Attempting to get current location...');
            if (navigator.geolocation) {
                navigator.geolocation.getCurrentPosition(function(position) {
                    const lat = position.coords.latitude;
                    const lng = position.coords.longitude;
                    console.log('Location obtained:', lat, lng);
                    document.getElementById('end_lat').value = lat;
                    document.getElementById('end_lng').value = lng;
                    console.log('Location fields filled successfully');
                    // Optional: Show a subtle notification instead of alert
                    showNotification('Location automatically filled: ' + lat.toFixed(6) + ', ' + lng.toFixed(6), 'success');
                }, function(error) {
                    console.error('Geolocation error:', error);
                    let errorMessage = 'Unable to get location. ';
                    switch (error.code) {
                        case error.PERMISSION_DENIED:
                            errorMessage += 'Please allow location access in your browser.';
                            break;
                        case error.POSITION_UNAVAILABLE:
                            errorMessage += 'Location information is unavailable.';
                            break;
                        case error.TIMEOUT:
                            errorMessage += 'Location request timed out.';
                            break;
                        default:
                            errorMessage += 'Please check your browser settings.';
                            break;
                    }
                    showNotification(errorMessage, 'error');
                }, {
                    enableHighAccuracy: true,
                    timeout: 15000,
                    maximumAge: 300000
                });
            } else {
                console.error('Geolocation not supported');
                showNotification('Geolocation is not supported by this browser.', 'error');
            }
        }

        function showNotification(message, type) {
            // Create a simple notification element
            const notification = document.createElement('div');
            notification.className = `alert alert-${type === 'success' ? 'success' : 'danger'} alert-dismissible fade show position-fixed`;
            notification.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px;';
            notification.innerHTML = `
                <strong>${type === 'success' ? 'Success!' : 'Error!'}</strong> ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            `;

            document.body.appendChild(notification);

            // Auto remove after 5 seconds
            setTimeout(() => {
                if (notification.parentNode) {
                    notification.remove();
                }
            }, 5000);
        }

        // Auto-fill location when page loads
        document.addEventListener('DOMContentLoaded', function() {
            console.log('Page loaded, checking location fields...');
            // Check if location fields are empty and try to auto-fill
            const latField = document.getElementById('end_lat');
            const lngField = document.getElementById('end_lng');

            console.log('Lat field value:', latField.value);
            console.log('Lng field value:', lngField.value);

            if (!latField.value.trim() && !lngField.value.trim()) {
                console.log('Location fields are empty, attempting to auto-fill...');
                // Try to auto-fill location after a short delay
                setTimeout(function() {
                    console.log('Calling autoFillLocation...');
                    autoFillLocation();
                }, 2000); // Increased delay to ensure page is fully loaded
            } else {
                console.log('Location fields already have values, skipping auto-fill');
            }
        });

        function handleQRError(img) {
            console.error('QR Code failed to load:', img.src);
            img.style.display = 'none';
            document.getElementById('qr-error').style.display = 'block';
        }

        function refreshQRCode() {
            const img = document.querySelector('img[alt="End QR Code"]');
            const errorDiv = document.getElementById('qr-error');

            // Hide error and show loading
            errorDiv.style.display = 'none';
            img.style.display = 'block';

            // Add timestamp to force reload
            const currentSrc = img.src;
            const separator = currentSrc.includes('?') ? '&' : '?';
            img.src = currentSrc + separator + 't=' + Date.now();
        }

        function generateNewQRCode() {
            const img = document.querySelector('img[alt="End QR Code"]');
            const errorDiv = document.getElementById('qr-error');
            const loadingDiv = document.getElementById('qr-loading');

            // Show loading state
            loadingDiv.style.display = 'block';
            errorDiv.style.display = 'none';
            img.style.display = 'none';

            // Generate new QR code data
            const tripId = <?php echo $trip_id; ?>;
            const timestamp = Date.now();
            const otp = '<?php echo $trip['end_otp'] ?? 'NO_OTP'; ?>';
            const qrData = 'END_TRIP_' + tripId + '_' + timestamp + '_' + otp;

            // Create QR Server QR code URL (more reliable)
            const newQrUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=' + encodeURIComponent(qrData);

            // Update image source
            img.src = newQrUrl;
            img.onload = function() {
                loadingDiv.style.display = 'none';
                img.style.display = 'block';
                console.log('New QR code generated successfully:', newQrUrl);
            };
            img.onerror = function() {
                loadingDiv.style.display = 'none';
                errorDiv.style.display = 'block';
                console.error('Failed to generate new QR code:', newQrUrl);
            };
        }

        function testQRCode() {
            const img = document.querySelector('img[alt="End QR Code"]');
            const currentSrc = img.src;
            console.log('Current QR Code URL:', currentSrc);
            console.log('Image natural dimensions:', img.naturalWidth, 'x', img.naturalHeight);
            console.log('Image display dimensions:', img.width, 'x', img.height);
            alert('Check browser console for QR code details');
        }

        // Billing calculation and preview
        function calculateBillingPreview() {
            const advance = parseFloat(document.getElementById('driver_advance').value) || 0;
            const fuel = parseFloat(document.getElementById('fuel_amount').value) || 0;

            // Get base amount (this would come from server calculation)
            const baseAmount = <?php
                                if ($trip) {
                                    $startLat = $trip['start_location_lat'];
                                    $startLng = $trip['start_location_lng'];
                                    $endLat = $trip['end_location_lat'] ?: 0;
                                    $endLng = $trip['end_location_lng'] ?: 0;
                                    $distance = calculateDistance($startLat, $startLng, $endLat, $endLng);
                                    $stmt = $conn->prepare("SELECT seating_capacity FROM vehicles WHERE id = ?");
                                    $stmt->execute([$trip['vehicle_id']]);
                                    $car = $stmt->fetch(PDO::FETCH_ASSOC);
                                    $seatingCapacity = $car ? $car['seating_capacity'] : 4;
                                    echo calculateBill($distance, $seatingCapacity);
                                } else {
                                    echo '0';
                                }
                                ?>;

            const fuelSurcharge = fuel > 0 ? fuel * 0.04 : 0;
            const finalAmount = Math.max(0, baseAmount + fuel + fuelSurcharge - advance);

            const preview = document.getElementById('billing-preview');
            const details = document.getElementById('billing-details');

            details.innerHTML = `
                <div class="row">
                    <div class="col-6"><strong>Base Fare:</strong></div>
                    <div class="col-6 text-end">₹${baseAmount.toFixed(2)}</div>
                </div>
                ${fuel > 0 ? `
                <div class="row">
                    <div class="col-6"><strong>Fuel Cost:</strong></div>
                    <div class="col-6 text-end">₹${fuel.toFixed(2)}</div>
                </div>
                <div class="row">
                    <div class="col-6"><strong>Fuel Surcharge (4%):</strong></div>
                    <div class="col-6 text-end">₹${fuelSurcharge.toFixed(2)}</div>
                </div>` : ''}
                ${advance > 0 ? `
                <div class="row">
                    <div class="col-6"><strong>Driver Advance:</strong></div>
                    <div class="col-6 text-end">-₹${advance.toFixed(2)}</div>
                </div>` : ''}
                <hr class="my-2">
                <div class="row">
                    <div class="col-6"><strong class="text-primary">Final Amount:</strong></div>
                    <div class="col-6 text-end"><strong class="text-primary">₹${finalAmount.toFixed(2)}</strong></div>
                </div>
            `;

            preview.style.display = (advance > 0 || fuel > 0) ? 'block' : 'none';
        }

        // Add event listeners for billing calculation
        document.addEventListener('DOMContentLoaded', function() {
            const advanceInput = document.getElementById('driver_advance');
            const fuelInput = document.getElementById('fuel_amount');

            if (advanceInput && fuelInput) {
                advanceInput.addEventListener('input', calculateBillingPreview);
                fuelInput.addEventListener('input', calculateBillingPreview);
            }
        });

        // Check QR code on page load
        document.addEventListener('DOMContentLoaded', function() {
            const qrImg = document.querySelector('img[alt="End QR Code"]');
            if (qrImg) {
                qrImg.addEventListener('load', function() {
                    console.log('QR Code loaded successfully');
                });
                qrImg.addEventListener('error', function() {
                    console.error('QR Code failed to load on page load');
                    handleQRError(qrImg);
                });
            }
        });
    </script>
    <script src="./assets/js/material-dashboard.min.js?v=3.2.0"></script>
</body>

</html>
