<?php
require_once '../config.php';
header('Content-Type: application/json');

$name = $_POST['name'] ?? '';
$email = $_POST['email'] ?? '';
$password = $_POST['password'] ?? '';

if (empty($name) || empty($email) || empty($password)) {
    echo json_encode(['success' => false, 'error' => 'All fields required']);
    exit;
}

$existing = fetchOne("SELECT id FROM users WHERE email = ?", [$email]);
if ($existing) {
    echo json_encode(['success' => false, 'error' => 'Email already registered']);
    exit;
}

$hash = password_hash($password, PASSWORD_DEFAULT);
$id = insertId("INSERT INTO users (name, email, password, role, status) VALUES (?, ?, ?, 'staff', 'active')", 
    [$name, $email, $hash]);

echo json_encode(['success' => true]);
?>