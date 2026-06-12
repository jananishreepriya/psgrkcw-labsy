<?php
require_once __DIR__ . '/../config.php';

header('Content-Type: application/json');

error_log("=== Auth API Called ===");
error_log("POST data: " . print_r($_POST, true));

$action = $_POST['action'] ?? '';
$email = trim($_POST['email'] ?? '');
$role = trim($_POST['role'] ?? '');

if ($action !== 'forgot_password') {
    echo json_encode(['success' => false, 'error' => 'Invalid action']);
    exit;
}

if (empty($email) || empty($role)) {
    echo json_encode(['success' => false, 'error' => 'Email and role are required']);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'error' => 'Invalid email format']);
    exit;
}

try {
    $db = getDB();
    
    // Check if user exists with exact role and active status
    $stmt = $db->prepare("SELECT id, name, email FROM users WHERE email = ? AND role = ? AND status = 'active'");
    $stmt->execute([$email, $role]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        error_log("No active user found for email=$email, role=$role");
        echo json_encode(['success' => false, 'error' => 'No account found with this email for the selected role.']);
        exit;
    }
    
    // Generate token
    $token = bin2hex(random_bytes(32));
    $expires = date('Y-m-d H:i:s', strtotime('+24 hours'));
    
    // Create table if not exists
    $db->exec("
        CREATE TABLE IF NOT EXISTS password_resets (
            id INT PRIMARY KEY AUTO_INCREMENT,
            user_id INT NOT NULL,
            token VARCHAR(100) NOT NULL UNIQUE,
            expires_at DATETIME NOT NULL,
            used BOOLEAN DEFAULT FALSE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            INDEX (token),
            INDEX (expires_at)
        )
    ");
    
    // Delete old tokens
    $stmt = $db->prepare("DELETE FROM password_resets WHERE user_id = ?");
    $stmt->execute([$user['id']]);
    
    // Insert new token
    $stmt = $db->prepare("INSERT INTO password_resets (user_id, token, expires_at) VALUES (?, ?, ?)");
    $stmt->execute([$user['id'], $token, $expires]);
    
    // Build reset link
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'];
    $resetLink = $protocol . "://" . $host . "/psgrkcw-labsy/reset-password.php?token=" . $token . "&role=" . $role;
    
    // Email content
    $subject = "Password Reset Request - PSGRKCW LABSY";
    $body = "
    <!DOCTYPE html>
    <html>
    <head>
        <style>
            body { font-family: 'Segoe UI', Arial, sans-serif; background: #f3f4f6; padding: 20px; margin:0; }
            .container { max-width: 550px; margin: 0 auto; background: white; border-radius: 16px; overflow: hidden; box-shadow: 0 20px 40px rgba(0,0,0,0.1); }
            .header { background: linear-gradient(135deg, #6b21a8 0%, #4c1d95 100%); color: white; padding: 30px; text-align: center; }
            .header h1 { margin: 0; font-size: 28px; font-weight: 600; }
            .content { padding: 30px; }
            .btn { display: inline-block; background: linear-gradient(135deg, #6b21a8, #4c1d95); color: white; padding: 12px 30px; text-decoration: none; border-radius: 50px; font-weight: 600; margin: 20px 0; box-shadow: 0 4px 15px rgba(107,33,168,0.3); }
            .footer { background: #f9fafb; padding: 20px; text-align: center; color: #6b7280; font-size: 12px; border-top: 1px solid #e5e7eb; }
            .link-box { background: #f3f4f6; padding: 12px; border-radius: 8px; word-break: break-all; font-size: 13px; margin-top: 15px; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1>🔬 PSGRKCW LABSY</h1>
                <p>Laboratory Management System</p>
            </div>
            <div class='content'>
                <h2 style='color:#1f2937; margin:0 0 10px;'>Password Reset Request</h2>
                <p>Hello " . htmlspecialchars($user['name']) . ",</p>
                <p>We received a request to reset your password. Click the button below to create a new password:</p>
                <div style='text-align: center;'>
                    <a href='" . $resetLink . "' class='btn'>Reset Password</a>
                </div>
                <p>Or copy this link into your browser:</p>
                <div class='link-box'>" . $resetLink . "</div>
                <p style='margin-top: 20px; font-size: 13px; color: #6b7280;'>This link expires in 24 hours. If you did not request this, please ignore this email.</p>
            </div>
            <div class='footer'>
                <p>PSGR Krishnammal College for Women</p>
                <p>Developed by Janani Shree Priya M | 2023 B.Sc Computer Science</p>
            </div>
        </div>
    </body>
    </html>";
    
    if (!function_exists('sendEmail')) {
        echo json_encode(['success' => false, 'error' => 'Email service not configured.']);
        exit;
    }
    
    $emailSent = sendEmail($email, $subject, $body);
    if ($emailSent) {
        echo json_encode(['success' => true, 'message' => '✅ Reset link sent! Check your email.']);
    } else {
        echo json_encode(['success' => false, 'error' => 'Failed to send email. Please try again.']);
    }
    
} catch (Exception $e) {
    error_log("Forgot password error: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Server error occurred.']);
}
?>