<?php
// test_hash.php - Simple hash generator
$password = 'campus';
$hash = password_hash($password, PASSWORD_DEFAULT);

echo "<h3>Password Hash Generator</h3>";
echo "<p><strong>Password:</strong> $password</p>";
echo "<p><strong>Generated Hash:</strong></p>";
echo "<code>$hash</code>";
echo "<hr>";
echo "<h4>SQL to run in phpMyAdmin:</h4>";
echo "<textarea rows='5' cols='80'>DELETE FROM users WHERE email = 'admin@campus.com';
INSERT INTO users (full_name, email, password_hash, role) VALUES 
('Admin User', 'admin@campus.com', '$hash', 'admin');</textarea>";

echo "<hr>";
echo "<h4>Test Verification:</h4>";
if (password_verify('campus', $hash)) {
    echo "<div style='color: green;'>✅ Hash is VALID for password 'campus'</div>";
} else {
    echo "<div style='color: red;'>❌ Hash verification FAILED</div>";
}
?>