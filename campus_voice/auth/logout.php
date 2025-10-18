<?php
require_once __DIR__ . '/../config.php';

// Check if user is logged in
if (empty($_SESSION['user_id'])) {
    header('Location: /index.php');
    exit;
}

// Optional: Log admin logout activity
$admin_name = $_SESSION['name'] ?? 'Admin';
$admin_id = $_SESSION['user_id'] ?? 0;

// Clear all session data
$_SESSION = [];

// Delete session cookie if it exists
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(), 
        '', 
        time() - 42000, 
        $params['path'], 
        $params['domain'], 
        $params['secure'], 
        $params['httponly']
    );
}

// Destroy session
session_destroy();

// Set logout success message (optional)
session_start(); // Start new session for flash message
set_flash('success', 'Admin logout successful');

// Redirect to homepage with logout confirmation
header('Location: /index.php?logout=success');
exit;
?>