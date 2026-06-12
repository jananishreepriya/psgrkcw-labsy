<?php
require_once '../config.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$id = intval($_POST['id'] ?? 0);
$status = $_POST['status'] ?? 'inactive';

query("UPDATE users SET status = ? WHERE id = ? AND role = 'staff'", [$status, $id]);
echo json_encode(['success' => true]);
?>