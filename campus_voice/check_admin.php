<?php
// check_admin.php - Run this to check admin account
require_once 'config.php';

echo "<h3>Checking Admin Account...</h3>";

try {
    // Check if admin exists
    $stmt = $mysqli->prepare("SELECT id, full_name, email, password_hash, role FROM users WHERE email = ?");
    $email = 'admin@campus.com';
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $result = $stmt->get_result();
    $admin = $result->fetch_assoc();
    $stmt->close();
    
    if ($admin) {
        echo "<div style='color: green;'>✅ Admin account found!</div>";
        echo "<table border='1' cellpadding='10'>";
        echo "<tr><td><strong>ID</strong></td><td>" . $admin['id'] . "</td></tr>";
        echo "<tr><td><strong>Name</strong></td><td>" . htmlspecialchars($admin['full_name']) . "</td></tr>";
        echo "<tr><td><strong>Email</strong></td><td>" . htmlspecialchars($admin['email']) . "</td></tr>";
        echo "<tr><td><strong>Role</strong></td><td>" . htmlspecialchars($admin['role']) . "</td></tr>";
        echo "<tr><td><strong>Password Hash</strong></td><td>" . substr($admin['password_hash'], 0, 20) . "...</td></tr>";
        echo "</table>";
        
        // Test password verification
        echo "<hr><h4>Testing Password 'campus':</h4>";
        if (password_verify('campus', $admin['password_hash'])) {
            echo "<div style='color: green;'>✅ Password 'campus' is CORRECT!</div>";
        } else {
            echo "<div style='color: red;'>❌ Password 'campus' is WRONG!</div>";
            echo "<p>Creating new hash for 'campus':</p>";
            $new_hash = password_hash('campus', PASSWORD_DEFAULT);
            echo "<code>$new_hash</code>";
            echo "<p><a href='#' onclick=\"updatePassword('$new_hash')\">Update Password Hash</a></p>";
        }
    } else {
        echo "<div style='color: red;'>❌ Admin account NOT found!</div>";
        echo "<p>Creating admin account now...</p>";
        
        // Create admin
        $password_hash = password_hash('campus', PASSWORD_DEFAULT);
        $stmt = $mysqli->prepare("INSERT INTO users (full_name, email, password_hash, role) VALUES (?, ?, ?, ?)");
        $full_name = 'Admin User';
        $role = 'admin';
        $stmt->bind_param('ssss', $full_name, $email, $password_hash, $role);
        
        if ($stmt->execute()) {
            echo "<div style='color: green;'>✅ Admin created successfully!</div>";
        } else {
            echo "<div style='color: red;'>❌ Error creating admin: " . $stmt->error . "</div>";
        }
        $stmt->close();
    }
    
} catch (Exception $e) {
    echo "<div style='color: red;'>❌ Database Error: " . $e->getMessage() . "</div>";
    echo "<p>Check your config.php database settings!</p>";
}

echo "<hr>";
echo "<h4>Login Test:</h4>";
echo "<p><strong>Email:</strong> admin@campus.com</p>";
echo "<p><strong>Password:</strong> campus</p>";
echo "<p><a href='/index.php'>Go to Login Page</a></p>";

?>

<script>
function updatePassword(hash) {
    if(confirm('Update password hash for admin?')) {
        fetch('', {
            method: 'POST', 
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: 'action=update_hash&hash=' + encodeURIComponent(hash)
        })
        .then(() => location.reload());
    }
}
</script>

<?php
// Handle password hash update
if ($_POST['action'] ?? '' === 'update_hash') {
    $new_hash = $_POST['hash'];
    $stmt = $mysqli->prepare("UPDATE users SET password_hash = ? WHERE email = ?");
    $email = 'admin@campus.com';
    $stmt->bind_param('ss', $new_hash, $email);
    if ($stmt->execute()) {
        echo "<div style='color: green;'>✅ Password hash updated!</div>";
    }
    $stmt->close();
    exit;
}
?>