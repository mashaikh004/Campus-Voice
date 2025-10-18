<?php
require_once __DIR__ . '/config.php';
// If already logged in, send to dashboard
if (!empty($_SESSION['user_id'])) {
    header('Location: ' . (!empty($_SESSION['role']) && $_SESSION['role']==='admin' ? '/admin/dashboard.php' : '/student/dashboard.php'));
    exit;
}

$err = [];
$active_tab = 'login'; // default

// Allow server to set tab via query param (used after successful register)
if (isset($_GET['tab']) && in_array($_GET['tab'], ['login','register'], true)) {
    $active_tab = $_GET['tab'];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // ------------------- LOGIN HANDLER -------------------
    if ($action === 'login') {
        $active_tab = 'login';

        $email    = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $err[] = 'Valid email required';
        if ($password === '') $err[] = 'Password required';

        if (!$err) {
            $stmt = $mysqli->prepare('SELECT id, full_name, email, password_hash, role FROM users WHERE email=? LIMIT 1');
            $stmt->bind_param('s', $email);
            $stmt->execute();
            $res  = $stmt->get_result();
            $user = $res->fetch_assoc();
            $stmt->close();

            if ($user && password_verify($password, $user['password_hash'])) {
                session_regenerate_id(true);
                $_SESSION['user_id'] = (int)$user['id'];
                $_SESSION['name']    = $user['full_name'];
                $_SESSION['role']    = $user['role'];
                header('Location: ' . ($user['role']==='admin' ? '/admin/dashboard.php' : '/student/dashboard.php'));
                exit;
            } else {
                $err[] = 'Invalid credentials';
            }
        }
    }

    // ------------------- REGISTER HANDLER -------------------
    elseif ($action === 'register') {
        $active_tab = 'register'; // stay on register tab on validation errors

        $full_name  = trim($_POST['full_name'] ?? '');
        $email      = trim($_POST['email'] ?? '');
        $student_id = trim($_POST['student_id'] ?? '');
        $department = trim($_POST['department'] ?? '');
        $year_sem   = trim($_POST['year_sem'] ?? '');
        $password   = $_POST['password'] ?? '';
        $confirm    = $_POST['confirm_password'] ?? '';
        $role       = 'student';

        if ($full_name === '') $err[] = 'Full name required';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $err[] = 'Valid email required';
        if (strlen($password) < 6) $err[] = 'Password must be at least 6 characters';
        if ($password !== $confirm) $err[] = 'Passwords do not match';

        if (!$err) {
            // Unique email check
            $stmt = $mysqli->prepare('SELECT id FROM users WHERE email=? LIMIT 1');
            $stmt->bind_param('s', $email);
            $stmt->execute();
            $stmt->store_result();
            if ($stmt->num_rows > 0) $err[] = 'Email already registered';
            $stmt->close();
        }

        if (!$err) {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $mysqli->prepare('
                INSERT INTO users (full_name, email, student_id, department, year_sem, password_hash, role)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ');
            $stmt->bind_param('sssssss', $full_name, $email, $student_id, $department, $year_sem, $hash, $role);

            if ($stmt->execute()) {
                $stmt->close();
                set_flash('success', 'Registration successful. Please login.');
                // CRITICAL: redirect back to this same page with login tab active
                header('Location: /index.php?tab=login');
                exit;
            } else {
                $err[] = 'Registration failed: ' . $stmt->error;
                $stmt->close();
            }
        }
    }

    // Unknown action safeguard
    else {
        $err[] = 'Invalid action';
    }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Campus Voice - Login / Register</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="icon" type="image/png" href="partials/logo.jpg" sizes="32x32">
  <!-- Bootstrap -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <!-- Your CSS (adjust path if needed) -->
<link rel="stylesheet" href="/assets/css/styles.css">
  
</head>
<body>
<nav class="navbar navbar-expand-lg px-4">
  <a class="navbar-brand" href="#">Campus Voice</a>
  <div class="ms-auto d-flex align-items-center">
    <button id="darkToggle" class="btn btn-ghost btn-sm me-2">Dark</button>

  </div>
</nav>

<main class="py-4">
  <div class="container">
    <div class="row g-4">
      <!-- Left: Intro + Messages -->
      <div class="col-lg-5">
        <div class="container-card">
          <div style="height:280px; background:url('partials/image.jpg') center/cover no-repeat; border-radius:12px;"></div>
          <h3>Welcome to <span style="color:var(--primary)">Campus Voice</span></h3>
          <p class="small text-muted">Submit complaints, track progress and get timely updates. Designed for students & admins.</p>
          <hr>
          <h6 class="small text-muted">Why use Campus Voice?</h6>
          <ul class="small text-muted mb-0">
            <li>Track complaint status</li>
            <li>Get notifications on updates</li>
            <li>Mobile friendly & secure</li>
          </ul>

          <?php if ($msg = get_flash('success')): ?>
            <div class="alert alert-success mt-3"><?= htmlspecialchars($msg) ?></div>
          <?php endif; ?>
          <?php if ($err): ?>
            <div class="alert alert-danger mt-3">
              <?php foreach ($err as $e) { echo '<div>'.htmlspecialchars($e).'</div>'; } ?>
            </div>
          <?php endif; ?>
        </div>
      </div>

      <!-- Right: Tabs -->
      <div class="col-lg-7">
        <div class="container-card cv-form">
          <ul class="nav nav-tabs" id="authTab" role="tablist">
            <li class="nav-item" role="presentation">
              <button class="nav-link <?= $active_tab==='login'?'active':'' ?>" id="login-tab" data-bs-toggle="tab" data-bs-target="#login" type="button">Login</button>
            </li>
            <li class="nav-item" role="presentation">
              <button class="nav-link <?= $active_tab==='register'?'active':'' ?>" id="register-tab" data-bs-toggle="tab" data-bs-target="#register" type="button">Register</button>
            </li>
          </ul>

          <div class="tab-content p-3">
            <!-- Login -->
            <div class="tab-pane fade <?= $active_tab==='login'?'show active':'' ?>" id="login">
              <form method="post" action="/index.php" novalidate>
                <input type="hidden" name="action" value="login">
                <div class="form-row">
                  <div class="form-group">
                    <label for="login_email">Email</label>
                    <input id="login_email" name="email" type="email" class="form-control" placeholder="you@college.edu" required>
                  </div>
                  <div class="form-group">
                    <label for="login_password">Password</label>
                    <input id="login_password" name="password" type="password" class="form-control" placeholder="Password" required>
                  </div>
                </div>
                <div class="form-actions">
                  <button type="submit" class="btn-primary-custom">Login</button>
                  <a class="small text-muted ms-auto" href="#">Forgot password?</a>
                </div>
              </form>
            </div>

            <!-- Register -->
            <div class="tab-pane fade <?= $active_tab==='register'?'show active':'' ?>" id="register">
              <form method="post" action="/index.php" novalidate>
                <input type="hidden" name="action" value="register">
                <div class="form-row">
                  <div class="form-group">
                    <label for="reg_full_name">Full Name</label>
                    <input id="reg_full_name" name="full_name" type="text" class="form-control" placeholder="Your Name" required>
                  </div>
                  <div class="form-group">
                    <label for="reg_email">Email</label>
                    <input id="reg_email" name="email" type="email" class="form-control" placeholder="you@college.edu" required>
                  </div>
                  <div class="form-group">
                    <label for="reg_student_id">Student ID / Roll No</label>
                    <input id="reg_student_id" name="student_id" type="text" class="form-control" placeholder="Roll No">
                  </div>
                  <div class="form-group">
                    <label for="reg_department">Department</label>
                    <input id="reg_department" name="department" type="text" class="form-control" placeholder="e.g. BCA">
                  </div>
                  <div class="form-group">
                    <label for="reg_year_sem">Year / Semester</label>
                    <select id="reg_year_sem" name="year_sem" class="form-control">
                      <option value="">Select</option>
                      <option value="1st Year">1st Year</option>
                      <option value="2nd Year">2nd Year</option>
                      <option value="3rd Year">3rd Year</option>
                      <option value="4th Year">4th Year</option>
                    </select>
                  </div>
                  <div class="form-group">
                    <label for="reg_password">Password</label>
                    <input id="reg_password" name="password" type="password" class="form-control" placeholder="Choose a password" required>
                  </div>
                  <div class="form-group">
                    <label for="reg_confirm">Confirm Password</label>
                    <input id="reg_confirm" name="confirm_password" type="password" class="form-control" placeholder="Confirm password" required>
                  </div>
                </div>
                <div class="form-actions">
                  <button type="submit" class="btn-primary-custom">Create Account</button>
                </div>
              </form>
              
            </div>
          </div>

        </div>
        
      </div>
    </div>
  </div>


<footer

class="footer text-center py-4">Campus Voice — Student Complaint Portal


</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Dark mode toggle persisted in localStorage
const btn = document.getElementById('darkToggle');
const apply = (on) => { document.body.classList.toggle('dark', on); btn.textContent = on ? 'Light' : 'Dark'; }
btn.addEventListener('click', () => { apply(!document.body.classList.contains('dark')); localStorage.setItem('cvDark', document.body.classList.contains('dark')); });
apply(localStorage.getItem('cvDark') === 'true');
</script>
</body>
</html>
