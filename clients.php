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

// Only admin can access clients
if ($role != 'admin') {
    header('Location: index.php');
    exit;
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['add_client'])) {
        $name = trim($_POST['name']);
        $email = trim($_POST['email']);
        $phone = trim($_POST['phone']);
        $company = trim($_POST['company']);
        $address = trim($_POST['address']);
        $city = trim($_POST['city']);
        $state = trim($_POST['state']);
        $pincode = trim($_POST['pincode']);
        $latitude = (float)$_POST['latitude'];
        $longitude = (float)$_POST['longitude'];

        $conn = getDBConnection();
        try {
            $stmt = $conn->prepare("INSERT INTO clients (name, email, phone, company, address, city, state, pincode, latitude, longitude) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$name, $email, $phone, $company, $address, $city, $state, $pincode, $latitude, $longitude]);
            $success_message = "Client added successfully!";
        } catch (PDOException $e) {
            $error_message = "Error adding client: " . $e->getMessage();
        }
    } elseif (isset($_POST['add_demo_client'])) {
        // Add demo client
        $conn = getDBConnection();
        try {
            $stmt = $conn->prepare("INSERT INTO clients (name, email, phone, company, address, city, state, pincode, latitude, longitude) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                'Demo Client',
                'demo@client.com',
                '+91-9876543210',
                'Demo Corporation',
                '123 Demo Street, Business District',
                'Mumbai',
                'Maharashtra',
                '400001',
                19.0760,
                72.8777
            ]);
            $success_message = "Demo client added successfully!";
        } catch (PDOException $e) {
            $error_message = "Error adding demo client: " . $e->getMessage();
        }
    }
}

// Get all clients
$conn = getDBConnection();
try {
    $stmt = $conn->query("SELECT * FROM clients ORDER BY name");
    $clients = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $clients = [];
    $error_message = "Database error: " . $e->getMessage();
}

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=5.0, user-scalable=yes">
    <link rel="apple-touch-icon" sizes="76x76" href="./assets/img/apple-icon.png">
    <link rel="icon" type="image/png" href="./assets/img/favicon.png">
    <title>Client Management - MS Infosystems Employee Transportation System</title>
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
                    <ol class="breadcrumb bg-transparent mb-0 pb-0 pt-1 px-0 me-sm-6 me-5">
                        <li class="breadcrumb-item text-sm"><a class="opacity-5 text-dark" href="javascript:;">Pages</a></li>
                        <li class="breadcrumb-item text-sm text-dark active" aria-current="page">Clients</li>
                    </ol>
                    <h6 class="font-weight-bolder mb-0">Client Management</h6>
                </nav>
            </div>
        </nav>

        <div class="container-fluid py-2">
            <?php if (isset($success_message)): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <strong>Success!</strong> <?php echo $success_message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if (isset($error_message)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <strong>Error!</strong> <?php echo $error_message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <div class="row">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header pb-0 p-3">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6>Add New Client</h6>
                                    <p class="text-sm mb-0">Create a new client profile for billing purposes.</p>
                                </div>
                            </div>
                        </div>
                        <div class="card-body p-3">
                            <form method="POST" action="">
                                <div class="border rounded p-3 mb-3 bg-light">
                                    <h6 class="text-primary mb-3"><i class="fas fa-user-tie"></i> Client Information</h6>
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="name" class="form-control-label">Client Name *</label>
                                                <div class="border rounded p-2 bg-white">
                                                    <input class="form-control border-0 bg-transparent" type="text" id="name" name="name" placeholder="John Doe" required>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="email" class="form-control-label">Email</label>
                                                <div class="border rounded p-2 bg-white">
                                                    <input class="form-control border-0 bg-transparent" type="email" id="email" name="email" placeholder="john@company.com">
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="phone" class="form-control-label">Phone</label>
                                                <div class="border rounded p-2 bg-white">
                                                    <input class="form-control border-0 bg-transparent" type="tel" id="phone" name="phone" placeholder="+91-9876543210">
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="company" class="form-control-label">Company</label>
                                                <div class="border rounded p-2 bg-white">
                                                    <input class="form-control border-0 bg-transparent" type="text" id="company" name="company" placeholder="ABC Corporation">
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="border rounded p-3 mb-3 bg-light">
                                    <h6 class="text-success mb-3"><i class="fas fa-map-marked-alt"></i> Address Information</h6>
                                    <div class="row">
                                        <div class="col-md-12">
                                            <div class="form-group">
                                                <label for="address_search" class="form-control-label">Search Address *</label>
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
                                                <label for="address" class="form-control-label">Full Address *</label>
                                                <div class="border rounded p-2 bg-white">
                                                    <textarea class="form-control border-0 bg-transparent" id="address" name="address" rows="2" placeholder="Complete address" required></textarea>
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
                                                <label for="latitude" class="form-control-label">Latitude *</label>
                                                <div class="border rounded p-2 bg-white">
                                                    <input class="form-control border-0 bg-transparent" type="number" id="latitude" name="latitude" step="0.000001" readonly required>
                                                </div>
                                                <small class="form-text text-muted">Auto-filled from address search</small>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="longitude" class="form-control-label">Longitude *</label>
                                                <div class="border rounded p-2 bg-white">
                                                    <input class="form-control border-0 bg-transparent" type="number" id="longitude" name="longitude" step="0.000001" readonly required>
                                                </div>
                                                <small class="form-text text-muted">Auto-filled from address search</small>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="row">
                                        <div class="col-md-12">
                                            <div id="map" style="height: 400px; margin-top: 10px;"></div>
                                        </div>
                                    </div>
                                </div>

                                <div class="row mt-3">
                                    <div class="col-12 d-flex justify-content-between">
                                        <button type="submit" name="add_client" class="btn bg-gradient-dark">
                                            <i class="fas fa-plus"></i> Add Client
                                        </button>
                                        <button type="submit" name="add_demo_client" class="btn btn-outline-info">
                                            <i class="fas fa-magic me-1"></i> Register Demo Client
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
                            <h6>Client List</h6>
                        </div>
                        <div class="card-body px-0 pb-2">
                            <div class="table-responsive">
                                <table class="table align-items-center mb-0">
                                    <thead>
                                        <tr>
                                            <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">ID</th>
                                            <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">Client Name</th>
                                            <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">Company</th>
                                            <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">Contact</th>
                                            <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">Location</th>
                                            <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($clients as $client): ?>
                                            <tr>
                                                <td>
                                                    <p class="text-xs font-weight-bold mb-0"><?php echo $client['id']; ?></p>
                                                </td>
                                                <td>
                                                    <div class="d-flex px-2 py-1">
                                                        <div>
                                                            <i class="material-symbols-rounded text-secondary">person</i>
                                                        </div>
                                                        <div class="d-flex flex-column justify-content-center">
                                                            <h6 class="mb-0 text-sm"><?php echo htmlspecialchars($client['name']); ?></h6>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td>
                                                    <p class="text-xs font-weight-bold mb-0"><?php echo htmlspecialchars($client['company'] ?? 'N/A'); ?></p>
                                                </td>
                                                <td>
                                                    <p class="text-xs font-weight-bold mb-0"><?php echo htmlspecialchars($client['phone']); ?></p>
                                                    <p class="text-xs text-secondary mb-0"><?php echo htmlspecialchars($client['email']); ?></p>
                                                </td>
                                                <td>
                                                    <p class="text-xs font-weight-bold mb-0"><?php echo htmlspecialchars($client['city'] . ', ' . $client['state']); ?></p>
                                                    <p class="text-xs text-secondary mb-0"><?php echo htmlspecialchars($client['pincode']); ?></p>
                                                </td>
                                                <td class="align-middle">
                                                    <a href="client_details.php?id=<?php echo $client['id']; ?>" class="text-secondary font-weight-bold text-xs" data-toggle="tooltip" data-original-title="View Details">
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
            const input = document.getElementById('address_search');
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
            // Focus on name field
            document.getElementById('name').focus();
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
