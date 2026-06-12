<?php
session_start();
date_default_timezone_set('Asia/Kolkata');

// Load Composer's autoloader (only if vendor exists)
if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
}

// Use statement must be at top level, not inside condition
use Dotenv\Dotenv;

// Load .env file if it exists (local development)
if (file_exists(__DIR__ . '/.env')) {
    $dotenv = Dotenv::createImmutable(__DIR__);
    $dotenv->load();
}

// Helper function to get env vars with fallback
function env($key, $default = null) {
    $value = getenv($key);
    if ($value === false) {
        $value = $_ENV[$key] ?? $_SERVER[$key] ?? null;
    }
    return $value !== null ? $value : $default;
}

// ============================================
// CONSTANTS (read from .env or use defaults)
// ============================================

// Database
define('DB_HOST', env('DB_HOST', 'localhost'));
define('DB_USER', env('DB_USER', 'root'));
define('DB_PASS', env('DB_PASS', ''));
define('DB_NAME', env('DB_NAME', 'psgrkcw_labsy'));

// Google OAuth
define('GOOGLE_CLIENT_ID', env('GOOGLE_CLIENT_ID', ''));
define('GOOGLE_CLIENT_SECRET', env('GOOGLE_CLIENT_SECRET', ''));
define('GOOGLE_REDIRECT_URI', env('GOOGLE_REDIRECT_URI', ''));

// Admin
define('ADMIN_EMAIL', env('ADMIN_EMAIL', 'admin@example.com'));

// SMTP
define('SMTP_HOST', env('SMTP_HOST', 'smtp.gmail.com'));
define('SMTP_PORT', env('SMTP_PORT', 587));
define('SMTP_USERNAME', env('SMTP_USERNAME', ''));
define('SMTP_PASSWORD', env('SMTP_PASSWORD', ''));
define('SMTP_FROM_EMAIL', env('SMTP_FROM_EMAIL', ''));
define('SMTP_FROM_NAME', env('SMTP_FROM_NAME', 'PSGRKCW LABSY'));

// Other constants
define('FN_START', '08:10');
define('FN_END', '12:40');
define('AN_START', '12:50');
define('AN_END', '17:20');
define('BASE_URL', env('BASE_URL', 'http://localhost/psgrkcw-labsy/'));

// ============================================
// DATABASE FUNCTIONS
// ============================================

function getDB() {
    static $pdo = null;
    if ($pdo === null) {
        try {
            $pdo = new PDO("mysql:host=".DB_HOST.";dbname=".DB_NAME.";charset=utf8mb4", DB_USER, DB_PASS);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Database connection failed: " . $e->getMessage());
            die(json_encode(['error' => 'Database connection failed']));
        }
    }
    return $pdo;
}

function query($sql, $params = []) {
    $stmt = getDB()->prepare($sql);
    $stmt->execute($params);
    return $stmt;
}

function fetchOne($sql, $params = []) {
    return query($sql, $params)->fetch();
}

function fetchAll($sql, $params = []) {
    return query($sql, $params)->fetchAll();
}

function insertId($sql, $params = []) {
    $db = getDB();
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    return $db->lastInsertId();
}

function getAllHeads() {
    return fetchAll("SELECT * FROM users WHERE role = 'head' ORDER BY name");
}

function getHeadsLabs($headId) {
    return fetchAll("
        SELECT l.* FROM labs l
        JOIN lab_heads lh ON l.id = lh.lab_id
        WHERE lh.head_id = ?
    ", [$headId]);
}

function getLabHeads($labId) {
    error_log("getLabHeads: Fetching heads for lab ID $labId");
    $heads = fetchAll("
        SELECT u.* FROM users u
        JOIN lab_heads lh ON u.id = lh.head_id
        WHERE lh.lab_id = ?
    ", [$labId]);
    error_log("getLabHeads: Found " . count($heads) . " head records");
    foreach ($heads as $i => $h) {
        error_log("getLabHeads: Head #$i => ID={$h['id']}, Name={$h['name']}, Email={$h['email']}, Role={$h['role']}, Status={$h['status']}");
    }
    return $heads;
}

function getLabHeadEmails($labId) {
    $heads = getLabHeads($labId);
    $emails = [];
    foreach ($heads as $head) {
        if (!empty($head['email'])) {
            $emails[] = $head['email'];
        } else {
            error_log("getLabHeadEmails: Head ID {$head['id']} has empty email – skipping");
        }
    }
    error_log("getLabHeadEmails: Returning " . count($emails) . " emails: " . implode(', ', $emails));
    return $emails;
}

function getGoogleLoginUrl() {
    $params = [
        'client_id' => GOOGLE_CLIENT_ID,
        'redirect_uri' => GOOGLE_REDIRECT_URI,
        'response_type' => 'code',
        'scope' => 'email profile openid',
        'access_type' => 'online',
        'prompt' => 'select_account consent'
    ];
    return 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query($params);
}

// ============================================
// EMAIL FUNCTIONS (PHPMailer)
// ============================================

function getSMTPInstance() {
    static $mail = null;
    if ($mail === null) {
        $phpmailer_path = __DIR__ . '/vendor/phpmailer/phpmailer/src/';
        if (!file_exists($phpmailer_path . 'PHPMailer.php')) {
            error_log("PHPMailer not found at: " . $phpmailer_path);
            return false;
        }
        require_once $phpmailer_path . 'PHPMailer.php';
        require_once $phpmailer_path . 'SMTP.php';
        require_once $phpmailer_path . 'Exception.php';
        
        try {
            $mail = new PHPMailer\PHPMailer\PHPMailer(true);
            $mail->isSMTP();
            $mail->Host = SMTP_HOST;
            $mail->SMTPAuth = true;
            $mail->Username = SMTP_USERNAME;
            $mail->Password = SMTP_PASSWORD;
            $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = SMTP_PORT;
            $mail->Timeout = 5;
            $mail->SMTPKeepAlive = true;
            $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
            $mail->isHTML(true);
            error_log("SMTP instance created successfully");
        } catch (Exception $e) {
            error_log("SMTP instance creation failed: " . $e->getMessage());
            return false;
        }
    }
    return $mail;
}

function sendEmail($to, $subject, $body, $altBody = '') {
    error_log("sendEmail: Attempting to send to $to, subject: $subject");
    try {
        $mail = getSMTPInstance();
        if (!$mail) {
            error_log("sendEmail: getSMTPInstance() returned false");
            return false;
        }
        
        $mail->clearAddresses();
        $mail->addAddress($to);
        $mail->Subject = $subject;
        $mail->Body = $body;
        $mail->AltBody = $altBody ?: strip_tags($body);
        
        $result = $mail->send();
        error_log("sendEmail: SUCCESS to $to, subject: $subject");
        return $result;
    } catch (Exception $e) {
        error_log("sendEmail: FAILED to $to - Error: " . $e->getMessage());
        return false;
    }
}

function getEmailTemplate($title, $content, $badge = '') {
    $badgeHtml = $badge ? "<div style='text-align:center; margin:20px 0;'><span style='background: linear-gradient(135deg, #8b5cf6, #6d28d9); color:#ffffff; padding:8px 25px; border-radius:30px; font-weight:bold; font-size:16px; display:inline-block; box-shadow:0 4px 10px rgba(139,92,246,0.3);'>$badge</span></div>" : '';
    return '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>PSGRKCW LABSY</title>
        <style>
            body, table, td, p, a { font-family: "Segoe UI", Arial, sans-serif; }
        </style>
    </head>
    <body style="margin:0; padding:20px; background-color:#f3f4f6; font-family: \'Segoe UI\', Arial, sans-serif;">
        <div style="max-width:600px; margin:0 auto; background-color:#ffffff; border-radius:16px; overflow:hidden; box-shadow:0 20px 40px rgba(0,0,0,0.1);">
            <div style="background: linear-gradient(135deg, #6b21a8 0%, #4c1d95 100%); padding:30px; text-align:center;">
                <h1 style="margin:0; color:#ffffff; font-size:28px; font-weight:600;">🔬 PSGRKCW LABSY</h1>
                <p style="margin:5px 0 0; color:rgba(255,255,255,0.9);">Laboratory Management System</p>
            </div>
            <div style="padding:30px;">
                <h2 style="color:#1f2937; margin:0 0 10px; text-align:center;">' . $title . '</h2>
                ' . $badgeHtml . '
                ' . $content . '
            </div>
            <div style="background:#f9fafb; padding:20px; text-align:center; border-top:1px solid #e5e7eb; color:#6b7280; font-size:13px;">
                <p style="margin:5px 0;"><strong>PSGR Krishnammal College for Women</strong></p>
                <p style="margin:5px 0;">Developed by Janani Shree Priya M | 2023 B.Sc Computer Science</p>
                <p style="margin:5px 0; font-size:11px;">This is an automated notification. Please do not reply.</p>
            </div>
        </div>
    </body>
    </html>';
}

// ============================================
// BOOKING NOTIFICATION FUNCTIONS
// ============================================

function notifyNewBooking($bookingId) {
    error_log("notifyNewBooking: START for booking ID $bookingId");
    $booking = fetchOne("
        SELECT b.*, l.lab_name, u.name as staff_name, u.email as staff_email 
        FROM bookings b 
        JOIN labs l ON b.lab_id = l.id 
        JOIN users u ON b.staff_id = u.id 
        WHERE b.id = ?
    ", [$bookingId]);

    if (!$booking) {
        error_log("notifyNewBooking: Booking $bookingId not found");
        return;
    }

    $labId = $booking['lab_id'];
    $headEmails = getLabHeadEmails($labId);
    error_log("notifyNewBooking: Found " . count($headEmails) . " heads for lab $labId");
    if (count($headEmails) > 0) {
        error_log("notifyNewBooking: Head emails: " . implode(', ', $headEmails));
    } else {
        error_log("notifyNewBooking: WARNING – No head emails found for lab $labId");
    }
    
    $subject = "New Booking Request: " . $booking['lab_name'] . " - " . $booking['time_slot'];
    
    $instantTag = $booking['is_instant'] ? '⚡ ' : '';
    $badge = $booking['is_instant'] ? '⚡ INSTANT BOOKING REQUEST' : '📋 NEW BOOKING REQUEST';
    
    $content = '
    <div style="background: #ede9fe; padding: 20px; border-radius: 12px; border-left: 5px solid #6b21a8; margin-bottom: 20px;">
        <p style="margin:0; color:#4c1d95; font-size:16px;"><strong>A new booking requires your attention.</strong></p>
    </div>
    
    <table style="width:100%; border-collapse: collapse; background: #f9fafb; border-radius: 12px; overflow: hidden; margin-bottom:20px;">
        <tr style="background: #f3f4f6;">
            <th style="padding: 12px 15px; text-align: left; width: 40%; color: #374151; font-weight:600;">Staff</th>
            <td style="padding: 12px 15px; color: #1f2937;">' . htmlspecialchars($booking['staff_name']) . ' (' . htmlspecialchars($booking['staff_email']) . ')</td>
        </tr>
        <tr>
            <th style="padding: 12px 15px; text-align: left; background: #f9fafb; color: #374151; font-weight:600;">Lab</th>
            <td style="padding: 12px 15px; background: #f9fafb;">' . htmlspecialchars($booking['lab_name']) . '</td>
        </tr>
        <tr style="background: #f3f4f6;">
            <th style="padding: 12px 15px; text-align: left; color: #374151; font-weight:600;">Date</th>
            <td style="padding: 12px 15px;">' . date('l, d F Y', strtotime($booking['booking_date'])) . '</td>
        </tr>
        <tr>
            <th style="padding: 12px 15px; text-align: left; background: #f9fafb; color: #374151; font-weight:600;">Time Slot</th>
            <td style="padding: 12px 15px; background: #f9fafb;">' . htmlspecialchars($booking['time_slot']) . ' (' . getTimeRange($booking['time_slot']) . ')</td>
        </tr>
        <tr style="background: #f3f4f6;">
            <th style="padding: 12px 15px; text-align: left; color: #374151; font-weight:600;">Purpose</th>
            <td style="padding: 12px 15px;">' . nl2br(htmlspecialchars($booking['purpose'])) . '</td>
        </tr>
     </table>
    
    <div style="text-align: center; margin-top: 25px;">
        <a href="' . BASE_URL . 'head.html" style="background: linear-gradient(135deg, #6b21a8, #4c1d95); color:#ffffff; padding: 12px 30px; text-decoration: none; border-radius: 50px; font-weight:600; display: inline-block; box-shadow:0 4px 15px rgba(107,33,168,0.3);">Review in Head Panel</a>
    </div>
    ';
    
    $body = getEmailTemplate('New Booking Request', $content, $badge);
    
    // Send only to heads (admin will be notified after head approval)
    foreach ($headEmails as $headEmail) {
        $headSent = sendEmail($headEmail, $instantTag . $subject, $body);
        error_log("notifyNewBooking: Head email to $headEmail sent: " . ($headSent ? 'yes' : 'no'));
    }
}

function sendInstantBookingConfirmation($bookingId) {
    error_log("sendInstantBookingConfirmation: START for booking ID $bookingId");
    $booking = fetchOne("
        SELECT b.*, l.lab_name, u.name as staff_name, u.email as staff_email 
        FROM bookings b 
        JOIN labs l ON b.lab_id = l.id 
        JOIN users u ON b.staff_id = u.id 
        WHERE b.id = ?
    ", [$bookingId]);
    if (!$booking) {
        error_log("sendInstantBookingConfirmation: Booking $bookingId not found");
        return;
    }
    
    $subject = "✅ INSTANT BOOKING CONFIRMED - " . $booking['lab_name'];
    $content = '
    <div style="background: #d1fae5; padding: 20px; border-radius: 12px; border-left: 5px solid #059669; margin-bottom: 20px;">
        <p style="margin:0; color:#065f46; font-size:16px;"><strong>Your instant booking has been automatically approved!</strong></p>
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
            <th style="padding: 12px 15px; text-align: left; color: #374151;">Time Slot</th>
            <td style="padding: 12px 15px;">' . htmlspecialchars($booking['time_slot']) . ' (' . getTimeRange($booking['time_slot']) . ')</td>
        </tr>
        <tr>
            <th style="padding: 12px 15px; text-align: left; background: #f9fafb; color: #374151;">Purpose</th>
            <td style="padding: 12px 15px; background: #f9fafb;">' . nl2br(htmlspecialchars($booking['purpose'])) . '</td>
        </tr>
     </table>
    
    <div style="background: #eff6ff; padding: 15px; border-radius: 8px; margin-top: 20px; border-left: 4px solid #3b82f6;">
        <p style="margin:0; color:#1e40af;"><strong>ℹ️ Note:</strong> You can use the lab at the scheduled time. Please carry your ID card.</p>
    </div>
    ';
    
    $body = getEmailTemplate('Booking Confirmed', $content, '⚡ INSTANT BOOKING');
    $sent = sendEmail($booking['staff_email'], $subject, $body);
    error_log("sendInstantBookingConfirmation: Email to {$booking['staff_email']} sent: " . ($sent ? 'yes' : 'no'));
}

function sendClassCancellationEmail($class, $date, $reason, $type) {
    if (empty($class['head_email'])) {
        error_log("sendClassCancellationEmail: No head email for class " . $class['class_name']);
        return false;
    }
    $subject = '⚠️ Class Cancellation Notice - ' . ucfirst($type);
    $content = '
    <div style="background: #fee2e2; padding: 20px; border-radius: 12px; border-left: 5px solid #dc2626; margin-bottom: 20px;">
        <p style="margin:0; color:#991b1b; font-size:16px;"><strong>A class has been cancelled due to a lab booking.</strong></p>
    </div>
    
    <table style="width:100%; border-collapse: collapse; background: #f9fafb; border-radius: 12px; overflow: hidden;">
        <tr style="background: #f3f4f6;">
            <th style="padding: 12px 15px; text-align: left; width: 40%; color: #374151;">Class</th>
            <td style="padding: 12px 15px; color: #1f2937;">' . htmlspecialchars($class['class_name']) . '</td>
        </tr>
        <tr>
            <th style="padding: 12px 15px; text-align: left; background: #f9fafb; color: #374151;">Subject</th>
            <td style="padding: 12px 15px; background: #f9fafb;">' . htmlspecialchars($class['subject'] ?? 'N/A') . '</td>
        </tr>
        <tr style="background: #f3f4f6;">
            <th style="padding: 12px 15px; text-align: left; color: #374151;">Faculty</th>
            <td style="padding: 12px 15px;">' . htmlspecialchars($class['faculty_name'] ?? 'N/A') . '</td>
         </tr>
        <tr>
            <th style="padding: 12px 15px; text-align: left; background: #f9fafb; color: #374151;">Lab</th>
            <td style="padding: 12px 15px; background: #f9fafb;">' . htmlspecialchars($class['lab_name']) . '</td>
         </tr>
        <tr style="background: #f3f4f6;">
            <th style="padding: 12px 15px; text-align: left; color: #374151;">Date</th>
            <td style="padding: 12px 15px;">' . date('l, d F Y', strtotime($date)) . '</td>
         </tr>
        <tr>
            <th style="padding: 12px 15px; text-align: left; background: #f9fafb; color: #374151;">Reason</th>
            <td style="padding: 12px 15px; background: #f9fafb;">' . htmlspecialchars($reason) . '</td>
         </tr>
     </table>
    
    <p style="margin-top:20px; color:#4b5563;">Please make alternative arrangements for your class.</p>
    ';
    
    $body = getEmailTemplate('Class Cancellation', $content);
    $sent = sendEmail($class['head_email'], $subject, $body);
    error_log("sendClassCancellationEmail: Email to {$class['head_email']} sent: " . ($sent ? 'yes' : 'no'));
    return $sent;
}

function sendInstantBookingCancellationEmail($cancelledBooking, $newBookingDetails, $remarks) {
    if (empty($cancelledBooking['staff_email'])) {
        error_log("sendInstantBookingCancellationEmail: No staff email for cancelled booking ID " . $cancelledBooking['id']);
        return false;
    }
    $subject = "❌ CANCELLED: Your Instant Booking - " . $cancelledBooking['lab_name'];
    $content = '
    <div style="background: #fee2e2; padding: 20px; border-radius: 12px; border-left: 5px solid #dc2626; margin-bottom: 20px;">
        <p style="margin:0; color:#991b1b; font-size:16px;"><strong>Your instant booking has been cancelled.</strong></p>
    </div>
    
    <div style="background: #fef3c7; padding: 15px; border-radius: 8px; margin-bottom: 20px; border-left: 4px solid #f59e0b;">
        <p style="margin:0; color:#92400e;"><strong>Reason:</strong> ' . htmlspecialchars($remarks ?: 'Priority booking approved by admin') . '</p>
    </div>
    
    <h3 style="color:#1f2937; margin:20px 0 10px;">Your Cancelled Booking</h3>
    <table style="width:100%; border-collapse: collapse; background: #f9fafb; border-radius: 12px; overflow: hidden;">
        <tr style="background: #f3f4f6;">
            <th style="padding: 12px 15px; text-align: left; width: 40%; color: #374151;">Lab</th>
            <td style="padding: 12px 15px; color: #1f2937;">' . htmlspecialchars($cancelledBooking['lab_name']) . '</td>
         </tr>
        <tr>
            <th style="padding: 12px 15px; text-align: left; background: #f9fafb; color: #374151;">Date</th>
            <td style="padding: 12px 15px; background: #f9fafb;">' . date('l, d F Y', strtotime($cancelledBooking['booking_date'])) . '</td>
         </tr>
        <tr style="background: #f3f4f6;">
            <th style="padding: 12px 15px; text-align: left; color: #374151;">Time Slot</th>
            <td style="padding: 12px 15px;">' . htmlspecialchars($cancelledBooking['time_slot']) . '</td>
         </tr>
     </table>
    
    <h3 style="color:#1f2937; margin:20px 0 10px;">Booking That Replaced Yours</h3>
    <table style="width:100%; border-collapse: collapse; background: #dbeafe; border-radius: 12px; overflow: hidden;">
        <tr style="background: #bfdbfe;">
            <th style="padding: 12px 15px; text-align: left; width: 40%; color: #1e40af;">Staff</th>
            <td style="padding: 12px 15px; color: #1e3a8a;">' . htmlspecialchars($newBookingDetails['staff_name']) . '</td>
         </tr>
        <tr>
            <th style="padding: 12px 15px; text-align: left; background: #dbeafe; color: #1e40af;">Purpose</th>
            <td style="padding: 12px 15px; background: #dbeafe;">' . nl2br(htmlspecialchars($newBookingDetails['purpose'])) . '</td>
         </tr>
     </table>
    
    <div style="background: #f0fdf4; padding: 20px; border-radius: 8px; margin-top: 20px;">
        <p style="margin:0 0 10px; color:#065f46; font-weight:600;">✅ What you can do now:</p>
        <ul style="margin:0; padding-left:20px; color:#047857;">
            <li>Book a different time slot for the same lab</li>
            <li>Choose another lab for the same time</li>
            <li>Contact admin if you believe this was an error</li>
        </ul>
    </div>
    ';
    
    $body = getEmailTemplate('Booking Cancelled', $content, '❌ CANCELLED');
    $sent = sendEmail($cancelledBooking['staff_email'], $subject, $body);
    error_log("sendInstantBookingCancellationEmail: Email to {$cancelledBooking['staff_email']} sent: " . ($sent ? 'yes' : 'no'));
    return $sent;
}

function getTimeRange($slot) {
    $periodMap = [
        'Period 1' => '08:10 - 09:00',
        'Period 2' => '09:00 - 09:50',
        'Period 3' => '10:10 - 11:00',
        'Period 4' => '11:00 - 11:50',
        'Period 5' => '11:50 - 12:40',
        'Period 6' => '12:50 - 13:40',
        'Period 7' => '13:40 - 14:30',
        'Period 8' => '14:30 - 15:20',
        'Period 9' => '15:40 - 16:30',
        'Period 10' => '16:30 - 17:20',
        'FN' => FN_START . ' - ' . FN_END,
        'AN' => AN_START . ' - ' . AN_END,
        'Full Day' => FN_START . ' - ' . AN_END
    ];
    return $periodMap[$slot] ?? $slot;
}
?>