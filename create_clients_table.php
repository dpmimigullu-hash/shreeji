<?php
// Script to create the clients table

require_once 'includes/config.php';

try {
    $conn = getDBConnection();

    echo "<h2>Creating Clients Table</h2>";
    echo "<p>This script will create the clients table for client billing.</p>";

    // Create clients table
    $conn->exec("
        CREATE TABLE IF NOT EXISTS clients (
            id INT PRIMARY KEY AUTO_INCREMENT,
            name VARCHAR(100) NOT NULL,
            email VARCHAR(100),
            phone VARCHAR(20),
            company VARCHAR(100),
            address TEXT,
            city VARCHAR(50),
            state VARCHAR(50),
            pincode VARCHAR(10),
            latitude DECIMAL(10, 8),
            longitude DECIMAL(11, 8),
            pan VARCHAR(20),
            gst VARCHAR(20),
            tan VARCHAR(20),
            status ENUM('active', 'inactive') DEFAULT 'active',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )
    ");

    echo "<h3 style='color: green;'>Clients table created successfully!</h3>";
    echo "<p>You can now access the clients page at: <a href='clients.php'>Clients Management</a></p>";
} catch (Exception $e) {
    echo "<h3 style='color: red;'>Error creating clients table!</h3>";
    echo "<p>Error: " . $e->getMessage() . "</p>";
    echo "<p>Please check your database permissions and try again.</p>";
}

