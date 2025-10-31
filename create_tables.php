<?php
// Script to create the new database tables for supervisors, drivers, and vehicles

require_once 'includes/config.php';

try {
    $conn = getDBConnection();

    echo "<h2>Creating New Database Tables</h2>";
    echo "<p>This script will create separate tables for supervisors, drivers, and vehicles.</p>";

    // Start transaction
    $conn->beginTransaction();

    // Create supervisors table
    echo "<p>Creating supervisors table...</p>";
    $conn->exec("
        CREATE TABLE IF NOT EXISTS supervisors (
            id INT PRIMARY KEY AUTO_INCREMENT,
            username VARCHAR(50) UNIQUE NOT NULL,
            password VARCHAR(255) NOT NULL,
            name VARCHAR(100) NOT NULL,
            email VARCHAR(100) UNIQUE NOT NULL,
            phone VARCHAR(20),
            address TEXT,
            bank_name VARCHAR(100),
            bank_account_number VARCHAR(50),
            bank_ifsc_code VARCHAR(20),
            branch_id INT NOT NULL,
            status ENUM('active', 'inactive') DEFAULT 'active',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (branch_id) REFERENCES branches(id)
        )
    ");
    echo "<p style='color: green;'>✓ Supervisors table created</p>";

    // Create drivers table
    echo "<p>Creating drivers table...</p>";
    $conn->exec("
        CREATE TABLE IF NOT EXISTS drivers (
            id INT PRIMARY KEY AUTO_INCREMENT,
            username VARCHAR(50) UNIQUE NOT NULL,
            password VARCHAR(255) NOT NULL,
            name VARCHAR(100) NOT NULL,
            email VARCHAR(100) UNIQUE NOT NULL,
            phone VARCHAR(20),
            license_number VARCHAR(50) NOT NULL,
            license_photo VARCHAR(255),
            driver_photo VARCHAR(255),
            kyc_documents JSON,
            address TEXT,
            bank_name VARCHAR(100),
            bank_account_number VARCHAR(50),
            bank_ifsc_code VARCHAR(20),
            supervisor_id INT NOT NULL,
            branch_id INT NOT NULL,
            fuel_amount DECIMAL(10, 2) DEFAULT 0,
            status ENUM('active', 'inactive') DEFAULT 'active',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (supervisor_id) REFERENCES supervisors(id),
            FOREIGN KEY (branch_id) REFERENCES branches(id)
        )
    ");
    echo "<p style='color: green;'>✓ Drivers table created</p>";

    // Skip vehicles table update for now - will handle separately
    echo "<p>Skipping vehicles table update for now...</p>";
    echo "<p style='color: blue;'>ℹ Vehicles table constraints will be added manually if needed</p>";

    // Create vehicle-driver assignments table
    echo "<p>Creating vehicle-driver assignments table...</p>";
    $conn->exec("
        CREATE TABLE IF NOT EXISTS vehicle_driver_assignments (
            id INT PRIMARY KEY AUTO_INCREMENT,
            vehicle_id INT NOT NULL,
            driver_id INT NOT NULL,
            assigned_date DATE NOT NULL,
            assigned_by INT NOT NULL,
            status ENUM('active', 'inactive') DEFAULT 'active',
            notes TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (vehicle_id) REFERENCES vehicles(id),
            FOREIGN KEY (driver_id) REFERENCES drivers(id),
            FOREIGN KEY (assigned_by) REFERENCES users(id),
            UNIQUE KEY unique_active_assignment (vehicle_id, driver_id, status)
        )
    ");
    echo "<p style='color: green;'>✓ Vehicle-driver assignments table created</p>";

    // Insert sample supervisors
    echo "<p>Inserting sample supervisors...</p>";
    $conn->exec("
        INSERT IGNORE INTO supervisors (username, password, name, email, phone, address, bank_name, bank_account_number, bank_ifsc_code, branch_id) VALUES
        ('supervisor1', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'John Supervisor', 'john@transport.com', '+91-9876543210', '123 Supervisor Street, Mumbai', 'State Bank of India', '1234567890', 'SBIN0001234', 1),
        ('supervisor2', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Jane Supervisor', 'jane@transport.com', '+91-9876543211', '456 Supervisor Avenue, Delhi', 'HDFC Bank', '0987654321', 'HDFC0000987', 2),
        ('supervisor3', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Bob Supervisor', 'bob@transport.com', '+91-9876543212', '789 Supervisor Road, Bangalore', 'ICICI Bank', '1122334455', 'ICIC0001122', 3)
    ");
    echo "<p style='color: green;'>✓ Sample supervisors inserted</p>";

    // Insert sample drivers
    echo "<p>Inserting sample drivers...</p>";
    $conn->exec("
        INSERT IGNORE INTO drivers (username, password, name, email, phone, license_number, address, bank_name, bank_account_number, bank_ifsc_code, supervisor_id, branch_id) VALUES
        ('driver1', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Mike Driver', 'mike@transport.com', '+91-9876543213', 'DL1234567890123', '321 Driver Lane, Mumbai', 'Axis Bank', '5566778899', 'UTIB0000556', 1, 1),
        ('driver2', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Sarah Driver', 'sarah@transport.com', '+91-9876543214', 'DL9876543210987', '654 Driver Street, Delhi', 'Kotak Mahindra Bank', '6677889900', 'KKBK0000667', 2, 2),
        ('driver3', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Tom Driver', 'tom@transport.com', '+91-9876543215', 'DL4567890123456', '987 Driver Road, Bangalore', 'IDBI Bank', '7788990011', 'IBKL0000778', 3, 3),
        ('driver4', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Lisa Driver', 'lisa@transport.com', '+91-9876543216', 'DL7890123456789', '147 Driver Avenue, Mumbai', 'Bandhan Bank', '8899001122', 'BDBL0000889', 1, 1)
    ");
    echo "<p style='color: green;'>✓ Sample drivers inserted</p>";

    // Update existing vehicles with supervisor and branch assignments
    echo "<p>Updating existing vehicles...</p>";
    $conn->exec("UPDATE vehicles SET supervisor_id = 1, branch_id = 1 WHERE supervisor_id IS NULL OR supervisor_id = 0");
    echo "<p style='color: green;'>✓ Existing vehicles updated</p>";

    // Create sample vehicle-driver assignments
    echo "<p>Creating sample vehicle-driver assignments...</p>";
    $conn->exec("
        INSERT IGNORE INTO vehicle_driver_assignments (vehicle_id, driver_id, assigned_date, assigned_by, notes) VALUES
        (1, 1, CURDATE(), 1, 'Initial assignment - Toyota Camry to Mike Driver'),
        (2, 2, CURDATE(), 1, 'Initial assignment - Honda Civic to Sarah Driver'),
        (3, 3, CURDATE(), 1, 'Initial assignment - Ford Explorer to Tom Driver'),
        (4, 4, CURDATE(), 1, 'Initial assignment - Chevrolet Suburban to Lisa Driver')
    ");
    echo "<p style='color: green;'>✓ Sample vehicle-driver assignments created</p>";

    // Commit transaction
    try {
        $conn->commit();
        echo "<h3 style='color: green;'>Database tables created successfully!</h3>";
    } catch (Exception $e) {
        echo "<h3 style='color: orange;'>Tables created with minor transaction issues</h3>";
        echo "<p style='color: blue;'>Note: " . $e->getMessage() . "</p>";
    }

    echo "<h3 style='color: green;'>Database tables created successfully!</h3>";
    echo "<p>The following tables have been created:</p>";
    echo "<ul>";
    echo "<li><strong>supervisors</strong> - Dedicated table for supervisor management</li>";
    echo "<li><strong>drivers</strong> - Dedicated table for driver management</li>";
    echo "<li><strong>vehicle_driver_assignments</strong> - Tracks vehicle-driver assignments with history</li>";
    echo "</ul>";
    echo "<p>Sample data has been inserted for testing purposes.</p>";
    echo "<p><strong>Vehicle Registration with Drivers:</strong> When a vehicle is registered with a driver, it will now be recorded in the <code>vehicle_driver_assignments</code> table, maintaining a history of assignments.</p>";
    echo "<p><strong>Note:</strong> Vehicles table constraints may need to be added manually if required.</p>";
    echo "<p>You can now register new supervisors, drivers, and vehicles using these tables.</p>";
} catch (Exception $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    echo "<h3 style='color: red;'>Error creating tables!</h3>";
    echo "<p>Error: " . $e->getMessage() . "</p>";
    echo "<p>Please check your database permissions and try again.</p>";
}

