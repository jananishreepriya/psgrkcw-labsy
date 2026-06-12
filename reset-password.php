<?php
require_once __DIR__ . '/config.php';

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

$token = $_GET['token'] ?? '';
$role = $_GET['role'] ?? '';

// Debug: Log the received token
error_log("Reset password page accessed with token: " . $token . ", role: " . $role);

if (empty($token) || empty($role)) {
    header('Location: login.html?error=missing_token');
    exit;
}

// Verify token
try {
    $db = getDB();
    
    // First, check if token exists at all
    $stmt = $db->prepare("SELECT * FROM password_resets WHERE token = ?");
    $stmt->execute([$token]);
    $tokenExists = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$tokenExists) {
        error_log("Token not found in database: " . $token);
        header('Location: login.html?error=invalid_token');
        exit;
    }
    
    // Check if token is used
    if ($tokenExists['used'] == 1) {
        error_log("Token already used: " . $token);
        header('Location: login.html?error=token_used');
        exit;
    }
    
    // Check if token expired
    $now = date('Y-m-d H:i:s');
    if ($tokenExists['expires_at'] < $now) {
        error_log("Token expired. Expires: " . $tokenExists['expires_at'] . ", Now: " . $now);
        header('Location: login.html?error=token_expired');
        exit;
    }
    
    // Get user details
    $stmt = $db->prepare("
        SELECT u.* 
        FROM users u
        WHERE u.id = ?
    ");
    $stmt->execute([$tokenExists['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        error_log("User not found for token: " . $token);
        header('Location: login.html?error=user_not_found');
        exit;
    }
    
    // Verify role matches
    if ($user['role'] !== $role) {
        error_log("Role mismatch. Expected: " . $role . ", Found: " . $user['role']);
        header('Location: login.html?error=role_mismatch');
        exit;
    }
    
    $email = $user['email'];
    $name = $user['name'];
    $userRole = $user['role'];
    
} catch (PDOException $e) {
    error_log("Database error in reset-password.php: " . $e->getMessage());
    header('Location: login.html?error=server_error');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - PSGRKCW LABSY</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            min-height: 100vh;
            background: linear-gradient(135deg, #6b21a8 0%, #4c1d95 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            position: relative;
            overflow-x: hidden;
        }

        /* Animated background bubbles */
        .bubbles {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: 0;
            overflow: hidden;
            pointer-events: none;
        }

        .bubble {
            position: absolute;
            background: rgba(255,255,255,0.1);
            border-radius: 50%;
            animation: float 20s infinite ease-in-out;
        }

        .bubble-1 { width: 300px; height: 300px; top: -100px; left: -100px; animation-duration: 25s; }
        .bubble-2 { width: 400px; height: 400px; top: 20%; right: -150px; animation-duration: 30s; animation-delay: 2s; }
        .bubble-3 { width: 250px; height: 250px; bottom: 10%; left: 10%; animation-duration: 22s; animation-delay: 4s; }
        .bubble-4 { width: 350px; height: 350px; bottom: 20%; right: 20%; animation-duration: 28s; animation-delay: 1s; }
        .bubble-5 { width: 200px; height: 200px; top: 40%; left: 30%; animation-duration: 18s; animation-delay: 3s; }
        .bubble-6 { width: 450px; height: 450px; top: 60%; left: -200px; animation-duration: 35s; animation-delay: 5s; }

        @keyframes float {
            0%,100% { transform: translate(0,0) scale(1); }
            25% { transform: translate(50px,-50px) scale(1.1); }
            50% { transform: translate(-30px,30px) scale(0.9); }
            75% { transform: translate(30px,50px) scale(1.05); }
        }

        .container {
            width: 100%;
            max-width: 500px;
            position: relative;
            z-index: 10;
        }

        .reset-box {
            background: rgba(255,255,255,0.95);
            backdrop-filter: blur(10px);
            border-radius: 24px;
            padding: 40px 35px;
            box-shadow: 0 25px 50px rgba(0,0,0,0.3);
            border: 1px solid rgba(255,255,255,0.2);
        }

        .reset-header {
            text-align: center;
            margin-bottom: 30px;
        }

        .reset-header i {
            font-size: 56px;
            background: linear-gradient(135deg, #6b21a8, #4c1d95);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 15px;
        }

        .reset-header h2 {
            color: #1f2937;
            font-size: 28px;
            font-weight: 700;
            margin-bottom: 8px;
        }

        .reset-header p {
            color: #6b7280;
            font-size: 15px;
        }

        .reset-header .user-name {
            color: #6b21a8;
            font-weight: 600;
            margin-top: 10px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #374151;
            font-weight: 500;
            font-size: 14px;
        }

        .form-group label i {
            color: #6b21a8;
            margin-right: 8px;
        }

        .password-input {
            position: relative;
        }

        .password-input input {
            width: 100%;
            padding: 14px 16px;
            padding-right: 45px;
            border: 2px solid #e5e7eb;
            border-radius: 12px;
            font-size: 15px;
            transition: all 0.3s;
            background: white;
        }

        .password-input input:focus {
            outline: none;
            border-color: #6b21a8;
            box-shadow: 0 0 0 4px rgba(107,33,168,0.1);
        }

        .toggle-password {
            position: absolute;
            right: 16px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: #9ca3af;
            cursor: pointer;
            font-size: 18px;
            padding: 5px;
        }

        .toggle-password:hover {
            color: #6b21a8;
        }

        .password-strength {
            margin-top: 10px;
            height: 5px;
            border-radius: 5px;
            background: #e5e7eb;
            overflow: hidden;
        }

        .strength-bar {
            height: 100%;
            width: 0;
            transition: all 0.3s ease;
        }

        .strength-text {
            font-size: 12px;
            margin-top: 5px;
            color: #6b7280;
        }

        .requirements {
            background: #f9fafb;
            padding: 15px;
            border-radius: 12px;
            margin-bottom: 20px;
            font-size: 13px;
            border-left: 4px solid #6b21a8;
        }

        .requirements i {
            color: #6b21a8;
            margin-right: 8px;
        }

        .requirements ul {
            margin-top: 10px;
            margin-left: 20px;
            list-style: none;
        }

        .requirements li {
            margin-bottom: 6px;
            color: #6b7280;
            font-size: 12px;
        }

        .requirements li.valid {
            color: #059669;
        }

        .requirements li.valid i {
            color: #059669;
        }

        .requirements li.invalid {
            color: #dc2626;
        }

        .requirements li.invalid i {
            color: #dc2626;
        }

        .btn {
            width: 100%;
            padding: 14px;
            border: none;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .btn-primary {
            background: linear-gradient(135deg, #6b21a8 0%, #4c1d95 100%);
            color: white;
            box-shadow: 0 4px 15px rgba(107,33,168,0.3);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(107,33,168,0.4);
        }

        .btn-secondary {
            background: #f3f4f6;
            color: #374151;
            margin-top: 12px;
        }

        .btn-secondary:hover {
            background: #e5e7eb;
        }

        .alert {
            padding: 15px;
            border-radius: 10px;
            margin-top: 20px;
            display: none;
            align-items: center;
            gap: 10px;
            font-size: 14px;
            animation: slideDown 0.3s ease;
        }

        .alert-success {
            background: #d1fae5;
            color: #065f46;
            border-left: 4px solid #059669;
        }

        .alert-error {
            background: #fee2e2;
            color: #991b1b;
            border-left: 4px solid #dc2626;
        }

        .alert i {
            font-size: 18px;
        }

        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Error container for invalid token */
        .error-container {
            text-align: center;
            padding: 20px;
        }

        .error-icon {
            font-size: 64px;
            color: #dc2626;
            margin-bottom: 20px;
        }

        .error-message {
            color: #dc2626;
            font-size: 16px;
            margin-bottom: 20px;
        }

        .error-link {
            display: inline-block;
            padding: 12px 30px;
            background: linear-gradient(135deg, #6b21a8 0%, #4c1d95 100%);
            color: white;
            text-decoration: none;
            border-radius: 12px;
            font-weight: 600;
            transition: all 0.3s;
        }

        .error-link:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(107,33,168,0.3);
        }

        /* Match message */
        .match-message {
            font-size: 12px;
            margin-top: 8px;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .match-message i {
            font-size: 12px;
        }

        /* Developer footer */
        .developer-footer {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            background: rgba(46, 26, 71, 0.95);
            color: white;
            text-align: center;
            padding: 12px 20px;
            font-size: 13px;
            z-index: 1000;
            border-top: 2px solid #8b5cf6;
            backdrop-filter: blur(5px);
        }

        .footer-content {
            max-width: 1200px;
            margin: 0 auto;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 15px;
            flex-wrap: wrap;
        }

        .footer-content i {
            color: #c084fc;
        }

        @media (max-width: 640px) {
            .reset-box {
                padding: 30px 20px;
            }
            .reset-header h2 {
                font-size: 24px;
            }
            .developer-footer {
                padding: 8px 12px;
                font-size: 11px;
            }
        }
    </style>
</head>
<body>
    <div class="bubbles">
        <div class="bubble bubble-1"></div>
        <div class="bubble bubble-2"></div>
        <div class="bubble bubble-3"></div>
        <div class="bubble bubble-4"></div>
        <div class="bubble bubble-5"></div>
        <div class="bubble bubble-6"></div>
    </div>

    <div class="container">
        <?php if (isset($name)): ?>
            <div class="reset-box">
                <div class="reset-header">
                    <i class="fas fa-key"></i>
                    <h2>Reset Password</h2>
                    <p>Create a new secure password</p>
                    <div class="user-name"><i class="fas fa-user"></i> <?php echo htmlspecialchars($name); ?></div>
                </div>

                <div id="message"></div>

                <form id="resetForm">
                    <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
                    <input type="hidden" name="role" value="<?php echo htmlspecialchars($role); ?>">

                    <div class="form-group">
                        <label><i class="fas fa-lock"></i> New Password</label>
                        <div class="password-input">
                            <input type="password" name="password" id="password" required placeholder="Enter new password" minlength="8">
                            <button type="button" class="toggle-password" onclick="togglePassword(this)">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                        <div class="password-strength">
                            <div class="strength-bar" id="strengthBar"></div>
                        </div>
                        <div class="strength-text" id="strengthText"></div>
                    </div>

                    <div class="form-group">
                        <label><i class="fas fa-lock"></i> Confirm Password</label>
                        <div class="password-input">
                            <input type="password" name="confirm_password" id="confirm_password" required placeholder="Confirm new password">
                            <button type="button" class="toggle-password" onclick="togglePassword(this)">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                        <div id="matchMessage" class="match-message"></div>
                    </div>

                    <div class="requirements">
                        <i class="fas fa-info-circle"></i> Password must contain:
                        <ul>
                            <li id="req-length" class="invalid"><i class="fas fa-times-circle"></i> At least 8 characters</li>
                            <li id="req-uppercase" class="invalid"><i class="fas fa-times-circle"></i> At least 1 uppercase letter</li>
                            <li id="req-lowercase" class="invalid"><i class="fas fa-times-circle"></i> At least 1 lowercase letter</li>
                            <li id="req-number" class="invalid"><i class="fas fa-times-circle"></i> At least 1 number</li>
                            <li id="req-special" class="invalid"><i class="fas fa-times-circle"></i> At least 1 special character (!@#$%^&*)</li>
                        </ul>
                    </div>

                    <button type="submit" class="btn btn-primary" id="submitBtn">
                        <i class="fas fa-save"></i> Reset Password
                    </button>

                    <a href="login.html" class="btn btn-secondary" style="text-decoration: none; display: block;">
                        <i class="fas fa-arrow-left"></i> Back to Login
                    </a>
                </form>
            </div>
        <?php else: ?>
            <div class="reset-box">
                <div class="error-container">
                    <div class="error-icon">
                        <i class="fas fa-exclamation-circle"></i>
                    </div>
                    <h2 style="color:#1f2937; margin-bottom:15px;">Invalid or Expired Link</h2>
                    <div class="error-message">
                        <?php
                        if (isset($_GET['error'])) {
                            switch($_GET['error']) {
                                case 'token_expired':
                                    echo 'This password reset link has expired. Please request a new one.';
                                    break;
                                case 'token_used':
                                    echo 'This password reset link has already been used. Please request a new one.';
                                    break;
                                case 'invalid_token':
                                    echo 'This password reset link is invalid. Please request a new one.';
                                    break;
                                default:
                                    echo 'Something went wrong. Please request a new password reset link.';
                            }
                        } else {
                            echo 'This password reset link is invalid or has expired. Please request a new one.';
                        }
                        ?>
                    </div>
                    <a href="login.html" class="error-link">
                        <i class="fas fa-arrow-left"></i> Back to Login
                    </a>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Fixed Footer -->
    <div class="developer-footer">
        <div class="footer-content">
            <span>&copy; Developed by Janani Shree Priya M | Guide - Ponmalar S, Assistant Professor | 2023 B.Sc Computer Science</span>
        </div>
    </div>

    <?php if (isset($name)): ?>
    <script>
        function togglePassword(btn) {
            const input = btn.parentElement.querySelector('input');
            const icon = btn.querySelector('i');
            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.replace('fa-eye', 'fa-eye-slash');
            } else {
                input.type = 'password';
                icon.classList.replace('fa-eye-slash', 'fa-eye');
            }
        }

        const password = document.getElementById('password');
        const confirm = document.getElementById('confirm_password');
        const strengthBar = document.getElementById('strengthBar');
        const strengthText = document.getElementById('strengthText');
        const submitBtn = document.getElementById('submitBtn');
        const matchMessage = document.getElementById('matchMessage');

        const requirements = {
            length: document.getElementById('req-length'),
            uppercase: document.getElementById('req-uppercase'),
            lowercase: document.getElementById('req-lowercase'),
            number: document.getElementById('req-number'),
            special: document.getElementById('req-special')
        };

        function checkPasswordStrength() {
            const pass = password.value;
            
            const hasLength = pass.length >= 8;
            const hasUppercase = /[A-Z]/.test(pass);
            const hasLowercase = /[a-z]/.test(pass);
            const hasNumber = /[0-9]/.test(pass);
            const hasSpecial = /[!@#$%^&*()_+\-=\[\]{};':"\\|,.<>\/?]/.test(pass);
            
            // Update requirement icons
            updateRequirement(requirements.length, hasLength);
            updateRequirement(requirements.uppercase, hasUppercase);
            updateRequirement(requirements.lowercase, hasLowercase);
            updateRequirement(requirements.number, hasNumber);
            updateRequirement(requirements.special, hasSpecial);
            
            const requirementsMet = [hasLength, hasUppercase, hasLowercase, hasNumber, hasSpecial].filter(Boolean).length;
            const strengthPercent = (requirementsMet / 5) * 100;
            
            strengthBar.style.width = strengthPercent + '%';
            
            if (strengthPercent <= 40) {
                strengthBar.style.background = '#dc2626';
                strengthText.textContent = 'Weak password';
                strengthText.style.color = '#dc2626';
            } else if (strengthPercent <= 70) {
                strengthBar.style.background = '#f59e0b';
                strengthText.textContent = 'Medium password';
                strengthText.style.color = '#f59e0b';
            } else {
                strengthBar.style.background = '#059669';
                strengthText.textContent = 'Strong password';
                strengthText.style.color = '#059669';
            }
            
            checkPasswordMatch();
        }

        function updateRequirement(element, isValid) {
            if (isValid) {
                element.classList.remove('invalid');
                element.classList.add('valid');
                element.innerHTML = '<i class="fas fa-check-circle"></i> ' + element.innerText.replace(/[✓✗]\s*/, '');
            } else {
                element.classList.remove('valid');
                element.classList.add('invalid');
                element.innerHTML = '<i class="fas fa-times-circle"></i> ' + element.innerText.replace(/[✓✗]\s*/, '');
            }
        }

        function checkPasswordMatch() {
            if (confirm.value === '') {
                matchMessage.innerHTML = '';
                matchMessage.style.color = '';
                submitBtn.disabled = true;
                return;
            }
            
            if (password.value === confirm.value) {
                matchMessage.innerHTML = '<i class="fas fa-check-circle" style="color:#059669;"></i> Passwords match';
                matchMessage.style.color = '#059669';
                
                const pass = password.value;
                const hasLength = pass.length >= 8;
                const hasUppercase = /[A-Z]/.test(pass);
                const hasLowercase = /[a-z]/.test(pass);
                const hasNumber = /[0-9]/.test(pass);
                const hasSpecial = /[!@#$%^&*()_+\-=\[\]{};':"\\|,.<>\/?]/.test(pass);
                
                submitBtn.disabled = !(hasLength && hasUppercase && hasLowercase && hasNumber && hasSpecial);
            } else {
                matchMessage.innerHTML = '<i class="fas fa-exclamation-circle" style="color:#dc2626;"></i> Passwords do not match';
                matchMessage.style.color = '#dc2626';
                submitBtn.disabled = true;
            }
        }

        password.addEventListener('input', checkPasswordStrength);
        confirm.addEventListener('input', checkPasswordMatch);

        document.getElementById('resetForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const messageDiv = document.getElementById('message');
            const submitBtn = document.getElementById('submitBtn');
            
            const formData = new FormData(this);
            formData.append('action', 'reset_password');
            
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Resetting...';
            submitBtn.disabled = true;
            
            try {
                const response = await fetch('api/reset-password.php', {
                    method: 'POST',
                    body: formData
                });
                
                const text = await response.text();
                console.log('Raw response:', text);
                
                let data;
                try {
                    data = JSON.parse(text);
                } catch (e) {
                    console.error('Invalid JSON:', text);
                    throw new Error('Server returned invalid JSON');
                }
                
                if (data.success) {
                    messageDiv.innerHTML = `
                        <div class="alert alert-success" style="display:flex;">
                            <i class="fas fa-check-circle"></i>
                            ${data.message}
                        </div>
                    `;
                    
                    setTimeout(() => {
                        window.location.href = 'login.html';
                    }, 3000);
                } else {
                    messageDiv.innerHTML = `
                        <div class="alert alert-error" style="display:flex;">
                            <i class="fas fa-exclamation-circle"></i>
                            ${data.error}
                        </div>
                    `;
                    submitBtn.innerHTML = '<i class="fas fa-save"></i> Reset Password';
                    submitBtn.disabled = false;
                }
            } catch (err) {
                console.error('Error:', err);
                messageDiv.innerHTML = `
                    <div class="alert alert-error" style="display:flex;">
                        <i class="fas fa-exclamation-circle"></i>
                        Network error: ${err.message}
                    </div>
                `;
                submitBtn.innerHTML = '<i class="fas fa-save"></i> Reset Password';
                submitBtn.disabled = false;
            }
        });
    </script>
    <?php endif; ?>
</body>
</html>