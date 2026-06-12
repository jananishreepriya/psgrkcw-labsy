<?php
require_once '../config.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$id = intval($_POST['id'] ?? 0);
$name = trim($_POST['name'] ?? '');
$email = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';
$labs = $_POST['labs'] ?? [];

if (empty($id) || empty($name) || empty($email)) {
    echo json_encode(['error' => 'ID, name and email are required']);
    exit;
}

$db = getDB();
$db->beginTransaction();

try {
    // Check email uniqueness
    $existing = fetchOne("SELECT id FROM users WHERE email = ? AND id != ? AND role = 'head'", [$email, $id]);
    if ($existing) {
        echo json_encode(['error' => 'Email already exists for another head']);
        $db->rollBack();
        exit;
    }

    if (!empty($password)) {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $db->prepare("UPDATE users SET name = ?, email = ?, password = ? WHERE id = ? AND role = 'head'");
        $stmt->execute([$name, $email, $hash, $id]);
    } else {
        $stmt = $db->prepare("UPDATE users SET name = ?, email = ? WHERE id = ? AND role = 'head'");
        $stmt->execute([$name, $email, $id]);
    }

    // Update lab assignments: delete old, insert new
    $db->prepare("DELETE FROM lab_heads WHERE head_id = ?")->execute([$id]);
    if (!empty($labs)) {
        $stmt = $db->prepare("INSERT INTO lab_heads (lab_id, head_id) VALUES (?, ?)");
        foreach ($labs as $labId) {
            $stmt->execute([$labId, $id]);
        }
    }

    $db->commit();
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    $db->rollBack();
    error_log("Update head error: " . $e->getMessage());
    echo json_encode(['error' => 'Database error']);
}
?>