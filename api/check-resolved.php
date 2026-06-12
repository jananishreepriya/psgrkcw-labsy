<?php
require_once '../config.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['resolved_recently' => false]);
    exit;
}

$userId = $_SESSION['user_id'];
$resolved = fetchOne("SELECT id FROM support_requests WHERE user_id = ? AND status = 'resolved' AND resolved_at > DATE_SUB(NOW(), INTERVAL 5 MINUTE)", [$userId]);
echo json_encode(['resolved_recently' => !empty($resolved)]);
?>