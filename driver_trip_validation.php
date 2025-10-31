<?php
session_start();
require_once 'includes/config.php';
require_once 'includes/functions.php';

// Check if user is logged in and is a driver
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user = getUserById($_SESSION['user_id']);
$role = $user['role'];

if ($role != 'driver') {
    header('Location: index.php');
    exit;
}

// Get driver's current trip
$conn = getDBConnection();
$stmt = $conn->prepare("SELECT * FROM trips WHERE driver_id = ? AND status IN ('scheduled', 'in_progress') ORDER BY created_at DESC LIMIT 1");
$stmt->execute([$_SESSION['user_id']]);
$currentTrip = $stmt->fetch(PDO::FETCH_ASSOC);

// Handle OTP/QR validation
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['validate_start'])) {
        $providedOtp = trim($_POST['start_otp']);
        $tripId = (int)$_POST['trip_id'];

        if (validateTripStart($tripId, $providedOtp)) {
            $message = 'Trip started successfully! You can now begin the journey.';
            $messageType = 'success';

            // Update trip status
            $stmt = $conn->prepare("UPDATE trips SET status = 'in_progress', start_time = NOW() WHERE id = ?");
            $stmt->execute([$tripId]);

            // Refresh current trip data
            $stmt = $conn->prepare("SELECT * FROM trips WHERE id = ?");
            $stmt->execute([$tripId]);
            $currentTrip = $stmt->fetch(PDO::FETCH_ASSOC);
        } else {
            $message = 'Invalid OTP. Please check with the passenger and try again.';
            $messageType = 'error';
        }
    }

    if (isset($_POST['validate_end'])) {
        $providedOtp = trim($_POST['end_otp']);
        $tripId = (int)$_POST['trip_id'];

        if (validateTripEnd($tripId, $providedOtp)) {
            $message = 'Trip completed successfully! Please confirm the final details.';
            $messageType = 'success';

            // Update trip status
            $stmt = $conn->prepare("UPDATE trips SET status = 'completed', end_time = NOW() WHERE id = ?");
            $stmt->execute([$tripId]);

            // Redirect to end trip page for final confirmation
            header('Location: end_trip.php?id=' . $tripId);
            exit;
        } else {
            $message = 'Invalid OTP. Please check with the passenger and try again.';
            $messageType = 'error';
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
    <title>Trip Validation - MS Infosystems Employee Transportation System</title>
    <link rel="stylesheet" type="text/css" href="https://fonts.googleapis.com/css?family=Inter:300,400,500,600,700,900" />
    <link href="./assets/nucleo-icons.css" rel="stylesheet" />
    <link href="./assets/nucleo-svg.css" rel="stylesheet" />
    <script src="https://kit.fontawesome.com/42d5adcbca.js" crossorigin="anonymous"></script>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded:opsz,wght,FILL,GRAD@24,400,0,0" />
    <link id="pagestyle" href="./assets/material-dashboard.css?v=3.2.0" rel="stylesheet" />
    <script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
    <style>
        .validation-card {
            max-width: 500px;
            margin: 0 auto;
        }

        .otp-input {
            font-size: 24px;
            text-align: center;
            letter-spacing: 8px;
        }

        .qr-scanner {
            width: 100%;
            max-width: 400px;
            margin: 0 auto;
        }

        .location-status {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1000;
            background: rgba(255, 255, 255, 0.95);
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 12px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            backdrop-filter: blur(10px);
        }

        .location-indicator {
            display: inline-block;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            margin-right: 8px;
        }

        .location-active {
            background: #28a745;
            animation: pulse 2s infinite;
        }

        .location-inactive {
            background: #dc3545;
        }

        @keyframes pulse {
            0% {
                opacity: 1;
            }

            50% {
                opacity: 0.5;
            }

            100% {
                opacity: 1;
            }
        }

        .camera-controls {
            margin-top: 10px;
        }

        .camera-controls button {
            margin: 0 5px 5px 0;
        }

        .location-info {
            font-size: 12px;
            color: #6c757d;
            margin-top: 4px;
        }
    </style>
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
                    <a class="nav-link active bg-gradient-dark text-white" href="driver_trip_validation.php">
                        <i class="material-symbols-rounded opacity-5">qr_code_scanner</i>
                        <span class="nav-link-text ms-1">Trip Validation</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link text-dark" href="geolocation_tracker.php">
                        <i class="material-symbols-rounded opacity-5">location_on</i>
                        <span class="nav-link-text ms-1">Live Tracking</span>
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

        <!-- Location Status Widget -->
        <div id="location-status" class="location-status">
            <div class="d-flex align-items-center">
                <span id="location-indicator" class="location-indicator location-inactive"></span>
                <div>
                    <div class="fw-bold" id="location-text">GPS Tracking</div>
                    <div class="location-info" id="location-details">Initializing...</div>
                </div>
            </div>
        </div>

        <div class="container-fluid py-2">
            <?php if ($message): ?>
                <div class="alert alert-<?php echo $messageType == 'success' ? 'success' : 'danger'; ?> alert-dismissible fade show" role="alert">
                    <strong><?php echo ucfirst($messageType); ?>!</strong> <?php echo $message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if ($currentTrip): ?>
                <div class="row">
                    <div class="col-12">
                        <div class="card validation-card">
                            <div class="card-header pb-0 p-3">
                                <h6>Trip #<?php echo $currentTrip['id']; ?> - <?php echo ucfirst(str_replace('_', ' ', $currentTrip['status'])); ?></h6>
                            </div>
                            <div class="card-body p-3">
                                <!-- Trip Info -->
                                <div class="row mb-4">
                                    <div class="col-12">
                                        <div class="border rounded p-3 bg-light">
                                            <div class="row">
                                                <div class="col-md-6">
                                                    <p class="mb-1"><strong>From:</strong> <?php echo htmlspecialchars($currentTrip['start_location_name']); ?></p>
                                                    <p class="mb-1"><strong>To:</strong> <?php echo htmlspecialchars($currentTrip['end_location_name']); ?></p>
                                                </div>
                                                <div class="col-md-6">
                                                    <p class="mb-1"><strong>Passengers:</strong> <?php echo $currentTrip['passenger_count']; ?></p>
                                                    <p class="mb-1"><strong>Date:</strong> <?php echo date('d/m/Y', strtotime($currentTrip['trip_date'])); ?></p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Start Trip Validation -->
                                <?php if ($currentTrip['status'] == 'scheduled'): ?>
                                    <div class="row mb-4">
                                        <div class="col-12">
                                            <div class="card border-primary">
                                                <div class="card-header bg-primary text-white">
                                                    <h6 class="mb-0"><i class="material-symbols-rounded me-2">play_arrow</i>Start Trip Validation</h6>
                                                </div>
                                                <div class="card-body">
                                                    <p class="text-muted mb-3">Get OTP from the first passenger to start the trip</p>

                                                    <!-- Tab Navigation -->
                                                    <ul class="nav nav-tabs" id="startValidationTabs" role="tablist">
                                                        <li class="nav-item" role="presentation">
                                                            <button class="nav-link active" id="otp-tab" data-bs-toggle="tab" data-bs-target="#otp-panel" type="button" role="tab">Enter OTP</button>
                                                        </li>
                                                        <li class="nav-item" role="presentation">
                                                            <button class="nav-link" id="qr-tab" data-bs-toggle="tab" data-bs-target="#qr-panel" type="button" role="tab">Scan QR Code</button>
                                                        </li>
                                                    </ul>

                                                    <!-- Tab Content -->
                                                    <div class="tab-content mt-3" id="startValidationContent">
                                                        <!-- OTP Input Tab -->
                                                        <div class="tab-pane fade show active" id="otp-panel" role="tabpanel">
                                                            <form method="POST" action="">
                                                                <input type="hidden" name="trip_id" value="<?php echo $currentTrip['id']; ?>">
                                                                <div class="form-group">
                                                                    <label for="start_otp" class="form-control-label">Enter Start OTP</label>
                                                                    <input type="text" class="form-control otp-input" id="start_otp" name="start_otp" placeholder="000000" maxlength="6" pattern="[0-9]{6}" required>
                                                                    <small class="form-text text-muted">Enter the 6-digit OTP provided by the first passenger</small>
                                                                </div>
                                                                <button type="submit" name="validate_start" class="btn btn-primary w-100">
                                                                    <i class="material-symbols-rounded me-2">play_arrow</i>Start Trip
                                                                </button>
                                                            </form>
                                                        </div>

                                                        <!-- QR Scanner Tab -->
                                                        <div class="tab-pane fade" id="qr-panel" role="tabpanel">
                                                            <div class="text-center">
                                                                <div id="qr-reader" class="qr-scanner"></div>
                                                                <div id="qr-result" class="mt-3"></div>
                                                                <div class="camera-controls">
                                                                    <button type="button" class="btn btn-primary" onclick="startQRScanner()">
                                                                        <i class="material-symbols-rounded me-2">qr_code_scanner</i>Start Camera
                                                                    </button>
                                                                    <button type="button" class="btn btn-outline-secondary" onclick="stopQRScanner()">
                                                                        <i class="material-symbols-rounded me-2">stop</i>Stop Camera
                                                                    </button>
                                                                    <button type="button" class="btn btn-outline-info" onclick="switchCamera()">
                                                                        <i class="material-symbols-rounded me-2">cameraswitch</i>Switch Camera
                                                                    </button>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endif; ?>

                                <!-- End Trip Validation -->
                                <?php if ($currentTrip['status'] == 'in_progress'): ?>
                                    <div class="row">
                                        <div class="col-12">
                                            <div class="card border-success">
                                                <div class="card-header bg-success text-white">
                                                    <h6 class="mb-0"><i class="material-symbols-rounded me-2">stop</i>End Trip Validation</h6>
                                                </div>
                                                <div class="card-body">
                                                    <p class="text-muted mb-3">Get OTP from the last passenger to complete the trip</p>

                                                    <!-- Tab Navigation -->
                                                    <ul class="nav nav-tabs" id="endValidationTabs" role="tablist">
                                                        <li class="nav-item" role="presentation">
                                                            <button class="nav-link active" id="end-otp-tab" data-bs-toggle="tab" data-bs-target="#end-otp-panel" type="button" role="tab">Enter OTP</button>
                                                        </li>
                                                        <li class="nav-item" role="presentation">
                                                            <button class="nav-link" id="end-qr-tab" data-bs-toggle="tab" data-bs-target="#end-qr-panel" type="button" role="tab">Scan QR Code</button>
                                                        </li>
                                                    </ul>

                                                    <!-- Tab Content -->
                                                    <div class="tab-content mt-3" id="endValidationContent">
                                                        <!-- OTP Input Tab -->
                                                        <div class="tab-pane fade show active" id="end-otp-panel" role="tabpanel">
                                                            <form method="POST" action="">
                                                                <input type="hidden" name="trip_id" value="<?php echo $currentTrip['id']; ?>">
                                                                <div class="form-group">
                                                                    <label for="end_otp" class="form-control-label">Enter End OTP</label>
                                                                    <input type="text" class="form-control otp-input" id="end_otp" name="end_otp" placeholder="000000" maxlength="6" pattern="[0-9]{6}" required>
                                                                    <small class="form-text text-muted">Enter the 6-digit OTP provided by the last passenger</small>
                                                                </div>
                                                                <button type="submit" name="validate_end" class="btn btn-success w-100">
                                                                    <i class="material-symbols-rounded me-2">stop</i>Complete Trip
                                                                </button>
                                                            </form>
                                                        </div>

                                                        <!-- QR Scanner Tab -->
                                                        <div class="tab-pane fade" id="end-qr-panel" role="tabpanel">
                                                            <div class="text-center">
                                                                <div id="end-qr-reader" class="qr-scanner"></div>
                                                                <div id="end-qr-result" class="mt-3"></div>
                                                                <div class="camera-controls">
                                                                    <button type="button" class="btn btn-success" onclick="startEndQRScanner()">
                                                                        <i class="material-symbols-rounded me-2">qr_code_scanner</i>Start Camera
                                                                    </button>
                                                                    <button type="button" class="btn btn-outline-secondary" onclick="stopEndQRScanner()">
                                                                        <i class="material-symbols-rounded me-2">stop</i>Stop Camera
                                                                    </button>
                                                                    <button type="button" class="btn btn-outline-info" onclick="switchEndCamera()">
                                                                        <i class="material-symbols-rounded me-2">cameraswitch</i>Switch Camera
                                                                    </button>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-body text-center p-5">
                                <i class="material-symbols-rounded text-muted" style="font-size: 4rem;">directions_car</i>
                                <h4 class="mt-3">No Active Trip</h4>
                                <p class="text-muted">You don't have any scheduled or in-progress trips at the moment.</p>
                                <a href="index.php" class="btn btn-primary">
                                    <i class="material-symbols-rounded me-2">dashboard</i>Go to Dashboard
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <script src="./assets/js/core/popper.min.js"></script>
    <script src="./assets/js/core/bootstrap.min.js"></script>
    <script src="./assets/js/plugins/perfect-scrollbar.min.js"></script>
    <script src="./assets/js/plugins/smooth-scrollbar.min.js"></script>
    <script src="./assets/js/material-dashboard.min.js?v=3.2.0"></script>

    <script>
        let qrScanner = null;
        let endQrScanner = null;
        let locationWatcher = null;
        let currentLocation = null;

        // GPS Location Tracking
        function initLocationTracking() {
            if (navigator.geolocation) {
                // Start continuous location tracking
                locationWatcher = navigator.geolocation.watchPosition(
                    (position) => {
                        currentLocation = {
                            lat: position.coords.latitude,
                            lng: position.coords.longitude,
                            accuracy: position.coords.accuracy,
                            timestamp: position.timestamp
                        };

                        updateLocationStatus(true, position.coords);
                        sendLocationUpdate(currentLocation);
                    },
                    (error) => {
                        console.error('Location error:', error);
                        updateLocationStatus(false, null, error.message);
                    }, {
                        enableHighAccuracy: true,
                        timeout: 10000,
                        maximumAge: 30000
                    }
                );
            } else {
                updateLocationStatus(false, null, 'Geolocation not supported');
            }
        }

        function updateLocationStatus(isActive, coords, error = null) {
            const indicator = document.getElementById('location-indicator');
            const text = document.getElementById('location-text');
            const details = document.getElementById('location-details');

            if (isActive && coords) {
                indicator.className = 'location-indicator location-active';
                text.textContent = 'GPS Active';
                details.textContent = `${coords.latitude.toFixed(6)}, ${coords.longitude.toFixed(6)} (±${Math.round(coords.accuracy)}m)`;
            } else {
                indicator.className = 'location-indicator location-inactive';
                text.textContent = 'GPS Inactive';
                details.textContent = error || 'Location unavailable';
            }
        }

        function sendLocationUpdate(location) {
            // Send location update to server
            fetch('update_driver_location.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        driver_id: <?php echo $_SESSION['user_id']; ?>,
                        latitude: location.lat,
                        longitude: location.lng,
                        accuracy: location.accuracy,
                        trip_id: <?php echo $currentTrip ? $currentTrip['id'] : 'null'; ?>
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        console.log('Location updated successfully');
                    } else {
                        console.error('Location update failed:', data.message);
                    }
                })
                .catch(error => {
                    console.error('Location update error:', error);
                });
        }

        function startQRScanner() {
            const qrReader = document.getElementById('qr-reader');
            const resultDiv = document.getElementById('qr-result');

            if (qrScanner) {
                qrScanner.clear();
            }

            qrScanner = new Html5QrcodeScanner("qr-reader", {
                fps: 10,
                qrbox: 250,
                aspectRatio: 1.0,
                videoConstraints: {
                    facingMode: "environment" // Use back camera by default
                }
            });

            qrScanner.render((decodedText) => {
                // Stop scanning
                qrScanner.clear();

                // Process the scanned QR code
                resultDiv.innerHTML = '<div class="alert alert-success"><i class="material-symbols-rounded me-2">check_circle</i>QR Code scanned successfully!</div>';

                // Extract OTP from QR data (format: START_TRIPID_TIMESTAMP_OTP)
                const parts = decodedText.split('_');
                if (parts.length >= 4 && parts[0] === 'START') {
                    const otp = parts[3];
                    document.getElementById('start_otp').value = otp;

                    // Auto-submit the form
                    setTimeout(() => {
                        document.querySelector('#otp-panel form').submit();
                    }, 1500);
                } else {
                    resultDiv.innerHTML = '<div class="alert alert-danger"><i class="material-symbols-rounded me-2">error</i>Invalid QR code format</div>';
                }
            }, (error) => {
                console.log('QR scan error:', error);
            });
        }

        function stopQRScanner() {
            if (qrScanner) {
                qrScanner.clear();
                qrScanner = null;
                document.getElementById('qr-result').innerHTML = '<div class="alert alert-info"><i class="material-symbols-rounded me-2">info</i>Scanner stopped</div>';
            }
        }

        function switchCamera() {
            if (qrScanner) {
                qrScanner.clear();
                // Reinitialize with different camera
                setTimeout(() => {
                    qrScanner = new Html5QrcodeScanner("qr-reader", {
                        fps: 10,
                        qrbox: 250,
                        videoConstraints: {
                            facingMode: qrScanner.getState().facingMode === "environment" ? "user" : "environment"
                        }
                    });
                    qrScanner.render( /* same callback */ );
                }, 500);
            }
        }

        function startEndQRScanner() {
            const qrReader = document.getElementById('end-qr-reader');
            const resultDiv = document.getElementById('end-qr-result');

            if (endQrScanner) {
                endQrScanner.clear();
            }

            endQrScanner = new Html5QrcodeScanner("end-qr-reader", {
                fps: 10,
                qrbox: 250,
                aspectRatio: 1.0,
                videoConstraints: {
                    facingMode: "environment"
                }
            });

            endQrScanner.render((decodedText) => {
                // Stop scanning
                endQrScanner.clear();

                // Process the scanned QR code
                resultDiv.innerHTML = '<div class="alert alert-success"><i class="material-symbols-rounded me-2">check_circle</i>QR Code scanned successfully!</div>';

                // Extract OTP from QR data (format: END_TRIP_TRIPID_TIMESTAMP_OTP)
                const parts = decodedText.split('_');
                if (parts.length >= 4 && parts[0] === 'END' && parts[1] === 'TRIP') {
                    const otp = parts[3];
                    document.getElementById('end_otp').value = otp;

                    // Auto-submit the form
                    setTimeout(() => {
                        document.querySelector('#end-otp-panel form').submit();
                    }, 1500);
                } else {
                    resultDiv.innerHTML = '<div class="alert alert-danger"><i class="material-symbols-rounded me-2">error</i>Invalid QR code format</div>';
                }
            }, (error) => {
                console.log('QR scan error:', error);
            });
        }

        function stopEndQRScanner() {
            if (endQrScanner) {
                endQrScanner.clear();
                endQrScanner = null;
                document.getElementById('end-qr-result').innerHTML = '<div class="alert alert-info"><i class="material-symbols-rounded me-2">info</i>Scanner stopped</div>';
            }
        }

        function switchEndCamera() {
            if (endQrScanner) {
                endQrScanner.clear();
                setTimeout(() => {
                    endQrScanner = new Html5QrcodeScanner("end-qr-reader", {
                        fps: 10,
                        qrbox: 250,
                        videoConstraints: {
                            facingMode: endQrScanner.getState().facingMode === "environment" ? "user" : "environment"
                        }
                    });
                    endQrScanner.render( /* same callback */ );
                }, 500);
            }
        }

        // Auto-format OTP input
        document.addEventListener('DOMContentLoaded', function() {
            const otpInputs = document.querySelectorAll('.otp-input');
            otpInputs.forEach(input => {
                input.addEventListener('input', function(e) {
                    // Remove non-numeric characters
                    this.value = this.value.replace(/\D/g, '');

                    // Limit to 6 digits
                    if (this.value.length > 6) {
                        this.value = this.value.slice(0, 6);
                    }
                });
            });

            // Initialize GPS tracking
            initLocationTracking();
        });

        // Cleanup on page unload
        window.addEventListener('beforeunload', function() {
            if (locationWatcher) {
                navigator.geolocation.clearWatch(locationWatcher);
            }
            if (qrScanner) {
                qrScanner.clear();
            }
            if (endQrScanner) {
                endQrScanner.clear();
            }
        });
    </script>
</body>

</html>
