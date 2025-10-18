<?php
// create_admin.php - Run this once to create admin
require_once 'config.php'; // Adjust path if needed

$password = 'campus';
$hash = password_hash($password, PASSWORD_DEFAULT);

echo "<h3>Creating Admin Account...</h3>";

try {
    // Delete existing admin if exists
    $stmt = $mysqli->prepare("DELETE FROM users WHERE email = ?");
    $email = 'admin@campus.com';
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $stmt->close();
    
    // Insert new admin
    $stmt = $mysqli->prepare("INSERT INTO users (full_name, email, password_hash, role) VALUES (?, ?, ?, ?)");
    $full_name = 'Admin User';
    $role = 'admin';
    $stmt->bind_param('ssss', $full_name, $email, $hash, $role);
    
    if ($stmt->execute()) {
        echo "<div style='color: green;'>✅ Admin created successfully!</div>";
        echo "<p><strong>Login Details:</strong></p>";
        echo "<p>Email: admin@campus.com</p>";
        echo "<p>Password: campus</p>";
        echo "<p><a href='/index.php'>Go to Login Page</a></p>";
    } else {
        echo "<div style='color: red;'>❌ Error: " . $stmt->error . "</div>";
    }
    $stmt->close();
    
} catch (Exception $e) {
    echo "<div style='color: red;'>❌ Database Error: " . $e->getMessage() . "</div>";
}

echo "<hr>";
echo "<p><strong>Generated Hash:</strong> $hash</p>";
echo "<p><em>You can delete this file after running it.</em></p>";
?>