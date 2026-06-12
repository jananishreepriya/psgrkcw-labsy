<?php
require_once '../config.php';
header('Content-Type: application/json');

error_reporting(E_ALL);
ini_set('display_errors', 1);

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$id = intval($_POST['id'] ?? 0);
$status = $_POST['status'] ?? '';
$remarks = trim($_POST['remarks'] ?? '');

if (empty($id) || empty($status) || !in_array($status, ['approved', 'rejected'])) {
    echo json_encode(['success' => false, 'error' => 'Invalid parameters']);
    exit;
}

try {
    $db = getDB();
    
    // Get booking details
    $booking = fetchOne("SELECT * FROM bookings WHERE id = ?", [$id]);
    if (!$booking) {
        echo json_encode(['success' => false, 'error' => 'Booking not found']);
        exit;
    }

    // Check head approval for approval action
    if ($status === 'approved' && !$booking['head_approved']) {
        echo json_encode(['success' => false, 'error' => 'Head approval required before admin can approve.']);
        exit;
    }

    // Get full details for email notifications
    $bookingDetails = fetchOne("
        SELECT b.*, l.lab_name, u.name as staff_name, u.email as staff_email 
        FROM bookings b 
        JOIN labs l ON b.lab_id = l.id 
        JOIN users u ON b.staff_id = u.id 
        WHERE b.id = ?
    ", [$id]);

    $emailsSent = [];
    $cancelledBookings = [];

    // If approving, handle conflict notifications and cancel conflicting instant bookings
    if ($status === 'approved') {
        // If this booking has conflict with timetable, notify affected class heads
        if ($booking['has_conflict']) {
            // Determine day order for the booking date
            $dayOrder = 1;
            $calendar = fetchOne("SELECT * FROM academic_calendar WHERE calendar_date = ?", [$booking['booking_date']]);
            if ($calendar && preg_match('/\d+/', $calendar['day_order'] ?? '', $matches)) {
                $dayOrder = (int)$matches[0];
            } else {
                $dayOrder = date('N', strtotime($booking['booking_date'])) ?: 1;
            }

            $timetable = fetchAll("
                SELECT t.*, l.lab_name 
                FROM timetable t 
                JOIN labs l ON t.lab_id = l.id
                WHERE t.lab_id = ? AND t.day_order = ? AND t.is_active = TRUE
            ", [$booking['lab_id'], $dayOrder]);

            foreach ($timetable as $t) {
                $conflict = false;
                $slot = $booking['time_slot'];
                // Check conflict based on slot vs timetable entry
                if ($slot === 'FN' && $t['start_time'] < '12:00:00') $conflict = true;
                elseif ($slot === 'AN' && $t['start_time'] >= '12:00:00') $conflict = true;
                elseif ($slot === 'Full Day') $conflict = true;
                elseif (strpos($slot, 'Period') === 0) {
                    $pNum = intval(str_replace('Period ', '', $slot));
                    $periodStartTimes = ['08:10','09:00','10:10','11:00','11:50','12:50','13:40','14:30','15:40','16:30'];
                    $periodEndTimes   = ['09:00','09:50','11:00','11:50','12:40','13:40','14:30','15:20','16:30','17:20'];
                    if (isset($periodStartTimes[$pNum-1])) {
                        $pStart = $periodStartTimes[$pNum-1];
                        $pEnd = $periodEndTimes[$pNum-1];
                        $cStart = substr($t['start_time'],0,5);
                        $cEnd = substr($t['end_time'],0,5);
                        if (!($pEnd <= $cStart || $pStart >= $cEnd)) $conflict = true;
                    }
                }

                if ($conflict && !empty($t['head_email'])) {
                    $sent = sendClassCancellationEmail($t, $booking['booking_date'], $remarks ?: 'Priority booking approved', 'conflict');
                    if ($sent) $emailsSent[] = $t['head_email'];
                }
            }

            // Mark conflict request as approved
            $db->prepare("UPDATE conflict_requests SET status = 'approved' WHERE lab_id = ? AND booking_date = ? AND time_slot = ? AND status = 'pending'")
                ->execute([$booking['lab_id'], $booking['booking_date'], $booking['time_slot']]);
        }

        // Cancel conflicting instant bookings
        $conflictingInstantBookings = findConflictingInstantBookings($booking, $bookingDetails);
        foreach ($conflictingInstantBookings as $conflictBooking) {
            $db->prepare("UPDATE bookings SET status = 'rejected', admin_remarks = ? WHERE id = ?")
                ->execute(["Cancelled due to approved booking #{$id}", $conflictBooking['id']]);
            sendInstantBookingCancellationEmail($conflictBooking, $bookingDetails, $remarks);
            $cancelledBookings[] = $conflictBooking['id'];
        }
    }

    // If rejecting, also reject associated conflict request
    if ($status === 'rejected') {
        $db->prepare("UPDATE conflict_requests SET status = 'rejected' WHERE lab_id = ? AND booking_date = ? AND time_slot = ? AND status = 'pending'")
            ->execute([$booking['lab_id'], $booking['booking_date'], $booking['time_slot']]);
    }

    // Update the booking status
    $stmt = $db->prepare("UPDATE bookings SET status = ?, admin_remarks = ? WHERE id = ?");
    $stmt->execute([$status, $remarks, $id]);

    // Send email to staff about the decision
    $staffEmailSent = false;
    if ($bookingDetails && !empty($bookingDetails['staff_email'])) {
        $subject = $status === 'approved' 
            ? "✅ Your Booking Approved - " . $bookingDetails['lab_name'] 
            : "❌ Your Booking Rejected - " . $bookingDetails['lab_name'];
        $badge = $status === 'approved' ? '✅ APPROVED' : '❌ REJECTED';
        
        $content = '
        <div style="background: ' . ($status === 'approved' ? '#d1fae5' : '#fee2e2') . '; padding: 20px; border-radius: 12px; border-left: 5px solid ' . ($status === 'approved' ? '#059669' : '#dc2626') . '; margin-bottom: 20px;">
            <p style="margin:0; color:' . ($status === 'approved' ? '#065f46' : '#991b1b') . '; font-size:16px;"><strong>Your booking has been ' . $status . ' by admin.</strong></p>
        </div>
        <table style="width:100%; border-collapse: collapse; background: #f9fafb; border-radius: 12px; overflow: hidden;">
            <tr style="background: #f3f4f6;">
                <th style="padding: 12px 15px; text-align: left; width: 40%; color: #374151;">Lab</th>
                <td style="padding: 12px 15px; color: #1f2937;">' . htmlspecialchars($bookingDetails['lab_name']) . '</td>
            </tr>
            <tr>
                <th style="padding: 12px 15px; text-align: left; background: #f9fafb; color: #374151;">Date</th>
                <td style="padding: 12px 15px; background: #f9fafb;">' . date('l, d F Y', strtotime($bookingDetails['booking_date'])) . '</td>
            </tr>
            <tr style="background: #f3f4f6;">
                <th style="padding: 12px 15px; text-align: left; color: #374151;">Slot</th>
                <td style="padding: 12px 15px;">' . htmlspecialchars($bookingDetails['time_slot']) . '</td>
            </tr>
            <tr>
                <th style="padding: 12px 15px; text-align: left; background: #f9fafb; color: #374151;">Purpose</th>
                <td style="padding: 12px 15px; background: #f9fafb;">' . nl2br(htmlspecialchars($bookingDetails['purpose'])) . '</td>
            </tr>
            ' . ($remarks ? '<tr style="background: #f3f4f6;"><th style="padding: 12px 15px; text-align: left; color: #374151;">Admin Remarks</th><td style="padding: 12px 15px;">' . htmlspecialchars($remarks) . '</td></tr>' : '') . '
        </table>
        ';
        
        $body = getEmailTemplate('Booking ' . ucfirst($status), $content, $badge);
        $staffEmailSent = sendEmail($bookingDetails['staff_email'], $subject, $body);
        error_log("update-booking: Staff email to {$bookingDetails['staff_email']} sent: " . ($staffEmailSent ? 'yes' : 'no'));
    }

    echo json_encode([
        'success' => true,
        'message' => "Booking $status successfully!",
        'staff_email_sent' => $staffEmailSent,
        'heads_notified' => count($emailsSent),
        'instant_bookings_cancelled' => count($cancelledBookings)
    ]);

} catch (PDOException $e) {
    error_log("Update booking PDO error: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Database error occurred.']);
} catch (Exception $e) {
    error_log("Update booking general error: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'An unexpected error occurred.']);
}

// Helper functions used above
function findConflictingInstantBookings($newBooking, $newBookingDetails) {
    $conflicts = [];
    if (empty($newBooking['lab_id']) || empty($newBooking['booking_date']) || empty($newBooking['time_slot'])) {
        return $conflicts;
    }
    $newTimeRange = getBookingTimeRange($newBooking['time_slot']);
    $instantBookings = fetchAll("
        SELECT b.*, l.lab_name, u.name as staff_name, u.email as staff_email 
        FROM bookings b 
        JOIN labs l ON b.lab_id = l.id 
        JOIN users u ON b.staff_id = u.id 
        WHERE b.lab_id = ? 
        AND b.booking_date = ? 
        AND b.status = 'approved' 
        AND b.is_instant = 1
        AND b.id != ?
    ", [$newBooking['lab_id'], $newBooking['booking_date'], $newBooking['id']]);
    foreach ($instantBookings as $instantBooking) {
        $instantTimeRange = getBookingTimeRange($instantBooking['time_slot']);
        if (doTimeSlotsOverlap($newTimeRange, $instantTimeRange)) {
            $conflicts[] = $instantBooking;
        }
    }
    return $conflicts;
}

function getBookingTimeRange($slot) {
    $periodTimes = [
        'Period 1' => ['start' => '08:10', 'end' => '09:00'],
        'Period 2' => ['start' => '09:00', 'end' => '09:50'],
        'Period 3' => ['start' => '10:10', 'end' => '11:00'],
        'Period 4' => ['start' => '11:00', 'end' => '11:50'],
        'Period 5' => ['start' => '11:50', 'end' => '12:40'],
        'Period 6' => ['start' => '12:50', 'end' => '13:40'],
        'Period 7' => ['start' => '13:40', 'end' => '14:30'],
        'Period 8' => ['start' => '14:30', 'end' => '15:20'],
        'Period 9' => ['start' => '15:40', 'end' => '16:30'],
        'Period 10' => ['start' => '16:30', 'end' => '17:20'],
        'FN' => ['start' => FN_START, 'end' => FN_END],
        'AN' => ['start' => AN_START, 'end' => AN_END],
        'Full Day' => ['start' => FN_START, 'end' => AN_END]
    ];
    return $periodTimes[$slot] ?? ['start' => '00:00', 'end' => '00:00'];
}

function doTimeSlotsOverlap($range1, $range2) {
    $start1 = strtotime($range1['start']);
    $end1 = strtotime($range1['end']);
    $start2 = strtotime($range2['start']);
    $end2 = strtotime($range2['end']);
    return !($end1 <= $start2 || $end2 <= $start1);
}
?>