<?php
require_once 'config.php';
require_once 'functions.php';
require_once 'whatsapp.php';

/**
 * Vehicle Document Expiry Notification System
 * Automatically sends notifications 20 days before document expiry
 */

// Configuration
define('NOTIFICATION_DAYS_BEFORE', 20);

/**
 * Check for expiring documents and send notifications
 *
 * @return array Results of notification checks
 */
function checkDocumentExpiryNotifications()
{
    $conn = getDBConnection();
    $results = [
        'checked' => 0,
        'notifications_sent' => 0,
        'errors' => []
    ];

    try {
        // Get all vehicles with document expiry dates
        $stmt = $conn->query("
            SELECT v.*,
                   u.name as driver_name,
                   u.phone as driver_phone,
                   u.email as driver_email
            FROM vehicles v
            LEFT JOIN users u ON v.driver_id = u.id
            WHERE v.driver_id IS NOT NULL
            AND (
                v.pollution_valid_till IS NOT NULL OR
                v.registration_valid_till IS NOT NULL OR
                v.road_tax_valid_till IS NOT NULL OR
                v.fast_tag_valid_till IS NOT NULL
            )
        ");

        $vehicles = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $results['checked'] = count($vehicles);

        foreach ($vehicles as $vehicle) {
            $notifications = checkVehicleDocuments($vehicle);
            foreach ($notifications as $notification) {
                if (sendDocumentExpiryNotification($vehicle, $notification)) {
                    $results['notifications_sent']++;
                } else {
                    $results['errors'][] = "Failed to send notification for vehicle ID: " . $vehicle['id'];
                }
            }
        }
    } catch (PDOException $e) {
        $results['errors'][] = "Database error: " . $e->getMessage();
    }

    return $results;
}

/**
 * Check individual vehicle documents for expiry
 *
 * @param array $vehicle Vehicle data
 * @return array Array of expiring documents
 */
function checkVehicleDocuments($vehicle)
{
    $notifications = [];
    $currentDate = date('Y-m-d');
    $notificationDate = date('Y-m-d', strtotime("+20 days"));

    $documents = [
        'pollution_certificate' => [
            'valid_from' => $vehicle['pollution_valid_from'],
            'valid_till' => $vehicle['pollution_valid_till'],
            'name' => 'Pollution Certificate'
        ],
        'registration_certificate' => [
            'valid_from' => $vehicle['registration_valid_from'],
            'valid_till' => $vehicle['registration_valid_till'],
            'name' => 'Registration Certificate'
        ],
        'road_tax' => [
            'valid_from' => $vehicle['road_tax_valid_from'],
            'valid_till' => $vehicle['road_tax_valid_till'],
            'name' => 'Road Tax Document'
        ],
        'fast_tag' => [
            'valid_from' => $vehicle['fast_tag_valid_from'],
            'valid_till' => $vehicle['fast_tag_valid_till'],
            'name' => 'Fast Tag'
        ]
    ];

    foreach ($documents as $docType => $docInfo) {
        if ($docInfo['valid_till']) {
            $expiryDate = $docInfo['valid_till'];

            // Check if expiry is within 20 days
            if ($expiryDate <= $notificationDate && $expiryDate >= $currentDate) {
                $daysUntilExpiry = floor((strtotime($expiryDate) - strtotime($currentDate)) / (60 * 60 * 24));

                // Check if notification already sent for this document in the last 7 days
                if (!hasRecentNotification($vehicle['id'], $docType, $expiryDate)) {
                    $notifications[] = [
                        'document_type' => $docType,
                        'document_name' => $docInfo['name'],
                        'expiry_date' => $expiryDate,
                        'days_until_expiry' => $daysUntilExpiry,
                        'valid_from' => $docInfo['valid_from']
                    ];
                }
            }
        }
    }

    return $notifications;
}

/**
 * Check if a notification was already sent recently for this document
 *
 * @param int $vehicleId
 * @param string $documentType
 * @param string $expiryDate
 * @return bool
 */
function hasRecentNotification($vehicleId, $documentType, $expiryDate)
{
    try {
        $conn = getDBConnection();

        // Create notifications table if it doesn't exist
        $conn->exec("
            CREATE TABLE IF NOT EXISTS document_notifications (
                id INT AUTO_INCREMENT PRIMARY KEY,
                vehicle_id INT NOT NULL,
                document_type VARCHAR(50) NOT NULL,
                expiry_date DATE NOT NULL,
                notification_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                status ENUM('sent', 'failed') DEFAULT 'sent',
                INDEX idx_vehicle_doc (vehicle_id, document_type),
                INDEX idx_expiry (expiry_date)
            )
        ");

        // Check if notification sent in last 7 days for this document
        $stmt = $conn->prepare("
            SELECT COUNT(*) as count
            FROM document_notifications
            WHERE vehicle_id = ?
            AND document_type = ?
            AND expiry_date = ?
            AND notification_date >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        ");
        $stmt->execute([$vehicleId, $documentType, $expiryDate]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return $result['count'] > 0;
    } catch (PDOException $e) {
        error_log("Error checking recent notifications: " . $e->getMessage());
        return false;
    }
}

/**
 * Send document expiry notification
 *
 * @param array $vehicle Vehicle data
 * @param array $notification Notification data
 * @return bool Success status
 */
function sendDocumentExpiryNotification($vehicle, $notification)
{
    try {
        $conn = getDBConnection();

        // Prepare notification message
        $message = prepareExpiryMessage($vehicle, $notification);

        // Send WhatsApp message if driver has phone number
        $whatsappSent = false;
        if (!empty($vehicle['driver_phone'])) {
            $whatsappSent = sendWhatsAppMessage(
                $vehicle['driver_phone'],
                'document_expiry',
                array_merge($vehicle, $notification, ['message' => $message])
            );
        }

        // Log notification
        $stmt = $conn->prepare("
            INSERT INTO document_notifications
            (vehicle_id, document_type, expiry_date, status)
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([
            $vehicle['id'],
            $notification['document_type'],
            $notification['expiry_date'],
            $whatsappSent ? 'sent' : 'failed'
        ]);

        return $whatsappSent;
    } catch (PDOException $e) {
        error_log("Error sending document expiry notification: " . $e->getMessage());
        return false;
    }
}

/**
 * Prepare expiry notification message
 *
 * @param array $vehicle
 * @param array $notification
 * @return string
 */
function prepareExpiryMessage($vehicle, $notification)
{
    $companyName = "MS Infosystems";
    $driverName = $vehicle['driver_name'] ?: 'Driver';
    $vehicleInfo = ($vehicle['make'] && $vehicle['model']) ?
        $vehicle['make'] . ' ' . $vehicle['model'] :
        'Vehicle';

    $expiryDate = date('d/m/Y', strtotime($notification['expiry_date']));
    $daysLeft = $notification['days_until_expiry'];

    $message = "🚨 *{$companyName} - Document Expiry Alert*\n\n";
    $message .= "Dear {$driverName},\n\n";
    $message .= "⚠️ *Important Document Expiry Notice*\n\n";
    $message .= "📋 *Document Details:*\n";
    $message .= "• Document: {$notification['document_name']}\n";
    $message .= "• Vehicle: {$vehicleInfo}\n";
    $message .= "• Registration: " . ($vehicle['registration_number'] ?: 'N/A') . "\n";
    $message .= "• Expiry Date: {$expiryDate}\n";
    $message .= "• Days Remaining: {$daysLeft} days\n\n";

    if ($notification['valid_from']) {
        $validFrom = date('d/m/Y', strtotime($notification['valid_from']));
        $message .= "• Valid From: {$validFrom}\n";
    }

    $message .= "\n🚨 *Action Required:*\n";
    $message .= "Please renew this document immediately to avoid any service interruptions.\n\n";
    $message .= "📞 Contact your supervisor or MS Infosystems support for assistance.\n\n";
    $message .= "Thank you for your attention to this important matter.\n\n";
    $message .= "*{$companyName}*";

    return $message;
}

/**
 * Get expiring documents report
 *
 * @param int $daysAhead Number of days to look ahead
 * @return array
 */
function getExpiringDocumentsReport($daysAhead = 30)
{
    try {
        $conn = getDBConnection();
        $futureDate = date('Y-m-d', strtotime("+{$daysAhead} days"));

        $stmt = $conn->prepare("
            SELECT v.*,
                   u.name as driver_name,
                   u.phone as driver_phone,
                   CASE
                       WHEN v.pollution_valid_till <= ? AND v.pollution_valid_till >= CURDATE() THEN 'pollution_certificate'
                       WHEN v.registration_valid_till <= ? AND v.registration_valid_till >= CURDATE() THEN 'registration_certificate'
                       WHEN v.road_tax_valid_till <= ? AND v.road_tax_valid_till >= CURDATE() THEN 'road_tax'
                       WHEN v.fast_tag_valid_till <= ? AND v.fast_tag_valid_till >= CURDATE() THEN 'fast_tag'
                   END as expiring_document,
                   CASE
                       WHEN v.pollution_valid_till <= ? AND v.pollution_valid_till >= CURDATE() THEN v.pollution_valid_till
                       WHEN v.registration_valid_till <= ? AND v.registration_valid_till >= CURDATE() THEN v.registration_valid_till
                       WHEN v.road_tax_valid_till <= ? AND v.road_tax_valid_till >= CURDATE() THEN v.road_tax_valid_till
                       WHEN v.fast_tag_valid_till <= ? AND v.fast_tag_valid_till >= CURDATE() THEN v.fast_tag_valid_till
                   END as expiry_date
            FROM vehicles v
            LEFT JOIN users u ON v.driver_id = u.id
            WHERE v.driver_id IS NOT NULL
            AND (
                (v.pollution_valid_till <= ? AND v.pollution_valid_till >= CURDATE()) OR
                (v.registration_valid_till <= ? AND v.registration_valid_till >= CURDATE()) OR
                (v.road_tax_valid_till <= ? AND v.road_tax_valid_till >= CURDATE()) OR
                (v.fast_tag_valid_till <= ? AND v.fast_tag_valid_till >= CURDATE())
            )
            ORDER BY expiry_date ASC
        ");

        $params = array_fill(0, 9, $futureDate);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error getting expiring documents report: " . $e->getMessage());
        return [];
    }
}

/**
 * Cron job function to run daily notification checks
 * This should be called by a cron job or scheduled task
 */
function runDailyDocumentNotifications()
{
    $results = checkDocumentExpiryNotifications();

    // Log results
    $logMessage = date('Y-m-d H:i:s') . " - Document expiry notifications: " .
        "Checked: {$results['checked']}, " .
        "Sent: {$results['notifications_sent']}, " .
        "Errors: " . count($results['errors']) . "\n";

    if (!empty($results['errors'])) {
        $logMessage .= "Errors: " . implode('; ', $results['errors']) . "\n";
    }

    error_log($logMessage);

    return $results;
}
