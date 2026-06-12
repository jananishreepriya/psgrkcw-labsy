<?php
require_once '../config.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$id = intval($_POST['id'] ?? 0);
$name = trim($_POST['name'] ?? '');
$email = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';

if (empty($id) || empty($name) || empty($email)) {
    echo json_encode(['success' => false, 'error' => 'ID, name and email are required']);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'error' => 'Invalid email format']);
    exit;
}

try {
    $db = getDB();
    
    // Check if email already exists for other staff
    $existing = fetchOne("SELECT id FROM users WHERE email = ? AND id != ? AND role = 'staff'", [$email, $id]);
    if ($existing) {
        echo json_encode(['success' => false, 'error' => 'Email already exists for another staff member']);
        exit;
    }
    
    // Build update query
    if (!empty($password)) {
        // Update with new password
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $db->prepare("UPDATE users SET name = ?, email = ?, password = ? WHERE id = ? AND role = 'staff'");
        $stmt->execute([$name, $email, $hashedPassword, $id]);
    } else {
        // Update without changing password
        $stmt = $db->prepare("UPDATE users SET name = ?, email = ? WHERE id = ? AND role = 'staff'");
        $stmt->execute([$name, $email, $id]);
    }
    
    echo json_encode(['success' => true, 'message' => 'Staff updated successfully']);
    
} catch (PDOException $e) {
    error_log("Update staff error: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Database error']);
} catch (Exception $e) {
    error_log("General error: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'An error occurred']);
}
?>