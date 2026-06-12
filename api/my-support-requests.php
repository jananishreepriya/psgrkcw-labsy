<?php
require_once '../config.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_role'], ['staff', 'head'])) {
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$userId = $_SESSION['user_id'];
$userRole = $_SESSION['user_role'];

$requests = fetchAll("
    SELECT id, request_type, message, status, created_at, resolved_at 
    FROM support_requests 
    WHERE user_id = ? AND user_role = ? 
    ORDER BY created_at DESC
", [$userId, $userRole]);

echo json_encode(['success' => true, 'requests' => $requests]);
?>