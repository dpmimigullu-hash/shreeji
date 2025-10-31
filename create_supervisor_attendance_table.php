<?php
// Create supervisor attendance table
require_once 'includes/config.php';

try {
    $conn = getDBConnection();

    $sql = "CREATE TABLE IF NOT EXISTS supervisor_attendance (
        id INT PRIMARY KEY AUTO_INCREMENT,
        supervisor_id INT NOT NULL,
        date DATE NOT NULL,
        status ENUM('present', 'absent', 'late', 'early_departure') NOT NULL,
        check_in_time TIME,
        check_out_time TIME,
        latitude DECIMAL(10, 8),
        longitude DECIMAL(11, 8),
        location_verified BOOLEAN DEFAULT FALSE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY unique_supervisor_date (supervisor_id, date),
        FOREIGN KEY (supervisor_id) REFERENCES users(id)
    )";

    $conn->exec($sql);
    echo "Supervisor attendance table created successfully!";
} catch (PDOException $e) {
    echo "Error creating table: " . $e->getMessage();
}

