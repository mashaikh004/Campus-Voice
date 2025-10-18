<?php
// config.php
session_start();

$DB_HOST = 'localhost';
$DB_USER = 'root';      // change as per XAMPP
$DB_PASS = '';          // change as per XAMPP
$DB_NAME = 'campus_voice';

$mysqli = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);
if ($mysqli->connect_error) {
    die('DB Connection failed: ' . $mysqli->connect_error);
}

// Set charset to utf8
$mysqli->set_charset("utf8");

// helper: require login
function require_login() {
    if (empty($_SESSION['user_id'])) {
        header('Location: /auth/login.php');
        exit;
    }
}

// helper: role guard
function require_role($role) {
    if (empty($_SESSION['role']) || $_SESSION['role'] !== $role) {
        header('HTTP/1.1 403 Forbidden');
        echo 'Access denied';
        exit;
    }
}

// flash messages
function set_flash($key, $msg) {
    $_SESSION['flash'][$key] = $msg;
}

function get_flash($key) {
    if (!empty($_SESSION['flash'][$key])) {
        $msg = $_SESSION['flash'][$key];
        unset($_SESSION['flash'][$key]);
        return $msg;
    }
    return null;
}

// Helper function to get user info
function get_user_info($mysqli, $user_id) {
    $stmt = $mysqli->prepare('SELECT full_name, email, role FROM users WHERE id = ?');
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $result;
}

// Helper function for sanitizing output
function escape_html($string) {
    return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
}
?>