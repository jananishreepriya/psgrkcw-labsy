<?php
require_once '../config.php';
header('Content-Type: application/json');

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Ensure correct timezone
date_default_timezone_set('Asia/Kolkata');

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'staff') {
    echo json_encode(['error' => 'Unauthorized - Please login again']);
    exit;
}

$labId = intval($_POST['lab_id'] ?? 0);
$date = trim($_POST['booking_date'] ?? '');
$slot = trim($_POST['time_slot'] ?? '');
$purpose = trim($_POST['purpose'] ?? '');
$isInstant = intval($_POST['is_instant'] ?? 0);

// Convert date to Y-m-d if needed
$bookingDate = '';
if (!empty($date)) {
    $dt = DateTime::createFromFormat('d-m-Y', $date);
    if ($dt) {
        $bookingDate = $dt->format('Y-m-d');
    } else {
        $ts = strtotime($date);
        if ($ts) $bookingDate = date('Y-m-d', $ts);
    }
}

if (empty($labId) || empty($bookingDate) || empty($slot) || empty($purpose)) {
    echo json_encode(['error' => 'All fields are required']);
    exit;
}

// ============================================
// PAST DATE / TIME VALIDATION
// ============================================
$today = date('Y-m-d');
if (strtotime($bookingDate) < strtotime($today)) {
    echo json_encode(['error' => 'Cannot book for past dates']);
    exit;
}

// For today's date, block slots that have already started
if ($bookingDate === $today) {
    $now = new DateTime();
    $slotStartTime = '';
    
    switch ($slot) {
        case 'FN':
            $slotStartTime = FN_START; // '08:10'
            break;
        case 'AN':
            $slotStartTime = AN_START; // '12:50'
            break;
        case 'Full Day':
            // Full day booking allowed only before the day starts (08:10 AM)
            $slotStartTime = FN_START;
            break;
        default:
            if (strpos($slot, 'Period') === 0) {
                $periodNum = intval(str_replace('Period ', '', $slot));
                $periodTimes = [
                    1 => '08:10', 2 => '09:00', 3 => '10:10', 4 => '11:00', 5 => '11:50',
                    6 => '12:50', 7 => '13:40', 8 => '14:30', 9 => '15:40', 10 => '16:30'
                ];
                if (isset($periodTimes[$periodNum])) {
                    $slotStartTime = $periodTimes[$periodNum];
                }
            }
    }
    
    if ($slotStartTime) {
        $slotStart = DateTime::createFromFormat('Y-m-d H:i', "$today $slotStartTime");
        if ($slotStart && $now >= $slotStart) {
            $errorMsg = ($slot === 'Full Day') 
                ? 'Cannot book Full Day. Full day booking must be made before 8:10 AM. The day has already started.'
                : "Cannot book {$slot}. Booking must be made before {$slotStartTime}. Current time: " . $now->format('H:i');
            echo json_encode(['error' => $errorMsg]);
            exit;
        }
    }
}

try {
    $db = getDB();

    $calendar = fetchOne("SELECT * FROM academic_calendar WHERE calendar_date = ?", [$bookingDate]);
    if ($calendar && $calendar['type'] !== 'normal') {
        echo json_encode(['error' => 'Booking blocked: This date is marked as ' . ucfirst($calendar['type'])]);
        exit;
    }

    $existingUserBooking = fetchOne("
        SELECT id, status FROM bookings 
        WHERE staff_id = ? AND lab_id = ? AND booking_date = ? AND time_slot = ? 
        AND status IN ('pending', 'approved')
    ", [$_SESSION['user_id'], $labId, $bookingDate, $slot]);
    if ($existingUserBooking) {
        echo json_encode(['error' => 'You already have a ' . $existingUserBooking['status'] . ' booking for this slot']);
        exit;
    }

    $existingAnyBooking = fetchOne("
        SELECT id, status FROM bookings 
        WHERE lab_id = ? AND booking_date = ? AND time_slot = ? 
        AND status IN ('pending', 'approved')
    ", [$labId, $bookingDate, $slot]);
    if ($existingAnyBooking) {
        echo json_encode(['error' => "This slot is already booked (Status: {$existingAnyBooking['status']})"]);
        exit;
    }

    $fnAnCheck = fetchAll("
        SELECT time_slot FROM bookings 
        WHERE lab_id = ? AND booking_date = ? AND status IN ('pending', 'approved')
        AND time_slot IN ('FN', 'AN', 'Full Day')
    ", [$labId, $bookingDate]);
    
    foreach ($fnAnCheck as $existing) {
        $existingSlot = $existing['time_slot'];
        $isFnPeriod = (strpos($slot, 'Period') === 0 && intval(str_replace('Period ', '', $slot)) <= 5);
        $isAnPeriod = (strpos($slot, 'Period') === 0 && intval(str_replace('Period ', '', $slot)) >= 6);
        
        if ($existingSlot === 'Full Day' || 
            ($existingSlot === 'FN' && ($isFnPeriod || $slot === 'FN')) ||
            ($existingSlot === 'AN' && ($isAnPeriod || $slot === 'AN'))) {
            echo json_encode(['error' => "Conflicts with existing $existingSlot booking"]);
            exit;
        }
    }

    $dayOrder = 1;
    if ($calendar && isset($calendar['day_order'])) {
        if (preg_match('/\d+/', $calendar['day_order'], $matches)) {
            $dayOrder = (int)$matches[0];
        }
    } else {
        $dayOrder = date('N', strtotime($bookingDate)) ?: 1;
    }

    $timetable = fetchAll("
        SELECT * FROM timetable 
        WHERE lab_id = ? AND day_order = ? AND is_active = TRUE
    ", [$labId, $dayOrder]);

    $hasConflict = false;
    $conflictReason = '';

    foreach ($timetable as $t) {
        $conflict = false;
        if ($slot === 'FN' && $t['start_time'] < '12:00:00') $conflict = true;
        elseif ($slot === 'AN' && $t['start_time'] >= '12:00:00') $conflict = true;
        elseif ($slot === 'Full Day') $conflict = true;
        elseif (strpos($slot, 'Period') === 0) {
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
            $hasConflict = true;
            $conflictReason .= "Class '{$t['class_name']}' from {$t['start_time']} to {$t['end_time']}. ";
        }
    }

    $stmt = $db->prepare("
        INSERT INTO bookings 
        (staff_id, lab_id, booking_date, time_slot, purpose, has_conflict, conflict_reason, status, is_instant, created_at) 
        VALUES (?, ?, ?, ?, ?, ?, ?, 'pending', ?, NOW())
    ");

    $stmt->execute([
        $_SESSION['user_id'],
        $labId,
        $bookingDate,
        $slot,
        $purpose,
        $hasConflict ? 1 : 0,
        $conflictReason,
        $isInstant
    ]);
    
    $id = $db->lastInsertId();

    notifyNewBooking($id);

    echo json_encode([
        'success' => true,
        'message' => 'Booking submitted successfully! Waiting for admin approval.',
        'booking_id' => $id,
        'has_conflict' => $hasConflict
    ]);

} catch (PDOException $e) {
    error_log("PDO error: " . $e->getMessage());
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
} catch (Exception $e) {
    error_log("General error: " . $e->getMessage());
    echo json_encode(['error' => 'An error occurred: ' . $e->getMessage()]);
}
?>