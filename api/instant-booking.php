<?php
require_once '../config.php';
header('Content-Type: application/json');

// Ensure correct timezone
date_default_timezone_set('Asia/Kolkata');

ini_set('display_errors', 0);
error_reporting(E_ALL);

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'staff') {
    echo json_encode(['success' => false, 'error' => 'Please login as staff to book']);
    exit;
}

$labId = intval($_POST['lab_id'] ?? 0);
$date = $_POST['booking_date'] ?? '';
$periodNumber = intval($_POST['period_number'] ?? 0);
$startTime = $_POST['start_time'] ?? '';
$endTime = $_POST['end_time'] ?? '';
$purpose = trim($_POST['purpose'] ?? '');

// Determine period and times
if ($periodNumber > 0) {
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
    $startTime = $periodTimes[$periodNumber]['start'];
    $endTime = $periodTimes[$periodNumber]['end'];
} else {
    $periodMap = [
        '08:10' => 1, '09:00' => 2, '10:10' => 3, '11:00' => 4, '11:50' => 5,
        '12:50' => 6, '13:40' => 7, '14:30' => 8, '15:40' => 9, '16:30' => 10
    ];
    $periodNumber = $periodMap[$startTime] ?? 0;
}

if (empty($labId) || empty($date) || empty($purpose) || $periodNumber < 1) {
    echo json_encode(['success' => false, 'error' => 'All fields are required']);
    exit;
}

// Convert date to YYYY-MM-DD
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

// ============================================
// PAST DATE / TIME VALIDATION
// ============================================
$today = date('Y-m-d');
if (strtotime($bookingDate) < strtotime($today)) {
    echo json_encode(['success' => false, 'error' => 'Cannot book for past dates']);
    exit;
}

// For today's date, block slots that have already started
if ($bookingDate === $today) {
    $now = new DateTime();
    $slotStartTime = $startTime; // Already determined above
    
    if ($slotStartTime) {
        $slotStart = DateTime::createFromFormat('Y-m-d H:i', "$today $slotStartTime");
        if ($slotStart && $now >= $slotStart) {
            echo json_encode([
                'success' => false,
                'error' => 'Cannot book Period ' . $periodNumber . '. Booking must be made before ' . $slotStartTime . '. Current time: ' . $now->format('H:i')
            ]);
            exit;
        }
    }
}

try {
    $db = getDB();

    // Check if date is blocked
    $calendar = fetchOne("SELECT * FROM academic_calendar WHERE calendar_date = ?", [$bookingDate]);
    if ($calendar && $calendar['type'] != 'normal') {
        echo json_encode(['success' => false, 'error' => 'This date is blocked for bookings']);
        exit;
    }

    // Determine day order
    $dayOrder = 1;
    if ($calendar && isset($calendar['day_order'])) {
        if (preg_match('/\d+/', $calendar['day_order'], $matches)) {
            $dayOrder = (int)$matches[0];
        }
    } else {
        $dayOfWeek = date('N', strtotime($bookingDate));
        $dayOrder = ($dayOfWeek <= 6) ? $dayOfWeek : 1;
    }

    $slot = 'Period ' . $periodNumber;

    // Check for conflict request (locking)
    $conflictRequest = fetchOne("
        SELECT cr.*, u.name as staff_name 
        FROM conflict_requests cr
        JOIN users u ON cr.requesting_staff_id = u.id
        WHERE cr.lab_id = ? AND cr.booking_date = ? AND cr.time_slot = ? 
        AND cr.status = 'pending'
    ", [$labId, $bookingDate, $slot]);

    if ($conflictRequest) {
        echo json_encode([
            'success' => false, 
            'error' => 'Another staff (' . $conflictRequest['staff_name'] . ') has requested admin approval for this period. Please wait for admin decision.'
        ]);
        exit;
    }

    // Duplicate check
    $existing = fetchOne("SELECT id FROM bookings WHERE lab_id = ? AND booking_date = ? AND time_slot = ? AND status IN ('approved', 'pending')", [$labId, $bookingDate, $slot]);
    if ($existing) {
        echo json_encode(['success' => false, 'error' => 'This period is already booked']);
        exit;
    }

    // FN/AN conflict check
    $fnAnCheck = fetchAll("SELECT time_slot FROM bookings WHERE lab_id = ? AND booking_date = ? AND status IN ('approved','pending') AND time_slot IN ('FN','AN','Full Day')", [$labId, $bookingDate]);
    foreach ($fnAnCheck as $b) {
        $isFn = ($periodNumber <= 5);
        if ($b['time_slot'] === 'Full Day' || ($b['time_slot'] === 'FN' && $isFn) || ($b['time_slot'] === 'AN' && !$isFn)) {
            echo json_encode(['success' => false, 'error' => 'Conflicts with existing ' . $b['time_slot'] . ' booking']);
            exit;
        }
    }

    // Timetable conflict check
    $timetable = fetchAll("SELECT * FROM timetable WHERE lab_id = ? AND day_order = ? AND is_active = TRUE", [$labId, $dayOrder]);
    $hasConflict = false;
    $conflictClasses = [];

    foreach ($timetable as $t) {
        $classStart = substr($t['start_time'], 0, 5);
        $classEnd = substr($t['end_time'], 0, 5);
        if (!($endTime <= $classStart || $startTime >= $classEnd)) {
            $hasConflict = true;
            $conflictClasses[] = $t;
        }
    }

    // If conflict exists, require admin approval
    if ($hasConflict) {
        // Check if this user already has a pending request
        $myRequest = fetchOne("
            SELECT * FROM conflict_requests 
            WHERE lab_id = ? AND booking_date = ? AND time_slot = ? 
            AND requesting_staff_id = ? AND status = 'pending'
        ", [$labId, $bookingDate, $slot, $_SESSION['user_id']]);

        if ($myRequest) {
            echo json_encode(['success' => false, 'error' => 'You already have a pending approval request for this period']);
            exit;
        }

        // Create conflict request and pending booking
        $stmt = $db->prepare("
            INSERT INTO conflict_requests (lab_id, booking_date, time_slot, requesting_staff_id, status, created_at) 
            VALUES (?, ?, ?, ?, 'pending', NOW())
        ");
        $stmt->execute([$labId, $bookingDate, $slot, $_SESSION['user_id']]);

        $conflictReason = '';
        foreach ($conflictClasses as $t) {
            $conflictReason .= "Class '{$t['class_name']}' from {$t['start_time']} to {$t['end_time']}. ";
        }

        $stmt = $db->prepare("
            INSERT INTO bookings 
            (staff_id, lab_id, booking_date, time_slot, start_time, end_time, period_number, purpose, status, has_conflict, conflict_reason, is_instant, created_at) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending', 1, ?, 1, NOW())
        ");
        $stmt->execute([$_SESSION['user_id'], $labId, $bookingDate, $slot, $startTime, $endTime, $periodNumber, $purpose, $conflictReason]);
        $id = $db->lastInsertId();

        // Notify admin and heads about the pending request
        notifyNewBooking($id);

        echo json_encode([
            'success' => true, 
            'requires_approval' => true,
            'message' => 'This period has scheduled classes. Your request has been sent to admin for approval. Class heads will be notified if approved.',
            'booking_id' => $id,
            'conflicts' => $conflictClasses
        ]);
        exit;
    }

    // ============================================
    // NO CONFLICT - PROCEED WITH INSTANT APPROVAL
    // ============================================
    $stmt = $db->prepare("INSERT INTO bookings (staff_id, lab_id, booking_date, time_slot, start_time, end_time, period_number, purpose, status, is_instant, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'approved', 1, NOW())");
    $stmt->execute([$_SESSION['user_id'], $labId, $bookingDate, $slot, $startTime, $endTime, $periodNumber, $purpose]);
    $id = $db->lastInsertId();

    // Send confirmation email to staff
    sendInstantBookingConfirmation($id);

    echo json_encode([
        'success' => true, 
        'message' => 'Instant booking confirmed! A confirmation email has been sent.',
        'booking_id' => $id
    ]);

} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
} catch (Exception $e) {
    error_log("General error: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'An error occurred']);
}
?>