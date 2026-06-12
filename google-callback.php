<?php
require_once 'config.php';

if (!isset($_GET['code'])) {
    header('Location: login.html?error=google_failed');
    exit;
}

$code = $_GET['code'];

// Exchange code for token
$url = 'https://oauth2.googleapis.com/token';
$data = [
    'code' => $code,
    'client_id' => GOOGLE_CLIENT_ID,
    'client_secret' => GOOGLE_CLIENT_SECRET,
    'redirect_uri' => GOOGLE_REDIRECT_URI,
    'grant_type' => 'authorization_code'
];

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
$response = curl_exec($ch);
curl_close($ch);

$tokenData = json_decode($response, true);

if (!isset($tokenData['access_token'])) {
    header('Location: login.html?error=token_failed');
    exit;
}

// Get user info
$userUrl = 'https://www.googleapis.com/oauth2/v2/userinfo?access_token=' . $tokenData['access_token'];
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $userUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
$userResponse = curl_exec($ch);
curl_close($ch);

$userInfo = json_decode($userResponse, true);

if (!isset($userInfo['email'])) {
    header('Location: login.html?error=user_info_failed');
    exit;
}

$email = $userInfo['email'];
$name = $userInfo['name'] ?? ($userInfo['given_name'] . ' ' . $userInfo['family_name']);
$googleId = $userInfo['id'];

// ONLY ALLOW ADMIN EMAIL FOR SSO
if ($email !== ADMIN_EMAIL) {
    header('Location: login.html?error=admin_only_sso');
    exit;
}

// Check if admin exists
$user = fetchOne("SELECT * FROM users WHERE email = ?", [$email]);

if ($user) {
    if ($user['status'] === 'inactive') {
        header('Location: login.html?error=account_inactive');
        exit;
    }
    
    // Update google_id if not set
    if (empty($user['google_id'])) {
        query("UPDATE users SET google_id = ? WHERE id = ?", [$googleId, $user['id']]);
    }
    
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['user_name'] = $user['name'];
    $_SESSION['user_email'] = $user['email'];
    $_SESSION['user_role'] = 'admin';
    
    header('Location: admin.html');
    exit;
} else {
    // Create admin account
    $newId = insertId("INSERT INTO users (name, email, google_id, role, status) VALUES (?, ?, ?, 'admin', 'active')", 
        [$name, $email, $googleId]);
    
    $_SESSION['user_id'] = $newId;
    $_SESSION['user_name'] = $name;
    $_SESSION['user_email'] = $email;
    $_SESSION['user_role'] = 'admin';
    
    header('Location: admin.html');
    exit;
}
?>