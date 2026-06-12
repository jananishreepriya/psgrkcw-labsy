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

$allLabs = fetchAll("SELECT id, lab_name FROM labs WHERE status = 'active'");
$labByName = [];
$labById = [];
foreach ($allLabs as $lab) {
    $labByName[strtolower(trim($lab['lab_name']))] = $lab['id'];
    $labById[$lab['id']] = true;
}

$periodTimes = [
    1 => ['start' => '08:10:00', 'end' => '09:00:00'],
    2 => ['start' => '09:00:00', 'end' => '09:50:00'],
    3 => ['start' => '10:10:00', 'end' => '11:00:00'],
    4 => ['start' => '11:00:00', 'end' => '11:50:00'],
    5 => ['start' => '11:50:00', 'end' => '12:40:00'],
    6 => ['start' => '12:50:00', 'end' => '13:40:00'],
    7 => ['start' => '13:40:00', 'end' => '14:30:00'],
    8 => ['start' => '14:30:00', 'end' => '15:20:00'],
    9 => ['start' => '15:40:00', 'end' => '16:30:00'],
    10 => ['start' => '16:30:00', 'end' => '17:20:00']
];

try {
    $inputFileType = IOFactory::identify($file);
    $reader = IOFactory::createReader($inputFileType);
    $spreadsheet = $reader->load($file);
    $worksheet = $spreadsheet->getActiveSheet();
    $rows = $worksheet->toArray();
} catch (Exception $e) {
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

// Normalise headers: trim and lowercase
$header = array_shift($rows);
$header = array_map(function($h) {
    return strtolower(trim($h));
}, $header);

// Flexible column matching
$labIdx = false;
foreach (['lab', 'lab_name'] as $key) {
    if (($idx = array_search($key, $header)) !== false) {
        $labIdx = $idx;
        break;
    }
}
$dayIdx = array_search('day_order', $header);
$periodIdx = array_search('period', $header);
$classIdx = array_search('class_name', $header);
$subjectIdx = array_search('subject', $header);
$facultyIdx = array_search('faculty_name', $header) ?: array_search('faculty', $header);
$headEmailIdx = array_search('head_email', $header);
$semesterIdx = array_search('semester', $header);

if ($labIdx === false || $dayIdx === false || $periodIdx === false || $classIdx === false) {
    echo json_encode(['error' => 'Missing required columns. Please ensure your file has: lab (or lab_name), day_order, period, class_name']);
    exit;
}

$db = getDB();
$rowIndex = 1;

foreach ($rows as $row) {
    $rowIndex++;
    if (empty(array_filter($row))) continue;

    $labInput = trim($row[$labIdx] ?? '');
    $dayOrder = intval($row[$dayIdx] ?? 0);
    $period = intval($row[$periodIdx] ?? 0);
    $className = trim($row[$classIdx] ?? '');
    $subject = trim($row[$subjectIdx] ?? '');
    $faculty = trim($row[$facultyIdx] ?? '');
    $headEmail = trim($row[$headEmailIdx] ?? '');
    $semester = trim($row[$semesterIdx] ?? '');

    if (empty($labInput) || $dayOrder < 1 || $dayOrder > 6 || $period < 1 || $period > 10 || empty($className)) {
        $messages[] = "Row $rowIndex: Skipped - missing required fields or invalid day/period";
        $errors++;
        continue;
    }

    // Determine lab ID
    $labId = null;
    if (is_numeric($labInput) && isset($labById[$labInput])) {
        $labId = (int)$labInput;
    } else {
        $key = strtolower($labInput);
        if (isset($labByName[$key])) {
            $labId = $labByName[$key];
        }
    }

    if (!$labId) {
        $messages[] = "Row $rowIndex: Lab '$labInput' not found - skipped";
        $errors++;
        continue;
    }

    $startTime = $periodTimes[$period]['start'];
    $endTime = $periodTimes[$period]['end'];

    $existing = fetchOne("
        SELECT id FROM timetable 
        WHERE lab_id = ? AND day_order = ? AND start_time = ? AND is_active = TRUE
    ", [$labId, $dayOrder, $startTime]);

    try {
        if ($existing) {
            $db->prepare("
                UPDATE timetable SET 
                    end_time = ?, class_name = ?, subject = ?, faculty_name = ?, head_email = ?, semester = ?
                WHERE id = ?
            ")->execute([$endTime, $className, $subject, $faculty, $headEmail, $semester, $existing['id']]);
            $updated++;
            $messages[] = "Row $rowIndex: Updated timetable entry for lab ID $labId, Day $dayOrder, Period $period";
        } else {
            $db->prepare("
                INSERT INTO timetable 
                (lab_id, day_order, start_time, end_time, class_name, subject, faculty_name, head_email, semester, created_by, is_active)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, TRUE)
            ")->execute([$labId, $dayOrder, $startTime, $endTime, $className, $subject, $faculty, $headEmail, $semester, $_SESSION['user_id']]);
            $inserted++;
            $messages[] = "Row $rowIndex: Inserted timetable entry for lab ID $labId, Day $dayOrder, Period $period";
        }
    } catch (Exception $e) {
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