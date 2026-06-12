<?php
require_once '../config.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// Get all staff
$staff = fetchAll("SELECT id, name, email FROM users WHERE role = 'staff' AND status = 'active' ORDER BY name");

// Get all labs
$labs = fetchAll("SELECT id, lab_name FROM labs WHERE status = 'active' ORDER BY lab_name");

// Get available years from bookings
$years = fetchAll("SELECT DISTINCT YEAR(booking_date) as year FROM bookings ORDER BY year DESC");

echo json_encode([
    'success' => true,
    'staff' => $staff,
    'labs' => $labs,
    'years' => array_column($years, 'year')
]);
?>