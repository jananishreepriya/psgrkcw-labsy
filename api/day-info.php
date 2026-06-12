<?php
require_once '../config.php';
header('Content-Type: application/json');

$date = $_GET['date'] ?? '';
if (!$date) {
    echo json_encode(['success' => false, 'error' => 'Date required']);
    exit;
}

// First check if date exists in calendar
$info = fetchOne("SELECT * FROM academic_calendar WHERE calendar_date = ?", [$date]);

if ($info) {
    echo json_encode([
        'success' => true,
        'day_name' => $info['day_name'],
        'day_order' => $info['day_order'], // "Day 1", "Day 2", etc.
        'day_order_num' => preg_replace('/[^0-9]/', '', $info['day_order']), // 1, 2, 3, etc.
        'type' => $info['type'],
        'block_reason' => $info['block_reason']
    ]);
} else {
    // Auto-generate for this date
    $dayOfWeek = date('w', strtotime($date));
    $dayNames = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
    $dayName = $dayNames[$dayOfWeek];
    
    // Get last day order before this date
    $lastDayOrder = 6;
    $prevDay = fetchOne("
        SELECT day_order FROM academic_calendar 
        WHERE calendar_date < ? 
        AND type = 'normal'
        ORDER BY calendar_date DESC LIMIT 1
    ", [$date]);
    
    if ($prevDay && !empty($prevDay['day_order'])) {
        if (preg_match('/Day\s*(\d+)/i', $prevDay['day_order'], $matches)) {
            $lastDayOrder = (int)$matches[1];
        }
    }
    
    if ($dayOfWeek == 0) {
        $dayOrder = "Day {$lastDayOrder}";
        $type = 'holiday';
    } else {
        $currentDayOrder = ($lastDayOrder % 6) + 1;
        $dayOrder = "Day {$currentDayOrder}";
        $type = 'normal';
    }
    
    // Insert this date
    query("INSERT INTO academic_calendar (calendar_date, day_name, day_order, type) VALUES (?, ?, ?, ?)",
        [$date, $dayName, $dayOrder, $type]);
    
    echo json_encode([
        'success' => true,
        'day_name' => $dayName,
        'day_order' => $dayOrder,
        'day_order_num' => preg_replace('/[^0-9]/', '', $dayOrder),
        'type' => $type,
        'block_reason' => null,
        'auto_generated' => true
    ]);
}
?>