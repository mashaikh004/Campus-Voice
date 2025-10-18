<?php
// debug.php - Complete debugging script
echo "<h2>🔍 Campus Voice Debug</h2>";

// Step 1: Check config.php
echo "<h3>Step 1: Config Check</h3>";
if (file_exists('config.php')) {
    echo "✅ config.php exists<br>";
    try {
        require_once 'config.php';
        echo "✅ config.php loaded successfully<br>";
    } catch (Exception $e) {
        echo "❌ Config error: " . $e->getMessage() . "<br>";
        exit;
    }
} else {
    echo "❌ config.php NOT found!<br>";
    exit;
}

// Step 2: Database connection test
echo "<h3>Step 2: Database Connection</h3>";
try {
    if (isset($mysqli) && $mysqli->ping()) {
        echo "✅ Database connected successfully<br>";
        echo "Database: " . $DB_NAME . "<br>";
    } else {
        echo "❌ Database connection failed<br>";
        exit;
    }
} catch (Exception $e) {
    echo "❌ Database error: " . $e->getMessage() . "<br>";
    exit;
}

// Step 3: Check users table
echo "<h3>Step 3: Users Table Check</h3>";
try {
    $result = $mysqli->query("DESCRIBE users");
    if ($result) {
        echo "✅ Users table exists<br>";
        echo "<table border='1'><tr><th>Field</th><th>Type</th><th>Key</th></tr>";
        while ($row = $result->fetch_assoc()) {
            echo "<tr><td>{$row['Field']}</td><td>{$row['Type']}</td><td>{$row['Key']}</td></tr>";
        }
        echo "</table>";
    } else {
        echo "❌ Users table does NOT exist!<br>";
        echo "Creating users table...<br>";
        
        $create_table = "
        CREATE TABLE users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            full_name VARCHAR(150) NOT NULL,
            email VARCHAR(150) NOT NULL UNIQUE,
            student_id VARCHAR(50) DEFAULT NULL,
            department VARCHAR(100) DEFAULT NULL,
            year_sem VARCHAR(50) DEFAULT NULL,
            password_hash VARCHAR(255) NOT NULL,
            role ENUM('student','admin') NOT NULL DEFAULT 'student',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )";
        
        if ($mysqli->query($create_table)) {
            echo "✅ Users table created!<br>";
        } else {
            echo "❌ Error creating table: " . $mysqli->error . "<br>";
        }
    }
} catch (Exception $e) {
    echo "❌ Table check error: " . $e->getMessage() . "<br>";
}

// Step 4: Check existing admin
echo "<h3>Step 4: Admin Account Check</h3>";
try {
    $stmt = $mysqli->prepare("SELECT * FROM users WHERE email = ?");
    $admin_email = 'admin@campus.com';
    $stmt->bind_param('s', $admin_email);
    $stmt->execute();
    $result = $stmt->get_result();
    $admin = $result->fetch_assoc();
    $stmt->close();
    
    if ($admin) {
        echo "✅ Admin found: " . htmlspecialchars($admin['full_name']) . "<br>";
        echo "Email: " . htmlspecialchars($admin['email']) . "<br>";
        echo "Role: " . htmlspecialchars($admin['role']) . "<br>";
        
        // Test password
        if (password_verify('campus', $admin['password_hash'])) {
            echo "✅ Password 'campus' is CORRECT!<br>";
        } else {
            echo "❌ Password 'campus' is WRONG!<br>";
            echo "Updating password...<br>";
            
            $new_hash = password_hash('campus', PASSWORD_DEFAULT);
            $update_stmt = $mysqli->prepare("UPDATE users SET password_hash = ? WHERE email = ?");
            $update_stmt->bind_param('ss', $new_hash, $admin_email);
            
            if ($update_stmt->execute()) {
                echo "✅ Password updated!<br>";
            } else {
                echo "❌ Password update failed: " . $update_stmt->error . "<br>";
            }
            $update_stmt->close();
        }
    } else {
        echo "❌ Admin NOT found! Creating...<br>";
        
        $password_hash = password_hash('campus', PASSWORD_DEFAULT);
        $stmt = $mysqli->prepare("INSERT INTO users (full_name, email, password_hash, role) VALUES (?, ?, ?, ?)");
        $full_name = 'Admin User';
        $role = 'admin';
        $stmt->bind_param('ssss', $full_name, $admin_email, $password_hash, $role);
        
        if ($stmt->execute()) {
            echo "✅ Admin created successfully!<br>";
            echo "ID: " . $mysqli->insert_id . "<br>";
        } else {
            echo "❌ Admin creation failed: " . $stmt->error . "<br>";
        }
        $stmt->close();
    }
} catch (Exception $e) {
    echo "❌ Admin check error: " . $e->getMessage() . "<br>";
}

// Step 5: Test login simulation
echo "<h3>Step 5: Login Test Simulation</h3>";
try {
    $test_email = 'admin@campus.com';
    $test_password = 'campus';
    
    $stmt = $mysqli->prepare('SELECT id, full_name, email, password_hash, role FROM users WHERE email=? LIMIT 1');
    $stmt->bind_param('s', $test_email);
    $stmt->execute();
    $res = $stmt->get_result();
    $user = $res->fetch_assoc();
    $stmt->close();
    
    if ($user) {
        echo "✅ User found in database<br>";
        if (password_verify($test_password, $user['password_hash'])) {
            echo "✅ Password verification SUCCESS!<br>";
            echo "User ID: " . $user['id'] . "<br>";
            echo "Role: " . $user['role'] . "<br>";
            echo "<strong style='color: green;'>LOGIN SHOULD WORK NOW!</strong><br>";
        } else {
            echo "❌ Password verification FAILED!<br>";
            echo "Stored hash: " . substr($user['password_hash'], 0, 30) . "...<br>";
        }
    } else {
        echo "❌ User NOT found during login test<br>";
    }
} catch (Exception $e) {
    echo "❌ Login test error: " . $e->getMessage() . "<br>";
}

// Step 6: Session test
echo "<h3>Step 6: Session Test</h3>";
if (session_status() === PHP_SESSION_ACTIVE) {
    echo "✅ Session is active<br>";
} else {
    echo "❌ Session not active<br>";
}

echo "<hr>";
echo "<h3>🎯 Final Test</h3>";
echo "<p><strong>Login Credentials:</strong></p>";
echo "<p>Email: admin@campus.com</p>";
echo "<p>Password: campus</p>";
echo "<p><a href='/index.php' style='background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>🚀 TEST LOGIN NOW</a></p>";

echo "<hr>";
echo "<p><em>If login still fails, copy-paste the exact error message you see.</em></p>";
?>