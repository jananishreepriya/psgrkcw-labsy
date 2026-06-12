<?php
require_once '../config.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$id = intval($_POST['id'] ?? 0);
$labName = trim($_POST['lab_name'] ?? '');
$description = trim($_POST['description'] ?? '');
$capacity = intval($_POST['capacity'] ?? 0);

if (empty($id) || empty($labName) || $capacity < 1) {
    echo json_encode(['success' => false, 'error' => 'ID, lab name and capacity are required']);
    exit;
}

try {
    $db = getDB();
    
    // Check if lab name already exists for other labs
    $existing = fetchOne("SELECT id FROM labs WHERE lab_name = ? AND id != ?", [$labName, $id]);
    if ($existing) {
        echo json_encode(['success' => false, 'error' => 'Lab name already exists']);
        exit;
    }
    
    $stmt = $db->prepare("UPDATE labs SET lab_name = ?, description = ?, capacity = ? WHERE id = ?");
    $stmt->execute([$labName, $description, $capacity, $id]);
    
    echo json_encode(['success' => true, 'message' => 'Lab updated successfully']);
    
} catch (PDOException $e) {
    error_log("Update lab error: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Database error']);
} catch (Exception $e) {
    error_log("General error: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'An error occurred']);
}
?>