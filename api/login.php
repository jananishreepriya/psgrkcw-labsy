<?php
require_once '../config.php';
header('Content-Type: application/json');

$email = $_POST['email'] ?? '';
$password = $_POST['password'] ?? '';
$role = $_POST['role'] ?? '';

if (empty($email) || empty($password) || empty($role)) {
    echo json_encode(['success' => false, 'error' => 'All fields required']);
    exit;
}

// Admin login with your email
if ($email === ADMIN_EMAIL && $password === 'admin123' && $role === 'admin') {
    $user = fetchOne("SELECT * FROM users WHERE email = ?", [$email]);
    if ($user) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_name'] = $user['name'];
        $_SESSION['user_email'] = $user['email'];
        $_SESSION['user_role'] = 'admin';
        echo json_encode(['success' => true, 'role' => 'admin']);
        exit;
    }
}

$user = fetchOne("SELECT * FROM users WHERE email = ? AND role = ? AND status = 'active'", [$email, $role]);

if (!$user || !password_verify($password, $user['password'])) {
    echo json_encode(['success' => false, 'error' => 'Invalid email or password']);
    exit;
}

$_SESSION['user_id'] = $user['id'];
$_SESSION['user_name'] = $user['name'];
$_SESSION['user_email'] = $user['email'];
$_SESSION['user_role'] = $user['role'];

echo json_encode(['success' => true, 'role' => $role]);
?>