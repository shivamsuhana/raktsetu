<?php
// ============================================================
//  api/check-eligibility.php
//  AJAX — returns eligibility status for logged-in donor
// ============================================================

header('Content-Type: application/json');
session_start();
require_once __DIR__ . '/../config/db.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['eligible' => false, 'reason' => 'not_logged_in']); exit;
}

$uid  = (int)$_SESSION['user_id'];
$row  = getDB()->prepare("SELECT last_donated, is_eligible FROM users WHERE id=?");
$row->execute([$uid]);
$user = $row->fetch();

if (!$user) {
    echo json_encode(['eligible' => false, 'reason' => 'user_not_found']); exit;
}

$daysLeft     = daysUntilEligible($user['last_donated']);
$eligible     = $daysLeft === 0;
$nextEligible = null;

if (!$eligible && $user['last_donated']) {
    $nextEligible = (new DateTime($user['last_donated']))
        ->modify('+' . DONATION_COOLDOWN_DAYS . ' days')
        ->format('Y-m-d');
}

// Sync DB if status drifted
if ($eligible !== (bool)$user['is_eligible']) {
    getDB()->prepare("UPDATE users SET is_eligible=? WHERE id=?")->execute([(int)$eligible, $uid]);
    $_SESSION['eligible'] = (int)$eligible;
}

echo json_encode([
    'eligible'      => $eligible,
    'days_left'     => $daysLeft,
    'next_eligible' => $nextEligible,
    'last_donated'  => $user['last_donated'],
]);
