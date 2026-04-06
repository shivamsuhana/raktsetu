<?php
// ============================================================
//  RaktSetu — helpers.php
//  Shared utility functions · Include after db.php
// ============================================================

/**
 * Human-readable time ago.
 */
function timeAgo(string $datetime): string {
    $diff = time() - strtotime($datetime);
    if ($diff < 60)    return 'just now';
    if ($diff < 3600)  return floor($diff / 60)   . 'm ago';
    if ($diff < 86400) return floor($diff / 3600)  . 'h ago';
    if ($diff < 604800)return floor($diff / 86400) . 'd ago';
    return date('d M Y', strtotime($datetime));
}

/**
 * Flash message helper — queue a message in session.
 * Type: 'success' | 'error' | 'info'
 */
function flash(string $type, string $msg): void {
    $_SESSION['flash'][] = ['type' => $type, 'msg' => $msg];
}

/**
 * Haversine formula — distance between two lat/lng points in km.
 */
function haversineKm(float $lat1, float $lng1, float $lat2, float $lng2): float {
    $R    = 6371;
    $dLat = deg2rad($lat2 - $lat1);
    $dLng = deg2rad($lng2 - $lng1);
    $a    = sin($dLat/2) ** 2 +
            cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng/2) ** 2;
    return $R * 2 * asin(sqrt($a));
}

/**
 * Sanitize string for safe HTML output.
 */
function e(string $str): string {
    return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
}

/**
 * Redirect with a flash message.
 */
function redirectWith(string $url, string $type, string $msg): never {
    flash($type, $msg);
    header('Location: ' . $url);
    exit;
}

/**
 * Donor badge based on verified donation count.
 * Returns [label, textColor, bgColor]
 */
function donorBadge(int $count): array {
    if ($count >= 10) return ['Champion', '#7e22ce', '#f3e8ff'];
    if ($count >= 5)  return ['Hero',     '#1d4ed8', '#dbeafe'];
    if ($count >= 2)  return ['Regular',  '#15803d', '#dcfce7'];
    return                   ['New',      '#4b5563', '#f3f4f6'];
}

/**
 * Days until donor is eligible again.
 * Returns 0 if already eligible.
 */
function daysUntilEligible(?string $lastDonated): int {
    if (!$lastDonated) return 0;
    $daysSince = (int)(new DateTime())->diff(new DateTime($lastDonated))->days;
    return max(0, DONATION_COOLDOWN_DAYS - $daysSince);
}

/**
 * Paginate a query — returns [rows, totalPages, totalRows].
 */
function paginate(PDO $db, string $sql, array $params, int $page, int $perPage = 15): array {
    // Count
    $countSql = preg_replace('/SELECT .+? FROM /is', 'SELECT COUNT(*) FROM ', $sql);
    $countSql = preg_replace('/ORDER BY .+$/is', '', $countSql);
    $countStmt = $db->prepare($countSql);
    $countStmt->execute($params);
    $total = (int)$countStmt->fetchColumn();

    $totalPages = max(1, (int)ceil($total / $perPage));
    $page       = max(1, min($page, $totalPages));
    $offset     = ($page - 1) * $perPage;

    $stmt = $db->prepare($sql . " LIMIT $perPage OFFSET $offset");
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    return [$rows, $totalPages, $total];
}

/**
 * Output urgency badge HTML.
 */
function urgencyBadge(string $urgency): string {
    $labels = ['critical'=>'Critical','high'=>'High','normal'=>'Normal'];
    $label  = $labels[$urgency] ?? ucfirst($urgency);
    return '<span class="badge badge-' . e($urgency) . '">' . $label . '</span>';
}

/**
 * Output blood type pill HTML.
 */
function btPill(string $bt): string {
    return '<span class="bt-pill">' . e($bt) . '</span>';
}

/**
 * Generate CSRF token — store in session.
 */
function csrfToken(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Verify CSRF token from POST.
 */
function verifyCsrf(): bool {
    $token = $_POST['csrf_token'] ?? '';
    return hash_equals($_SESSION['csrf_token'] ?? '', $token);
}

/**
 * Return JSON response and exit.
 */
function jsonResponse(array $data, int $code = 200): never {
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}
