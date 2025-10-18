<?php
require_once __DIR__ . '/../config.php';
require_login();
require_role('student');

// Fetch recent complaints of this student
$user_id = (int)$_SESSION['user_id'];

// Get complaint statistics - simpler approach
$stats = ['total' => 0, 'pending' => 0, 'resolved' => 0, 'in_progress' => 0, 'high_priority' => 0];

// Get total complaints
$stmt = $mysqli->prepare('SELECT COUNT(*) as total FROM complaints WHERE user_id = ?');
if ($stmt) {
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $stats['total'] = $result['total'];
    $stmt->close();
}

// Get pending complaints
$stmt = $mysqli->prepare('SELECT COUNT(*) as pending FROM complaints WHERE user_id = ? AND status = "Pending"');
if ($stmt) {
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $stats['pending'] = $result['pending'];
    $stmt->close();
}

// Get resolved complaints
$stmt = $mysqli->prepare('SELECT COUNT(*) as resolved FROM complaints WHERE user_id = ? AND status = "Resolved"');
if ($stmt) {
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $stats['resolved'] = $result['resolved'];
    $stmt->close();
}

// REMOVED DUPLICATE HIGH PRIORITY QUERY - using the one below instead

// Get user name with error handling
$stmt = $mysqli->prepare('SELECT full_name FROM users WHERE id = ?');
if ($stmt === false) {
    die('Prepare failed: ' . $mysqli->error);
}
$stmt->bind_param('i', $user_id);
$stmt->execute();
$user_result = $stmt->get_result()->fetch_assoc();
$user_name = $user_result ? $user_result['full_name'] : 'Student';
$stmt->close();

// Fetch recent complaints with more details and error handling
$stmt = $mysqli->prepare('SELECT id, title, category, priority, status, created_at FROM complaints WHERE user_id=? ORDER BY created_at DESC LIMIT 10');
if ($stmt === false) {
    die('Prepare failed: ' . $mysqli->error);
}
$stmt->bind_param('i', $user_id);
$stmt->execute();
$complaints = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Optional notifications (from admin actions)
$notif = get_flash('notif'); // admin actions will set this
$success_msg = get_flash('success');

// MariaDB-safe high priority count using subquery approach
$stmt = $mysqli->prepare(
  "SELECT (
     SELECT COUNT(*)
     FROM complaints
     WHERE user_id = ? AND priority = 'High' AND status <> 'Resolved'
   ) AS high_priority_count"
);
$stmt->bind_param('i', $user_id);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stats['high_priority'] = (int)($row['high_priority_count'] ?? 0);
$stmt->close();

?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Student Dashboard - Campus Voice</title>
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
  text-decoration: none;
  display: inline-block;
}
.btn-primary-custom:hover {
  background: #1a5cd8;
  color: white;
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
/* Success/Error messages */
.alert-success { background: #e6ffef; color: #0b6b3a; padding: 12px; border-radius: 8px; margin-bottom: 16px; }
.alert-info { background: #e6f6ff; color: #185c9c; padding: 12px; border-radius: 8px; margin-bottom: 16px; }
  </style>
</head>
<body>

<nav class="navbar px-4">
  <a class="navbar-brand" href="/student/dashboard.php">Campus Voice</a>
  <div class="ms-auto d-flex gap-2 align-items-center">
    <span class="text-muted">Welcome, <?= htmlspecialchars($user_name) ?></span>
    <a class="btn btn-ghost" href="/student/create_complaint.php">New Complaint</a>
    <button id="darkToggle" class="btn btn-ghost">Dark</button>
    <a class="btn btn-ghost" href="/auth/logout.php">Logout</a>
  </div>
</nav>

<main class="py-4 container">
  <?php if ($success_msg): ?>
    <div class="alert-success"><?= htmlspecialchars($success_msg) ?></div>
  <?php endif; ?>
  
  <?php if ($notif): ?>
    <div class="alert-info"><?= htmlspecialchars($notif) ?></div>
  <?php endif; ?>

  <div class="container-card">
    <h4>Hi, <strong><?= htmlspecialchars($user_name) ?></strong></h4>
    <p class="small text-muted">Welcome back — track your complaints and their statuses.</p>

    <div class="stat-grid">
      <div class="stat">
        <h4><?= sprintf('%02d', $stats['total'] ?? 0) ?></h4><p class="small">Total Complaints</p>
      </div>
      <div class="stat">
        <h4><?= sprintf('%02d', $stats['pending'] ?? 0) ?></h4><p class="small">Pending</p>
      </div>
      <div class="stat">
        <h4><?= sprintf('%02d', $stats['resolved'] ?? 0) ?></h4><p class="small">Resolved</p>
      </div>
      <div class="stat">
        <h4><?= $stats['high_priority'] ?? 0 ?></h4><p class="small">Open High Priority</p>
      </div>
    </div>

    <h5 class="mt-3">Recent Complaints</h5>
    <?php if (empty($complaints)): ?>
      <p class="text-muted">No complaints submitted yet. <a href="/student/create_complaint.php">Submit your first complaint</a></p>
    <?php else: ?>
    <table class="table-like">
      <thead><tr><th>Title</th><th>Category</th><th>Priority</th><th>Status</th><th>Created</th><th>Action</th></tr></thead>
      <tbody>
        <?php foreach($complaints as $complaint): ?>
        <tr>
          <td><?= htmlspecialchars($complaint['title']) ?></td>
          <td><?= htmlspecialchars($complaint['category']) ?></td>
          <td><?= htmlspecialchars($complaint['priority']) ?></td>
          <td><span class="badge <?= strtolower(str_replace(' ', '', $complaint['status'])) ?>"><?= htmlspecialchars($complaint['status']) ?></span></td>
          <td><?= date('M j, Y', strtotime($complaint['created_at'])) ?></td>
          <td><a href="/student/view_complaint.php?id=<?= (int)$complaint['id'] ?>">View</a></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <?php endif; ?>

    <div class="mt-3 d-flex gap-2">
      <a class="btn btn-primary-custom" href="/student/create_complaint.php">Submit New Complaint</a>
    </div>
  </div>
</main>

<footer class="footer">• Campus Voice</footer>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
const btn = document.getElementById('darkToggle');
const apply = (on) => { document.body.classList.toggle('dark', on); btn.textContent = on ? 'Light' : 'Dark'; }
btn.addEventListener('click', () => { apply(!document.body.classList.contains('dark')); localStorage.setItem('cvDark', document.body.classList.contains('dark')); });
apply(localStorage.getItem('cvDark') === 'true');
</script>
</body>
</html>