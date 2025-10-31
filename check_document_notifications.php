<?php

/**
 * Cron job script to check and send document expiry notifications
 * This should be run daily via cron job
 *
 * Example cron job (run daily at 9 AM):
 * 0 9 * * * php /path/to/this/file.php
 */

require_once 'includes/config.php';
require_once 'includes/functions.php';
require_once 'includes/whatsapp.php';
require_once 'includes/document_notifications.php';

// Run the notification check
$results = runDailyDocumentNotifications();

// Output results for logging
echo "Document expiry notification check completed:\n";
echo "Vehicles checked: " . $results['checked'] . "\n";
echo "Notifications sent: " . $results['notifications_sent'] . "\n";

if (!empty($results['errors'])) {
    echo "Errors encountered:\n";
    foreach ($results['errors'] as $error) {
        echo "- $error\n";
    }
}

echo "\nScript completed at: " . date('Y-m-d H:i:s') . "\n";

