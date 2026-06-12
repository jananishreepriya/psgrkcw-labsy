<?php
require_once '../config.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$name = trim($_POST['lab_name'] ?? '');
$desc = trim($_POST['description'] ?? '');
$capacity = intval($_POST['capacity'] ?? 30);

if (empty($name)) {
    echo json_encode(['error' => 'Lab name required']);
    exit;
}

$id = insertId("INSERT INTO labs (lab_name, description, capacity) VALUES (?, ?, ?)", [$name, $desc, $capacity]);
echo json_encode(['success' => true, 'id' => $id]);
?>
