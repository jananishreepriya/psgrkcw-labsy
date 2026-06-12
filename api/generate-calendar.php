<?php
require_once '../config.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$year = intval($_GET['year'] ?? date('Y'));
$month = intval($_GET['month'] ?? date('n'));

$daysInMonth = cal_days_in_month(CAL_GREGORIAN, $month, $year);
$dayNames = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];

// Get the last day order from previous month to continue rotation
$lastDayOrder = 6;
$prevMonthLastDay = fetchOne("
    SELECT day_order FROM academic_calendar 
    WHERE calendar_date < ? 
    AND type = 'normal'
    ORDER BY calendar_date DESC LIMIT 1
", [sprintf('%04d-%02d-01', $year, $month)]);

if ($prevMonthLastDay && !empty($prevMonthLastDay['day_order'])) {
    if (preg_match('/Day\s*(\d+)/i', $prevMonthLastDay['day_order'], $matches)) {
        $lastDayOrder = (int)$matches[1];
    }
}

$currentDayOrder = $lastDayOrder;

for ($d = 1; $d <= $daysInMonth; $d++) {
    $dateStr = sprintf('%04d-%02d-%02d', $year, $month, $d);
    $dayOfWeek = date('w', strtotime($dateStr));
    $dayName = $dayNames[$dayOfWeek];
    
    $exists = fetchOne("SELECT id, type FROM academic_calendar WHERE calendar_date = ?", [$dateStr]);
    
    if ($exists) {
        // Update existing entry, preserve day order if already set
        if (empty($exists['day_order']) || $exists['type'] === 'normal') {
            if ($dayOfWeek == 0 || $exists['type'] !== 'normal') {
                // Sunday or already blocked - don't advance day order
                $dayOrder = "Day {$currentDayOrder}";
            } else {
                // Advance day order for working day
                $currentDayOrder = ($currentDayOrder % 6) + 1;
                $dayOrder = "Day {$currentDayOrder}";
            }
            query("UPDATE academic_calendar SET day_name = ?, day_order = ? WHERE calendar_date = ?",
                [$dayName, $dayOrder, $dateStr]);
        }
    } else {
        // New entry
        if ($dayOfWeek == 0) {
            // Sunday = Holiday, keep same day order (don't advance)
            $dayOrder = "Day {$currentDayOrder}";
            $type = 'holiday';
        } else {
            // Working day - advance day order
            $currentDayOrder = ($currentDayOrder % 6) + 1;
            $dayOrder = "Day {$currentDayOrder}";
            $type = 'normal';
        }
        
        query("INSERT INTO academic_calendar (calendar_date, day_name, day_order, type) VALUES (?, ?, ?, ?)",
            [$dateStr, $dayName, $dayOrder, $type]);
    }
}

echo json_encode(['success' => true, 'message' => 'Calendar generated with 6-day timetable rotation']);
?>