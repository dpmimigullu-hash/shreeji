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

// Get client ID from URL
$client_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$edit_mode = isset($_GET['edit']) && $_GET['edit'] == '1';

if (!$client_id) {
    header('Location: clients.php');
    exit;
}

// Get client details
$conn = getDBConnection();
$stmt = $conn->prepare("SELECT * FROM clients WHERE id = ?");
$stmt->execute([$client_id]);
$client = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$client) {
    header('Location: clients.php');
    exit;
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['update_client'])) {
        $name = trim($_POST['name']);
        $email = trim($_POST['email']);
        $phone = isset($_POST['phone']) && !empty(trim($_POST['phone'])) ? trim($_POST['phone']) : '';
        $company = isset($_POST['company']) && !empty(trim($_POST['company'])) ? trim($_POST['company']) : '';
        $address = isset($_POST['address']) && !empty(trim($_POST['address'])) ? trim($_POST['address']) : '';
        $city = isset($_POST['city']) && !empty(trim($_POST['city'])) ? trim($_POST['city']) : '';
        $state = isset($_POST['state']) && !empty(trim($_POST['state'])) ? trim($_POST['state']) : '';
        $pincode = isset($_POST['pincode']) && !empty(trim($_POST['pincode'])) ? trim($_POST['pincode']) : '';
        $latitude = isset($_POST['latitude']) && !empty($_POST['latitude']) ? (float)$_POST['latitude'] : 0;
        $longitude = isset($_POST['longitude']) && !empty($_POST['longitude']) ? (float)$_POST['longitude'] : 0;

        $conn = getDBConnection();
        $conn->beginTransaction();

        try {
            $stmt = $conn->prepare("UPDATE clients SET name = ?, email = ?, phone = ?, company = ?, address = ?, city = ?, state = ?, pincode = ?, latitude = ?, longitude = ? WHERE id = ?");
            $stmt->execute([$name, $email, $phone, $company, $address, $city, $state, $pincode, $latitude, $longitude, $client_id]);

            $conn->commit();
            $success_message = "Client updated successfully!";

            // Refresh client data
            $stmt = $conn->prepare("SELECT * FROM clients WHERE id = ?");
            $stmt->execute([$client_id]);
            $client = $stmt->fetch(PDO::FETCH_ASSOC);
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
    <title>Client Details - MS Infosystems Employee Transportation System</title>
    <link rel="stylesheet" type="text/css" href="https://fonts.googleapis.com/css?family=Inter:300,400,500,600,700,900" />
    <link href="./assets/nucleo-icons.css" rel="stylesheet" />
    <link href="./assets/nucleo-svg.css" rel="stylesheet" />
    <script src="https://kit.fontawesome.com/42d5adcbca.js" crossorigin="anonymous"></script>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded:opsz,wght,FILL,GRAD@24,400,0,0" />
    <link id="pagestyle" href="./assets/material-dashboard.css?v=3.2.0" rel="stylesheet" />
    <script src="https://maps.googleapis.com/maps/api/js?key=AIzaSyAUnYgTLEnIHwXCE1A98gAGFXBrvQEv8aI&libraries=places&callback=initGoogleMaps" async defer></script>
    <style>
        .ola-uber-input {
            border-radius: 8px !important;
            border: 2px solid #e0e0e0 !important;
            padding: 12px 16px !important;
            font-size: 16px !important;
            transition: all 0.2s ease !important;
            background: white !important;
        }

        .ola-uber-input:focus {
            border-color: #1976d2 !important;
            box-shadow: 0 0 0 3px rgba(25, 118, 210, 0.1) !important;
            outline: none !important;
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
                <li class="nav-item">
                    <a class="nav-link text-dark" href="branches.php">
                        <i class="material-symbols-rounded opacity-5">business</i>
                        <span class="nav-link-text ms-1">Branches</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link active bg-gradient-dark text-white" href="clients.php">
                        <i class="material-symbols-rounded opacity-5">business_center</i>
                        <span class="nav-link-text ms-1">Clients</span>
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
                                    <h6>Client Information</h6>
                                    <p class="text-sm mb-0"><?php echo $edit_mode ? 'Edit client details.' : 'View client details.'; ?></p>
                                </div>
                                <div>
                                    <?php if (!$edit_mode): ?>
                                        <a href="client_details.php?id=<?php echo $client_id; ?>&edit=1" class="btn btn-primary btn-sm me-2">
                                            <i class="fas fa-edit"></i> Edit
                                        </a>
                                    <?php endif; ?>
                                    <a href="clients.php" class="btn btn-outline-primary btn-sm">
                                        <i class="fas fa-arrow-left"></i> Back to Clients
                                    </a>
                                </div>
                            </div>
                        </div>
                        <div class="card-body p-3">
                            <form method="POST" action="" enctype="multipart/form-data">
                                <div class="row">
                                    <!-- Client Information Section -->
                                    <div class="col-lg-6">
                                        <div class="card h-100">
                                            <div class="card-header pb-0 p-3">
                                                <h6 class="mb-0">Client Information</h6>
                                            </div>
                                            <div class="card-body p-3">
                                                <!-- Account Information Box -->
                                                <div class="border rounded p-3 mb-3 bg-light">
                                                    <h6 class="text-primary mb-3"><i class="fas fa-user-tie"></i> Client Information</h6>
                                                    <div class="form-group">
                                                        <label for="name" class="form-control-label">Client Name</label>
                                                        <div class="border rounded p-2 bg-white">
                                                            <input class="form-control border-0 bg-transparent" type="text" id="name" name="name" value="<?php echo htmlspecialchars($client['name']); ?>" readonly>
                                                        </div>
                                                    </div>
                                                </div>

                                                <?php if ($edit_mode): ?>
                                                    <!-- Personal Information Box -->
                                                    <div class="border rounded p-3 mb-3 bg-light">
                                                        <h6 class="text-primary mb-3"><i class="fas fa-id-card"></i> Contact Information</h6>
                                                        <div class="form-group">
                                                            <label for="email" class="form-control-label">Email</label>
                                                            <div class="border rounded p-2 bg-white">
                                                                <input class="form-control border-0 bg-transparent" type="email" id="email" name="email" value="<?php echo htmlspecialchars($client['email']); ?>">
                                                            </div>
                                                        </div>
                                                        <div class="form-group">
                                                            <label for="phone" class="form-control-label">Phone</label>
                                                            <div class="border rounded p-2 bg-white">
                                                                <input class="form-control border-0 bg-transparent" type="tel" id="phone" name="phone" value="<?php echo htmlspecialchars($client['phone'] ?: ''); ?>">
                                                            </div>
                                                        </div>
                                                        <div class="form-group">
                                                            <label for="company" class="form-control-label">Company</label>
                                                            <div class="border rounded p-2 bg-white">
                                                                <input class="form-control border-0 bg-transparent" type="text" id="company" name="company" value="<?php echo htmlspecialchars($client['company'] ?: ''); ?>">
                                                            </div>
                                                        </div>
                                                    </div>
                                                <?php else: ?>
                                                    <!-- View Mode - Display Information -->
                                                    <div class="border rounded p-3 mb-3 bg-light">
                                                        <h6 class="text-primary mb-3"><i class="fas fa-id-card"></i> Contact Information</h6>
                                                        <div class="row">
                                                            <div class="col-md-6">
                                                                <p><strong>Email:</strong> <?php echo htmlspecialchars($client['email'] ?: 'N/A'); ?></p>
                                                                <p><strong>Phone:</strong> <?php echo htmlspecialchars($client['phone'] ?: 'N/A'); ?></p>
                                                            </div>
                                                            <div class="col-md-6">
                                                                <p><strong>Company:</strong> <?php echo htmlspecialchars($client['company'] ?: 'N/A'); ?></p>
                                                            </div>
                                                        </div>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Address & Location Section -->
                                    <div class="col-lg-6">
                                        <div class="card h-100">
                                            <div class="card-header pb-0 p-3">
                                                <h6 class="mb-0">Address & Location</h6>
                                            </div>
                                            <div class="card-body p-3">
                                                <?php if ($edit_mode): ?>
                                                    <!-- Address Information Box -->
                                                    <div class="border rounded p-3 mb-3 bg-light">
                                                        <h6 class="text-success mb-3"><i class="fas fa-map-marked-alt"></i> Address Information</h6>
                                                        <div class="row">
                                                            <div class="col-md-12">
                                                                <div class="form-group">
                                                                    <label for="address_search" class="form-control-label">Search Address</label>
                                                                    <div class="border rounded p-2 bg-white">
                                                                        <div class="input-group">
                                                                            <input class="form-control border-0 bg-transparent ola-uber-input" type="text" id="address_search" placeholder="Type address to search">
                                                                            <button class="btn btn-outline-secondary" type="button" onclick="searchAddress()">
                                                                                <i class="fas fa-search"></i> Search
                                                                            </button>
                                                                        </div>
                                                                    </div>
                                                                    <small class="form-text text-muted">Search for an address to auto-fill coordinates</small>
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <div class="row">
                                                            <div class="col-md-12">
                                                                <div class="form-group">
                                                                    <label for="address" class="form-control-label">Full Address</label>
                                                                    <div class="border rounded p-2 bg-white">
                                                                        <textarea class="form-control border-0 bg-transparent" id="address" name="address" rows="2"><?php echo htmlspecialchars($client['address']); ?></textarea>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <div class="row">
                                                            <div class="col-md-4">
                                                                <div class="form-group">
                                                                    <label for="city" class="form-control-label">City</label>
                                                                    <div class="border rounded p-2 bg-white">
                                                                        <input class="form-control border-0 bg-transparent" type="text" id="city" name="city" value="<?php echo htmlspecialchars($client['city']); ?>">
                                                                    </div>
                                                                </div>
                                                            </div>
                                                            <div class="col-md-4">
                                                                <div class="form-group">
                                                                    <label for="state" class="form-control-label">State</label>
                                                                    <div class="border rounded p-2 bg-white">
                                                                        <input class="form-control border-0 bg-transparent" type="text" id="state" name="state" value="<?php echo htmlspecialchars($client['state']); ?>">
                                                                    </div>
                                                                </div>
                                                            </div>
                                                            <div class="col-md-4">
                                                                <div class="form-group">
                                                                    <label for="pincode" class="form-control-label">Pincode</label>
                                                                    <div class="border rounded p-2 bg-white">
                                                                        <input class="form-control border-0 bg-transparent" type="text" id="pincode" name="pincode" value="<?php echo htmlspecialchars($client['pincode']); ?>">
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <div class="row">
                                                            <div class="col-md-6">
                                                                <div class="form-group">
                                                                    <label for="latitude" class="form-control-label">Latitude</label>
                                                                    <div class="border rounded p-2 bg-white">
                                                                        <input class="form-control border-0 bg-transparent" type="number" id="latitude" name="latitude" step="0.000001" value="<?php echo htmlspecialchars($client['latitude']); ?>" readonly>
                                                                    </div>
                                                                    <small class="form-text text-muted">Auto-filled from address search</small>
                                                                </div>
                                                            </div>
                                                            <div class="col-md-6">
                                                                <div class="form-group">
                                                                    <label for="longitude" class="form-control-label">Longitude</label>
                                                                    <div class="border rounded p-2 bg-white">
                                                                        <input class="form-control border-0 bg-transparent" type="number" id="longitude" name="longitude" step="0.000001" value="<?php echo htmlspecialchars($client['longitude']); ?>" readonly>
                                                                    </div>
                                                                    <small class="form-text text-muted">Auto-filled from address search</small>
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <div class="row">
                                                            <div class="col-md-12">
                                                                <div id="map" style="height: 300px; margin-top: 10px;"></div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                <?php else: ?>
                                                    <!-- View Mode - Display Address -->
                                                    <div class="border rounded p-3 mb-3 bg-light">
                                                        <h6 class="text-success mb-3"><i class="fas fa-map-marked-alt"></i> Address Information</h6>
                                                        <div class="row">
                                                            <div class="col-md-12">
                                                                <p><strong>Full Address:</strong> <?php echo htmlspecialchars($client['address']); ?></p>
                                                                <p><strong>City:</strong> <?php echo htmlspecialchars($client['city']); ?>, <strong>State:</strong> <?php echo htmlspecialchars($client['state']); ?> - <strong>Pincode:</strong> <?php echo htmlspecialchars($client['pincode']); ?></p>
                                                            </div>
                                                        </div>
                                                        <div class="row">
                                                            <div class="col-md-6">
                                                                <p><strong>Latitude:</strong> <?php echo htmlspecialchars($client['latitude']); ?></p>
                                                            </div>
                                                            <div class="col-md-6">
                                                                <p><strong>Longitude:</strong> <?php echo htmlspecialchars($client['longitude']); ?></p>
                                                            </div>
                                                        </div>
                                                        <div class="row">
                                                            <div class="col-md-12">
                                                                <div id="map" style="height: 300px; margin-top: 10px;"></div>
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
                                            <button type="submit" name="update_client" class="btn bg-gradient-dark">
                                                <i class="fas fa-save"></i> Update Client
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
        let map;
        let marker;
        let geocoder;
        let autocomplete;

        function initGoogleMaps() {
            // Use client coordinates if available, otherwise default to India
            const clientLat = <?php echo $client['latitude'] ?: 20.5937; ?>;
            const clientLng = <?php echo $client['longitude'] ?: 78.9629; ?>;
            const clientLocation = {
                lat: clientLat,
                lng: clientLng
            };

            map = new google.maps.Map(document.getElementById('map'), {
                zoom: 15,
                center: clientLocation,
                mapTypeControl: false,
                streetViewControl: false,
                fullscreenControl: false
            });

            geocoder = new google.maps.Geocoder();

            // Place marker at client location
            placeMarker(new google.maps.LatLng(clientLat, clientLng));

            // Add click listener to place marker
            map.addListener('click', function(event) {
                placeMarker(event.latLng);
            });

            // Initialize Places Autocomplete
            const input = document.getElementById('address_search');
            if (input) {
                autocomplete = new google.maps.places.Autocomplete(input, {
                    componentRestrictions: {
                        country: 'in'
                    },
                    fields: ['formatted_address', 'geometry', 'place_id']
                });

                autocomplete.addListener('place_changed', function() {
                    const place = autocomplete.getPlace();
                    if (!place.geometry) {
                        console.log("No details available for input: '" + place.name + "'");
                        return;
                    }

                    // If the place has a geometry, then present it on a map.
                    if (place.geometry.viewport) {
                        map.fitBounds(place.geometry.viewport);
                    } else {
                        map.setCenter(place.geometry.location);
                        map.setZoom(17);
                    }

                    placeMarker(place.geometry.location);

                    // Auto-fill address fields
                    fillAddressFields(place);
                });
            }
        }

        function searchAddress() {
            const address = document.getElementById('address_search').value;
            if (!address) {
                alert('Please enter an address');
                return;
            }

            geocoder.geocode({
                address: address
            }, function(results, status) {
                if (status === 'OK') {
                    const location = results[0].geometry.location;
                    map.setCenter(location);
                    map.setZoom(15);
                    placeMarker(location);
                    fillAddressFields(results[0]);
                } else {
                    alert('Address not found: ' + status);
                }
            });
        }

        function placeMarker(location) {
            // Remove existing marker
            if (marker) {
                marker.setMap(null);
            }

            // Create new marker
            marker = new google.maps.Marker({
                position: location,
                map: map,
                draggable: true
            });

            // Update form fields
            document.getElementById('latitude').value = location.lat();
            document.getElementById('longitude').value = location.lng();

            // Add drag listener to update coordinates
            marker.addListener('dragend', function(event) {
                document.getElementById('latitude').value = event.latLng.lat();
                document.getElementById('longitude').value = event.latLng.lng();
            });
        }

        function fillAddressFields(place) {
            // Extract address components
            const addressComponents = place.address_components;
            let streetNumber = '';
            let streetName = '';
            let city = '';
            let state = '';
            let postalCode = '';

            addressComponents.forEach(component => {
                const types = component.types;
                if (types.includes('street_number')) {
                    streetNumber = component.long_name;
                }
                if (types.includes('route')) {
                    streetName = component.long_name;
                }
                if (types.includes('locality') || types.includes('administrative_area_level_2')) {
                    city = component.long_name;
                }
                if (types.includes('administrative_area_level_1')) {
                    state = component.long_name;
                }
                if (types.includes('postal_code')) {
                    postalCode = component.long_name;
                }
            });

            // Fill form fields
            const fullAddress = [streetNumber, streetName].filter(Boolean).join(' ') || place.formatted_address.split(',')[0];
            document.getElementById('address').value = fullAddress;
            document.getElementById('city').value = city;
            document.getElementById('state').value = state;
            document.getElementById('pincode').value = postalCode;
        }

        // Initialize when page loads
        document.addEventListener('DOMContentLoaded', function() {
            // Focus on first editable field if in edit mode
            <?php if ($edit_mode): ?>
                const firstField = document.querySelector('input:not([readonly]):not([type="hidden"])');
                if (firstField) firstField.focus();
            <?php endif; ?>
        });

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
