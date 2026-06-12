<?php
require_once '../config.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$name = trim($_POST['name'] ?? '');
$email = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';

if (empty($name) || empty($email) || empty($password)) {
    echo json_encode(['error' => 'All fields required']);
    exit;
}

if (strlen($password) < 6) {
    echo json_encode(['error' => 'Password must be at least 6 characters']);
    exit;
}

$existing = fetchOne("SELECT id FROM users WHERE email = ?", [$email]);
if ($existing) {
    echo json_encode(['error' => 'Email already exists']);
    exit;
}

$hash = password_hash($password, PASSWORD_DEFAULT);
$id = insertId("INSERT INTO users (name, email, password, role, status) VALUES (?, ?, ?, 'staff', 'active')", 
    [$name, $email, $hash]);

echo json_encode(['success' => true, 'id' => $id]);
?>