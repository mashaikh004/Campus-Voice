<?php
require_once __DIR__ . '/../config.php';
require_login();
require_role('admin');

$complaint_id = (int)($_GET['id'] ?? 0);
$stmt = $mysqli->prepare('UPDATE complaints SET status="Resolved" WHERE id=?');
$stmt->bind_param('i', $complaint_id);
$stmt->execute();
$stmt->close();

set_flash('notif', 'Complaint Resolved');
header('Location: /admin/reply.php?id='.(int)$complaint_id);
exit;
