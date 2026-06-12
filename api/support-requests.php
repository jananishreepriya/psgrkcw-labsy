<?php
require_once '../config.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $status = $_GET['status'] ?? 'all';
    $source = $_GET['source'] ?? 'all';
    $search = trim($_GET['search'] ?? '');
    $dateFrom = $_GET['date_from'] ?? '';
    $dateTo = $_GET['date_to'] ?? '';
    
    $sql = "SELECT * FROM support_requests WHERE 1=1";
    $params = [];
    
    if ($status !== 'all') {
        $sql .= " AND status = ?";
        $params[] = $status;
    }
    if ($source !== 'all') {
        $sql .= " AND source = ?";
        $params[] = $source;
    }
    if (!empty($search)) {
        $sql .= " AND (name LIKE ? OR email LIKE ? OR message LIKE ?)";
        $searchTerm = "%$search%";
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $params[] = $searchTerm;
    }
    if (!empty($dateFrom)) {
        $sql .= " AND DATE(created_at) >= ?";
        $params[] = $dateFrom;
    }
    if (!empty($dateTo)) {
        $sql .= " AND DATE(created_at) <= ?";
        $params[] = $dateTo;
    }
    
    $sql .= " ORDER BY status ASC, created_at DESC";
    $requests = fetchAll($sql, $params);
    echo json_encode(['requests' => $requests]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = intval($_POST['id'] ?? 0);
    $action = $_POST['action'] ?? '';
    if ($action === 'resolve' && $id) {
        $req = fetchOne("SELECT * FROM support_requests WHERE id = ?", [$id]);
        if (!$req) {
            echo json_encode(['error' => 'Request not found']);
            exit;
        }
        query("UPDATE support_requests SET status = 'resolved', resolved_at = NOW() WHERE id = ?", [$id]);

        if (!empty($req['email'])) {
            $subject = "Your Support Request #$id has been resolved";
            $content = "
            <div style='background: #d1fae5; padding: 20px; border-radius: 12px;'>
                <p><strong>✅ Your request has been marked as completed by the admin.</strong></p>
                <p><strong>Request:</strong> " . nl2br(htmlspecialchars($req['message'])) . "</p>
                <p>If you need further assistance, please submit a new request.</p>
            </div>
            ";
            $body = getEmailTemplate('Request Resolved', $content);
            sendEmail($req['email'], $subject, $body);
        }
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['error' => 'Invalid action']);
    }
    exit;
}
?>