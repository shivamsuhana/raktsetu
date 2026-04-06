<?php
// ============================================================
//  api/fetch-requests.php
//  AJAX endpoint — returns live requests as JSON
//  Called by JS every 30 seconds
// ============================================================

header('Content-Type: application/json');
header('Cache-Control: no-store');

require_once __DIR__ . '/../config/db.php';

try {
    $rows = getDB()->query("
        SELECT br.id, br.blood_type, br.units_needed, br.units_fulfilled,
               br.urgency, br.status, br.notes, br.created_at,
               h.name AS hospital_name, h.city,
               (SELECT COUNT(*) FROM users u2
                WHERE u2.role='donor' AND u2.blood_type = br.blood_type
                AND u2.is_verified = 1 AND u2.is_eligible = 1
               ) AS donors_nearby
        FROM blood_requests br
        LEFT JOIN hospitals h ON h.id = br.hospital_id
        WHERE br.status IN ('open','in_progress')
        ORDER BY FIELD(br.urgency,'critical','high','normal'), br.created_at DESC
        LIMIT 50
    ")->fetchAll();

    $out = [];
    foreach ($rows as $r) {
        $out[] = [
            'id'              => (int)$r['id'],
            'blood_type'      => $r['blood_type'],
            'units_needed'    => (int)$r['units_needed'],
            'units_fulfilled' => (int)($r['units_fulfilled'] ?? 0),
            'urgency'         => $r['urgency'],
            'hospital_name'   => $r['hospital_name'] ?? '',
            'city'            => $r['city'] ?? '',
            'notes'           => mb_substr($r['notes'] ?? '', 0, 200),
            'donors_nearby'   => (int)$r['donors_nearby'],
            'time_ago'        => timeAgo($r['created_at']),
        ];
    }

    echo json_encode(['requests' => $out, 'timestamp' => time()]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Server error']);
}
