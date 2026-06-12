<?php
require_once '../config.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$filterType = $_GET['filter_type'] ?? 'all';
$filterValue = $_GET['filter_value'] ?? '';
$page = intval($_GET['page'] ?? 1);
$limit = intval($_GET['limit'] ?? 50);
$offset = ($page - 1) * $limit;

$sql = "SELECT b.*, u.name as staff_name, u.email as staff_email, l.lab_name 
        FROM bookings b 
        JOIN users u ON b.staff_id = u.id 
        JOIN labs l ON b.lab_id = l.id 
        WHERE 1=1";
$params = [];

switch ($filterType) {
    case 'date': if ($filterValue) { $sql .= " AND b.booking_date = ?"; $params[] = $filterValue; } break;
    case 'month': if ($filterValue) { $sql .= " AND MONTH(b.booking_date) = ?"; $params[] = $filterValue; } break;
    case 'year': if ($filterValue) { $sql .= " AND YEAR(b.booking_date) = ?"; $params[] = $filterValue; } break;
    case 'staff': if ($filterValue) { $sql .= " AND b.staff_id = ?"; $params[] = $filterValue; } break;
    case 'lab': if ($filterValue) { $sql .= " AND b.lab_id = ?"; $params[] = $filterValue; } break;
}

$countSql = str_replace("SELECT b.*, u.name as staff_name, u.email as staff_email, l.lab_name", "SELECT COUNT(*) as total", $sql);
$totalResult = fetchOne($countSql, $params);
$totalRecords = $totalResult['total'];
$totalPages = ceil($totalRecords / $limit);

$sql .= " ORDER BY b.booking_date DESC, b.created_at DESC LIMIT $limit OFFSET $offset";
$bookings = fetchAll($sql, $params);

// Add time range and instant tag info to each booking
foreach ($bookings as &$booking) {
    $booking['time_range'] = getTimeRange($booking['time_slot']);
    // Ensure is_instant is properly typed
    $booking['is_instant'] = intval($booking['is_instant'] ?? 0);
}

// Stats
$statsSql = "SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved,
    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
    SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected,
    SUM(CASE WHEN is_instant = 1 THEN 1 ELSE 0 END) as instant_bookings
FROM bookings b WHERE 1=1";

$statsParams = [];
switch ($filterType) {
    case 'date': if ($filterValue) { $statsSql .= " AND b.booking_date = ?"; $statsParams[] = $filterValue; } break;
    case 'month': if ($filterValue) { $statsSql .= " AND MONTH(b.booking_date) = ?"; $statsParams[] = $filterValue; } break;
    case 'year': if ($filterValue) { $statsSql .= " AND YEAR(b.booking_date) = ?"; $statsParams[] = $filterValue; } break;
    case 'staff': if ($filterValue) { $statsSql .= " AND b.staff_id = ?"; $statsParams[] = $filterValue; } break;
    case 'lab': if ($filterValue) { $statsSql .= " AND b.lab_id = ?"; $statsParams[] = $filterValue; } break;
}

$stats = fetchOne($statsSql, $statsParams);

echo json_encode([
    'success' => true,
    'bookings' => $bookings,
    'stats' => $stats,
    'pagination' => [
        'current_page' => $page,
        'total_pages' => $totalPages,
        'total_records' => $totalRecords,
        'per_page' => $limit
    ]
]);
?>