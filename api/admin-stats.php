<?php
require_once '../config.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$stats = [
    'labs' => fetchOne("SELECT COUNT(*) as c FROM labs")['c'],
    'staff' => fetchOne("SELECT COUNT(*) as c FROM users WHERE role = 'staff'")['c'],
    'heads' => fetchOne("SELECT COUNT(*) as c FROM users WHERE role = 'head'")['c'],
    'today_bookings' => fetchOne("SELECT COUNT(*) as c FROM bookings WHERE booking_date = CURDATE()")['c'],
    'pending' => fetchOne("SELECT COUNT(*) as c FROM bookings WHERE status = 'pending'")['c']
];

echo json_encode($stats);
?>