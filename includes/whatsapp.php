<?php
require_once 'config.php';

/**
 * WhatsApp Messaging Module for Employee Transportation System
 * Uses WhatsApp Business API or third-party services like 360Dialog, Twilio, etc.
 */

// Configuration - Load from database or fallback to defaults
function getWhatsAppConfigFromDB()
{
    try {
        $conn = getDBConnection();
        $stmt = $conn->query("SELECT * FROM whatsapp_config WHERE id = 1");
        $config = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($config) {
            return $config;
        }
    } catch (PDOException $e) {
        // Table might not exist yet
    }

    // Return defaults if no config found
    return [
        'api_key' => 'your_whatsapp_api_key_here',
        'api_url' => 'https://api.whatsapp.com/v1/messages',
        'phone_number_id' => 'your_phone_number_id_here'
    ];
}

$config = getWhatsAppConfigFromDB();
define('WHATSAPP_API_KEY', $config['api_key']);
define('WHATSAPP_API_URL', $config['api_url']);
define('WHATSAPP_PHONE_NUMBER_ID', $config['phone_number_id']);

/**
 * Send WhatsApp message with OTP and QR code
 *
 * @param string $phoneNumber Recipient phone number (with country code)
 * @param string $messageType 'start_trip', 'end_trip', or 'document_expiry'
 * @param array $data Message data (trip data for trips, combined data for documents)
 * @return bool Success status
 */
function sendWhatsAppMessage($phoneNumber, $messageType, $data)
{
    // Format phone number (ensure it starts with country code)
    $formattedPhone = formatPhoneNumber($phoneNumber);

    if (!$formattedPhone) {
        error_log("Invalid phone number: $phoneNumber");
        return false;
    }

    // Generate message content based on type
    $message = generateMessage($messageType, $data);

    // For now, we'll use a simple curl implementation
    // In production, replace with your actual WhatsApp API provider

    $apiUrl = WHATSAPP_API_URL;
    $apiKey = WHATSAPP_API_KEY;

    $postData = [
        'messaging_product' => 'whatsapp',
        'recipient_type' => 'individual',
        'to' => $formattedPhone,
        'type' => 'text',
        'text' => [
            'preview_url' => false,
            'body' => $message
        ]
    ];

    $headers = [
        'Authorization: Bearer ' . $apiKey,
        'Content-Type: application/json'
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $apiUrl);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postData));
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // For development only

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    if (curl_errno($ch)) {
        error_log('WhatsApp API Error: ' . curl_error($ch));
        curl_close($ch);
        return false;
    }

    curl_close($ch);

    $responseData = json_decode($response, true);

    if ($httpCode === 200 && isset($responseData['messages'][0]['id'])) {
        // Log successful message
        logWhatsAppMessage($formattedPhone, $messageType, $data['id'] ?? 0, 'sent');
        return true;
    } else {
        // Log failed message
        $errorMessage = isset($responseData['error']['message']) ? $responseData['error']['message'] : 'Unknown error';
        error_log("WhatsApp send failed: $errorMessage");
        logWhatsAppMessage($formattedPhone, $messageType, $data['id'] ?? 0, 'failed', $errorMessage);
        return false;
    }
}

/**
 * Generate message content based on type
 *
 * @param string $messageType
 * @param array $data
 * @return string
 */
function generateMessage($messageType, $data)
{
    $companyName = "MS Infosystems";
    $tripId = $tripData['id'];

    if ($messageType === 'start_trip') {
        $otp = $tripData['start_otp'];
        $qrCode = $tripData['start_qr_code'];
        $passengerName = $tripData['first_passenger_name'];
        $pickupTime = date('h:i A', strtotime($tripData['scheduled_pickup_time']));
        $pickupLocation = $tripData['first_passenger_address'];

        $message = "🚗 *{$companyName} - Trip Started*\n\n";
        $message .= "Dear {$passengerName},\n\n";
        $message .= "Your trip has been scheduled and is now active!\n\n";
        $message .= "📋 *Trip Details:*\n";
        $message .= "• Trip ID: {$tripId}\n";
        $message .= "• Pickup Time: {$pickupTime}\n";
        $message .= "• Pickup Location: {$pickupLocation}\n\n";
        $message .= "🔐 *Start Trip OTP:* {$otp}\n\n";
        $message .= "📱 *QR Code:* {$qrCode}\n\n";
        $message .= "Please show this OTP/QR code to your driver when they arrive.\n\n";
        $message .= "Thank you for choosing {$companyName}!";
    } elseif ($messageType === 'end_trip') {
        $otp = $tripData['end_otp'];
        $qrCode = $tripData['end_qr_code'];
        $passengerName = $tripData['last_passenger_name'];
        $dropTime = date('h:i A', strtotime($tripData['scheduled_drop_time']));
        $dropLocation = $tripData['last_passenger_address'];

        $message = "🏁 *{$companyName} - Trip Completion*\n\n";
        $message .= "Dear {$passengerName},\n\n";
        $message .= "Your trip is approaching completion!\n\n";
        $message .= "📋 *Trip Details:*\n";
        $message .= "• Trip ID: {$tripId}\n";
        $message .= "• Drop Time: {$dropTime}\n";
        $message .= "• Drop Location: {$dropLocation}\n\n";
        $message .= "🔐 *End Trip OTP:* {$otp}\n\n";
        $message .= "📱 *QR Code:* {$qrCode}\n\n";
        $message .= "Please show this OTP/QR code to your driver for trip completion.\n\n";
        $message .= "Safe travels with {$companyName}!";
    } else {
        return "Invalid message type";
    }

    return $message;
}

/**
 * Format phone number to international format
 *
 * @param string $phoneNumber
 * @return string|null
 */
function formatPhoneNumber($phoneNumber)
{
    // Remove all non-numeric characters
    $cleaned = preg_replace('/\D/', '', $phoneNumber);

    // Check if it's a valid Indian mobile number
    if (preg_match('/^[6-9]\d{9}$/', $cleaned)) {
        // Add Indian country code
        return '91' . $cleaned;
    } elseif (preg_match('/^91[6-9]\d{9}$/', $cleaned)) {
        // Already has Indian country code
        return $cleaned;
    } elseif (preg_match('/^\d{10,15}$/', $cleaned)) {
        // Assume it's already in international format
        return $cleaned;
    }

    // Invalid number
    return null;
}

/**
 * Log WhatsApp message for tracking
 *
 * @param string $phoneNumber
 * @param string $messageType
 * @param int $tripId
 * @param string $status
 * @param string|null $errorMessage
 */
function logWhatsAppMessage($phoneNumber, $messageType, $tripId, $status, $errorMessage = null)
{
    try {
        $conn = getDBConnection();

        // Create whatsapp_logs table if it doesn't exist
        $conn->exec("CREATE TABLE IF NOT EXISTS whatsapp_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            phone_number VARCHAR(20) NOT NULL,
            message_type ENUM('start_trip', 'end_trip') NOT NULL,
            trip_id INT NOT NULL,
            status ENUM('sent', 'failed', 'pending') NOT NULL,
            error_message TEXT,
            sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_trip_id (trip_id),
            INDEX idx_phone_number (phone_number)
        )");

        $stmt = $conn->prepare("INSERT INTO whatsapp_logs (phone_number, message_type, trip_id, status, error_message) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$phoneNumber, $messageType, $tripId, $status, $errorMessage]);
    } catch (PDOException $e) {
        error_log("Failed to log WhatsApp message: " . $e->getMessage());
    }
}

/**
 * Send start trip WhatsApp message
 *
 * @param array $tripData
 * @return bool
 */
function sendStartTripWhatsApp($tripData)
{
    if (empty($tripData['first_passenger_phone'])) {
        error_log("No first passenger phone number for trip ID: " . $tripData['id']);
        return false;
    }

    return sendWhatsAppMessage($tripData['first_passenger_phone'], 'start_trip', $tripData);
}

/**
 * Send end trip WhatsApp message
 *
 * @param array $tripData
 * @return bool
 */
function sendEndTripWhatsApp($tripData)
{
    if (empty($tripData['last_passenger_phone'])) {
        error_log("No last passenger phone number for trip ID: " . $tripData['id']);
        return false;
    }

    return sendWhatsAppMessage($tripData['last_passenger_phone'], 'end_trip', $tripData);
}

/**
 * Alternative implementation using Twilio (if you prefer Twilio over WhatsApp Business API)
 *
 * Uncomment and configure if using Twilio
 */
/*
define('TWILIO_SID', 'your_twilio_sid');
define('TWILIO_AUTH_TOKEN', 'your_twilio_auth_token');
define('TWILIO_WHATSAPP_NUMBER', 'whatsapp:+14155238886'); // Twilio sandbox number

function sendWhatsAppMessageTwilio($phoneNumber, $message)
{
    require_once 'vendor/autoload.php'; // If using Composer

    $client = new Twilio\Rest\Client(TWILIO_SID, TWILIO_AUTH_TOKEN);

    try {
        $message = $client->messages->create(
            "whatsapp:$phoneNumber",
            [
                'from' => TWILIO_WHATSAPP_NUMBER,
                'body' => $message
            ]
        );

        logWhatsAppMessage($phoneNumber, 'custom', 0, 'sent');
        return true;
    } catch (Exception $e) {
        logWhatsAppMessage($phoneNumber, 'custom', 0, 'failed', $e->getMessage());
        return false;
    }
}
*/
