<?php
require_once '../config.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$heads = fetchAll("
    SELECT u.*, 
        (SELECT GROUP_CONCAT(l.lab_name SEPARATOR ', ') 
         FROM lab_heads lh 
         JOIN labs l ON lh.lab_id = l.id 
         WHERE lh.head_id = u.id) as lab_names,
        (SELECT GROUP_CONCAT(lab_id) FROM lab_heads WHERE head_id = u.id) as assigned_labs
    FROM users u 
    WHERE u.role = 'head' 
    ORDER BY u.created_at DESC
");

echo json_encode(['heads' => $heads]);
?>