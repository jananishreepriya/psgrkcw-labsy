<?php
require_once '../config.php';
header('Content-Type: application/json');

$date = $_GET['date'] ?? '';

if ($date) {
    $block = fetchOne("SELECT * FROM academic_calendar WHERE calendar_date = ? AND type != 'normal'", [$date]);
    echo json_encode(['blocked' => !!$block, 'info' => $block]);
} else {
    $blocks = fetchAll("SELECT * FROM academic_calendar WHERE calendar_date >= CURDATE() AND type != 'normal' ORDER BY calendar_date ASC LIMIT 10");
    echo json_encode(['blocks' => $blocks]);
}
?>