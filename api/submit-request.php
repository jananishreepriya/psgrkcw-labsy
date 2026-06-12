<?php
require_once '../config.php';
header('Content-Type: application/json');

$name = trim($_POST['name'] ?? '');
$email = trim($_POST['email'] ?? '');
$type = $_POST['request_type'] ?? 'login_issue';
$message = trim($_POST['message'] ?? '');

if (empty($name) || empty($email) || empty($message)) {
    echo json_encode(['success' => false, 'error' => 'All fields are required']);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'error' => 'Invalid email address']);
    exit;
}

if (!in_array($type, ['login_issue', 'new_staff', 'other'])) {
    $type = 'login_issue';
}

try {
    $db = getDB();
    $stmt = $db->prepare("INSERT INTO support_requests (name, email, request_type, message, source, status) VALUES (?, ?, ?, ?, 'login', 'pending')");
    $stmt->execute([$name, $email, $type, $message]);
    $requestId = $db->lastInsertId();

    $subject = "New Support Request #$requestId from Login Page - " . ucfirst($type);
    $adminContent = "
    <h2>New Support Request (from Login Page)</h2>
    <p><strong>From:</strong> " . htmlspecialchars($name) . " (" . htmlspecialchars($email) . ")</p>
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