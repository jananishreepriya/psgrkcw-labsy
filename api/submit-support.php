<?php
require_once '../config.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_role'], ['staff', 'head'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$message = trim($_POST['message'] ?? '');
$type = $_POST['request_type'] ?? 'issue';

if (empty($message)) {
    echo json_encode(['success' => false, 'error' => 'Message cannot be empty']);
    exit;
}

$userId = $_SESSION['user_id'];
$userRole = $_SESSION['user_role'];
$userName = $_SESSION['user_name'];
$userEmail = $_SESSION['user_email'];
$source = $userRole;

try {
    $db = getDB();
    $stmt = $db->prepare("INSERT INTO support_requests (user_id, user_role, source, name, email, request_type, message, status) VALUES (?, ?, ?, ?, ?, ?, ?, 'pending')");
    $stmt->execute([$userId, $userRole, $source, $userName, $userEmail, $type, $message]);
    $requestId = $db->lastInsertId();

    $subject = "New Support Request #$requestId from $userRole: " . ucfirst($type);
    $adminContent = "
    <h2>Support Request from $userRole Dashboard</h2>
    <p><strong>From:</strong> $userName ($userEmail)</p>
    <p><strong>Type:</strong> " . ucfirst($type) . "</p>
    <p><strong>Message:</strong><br>" . nl2br(htmlspecialchars($message)) . "</p>
    <p><a href='" . BASE_URL . "admin.html?page=support'>View in Admin Panel</a></p>
    ";
    $body = getEmailTemplate('Support Request', $adminContent);
    sendEmail(ADMIN_EMAIL, $subject, $body);

    echo json_encode(['success' => true, 'message' => 'Request submitted. Admin will respond soon.']);
} catch (PDOException $e) {
    error_log("Support request error: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Database error']);
}
?>