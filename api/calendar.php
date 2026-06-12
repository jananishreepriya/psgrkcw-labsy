<?php
require_once '../config.php';
header('Content-Type: application/json');

$year = intval($_GET['year'] ?? date('Y'));
$month = intval($_GET['month'] ?? date('n'));

// Get all days for the month
$days = fetchAll("SELECT * FROM academic_calendar WHERE MONTH(calendar_date) = ? AND YEAR(calendar_date) = ?", [$month, $year]);

// Auto-generate if empty
if (empty($days)) {
    generateMonthCalendar($year, $month);
    $days = fetchAll("SELECT * FROM academic_calendar WHERE MONTH(calendar_date) = ? AND YEAR(calendar_date) = ?", [$month, $year]);
}

$dayMap = [];
foreach ($days as $d) {
    $dayMap[$d['calendar_date']] = $d;
}

$stats = [
    'working' => count(array_filter($days, fn($d) => $d['type'] === 'normal')),
    'holiday' => count(array_filter($days, fn($d) => $d['type'] === 'holiday')),
    'exam' => count(array_filter($days, fn($d) => $d['type'] === 'exam'))
];

echo json_encode(['days' => $dayMap, 'stats' => $stats, 'year' => $year, 'month' => $month]);

function generateMonthCalendar($year, $month) {
    $daysInMonth = cal_days_in_month(CAL_GREGORIAN, $month, $year);
    $dayNames = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
    
    // Get last day order from previous month
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
        
        if (!$exists) {
            if ($dayOfWeek == 0) {
                $dayOrder = "Day {$currentDayOrder}";
                $type = 'holiday';
            } else {
                $currentDayOrder = ($currentDayOrder % 6) + 1;
                $dayOrder = "Day {$currentDayOrder}";
                $type = 'normal';
            }
            
            query("INSERT INTO academic_calendar (calendar_date, day_name, day_order, type) VALUES (?, ?, ?, ?)",
                [$dateStr, $dayName, $dayOrder, $type]);
        }
    }
}
?>