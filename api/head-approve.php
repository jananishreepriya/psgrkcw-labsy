<?php
require_once '../config.php';
header('Content-Type: application/json');

error_log("=== head-approve.php called ===");
error_log("POST data: " . print_r($_POST, true));

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'head') {
    error_log("head-approve: Unauthorized access attempt");
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$bookingId = intval($_POST['id'] ?? 0);
$action = $_POST['action'] ?? '';
$remarks = trim($_POST['remarks'] ?? '');

error_log("head-approve: bookingId=$bookingId, action=$action, remarks=$remarks");

if (!$bookingId || !in_array($action, ['approve', 'reject'])) {
    error_log("head-approve: Invalid parameters");
    echo json_encode(['error' => 'Invalid request']);
    exit;
}

$headId = $_SESSION['user_id'];

$booking = fetchOne("
    SELECT b.*, l.lab_name, l.id as lab_id, u.name as staff_name, u.email as staff_email
    FROM bookings b
    JOIN labs l ON b.lab_id = l.id
    JOIN users u ON b.staff_id = u.id
    WHERE b.id = ?
", [$bookingId]);

if (!$booking) {
    error_log("head-approve: Booking $bookingId not found");
    echo json_encode(['error' => 'Booking not found']);
    exit;
}

error_log("head-approve: Booking found: lab_id={$booking['lab_id']}, staff={$booking['staff_name']}, status={$booking['status']}");

$labHead = fetchOne("SELECT * FROM lab_heads WHERE lab_id = ? AND head_id = ?", [$booking['lab_id'], $headId]);
if (!$labHead) {
    error_log("head-approve: Head $headId not authorized for lab {$booking['lab_id']}");
    echo json_encode(['error' => 'You are not authorized to handle bookings for this lab']);
    exit;
}

if ($booking['status'] !== 'pending') {
    error_log("head-approve: Booking already processed, current status: {$booking['status']}");
    echo json_encode(['error' => 'This booking has already been processed']);
    exit;
}

$db = getDB();

if ($action === 'approve') {
    error_log("head-approve: Processing approval for booking $bookingId");
    $remarksText = "\nHead approved: " . date('Y-m-d H:i:s') . " - " . ($remarks ?: 'Approved');
    $stmt = $db->prepare("
        UPDATE bookings
        SET status = 'head_approved', head_approved = 1, head_approved_at = NOW(), admin_remarks = CONCAT(admin_remarks, ?)
        WHERE id = ? AND status = 'pending'
    ");
    $stmt->execute([$remarksText, $bookingId]);
    $affected = $stmt->rowCount();
    error_log("head-approve: Approval update affected $affected rows");

    if ($affected > 0) {
        // --- Send email to ADMIN ---
        $subject = "Head Approval Received - Booking #{$bookingId}";
        $content = '
        <div style="background: #d1fae5; padding: 20px; border-radius: 12px; border-left: 5px solid #059669; margin-bottom: 20px;">
            <p style="margin:0; color:#065f46; font-size:16px;"><strong>A head has approved a pending booking.</strong></p>
        </div>
        <table style="width:100%; border-collapse: collapse; background: #f9fafb; border-radius: 12px; overflow: hidden;">
            <tr style="background: #f3f4f6;">
                <th style="padding: 12px 15px; text-align: left; width: 40%; color: #374151;">Head</th>
                <td style="padding: 12px 15px; color: #1f2937;">' . htmlspecialchars($_SESSION['user_name']) . '</td>
            </tr>
            <tr>
                <th style="padding: 12px 15px; text-align: left; background: #f9fafb; color: #374151;">Staff</th>
                <td style="padding: 12px 15px; background: #f9fafb;">' . htmlspecialchars($booking['staff_name']) . ' (' . htmlspecialchars($booking['staff_email']) . ')</td>
            </tr>
            <tr style="background: #f3f4f6;">
                <th style="padding: 12px 15px; text-align: left; color: #374151;">Lab</th>
                <td style="padding: 12px 15px;">' . htmlspecialchars($booking['lab_name']) . '</td>
            </tr>
            <tr>
                <th style="padding: 12px 15px; text-align: left; background: #f9fafb; color: #374151;">Date</th>
                <td style="padding: 12px 15px; background: #f9fafb;">' . date('l, d F Y', strtotime($booking['booking_date'])) . '</td>
            </tr>
            <tr style="background: #f3f4f6;">
                <th style="padding: 12px 15px; text-align: left; color: #374151;">Slot</th>
                <td style="padding: 12px 15px;">' . htmlspecialchars($booking['time_slot']) . '</td>
            </tr>
            <tr>
                <th style="padding: 12px 15px; text-align: left; background: #f9fafb; color: #374151;">Remarks</th>
                <td style="padding: 12px 15px; background: #f9fafb;">' . htmlspecialchars($remarks ?: 'None') . '</td>
            </tr>
        </table>
        <div style="text-align: center; margin-top: 25px;">
            <a href="' . BASE_URL . 'admin.html" style="background: linear-gradient(135deg, #6b21a8, #4c1d95); color:white; padding: 12px 30px; text-decoration: none; border-radius: 50px; font-weight:600; display: inline-block; box-shadow:0 4px 15px rgba(107,33,168,0.3);">Review in Admin Panel</a>
        </div>
        ';
        
        $body = getEmailTemplate('Head Approval Received', $content);
        error_log("head-approve: Attempting to send admin email to " . ADMIN_EMAIL);
        $emailSent = sendEmail(ADMIN_EMAIL, $subject, $body);
        error_log("head-approve: Admin email sent: " . ($emailSent ? 'yes' : 'no'));
        
        if (!$emailSent) {
            error_log("head-approve: FAILED to send admin email. Check SMTP configuration in config.php");
        }

        echo json_encode(['success' => true, 'message' => 'Booking approved successfully. Admin has been notified.']);
    } else {
        error_log("head-approve: Approval update failed (no rows affected)");
        echo json_encode(['error' => 'Failed to approve booking']);
    }
} elseif ($action === 'reject') {
    error_log("head-approve: Processing rejection for booking $bookingId");
    $remarksText = "\nHead rejected: " . date('Y-m-d H:i:s') . " - " . ($remarks ?: 'Rejected');
    $stmt = $db->prepare("
        UPDATE bookings
        SET status = 'rejected', admin_remarks = CONCAT(admin_remarks, ?)
        WHERE id = ? AND status = 'pending'
    ");
    $stmt->execute([$remarksText, $bookingId]);
    $affected = $stmt->rowCount();
    error_log("head-approve: Rejection update affected $affected rows");

    if ($affected > 0) {
        // --- Send to staff ---
        $staffSubject = "Your Booking Request Has Been Rejected - " . $booking['lab_name'];
        $staffContent = '
        <div style="background: #fee2e2; padding: 20px; border-radius: 12px; border-left: 5px solid #dc2626; margin-bottom: 20px;">
            <p style="margin:0; color:#991b1b; font-size:16px;"><strong>Your booking request was not approved by the lab head.</strong></p>
        </div>
        <table style="width:100%; border-collapse: collapse; background: #f9fafb; border-radius: 12px; overflow: hidden;">
            <tr style="background: #f3f4f6;">
                <th style="padding: 12px 15px; text-align: left; width: 40%; color: #374151;">Lab</th>
                <td style="padding: 12px 15px; color: #1f2937;">' . htmlspecialchars($booking['lab_name']) . '</td>
            </tr>
            <tr>
                <th style="padding: 12px 15px; text-align: left; background: #f9fafb; color: #374151;">Date</th>
                <td style="padding: 12px 15px; background: #f9fafb;">' . date('l, d F Y', strtotime($booking['booking_date'])) . '</td>
            </tr>
            <tr style="background: #f3f4f6;">
                <th style="padding: 12px 15px; text-align: left; color: #374151;">Slot</th>
                <td style="padding: 12px 15px;">' . htmlspecialchars($booking['time_slot']) . '</td>
            </tr>
            <tr>
                <th style="padding: 12px 15px; text-align: left; background: #f9fafb; color: #374151;">Head Remarks</th>
                <td style="padding: 12px 15px; background: #f9fafb;">' . htmlspecialchars($remarks ?: 'No remarks provided') . '</td>
            </tr>
        </table>
        <p style="margin-top: 20px; color: #374151;">If you have questions, please contact the lab head directly.</p>
        ';
        $staffBody = getEmailTemplate('Booking Request Rejected', $staffContent);
        sendEmail($booking['staff_email'], $staffSubject, $staffBody);

        // --- Send to admin ---
        $adminSubject = "Head Rejected a Booking - #{$bookingId}";
        $adminContent = '
        <div style="background: #fee2e2; padding: 20px; border-radius: 12px; border-left: 5px solid #dc2626; margin-bottom: 20px;">
            <p style="margin:0; color:#991b1b; font-size:16px;"><strong>A head has rejected a pending booking.</strong></p>
        </div>
        <table style="width:100%; border-collapse: collapse; background: #f9fafb; border-radius: 12px; overflow: hidden;">
            <tr style="background: #f3f4f6;">
                <th style="padding: 12px 15px; text-align: left; width: 40%; color: #374151;">Head</th>
                <td style="padding: 12px 15px; color: #1f2937;">' . htmlspecialchars($_SESSION['user_name']) . '</td>
            </tr>
            <tr>
                <th style="padding: 12px 15px; text-align: left; background: #f9fafb; color: #374151;">Staff</th>
                <td style="padding: 12px 15px; background: #f9fafb;">' . htmlspecialchars($booking['staff_name']) . ' (' . htmlspecialchars($booking['staff_email']) . ')</td>
            </tr>
            <tr style="background: #f3f4f6;">
                <th style="padding: 12px 15px; text-align: left; color: #374151;">Lab</th>
                <td style="padding: 12px 15px;">' . htmlspecialchars($booking['lab_name']) . '</td>
            </tr>
            <tr>
                <th style="padding: 12px 15px; text-align: left; background: #f9fafb; color: #374151;">Date</th>
                <td style="padding: 12px 15px; background: #f9fafb;">' . date('l, d F Y', strtotime($booking['booking_date'])) . '</td>
            </tr>
            <tr style="background: #f3f4f6;">
                <th style="padding: 12px 15px; text-align: left; color: #374151;">Slot</th>
                <td style="padding: 12px 15px;">' . htmlspecialchars($booking['time_slot']) . '</td>
            </tr>
            <tr>
                <th style="padding: 12px 15px; text-align: left; background: #f9fafb; color: #374151;">Head Remarks</th>
                <td style="padding: 12px 15px; background: #f9fafb;">' . htmlspecialchars($remarks ?: 'None') . '</td>
            </tr>
        </table>
        <div style="text-align: center; margin-top: 25px;">
            <a href="' . BASE_URL . 'admin.html" style="background: linear-gradient(135deg, #6b21a8, #4c1d95); color:white; padding: 12px 30px; text-decoration: none; border-radius: 50px; font-weight:600; display: inline-block; box-shadow:0 4px 15px rgba(107,33,168,0.3);">View in Admin Panel</a>
        </div>
        ';
        $adminBody = getEmailTemplate('Head Rejected Booking', $adminContent, '❌ REJECTED');
        sendEmail(ADMIN_EMAIL, $adminSubject, $adminBody);

        echo json_encode(['success' => true, 'message' => 'Booking rejected successfully. Admin and staff notified.']);
    } else {
        error_log("head-approve: Rejection update failed (no rows affected) – booking may already be processed");
        echo json_encode(['error' => 'Failed to reject booking (already processed?)']);
    }
}
?>