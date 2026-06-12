<?php
require_once '../config.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$date = $_POST['date'] ?? '';
$type = strtolower($_POST['type'] ?? '');
$day_order = $_POST['day_order'] ?? null; // optional

if (empty($date) || empty($type)) {
    echo json_encode(['error' => 'Date and type are required']);
    exit;
}

$allowed_types = ['normal', 'holiday', 'exam', 'cultural', 'maintenance', 'other'];
if (!in_array($type, $allowed_types)) {
    echo json_encode(['error' => 'Invalid type. Allowed: ' . implode(', ', $allowed_types)]);
    exit;
}

try {
    $db = getDB();
    
    // Check if record exists
    $exists = fetchOne("SELECT id FROM academic_calendar WHERE calendar_date = ?", [$date]);
    
    if ($exists) {
        if ($day_order !== null && trim($day_order) !== '') {
            $stmt = $db->prepare("UPDATE academic_calendar SET type = ?, day_order = ? WHERE calendar_date = ?");
            $stmt->execute([$type, trim($day_order), $date]);
        } else {
            $stmt = $db->prepare("UPDATE academic_calendar SET type = ? WHERE calendar_date = ?");
            $stmt->execute([$type, $date]);
        }
    } else {
        // Insert new record with provided day order or auto-generate
        $dayName = date('l', strtotime($date));
        $dayOfWeek = date('w', strtotime($date));
        if ($day_order === null || trim($day_order) === '') {
            // Auto-generate day order based on previous day (simple fallback)
            $prev = fetchOne("SELECT day_order FROM academic_calendar WHERE calendar_date < ? ORDER BY calendar_date DESC LIMIT 1", [$date]);
            $lastNum = 6;
            if ($prev && preg_match('/\d+/', $prev['day_order'], $m)) $lastNum = (int)$m[0];
            $nextNum = ($lastNum % 6) + 1;
            $day_order = "Day {$nextNum}";
        }
        $stmt = $db->prepare("INSERT INTO academic_calendar (calendar_date, day_name, day_order, type) VALUES (?, ?, ?, ?)");
        $stmt->execute([$date, $dayName, trim($day_order), $type]);
    }
    
    echo json_encode(['success' => true]);
    
} catch (PDOException $e) {
    error_log("Update day error: " . $e->getMessage());
    echo json_encode(['error' => 'Database error']);
}
?>