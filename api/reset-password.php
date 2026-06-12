<?php
require_once __DIR__ . '/../config.php';

header('Content-Type: application/json');

error_log("=== Reset Password API Called ===");
error_log("POST data: " . print_r($_POST, true));

$action = $_POST['action'] ?? '';
$token = $_POST['token'] ?? '';
$password = $_POST['password'] ?? '';
$role = $_POST['role'] ?? '';

if ($action !== 'reset_password') {
    echo json_encode(['success' => false, 'error' => 'Invalid action']);
    exit;
}

if (empty($token) || empty($password) || empty($role)) {
    echo json_encode(['success' => false, 'error' => 'All fields are required']);
    exit;
}

// Validate password strength
if (strlen($password) < 8) {
    echo json_encode(['success' => false, 'error' => 'Password must be at least 8 characters']);
    exit;
}

if (!preg_match('/[A-Z]/', $password)) {
    echo json_encode(['success' => false, 'error' => 'Password must contain at least one uppercase letter']);
    exit;
}

if (!preg_match('/[a-z]/', $password)) {
    echo json_encode(['success' => false, 'error' => 'Password must contain at least one lowercase letter']);
    exit;
}

if (!preg_match('/[0-9]/', $password)) {
    echo json_encode(['success' => false, 'error' => 'Password must contain at least one number']);
    exit;
}

if (!preg_match('/[!@#$%^&*()_+\-=\[\]{};\':"\\|,.<>\/?]/', $password)) {
    echo json_encode(['success' => false, 'error' => 'Password must contain at least one special character']);
    exit;
}

try {
    $db = getDB();
    
    // Verify token
    $stmt = $db->prepare("
        SELECT pr.*, u.id as user_id, u.email, u.role 
        FROM password_resets pr
        JOIN users u ON pr.user_id = u.id
        WHERE pr.token = ? AND pr.used = FALSE AND pr.expires_at > NOW()
    ");
    $stmt->execute([$token]);
    $reset = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$reset) {
        echo json_encode(['success' => false, 'error' => 'Invalid or expired token']);
        exit;
    }
    
    // Verify role matches
    if ($reset['role'] !== $role) {
        echo json_encode(['success' => false, 'error' => 'Invalid token for this user type']);
        exit;
    }
    
    // Hash new password
    $hash = password_hash($password, PASSWORD_DEFAULT);
    
    // Update user password
    $stmt = $db->prepare("UPDATE users SET password = ? WHERE id = ?");
    $stmt->execute([$hash, $reset['user_id']]);
    
    // Mark token as used
    $stmt = $db->prepare("UPDATE password_resets SET used = TRUE WHERE id = ?");
    $stmt->execute([$reset['id']]);
    
    // Delete all other tokens for this user
    $stmt = $db->prepare("DELETE FROM password_resets WHERE user_id = ? AND id != ?");
    $stmt->execute([$reset['user_id'], $reset['id']]);
    
    echo json_encode(['success' => true, 'message' => '✅ Password reset successfully! Redirecting to login...']);
    
} catch (PDOException $e) {
    error_log("Database error in reset-password.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Database error occurred']);
} catch (Exception $e) {
    error_log("General error in reset-password.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'An error occurred']);
}
?>