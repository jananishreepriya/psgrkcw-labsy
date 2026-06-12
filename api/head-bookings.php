<?php
require_once '../config.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'head') {
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$headId = $_SESSION['user_id'];
$filter = $_GET['filter'] ?? 'all';
$labId = isset($_GET['lab_id']) ? intval($_GET['lab_id']) : 0;
$search = trim($_GET['search'] ?? '');
$page = intval($_GET['page'] ?? 1);
$limit = intval($_GET['limit'] ?? 20);
$offset = ($page - 1) * $limit;

$labs = getHeadsLabs($headId);
$labIds = array_column($labs, 'id');

if (empty($labIds)) {
    echo json_encode(['bookings' => [], 'pagination' => ['total' => 0, 'page' => 1, 'pages' => 1]]);
    exit;
}

if ($labId && in_array($labId, $labIds)) {
    $labIds = [$labId];
} elseif ($labId && !in_array($labId, $labIds)) {
    error_log("Head $headId requested unauthorized lab $labId");
    echo json_encode(['bookings' => [], 'pagination' => ['total' => 0, 'page' => 1, 'pages' => 1]]);
    exit;
}

$placeholders = implode(',', array_fill(0, count($labIds), '?'));

$countSql = "SELECT COUNT(*) as total 
             FROM bookings b 
             JOIN users u ON b.staff_id = u.id 
             WHERE b.lab_id IN ($placeholders)";
$countParams = $labIds;

if ($filter !== 'all') {
    $countSql .= " AND b.status = ?";
    $countParams[] = $filter;
}
if (!empty($search)) {
    $countSql .= " AND (u.name LIKE ? OR b.purpose LIKE ?)";
    $searchTerm = "%$search%";
    $countParams[] = $searchTerm;
    $countParams[] = $searchTerm;
}

$total = fetchOne($countSql, $countParams)['total'];
$totalPages = ceil($total / $limit);

$sql = "SELECT b.*, u.name as staff_name, l.lab_name 
        FROM bookings b 
        JOIN users u ON b.staff_id = u.id 
        JOIN labs l ON b.lab_id = l.id 
        WHERE b.lab_id IN ($placeholders)";
$params = $labIds;

if ($filter !== 'all') {
    $sql .= " AND b.status = ?";
    $params[] = $filter;
}
if (!empty($search)) {
    $sql .= " AND (u.name LIKE ? OR b.purpose LIKE ?)";
    $searchTerm = "%$search%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
}

$sql .= " ORDER BY b.created_at DESC LIMIT $limit OFFSET $offset";
$bookings = fetchAll($sql, $params);

foreach ($bookings as &$b) {
    $b['time_range'] = getTimeRange($b['time_slot']);
    $b['is_instant'] = intval($b['is_instant'] ?? 0);
}

echo json_encode([
    'bookings' => $bookings,
    'pagination' => [
        'total' => $total,
        'page' => $page,
        'limit' => $limit,
        'pages' => $totalPages
    ]
]);
?>