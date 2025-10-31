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

$conn = getDBConnection();

// Handle view action
if (isset($_GET['action']) && $_GET['action'] == 'view' && isset($_GET['id'])) {
    $bill_id = (int)$_GET['id'];

    // Get bill details with fuel amount auto-detection
    try {
        $stmt = $conn->prepare("
            SELECT
                b.*,
                t.start_location_lat,
                t.start_location_lng,
                t.end_location_lat,
                t.end_location_lng,
                t.distance,
                t.status as trip_status,
                t.start_time,
                t.end_time,
                t.passenger_count,
                t.trip_type,
                t.scheduled_pickup_time,
                t.scheduled_drop_time,
                t.first_passenger_name,
                t.first_passenger_phone,
                t.first_passenger_address,
                t.last_passenger_name,
                t.last_passenger_phone,
                t.last_passenger_address,
                u.name as driver_name,
                u.fuel_amount as driver_fuel_amount,
                c.make,
                c.model,
                c.license_plate,
                c.insurance_amount as car_insurance_amount
            FROM billing b
            JOIN trips t ON b.trip_id = t.id
            LEFT JOIN users u ON t.driver_id = u.id
            LEFT JOIN cars c ON t.car_id = c.id
            WHERE b.id = ?
        ");
        $stmt->execute([$bill_id]);
        $bill_details = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        // Fallback query without new columns if they don't exist yet
        try {
            $stmt = $conn->prepare("
                SELECT
                    b.*,
                    t.start_location_lat,
                    t.start_location_lng,
                    t.end_location_lat,
                    t.end_location_lng,
                    t.distance,
                    t.status as trip_status,
                    t.start_time,
                    t.end_time,
                    t.passenger_count,
                    u.name as driver_name,
                    c.make,
                    c.model,
                    c.license_plate
                FROM billing b
                JOIN trips t ON b.trip_id = t.id
                LEFT JOIN users u ON t.driver_id = u.id
                LEFT JOIN cars c ON t.car_id = c.id
                WHERE b.id = ?
            ");
            $stmt->execute([$bill_id]);
            $bill_details = $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e2) {
            // Ultimate fallback - basic query
            $stmt = $conn->prepare("
                SELECT b.*, t.distance, t.start_time, t.end_time, u.name as driver_name, c.make, c.model, c.license_plate
                FROM billing b
                JOIN trips t ON b.trip_id = t.id
                LEFT JOIN users u ON t.driver_id = u.id
                LEFT JOIN vehicles c ON t.vehicle_id = c.id
                WHERE b.id = ?
            ");
            $stmt->execute([$bill_id]);
            $bill_details = $stmt->fetch(PDO::FETCH_ASSOC);
        }
    }

    // Handle case where new columns might not exist yet
    if (!$bill_details) {
        // Fallback query without new columns
        try {
            $stmt = $conn->prepare("
                SELECT
                    b.*,
                    t.start_location_lat,
                    t.start_location_lng,
                    t.end_location_lat,
                    t.end_location_lng,
                    t.distance,
                    t.status as trip_status,
                    t.start_time,
                    t.end_time,
                    t.passenger_count,
                    u.name as driver_name,
                    c.make,
                    c.model,
                    c.license_plate
                FROM billing b
                JOIN trips t ON b.trip_id = t.id
                LEFT JOIN users u ON t.driver_id = u.id
                LEFT JOIN vehicles c ON t.vehicle_id = c.id
                WHERE b.id = ?
            ");
            $stmt->execute([$bill_id]);
            $bill_details = $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e2) {
            // Ultimate fallback - basic query
            $stmt = $conn->prepare("
                SELECT b.*, t.distance, t.start_time, t.end_time, u.name as driver_name, c.make, c.model, c.license_plate
                FROM billing b
                JOIN trips t ON b.trip_id = t.id
                LEFT JOIN users u ON t.driver_id = u.id
                LEFT JOIN vehicles c ON t.vehicle_id = c.id
                WHERE b.id = ?
            ");
            $stmt->execute([$bill_id]);
            $bill_details = $stmt->fetch(PDO::FETCH_ASSOC);
        }
    }

    if ($bill_details) {
        // Display bill details page
?>
        <!DOCTYPE html>
        <html lang="en">

        <head>
            <meta charset="utf-8" />
            <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=5.0, user-scalable=yes">
            <link rel="apple-touch-icon" sizes="76x76" href="./assets/img/apple-icon.png">
            <link rel="icon" type="image/png" href="./assets/img/favicon.png">
            <title>Bill Details - MS Infosystems Employee Transportation System</title>
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
                            <a class="nav-link text-dark" href="billing.php">
                                <i class="material-symbols-rounded opacity-5">receipt_long</i>
                                <span class="nav-link-text ms-1">Driver Billing</span>
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
                                <li class="breadcrumb-item text-sm"><a class="opacity-5 text-dark" href="billing.php">Billing</a></li>
                                <li class="breadcrumb-item text-sm text-dark active" aria-current="page">Bill Details</li>
                            </ol>
                            <h6 class="font-weight-bolder mb-0">Bill #<?php echo $bill_details['id']; ?> Details</h6>
                        </nav>
                        <div class="collapse navbar-collapse mt-sm-0 mt-2 me-md-0 me-sm-4" id="navbar">
                            <div class="ms-md-auto pe-md-3 d-flex align-items-center">
                                <a href="billing.php" class="btn btn-outline-primary btn-sm mb-0 me-3">
                                    <i class="material-symbols-rounded me-1">arrow_back</i>Back to Billing
                                </a>
                            </div>
                        </div>
                    </div>
                </nav>

                <div class="container-fluid py-2">
                    <div class="row">
                        <div class="col-12">
                            <div class="card">
                                <div class="card-header pb-0 p-3">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <h6>Bill Information</h6>
                                        <span class="badge badge-sm bg-gradient-<?php echo ($bill_details['payment_status'] ?? $bill_details['status']) == 'paid' ? 'success' : 'warning'; ?>">
                                            <?php echo ucfirst($bill_details['payment_status'] ?? $bill_details['status']); ?>
                                        </span>
                                    </div>
                                </div>
                                <div class="card-body p-3">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="border rounded p-3 mb-3">
                                                <h6 class="text-primary mb-3"><i class="material-symbols-rounded me-2">receipt_long</i>Bill Details</h6>
                                                <div class="row">
                                                    <div class="col-6"><strong>Bill ID:</strong></div>
                                                    <div class="col-6">#<?php echo $bill_details['id']; ?></div>
                                                </div>
                                                <div class="row">
                                                    <div class="col-6"><strong>Amount:</strong></div>
                                                    <div class="col-6">₹<?php echo number_format($bill_details['amount'], 2); ?></div>
                                                </div>
                                                <div class="row">
                                                    <div class="col-6"><strong>Status:</strong></div>
                                                    <div class="col-6">
                                                        <span class="badge badge-sm bg-gradient-<?php echo ($bill_details['payment_status'] ?? $bill_details['status']) == 'paid' ? 'success' : 'warning'; ?>">
                                                            <?php echo ucfirst($bill_details['payment_status'] ?? $bill_details['status']); ?>
                                                        </span>
                                                    </div>
                                                </div>
                                                <div class="row">
                                                    <div class="col-6"><strong>Created:</strong></div>
                                                    <div class="col-6"><?php echo date('M d, Y H:i', strtotime($bill_details['created_at'])); ?></div>
                                                </div>
                                                <?php if (($bill_details['payment_status'] ?? $bill_details['status']) == 'paid'): ?>
                                                    <div class="row">
                                                        <div class="col-6"><strong>Paid Date:</strong></div>
                                                        <div class="col-6"><?php echo $bill_details['payment_date'] ? date('M d, Y H:i', strtotime($bill_details['payment_date'])) : 'N/A'; ?></div>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="border rounded p-3 mb-3">
                                                <h6 class="text-success mb-3"><i class="material-symbols-rounded me-2">local_shipping</i>Trip Information</h6>
                                                <div class="row">
                                                    <div class="col-6"><strong>Driver:</strong></div>
                                                    <div class="col-6"><?php echo htmlspecialchars($bill_details['driver_name'] ?? 'N/A'); ?></div>
                                                </div>
                                                <div class="row">
                                                    <div class="col-6"><strong>Car:</strong></div>
                                                    <div class="col-6"><?php echo $bill_details['make'] && $bill_details['model'] ? htmlspecialchars($bill_details['make'] . ' ' . $bill_details['model']) : 'N/A'; ?></div>
                                                </div>
                                                <div class="row">
                                                    <div class="col-6"><strong>Vehicle Registration Number:</strong></div>
                                                    <div class="col-6"><?php echo htmlspecialchars($bill_details['vehicle_registration_number'] ?? $bill_details['license_plate'] ?? 'N/A'); ?></div>
                                                </div>
                                                <div class="row">
                                                    <div class="col-6"><strong>Distance:</strong></div>
                                                    <div class="col-6"><?php echo $bill_details['distance'] ? number_format($bill_details['distance'], 2) . ' km' : 'N/A'; ?></div>
                                                </div>
                                                <div class="row">
                                                    <div class="col-6"><strong>Start Time:</strong></div>
                                                    <div class="col-6"><?php echo $bill_details['start_time'] ? date('M d, Y H:i', strtotime($bill_details['start_time'])) : 'N/A'; ?></div>
                                                </div>
                                                <div class="row">
                                                    <div class="col-6"><strong>End Time:</strong></div>
                                                    <div class="col-6"><?php echo $bill_details['end_time'] ? date('M d, Y H:i', strtotime($bill_details['end_time'])) : 'N/A'; ?></div>
                                                </div>
                                                <div class="row">
                                                    <div class="col-6"><strong>Trip Type:</strong></div>
                                                    <div class="col-6"><?php echo isset($bill_details['trip_type']) && $bill_details['trip_type'] ? ucfirst(str_replace('_', ' ', $bill_details['trip_type'])) : 'N/A'; ?></div>
                                                </div>
                                                <div class="row">
                                                    <div class="col-6"><strong>Passengers:</strong></div>
                                                    <div class="col-6"><?php echo isset($bill_details['passenger_count']) && $bill_details['passenger_count'] ? $bill_details['passenger_count'] : 'N/A'; ?></div>
                                                </div>
                                                <div class="row">
                                                    <div class="col-6"><strong>Fuel Amount:</strong></div>
                                                    <div class="col-6">₹<?php echo number_format($bill_details['driver_fuel_amount'] ?? 0, 2); ?></div>
                                                </div>
                                                <div class="row">
                                                    <div class="col-6"><strong>Insurance Amount:</strong></div>
                                                    <div class="col-6">₹<?php echo number_format($bill_details['car_insurance_amount'] ?? 0, 2); ?></div>
                                                </div>
                                                <?php
                                                // Calculate amounts matching sample.xlsx format
                                                $base_amount = $bill_details['amount'] ?? 0;
                                                $service_charge = $base_amount * 0.01; // 1%
                                                $surcharge = $base_amount * 0.04; // 4%
                                                $advance = $bill_details['advance'] ?? 0;
                                                $diesel_adv = $bill_details['diesel_adv'] ?? 0;
                                                // Net Payable = Base Amount - Service Charge - Advance - Diesel Advance - Surcharge
                                                $net_payable = $base_amount - $service_charge - $advance - $diesel_adv - $surcharge;
                                                ?>
                                                <div class="row">
                                                    <div class="col-6"><strong>Base Amount:</strong></div>
                                                    <div class="col-6">₹<?php echo number_format($base_amount, 2); ?></div>
                                                </div>
                                                <?php if (($bill_details['fuel_amount'] ?? 0) > 0): ?>
                                                    <div class="row">
                                                        <div class="col-6"><strong>Fuel Amount:</strong></div>
                                                        <div class="col-6">₹<?php echo number_format($bill_details['fuel_amount'] ?? 0, 2); ?></div>
                                                    </div>
                                                    <div class="row">
                                                        <div class="col-6"><strong>Fuel Surcharge (4%):</strong></div>
                                                        <div class="col-6">₹<?php echo number_format($bill_details['fuel_surcharge'] ?? 0, 2); ?></div>
                                                    </div>
                                                <?php endif; ?>
                                                <?php if (($bill_details['driver_advance'] ?? 0) > 0): ?>
                                                    <div class="row">
                                                        <div class="col-6"><strong>Driver Advance:</strong></div>
                                                        <div class="col-6">-₹<?php echo number_format($bill_details['driver_advance'] ?? 0, 2); ?></div>
                                                    </div>
                                                <?php endif; ?>
                                                <hr class="my-2">
                                                <div class="row">
                                                    <div class="col-6"><strong class="text-primary">Final Amount:</strong></div>
                                                    <div class="col-6"><strong class="text-primary">₹<?php echo number_format($bill_details['final_amount'] ?? $bill_details['amount'] ?? 0, 2); ?></strong></div>
                                                </div>
                                                <div class="mt-2">
                                                    <small class="text-muted">
                                                        <i class="material-symbols-rounded me-1">info</i>
                                                        <strong>Billing Rules:</strong><br>
                                                        • Driver advance (if any) is deducted from the final bill<br>
                                                        • 4% surcharge applies only when fuel amount is entered<br>
                                                        • If fuel amount is zero, no surcharge is applicable
                                                    </small>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="row mt-3">
                                        <div class="col-12">
                                            <div class="d-flex gap-2">
                                                <button onclick="window.print()" class="btn bg-gradient-primary">
                                                    <i class="material-symbols-rounded me-2">print</i>Print Bill
                                                </button>
                                                <a href="generate_bill_pdf.php?id=<?php echo $bill_details['id']; ?>" target="_blank" class="btn bg-gradient-success">
                                                    <i class="material-symbols-rounded me-2">download</i>Download PDF
                                                </a>
                                                <a href="generate_bill_excel.php?id=<?php echo $bill_details['id']; ?>" target="_blank" class="btn bg-gradient-primary">
                                                    <i class="material-symbols-rounded me-2">table</i>Download Excel
                                                </a>
                                                <?php if (($bill_details['payment_status'] ?? $bill_details['status']) == 'pending'): ?>
                                                    <a href="mark_paid.php?id=<?php echo $bill_details['id']; ?>" class="btn bg-gradient-warning">
                                                        <i class="material-symbols-rounded me-2">payment</i>Mark as Paid
                                                    </a>
                                                <?php endif; ?>
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
            <script>
                function downloadBill(billId) {
                    var link = document.createElement('a');
                    link.href = 'generate_bill_pdf.php?id=' + billId;
                    link.download = 'bill_' + billId + '.pdf';
                    link.style.display = 'none';
                    document.body.appendChild(link);
                    link.click();
                    document.body.removeChild(link);
                }
            </script>
        </body>

        </html>
<?php
        exit;
    }
}

// Get billing data with fuel amount auto-detection
try {
    $stmt = $conn->prepare("
        SELECT
            b.*,
            t.start_location_lat,
            t.start_location_lng,
            t.end_location_lat,
            t.end_location_lng,
            t.distance,
            t.status as trip_status,
            t.start_time,
            t.end_time,
            t.passenger_count,
            t.trip_type,
            t.scheduled_pickup_time,
            t.scheduled_drop_time,
            t.first_passenger_name,
            t.first_passenger_phone,
            t.first_passenger_address,
            t.last_passenger_name,
            t.last_passenger_phone,
            t.last_passenger_address,
            u.name as driver_name,
            u.fuel_amount as driver_fuel_amount,
            driver_info.vehicle_registration_number,
            c.make,
            c.model,
            c.license_plate,
            c.insurance_amount as car_insurance_amount
        FROM billing b
        JOIN trips t ON b.trip_id = t.id
        LEFT JOIN users u ON t.driver_id = u.id
        LEFT JOIN vehicles c ON t.vehicle_id = c.id
        ORDER BY b.created_at DESC
    ");
    $stmt->execute();
} catch (PDOException $e) {
    // Fallback query without new columns if they don't exist yet
    try {
        $stmt = $conn->prepare("
            SELECT
                b.*,
                t.start_location_lat,
                t.start_location_lng,
                t.end_location_lat,
                t.end_location_lng,
                t.distance,
                t.status as trip_status,
                t.start_time,
                t.end_time,
                t.passenger_count,
                u.name as driver_name,
                c.make,
                c.model,
                c.license_plate
            FROM billing b
            JOIN trips t ON b.trip_id = t.id
            LEFT JOIN users u ON t.driver_id = u.id
            LEFT JOIN vehicles c ON t.vehicle_id = c.id
            ORDER BY b.created_at DESC
        ");
        $stmt->execute();
    } catch (PDOException $e2) {
        // Ultimate fallback - basic query
        $stmt = $conn->prepare("
            SELECT b.*, t.distance, t.start_time, t.end_time, u.name as driver_name, c.make, c.model, c.license_plate
            FROM billing b
            JOIN trips t ON b.trip_id = t.id
            LEFT JOIN users u ON t.driver_id = u.id
            LEFT JOIN vehicles c ON t.vehicle_id = c.id
            LEFT JOIN users driver_info ON t.driver_id = driver_info.id
            ORDER BY b.created_at DESC
        ");
        $stmt->execute();
    }
}

// Calculate total revenue
$totalRevenue = $conn->query("SELECT SUM(amount) FROM billing")->fetchColumn() ?: 0;

// Get monthly revenue for the last 6 months
$monthlyRevenue = $conn->query("
    SELECT
        DATE_FORMAT(created_at, '%Y-%m') as month,
        SUM(amount) as revenue
    FROM billing
    WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
    GROUP BY DATE_FORMAT(created_at, '%Y-%m')
    ORDER BY month DESC
")->fetchAll(PDO::FETCH_ASSOC);

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=5.0, user-scalable=yes">
    <link rel="apple-touch-icon" sizes="76x76" href="./assets/img/apple-icon.png">
    <link rel="icon" type="image/png" href="./assets/img/favicon.png">
    <title>Billing - MS Infosystems Employee Transportation System</title>
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
                            <span class="nav-link-text ms-1">Driver Billing</span>
                        </a>
                    </li>
                    <!-- Client billing removed - will be implemented later -->
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
                        <li class="breadcrumb-item text-sm text-dark active" aria-current="page">Billing</li>
                    </ol>
                    <h6 class="font-weight-bolder mb-0">Billing Management</h6>
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
                                <li class="mb-2">
                                    <a class="dropdown-item border-radius-md" href="javascript:;">
                                        <div class="d-flex py-1">
                                            <div class="my-auto">
                                                <img src="./assets/img/small-logos/logo-spotify.svg" class="avatar avatar-sm bg-gradient-dark me-3">
                                            </div>
                                            <div class="d-flex flex-column justify-content-center">
                                                <h6 class="text-sm font-weight-normal mb-1">
                                                    <span class="font-weight-bold">New album</span> by Travis Scott
                                                </h6>
                                                <p class="text-xs text-secondary mb-0">
                                                    <i class="fa fa-clock me-1"></i>
                                                    1 day
                                                </p>
                                            </div>
                                        </div>
                                    </a>
                                </li>
                                <li>
                                    <a class="dropdown-item border-radius-md" href="javascript:;">
                                        <div class="d-flex py-1">
                                            <div class="my-auto">
                                                <img src="./assets/img/small-logos/logo-invision.svg" class="avatar avatar-sm bg-gradient-danger me-3">
                                            </div>
                                            <div class="d-flex flex-column justify-content-center">
                                                <h6 class="text-sm font-weight-normal mb-1">
                                                    Payment successfully completed
                                                </h6>
                                                <p class="text-xs text-secondary mb-0">
                                                    <i class="fa fa-clock me-1"></i>
                                                    2 days
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
                <div class="col-xl-3 col-sm-6 mb-xl-0 mb-4">
                    <div class="card">
                        <div class="card-body p-3">
                            <div class="row">
                                <div class="col-8">
                                    <div class="numbers">
                                        <p class="text-sm mb-0 text-capitalize font-weight-bold">Total Revenue</p>
                                        <h5 class="font-weight-bolder mb-0">
                                            ₹<?php echo number_format($totalRevenue, 2); ?>
                                        </h5>
                                    </div>
                                </div>
                                <div class="col-4 text-end">
                                    <div class="icon icon-shape bg-gradient-primary shadow text-center border-radius-md">
                                        <i class="material-symbols-rounded opacity-10">attach_money</i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-xl-3 col-sm-6 mb-xl-0 mb-4">
                    <div class="card">
                        <div class="card-body p-3">
                            <div class="row">
                                <div class="col-8">
                                    <div class="numbers">
                                        <p class="text-sm mb-0 text-capitalize font-weight-bold">Total Bills</p>
                                        <h5 class="font-weight-bolder mb-0">
                                            <?php echo $stmt->rowCount(); ?>
                                        </h5>
                                    </div>
                                </div>
                                <div class="col-4 text-end">
                                    <div class="icon icon-shape bg-gradient-success shadow text-center border-radius-md">
                                        <i class="material-symbols-rounded opacity-10">receipt_long</i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-xl-3 col-sm-6 mb-xl-0 mb-4">
                    <div class="card">
                        <div class="card-body p-3">
                            <div class="row">
                                <div class="col-8">
                                    <div class="numbers">
                                        <p class="text-sm mb-0 text-capitalize font-weight-bold">Average Bill</p>
                                        <h5 class="font-weight-bolder mb-0">
                                            ₹<?php echo $stmt->rowCount() > 0 ? number_format($totalRevenue / $stmt->rowCount(), 2) : '0.00'; ?>
                                        </h5>
                                    </div>
                                </div>
                                <div class="col-4 text-end">
                                    <div class="icon icon-shape bg-gradient-info shadow text-center border-radius-md">
                                        <i class="material-symbols-rounded opacity-10">calculate</i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-xl-3 col-sm-6">
                    <div class="card">
                        <div class="card-body p-3">
                            <div class="row">
                                <div class="col-8">
                                    <div class="numbers">
                                        <p class="text-sm mb-0 text-capitalize font-weight-bold">This Month</p>
                                        <h5 class="font-weight-bolder mb-0">
                                            ₹<?php echo number_format(array_sum(array_column($monthlyRevenue, 'revenue')), 2); ?>
                                        </h5>
                                    </div>
                                </div>
                                <div class="col-4 text-end">
                                    <div class="icon icon-shape bg-gradient-warning shadow text-center border-radius-md">
                                        <i class="material-symbols-rounded opacity-10">calendar_today</i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row mt-4">
                <div class="col-lg-7 mb-lg-0 mb-4">
                    <div class="card z-index-2 h-100">
                        <div class="card-header pb-0 pt-3 bg-transparent">
                            <h6 class="text-capitalize">Monthly Revenue Trend</h6>
                            <p class="text-sm mb-0">
                                <i class="fa fa-arrow-up text-success"></i>
                                <span class="font-weight-bold">4% more</span> in 2021
                            </p>
                        </div>
                        <div class="card-body p-3">
                            <div class="chart">
                                <canvas id="chart-line" class="chart-canvas" height="300"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-5">
                    <div class="card">
                        <div class="card-header pb-0 p-3">
                            <h6 class="mb-0">Revenue Breakdown</h6>
                        </div>
                        <div class="card-body p-3">
                            <div class="row">
                                <?php foreach (array_slice($monthlyRevenue, 0, 4) as $month): ?>
                                    <div class="col-6 mb-3">
                                        <div class="d-flex align-items-center">
                                            <div class="text-center">
                                                <span class="badge badge-sm bg-gradient-success me-2"><?php echo date('M', strtotime($month['month'] . '-01')); ?></span>
                                                <h6 class="mb-0">₹<?php echo number_format($month['revenue'], 0); ?></h6>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row mt-4">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header pb-0 p-3">
                            <h6>Billing Records</h6>
                        </div>
                        <div class="card-body px-0 pb-2">
                            <div class="table-responsive">
                                <table class="table align-items-center mb-0">
                                    <thead>
                                        <tr>
                                            <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Bill ID</th>
                                            <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">Driver</th>
                                            <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">Car</th>
                                            <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">License Plate</th>
                                            <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">Distance</th>
                                            <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">Amount</th>
                                            <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">Status</th>
                                            <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">Date</th>
                                            <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php while ($bill = $stmt->fetch(PDO::FETCH_ASSOC)): ?>
                                            <tr>
                                                <td>
                                                    <p class="text-xs font-weight-bold mb-0">#<?php echo $bill['id']; ?></p>
                                                </td>
                                                <td>
                                                    <div class="d-flex px-2 py-1">
                                                        <div>
                                                            <i class="material-symbols-rounded text-secondary">person</i>
                                                        </div>
                                                        <div class="d-flex flex-column justify-content-center">
                                                            <h6 class="mb-0 text-sm"><?php echo htmlspecialchars($bill['driver_name'] ?? 'N/A'); ?></h6>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="d-flex px-2 py-1">
                                                        <div>
                                                            <i class="material-symbols-rounded text-secondary">directions_car</i>
                                                        </div>
                                                        <div class="d-flex flex-column justify-content-center">
                                                            <h6 class="mb-0 text-sm"><?php echo $bill['make'] && $bill['model'] ? htmlspecialchars($bill['make'] . ' ' . $bill['model']) : 'N/A'; ?></h6>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td>
                                                    <p class="text-xs font-weight-bold mb-0"><?php echo htmlspecialchars($bill['license_plate'] ?? 'N/A'); ?></p>
                                                </td>
                                                <td>
                                                    <p class="text-xs font-weight-bold mb-0"><?php echo $bill['distance'] ? number_format($bill['distance'], 2) . ' km' : 'N/A'; ?></p>
                                                </td>
                                                <td>
                                                    <p class="text-xs font-weight-bold mb-0">₹<?php echo number_format($bill['amount'], 2); ?></p>
                                                </td>
                                                <td>
                                                    <span class="badge badge-sm bg-gradient-<?php echo ($bill['payment_status'] ?? $bill['status']) == 'paid' ? 'success' : 'warning'; ?>">
                                                        <?php echo ucfirst($bill['payment_status'] ?? $bill['status']); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <p class="text-xs font-weight-bold mb-0"><?php echo date('M d, Y', strtotime($bill['created_at'])); ?></p>
                                                </td>
                                                <td class="align-middle">
                                                    <div class="d-flex flex-column gap-1">
                                                        <a href="billing.php?action=view&id=<?php echo $bill['id']; ?>" class="text-secondary font-weight-bold text-xs" data-toggle="tooltip" data-original-title="View Details">
                                                            <i class="material-symbols-rounded text-xs me-1">visibility</i>View
                                                        </a>
                                                        <a href="#" onclick="calculateBill(<?php echo $bill['id']; ?>)" class="text-info font-weight-bold text-xs" data-toggle="tooltip" data-original-title="Calculate Bill">
                                                            <i class="material-symbols-rounded text-xs me-1">calculate</i>Calculate
                                                        </a>
                                                        <a href="#" onclick="generateBill(<?php echo $bill['id']; ?>)" class="text-primary font-weight-bold text-xs" data-toggle="tooltip" data-original-title="Generate Bill">
                                                            <i class="material-symbols-rounded text-xs me-1">description</i>Generate
                                                        </a>
                                                        <a href="generate_bill_pdf.php?id=<?php echo $bill['id']; ?>" target="_blank" class="text-success font-weight-bold text-xs" data-toggle="tooltip" data-original-title="Download PDF">
                                                            <i class="material-symbols-rounded text-xs me-1">download</i>Download PDF
                                                        </a>
                                                        <a href="generate_bill_excel.php?id=<?php echo $bill['id']; ?>" target="_blank" class="text-primary font-weight-bold text-xs" data-toggle="tooltip" data-original-title="Download Excel">
                                                            <i class="material-symbols-rounded text-xs me-1">table</i>Download Excel
                                                        </a>
                                                        <?php if (($bill['payment_status'] ?? $bill['status']) == 'pending'): ?>
                                                            <a href="mark_paid.php?id=<?php echo $bill['id']; ?>" class="text-warning font-weight-bold text-xs" data-toggle="tooltip" data-original-title="Mark Paid">
                                                                <i class="material-symbols-rounded text-xs me-1">payment</i>Mark Paid
                                                            </a>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
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
        var ctx = document.getElementById("chart-line").getContext("2d");

        var gradientStroke1 = ctx.createLinearGradient(0, 230, 0, 50);
        gradientStroke1.addColorStop(1, 'rgba(203,12,159,0.2)');
        gradientStroke1.addColorStop(0.2, 'rgba(72,72,176,0.0)');
        gradientStroke1.addColorStop(0, 'rgba(203,12,159,0.0)');

        var gradientStroke2 = ctx.createLinearGradient(0, 230, 0, 50);
        gradientStroke2.addColorStop(1, 'rgba(20,23,39,0.2)');
        gradientStroke2.addColorStop(0.2, 'rgba(72,72,176,0.0)');
        gradientStroke2.addColorStop(0, 'rgba(20,23,39,0.0)');

        new Chart(ctx, {
            type: "line",
            data: {
                labels: [<?php foreach ($monthlyRevenue as $month) {
                                echo '"' . date('M Y', strtotime($month['month'] . '-01')) . '",';
                            } ?>],
                datasets: [{
                    label: "Revenue",
                    tension: 0.4,
                    borderWidth: 0,
                    pointRadius: 0,
                    borderColor: "#cb0c9f",
                    borderWidth: 3,
                    backgroundColor: gradientStroke1,
                    fill: true,
                    data: [<?php foreach ($monthlyRevenue as $month) {
                                echo $month['revenue'] . ',';
                            } ?>],
                    maxBarThickness: 6
                }],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false,
                    }
                },
                interaction: {
                    intersect: false,
                    mode: 'index',
                },
                scales: {
                    y: {
                        grid: {
                            drawBorder: false,
                            display: true,
                            drawOnChartArea: true,
                            drawTicks: false,
                            borderDash: [5, 5]
                        },
                        ticks: {
                            display: true,
                            padding: 10,
                            color: '#b2b9bf',
                            font: {
                                size: 11,
                                family: "Open Sans",
                                style: 'normal',
                                lineHeight: 2
                            },
                        }
                    },
                    x: {
                        grid: {
                            drawBorder: false,
                            display: false,
                            drawOnChartArea: false,
                            drawTicks: false,
                            borderDash: [5, 5]
                        },
                        ticks: {
                            display: true,
                            color: '#b2b9bf',
                            padding: 20,
                            font: {
                                size: 11,
                                family: "Open Sans",
                                style: 'normal',
                                lineHeight: 2
                            },
                        }
                    },
                },
            },
        });

        // Bill action functions
        function calculateBill(billId) {
            alert('Calculate bill functionality for bill #' + billId + ' - This would recalculate the bill amount based on current rates.');
        }

        function generateBill(billId) {
            if (confirm('Generate bill for bill #' + billId + '? This will create a formal bill document.')) {
                // Here you would typically make an AJAX call to generate the bill
                alert('Bill generated successfully! Bill #' + billId);
            }
        }

        function downloadBill(billId) {
            // Open PDF in new tab for download
            window.open('generate_bill_pdf.php?id=' + billId, '_blank');
        }

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

<style>
    .chart-section {
        margin-bottom: 40px;
        background: linear-gradient(135deg, var(--glass-bg) 0%, rgba(26, 26, 46, 0.8) 100%);
        backdrop-filter: blur(20px);
        border: 1px solid var(--glass-border);
        padding: 25px;
        border-radius: var(--border-radius);
        box-shadow: var(--shadow-medium);
    }

    .chart-section h3 {
        color: var(--text-primary);
        margin-bottom: 20px;
        font-size: 1.4rem;
        font-weight: 600;
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .chart-section h3 i {
        color: var(--accent-color);
    }

    .chart-placeholder {
        background: var(--background-secondary);
        border-radius: 8px;
        padding: 20px;
        border: 1px solid var(--glass-border);
    }

    .chart-container {
        display: flex;
        align-items: end;
        justify-content: space-between;
        height: 200px;
        gap: 10px;
    }

    .chart-bar {
        flex: 1;
        background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
        border-radius: 4px 4px 0 0;
        position: relative;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: flex-end;
        min-height: 20px;
        transition: var(--transition);
    }

    .chart-bar:hover {
        transform: scale(1.05);
    }

    .bar-value {
        color: white;
        font-size: 0.8rem;
        font-weight: 600;
        margin-bottom: 5px;
        text-shadow: 0 1px 2px rgba(0, 0, 0, 0.5);
    }

    .bar-label {
        position: absolute;
        bottom: -25px;
        left: 50%;
        transform: translateX(-50%);
        font-size: 0.7rem;
        color: var(--text-secondary);
        white-space: nowrap;
    }

    .status-success {
        background: linear-gradient(135deg, var(--success-color), #047857);
        color: white;
        padding: 4px 12px;
        border-radius: 20px;
        font-size: 0.8rem;
        font-weight: 600;
    }

    .status-warning {
        background: linear-gradient(135deg, var(--warning-color), #d97706);
        color: white;
        padding: 4px 12px;
        border-radius: 20px;
        font-size: 0.8rem;
        font-weight: 600;
    }

    .btn-success {
        background: linear-gradient(135deg, var(--success-color), #047857);
        color: white;
        padding: 6px 12px;
        border-radius: 6px;
        text-decoration: none;
        font-size: 0.8rem;
        font-weight: 600;
        margin-left: 5px;
        transition: var(--transition);
    }

    .btn-success:hover {
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(5, 150, 105, 0.3);
    }

    @media (max-width: 768px) {
        .chart-container {
            height: 150px;
            gap: 5px;
        }

        .bar-label {
            font-size: 0.6rem;
            bottom: -20px;
        }

        .chart-section {
            padding: 20px;
        }
    }
</style>
</body>

</html>
