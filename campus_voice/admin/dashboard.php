<?php
require_once __DIR__ . '/../config.php';
require_login();
require_role('admin');

// filter/search optional
$status = $_GET['status'] ?? '';
$where = '';
$params = [];
$types = '';

if (in_array($status, ['Pending','In Progress','Resolved'], true)) {
    $where = ' WHERE c.status=? ';
    $params[] = $status;
    $types .= 's';
}

$sql = 'SELECT c.id,c.title,c.status,c.priority,c.created_at,u.full_name AS student_name 
        FROM complaints c 
        JOIN users u ON u.id=c.user_id ' . $where . ' ORDER BY c.created_at DESC LIMIT 50';
$stmt = $mysqli->prepare($sql);
if ($types) $stmt->bind_param($types, ...$params);
$stmt->execute();
$list = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Admin Dashboard - Campus Voice</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="/assets/css/styles.css">
  <link rel="icon" type="image/png" href="partials/icon.jpg" sizes="32x32">
</head>
<body>
  <!-- Filter Navigation -->
  <nav class="navbar navbar-expand-lg px-4">
    <a class="navbar-brand" href="/admin/dashboard.php">Campus Voice - Admin</a>
    <div class="ms-auto d-flex gap-2">
      <a class="btn btn-ghost" href="/admin/dashboard.php">All</a>
      <a class="btn btn-ghost" href="/admin/dashboard.php?status=Pending">Pending</a>
      <a class="btn btn-ghost" href="/admin/dashboard.php?status=In%20Progress">In Progress</a>
      <a class="btn btn-ghost" href="/admin/dashboard.php?status=Resolved">Resolved</a>
      <button id="darkToggle" class="btn btn-ghost">Dark</button>
      <a class="btn btn-outline-danger" href="/auth/logout.php">Logout</a>
    </div>
  </nav>

  <main class="py-4 container">
    <div class="container-card">
      <h4>Admin Panel</h4>
      <p class="small text-muted">Overview of complaint activity & quick filters</p>

      <div class="stat-grid">
        <div class="stat">
          <h4><?= count($list) ?></h4><p class="small">Total complaints</p>
        </div>
        <div class="stat">
          <h4><?= count(array_filter($list, fn($c) => $c['status'] === 'Pending')) ?></h4><p class="small">Pending</p>
        </div>
        <div class="stat">
          <h4><?= count(array_filter($list, fn($c) => $c['status'] === 'Resolved')) ?></h4><p class="small">Resolved</p>
        </div>
        <div class="stat">
          <h4><?= count(array_filter($list, fn($c) => $c['priority'] === 'High')) ?></h4><p class="small">High Priority</p>
        </div>
      </div>

      <div class="d-flex gap-2 align-items-center mb-2">
        <input class="form-control" placeholder="Search by title / student id" style="max-width:380px">
        <select class="form-control" style="max-width:180px">
          <option>All Categories</option><option>Academic</option><option>Hostel</option><option>Mess</option><option>Maintenance</option>
        </select>
        <select class="form-control" style="max-width:140px">
          <option>All Status</option><option>Pending</option><option>In Progress</option><option>Resolved</option>
        </select>
        <button class="btn btn-primary-custom">Search</button>
      </div>

      <table class="table table-striped">
        <thead>
          <tr>
            <th>ID</th><th>Title</th><th>Student</th><th>Priority</th><th>Status</th><th>Created</th><th>Action</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($list)): ?>
            <tr><td colspan="7" class="text-center text-muted">No complaints found</td></tr>
          <?php else: ?>
            <?php foreach ($list as $complaint): ?>
            <tr>
              <td>#<?= $complaint['id'] ?></td>
              <td><?= htmlspecialchars($complaint['title']) ?></td>
              <td><?= htmlspecialchars($complaint['student_name']) ?></td>
              <td><?= htmlspecialchars($complaint['priority']) ?></td>
              <td>
                <span class="badge <?= strtolower(str_replace(' ', '', $complaint['status'])) ?>">
                  <?= htmlspecialchars($complaint['status']) ?>
                </span>
              </td>
              <td><?= date('M j, Y', strtotime($complaint['created_at'])) ?></td>
              <td>
                <a href="/admin/reply.php?id=<?= $complaint['id'] ?>" class="btn btn-sm btn-primary">Manage</a>
              </td>
            </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </main>

  <footer class="footer text-center">Admin • Campus Voice</footer>
  
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
  <script>
  const btn = document.getElementById('darkToggle');
  const apply = (on) => { 
    document.body.classList.toggle('dark', on); 
    btn.textContent = on ? 'Light' : 'Dark'; 
  }
  btn.addEventListener('click', () => { 
    apply(!document.body.classList.contains('dark')); 
    localStorage.setItem('cvDark', document.body.classList.contains('dark')); 
  });
  apply(localStorage.getItem('cvDark') === 'true');
  </script>
</body>
</html>