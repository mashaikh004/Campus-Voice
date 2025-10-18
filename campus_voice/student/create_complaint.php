<?php
require_once __DIR__ . '/../config.php';
require_login();
require_role('student');

// Validate user exists in database and get user info
$user_id = (int)$_SESSION['user_id'];

// Check if user exists in database first
$stmt = $mysqli->prepare('SELECT id, full_name FROM users WHERE id = ?');
if (!$stmt) {
    die('Database error: ' . $mysqli->error);
}
$stmt->bind_param('i', $user_id);
$stmt->execute();
$user_result = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$user_result) {
    // User doesn't exist in database, force logout
    session_destroy();
    header('Location: /auth/login.php?error=' . urlencode('User session invalid. Please login again.'));
    exit;
}

$user_name = $user_result['full_name'];

$err = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title       = trim($_POST['title'] ?? '');
    $category    = trim($_POST['category'] ?? '');
    $priority    = $_POST['priority'] ?? 'Medium';
    $description = trim($_POST['description'] ?? '');
    $phone       = trim($_POST['phone'] ?? '');

    $attachment_filename = null;
    $attachment_original = null;

    // Optional: handle file upload
    if (!empty($_FILES['attachment']['name'])) {
        $orig = $_FILES['attachment']['name'];
        $tmp  = $_FILES['attachment']['tmp_name'];
        
        // Check file size (5MB limit)
        if ($_FILES['attachment']['size'] > 5 * 1024 * 1024) {
            $err[] = 'File size must be less than 5MB';
        } else {
            $safe = bin2hex(random_bytes(8)) . '_' . preg_replace('/[^A-Za-z0-9._-]/','_', $orig);
            $destDir = __DIR__ . '/../uploads';
            if (!is_dir($destDir)) mkdir($destDir, 0777, true);
            if (move_uploaded_file($tmp, $destDir . '/' . $safe)) {
                $attachment_filename = $safe;
                $attachment_original = $orig;
            } else {
                $err[] = 'File upload failed';
            }
        }
    }

    if ($title === '') $err[] = 'Title required';
    if ($category === '') $err[] = 'Category required';
    if ($description === '') $err[] = 'Description required';
    if (!in_array($priority, ['Low','Medium','High'], true)) $priority = 'Medium';
    if (!in_array($category, ['Academic','Hostel','Mess','Maintenance','Others'], true)) $category = 'Others';

    if (!$err) {
        // Check if phone column exists, if not insert without it
        $phone_column_exists = $mysqli->query("SHOW COLUMNS FROM complaints LIKE 'phone'")->num_rows > 0;
        
        if ($phone_column_exists) {
            $stmt = $mysqli->prepare('INSERT INTO complaints (user_id,title,category,priority,description,attachment_filename,attachment_original,phone,created_at) VALUES (?,?,?,?,?,?,?,?,NOW())');
            if (!$stmt) {
                die('Database error: ' . $mysqli->error);
            }
            // Use $user_id instead of $_SESSION['user_id']
            $stmt->bind_param('isssssss', $user_id, $title, $category, $priority, $description, $attachment_filename, $attachment_original, $phone);
        } else {
            $stmt = $mysqli->prepare('INSERT INTO complaints (user_id,title,category,priority,description,attachment_filename,attachment_original,created_at) VALUES (?,?,?,?,?,?,?,NOW())');
            if (!$stmt) {
                die('Database error: ' . $mysqli->error);
            }
            // Use $user_id instead of $_SESSION['user_id']
            $stmt->bind_param('issssss', $user_id, $title, $category, $priority, $description, $attachment_filename, $attachment_original);
        }
        
        if ($stmt->execute()) {
            set_flash('success', 'Complaint submitted successfully!');
            header('Location: /student/dashboard.php');
            exit;
        } else {
            $err[] = 'Save failed: ' . $stmt->error;
            // Add debug information
            $err[] = 'Debug: User ID = ' . $user_id . ', exists in users table: ' . ($user_result ? 'Yes' : 'No');
        }
        $stmt->close();
    }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Submit Complaint - Campus Voice</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="icon" type="image/png" href="partials/icon.jpg" sizes="32x32">
    <style>
    :root{
  --primary:#1f6feb;
  --accent:#06b6d4;
  --bg:#f7f9fc;
  --card:#ffffff;
  --text:#1f2937;
  --muted:#6b7280;
  --danger:#ef4444;
  --success:#10b981;
  --radius:12px;
}
/* Dark mode */
body.dark {
  --bg:#0b1220;
  --card:#071227;
  --text:#e6eef8;
  --muted:#9aa6b2;
}
* { box-sizing:border-box; }
body{
  font-family:Inter, system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial;
  background:var(--bg);
  color:var(--text);
  margin:0;
  padding:0;
  -webkit-font-smoothing:antialiased;
  -moz-osx-font-smoothing:grayscale;
}
/* Top nav */
.navbar-brand { font-weight:700; letter-spacing:0.2px; color:var(--primary) !important; }
.navbar { 
  background:var(--card); 
  border-bottom: 1px solid rgba(15,23,42,0.08);
  box-shadow: 0 2px 8px rgba(15,23,42,0.04);
}
/* Container cards */
.container-card{
  background:linear-gradient(180deg, rgba(255,255,255,0.6), rgba(255,255,255,0.4));
  background-color:var(--card);
  border-radius:var(--radius);
  padding:22px;
  box-shadow: 0 6px 18px rgba(15,23,42,0.06);
}
/* consistent form look */
.cv-form{ max-width:900px; margin:18px auto; }
.cv-form .form-row{ display:flex; gap:14px; flex-wrap:wrap; }
.cv-form .form-group{ flex:1 1 260px; min-width:220px; }
.cv-form label{ display:block; font-size:0.9rem; color:var(--muted); margin-bottom:6px; }
.cv-form input[type="text"],
.cv-form input[type="email"],
.cv-form input[type="password"],
.cv-form input[type="file"],
.cv-form select,
.cv-form textarea{
  width:100%;
  padding:10px 12px;
  border-radius:10px;
  border:1px solid rgba(15,23,42,0.08);
  background:transparent;
  outline:none;
  color:var(--text);
  font-size:0.95rem;
}
.cv-form textarea{ min-height:140px; resize:vertical; padding-top:12px; }
/* form actions */
.form-actions{ display:flex; gap:12px; align-items:center; margin-top:12px; }
.btn-primary-custom{
  background:var(--primary);
  color:white;
  border:none;
  padding:10px 16px;
  border-radius:10px;
  font-weight:600;
  box-shadow: 0 6px 14px rgba(31,111,235,0.12);
}
.btn-primary-custom:hover {
  background: #1a5cd8;
}
.btn-ghost{
  background:transparent;
  border:1px solid rgba(15,23,42,0.06);
  padding:9px 14px;
  border-radius:10px;
  color: var(--text);
  text-decoration: none;
}
.btn-ghost:hover {
  background: rgba(15,23,42,0.04);
  color: var(--text);
}
/* small badges + stat cards */
.stat-grid{ display:flex; gap:14px; flex-wrap:wrap; margin:12px 0 18px;}
.stat{
  flex:1 1 180px;
  min-width:140px;
  background:linear-gradient(180deg, rgba(255,255,255,0.6), rgba(255,255,255,0.2));
  border-radius:12px;
  padding:14px;
  box-shadow:0 6px 18px rgba(15,23,42,0.04);
}
.stat h4{ margin:0; font-size:1.25rem; color:var(--primary) }
.stat p{ margin:6px 0 0; color:var(--muted); font-size:0.86rem; }
/* complaint list */
.table-like{ width:100%; border-collapse:collapse; margin-top:8px;}
.table-like th, .table-like td{
  text-align:left; padding:10px 12px; border-bottom:1px solid rgba(15,23,42,0.04); font-size:0.95rem;
}
.badge{ display:inline-block; padding:6px 8px; border-radius:999px; font-weight:600; font-size:0.78rem; }
.badge.pending{ background:#fff3c4; color:#7a5800; }
.badge.inprogress{ background:#e6f6ff; color:#185c9c; }
.badge.resolved{ background:#e6ffef; color:#0b6b3a; }
/* responsive tweaks */
@media (max-width:720px){
  .cv-form .form-row{ flex-direction:column; }
  .stat-grid{ flex-direction:column; }
}
/* small utilities */
.text-muted{ color:var(--muted) }
.small{ font-size:0.85rem; color:var(--muted) }
/* file input preview */
.preview-img{ max-width:180px; max-height:120px; border-radius:8px; display:block; margin-top:8px; object-fit:cover; }
/* footer */
.footer{ padding:18px; text-align:center; font-size:0.9rem; color:var(--muted); }
/* Error messages */
.alert-danger { background: #fef2f2; color: #dc2626; padding: 12px; border-radius: 8px; margin-bottom: 16px; }
.alert-danger ul { margin: 0; padding-left: 20px; }
  </style>
</head>
<body>
<nav class="navbar px-4">
  <a class="navbar-brand" href="/student/dashboard.php">Campus Voice</a>
  <div class="ms-auto d-flex gap-2 align-items-center">
    <span class="text-muted">Welcome, <?= htmlspecialchars($user_name) ?></span>
    <a class="btn btn-ghost" href="/student/dashboard.php">Dashboard</a>
    <button id="darkToggle" class="btn btn-ghost">Dark</button>
    <a class="btn btn-ghost" href="/auth/logout.php">Logout</a>
  </div>
</nav>

<main class="py-4 container">
  <div class="container-card cv-form">
    <h4>New Complaint</h4>
    <p class="small text-muted">Fill all required fields and attach proof if available.</p>

    <?php if ($err): ?>
    <div class="alert-danger">
      <ul>
        <?php foreach($err as $error): ?>
        <li><?= htmlspecialchars($error) ?></li>
        <?php endforeach; ?>
      </ul>
    </div>
    <?php endif; ?>

    <form method="post" enctype="multipart/form-data">
      <div class="form-row">
        <div class="form-group">
          <label>Complaint Title *</label>
          <input type="text" name="title" placeholder="Short title" value="<?= htmlspecialchars($_POST['title'] ?? '') ?>" required>
        </div>

        <div class="form-group">
          <label>Category *</label>
          <select name="category" required>
            <option value="">Select Category</option>
            <option value="Academic" <?= ($_POST['category'] ?? '') === 'Academic' ? 'selected' : '' ?>>Academic</option>
            <option value="Hostel" <?= ($_POST['category'] ?? '') === 'Hostel' ? 'selected' : '' ?>>Hostel</option>
            <option value="Mess" <?= ($_POST['category'] ?? '') === 'Mess' ? 'selected' : '' ?>>Mess</option>
            <option value="Maintenance" <?= ($_POST['category'] ?? '') === 'Maintenance' ? 'selected' : '' ?>>Maintenance</option>
            <option value="Others" <?= ($_POST['category'] ?? '') === 'Others' ? 'selected' : '' ?>>Others</option>
          </select>
        </div>

        <div class="form-group">
          <label>Priority</label>
          <select name="priority">
            <option value="Low" <?= ($_POST['priority'] ?? 'Medium') === 'Low' ? 'selected' : '' ?>>Low</option>
            <option value="Medium" <?= ($_POST['priority'] ?? 'Medium') === 'Medium' ? 'selected' : '' ?>>Medium</option>
            <option value="High" <?= ($_POST['priority'] ?? 'Medium') === 'High' ? 'selected' : '' ?>>High</option>
          </select>
        </div>
      </div>

      <div class="form-row">
        <div class="form-group" style="flex-basis:100%">
          <label>Description *</label>
          <textarea name="description" placeholder="Describe the issue with as much detail as possible..." required><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>
        </div>
      </div>

      <div class="form-row">
        <div class="form-group">
          <label>Attach Image / File (optional)</label>
          <input type="file" name="attachment" id="fileInput" accept="image/*,.pdf,.doc,.docx">
          <small class="text-muted">Max file size: 5MB</small>
          <img id="preview" class="preview-img" style="display:none">
        </div>
        <div class="form-group">
          <label>Contact Phone (optional)</label>
          <input type="text" name="phone" placeholder="e.g. +91 98XXXXXXXX" value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>">
        </div>
      </div>

      <div class="form-actions">
        <button class="btn-primary-custom" type="submit">Submit Complaint</button>
        <button type="reset" class="btn-ghost">Reset</button>
        <a href="/student/dashboard.php" class="btn-ghost">Cancel</a>
      </div>
    </form>
  </div>
</main>

<footer class="footer">Submit complaints responsibly • Campus Voice</footer>

<script>
document.getElementById('fileInput').addEventListener('change', function(e){
  const file = e.target.files[0];
  const img = document.getElementById('preview');
  if(!file){ img.style.display='none'; return; }
  if(file.type.startsWith('image/')){
    const url = URL.createObjectURL(file);
    img.src = url; img.style.display = 'block';
  } else { img.style.display='none'; }
});
const btn = document.getElementById('darkToggle');
const apply = (on) => { document.body.classList.toggle('dark', on); btn.textContent = on ? 'Light' : 'Dark'; }
btn.addEventListener('click', () => { apply(!document.body.classList.contains('dark')); localStorage.setItem('cvDark', document.body.classList.contains('dark')); });
apply(localStorage.getItem('cvDark') === 'true');
</script>
</body>
</html>