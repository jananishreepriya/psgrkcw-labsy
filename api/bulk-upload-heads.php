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

// Pre-fetch labs
$allLabs = fetchAll("SELECT id, lab_name FROM labs WHERE status = 'active'");
$labByName = [];
$labById = [];
foreach ($allLabs as $lab) {
    $labByName[strtolower(trim($lab['lab_name']))] = $lab['id'];
    $labById[$lab['id']] = true;
}

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
$labsIdx = array_search('labs', $header);

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
    $labsField = trim($row[$labsIdx] ?? '');

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

    $labIds = [];
    if (!empty($labsField)) {
        $labItems = array_map('trim', explode(',', $labsField));
        foreach ($labItems as $item) {
            if (is_numeric($item) && isset($labById[$item])) {
                $labIds[] = (int)$item;
            } else {
                $key = strtolower($item);
                if (isset($labByName[$key])) {
                    $labIds[] = $labByName[$key];
                } else {
                    $messages[] = "Row $rowIndex: Lab '$item' not found - skipped";
                }
            }
        }
    }

    $db->beginTransaction();
    try {
        $existing = fetchOne("SELECT id FROM users WHERE email = ?", [$email]);
        if ($existing) {
            $roleCheck = fetchOne("SELECT role FROM users WHERE id = ?", [$existing['id']]);
            if ($roleCheck['role'] === 'head') {
                $db->prepare("UPDATE users SET name = ?, password = ? WHERE id = ?")->execute([$name, $hash, $existing['id']]);
                $headId = $existing['id'];
                $updated++;
                $messages[] = "Row $rowIndex: Updated head '$email'";
            } else {
                $db->rollBack();
                $messages[] = "Row $rowIndex: Email '$email' already used by a " . $roleCheck['role'] . " - skipped";
                $errors++;
                continue;
            }
        } else {
            $db->prepare("INSERT INTO users (name, email, password, role, status) VALUES (?, ?, ?, 'head', 'active')")
                ->execute([$name, $email, $hash]);
            $headId = $db->lastInsertId();
            $inserted++;
            $messages[] = "Row $rowIndex: Inserted head '$email'";
        }

        $db->prepare("DELETE FROM lab_heads WHERE head_id = ?")->execute([$headId]);
        if (!empty($labIds)) {
            $stmt = $db->prepare("INSERT INTO lab_heads (lab_id, head_id) VALUES (?, ?)");
            foreach ($labIds as $labId) {
                $stmt->execute([$labId, $headId]);
                $messages[] = "Row $rowIndex: Assigned lab ID $labId";
            }
        } else {
            $messages[] = "Row $rowIndex: No labs assigned";
        }

        $db->commit();
    } catch (Exception $e) {
        $db->rollBack();
        $messages[] = "Row $rowIndex: Database error - " . $e->getMessage();
        $errors++;
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