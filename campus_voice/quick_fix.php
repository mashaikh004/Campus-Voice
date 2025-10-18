<?php
// quick_fix.php - Run this once
require_once 'config.php';

// Create admin with correct password
$email = 'admin@campus.com';
$password = 'campus';
$hash = password_hash($password, PASSWORD_DEFAULT);

$mysqli->query("DELETE FROM users WHERE email = '$email'");
$stmt = $mysqli->prepare("INSERT INTO users (full_name, email, password_hash, role) VALUES (?, ?, ?, ?)");
$full_name = 'Admin User';
$role = 'admin';
$stmt->bind_param('ssss', $full_name, $email, $hash, $role);

if ($stmt->execute()) {
    echo "✅ Admin created! Login with: admin@campus.com / campus";
} else {
    echo "❌ Error: " . $stmt->error;
}
?>

<p><a href="/index.php">Login Now</a></p>