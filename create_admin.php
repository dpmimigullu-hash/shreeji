<?php
// Script to create admin user in the users table

require_once 'includes/config.php';

try {
    $conn = getDBConnection();

    echo "<h2>Creating Admin User</h2>";

    // Check if admin user already exists
    $stmt = $conn->prepare("SELECT id FROM users WHERE username = 'admin'");
    $stmt->execute();
    $existingAdmin = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($existingAdmin) {
        echo "<p style='color: blue;'>ℹ Admin user already exists with ID: " . $existingAdmin['id'] . "</p>";
    } else {
        // Insert admin user
        $stmt = $conn->prepare("
            INSERT INTO users (
                username, password, name, email, role, branch_id
            ) VALUES (
                'admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'System Admin', 'admin@transport.com', 'admin', 1
            )
        ");

        $stmt->execute();

        $adminId = $conn->lastInsertId();
        echo "<p style='color: green;'>✓ Admin user created successfully!</p>";
        echo "<p><strong>Admin ID:</strong> " . $adminId . "</p>";
        echo "<p><strong>Username:</strong> admin</p>";
        echo "<p><strong>Password:</strong> password</p>";
        echo "<p><strong>Email:</strong> admin@transport.com</p>";
    }

    echo "<h3>Admin Login Details</h3>";
    echo "<ul>";
    echo "<li><strong>Username:</strong> admin</li>";
    echo "<li><strong>Password:</strong> password</li>";
    echo "<li><strong>Role:</strong> admin</li>";
    echo "</ul>";

    echo "<p>You can now login at: <a href='login.php'>Login Page</a></p>";
} catch (PDOException $e) {
    echo "<h3 style='color: red;'>Error creating admin user!</h3>";
    echo "<p>Error: " . $e->getMessage() . "</p>";
}

