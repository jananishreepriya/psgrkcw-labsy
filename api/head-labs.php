<?php
require_once '../config.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'head') {
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$headId = $_SESSION['user_id'];
$labs = getHeadsLabs($headId);
echo json_encode(['labs' => $labs]);
?>