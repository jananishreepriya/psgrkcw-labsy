<?php
require_once '../config.php';
header('Content-Type: application/json');

$url = getGoogleLoginUrl();
echo json_encode(['url' => $url]);
?>