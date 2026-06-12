<?php
require_once '../config.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$staff = fetchAll("SELECT * FROM users WHERE role = 'staff' ORDER BY created_at DESC");
echo json_encode(['staff' => $staff]);
?>