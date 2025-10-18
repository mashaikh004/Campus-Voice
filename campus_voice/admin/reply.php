<?php
require_once __DIR__ . '/../config.php';
require_login();
require_role('admin');

$complaint_id = (int)($_GET['id'] ?? 0);

// fetch complaint basic
$stmt = $mysqli->prepare('SELECT c.id,c.title,c.description,c.status,c.user_id,u.full_name AS student_name FROM complaints c JOIN users u ON u.id=c.user_id WHERE c.id=?');
$stmt->bind_param('i', $complaint_id);
$stmt->execute();
$complaint = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$complaint) {
    echo 'Complaint not found';
    exit;
}

$err = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['reply_text'])) {
        $reply_text = trim($_POST['reply_text']);
        if ($reply_text === '') $err[] = 'Reply text required';
        if (!$err) {
            // insert reply
            $stmt = $mysqli->prepare('INSERT INTO replies (complaint_id, admin_id, reply_text) VALUES (?,?,?)');
            $admin_id = (int)$_SESSION['user_id'];
            $stmt->bind_param('iis', $complaint_id, $admin_id, $reply_text);
            $stmt->execute();
            $stmt->close();

            // set notification for student (flash is session-based; for persistent notification table, insert into notifications table linked to user)
            set_flash('notif', 'Complaint ka ans mila'); // will show after next student request

            header('Location: /admin/reply.php?id='.(int)$complaint_id);
            exit;
        }
    } elseif (isset($_POST['status'])) {
        $status = $_POST['status'];
        if (!in_array($status, ['Pending','In Progress','Resolved'], true)) {
            $err[] = 'Invalid status';
        } else {
            $stmt = $mysqli->prepare('UPDATE complaints SET status=? WHERE id=?');
            $stmt->bind_param('si', $status, $complaint_id);
            $stmt->execute();
            $stmt->close();

            // notify student via flash on their next visit
            set_flash('notif', 'Complaint status updated: '.$status);

            header('Location: /admin/reply.php?id='.(int)$complaint_id);
            exit;
        }
    }
}
?>
<!-- yahan aapki HTML file paste karein: Admin Reply / Manage -->
 <?php
// ... your existing PHP logic above (query, form handling, flashes, etc.)
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Complaint Reply · Admin</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="icon" type="image/png" href="partials/icon.jpg" sizes="32x32">
  <style>
  :root {
    --bg: #0b1020;
    --card: #121833;
    --muted: #6B7280;
    --text: #111827;
    --accent: #6ea8ff;
    --accent-2: #5eead4;
    --danger: #ff6b6b;
    --success: #22c55e;
    --border: rgba(255,255,255,.08);
    --shadow: 0 10px 30px rgba(0,0,0,.35);
    --radius: 14px;
    --radius-sm: 10px;
  }

  * {
    box-sizing: border-box;
  }

  html, body {
    height: 100%;
  }

  body {
    margin: 0;
    font-family: ui-sans-serif, system-ui, -apple-system, Segoe UI, Roboto, "Helvetica Neue", Arial, "Noto Sans", "Apple Color Emoji", "Segoe UI Emoji";
    background: #ffffff;
    color: var(--text);
    -webkit-font-smoothing: antialiased;
    line-height: 1.5;
  }

  .wrap {
    max-width: 980px;
    margin: 48px auto;
    padding: 0 20px;
  }

  .breadcrumbs a {
    color: var(--muted);
    text-decoration: none;
  }

  .breadcrumbs {
    font-size: .9rem;
    margin-bottom: 18px;
    color: var(--muted);
  }

  .header {
    display: flex;
    justify-content: space-between;
    align-items: flex-end;
    gap: 16px;
    margin-bottom: 20px;
  }

  .title {
    font-size: clamp(22px, 3.2vw, 30px);
    font-weight: 700;
    letter-spacing: .2px;
  }

  .status-pill {
    padding: 6px 12px;
    border-radius: 999px;
    font-size: .85rem;
    border: 1px solid var(--border);
    background: linear-gradient(180deg, rgba(255,255,255,.04), rgba(255,255,255,.01));
    color: #ffffff;
  }

  .pill-pending {
    color: #fbbf24;
  }

  .pill-progress {
    color: #60a5fa;
  }

  .pill-resolved {
    color: #34d399;
  }

  .grid {
    display: grid;
    grid-template-columns: 1.15fr .9fr;
    gap: 18px;
  }

  @media (max-width: 860px) {
    .grid {
      grid-template-columns: 1fr;
    }
  }

  .card {
    background: #ffffff;
    border: 1px solid var(--border);
    border-radius: 8px;
    box-shadow: var(--shadow);
  }

  .card + .card {
    margin-top: 0;
  }

  .card-head {
    padding: 16px 18px 8px 18px;
    border-bottom: 1px solid var(--border);
    font-weight: 600;
    color: #111827;
  }

  .card-body {
    padding: 18px;
  }

  .meta {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 12px;
    margin-bottom: 14px;
    color: var(--muted);
    font-size: .95rem;
  }

  @media (max-width: 560px) {
    .meta {
      grid-template-columns: 1fr;
    }
  }

  .description {
    padding: 14px;
    background: rgba(255,255,255,.03);
    border: 1px solid var(--border);
    border-radius: var(--radius-sm);
    white-space: pre-wrap;
    color: var(--text);
  }

  label {
    display: block;
    font-size: .95rem;
    color: #6B7280;
    margin-bottom: 8px;
  }

  textarea, select, input[type="text"] {
    width: 100%;
    padding: 12px 14px;
    border-radius: 12px;
    border: 1px solid var(--border);
    background: rgba(255,255,255,.03);
    color: var(--text);
    outline: none;
    transition: border-color .2s, box-shadow .2s;
  }

  textarea {
    min-height: 140px;
    resize: vertical;
  }

  textarea:focus, select:focus, input:focus {
    border-color: rgba(11, 11, 11, 0.55);
    box-shadow: 0 0 0 3px rgba(110,168,255,.18);
  }

  textarea::placeholder {
    color: #9CA3AF;
  }

  .actions {
    display: flex;
    gap: 12px;
    flex-wrap: wrap;
    margin-top: 12px;
  }

  .btn {
    appearance: none;
    border: 0;
    cursor: pointer;
    padding: 11px 16px;
    border-radius: 12px;
    font-weight: 600;
    color: #0b1020;
    background: var(--accent);
    box-shadow: 0 8px 18px rgba(110,168,255,.28);
    transition: transform .05s ease, box-shadow .2s ease, background .2s ease;
  }

  .btn:hover {
    transform: translateY(-1px);
  }

  .btn:active {
    transform: translateY(0);
  }

  .btn-outline {
    background: transparent;
    color: var(--text);
    border: 1px solid var(--border);
    box-shadow: none;
  }

  .muted {
    color: var(--muted);
  }

  .footer-link {
    display: inline-block;
    margin-top: 22px;
    color: var(--muted);
    text-decoration: none;
  }

  .flash {
    margin: 0 0 16px 0;
    padding: 12px 14px;
    border-radius: 12px;
    border: 1px solid var(--border);
    background: rgba(34,197,94,.07);
    color: #22c55e;
  }

  .errors {
    margin: 0 0 16px 0;
    padding: 12px 14px;
    border-radius: 12px;
    border: 1px solid var(--border);
    background: rgba(255,107,107,.08);
    color: #ff6b6b;
  }

  .tip-text {
    color: #6B7280;
    font-size: .9rem;
  }
</style>

</head>
<body>
  <div class="wrap">
    <div class="breadcrumbs">
      <a href="/admin/index.php">Dashboard</a> · Complaints · Reply
    </div>

    <div class="header">
      <div class="title">Complaint #<?= (int)$complaint['id']; ?></div>
      <?php
        $pill = 'pill-pending';
        if ($complaint['status']==='Resolved') $pill='pill-resolved';
        elseif ($complaint['status']==='In Progress') $pill='pill-progress';
      ?>
      <div class="status-pill <?= $pill ?>">
        <?= htmlspecialchars($complaint['status']) ?>
      </div>
    </div>

    <?php if (!empty($err)): ?>
      <div class="errors"><?= htmlspecialchars(implode(' · ', $err)) ?></div>
    <?php endif; ?>

    <?php if (!empty($_SESSION['flash']['notif'])): ?>
      <div class="flash"><?= htmlspecialchars($_SESSION['flash']['notif']) ?></div>
    <?php endif; ?>

    <div class="grid">
      <!-- Complaint details + Reply -->
      <div class="card">
        <div class="card-head">Details</div>
        <div class="card-body">
          <div class="meta">
            <div><span class="muted">By</span><br><?= htmlspecialchars($complaint['student_name']) ?></div>
            <div><span class="muted">Complaint ID</span><br>#<?= (int)$complaint['id'] ?></div>
          </div>
          <div class="description"><?= nl2br(htmlspecialchars($complaint['description'])) ?></div>

          <form method="post" style="margin-top:18px">
            <label for="reply_text">Add Reply</label>
            <textarea id="reply_text" name="reply_text" placeholder="Type your reply..."></textarea>
            
          </form>
        </div>
      </div>

      <!-- Status update -->
      <div class="card">
        <div class="card-head">Update Status</div>
        <div class="card-body">
          <form method="post">
            <label for="status">Status</label>
            <select id="status" name="status">
              <option value="Pending"     <?= $complaint['status']==='Pending' ? 'selected':''; ?>>Pending</option>
              <option value="In Progress" <?= $complaint['status']==='In Progress' ? 'selected':''; ?>>In Progress</option>
              <option value="Resolved"    <?= $complaint['status']==='Resolved' ? 'selected':''; ?>>Resolved</option>
            </select>
            <div class="actions" style="margin-top:14px">
              <button type="submit" class="btn">Save</button>
            </div>
          </form>

          <p class="muted" style="margin-top:14px">
            Tip: set to Resolved after posting the final reply.
          </p>
        </div>
      </div>
    </div>

   
  </div>
</body>
</html>
