<?php
require_once '../config.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'head') {
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$headId = $_SESSION['user_id'];

// Get labs assigned to this head
$labs = getHeadsLabs($headId);
$labIds = array_column($labs, 'id');

if (empty($labIds)) {
    echo json_encode(['labs' => 0, 'pending' => 0, 'approved' => 0]);
    exit;
}

$placeholders = implode(',', array_fill(0, count($labIds), '?'));

$pending = fetchOne("SELECT COUNT(*) as c FROM bookings WHERE lab_id IN ($placeholders) AND status = 'pending'", $labIds)['c'];
$approved = fetchOne("SELECT COUNT(*) as c FROM bookings WHERE lab_id IN ($placeholders) AND status = 'approved'", $labIds)['c'];

echo json_encode([
    'labs' => count($labs),
    'pending' => $pending,
    'approved' => $approved
]);
?>