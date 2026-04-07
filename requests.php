<?php
// ============================================================
//  RaktSetu — requests.php  (Live Emergency Board)
//  Demonstrates: PHP data fetch, JS polling, DHTML filtering
// ============================================================

require_once 'config/db.php';
require_once 'includes/auth-guard.php';

$pageTitle = 'Live Requests · ' . APP_NAME;

$db  = getDB();

// Filters from GET
$filterBT  = in_array($_GET['bt']  ?? '', array_merge(BLOOD_TYPES, [''])) ? ($_GET['bt']  ?? '') : '';
$filterUrg = in_array($_GET['urg'] ?? '', ['critical','high','normal','']) ? ($_GET['urg'] ?? '') : '';

// Build query with optional filters
$where = ["br.status IN ('open','in_progress')"];
$params = [];

if ($filterBT)  { $where[] = 'br.blood_type = ?'; $params[] = $filterBT; }
if ($filterUrg) { $where[] = 'br.urgency = ?';    $params[] = $filterUrg; }

$sql = "
    SELECT br.*,
           h.name AS hospital_name, h.city, h.address,
           u.name AS requester_name,
           (SELECT COUNT(*) FROM donor_responses dr
            WHERE dr.request_id = br.id AND dr.status IN ('available','confirmed')) AS response_count
    FROM blood_requests br
    LEFT JOIN hospitals h ON h.id = br.hospital_id
    LEFT JOIN users u     ON u.id = br.requester_id
    WHERE " . implode(' AND ', $where) . "
    ORDER BY FIELD(br.urgency,'critical','high','normal'), br.created_at DESC
    LIMIT 50
";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$requests = $stmt->fetchAll();

// Stats bar
$totalOpen     = $db->query("SELECT COUNT(*) FROM blood_requests WHERE status IN ('open','in_progress')")->fetchColumn();
$criticalCount = $db->query("SELECT COUNT(*) FROM blood_requests WHERE status='open' AND urgency='critical'")->fetchColumn();

require_once 'includes/header.php';
?>

<!-- ── FILTER BAR ──────────────────────────────────────────── -->
<div class="filter-bar">
    <div style="display:flex;align-items:center;gap:8px;margin-right:8px;font-size:12px;color:var(--gray-500)">
        <div style="width:6px;height:6px;background:var(--red);border-radius:50%;animation:livePulse 1.4s ease infinite"></div>
        <span id="resultCount"><?= count($requests) ?></span> requests
        <span id="lastUpdated" style="color:var(--gray-300)">· live</span>
    </div>

    <!-- Blood type pills -->
    <button class="filter-pill <?= !$filterBT ? 'active' : '' ?>" data-bt="all">All types</button>
    <?php foreach (BLOOD_TYPES as $bt): ?>
        <button class="filter-pill <?= $filterBT===$bt ? 'active' : '' ?>" data-bt="<?= $bt ?>"><?= $bt ?></button>
    <?php endforeach; ?>

    <div style="width:1px;height:20px;background:var(--gray-200);margin:0 4px"></div>

    <!-- Urgency pills -->
    <button class="filter-pill <?= !$filterUrg ? 'active' : '' ?>" data-urg="all">All urgency</button>
    <button class="filter-pill <?= $filterUrg==='critical' ? 'active-red' : '' ?>" data-urg="critical">Critical</button>
    <button class="filter-pill <?= $filterUrg==='high' ? 'active' : '' ?>"    data-urg="high">High</button>
    <button class="filter-pill <?= $filterUrg==='normal' ? 'active' : '' ?>"  data-urg="normal">Normal</button>
</div>

<div class="container">
    <div style="display:grid;grid-template-columns:1fr 280px;gap:24px;padding:24px 0;align-items:start">

        <!-- ── REQUESTS BOARD ────────────────────────────── -->
        <div>
            <!-- Critical alert banner -->
            <?php if ($criticalCount > 0): ?>
            <div style="background:var(--red);color:white;border-radius:var(--radius-lg);padding:12px 16px;margin-bottom:16px;display:flex;align-items:center;gap:10px;font-size:13px">
                <span style="font-size:20px">🚨</span>
                <span><strong><?= $criticalCount ?> critical request<?= $criticalCount>1?'s':'' ?></strong> need immediate donors. If your blood type matches, please respond now.</span>
            </div>
            <?php endif; ?>

            <!-- Requests list (polled by JS) -->
            <div id="liveBoard">
                <?php if (empty($requests)): ?>
                    <div class="card text-center" style="padding:48px;color:var(--gray-500)">
                        <div style="font-size:40px;margin-bottom:12px">✓</div>
                        <p style="font-size:16px;font-weight:600;color:var(--dark)">No active requests right now</p>
                        <p style="font-size:13px;margin-top:6px">All emergencies have been fulfilled. Check back later.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($requests as $r):
                        $pct = min(100, (int)(($r['units_fulfilled']??0) / max(1,$r['units_needed']) * 100));
                    ?>
                    <div class="request-card <?= $r['urgency'] ?> fade-in"
                         data-req-id="<?= $r['id'] ?>"
                         data-card-bt="<?= $r['blood_type'] ?>"
                         data-card-urg="<?= $r['urgency'] ?>">

                        <div class="req-header">
                            <div class="req-badges">
                                <span class="badge badge-<?= $r['urgency'] ?>"><?= ucfirst($r['urgency']) ?></span>
                                <span class="bt-pill"><?= htmlspecialchars($r['blood_type']) ?></span>
                                <span class="text-muted" style="font-size:12px"><?= timeAgo($r['created_at']) ?></span>
                                <?php if ($r['response_count'] > 0): ?>
                                    <span class="badge badge-success"><?= $r['response_count'] ?> responding</span>
                                <?php endif; ?>
                            </div>
                            <div class="req-units">
                                <?= (int)($r['units_fulfilled']??0) ?>/<?= $r['units_needed'] ?>
                                <span>units</span>
                            </div>
                        </div>

                        <p class="req-note">
                            <?= htmlspecialchars(mb_substr($r['notes'] ?? '—', 0, 200)) ?>
                        </p>

                        <div class="req-meta">
                            <?php if ($r['hospital_name']): ?>
                                <span>🏥 <?= htmlspecialchars($r['hospital_name']) ?></span>
                            <?php endif; ?>
                            <span>📍 <?= htmlspecialchars($r['city'] ?? '') ?></span>
                            <?php if ($r['needed_by']): ?>
                                <span>⏰ Needed by <?= date('d M, g:ia', strtotime($r['needed_by'])) ?></span>
                            <?php endif; ?>
                        </div>

                        <div class="progress-bar">
                            <div class="progress-fill <?= $r['urgency'] ?>"
                                 data-pct="<?= $pct ?>" style="width:0"></div>
                        </div>

                        <!-- Respond button — only for eligible logged-in donors -->
                        <?php if (isLoggedIn() && $_SESSION['user_role']==='donor' && $_SESSION['eligible']): ?>
                            <button class="btn btn-red btn-full btn-respond"
                                    data-req-id="<?= $r['id'] ?>"
                                    style="margin-top:12px">
                                I can donate — Respond
                            </button>
                        <?php elseif (!isLoggedIn()): ?>
                            <a href="<?= APP_URL ?>/auth.php?redirect=<?= urlencode($_SERVER['REQUEST_URI']) ?>"
                               class="btn btn-outline btn-full" style="margin-top:12px">
                                Sign in to respond
                            </a>
                        <?php elseif (!$_SESSION['eligible']): ?>
                            <div style="margin-top:10px;font-size:12px;color:var(--warning);text-align:center">
                                You are not eligible to donate yet (90-day cooldown).
                            </div>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- ── SIDEBAR ───────────────────────────────────── -->
        <div style="position:sticky;top:calc(var(--nav-h) + 52px)">

            <!-- Post a request CTA -->
            <div class="card" style="background:var(--red);color:white;border:none;margin-bottom:16px">
                <div style="font-size:24px;margin-bottom:8px">🆘</div>
                <p style="font-size:15px;font-weight:700;margin-bottom:6px">Need blood urgently?</p>
                <p style="font-size:12px;opacity:.85;margin-bottom:16px">Post a request and we'll match donors near your hospital immediately.</p>
                <a href="<?= APP_URL ?>/post-request.php" class="btn btn-full"
                   style="background:white;color:var(--red);font-weight:600">
                    Post Emergency Request
                </a>
            </div>

            <!-- Live stats -->
            <div class="card">
                <p class="card-title">Live stats</p>
                <div style="display:flex;flex-direction:column;gap:10px">
                    <?php
                    $sideStats = [
                        ['Open requests',    $totalOpen,     'var(--red)'],
                        ['Critical',         $criticalCount, '#b91c1c'],
                        ['Donors online',    '2,400+',       'var(--success)'],
                        ['Avg response time','18 min',       'var(--info)'],
                    ];
                    foreach ($sideStats as [$label,$val,$col]):
                    ?>
                    <div style="display:flex;justify-content:space-between;align-items:center;font-size:13px">
                        <span style="color:var(--gray-500)"><?= $label ?></span>
                        <strong style="color:<?= $col ?>"><?= $val ?></strong>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Emergency contacts -->
            <div class="card" style="margin-top:16px">
                <p class="card-title">Emergency contacts</p>
                <div style="font-size:13px;display:flex;flex-direction:column;gap:8px">
                    <div><span style="color:var(--gray-500)">National Blood Bank</span><br><strong>1800-11-2</strong></div>
                    <div><span style="color:var(--gray-500)">AIIMS Blood Bank</span><br><strong>011-2659-3761</strong></div>
                    <div><span style="color:var(--gray-500)">Red Cross Society</span><br><strong>1800-180-7001</strong></div>
                </div>
            </div>
        </div>

    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
