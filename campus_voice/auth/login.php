<?php
// Save as quick_fix.php and run once
require_once __DIR__ . '/config.php';

echo "<h2>Database Quick Fix</h2>";

// 1. Check current table structure
echo "<h3>1. Current users table structure:</h3>";
$result = $mysqli->query("DESCRIBE users");
echo "<table border='1' cellpadding='5'>";
echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th></tr>";
while ($row = $result->fetch_assoc()) {
    echo "<tr>";
    echo "<td>{$row['Field']}</td>";
    echo "<td>{$row['Type']}</td>";
    echo "<td>{$row['Null']}</td>";
    echo "<td>{$row['Key']}</td>";
    echo "<td>{$row['Default']}</td>";
    echo "</tr>";
}
echo "</table><br>";

// 2. Check existing users
echo "<h3>2. Current users:</h3>";
$result = $mysqli->query("SELECT id, username, email, role FROM users");
if ($result->num_rows > 0) {
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>ID</th><th>Username</th><th>Email</th><th>Role</th></tr>";
    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>{$row['id']}</td>";
        echo "<td>{$row['username']}</td>";
        echo "<td>{$row['email']}</td>";
        echo "<td>{$row['role']}</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p style='color: red;'>❌ No users found!</p>";
}

// 3. Create admin user if not exists
echo "<h3>3. Creating admin user:</h3>";

// Check if admin exists
$check = $mysqli->prepare("SELECT id FROM users WHERE email = 'admin@campus.com' OR username = 'admin'");
$check->execute();
$admin_exists = $check->get_result()->fetch_assoc();
$check->close();

if (!$admin_exists) {
    // Create admin user
    $username = 'admin';
    $email = 'admin@campus.com';
    $password = password_hash('password', PASSWORD_DEFAULT);
    $full_name = 'System Administrator';
    $role = 'admin';
    
    // Check if we have password_hash column or password column
    $columns = $mysqli->query("SHOW COLUMNS FROM users LIKE 'password%'");
    $password_column = 'password';
    while ($col = $columns->fetch_assoc()) {
        if ($col['Field'] == 'password_hash') {
            $password_column = 'password_hash';
            break;
        }
    }
    
    $sql = "INSERT INTO users (username, email, {$password_column}, full_name, role, created_at) VALUES (?, ?, ?, ?, ?, NOW())";
    $stmt = $mysqli->prepare($sql);
    $stmt->bind_param('sssss', $username, $email, $password, $full_name, $role);
    
    if ($stmt->execute()) {
        echo "<p style='color: green;'>✅ Admin user created successfully!</p>";
        echo "<strong>Login Details:</strong><br>";
        echo "Email: admin@campus.com<br>";
        echo "Username: admin<br>";
        echo "Password: password<br>";
    } else {
        echo "<p style='color: red;'>❌ Error creating admin: " . $stmt->error . "</p>";
    }
    $stmt->close();
} else {
    echo "<p style='color: orange;'>ℹ️ Admin user already exists (ID: {$admin_exists['id']})</p>";
    
    // Update password to 'password' for testing
    $new_password = password_hash('password', PASSWORD_DEFAULT);
    
    // Check password column name
    $columns = $mysqli->query("SHOW COLUMNS FROM users LIKE 'password%'");
    $password_column = 'password';
    while ($col = $columns->fetch_assoc()) {
        if ($col['Field'] == 'password_hash') {
            $password_column = 'password_hash';
            break;
        }
    }
    
    $update_sql = "UPDATE users SET {$password_column} = ? WHERE email = 'admin@campus.com'";
    $stmt = $mysqli->prepare($update_sql);
    $stmt->bind_param('s', $new_password);
    
    if ($stmt->execute()) {
        echo "<p style='color: green;'>✅ Admin password reset to 'password'</p>";
    }
    $stmt->close();
}

echo "<br><h3>4. Test Login:</h3>";
echo "<form method='post' action='/auth/login.php'>";
echo "<input type='text' name='email' value='admin@campus.com' readonly> ";
echo "<input type='password' name='password' value='password' readonly> ";
echo "<button type='submit'>Test Login</button>";
echo "</form>";

echo "<br><a href='/auth/login.php'>Go to Login Page</a>";
?>