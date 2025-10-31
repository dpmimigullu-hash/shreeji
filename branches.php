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

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['add_branch'])) {
        $name = trim($_POST['name']);
        $address = trim($_POST['address']);
        $city = trim($_POST['city']);
        $state = trim($_POST['state']);
        $pincode = trim($_POST['pincode']);
        $phone = trim($_POST['phone']);
        $email = trim($_POST['email']);
        $latitude = (float)$_POST['latitude'];
        $longitude = (float)$_POST['longitude'];

        $conn = getDBConnection();
        try {
            $stmt = $conn->prepare("INSERT INTO branches (name, address, city, state, pincode, phone, email, latitude, longitude) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$name, $address, $city, $state, $pincode, $phone, $email, $latitude, $longitude]);
        } catch (PDOException $e) {
            // If branches table doesn't exist, show error message
            $error_message = "Database table not found. Please run the migration script first.";
        }
    }
}

// Get all branches
$conn = getDBConnection();
try {
    $stmt = $conn->query("SELECT * FROM branches ORDER BY name");
    $branches = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // If branches table doesn't exist yet, show empty array
    $branches = [];
}

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=5.0, user-scalable=yes">
    <link rel="apple-touch-icon" sizes="76x76" href="./assets/img/apple-icon.png">
    <link rel="icon" type="image/png" href="./assets/img/favicon.png">
    <title>Branch Management - MS Infosystems Employee Transportation System</title>
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
                    <a class="nav-link active bg-gradient-dark text-white" href="branches.php">
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
                    <ol class="breadcrumb bg-transparent mb-0 pb-0 pt-1 px-0 me-sm-6 me-5">
                        <li class="breadcrumb-item text-sm"><a class="opacity-5 text-dark" href="javascript:;">Pages</a></li>
                        <li class="breadcrumb-item text-sm text-dark active" aria-current="page">Branches</li>
                    </ol>
                    <h6 class="font-weight-bolder mb-0">Branch Management</h6>
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
                            <h6>Add New Branch</h6>
                            <p class="text-sm mb-0">Create a new branch/office location.</p>
                        </div>
                        <div class="card-body p-3">
                            <form method="POST" action="">
                                <div class="border rounded p-3 mb-3 bg-light">
                                    <h6 class="text-primary mb-3"><i class="fas fa-building"></i> Branch Information</h6>
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="name" class="form-control-label">Branch Name *</label>
                                                <div class="border rounded p-2 bg-white">
                                                    <input class="form-control border-0 bg-transparent" type="text" id="name" name="name" placeholder="e.g., Mumbai Head Office" required>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="email" class="form-control-label">Email</label>
                                                <div class="border rounded p-2 bg-white">
                                                    <input class="form-control border-0 bg-transparent" type="email" id="email" name="email" placeholder="branch@mumbai.com">
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="row">
                                        <div class="col-md-12">
                                            <div class="form-group">
                                                <label for="address" class="form-control-label">Address *</label>
                                                <div class="border rounded p-2 bg-white">
                                                    <textarea class="form-control border-0 bg-transparent" id="address" name="address" rows="2" placeholder="Complete office address" required></textarea>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="row">
                                        <div class="col-md-4">
                                            <div class="form-group">
                                                <label for="city" class="form-control-label">City *</label>
                                                <div class="border rounded p-2 bg-white">
                                                    <input class="form-control border-0 bg-transparent" type="text" id="city" name="city" placeholder="Mumbai" required>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="form-group">
                                                <label for="state" class="form-control-label">State *</label>
                                                <div class="border rounded p-2 bg-white">
                                                    <input class="form-control border-0 bg-transparent" type="text" id="state" name="state" placeholder="Maharashtra" required>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="form-group">
                                                <label for="pincode" class="form-control-label">Pincode *</label>
                                                <div class="border rounded p-2 bg-white">
                                                    <input class="form-control border-0 bg-transparent" type="text" id="pincode" name="pincode" placeholder="400001" required>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="phone" class="form-control-label">Phone</label>
                                                <div class="border rounded p-2 bg-white">
                                                    <input class="form-control border-0 bg-transparent" type="tel" id="phone" name="phone" placeholder="+91-22-12345678">
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="border rounded p-3 mb-3 bg-light">
                                    <h6 class="text-success mb-3"><i class="fas fa-map-marked-alt"></i> Location Setup</h6>
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="location_search" class="form-control-label">Search Location on Map *</label>
                                                <div class="border rounded p-2 bg-white">
                                                    <div class="input-group">
                                                        <input class="form-control border-0 bg-transparent" type="text" id="location_search" placeholder="Type location name (e.g., Mumbai, Delhi)">
                                                        <button class="btn btn-outline-secondary" type="button" onclick="searchLocation()">
                                                            <i class="fas fa-search"></i> Search
                                                        </button>
                                                    </div>
                                                </div>
                                                <small class="form-text text-muted">Search for a location to auto-fill coordinates</small>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label class="form-control-label">Map Preview *</label>
                                                <div class="border rounded p-2 bg-white">
                                                    <div id="map" style="height: 200px; border: 1px solid #ddd; border-radius: 4px;"></div>
                                                </div>
                                                <small class="form-text text-muted">Click on map or search to set location</small>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="latitude" class="form-control-label">Latitude *</label>
                                                <div class="border rounded p-2 bg-white">
                                                    <input class="form-control border-0 bg-transparent" type="number" id="latitude" name="latitude" step="0.000001" readonly required>
                                                </div>
                                                <small class="form-text text-muted">Auto-filled from map selection</small>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="longitude" class="form-control-label">Longitude *</label>
                                                <div class="border rounded p-2 bg-white">
                                                    <input class="form-control border-0 bg-transparent" type="number" id="longitude" name="longitude" step="0.000001" readonly required>
                                                </div>
                                                <small class="form-text text-muted">Auto-filled from map selection</small>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="row mt-3">
                                    <div class="col-12">
                                        <button type="submit" name="add_branch" class="btn bg-gradient-dark">
                                            <i class="fas fa-plus"></i> Create Branch
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
                            <h6>Branch List</h6>
                        </div>
                        <div class="card-body px-0 pb-2">
                            <div class="table-responsive">
                                <table class="table align-items-center mb-0">
                                    <thead>
                                        <tr>
                                            <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">ID</th>
                                            <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">Branch Name</th>
                                            <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">Location</th>
                                            <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">Contact</th>
                                            <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">Status</th>
                                            <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($branches as $branch): ?>
                                            <tr>
                                                <td>
                                                    <p class="text-xs font-weight-bold mb-0"><?php echo $branch['id']; ?></p>
                                                </td>
                                                <td>
                                                    <div class="d-flex px-2 py-1">
                                                        <div>
                                                            <i class="material-symbols-rounded text-secondary">business</i>
                                                        </div>
                                                        <div class="d-flex flex-column justify-content-center">
                                                            <h6 class="mb-0 text-sm"><?php echo htmlspecialchars($branch['name']); ?></h6>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td>
                                                    <p class="text-xs font-weight-bold mb-0"><?php echo htmlspecialchars($branch['city'] . ', ' . $branch['state']); ?></p>
                                                    <p class="text-xs text-secondary mb-0"><?php echo htmlspecialchars($branch['pincode']); ?></p>
                                                </td>
                                                <td>
                                                    <p class="text-xs font-weight-bold mb-0"><?php echo htmlspecialchars($branch['phone']); ?></p>
                                                    <p class="text-xs text-secondary mb-0"><?php echo htmlspecialchars($branch['email']); ?></p>
                                                </td>
                                                <td>
                                                    <span class="badge badge-sm bg-gradient-<?php echo $branch['status'] == 'active' ? 'success' : 'secondary'; ?>">
                                                        <?php echo ucfirst($branch['status']); ?>
                                                    </span>
                                                </td>
                                                <td class="align-middle">
                                                    <a href="branch_details.php?id=<?php echo $branch['id']; ?>" class="text-secondary font-weight-bold text-xs" data-toggle="tooltip" data-original-title="View Details">
                                                        View
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

    <!-- Google Maps API - Replace YOUR_API_KEY with actual Google Maps API key -->
    <script src="https://maps.googleapis.com/maps/api/js?key=AIzaSyAUnYgTLEnIHwXCE1A98gAGFXBrvQEv8aI&libraries=places"></script>

    <script src="./assets/js/core/popper.min.js"></script>
    <script src="./assets/js/core/bootstrap.min.js"></script>
    <script src="./assets/js/plugins/perfect-scrollbar.min.js"></script>
    <script src="./assets/js/plugins/smooth-scrollbar.min.js"></script>
    <script src="./assets/js/plugins/chartjs.min.js"></script>

    <script>
        let map;
        let marker;
        let geocoder;

        function initMap() {
            // Default center (India)
            const defaultLocation = {
                lat: 20.5937,
                lng: 78.9629
            };

            map = new google.maps.Map(document.getElementById('map'), {
                zoom: 5,
                center: defaultLocation,
                mapTypeControl: false,
                streetViewControl: false,
                fullscreenControl: false
            });

            geocoder = new google.maps.Geocoder();

            // Add click listener to place marker
            map.addListener('click', function(event) {
                placeMarker(event.latLng);
            });

            // Initialize Places Autocomplete
            const input = document.getElementById('location_search');
            const autocomplete = new google.maps.places.Autocomplete(input);
            autocomplete.bindTo('bounds', map);

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
            });
        }

        function searchLocation() {
            const address = document.getElementById('location_search').value;
            if (!address) {
                alert('Please enter a location name');
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
                } else {
                    alert('Location not found: ' + status);
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

        // Initialize map when page loads
        google.maps.event.addDomListener(window, 'load', initMap);

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

