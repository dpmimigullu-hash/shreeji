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
        LEFT JOIN vehicles c ON t.vehicle_id = c.id
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

// Generate PDF using TCPDF library if available, otherwise fallback to HTML
$filename = 'bill_' . $bill_id . '.pdf';

// Generate PDF using FPDF library if available, otherwise fallback to HTML
$filename = 'bill_' . $bill_id . '.pdf';

// Check if FPDF is available (download and place in fpdf folder if needed)
$fpdf_path = 'fpdf/fpdf.php'; // Common FPDF path

if (file_exists($fpdf_path)) {
    // Use FPDF for proper PDF generation
    require_once $fpdf_path;

    // Create new PDF document
    $pdf = new FPDF();
    $pdf->AddPage();

    // Set font
    $pdf->SetFont('Arial', 'B', 16);

    // Header
    $pdf->Cell(0, 10, 'MS Infosystems', 0, 1, 'C');
    $pdf->Cell(0, 10, 'Employee Transportation System', 0, 1, 'C');
    $pdf->Cell(0, 10, 'Bill Invoice', 0, 1, 'C');
    $pdf->Ln(10);

    // Company Info
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->Cell(40, 8, 'VENDOR NAME:', 0, 0);
    $pdf->SetFont('Arial', '', 10);
    $pdf->Cell(50, 8, htmlspecialchars($bill_details['vendor_name'] ?? 'MS Infosystems'), 0, 0);
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->Cell(40, 8, 'INVOICE NO.:', 0, 0);
    $pdf->SetFont('Arial', '', 10);
    $pdf->Cell(0, 8, htmlspecialchars($bill_details['invoice_number'] ?? 'SLCT/FU/' . date('M/Y') . '/' . str_pad($bill_id, 3, '0', STR_PAD_LEFT)), 0, 1);

    $pdf->SetFont('Arial', 'B', 10);
    $pdf->Cell(40, 8, 'CAB NO.:', 0, 0);
    $pdf->SetFont('Arial', '', 10);
    $pdf->Cell(50, 8, htmlspecialchars($bill_details['cab_registration'] ?? $bill_details['license_plate']), 0, 0);
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->Cell(40, 8, 'INVOICE DATE:', 0, 0);
    $pdf->SetFont('Arial', '', 10);
    $pdf->Cell(0, 8, date('Y-m-d'), 0, 1);

    $pdf->SetFont('Arial', 'B', 10);
    $pdf->Cell(40, 8, 'CAB TYPE:', 0, 0);
    $pdf->SetFont('Arial', '', 10);
    $pdf->Cell(50, 8, htmlspecialchars($bill_details['cab_make_model'] ?? ($bill_details['make'] . ' ' . $bill_details['model'])), 0, 0);
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->Cell(40, 8, 'SITE:', 0, 0);
    $pdf->SetFont('Arial', '', 10);
    $pdf->Cell(0, 8, htmlspecialchars($bill_details['site_name'] ?? 'N/A'), 0, 1);

    $pdf->SetFont('Arial', 'B', 10);
    $pdf->Cell(40, 8, 'PAN NO.:', 0, 0);
    $pdf->SetFont('Arial', '', 10);
    $pdf->Cell(50, 8, htmlspecialchars($bill_details['pan_number'] ?? 'N/A'), 0, 0);
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->Cell(40, 8, 'G.S.T Number:', 0, 0);
    $pdf->SetFont('Arial', '', 10);
    $pdf->Cell(0, 8, htmlspecialchars($bill_details['gst_number'] ?? 'N/A'), 0, 1);

    $pdf->Ln(10);

    // Trip Details Table
    $pdf->SetFont('Arial', 'B', 12);
    $pdf->Cell(0, 8, 'Trip Details', 0, 1);
    $pdf->Ln(5);

    // Table headers
    $pdf->SetFont('Arial', 'B', 8);
    $pdf->Cell(10, 6, 'Sl No', 1, 0, 'C');
    $pdf->Cell(20, 6, 'Date', 1, 0, 'C');
    $pdf->Cell(25, 6, 'Vehicle No.', 1, 0, 'C');
    $pdf->Cell(25, 6, 'Site Name', 1, 0, 'C');
    $pdf->Cell(15, 6, 'Actual Pax', 1, 0, 'C');
    $pdf->Cell(20, 6, 'Drop Time', 1, 0, 'C');
    $pdf->Cell(25, 6, 'Route', 1, 0, 'C');
    $pdf->Cell(15, 6, 'Km', 1, 0, 'C');
    $pdf->Cell(20, 6, 'Pick Up/Drop', 1, 0, 'C');
    $pdf->Cell(15, 6, 'Rate', 1, 0, 'C');
    $pdf->Cell(20, 6, 'Amount', 1, 1, 'C');

    // Table data
    $pdf->SetFont('Arial', '', 8);
    $pdf->Cell(10, 6, '1', 1, 0, 'C');
    $pdf->Cell(20, 6, ($bill_details['trip_date'] ? date('Y-m-d', strtotime($bill_details['trip_date'])) : date('Y-m-d')), 1, 0, 'C');
    $pdf->Cell(25, 6, htmlspecialchars($bill_details['license_plate'] ?? 'N/A'), 1, 0, 'C');
    $pdf->Cell(25, 6, htmlspecialchars($bill_details['site_name'] ?? 'N/A'), 1, 0, 'C');
    $pdf->Cell(15, 6, ($bill_details['actual_pax'] ?? $bill_details['passenger_count'] ?? 1), 1, 0, 'C');
    $pdf->Cell(20, 6, ($bill_details['scheduled_drop_time'] ? date('H:i', strtotime($bill_details['scheduled_drop_time'])) : 'N/A'), 1, 0, 'C');
    $pdf->Cell(25, 6, htmlspecialchars($bill_details['route'] ?? 'N/A'), 1, 0, 'C');
    $pdf->Cell(15, 6, number_format($bill_details['km'] ?? $bill_details['distance'] ?? 0, 2), 1, 0, 'C');
    $pdf->Cell(20, 6, htmlspecialchars($bill_details['pickup_drop'] ?? ucfirst($bill_details['trip_type'] ?? 'pickup')), 1, 0, 'C');
    $pdf->Cell(15, 6, 'Rs.' . number_format($bill_details['rate'] ?? 0, 2), 1, 0, 'C');
    $pdf->Cell(20, 6, 'Rs.' . number_format($base_amount, 2), 1, 1, 'C');

    $pdf->Ln(10);

    // Amount calculations
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->Cell(0, 8, 'TOTAL DUTY HOURS: ' . ($bill_details['passenger_count'] ?? 1), 0, 1, 'C');
    $pdf->Ln(5);

    // Amount table
    $pdf->SetFont('Arial', 'B', 9);
    $pdf->Cell(80, 6, 'Total Amount', 1, 0);
    $pdf->SetFont('Arial', '', 9);
    $pdf->Cell(30, 6, 'Rs.' . number_format($base_amount, 2), 1, 1, 'R');

    $pdf->SetFont('Arial', 'B', 9);
    $pdf->Cell(80, 6, 'Service Charge @1%', 1, 0);
    $pdf->SetFont('Arial', '', 9);
    $pdf->Cell(30, 6, 'Rs.' . number_format($service_charge, 2), 1, 1, 'R');

    $pdf->SetFont('Arial', 'B', 9);
    $pdf->Cell(80, 6, 'ADVANCE', 1, 0);
    $pdf->SetFont('Arial', '', 9);
    $pdf->Cell(30, 6, 'Rs.' . number_format($advance, 2), 1, 1, 'R');

    $pdf->SetFont('Arial', 'B', 9);
    $pdf->Cell(80, 6, 'Diesel Adv.', 1, 0);
    $pdf->SetFont('Arial', '', 9);
    $pdf->Cell(30, 6, 'Rs.' . number_format($diesel_adv, 2), 1, 1, 'R');

    $pdf->SetFont('Arial', 'B', 9);
    $pdf->Cell(80, 6, 'Surcharge @4%', 1, 0);
    $pdf->SetFont('Arial', '', 9);
    $pdf->Cell(30, 6, 'Rs.' . number_format($surcharge, 2), 1, 1, 'R');

    // Net Payable
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->Cell(80, 8, 'Net Payable', 1, 0);
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->SetTextColor(0, 128, 0); // Green color
    $pdf->Cell(30, 8, 'Rs.' . number_format($net_payable, 2), 1, 1, 'R');
    $pdf->SetTextColor(0, 0, 0); // Reset to black

    $pdf->Ln(15);

    // Footer
    $pdf->SetFont('Arial', 'I', 8);
    $pdf->Cell(0, 5, 'This is a computer generated invoice. No signature required.', 0, 1, 'C');
    $pdf->Cell(0, 5, 'Generated on ' . date('Y-m-d H:i:s') . ' by MS Infosystems Employee Transportation System', 0, 1, 'C');

    // Output PDF
    $pdf->Output('D', $filename);
    exit;
} else {
    // Fallback to HTML display with print styling - force download as HTML file that can be printed
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Transfer-Encoding: binary');
}
?>
<!DOCTYPE html>
<html>

<head>
    <title>Bill #<?php echo $bill_id; ?> - MS Infosystems</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
        }

        .header {
            text-align: center;
            border-bottom: 2px solid #000;
            padding-bottom: 10px;
            margin-bottom: 20px;
        }

        .company-info {
            margin-bottom: 30px;
        }

        .bill-details {
            margin-bottom: 20px;
        }

        .amounts {
            margin-top: 20px;
        }

        .total {
            font-weight: bold;
            font-size: 16px;
        }

        .net-payable {
            color: green;
            font-weight: bold;
            font-size: 18px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }

        th,
        td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }

        th {
            background-color: #f2f2f2;
        }

        .footer {
            margin-top: 40px;
            text-align: center;
            font-size: 12px;
            color: #666;
        }
    </style>
</head>

<body>
    <div class="header">
        <h1>MS Infosystems</h1>
        <h2>Employee Transportation System</h2>
        <h3>Bill Invoice</h3>
    </div>

    <div class="company-info">
        <table style="width: 100%; margin-bottom: 20px;">
            <tr>
                <td style="width: 25%;"><strong>VENDOR NAME</strong></td>
                <td style="width: 25%;"><?php echo htmlspecialchars($bill_details['vendor_name'] ?? 'MS Infosystems'); ?></td>
                <td style="width: 25%;"><strong>INVOICE NO.</strong></td>
                <td style="width: 25%;"><?php echo htmlspecialchars($bill_details['invoice_number'] ?? 'SLCT/FU/' . date('M/Y') . '/' . str_pad($bill_id, 3, '0', STR_PAD_LEFT)); ?></td>
            </tr>
            <tr>
                <td><strong>CAB NO.</strong></td>
                <td><?php echo htmlspecialchars($bill_details['cab_registration'] ?? $bill_details['license_plate']); ?></td>
                <td><strong>INVOICE DATE</strong></td>
                <td><?php echo date('Y-m-d'); ?></td>
            </tr>
            <tr>
                <td><strong>CAB TYPE</strong></td>
                <td><?php echo htmlspecialchars($bill_details['cab_make_model'] ?? ($bill_details['make'] . ' ' . $bill_details['model'])); ?></td>
                <td><strong>SITE</strong></td>
                <td><?php echo htmlspecialchars($bill_details['site_name'] ?? 'N/A'); ?></td>
            </tr>
            <tr>
                <td><strong>PAN NO.</strong></td>
                <td><?php echo htmlspecialchars($bill_details['pan_number'] ?? 'N/A'); ?></td>
                <td><strong>G.S.T Number</strong></td>
                <td><?php echo htmlspecialchars($bill_details['gst_number'] ?? 'N/A'); ?></td>
            </tr>
        </table>
    </div>

    <div class="bill-details">
        <h4 style="margin-bottom: 15px;">Trip Details</h4>
        <table style="width: 100%; border-collapse: collapse;">
            <thead>
                <tr style="background-color: #f5f5f5;">
                    <th style="border: 1px solid #ddd; padding: 8px; text-align: left; font-weight: bold;">Sl No</th>
                    <th style="border: 1px solid #ddd; padding: 8px; text-align: left; font-weight: bold;">Date</th>
                    <th style="border: 1px solid #ddd; padding: 8px; text-align: left; font-weight: bold;">Vehicle No.</th>
                    <th style="border: 1px solid #ddd; padding: 8px; text-align: left; font-weight: bold;">Site Name</th>
                    <th style="border: 1px solid #ddd; padding: 8px; text-align: left; font-weight: bold;">Actual Pax</th>
                    <th style="border: 1px solid #ddd; padding: 8px; text-align: left; font-weight: bold;">Drop Time</th>
                    <th style="border: 1px solid #ddd; padding: 8px; text-align: left; font-weight: bold;">Route</th>
                    <th style="border: 1px solid #ddd; padding: 8px; text-align: left; font-weight: bold;">Km</th>
                    <th style="border: 1px solid #ddd; padding: 8px; text-align: left; font-weight: bold;">Pick Up/Drop</th>
                    <th style="border: 1px solid #ddd; padding: 8px; text-align: left; font-weight: bold;">Rate</th>
                    <th style="border: 1px solid #ddd; padding: 8px; text-align: left; font-weight: bold;">Amount</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td style="border: 1px solid #ddd; padding: 8px;">1</td>
                    <td style="border: 1px solid #ddd; padding: 8px;"><?php echo $bill_details['trip_date'] ? date('Y-m-d', strtotime($bill_details['trip_date'])) : date('Y-m-d'); ?></td>
                    <td style="border: 1px solid #ddd; padding: 8px;"><?php echo htmlspecialchars($bill_details['license_plate'] ?? 'N/A'); ?></td>
                    <td style="border: 1px solid #ddd; padding: 8px;"><?php echo htmlspecialchars($bill_details['site_name'] ?? 'N/A'); ?></td>
                    <td style="border: 1px solid #ddd; padding: 8px;"><?php echo $bill_details['actual_pax'] ?? $bill_details['passenger_count'] ?? 1; ?></td>
                    <td style="border: 1px solid #ddd; padding: 8px;"><?php echo $bill_details['scheduled_drop_time'] ? date('H:i', strtotime($bill_details['scheduled_drop_time'])) : 'N/A'; ?></td>
                    <td style="border: 1px solid #ddd; padding: 8px;"><?php echo htmlspecialchars($bill_details['route'] ?? 'N/A'); ?></td>
                    <td style="border: 1px solid #ddd; padding: 8px;"><?php echo number_format($bill_details['km'] ?? $bill_details['distance'] ?? 0, 2); ?></td>
                    <td style="border: 1px solid #ddd; padding: 8px;"><?php echo htmlspecialchars($bill_details['pickup_drop'] ?? ucfirst($bill_details['trip_type'] ?? 'pickup')); ?></td>
                    <td style="border: 1px solid #ddd; padding: 8px;">₹<?php echo number_format($bill_details['rate'] ?? 0, 2); ?></td>
                    <td style="border: 1px solid #ddd; padding: 8px;">₹<?php echo number_format($base_amount, 2); ?></td>
                </tr>
            </tbody>
        </table>
    </div>

    <div class="amounts" style="margin-top: 20px;">
        <table style="width: 400px; margin-left: auto; border-collapse: collapse;">
            <tr>
                <td colspan="2" style="text-align: center; font-weight: bold; font-size: 14px; padding: 10px; background-color: #f5f5f5;">TOTAL DUTY HOURS: <?php echo $bill_details['passenger_count'] ?? 1; ?></td>
            </tr>
            <tr>
                <td style="padding: 8px; font-weight: bold;">Total Amount</td>
                <td style="padding: 8px; text-align: right;">₹<?php echo number_format($base_amount, 2); ?></td>
            </tr>
            <tr>
                <td style="padding: 8px; font-weight: bold;">Service Charge @1%</td>
                <td style="padding: 8px; text-align: right;">₹<?php echo number_format($service_charge, 2); ?></td>
            </tr>
            <tr>
                <td style="padding: 8px; font-weight: bold;">ADVANCE</td>
                <td style="padding: 8px; text-align: right;">₹<?php echo number_format($advance, 2); ?></td>
            </tr>
            <tr>
                <td style="padding: 8px; font-weight: bold;">Diesel Adv.</td>
                <td style="padding: 8px; text-align: right;">₹<?php echo number_format($diesel_adv, 2); ?></td>
            </tr>
            <tr>
                <td style="padding: 8px; font-weight: bold;">Surcharge @4%</td>
                <td style="padding: 8px; text-align: right;">₹<?php echo number_format($surcharge, 2); ?></td>
            </tr>
            <tr style="border-top: 2px solid #000; font-weight: bold; font-size: 16px;">
                <td style="padding: 8px; font-weight: bold;">Net Payable</td>
                <td style="padding: 8px; text-align: right; color: green; font-weight: bold;">₹<?php echo number_format($net_payable, 2); ?></td>
            </tr>
        </table>
    </div>

    <div class="footer">
        <p>This is a computer generated invoice. No signature required.</p>
        <p>Generated on <?php echo date('Y-m-d H:i:s'); ?> by MS Infosystems Employee Transportation System</p>
    </div>
</body>

</html>
<?php
// Note: This is a basic HTML to PDF conversion. For production use,
// consider using libraries like TCPDF, FPDF, or DomPDF for better PDF generation.
?>

