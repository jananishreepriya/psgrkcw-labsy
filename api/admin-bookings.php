<?php
require_once '../config.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$filter = $_GET['filter'] ?? 'all';
$limit = intval($_GET['limit'] ?? 100);

$sql = "SELECT b.*, u.name as staff_name, l.lab_name 
        FROM bookings b 
        JOIN users u ON b.staff_id = u.id 
        JOIN labs l ON b.lab_id = l.id";
$params = [];

if ($filter !== 'all') {
    $sql .= " WHERE b.status = ?";
    $params[] = $filter;
}

$sql .= " ORDER BY b.has_conflict DESC, b.created_at DESC LIMIT $limit";

$bookings = fetchAll($sql, $params);

// Add time range and instant tag info to each booking
foreach ($bookings as &$booking) {
    // Use stored time_range if available, otherwise calculate
    if (!empty($booking['start_time']) && !empty($booking['end_time'])) {
        $booking['time_range'] = $booking['start_time'] . ' - ' . $booking['end_time'];
    } else {
        $booking['time_range'] = getTimeRange($booking['time_slot']);
    }
    // Ensure is_instant is properly typed
    $booking['is_instant'] = intval($booking['is_instant'] ?? 0);
    
    // Get conflict details if has_conflict
    if ($booking['has_conflict']) {
        $conflictRequest = fetchOne("
            SELECT * FROM conflict_requests 
            WHERE lab_id = ? AND booking_date = ? AND time_slot = ? AND status = 'pending'
        ", [$booking['lab_id'], $booking['booking_date'], $booking['time_slot']]);
        
        if ($conflictRequest) {
            $booking['conflict_requester_id'] = $conflictRequest['requesting_staff_id'];
        }
    }
}

echo json_encode(['bookings' => $bookings]);
?>