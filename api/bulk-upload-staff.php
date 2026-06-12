<?php
require_once '../config.php';
require_once '../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['error' => 'File upload error']);
    exit;
}

$file = $_FILES['file']['tmp_name'];
$inserted = 0;
$updated = 0;
$errors = 0;
$messages = [];

try {
    $inputFileType = IOFactory::identify($file);
    $reader = IOFactory::createReader($inputFileType);
    $spreadsheet = $reader->load($file);
    $worksheet = $spreadsheet->getActiveSheet();
    $rows = $worksheet->toArray();
} catch (Exception $e) {
    // Fallback to CSV
    if (($handle = fopen($file, 'r')) !== false) {
        $rows = [];
        while (($data = fgetcsv($handle)) !== false) {
            $rows[] = $data;
        }
        fclose($handle);
    } else {
        echo json_encode(['error' => 'Could not read file']);
        exit;
    }
}

if (empty($rows)) {
    echo json_encode(['error' => 'Empty file']);
    exit;
}

$header = array_shift($rows);
$header = array_map('strtolower', $header);

$nameIdx = array_search('name', $header);
$emailIdx = array_search('email', $header);
$passIdx = array_search('password', $header);

if ($nameIdx === false || $emailIdx === false || $passIdx === false) {
    echo json_encode(['error' => 'Missing required columns: name, email, password']);
    exit;
}

$db = getDB();
$rowIndex = 1;

foreach ($rows as $row) {
    $rowIndex++;
    if (empty(array_filter($row))) continue;

    $name = trim($row[$nameIdx] ?? '');
    $email = trim($row[$emailIdx] ?? '');
    $password = trim($row[$passIdx] ?? '');

    if (empty($name) || empty($email) || empty($password)) {
        $messages[] = "Row $rowIndex: Skipped - missing required fields";
        $errors++;
        continue;
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $messages[] = "Row $rowIndex: Invalid email '$email'";
        $errors++;
        continue;
    }

    $hash = password_hash($password, PASSWORD_DEFAULT);

    $existing = fetchOne("SELECT id FROM users WHERE email = ?", [$email]);
    if ($existing) {
        $roleCheck = fetchOne("SELECT role FROM users WHERE id = ?", [$existing['id']]);
        if ($roleCheck['role'] === 'staff') {
            $db->prepare("UPDATE users SET name = ?, password = ? WHERE id = ?")->execute([$name, $hash, $existing['id']]);
            $updated++;
            $messages[] = "Row $rowIndex: Updated staff '$email'";
        } else {
            $messages[] = "Row $rowIndex: Email '$email' already used by a " . $roleCheck['role'] . " - skipped";
            $errors++;
        }
    } else {
        $db->prepare("INSERT INTO users (name, email, password, role, status) VALUES (?, ?, ?, 'staff', 'active')")
            ->execute([$name, $email, $hash]);
        $inserted++;
        $messages[] = "Row $rowIndex: Inserted staff '$email'";
    }
}

echo json_encode([
    'success' => true,
    'inserted' => $inserted,
    'updated' => $updated,
    'errors' => $errors,
    'messages' => $messages
]);
?>