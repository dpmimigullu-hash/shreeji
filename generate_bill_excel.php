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

// Get bill ID from URL
$bill_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$bill_id) {
    die('Invalid bill ID');
}

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
            LEFT JOIN cars c ON t.car_id = c.id
            WHERE b.id = ?
        ");
        $stmt->execute([$bill_id]);
        $bill_details = $stmt->fetch(PDO::FETCH_ASSOC);
    }
}

if (!$bill_details) {
    die('Bill not found');
}

// Calculate amounts matching sample.xlsx format
$base_amount = $bill_details['amount'] ?? 0;
$service_charge = $base_amount * 0.01; // 1%
$surcharge = $base_amount * 0.04; // 4%
$advance = $bill_details['advance'] ?? 0;
$diesel_adv = $bill_details['diesel_adv'] ?? 0;
// Net Payable = Base Amount - Service Charge - Advance - Diesel Advance - Surcharge
$net_payable = $base_amount - $service_charge - $advance - $diesel_adv - $surcharge;

// Generate Excel file
header('Content-Type: application/vnd.ms-excel');
header('Content-Disposition: attachment; filename="bill_' . $bill_id . '.xls"');
header('Cache-Control: max-age=0');

// Excel content as HTML table (will be opened by Excel)
?>
<table border="1">
    <tr>
        <td colspan="2"><strong>VENDOR NAME</strong></td>
        <td><?php echo htmlspecialchars($bill_details['vendor_name'] ?? 'MS Infosystems'); ?></td>
        <td><strong>INVOICE NO.</strong></td>
        <td><?php echo htmlspecialchars($bill_details['invoice_number'] ?? 'SLCT/FU/' . date('M/Y') . '/' . str_pad($bill_id, 3, '0', STR_PAD_LEFT)); ?></td>
    </tr>
    <tr>
        <td colspan="2"><strong>CAB NO.</strong></td>
        <td><?php echo htmlspecialchars($bill_details['cab_registration'] ?? $bill_details['license_plate']); ?></td>
        <td><strong>INVOICE DATE</strong></td>
        <td><?php echo date('Y-m-d'); ?></td>
    </tr>
    <tr>
        <td colspan="2"><strong>CAB TYPE</strong></td>
        <td><?php echo htmlspecialchars($bill_details['cab_make_model'] ?? ($bill_details['make'] . ' ' . $bill_details['model'])); ?></td>
        <td><strong>SITE</strong></td>
        <td><?php echo htmlspecialchars($bill_details['site_name'] ?? 'N/A'); ?></td>
    </tr>
    <tr>
        <td colspan="2"><strong>PAN NO.</strong></td>
        <td><?php echo htmlspecialchars($bill_details['pan_number'] ?? 'N/A'); ?></td>
        <td><strong>G.S.T Number</strong></td>
        <td><?php echo htmlspecialchars($bill_details['gst_number'] ?? 'N/A'); ?></td>
    </tr>
    <tr>
        <td colspan="5"></td>
    </tr>
    <tr>
        <td><strong>Sl No</strong></td>
        <td><strong>Date</strong></td>
        <td><strong>Vehicle No.</strong></td>
        <td><strong>Site Name</strong></td>
        <td><strong>Actual Pax</strong></td>
        <td><strong>Drop Time</strong></td>
        <td><strong>Route</strong></td>
        <td><strong>Km</strong></td>
        <td><strong>Pick Up/Drop</strong></td>
        <td><strong>Rate</strong></td>
        <td><strong>Amount</strong></td>
    </tr>
    <tr>
        <td>1</td>
        <td><?php echo $bill_details['trip_date'] ? date('Y-m-d', strtotime($bill_details['trip_date'])) : date('Y-m-d'); ?></td>
        <td><?php echo htmlspecialchars($bill_details['license_plate'] ?? 'N/A'); ?></td>
        <td><?php echo htmlspecialchars($bill_details['site_name'] ?? 'N/A'); ?></td>
        <td><?php echo $bill_details['actual_pax'] ?? $bill_details['passenger_count'] ?? 1; ?></td>
        <td><?php echo $bill_details['scheduled_drop_time'] ? date('H:i', strtotime($bill_details['scheduled_drop_time'])) : 'N/A'; ?></td>
        <td><?php echo htmlspecialchars($bill_details['route'] ?? 'N/A'); ?></td>
        <td><?php echo number_format($bill_details['km'] ?? $bill_details['distance'] ?? 0, 2); ?></td>
        <td><?php echo htmlspecialchars($bill_details['pickup_drop'] ?? ucfirst($bill_details['trip_type'] ?? 'pickup')); ?></td>
        <td><?php echo number_format($bill_details['rate'] ?? 0, 2); ?></td>
        <td><?php echo number_format($base_amount, 2); ?></td>
    </tr>
    <tr>
        <td colspan="11"></td>
    </tr>
    <tr>
        <td colspan="9"><strong>Total Amount</strong></td>
        <td colspan="2"><?php echo number_format($base_amount, 2); ?></td>
    </tr>
    <tr>
        <td colspan="9"><strong>Service Charge @1%</strong></td>
        <td colspan="2"><?php echo number_format($service_charge, 2); ?></td>
    </tr>
    <tr>
        <td colspan="9"><strong>ADVANCE</strong></td>
        <td colspan="2"><?php echo number_format($advance, 2); ?></td>
    </tr>
    <tr>
        <td colspan="9"><strong>Diesel Adv.</strong></td>
        <td colspan="2"><?php echo number_format($diesel_adv, 2); ?></td>
    </tr>
    <tr>
        <td colspan="9"><strong>Surcharge @4%</strong></td>
        <td colspan="2"><?php echo number_format($surcharge, 2); ?></td>
    </tr>
    <tr>
        <td colspan="9"><strong>Net Payable</strong></td>
        <td colspan="2"><strong><?php echo number_format($net_payable, 2); ?></strong></td>
    </tr>
</table>
<?php
// Note: This creates a basic Excel-compatible HTML table that Excel can open directly
?>

