<?php
// ============================================================
//  api/update-status.php
//  AJAX — update request status (hospital/admin use)
// ============================================================

header('Content-Type: application/json');
session_start();
require_once __DIR__ . '/../config/db.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success'=>false,'message'=>'Not authenticated']); exit;
}
if (!in_array($_SESSION['user_role'], ['admin','hospital_staff'])) {
    echo json_encode(['success'=>false,'message'=>'Unauthorized']); exit;
}

$data      = json_decode(file_get_contents('php://input'), true);
$requestId = (int)($data['request_id'] ?? 0);
$newStatus = $data['status'] ?? '';

$allowed = ['open','in_progress','fulfilled','closed'];
if (!$requestId || !in_array($newStatus, $allowed)) {
    echo json_encode(['success'=>false,'message'=>'Invalid parameters']); exit;
}

try {
    getDB()->prepare("UPDATE blood_requests SET status=? WHERE id=?")->execute([$newStatus, $requestId]);
    echo json_encode(['success'=>true,'status'=>$newStatus]);
} catch (PDOException $e) {
    echo json_encode(['success'=>false,'message'=>'Database error']);
}
