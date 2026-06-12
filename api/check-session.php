<?php
require_once '../config.php';
header('Content-Type: application/json');

if (isset($_SESSION['user_id'])) {
    // Get fresh user data from database
    $user = fetchOne("SELECT name, email, role FROM users WHERE id = ?", [$_SESSION['user_id']]);
    
    if ($user) {
        // For admin, always return "Admin" as the display name
        // For staff, return their actual name
        $displayName = ($user['role'] === 'admin') ? 'Admin' : $user['name'];
        
        echo json_encode([
            'logged_in' => true,
            'role' => $user['role'],
            'name' => $displayName,
            'email' => $user['email'],
            'real_name' => $user['name'] // Optional: if you need the real name somewhere
        ]);
    } else {
        // User not found in database, clear session
        session_destroy();
        echo json_encode(['logged_in' => false]);
    }
} else {
    echo json_encode(['logged_in' => false]);
}
?>