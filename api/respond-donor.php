<?php
// ============================================================
//  api/respond-donor.php
//  AJAX — donor marks themselves available for a request
// ============================================================

header('Content-Type: application/json');
session_start();
require_once __DIR__ . '/../config/db.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success'=>false,'message'=>'Please sign in first.']); exit;
}
if ($_SESSION['user_role'] !== 'donor') {
    echo json_encode(['success'=>false,'message'=>'Only donors can respond.']); exit;
}
if (!$_SESSION['eligible']) {
    echo json_encode(['success'=>false,'message'=>'You are not eligible to donate yet.']); exit;
}

$data      = json_decode(file_get_contents('php://input'), true);
$requestId = (int)($data['request_id'] ?? 0);

if (!$requestId) {
    echo json_encode(['success'=>false,'message'=>'Invalid request.']); exit;
}

$db = getDB();

// Verify request exists and is open
$req = $db->prepare("SELECT id,blood_type,status FROM blood_requests WHERE id=? AND status IN ('open','in_progress')");
$req->execute([$requestId]);
$request = $req->fetch();

if (!$request) {
    echo json_encode(['success'=>false,'message'=>'This request is no longer active.']); exit;
}

// Check donor blood type matches
if ($request['blood_type'] !== $_SESSION['blood_type']) {
    echo json_encode(['success'=>false,'message'=>"This request needs {$request['blood_type']}. Your type is {$_SESSION['blood_type']}."]); exit;
}

try {
    // INSERT OR UPDATE (upsert)
    $db->prepare("
        INSERT INTO donor_responses (donor_id, request_id, status)
        VALUES (?, ?, 'available')
        ON DUPLICATE KEY UPDATE status='available', responded_at=NOW()
    ")->execute([$_SESSION['user_id'], $requestId]);

    // Update request status to in_progress
    $db->prepare("UPDATE blood_requests SET status='in_progress' WHERE id=? AND status='open'")->execute([$requestId]);

    // Alert the requester
    $db->prepare("
        INSERT INTO alerts (user_id, request_id, type, message)
        SELECT requester_id, ?, 'donor_confirmed',
               CONCAT(?, ' has responded to your ', blood_type, ' blood request.')
        FROM blood_requests WHERE id=?
    ")->execute([$requestId, $_SESSION['user_name'], $requestId]);

    echo json_encode(['success'=>true,'message'=>'Response sent! The hospital will contact you shortly.']);

} catch (PDOException $e) {
    echo json_encode(['success'=>false,'message'=>'Error saving response. Please try again.']);
}
