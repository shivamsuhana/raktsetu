<?php
// ============================================================
//  RaktSetu — donor-search.php
//  Public donor directory · PHP pagination · JS real-time filter
// ============================================================

require_once 'config/db.php';
require_once 'includes/auth-guard.php';

$pageTitle = 'Find Donors · ' . APP_NAME;
$db        = getDB();

// ── Filters ─────────────────────────────────────────────────
$btList     = array_merge(BLOOD_TYPES, ['']);
$filterBT   = in_array($_GET['bt']   ?? '', $btList) ? ($_GET['bt']   ?? '') : '';
$filterCity = trim(htmlspecialchars($_GET['city'] ?? '', ENT_QUOTES));
$page       = max(1, (int)($_GET['page'] ?? 1));
$perPage    = 12;
$offset     = ($page - 1) * $perPage;

// ── Build query ──────────────────────────────────────────────
$where  = ["u.role = 'donor'", "u.is_verified = 1"];
$params = [];

if ($filterBT)   { $where[] = 'u.blood_type = ?'; $params[] = $filterBT; }
if ($filterCity) { $where[] = 'u.city LIKE ?';    $params[] = '%' . $filterCity . '%'; }

$whereSQL = implode(' AND ', $where);

// Total count for pagination
$countStmt = $db->prepare("SELECT COUNT(*) FROM users u WHERE $whereSQL");
if (empty($params)) {
    $countStmt->execute();
} else {
    $countStmt->execute($params);
}
$totalDonors = (int)$countStmt->fetchColumn();
$totalPages  = max(1, (int)ceil($totalDonors / $perPage));

// Donors list — READ from DB
$sql = "
    SELECT u.id, u.name, u.blood_type, u.city, u.state, u.is_eligible,
           u.last_donated, u.created_at,
           (SELECT COUNT(*) FROM donations d WHERE d.donor_id = u.id AND d.verified_by_hospital=1) AS donation_count
    FROM users u
    WHERE $whereSQL
    ORDER BY u.is_eligible DESC, (SELECT COUNT(*) FROM donations d WHERE d.donor_id = u.id AND d.verified_by_hospital=1) DESC, u.created_at DESC
    LIMIT $offset, $perPage
";
$stmt = $db->prepare($sql);
if (empty($params)) {
    $stmt->execute();
} else {
    $stmt->execute($params);
}
$donors = $stmt->fetchAll();

// Donor badge helper
function donorBadge(int $count): array {
    if ($count >= 10) return ['Champion', '#7e22ce', '#f3e8ff'];
    if ($count >= 5)  return ['Hero',     '#1d4ed8', '#dbeafe'];
    if ($count >= 2)  return ['Regular',  '#15803d', '#dcfce7'];
    return              ['New',      '#4b5563', '#f3f4f6'];
}

require_once 'includes/header.php';
?>

<!-- ── FILTER BAR ──────────────────────────────────────────── -->
<div class="filter-bar">
    <!-- Search box -->
    <div class="search-box" style="flex:0 0 220px">
        <span class="search-icon">🔍</span>
        <input type="text" id="donorSearch" placeholder="Search name or city…"
               value="<?= htmlspecialchars($filterCity) ?>">
    </div>

    <!-- Blood type pills -->
    <button class="filter-pill <?= !$filterBT ? 'active' : '' ?>" data-bt="all">All types</button>
    <?php foreach (BLOOD_TYPES as $bt): ?>
        <button class="filter-pill <?= $filterBT===$bt ? 'active' : '' ?>"
                data-bt="<?= $bt ?>"><?= $bt ?></button>
    <?php endforeach; ?>

    <span style="margin-left:auto;font-size:12px;color:var(--gray-500)">
        <span id="resultCount"><?= $totalDonors ?></span> donors found
    </span>
</div>

<div class="container" style="padding-top:28px;padding-bottom:60px">

    <!-- ── Stats pills ─────────────────────────────────────── -->
    <div style="display:flex;gap:10px;margin-bottom:24px;flex-wrap:wrap">
        <?php
        $eligibleCount = $db->query("SELECT COUNT(*) FROM users WHERE role='donor' AND is_verified=1 AND is_eligible=1")->fetchColumn();
        $pills = [
            ['🟢', $eligibleCount . ' eligible now', '#f0fdf4', 'var(--success)'],
            ['🩸', implode(', ', array_slice(BLOOD_TYPES, 0, 3)) . '… all types', '#fef2f2', 'var(--red)'],
            ['📍', 'Multiple cities across India', '#eff6ff', 'var(--info)'],
        ];
        foreach ($pills as [$icon,$label,$bg,$col]):
        ?>
        <div style="display:inline-flex;align-items:center;gap:6px;padding:6px 14px;background:<?= $bg ?>;border-radius:20px;font-size:12px;font-weight:500;color:<?= $col ?>">
            <?= $icon ?> <?= $label ?>
        </div>
        <?php endforeach; ?>
    </div>

    <?php if (empty($donors)): ?>
        <!-- Empty state -->
        <div class="card text-center" style="padding:60px 20px">
            <div style="font-size:48px;margin-bottom:12px">🔍</div>
            <p style="font-size:17px;font-weight:700;color:var(--dark)">No donors found</p>
            <p class="text-muted" style="margin-top:6px">Try a different blood type or city, or <a href="auth.php?tab=register">register as a donor</a> yourself.</p>
        </div>
    <?php else: ?>

        <!-- ── Donor grid ─────────────────────────────────── -->
        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:14px" id="donorGrid">
            <?php foreach ($donors as $d):
                [$badgeLabel,$badgeText,$badgeBg] = donorBadge((int)$d['donation_count']);
                $initials = strtoupper(implode('', array_map(fn($w)=>$w[0]??'', explode(' ', $d['name']??''))));
                $initials = substr($initials, 0, 2);
            ?>
            <div class="donor-card fade-in"
                 data-donor-bt="<?= $d['blood_type'] ?>"
                 data-donor-name="<?= htmlspecialchars($d['name']) ?>"
                 data-donor-city="<?= htmlspecialchars($d['city'] ?? '') ?>"
                 style="padding:16px 18px;flex-direction:column;align-items:flex-start;gap:12px">

                <div style="display:flex;align-items:center;gap:12px;width:100%">
                    <!-- Avatar -->
                    <div class="donor-avatar" style="width:48px;height:48px;font-size:15px"><?= $initials ?></div>
                    <div style="flex:1;min-width:0">
                        <div style="display:flex;align-items:center;gap:6px;flex-wrap:wrap">
                            <span class="donor-name" style="font-size:15px"><?= htmlspecialchars($d['name']) ?></span>
                            <?php if ($d['is_eligible']): ?>
                                <span style="color:var(--success);font-size:12px" title="Eligible to donate">✓</span>
                            <?php endif; ?>
                        </div>
                        <div class="donor-sub">
                            📍 <?= htmlspecialchars($d['city'] ?? '—') ?>
                            <?php if ($d['state'] && $d['state'] !== $d['city']): ?>
                                , <?= htmlspecialchars($d['state']) ?>
                            <?php endif; ?>
                        </div>
                    </div>
                    <span class="bt-pill"><?= htmlspecialchars($d['blood_type'] ?? '?') ?></span>
                </div>

                <!-- Stats row -->
                <div style="display:flex;gap:10px;width:100%;font-size:12px">
                    <div style="flex:1;background:var(--gray-50);border-radius:var(--radius-sm);padding:8px;text-align:center">
                        <div style="font-weight:700;color:var(--dark)"><?= $d['donation_count'] ?></div>
                        <div style="color:var(--gray-500)">donations</div>
                    </div>
                    <div style="flex:1;background:var(--gray-50);border-radius:var(--radius-sm);padding:8px;text-align:center">
                        <div style="font-weight:700;color:<?= $d['is_eligible'] ? 'var(--success)' : 'var(--warning)' ?>">
                            <?= $d['is_eligible'] ? 'Ready' : 'Cooling' ?>
                        </div>
                        <div style="color:var(--gray-500)">status</div>
                    </div>
                    <div style="flex:1;background:<?= $badgeBg ?>;border-radius:var(--radius-sm);padding:8px;text-align:center">
                        <div style="font-weight:700;color:<?= $badgeText ?>"><?= $badgeLabel ?></div>
                        <div style="color:<?= $badgeText ?>;opacity:.7">rank</div>
                    </div>
                </div>

                <!-- Last donated -->
                <?php if ($d['last_donated']): ?>
                <div style="font-size:11px;color:var(--gray-500);width:100%">
                    Last donated: <?= date('d M Y', strtotime($d['last_donated'])) ?>
                </div>
                <?php endif; ?>

                <!-- Contact button (only for logged-in users) -->
                <?php if (isLoggedIn()): ?>
                    <a href="<?= APP_URL ?>/post-request.php?donor=<?= $d['id'] ?>"
                       class="btn btn-sm btn-outline btn-full">
                        Request this donor
                    </a>
                <?php else: ?>
                    <a href="<?= APP_URL ?>/auth.php?redirect=<?= urlencode($_SERVER['REQUEST_URI']) ?>"
                       class="btn btn-sm btn-outline btn-full">
                        Sign in to contact
                    </a>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- ── Pagination ─────────────────────────────────── -->
        <?php if ($totalPages > 1): ?>
        <div style="display:flex;justify-content:center;align-items:center;gap:8px;margin-top:32px">
            <?php if ($page > 1): ?>
                <a href="?bt=<?= urlencode($filterBT) ?>&city=<?= urlencode($filterCity) ?>&page=<?= $page-1 ?>"
                   class="btn btn-sm btn-outline">← Prev</a>
            <?php endif; ?>

            <?php for ($p = max(1,$page-2); $p <= min($totalPages,$page+2); $p++): ?>
                <a href="?bt=<?= urlencode($filterBT) ?>&city=<?= urlencode($filterCity) ?>&page=<?= $p ?>"
                   class="btn btn-sm <?= $p===$page ? 'btn-dark' : 'btn-outline' ?>"><?= $p ?></a>
            <?php endfor; ?>

            <?php if ($page < $totalPages): ?>
                <a href="?bt=<?= urlencode($filterBT) ?>&city=<?= urlencode($filterCity) ?>&page=<?= $page+1 ?>"
                   class="btn btn-sm btn-outline">Next →</a>
            <?php endif; ?>

            <span style="font-size:12px;color:var(--gray-500);margin-left:8px">
                Page <?= $page ?> of <?= $totalPages ?>
            </span>
        </div>
        <?php endif; ?>

    <?php endif; ?>

    <!-- ── Become a donor CTA ─────────────────────────────── -->
    <?php if (!isLoggedIn()): ?>
    <div class="card" style="margin-top:40px;text-align:center;background:var(--red-light);border-color:var(--red-mid)">
        <div style="font-size:36px;margin-bottom:8px">🩸</div>
        <h2 style="font-size:20px;font-weight:700;color:var(--dark)">Are you a donor?</h2>
        <p class="text-muted" style="margin-top:6px;max-width:400px;margin-left:auto;margin-right:auto">
            Join 18,000+ verified donors and show up in this directory when someone needs your blood type.
        </p>
        <a href="<?= APP_URL ?>/auth.php?tab=register" class="btn btn-red" style="margin-top:16px">
            Register as a Donor — Free
        </a>
    </div>
    <?php endif; ?>

</div>

<!-- URL-based filter (full reload) -->
<script>
document.getElementById('donorSearch').addEventListener('keydown', function(e) {
    if (e.key === 'Enter') {
        const q = this.value.trim();
        window.location.href = '?bt=<?= urlencode($filterBT) ?>&city=' + encodeURIComponent(q);
    }
});
document.querySelectorAll('.filter-pill[data-bt]').forEach(btn => {
    btn.addEventListener('click', () => {
        const bt = btn.dataset.bt === 'all' ? '' : btn.dataset.bt;
        window.location.href = '?bt=' + encodeURIComponent(bt) + '&city=<?= urlencode($filterCity) ?>';
    });
});
</script>

<?php require_once 'includes/footer.php'; ?>
