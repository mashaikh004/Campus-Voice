<?php
require_once __DIR__ . '/../config.php';
require_login();
require_role('student');

$id = (int)($_GET['id'] ?? 0);

// Get user name for display
$user_id = (int)$_SESSION['user_id'];
$stmt = $mysqli->prepare('SELECT full_name FROM users WHERE id = ?');
$stmt->bind_param('i', $user_id);
$stmt->execute();
$user_result = $stmt->get_result()->fetch_assoc();
$user_name = $user_result ? $user_result['full_name'] : 'Student';
$stmt->close();

// fetch complaint with replies and feedback
$stmt = $mysqli->prepare('SELECT c.id,c.title,c.category,c.priority,c.description,c.status,c.created_at,c.attachment_filename,c.attachment_original,c.phone FROM complaints c WHERE c.id=? AND c.user_id=?');
$stmt->bind_param('ii', $id, $_SESSION['user_id']);
$stmt->execute();
$complaint = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$complaint) {
    header('Location: /student/dashboard.php');
    exit;
}

// replies
$stmt = $mysqli->prepare('SELECT r.id, r.reply_text, r.created_at, u.full_name AS admin_name FROM replies r JOIN users u ON r.admin_id=u.id WHERE r.complaint_id=? ORDER BY r.created_at ASC');
$stmt->bind_param('i', $id);
$stmt->execute();
$replies = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// feedback if any
$stmt = $mysqli->prepare('SELECT rating, comments, created_at FROM feedback WHERE complaint_id=? AND user_id=? LIMIT 1');
$stmt->bind_param('ii', $id, $_SESSION['user_id']);
$stmt->execute();
$feedback = $stmt->get_result()->fetch_assoc();
$stmt->close();

// handle feedback submit when status resolved
$err = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['rating'])) {
    if ($complaint['status'] !== 'Resolved') {
        $err[] = 'You can submit feedback only after resolution.';
    } else {
        $rating = (int)($_POST['rating']);
        $comments = trim($_POST['comments'] ?? '');
        if ($rating < 1 || $rating > 5) $err[] = 'Rating 1-5 required';

        if (!$err) {
            if ($feedback) {
                // update
                $stmt = $mysqli->prepare('UPDATE feedback SET rating=?, comments=? WHERE complaint_id=? AND user_id=?');
                $stmt->bind_param('isii', $rating, $comments, $id, $_SESSION['user_id']);
                $stmt->execute();
                $stmt->close();
            } else {
                // insert
                $stmt = $mysqli->prepare('INSERT INTO feedback (complaint_id,user_id,rating,comments) VALUES (?,?,?,?)');
                $stmt->bind_param('iiis', $id, $_SESSION['user_id'], $rating, $comments);
                $stmt->execute();
                $stmt->close();
            }
            set_flash('success', 'Feedback saved successfully!');
            header('Location: /student/view_complaint.php?id='.(int)$id);
            exit;
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Complaint Details - Campus Voice</title>
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
  margin-bottom: 20px;
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
.badge{ display:inline-block; padding:6px 12px; border-radius:999px; font-weight:600; font-size:0.85rem; }
.badge.pending{ background:#fff3c4; color:#7a5800; }
.badge.inprogress{ background:#e6f6ff; color:#185c9c; }
.badge.resolved{ background:#e6ffef; color:#0b6b3a; }
.complaint-meta { 
  display: flex; 
  gap: 20px; 
  margin: 15px 0; 
  flex-wrap: wrap;
}
.complaint-meta div {
  background: rgba(31,111,235,0.05);
  padding: 8px 12px;
  border-radius: 8px;
  font-size: 0.9rem;
}
.replies-section {
  margin-top: 30px;
}
.reply-item {
  background: rgba(6,182,212,0.05);
  border-left: 3px solid var(--accent);
  padding: 15px;
  margin: 10px 0;
  border-radius: 0 8px 8px 0;
}
.reply-meta {
  font-size: 0.85rem;
  color: var(--muted);
  margin-bottom: 8px;
}
.feedback-form {
  background: rgba(16,185,129,0.05);
  padding: 20px;
  border-radius: 12px;
  margin-top: 20px;
}
.rating-stars {
  display: flex;
  gap: 5px;
  margin: 10px 0;
}
.rating-stars input[type="radio"] {
  display: none;
}
.rating-stars label {
  font-size: 1.5rem;
  color: #ddd;
  cursor: pointer;
  transition: color 0.2s;
}
.rating-stars input:checked ~ label,
.rating-stars label:hover {
  color: #ffc107;
}
.text-muted{ color:var(--muted) }
.small{ font-size:0.85rem; color:var(--muted) }
.footer{ padding:18px; text-align:center; font-size:0.9rem; color:var(--muted); }
.alert-success { background: #e6ffef; color: #0b6b3a; padding: 12px; border-radius: 8px; margin-bottom: 16px; }
.alert-danger { background: #fef2f2; color: #dc2626; padding: 12px; border-radius: 8px; margin-bottom: 16px; }
  </style>
</head>
<body>

<nav class="navbar px-4">
  <a class="navbar-brand" href="/student/dashboard.php">Campus Voice</a>
  <div class="ms-auto d-flex gap-2 align-items-center">
    <span class="text-muted">Welcome, <?= htmlspecialchars($user_name) ?></span>
    <a class="btn btn-ghost" href="/student/dashboard.php">Dashboard</a>
    <a class="btn btn-ghost" href="/student/create_complaint.php">New Complaint</a>
    <button id="darkToggle" class="btn btn-ghost">Dark</button>
    <a class="btn btn-ghost" href="/auth/logout.php">Logout</a>
  </div>
</nav>

<main class="py-4 container">
  <?php if ($msg = get_flash('success')): ?>
    <div class="alert-success"><?= htmlspecialchars($msg) ?></div>
  <?php endif; ?>

  <?php if ($err): ?>
    <div class="alert-danger">
      <?php foreach($err as $e): ?>
        <div><?= htmlspecialchars($e) ?></div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <div class="container-card">
    <div class="d-flex justify-content-between align-items-start mb-3">
      <h4><?= htmlspecialchars($complaint['title']) ?></h4>
      <span class="badge <?= strtolower(str_replace(' ', '', $complaint['status'])) ?>"><?= htmlspecialchars($complaint['status']) ?></span>
    </div>
    
    <div class="complaint-meta">
      <div><strong>Category:</strong> <?= htmlspecialchars($complaint['category']) ?></div>
      <div><strong>Priority:</strong> <?= htmlspecialchars($complaint['priority']) ?></div>
      <div><strong>Created:</strong> <?= date('M j, Y g:i A', strtotime($complaint['created_at'])) ?></div>
      <?php if ($complaint['phone']): ?>
      <div><strong>Contact:</strong> <?= htmlspecialchars($complaint['phone']) ?></div>
      <?php endif; ?>
    </div>

    <div class="mt-3">
      <h6>Description:</h6>
      <p><?= nl2br(htmlspecialchars($complaint['description'])) ?></p>
    </div>

    <?php if ($complaint['attachment_filename']): ?>
    <div class="mt-3">
      <h6>Attachment:</h6>
      <a href="/uploads/<?= htmlspecialchars($complaint['attachment_filename']) ?>" 
         download="<?= htmlspecialchars($complaint['attachment_original']) ?>"
         class="btn btn-ghost">
        📎 <?= htmlspecialchars($complaint['attachment_original']) ?>
      </a>
    </div>
    <?php endif; ?>
  </div>

  <div class="container-card replies-section">
    <h5>Admin Replies</h5>
    <?php if (!$replies): ?>
      <p class="text-muted">No replies from administration yet. You will be notified when an admin responds.</p>
    <?php else: ?>
      <?php foreach($replies as $r): ?>
      <div class="reply-item">
        <div class="reply-meta">
          <strong><?= htmlspecialchars($r['admin_name']) ?></strong> • <?= date('M j, Y g:i A', strtotime($r['created_at'])) ?>
        </div>
        <div><?= nl2br(htmlspecialchars($r['reply_text'])) ?></div>
      </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>

  <?php if ($complaint['status'] === 'Resolved'): ?>
  <div class="container-card">
    <div class="feedback-form">
      <h5>Feedback & Rating</h5>
      <p class="small text-muted">Your complaint has been resolved. Please rate our service.</p>
      
      <?php if ($feedback): ?>
        <div class="alert-success">
          <strong>Your Rating:</strong> <?= str_repeat('⭐', (int)$feedback['rating']) ?> (<?= (int)$feedback['rating'] ?>/5)<br>
          <?php if ($feedback['comments']): ?>
          <strong>Your Feedback:</strong> <?= nl2br(htmlspecialchars($feedback['comments'])) ?>
          <?php endif; ?>
          <br><small>Submitted on <?= date('M j, Y g:i A', strtotime($feedback['created_at'])) ?></small>
        </div>
        <p class="text-muted">You can update your feedback below:</p>
      <?php endif; ?>

      <form method="post">
        <div class="mb-3">
          <label>Rating (1-5 stars):</label>
          <div class="rating-stars">
            <input type="radio" name="rating" value="5" id="star5" <?= ($feedback && (int)$feedback['rating'] === 5) ? 'checked' : '' ?>>
            <label for="star5">⭐</label>
            <input type="radio" name="rating" value="4" id="star4" <?= ($feedback && (int)$feedback['rating'] === 4) ? 'checked' : '' ?>>
            <label for="star4">⭐</label>
            <input type="radio" name="rating" value="3" id="star3" <?= ($feedback && (int)$feedback['rating'] === 3) ? 'checked' : '' ?>>
            <label for="star3">⭐</label>
            <input type="radio" name="rating" value="2" id="star2" <?= ($feedback && (int)$feedback['rating'] === 2) ? 'checked' : '' ?>>
            <label for="star2">⭐</label>
            <input type="radio" name="rating" value="1" id="star1" <?= ($feedback && (int)$feedback['rating'] === 1) ? 'checked' : '' ?>>
            <label for="star1">⭐</label>
          </div>
        </div>
        <div class="mb-3">
          <label>Comments (optional):</label>
          <textarea name="comments" rows="3" style="width:100%; padding:10px; border-radius:8px; border:1px solid rgba(15,23,42,0.08); background:transparent; color:var(--text);" placeholder="Share your experience..."><?= $feedback ? htmlspecialchars($feedback['comments']) : '' ?></textarea>
        </div>
        <button type="submit" class="btn-primary-custom">
          <?= $feedback ? 'Update Feedback' : 'Submit Feedback' ?>
        </button>
      </form>
    </div>
  </div>
  <?php endif; ?>

  <div class="mt-4">
    <a href="/student/dashboard.php" class="btn btn-ghost">← Back to Dashboard</a>
  </div>
</main>

<footer class="footer">• Campus Voice</footer>

<script>
// Dark mode toggle
const btn = document.getElementById('darkToggle');
const apply = (on) => { document.body.classList.toggle('dark', on); btn.textContent = on ? 'Light' : 'Dark'; }
btn.addEventListener('click', () => { apply(!document.body.classList.contains('dark')); localStorage.setItem('cvDark', document.body.classList.contains('dark')); });
apply(localStorage.getItem('cvDark') === 'true');

// Rating stars interaction
document.querySelectorAll('.rating-stars input').forEach(radio => {
  radio.addEventListener('change', function() {
    const rating = parseInt(this.value);
    document.querySelectorAll('.rating-stars label').forEach((label, index) => {
      if (5 - index <= rating) {
        label.style.color = '#ffc107';
      } else {
        label.style.color = '#ddd';
      }
    });
  });
});
</script>
</body>
</html>