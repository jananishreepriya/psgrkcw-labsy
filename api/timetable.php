<?php
require_once '../config.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // If an ID is provided, return that single entry
    $id = $_GET['id'] ?? '';
    if ($id) {
        $entry = fetchOne("SELECT * FROM timetable WHERE id = ? AND is_active = TRUE", [$id]);
        echo json_encode(['entry' => $entry]);
        exit;
    }

    // Otherwise return filtered list
    $labId = $_GET['lab_id'] ?? '';
    $dayOrder = $_GET['day_order'] ?? $_GET['day'] ?? ''; // Support both day_order and day params
    
    $sql = "SELECT t.*, l.lab_name, u.name as created_by_name 
            FROM timetable t 
            JOIN labs l ON t.lab_id = l.id 
            LEFT JOIN users u ON t.created_by = u.id 
            WHERE t.is_active = TRUE";
    $params = [];
    
    if ($labId) {
        $sql .= " AND t.lab_id = ?";
        $params[] = $labId;
    }
    if ($dayOrder !== '') {
        $sql .= " AND t.day_order = ?";
        $params[] = $dayOrder;
    }
    
    $sql .= " ORDER BY t.day_order, t.start_time";
    $timetable = fetchAll($sql, $params);
    echo json_encode(['timetable' => $timetable]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
        echo json_encode(['error' => 'Unauthorized']);
        exit;
    }
    
    $action = $_POST['action'] ?? 'add';
    
    if ($action === 'add') {
        $labId = intval($_POST['lab_id'] ?? 0);
        $dayOrder = intval($_POST['day_order'] ?? 0);
        $startTime = $_POST['start_time'] ?? '';
        $endTime = $_POST['end_time'] ?? '';
        $className = trim($_POST['class_name'] ?? '');
        $subject = trim($_POST['subject'] ?? '');
        $facultyName = trim($_POST['faculty_name'] ?? '');
        $semester = trim($_POST['semester'] ?? '');
        $headEmail = trim($_POST['head_email'] ?? '');
        
        if (empty($labId) || empty($dayOrder) || empty($startTime) || empty($endTime) || empty($className)) {
            echo json_encode(['error' => 'Required fields missing']);
            exit;
        }
        
        // Check for duplicate active entry (same lab, day order, start time)
        $existing = fetchOne("
            SELECT id FROM timetable 
            WHERE lab_id = ? AND day_order = ? AND start_time = ? AND is_active = TRUE
        ", [$labId, $dayOrder, $startTime]);

        if ($existing) {
            echo json_encode(['error' => 'This schedule already exists for this lab, day order, and time']);
            exit;
        }
        
        // Insert with head_email
        $id = insertId("INSERT INTO timetable (lab_id, day_order, start_time, end_time, class_name, subject, faculty_name, semester, head_email, created_by) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
            [$labId, $dayOrder, $startTime, $endTime, $className, 
             $subject, $facultyName, $semester, $headEmail, $_SESSION['user_id']]);
        
        echo json_encode(['success' => true, 'id' => $id]);
        exit;
    }
    
    if ($action === 'update') {
        $id = intval($_POST['id'] ?? 0);
        $labId = intval($_POST['lab_id'] ?? 0);
        $dayOrder = intval($_POST['day_order'] ?? 0);
        $startTime = $_POST['start_time'] ?? '';
        $endTime = $_POST['end_time'] ?? '';
        $className = trim($_POST['class_name'] ?? '');
        $subject = trim($_POST['subject'] ?? '');
        $facultyName = trim($_POST['faculty_name'] ?? '');
        $semester = trim($_POST['semester'] ?? '');
        $headEmail = trim($_POST['head_email'] ?? '');
        
        if (empty($id) || empty($labId) || empty($dayOrder) || empty($startTime) || empty($endTime) || empty($className)) {
            echo json_encode(['error' => 'Required fields missing']);
            exit;
        }
        
        // Optional: check for duplicate if lab/day/time changed, but we'll skip for simplicity
        
        query("UPDATE timetable SET lab_id=?, day_order=?, start_time=?, end_time=?, class_name=?, subject=?, faculty_name=?, semester=?, head_email=? WHERE id=? AND is_active=TRUE",
            [$labId, $dayOrder, $startTime, $endTime, $className, $subject, $facultyName, $semester, $headEmail, $id]);
        
        echo json_encode(['success' => true, 'id' => $id]);
        exit;
    }
    
    if ($action === 'delete') {
        $id = intval($_POST['id'] ?? 0);
        query("UPDATE timetable SET is_active = FALSE WHERE id = ?", [$id]);
        echo json_encode(['success' => true]);
        exit;
    }
}
?>