<?php
// Script to create WhatsApp-related tables
require_once 'includes/config.php';

try {
    $conn = getDBConnection();

    echo "<h2>Creating WhatsApp Tables</h2>";

    // Create whatsapp_config table
    echo "<p>Creating whatsapp_config table...</p>";
    $conn->exec("CREATE TABLE IF NOT EXISTS whatsapp_config (
        id INT PRIMARY KEY DEFAULT 1,
        api_key VARCHAR(255) NOT NULL,
        api_url VARCHAR(500) NOT NULL,
        phone_number_id VARCHAR(100) NOT NULL,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        updated_by INT NOT NULL,
        FOREIGN KEY (updated_by) REFERENCES users(id)
    )");
    echo "<p style='color: green;'>✓ whatsapp_config table created</p>";

    // Create whatsapp_logs table
    echo "<p>Creating whatsapp_logs table...</p>";
    $conn->exec("CREATE TABLE IF NOT EXISTS whatsapp_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        phone_number VARCHAR(20) NOT NULL,
        message_type ENUM('start_trip', 'end_trip') NOT NULL,
        trip_id INT NOT NULL,
        status ENUM('sent', 'failed', 'pending') NOT NULL,
        error_message TEXT,
        sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_trip_id (trip_id),
        INDEX idx_phone_number (phone_number),
        FOREIGN KEY (trip_id) REFERENCES trips(id)
    )");
    echo "<p style='color: green;'>✓ whatsapp_logs table created</p>";

    // Insert default config if not exists
    echo "<p>Checking for default configuration...</p>";
    $stmt = $conn->query("SELECT COUNT(*) as count FROM whatsapp_config");
    $count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

    if ($count == 0) {
        // Get admin user ID
        $stmt = $conn->prepare("SELECT id FROM users WHERE role = 'admin' LIMIT 1");
        $stmt->execute();
        $admin = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($admin) {
            $conn->exec("INSERT INTO whatsapp_config (api_key, api_url, phone_number_id, updated_by) VALUES
                        ('your_whatsapp_api_key_here', 'https://api.whatsapp.com/v1/messages', 'your_phone_number_id_here', {$admin['id']})");
            echo "<p style='color: green;'>✓ Default WhatsApp configuration inserted</p>";
        }
    } else {
        echo "<p style='color: blue;'>ℹ WhatsApp configuration already exists</p>";
    }

    echo "<h3 style='color: green;'>WhatsApp tables created successfully!</h3>";
    echo "<p>You can now access the WhatsApp Admin panel at: <a href='whatsapp_admin.php'>WhatsApp Admin</a></p>";
} catch (PDOException $e) {
    echo "<h3 style='color: red;'>Error creating WhatsApp tables!</h3>";
    echo "<p>Error: " . $e->getMessage() . "</p>";
    echo "<p>Please check your database connection and try again.</p>";
}

