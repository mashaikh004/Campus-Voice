<?php
require_once __DIR__ . '/../config.php';

/*
Recommended in config.php (development only):
ini_set('display_errors', 1);
error_reporting(E_ALL);
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$mysqli->set_charset('utf8mb4');
*/

// If already logged in, redirect based on role
if (!empty($_SESSION['user_id'])) {
    if (!empty($_SESSION['role']) && $_SESSION['role'] === 'admin') {
        header('Location: /admin/dashboard.php');
    } else {
        header('Location: /student/dashboard.php');
    }
    exit;
}

$err = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1) Read and sanitize inputs (names match your table and earlier HTML)
    $full_name  = trim($_POST['full_name'] ?? '');
    $email      = trim($_POST['email'] ?? '');
    $student_id = trim($_POST['student_id'] ?? '');
    $department = trim($_POST['department'] ?? '');
    $year_sem   = trim($_POST['year_sem'] ?? '');
    $password   = $_POST['password'] ?? '';
    $confirm    = $_POST['confirm_password'] ?? '';

    // 2) Basic validation
    if ($full_name === '') {
        $err[] = 'Full name required';
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $err[] = 'Valid email required';
    }
    if (strlen($password) < 6) {
        $err[] = 'Password must be at least 6 characters';
    }
    if ($password !== $confirm) {
        $err[] = 'Passwords do not match';
    }

    // 3) Unique email check
    if (!$err) {
        $stmt = $mysqli->prepare('SELECT id FROM users WHERE email=? LIMIT 1');
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $stmt->store_result();
        if ($stmt->num_rows > 0) {
            $err[] = 'Email already registered';
        }
        $stmt->close();
    }

    // 4) Insert user
    if (!$err) {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $role = 'student'; // default per your schema

        $stmt = $mysqli->prepare('
            INSERT INTO users (full_name, email, student_id, department, year_sem, password_hash, role)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ');
        $stmt->bind_param(
            'sssssss',
            $full_name,
            $email,
            $student_id,
            $department,
            $year_sem,
            $hash,
            $role
        );

        if ($stmt->execute()) {
            // Success: set a flash and redirect to login
            set_flash('success', 'Registration successful. Please login.');
            $stmt->close();
            header('Location: /auth/login.php');
            exit;
        } else {
            // If something goes wrong at DB level
            $err[] = 'Registration failed: ' . $stmt->error;
            $stmt->close();
        }
    }
}
?>
<!-- Paste your register page HTML below (or keep this minimal form for testing) -->
<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <title>Register</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <style>body{font-family:system-ui,-apple-system,Segoe UI,Roboto,sans-serif;margin:20px}input,select{display:block;margin:.4rem 0;padding:.5rem;width:280px}</style>
  <link rel="icon" type="image/png" href="partials/icon.jpg" sizes="32x32">
</head>
<body>
  <h2>Create Account</h2>

  <?php if ($msg = get_flash('success')): ?>
    <div style="color:green;"><?= htmlspecialchars($msg) ?></div>
  <?php endif; ?>

  <?php if ($err): ?>
    <div style="color:red;">
      <?php foreach ($err as $e) { echo '<div>'.htmlspecialchars($e).'</div>'; } ?>
    </div>
  <?php endif; ?>

  <form method="post" action="/auth/register.php" novalidate>
    <label>Full Name</label>
    <input name="full_name" placeholder="Your Name" required>

    <label>Email</label>
    <input name="email" type="email" placeholder="you@college.edu" required>

    <label>Student ID / Roll No</label>
    <input name="student_id" placeholder="Roll No">

    <label>Department</label>
    <input name="department" placeholder="e.g. BCA">

    <label>Year / Semester</label>
    <select name="year_sem">
      <option value="">Select</option>
      <option value="1st Year">1st Year</option>
      <option value="2nd Year">2nd Year</option>
      <option value="3rd Year">3rd Year</option>
      <option value="4th Year">4th Year</option>
    </select>

    <label>Password</label>
    <input name="password" type="password" placeholder="Choose a password" required>

    <label>Confirm Password</label>
    <input name="confirm_password" type="password" placeholder="Confirm password" required>

    <button type="submit">Register</button>
  </form>

  <p><a href="/auth/login.php">Already have an account? Login</a></p>
</body>
</html>
