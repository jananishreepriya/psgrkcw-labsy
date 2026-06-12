<?php
require_once '../config.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$id = intval($_GET['id'] ?? 0);
query("DELETE FROM labs WHERE id = ?", [$id]);
echo json_encode(['success' => true]);
?>