<?php
require_once '../config.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'staff') {
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$filter = $_GET['filter'] ?? 'all';

$sql = "SELECT b.*, l.lab_name FROM bookings b JOIN labs l ON b.lab_id = l.id WHERE b.staff_id = ?";
$params = [$_SESSION['user_id']];

if ($filter !== 'all') {
    $sql .= " AND b.status = ?";
    $params[] = $filter;
}

$sql .= " ORDER BY b.booking_date DESC";

$bookings = fetchAll($sql, $params);

// Add time range and instant tag info to each booking
foreach ($bookings as &$booking) {
    $booking['time_range'] = getTimeRange($booking['time_slot']);
    // Ensure is_instant is properly typed
    $booking['is_instant'] = intval($booking['is_instant'] ?? 0);
}

echo json_encode(['bookings' => $bookings]);
?>