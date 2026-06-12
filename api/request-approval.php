<?php
require_once '../config.php';
header('Content-Type: application/json');

date_default_timezone_set('Asia/Kolkata');

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'staff') {
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$labId = intval($_POST['lab_id'] ?? 0);
$date = trim($_POST['booking_date'] ?? '');
$slot = trim($_POST['time_slot'] ?? '');
$purpose = trim($_POST['purpose'] ?? '');

$bookingDate = $date;
if (strpos($date, '-') !== false && substr_count($date, '-') === 2) {
    $parts = explode('-', $date);
    if (strlen($parts[0]) === 2) {
        $dt = DateTime::createFromFormat('d-m-Y', $date);
        if ($dt) $bookingDate = $dt->format('Y-m-d');
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
    echo json_encode(['error' => 'Cannot request approval for past dates']);
    exit;
}

if ($bookingDate === $today) {
    $now = new DateTime();
    $slotStartTime = '';
    
    switch ($slot) {
        case 'FN':       $slotStartTime = FN_START; break;
        case 'AN':       $slotStartTime = AN_START; break;
        case 'Full Day': $slotStartTime = FN_START; break;
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
            echo json_encode([
                'error' => 'Cannot request approval for ' . $slot . '. The time slot has already started (before ' . $slotStartTime . '). Current time: ' . $now->format('H:i')
            ]);
            exit;
        }
    }
}

// Continue with the rest of your existing request-approval.php code...
// (the logic remains unchanged)

try {
    $db = getDB();
    $db->beginTransaction();

    // Check if this user already has a pending request for this slot
    $myPendingRequest = fetchOne("
        SELECT * FROM conflict_requests 
        WHERE lab_id = ? AND booking_date = ? AND time_slot = ? 
        AND status = 'pending' AND requesting_staff_id = ?
    ", [$labId, $bookingDate, $slot, $_SESSION['user_id']]);
    if ($myPendingRequest) {
        $db->rollBack();
        echo json_encode(['error' => 'You have already requested approval for this slot. Please wait for admin decision.']);
        exit;
    }

    // Check if pending request exists from other users
    $otherPending = fetchOne("
        SELECT cr.*, u.name as staff_name 
        FROM conflict_requests cr
        JOIN users u ON cr.requesting_staff_id = u.id
        WHERE cr.lab_id = ? AND cr.booking_date = ? AND cr.time_slot = ? 
        AND cr.status = 'pending'
        AND cr.requesting_staff_id != ?
    ", [$labId, $bookingDate, $slot, $_SESSION['user_id']]);
    if ($otherPending) {
        $db->rollBack();
        echo json_encode(['error' => 'Another staff (' . $otherPending['staff_name'] . ') has already requested approval for this slot']);
        exit;
    }

    // Check if this user already has a booking for this slot
    $myExistingBooking = fetchOne("
        SELECT * FROM bookings 
        WHERE lab_id = ? AND booking_date = ? AND time_slot = ? 
        AND staff_id = ? AND status IN ('pending', 'approved')
    ", [$labId, $bookingDate, $slot, $_SESSION['user_id']]);
    if ($myExistingBooking) {
        $db->rollBack();
        echo json_encode(['error' => 'You already have a booking for this slot']);
        exit;
    }

    // Check if regular booking exists from other users
    $existingRegularBooking = fetchOne("
        SELECT b.*, u.name as staff_name 
        FROM bookings b 
        JOIN users u ON b.staff_id = u.id
        WHERE b.lab_id = ? AND b.booking_date = ? AND b.time_slot = ? 
        AND b.status IN ('pending', 'approved')
        AND b.is_instant = 0
        AND b.staff_id != ?
    ", [$labId, $bookingDate, $slot, $_SESSION['user_id']]);
    if ($existingRegularBooking) {
        $db->rollBack();
        echo json_encode(['error' => 'This slot is already booked by ' . $existingRegularBooking['staff_name'] . ' (regular booking)']);
        exit;
    }

    // Delete any existing conflict request for this lab/date/slot (including old approved/rejected)
    $db->prepare("DELETE FROM conflict_requests WHERE lab_id = ? AND booking_date = ? AND time_slot = ?")
        ->execute([$labId, $bookingDate, $slot]);

    // Get conflict reason from timetable
    $dayOrder = 1;
    $calendar = fetchOne("SELECT * FROM academic_calendar WHERE calendar_date = ?", [$bookingDate]);
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
            $conflictReason .= "Class '{$t['class_name']}' from {$t['start_time']} to {$t['end_time']}. ";
        }
    }

    // Create new conflict request
    $stmt = $db->prepare("
        INSERT INTO conflict_requests (lab_id, booking_date, time_slot, requesting_staff_id, status, created_at) 
        VALUES (?, ?, ?, ?, 'pending', NOW())
    ");
    $stmt->execute([$labId, $bookingDate, $slot, $_SESSION['user_id']]);

    // Create the booking as pending with conflict flag
    $stmt = $db->prepare("
        INSERT INTO bookings 
        (staff_id, lab_id, booking_date, time_slot, purpose, has_conflict, conflict_reason, status, is_instant, created_at) 
        VALUES (?, ?, ?, ?, ?, 1, ?, 'pending', 0, NOW())
    ");
    $stmt->execute([
        $_SESSION['user_id'],
        $labId,
        $bookingDate,
        $slot,
        $purpose,
        $conflictReason
    ]);
    
    $bookingId = $db->lastInsertId();
    $db->commit();

    notifyNewBooking($bookingId);

    echo json_encode([
        'success' => true,
        'message' => 'Approval request submitted successfully! Admin will review and notify affected class heads if approved.',
        'booking_id' => $bookingId
    ]);

} catch (PDOException $e) {
    if ($db->inTransaction()) $db->rollBack();
    error_log("Request approval error: " . $e->getMessage());
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
?>