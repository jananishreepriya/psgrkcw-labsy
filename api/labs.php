<?php
require_once '../config.php';
header('Content-Type: application/json');

$admin = isset($_GET['admin']) && isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin';
$sql = "SELECT l.*, (SELECT COUNT(*) FROM bookings WHERE lab_id = l.id AND status = 'approved') as booking_count FROM labs l";
if (!$admin) {
    $sql .= " WHERE status = 'active'";
}
$sql .= " ORDER BY l.lab_name";

$labs = fetchAll($sql);
echo json_encode(['labs' => $labs]);
?>