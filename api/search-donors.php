<?php
// ============================================================
//  api/search-donors.php
//  AJAX endpoint — search donors by blood type / city / name
//  Returns JSON for JS-driven live search
// ============================================================

header('Content-Type: application/json');
header('Cache-Control: no-store');

require_once __DIR__ . '/../config/db.php';

$bt   = in_array($_GET['bt'] ?? '', BLOOD_TYPES) ? $_GET['bt'] : '';
$city = trim($_GET['city'] ?? '');
$q    = trim($_GET['q']    ?? '');

$where  = ["u.role='donor'", "u.is_verified=1"];
$params = [];

if ($bt)   { $where[] = 'u.blood_type = ?'; $params[] = $bt;           }
if ($city) { $where[] = 'u.city LIKE ?';    $params[] = "%$city%";     }
if ($q)    { $where[] = '(u.name LIKE ? OR u.city LIKE ?)';
             $params[] = "%$q%"; $params[] = "%$q%"; }

$sql = "
    SELECT u.id, u.name, u.blood_type, u.city, u.state,
           u.is_eligible, u.last_donated,
           (SELECT COUNT(*) FROM donations d WHERE d.donor_id=u.id AND d.verified_by_hospital=1) AS donations
    FROM users u
    WHERE " . implode(' AND ', $where) . "
    ORDER BY u.is_eligible DESC, (SELECT COUNT(*) FROM donations d WHERE d.donor_id=u.id AND d.verified_by_hospital=1) DESC
    LIMIT 20
";

try {
    $stmt = getDB()->prepare($sql);
    if (empty($params)) {
        $stmt->execute();
    } else {
        $stmt->execute($params);
    }
    $rows = $stmt->fetchAll();

    $out = array_map(function($r) {
        $count = (int)$r['donations'];
        $badgeLabels = ['New','Regular','Hero','Champion'];
        $badgeIdx = $count >= 10 ? 3 : ($count >= 5 ? 2 : ($count >= 2 ? 1 : 0));
        return [
            'id'          => (int)$r['id'],
            'name'        => $r['name'],
            'blood_type'  => $r['blood_type'],
            'city'        => $r['city'] ?? '',
            'state'       => $r['state'] ?? '',
            'is_eligible' => (bool)$r['is_eligible'],
            'donations'   => $count,
            'badge'       => $badgeLabels[$badgeIdx],
            'initials'    => strtoupper(implode('', array_map(fn($w)=>$w[0]??'', explode(' ', $r['name']??'')))),
        ];
    }, $rows);

    echo json_encode(['donors' => $out, 'count' => count($out)]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Search failed']);
}
