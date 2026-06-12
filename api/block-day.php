<?php
require_once '../config.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $blocks = fetchAll("
        SELECT ac.*, u.name as blocked_by_name 
        FROM academic_calendar ac 
        LEFT JOIN users u ON ac.blocked_by = u.id 
        WHERE ac.type != 'normal'
        ORDER BY ac.calendar_date DESC
    ");
    echo json_encode(['blocks' => $blocks]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
        echo json_encode(['error' => 'Unauthorized']);
        exit;
    }
    
    $action = $_POST['action'] ?? 'block';
    
    if ($action === 'block') {
        $date = $_POST['date'] ?? '';
        $type = $_POST['type'] ?? 'holiday';
        $reason = trim($_POST['reason'] ?? '');
        
        if (empty($date) || empty($reason)) {
            echo json_encode(['error' => 'Date and reason are required']);
            exit;
        }
        
        // Get day order for the date to find timetable conflicts
        $dayOfWeek = date('w', strtotime($date));
        $dayNames = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
        $dayName = $dayNames[$dayOfWeek];
        $dayOrder = 'Day ' . (($dayOfWeek == 0) ? 7 : $dayOfWeek);
        $dayOrderNum = ($dayOfWeek == 0) ? 7 : $dayOfWeek;
        
        $exists = fetchOne("SELECT id FROM academic_calendar WHERE calendar_date = ?", [$date]);
        
        if ($exists) {
            query("UPDATE academic_calendar SET type = ?, block_reason = ?, blocked_by = ?, blocked_at = NOW() WHERE calendar_date = ?",
                [$type, $reason, $_SESSION['user_id'], $date]);
        } else {
            query("INSERT INTO academic_calendar (calendar_date, day_name, day_order, type, block_reason, blocked_by, blocked_at) 
                VALUES (?, ?, ?, ?, ?, ?, NOW())",
                [$date, $dayName, $dayOrder, $type, $reason, $_SESSION['user_id']]);
        }
        
        // Find affected timetable entries and notify heads
        $affectedClasses = fetchAll("
            SELECT t.*, l.lab_name, l.head_email, l.head_name 
            FROM timetable t 
            JOIN labs l ON t.lab_id = l.id 
            WHERE t.day_order = ? AND t.is_active = TRUE
        ", [$dayOrderNum]);
        
        $notifiedHeads = [];
        $emailErrors = [];
        
        if (!empty($affectedClasses)) {
            foreach ($affectedClasses as $class) {
                // Send email to head if email exists and not already notified
                if (!empty($class['head_email']) && !in_array($class['head_email'], $notifiedHeads)) {
                    if (function_exists('sendClassCancellationEmail')) {
                        $result = @sendClassCancellationEmail($class, $date, $reason, $type);
                        if ($result) {
                            $notifiedHeads[] = $class['head_email'];
                        } else {
                            $emailErrors[] = $class['head_email'];
                        }
                    }
                }
            }
        }
        
        echo json_encode([
            'success' => true, 
            'message' => 'Day blocked successfully',
            'notified_heads' => count($notifiedHeads),
            'affected_classes' => count($affectedClasses),
            'email_errors' => $emailErrors
        ]);
        exit;
    }
    
    if ($action === 'unblock') {
        $date = $_POST['date'] ?? '';
        query("UPDATE academic_calendar SET type = 'normal', block_reason = NULL, blocked_by = NULL, blocked_at = NULL WHERE calendar_date = ?", [$date]);
        echo json_encode(['success' => true, 'message' => 'Day unblocked successfully']);
        exit;
    }
}
?>