<?php
require_once '../config.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$name = trim($_POST['name'] ?? '');
$email = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';
$labs = $_POST['labs'] ?? []; // array of lab IDs

if (empty($name) || empty($email) || empty($password)) {
    echo json_encode(['error' => 'All fields required']);
    exit;
}

if (strlen($password) < 6) {
    echo json_encode(['error' => 'Password must be at least 6 characters']);
    exit;
}

// Check if email already exists
$existing = fetchOne("SELECT id FROM users WHERE email = ?", [$email]);
if ($existing) {
    echo json_encode(['error' => 'Email already exists']);
    exit;
}

$db = getDB();
$db->beginTransaction();

try {
    $hash = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $db->prepare("INSERT INTO users (name, email, password, role, status) VALUES (?, ?, ?, 'head', 'active')");
    $stmt->execute([$name, $email, $hash]);
    $headId = $db->lastInsertId();

    // Assign labs
    if (!empty($labs)) {
        $stmt = $db->prepare("INSERT INTO lab_heads (lab_id, head_id) VALUES (?, ?)");
        foreach ($labs as $labId) {
            $stmt->execute([$labId, $headId]);
        }
    }

    $db->commit();
    echo json_encode(['success' => true, 'id' => $headId]);
} catch (Exception $e) {
    $db->rollBack();
    error_log("Add head error: " . $e->getMessage());
    echo json_encode(['error' => 'Database error']);
}
?>