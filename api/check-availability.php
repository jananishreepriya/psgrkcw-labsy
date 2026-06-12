<?php
require_once '../config.php';
header('Content-Type: application/json');

$labId = intval($_GET['lab_id'] ?? 0);
$date = $_GET['date'] ?? '';
$slot = $_GET['slot'] ?? '';

if (empty($labId) || empty($date) || empty($slot)) {
    echo json_encode(['error' => 'Lab, date and slot required']);
    exit;
}

$response = [
    'date' => $date,
    'lab_id' => $labId,
    'slot' => $slot,
    'is_available' => true,
    'day_order' => null,
    'day_order_num' => null,
    'day_name' => null,
    'conflicts' => [],
    'has_conflict' => false,
    'has_timetable_clash' => false,
    'has_booking_clash' => false,
    'has_regular_booking' => false,
    'has_instant_booking' => false,
    'has_pending_request' => false,
    'can_request_approval' => false,
    'instant_bookings' => [],
    'regular_bookings' => [],
    'total_bookings' => 0
];

// Check past date
$today = date('Y-m-d');
if (strtotime($date) < strtotime($today)) {
    $response['is_available'] = false;
    $response['conflicts'][] = [
        'type' => 'past',
        'message' => 'Cannot book for past dates'
    ];
    echo json_encode($response);
    exit;
}

// Same-day time check
if ($date === $today) {
    $now = new DateTime();
    $slotStart = '';
    
    if ($slot === 'FN') {
        $slotStart = FN_START; // '08:10'
    } elseif ($slot === 'AN') {
        $slotStart = AN_START; // '12:50'
    } elseif ($slot === 'Full Day') {
        // Full day allowed only before 08:10 AM
        $slotStart = FN_START;
    } elseif (strpos($slot, 'Period') === 0) {
        $periodNum = intval(str_replace('Period ', '', $slot));
        $periodTimes = [
            1 => '08:10', 2 => '09:00', 3 => '10:10', 4 => '11:00', 5 => '11:50',
            6 => '12:50', 7 => '13:40', 8 => '14:30', 9 => '15:40', 10 => '16:30'
        ];
        if (isset($periodTimes[$periodNum])) {
            $slotStart = $periodTimes[$periodNum];
        }
    }

    if ($slotStart) {
        $slotStartTime = DateTime::createFromFormat('Y-m-d H:i', "$today $slotStart");
        if ($slotStartTime && $now >= $slotStartTime) {
            $response['is_available'] = false;
            $message = ($slot === 'Full Day') 
                ? 'Full day booking must be made before 8:10 AM. The day has already started.'
                : "This time slot has already passed for today (must book before $slotStart)";
            $response['conflicts'][] = [
                'type' => 'past_time',
                'message' => $message
            ];
            echo json_encode($response);
            exit;
        }
    }
}

// Get day order from calendar
$calendar = fetchOne("SELECT * FROM academic_calendar WHERE calendar_date = ?", [$date]);

$dayOrder = null;
$dayName = date('l', strtotime($date));

if ($calendar && !empty($calendar['day_order'])) {
    if (preg_match('/Day\s*(\d+)/i', $calendar['day_order'], $matches)) {
        $dayOrder = (int)$matches[1];
    }
    $response['calendar_type'] = $calendar['type'];
    $response['calendar_day_order'] = $calendar['day_order'];
    $dayName = $calendar['day_name'] ?? $dayName;
}

// If no calendar entry, generate it
if (!$dayOrder) {
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
    
    $dayOfWeek = date('w', strtotime($date));
    if ($dayOfWeek == 0) {
        $dayOrder = $lastDayOrder;
        $calType = 'holiday';
    } else {
        $dayOrder = ($lastDayOrder % 6) + 1;
        $calType = 'normal';
    }
    
    $dayNames = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
    query("INSERT INTO academic_calendar (calendar_date, day_name, day_order, type) VALUES (?, ?, ?, ?)",
        [$date, $dayNames[$dayOfWeek], "Day {$dayOrder}", $calType]);
    
    $response['auto_generated'] = true;
}

$response['day_order'] = "Day {$dayOrder}";
$response['day_order_num'] = $dayOrder;
$response['day_name'] = $dayName;

// Check if date is blocked
if ($calendar && $calendar['type'] != 'normal') {
    $response['is_available'] = false;
    $response['conflicts'][] = [
        'type' => 'blocked',
        'message' => 'This date is blocked: ' . ($calendar['block_reason'] ?? ucfirst($calendar['type']))
    ];
}

// CHECK TIMETABLE CONFLICTS
$timetable = fetchAll("
    SELECT * FROM timetable 
    WHERE lab_id = ? AND day_order = ? AND is_active = TRUE
", [$labId, $dayOrder]);

foreach ($timetable as $t) {
    $conflict = false;
    
    if ($slot === 'FN' && $t['start_time'] < '12:00:00') {
        $conflict = true;
    } elseif ($slot === 'AN' && $t['start_time'] >= '12:00:00') {
        $conflict = true;
    } elseif ($slot === 'Full Day') {
        $conflict = true;
    } elseif (strpos($slot, 'Period') === 0) {
        $periodNum = intval(str_replace('Period ', '', $slot));
        $periodTimes = [
            1 => ['start' => '08:10', 'end' => '09:00'],
            2 => ['start' => '09:00', 'end' => '09:50'],
            3 => ['start' => '10:10', 'end' => '11:00'],
            4 => ['start' => '11:00', 'end' => '11:50'],
            5 => ['start' => '11:50', 'end' => '12:40'],
            6 => ['start' => '12:50', 'end' => '13:40'],
            7 => ['start' => '13:40', 'end' => '14:30'],
            8 => ['start' => '14:30', 'end' => '15:20'],
            9 => ['start' => '15:40', 'end' => '16:30'],
            10 => ['start' => '16:30', 'end' => '17:20']
        ];
        
        if (isset($periodTimes[$periodNum])) {
            $pStart = $periodTimes[$periodNum]['start'];
            $pEnd = $periodTimes[$periodNum]['end'];
            $cStart = substr($t['start_time'], 0, 5);
            $cEnd = substr($t['end_time'], 0, 5);
            
            if (!($pEnd <= $cStart || $pStart >= $cEnd)) {
                $conflict = true;
            }
        }
    }
    
    if ($conflict) {
        $response['has_conflict'] = true;
        $response['has_timetable_clash'] = true;
        $response['conflicts'][] = [
            'type' => 'timetable',
            'class_name' => $t['class_name'],
            'subject' => $t['subject'],
            'faculty' => $t['faculty_name'],
            'head_email' => $t['head_email'],
            'start_time' => $t['start_time'],
            'end_time' => $t['end_time'],
            'message' => "Class: {$t['class_name']} ({$t['start_time']}-{$t['end_time']})"
        ];
    }
}

// CHECK FOR PENDING REQUEST
$pendingRequest = fetchOne("
    SELECT cr.*, u.name as staff_name 
    FROM conflict_requests cr
    JOIN users u ON cr.requesting_staff_id = u.id
    WHERE cr.lab_id = ? AND cr.booking_date = ? AND cr.time_slot = ? 
    AND cr.status = 'pending'
", [$labId, $date, $slot]);

if ($pendingRequest) {
    $response['is_available'] = false;
    $response['has_pending_request'] = true;
    $response['pending_request_by'] = $pendingRequest['staff_name'];
    $response['conflicts'][] = [
        'type' => 'locked',
        'message' => "Another staff ({$pendingRequest['staff_name']}) has requested admin approval for this slot"
    ];
}

// CHECK EXISTING BOOKINGS
$existingBookings = fetchAll("
    SELECT b.*, u.name as staff_name, u.email as staff_email 
    FROM bookings b 
    JOIN users u ON b.staff_id = u.id 
    WHERE b.lab_id = ? AND b.booking_date = ? AND b.status IN ('pending', 'approved')
    ORDER BY b.is_instant DESC, b.created_at DESC
", [$labId, $date]);

foreach ($existingBookings as $b) {
    $conflict = false;
    $bSlot = $b['time_slot'];
    
    if ($slot === $bSlot) {
        $conflict = true;
    }
    elseif ($slot === 'FN' && (strpos($bSlot, 'Period') === 0 && intval(str_replace('Period ', '', $bSlot)) <= 5)) {
        $conflict = true;
    }
    elseif ($slot === 'AN' && (strpos($bSlot, 'Period') === 0 && intval(str_replace('Period ', '', $bSlot)) >= 6)) {
        $conflict = true;
    }
    elseif (strpos($slot, 'Period') === 0) {
        $pNum = intval(str_replace('Period ', '', $slot));
        if ($bSlot === 'Full Day' || ($bSlot === 'FN' && $pNum <= 5) || ($bSlot === 'AN' && $pNum >= 6)) {
            $conflict = true;
        }
    }
    elseif ($slot === 'Full Day' || $bSlot === 'Full Day') {
        $conflict = true;
    }
    
    if ($conflict) {
        $response['total_bookings']++;
        
        if ($b['is_instant']) {
            $response['has_instant_booking'] = true;
            $response['instant_bookings'][] = [
                'id' => $b['id'],
                'staff_name' => $b['staff_name'],
                'staff_email' => $b['staff_email'],
                'purpose' => $b['purpose'],
                'status' => $b['status'],
                'time_slot' => $b['time_slot'],
                'is_instant' => true,
                'created_at' => $b['created_at']
            ];
        } else {
            $response['has_regular_booking'] = true;
            $response['has_booking_clash'] = true;
            $response['is_available'] = false;
            $response['regular_bookings'][] = [
                'id' => $b['id'],
                'staff_name' => $b['staff_name'],
                'staff_email' => $b['staff_email'],
                'purpose' => $b['purpose'],
                'status' => $b['status'],
                'time_slot' => $b['time_slot'],
                'is_instant' => false,
                'created_at' => $b['created_at']
            ];
            
            $response['conflicts'][] = [
                'type' => 'booking',
                'is_instant' => false,
                'message' => "Regular booking by {$b['staff_name']} for {$b['time_slot']}"
            ];
        }
    }
}

// CAN REQUEST IF: has timetable clash OR instant booking, BUT NO regular booking AND NO pending request
if (($response['has_timetable_clash'] || $response['has_instant_booking']) 
    && !$response['has_regular_booking'] 
    && !$response['has_pending_request']) {
    $response['can_request_approval'] = true;
}

echo json_encode($response);
?>