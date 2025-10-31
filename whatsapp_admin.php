<?php
session_start();
require_once 'includes/config.php';
require_once 'includes/functions.php';

// Check if user is logged in and is admin
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
    if (isset($_POST['update_whatsapp_config'])) {
        $api_key = trim($_POST['whatsapp_api_key']);
        $api_url = trim($_POST['whatsapp_api_url']);
        $phone_number_id = trim($_POST['whatsapp_phone_number_id']);

        try {
            $conn = getDBConnection();

            // Create whatsapp_config table if it doesn't exist
            $conn->exec("CREATE TABLE IF NOT EXISTS whatsapp_config (
                id INT PRIMARY KEY DEFAULT 1,
                api_key VARCHAR(255) NOT NULL,
                api_url VARCHAR(500) NOT NULL,
                phone_number_id VARCHAR(100) NOT NULL,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                updated_by INT NOT NULL,
                FOREIGN KEY (updated_by) REFERENCES users(id)
            )");

            // Insert or update configuration
            $stmt = $conn->prepare("INSERT INTO whatsapp_config (id, api_key, api_url, phone_number_id, updated_by)
                                   VALUES (1, ?, ?, ?, ?)
                                   ON DUPLICATE KEY UPDATE
                                   api_key = VALUES(api_key),
                                   api_url = VALUES(api_url),
                                   phone_number_id = VALUES(phone_number_id),
                                   updated_at = CURRENT_TIMESTAMP,
                                   updated_by = VALUES(updated_by)");

            $stmt->execute([$api_key, $api_url, $phone_number_id, $_SESSION['user_id']]);

            $success_message = "WhatsApp configuration updated successfully!";
        } catch (PDOException $e) {
            $error_message = "Error updating configuration: " . $e->getMessage();
        }
    }

    if (isset($_POST['test_whatsapp'])) {
        $test_phone = trim($_POST['test_phone']);
        $test_message = trim($_POST['test_message']);

        if (empty($test_phone) || empty($test_message)) {
            $error_message = "Please provide both test phone number and message.";
        } else {
            // Load current config
            $config = getWhatsAppConfig();

            if ($config) {
                // Temporarily update constants for testing
                define('WHATSAPP_API_KEY', $config['api_key']);
                define('WHATSAPP_API_URL', $config['api_url']);
                define('WHATSAPP_PHONE_NUMBER_ID', $config['phone_number_id']);

                require_once 'includes/whatsapp.php';

                $testTripData = [
                    'id' => 0,
                    'start_otp' => '123456',
                    'end_otp' => '654321',
                    'start_qr_code' => 'https://chart.googleapis.com/chart?chs=200x200&cht=qr&chl=TEST_QR',
                    'end_qr_code' => 'https://chart.googleapis.com/chart?chs=200x200&cht=qr&chl=TEST_QR',
                    'first_passenger_name' => 'Test User',
                    'first_passenger_phone' => $test_phone,
                    'last_passenger_name' => 'Test User',
                    'last_passenger_phone' => $test_phone,
                    'scheduled_pickup_time' => date('H:i:s'),
                    'scheduled_drop_time' => date('H:i:s', strtotime('+1 hour')),
                    'first_passenger_address' => 'Test Location',
                    'last_passenger_address' => 'Test Location',
                    'trip_date' => date('Y-m-d')
                ];

                // Send test message
                $testResult = sendWhatsAppMessage($test_phone, 'start_trip', $testTripData);

                if ($testResult) {
                    $success_message = "Test WhatsApp message sent successfully to $test_phone!";
                } else {
                    $error_message = "Failed to send test WhatsApp message. Check logs for details.";
                }
            } else {
                $error_message = "WhatsApp configuration not found. Please configure first.";
            }
        }
    }

    if (isset($_POST['resend_message'])) {
        $log_id = (int)$_POST['log_id'];

        try {
            $conn = getDBConnection();
            $stmt = $conn->prepare("SELECT * FROM whatsapp_logs WHERE id = ?");
            $stmt->execute([$log_id]);
            $log = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($log) {
                // Get trip data for resending
                $stmt = $conn->prepare("SELECT * FROM trips WHERE id = ?");
                $stmt->execute([$log['trip_id']]);
                $tripData = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($tripData) {
                    $config = getWhatsAppConfig();
                    if ($config) {
                        define('WHATSAPP_API_KEY', $config['api_key']);
                        define('WHATSAPP_API_URL', $config['api_url']);
                        define('WHATSAPP_PHONE_NUMBER_ID', $config['phone_number_id']);

                        require_once 'includes/whatsapp.php';

                        $resendResult = sendWhatsAppMessage($log['phone_number'], $log['message_type'], $tripData);

                        if ($resendResult) {
                            $success_message = "Message resent successfully!";
                        } else {
                            $error_message = "Failed to resend message.";
                        }
                    } else {
                        $error_message = "WhatsApp configuration not found.";
                    }
                } else {
                    $error_message = "Trip data not found.";
                }
            } else {
                $error_message = "Message log not found.";
            }
        } catch (PDOException $e) {
            $error_message = "Error resending message: " . $e->getMessage();
        }
    }
}

// Get current WhatsApp configuration
function getWhatsAppConfig()
{
    try {
        $conn = getDBConnection();
        $stmt = $conn->query("SELECT * FROM whatsapp_config WHERE id = 1");
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return null;
    }
}

$config = getWhatsAppConfig();

// Get WhatsApp logs with pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 20;
$offset = ($page - 1) * $per_page;
$total_pages = 0;

try {
    $conn = getDBConnection();

    // Check if whatsapp_logs table exists
    $tableCheck = $conn->query("SHOW TABLES LIKE 'whatsapp_logs'");
    $tableExists = $tableCheck->rowCount() > 0;

    if ($tableExists) {
        // Get total count
        $total_stmt = $conn->query("SELECT COUNT(*) FROM whatsapp_logs");
        $total_logs = $total_stmt->fetchColumn();
        $total_pages = ceil($total_logs / $per_page);

        // Get logs with trip info
        $stmt = $conn->prepare("
            SELECT wl.*, t.trip_date, t.scheduled_pickup_time, t.first_passenger_name, t.last_passenger_name
            FROM whatsapp_logs wl
            LEFT JOIN trips t ON wl.trip_id = t.id
            ORDER BY wl.sent_at DESC
            LIMIT ? OFFSET ?
        ");
        $stmt->execute([$per_page, $offset]);
        $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $logs = [];
        $error_message = "WhatsApp logs table does not exist. Please run the table creation script first.";
    }
} catch (PDOException $e) {
    $logs = [];
    $error_message = "Error loading WhatsApp logs: " . $e->getMessage();
}
// Get statistics
try {
    $conn = getDBConnection();

    $stats_stmt = $conn->query("
        SELECT
            COUNT(*) as total_messages,
            SUM(CASE WHEN status = 'sent' THEN 1 ELSE 0 END) as sent_messages,
            SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed_messages,
            SUM(CASE WHEN message_type = 'start_trip' THEN 1 ELSE 0 END) as start_trip_messages,
            SUM(CASE WHEN message_type = 'end_trip' THEN 1 ELSE 0 END) as end_trip_messages
        FROM whatsapp_logs
    ");
    $stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $stats = [
        'total_messages' => 0,
        'sent_messages' => 0,
        'failed_messages' => 0,
        'start_trip_messages' => 0,
        'end_trip_messages' => 0
    ];
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=5.0, user-scalable=yes">
    <link rel="apple-touch-icon" sizes="76x76" href="./assets/img/apple-icon.png">
    <link rel="icon" type="image/png" href="./assets/img/favicon.png">
    <title>WhatsApp Administration - MS Infosystems Employee Transportation System</title>
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
                <li class="nav-item">
                    <a class="nav-link active bg-gradient-dark text-white" href="whatsapp_admin.php">
                        <i class="material-symbols-rounded opacity-5">chat</i>
                        <span class="nav-link-text ms-1">WhatsApp Admin</span>
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

            <!-- Statistics Cards -->
            <div class="row mb-4">
                <div class="col-xl-3 col-sm-6 mb-xl-0 mb-4">
                    <div class="card">
                        <div class="card-body p-3">
                            <div class="row">
                                <div class="col-8">
                                    <div class="numbers">
                                        <p class="text-sm mb-0 text-capitalize font-weight-bold">Total Messages</p>
                                        <h5 class="font-weight-bolder mb-0"><?php echo $stats['total_messages']; ?></h5>
                                    </div>
                                </div>
                                <div class="col-4 text-end">
                                    <div class="icon icon-shape bg-gradient-primary shadow text-center border-radius-md">
                                        <i class="material-symbols-rounded opacity-10">chat</i>
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
                                        <p class="text-sm mb-0 text-capitalize font-weight-bold">Sent</p>
                                        <h5 class="font-weight-bolder mb-0 text-success"><?php echo $stats['sent_messages']; ?></h5>
                                    </div>
                                </div>
                                <div class="col-4 text-end">
                                    <div class="icon icon-shape bg-gradient-success shadow text-center border-radius-md">
                                        <i class="material-symbols-rounded opacity-10">check_circle</i>
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
                                        <p class="text-sm mb-0 text-capitalize font-weight-bold">Failed</p>
                                        <h5 class="font-weight-bolder mb-0 text-danger"><?php echo $stats['failed_messages']; ?></h5>
                                    </div>
                                </div>
                                <div class="col-4 text-end">
                                    <div class="icon icon-shape bg-gradient-danger shadow text-center border-radius-md">
                                        <i class="material-symbols-rounded opacity-10">error</i>
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
                                        <p class="text-sm mb-0 text-capitalize font-weight-bold">Success Rate</p>
                                        <h5 class="font-weight-bolder mb-0">
                                            <?php
                                            $total = $stats['total_messages'];
                                            $rate = $total > 0 ? round(($stats['sent_messages'] / $total) * 100, 1) : 0;
                                            echo $rate . '%';
                                            ?>
                                        </h5>
                                    </div>
                                </div>
                                <div class="col-4 text-end">
                                    <div class="icon icon-shape bg-gradient-info shadow text-center border-radius-md">
                                        <i class="material-symbols-rounded opacity-10">trending_up</i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Configuration Section -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header pb-0 p-3">
                            <h6>WhatsApp API Configuration</h6>
                        </div>
                        <div class="card-body p-3">
                            <form method="POST" action="">
                                <div class="row">
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label for="whatsapp_api_key" class="form-control-label">API Key</label>
                                            <input class="form-control" type="password" id="whatsapp_api_key" name="whatsapp_api_key"
                                                value="<?php echo htmlspecialchars($config['api_key'] ?? ''); ?>" required>
                                            <small class="text-muted">Your WhatsApp API key from your provider</small>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label for="whatsapp_api_url" class="form-control-label">API URL</label>
                                            <input class="form-control" type="url" id="whatsapp_api_url" name="whatsapp_api_url"
                                                value="<?php echo htmlspecialchars($config['api_url'] ?? ''); ?>" required>
                                            <small class="text-muted">API endpoint URL (e.g., https://waba.360dialog.io/v1/messages)</small>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label for="whatsapp_phone_number_id" class="form-control-label">Phone Number ID</label>
                                            <input class="form-control" type="text" id="whatsapp_phone_number_id" name="whatsapp_phone_number_id"
                                                value="<?php echo htmlspecialchars($config['phone_number_id'] ?? ''); ?>" required>
                                            <small class="text-muted">Your WhatsApp phone number ID</small>
                                        </div>
                                    </div>
                                </div>
                                <div class="row mt-3">
                                    <div class="col-12">
                                        <button type="submit" name="update_whatsapp_config" class="btn bg-gradient-primary">
                                            <i class="material-symbols-rounded me-2">save</i>
                                            Update Configuration
                                        </button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Test Message Section -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header pb-0 p-3">
                            <h6>Test WhatsApp Message</h6>
                        </div>
                        <div class="card-body p-3">
                            <form method="POST" action="">
                                <div class="row">
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label for="test_phone" class="form-control-label">Test Phone Number</label>
                                            <input class="form-control" type="tel" id="test_phone" name="test_phone"
                                                placeholder="9876543210" required>
                                            <small class="text-muted">Enter phone number to send test message</small>
                                        </div>
                                    </div>
                                    <div class="col-md-8">
                                        <div class="form-group">
                                            <label for="test_message" class="form-control-label">Test Message</label>
                                            <textarea class="form-control" id="test_message" name="test_message" rows="3"
                                                placeholder="Enter test message..." required></textarea>
                                        </div>
                                    </div>
                                </div>
                                <div class="row mt-3">
                                    <div class="col-12">
                                        <button type="submit" name="test_whatsapp" class="btn bg-gradient-info">
                                            <i class="material-symbols-rounded me-2">send</i>
                                            Send Test Message
                                        </button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Message Logs -->
            <div class="row">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header pb-0 p-3">
                            <h6>WhatsApp Message Logs</h6>
                        </div>
                        <div class="card-body px-0 pb-2">
                            <div class="table-responsive">
                                <table class="table align-items-center mb-0">
                                    <thead>
                                        <tr>
                                            <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">ID</th>
                                            <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Phone Number</th>
                                            <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Type</th>
                                            <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Trip ID</th>
                                            <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Passenger</th>
                                            <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Status</th>
                                            <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Sent At</th>
                                            <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($logs as $log): ?>
                                            <tr>
                                                <td><?php echo $log['id']; ?></td>
                                                <td><?php echo htmlspecialchars($log['phone_number']); ?></td>
                                                <td>
                                                    <span class="badge badge-sm bg-gradient-<?php echo $log['message_type'] == 'start_trip' ? 'primary' : 'success'; ?>">
                                                        <?php echo ucfirst(str_replace('_', ' ', $log['message_type'])); ?>
                                                    </span>
                                                </td>
                                                <td><?php echo $log['trip_id']; ?></td>
                                                <td>
                                                    <?php
                                                    if ($log['message_type'] == 'start_trip') {
                                                        echo htmlspecialchars($log['first_passenger_name'] ?? 'N/A');
                                                    } else {
                                                        echo htmlspecialchars($log['last_passenger_name'] ?? 'N/A');
                                                    }
                                                    ?>
                                                </td>
                                                <td>
                                                    <span class="badge badge-sm bg-gradient-<?php echo $log['status'] == 'sent' ? 'success' : 'danger'; ?>">
                                                        <?php echo ucfirst($log['status']); ?>
                                                    </span>
                                                </td>
                                                <td><?php echo date('d/m/Y H:i', strtotime($log['sent_at'])); ?></td>
                                                <td>
                                                    <div class="d-flex">
                                                        <?php if ($log['status'] == 'failed'): ?>
                                                            <form method="POST" action="" class="me-2">
                                                                <input type="hidden" name="log_id" value="<?php echo $log['id']; ?>">
                                                                <button type="submit" name="resend_message" class="btn btn-sm btn-outline-primary"
                                                                    onclick="return confirm('Resend this message?')">
                                                                    <i class="material-symbols-rounded">refresh</i>
                                                                </button>
                                                            </form>
                                                        <?php endif; ?>
                                                        <?php if (!empty($log['error_message'])): ?>
                                                            <button class="btn btn-sm btn-outline-info" onclick="showError('<?php echo addslashes($log['error_message']); ?>')">
                                                                <i class="material-symbols-rounded">info</i>
                                                            </button>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>

                            <!-- Pagination -->
                            <?php if ($total_pages > 1): ?>
                                <div class="d-flex justify-content-center mt-3">
                                    <nav aria-label="Page navigation">
                                        <ul class="pagination pagination-sm">
                                            <?php if ($page > 1): ?>
                                                <li class="page-item">
                                                    <a class="page-link" href="?page=<?php echo $page - 1; ?>">Previous</a>
                                                </li>
                                            <?php endif; ?>

                                            <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                                                <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                                    <a class="page-link" href="?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                                                </li>
                                            <?php endfor; ?>

                                            <?php if ($page < $total_pages): ?>
                                                <li class="page-item">
                                                    <a class="page-link" href="?page=<?php echo $page + 1; ?>">Next</a>
                                                </li>
                                            <?php endif; ?>
                                        </ul>
                                    </nav>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- Error Modal -->
    <div class="modal fade" id="errorModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Error Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p id="errorMessage"></p>
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
        function showError(message) {
            document.getElementById('errorMessage').textContent = message;
            new bootstrap.Modal(document.getElementById('errorModal')).show();
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

</html>
