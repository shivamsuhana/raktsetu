<?php
// ============================================================
//  RaktSetu — index.php  (Home page)
// ============================================================

require_once 'config/db.php';

$pageTitle = 'RaktSetu · Emergency Blood Donor Network';

$db = getDB();

// Live stats
$stats = [];
$stats['donors']    = $db->query("SELECT COUNT(*) FROM users WHERE role='donor' AND is_verified=1")->fetchColumn();
$stats['requests']  = $db->query("SELECT COUNT(*) FROM blood_requests WHERE MONTH(created_at)=MONTH(CURDATE())")->fetchColumn();
$stats['fulfilled'] = $db->query("SELECT COUNT(*) FROM blood_requests WHERE status='fulfilled'")->fetchColumn();
$stats['hospitals'] = $db->query("SELECT COUNT(*) FROM hospitals WHERE is_verified=1")->fetchColumn();

// Live critical/high requests for ticker + featured section
$criticalReqs = $db->query("
    SELECT br.*, h.name AS hospital_name, h.city
    FROM blood_requests br
    LEFT JOIN hospitals h ON h.id = br.hospital_id
    WHERE br.status IN ('open','in_progress')
    ORDER BY FIELD(br.urgency,'critical','high','normal'), br.created_at DESC
    LIMIT 10
")->fetchAll();

// Blood inventory summary across all verified hospitals
$inventoryRaw = $db->query("
    SELECT blood_type, SUM(units_available) AS total_units
    FROM blood_inventory bi
    JOIN hospitals h ON h.id = bi.hospital_id
    WHERE h.is_verified = 1
    GROUP BY blood_type
")->fetchAll(PDO::FETCH_KEY_PAIR);

// Fill missing blood types with 0
$inventory = [];
foreach (BLOOD_TYPES as $bt) {
    $inventory[$bt] = (int)($inventoryRaw[$bt] ?? 0);
}

// Recent donors (public — verified only)
$recentDonors = $db->query("
    SELECT name, blood_type, city
    FROM users
    WHERE role='donor' AND is_verified=1
    ORDER BY created_at DESC
    LIMIT 6
")->fetchAll();

require_once 'includes/header.php';
?>

<!-- ── LIVE TICKER ─────────────────────────────────────────── -->
<?php if ($criticalReqs): ?>
<div class="live-ticker" id="liveTicker">
    <div class="ticker-label">
        <span class="ticker-dot"></span>
        LIVE
    </div>
    <div class="ticker-text">
        <?php foreach ($criticalReqs as $i => $r): ?>
            <span class="ticker-item <?= $i===0 ? 'ticker-visible' : 'ticker-hidden' ?>"
                  style="<?= $i>0 ? 'display:none' : '' ?>">
                <?= htmlspecialchars($r['blood_type']) ?> needed ·
                <?= htmlspecialchars($r['hospital_name'] ?? 'Hospital') ?>,
                <?= htmlspecialchars($r['city'] ?? '') ?> ·
                <?= $r['units_needed'] ?> unit<?= $r['units_needed']>1?'s':'' ?> ·
                <?= timeAgo($r['created_at']) ?>
            </span>
        <?php endforeach; ?>
    </div>
    <a href="<?= APP_URL ?>/requests.php" style="color:rgba(255,255,255,.85);font-size:12px;white-space:nowrap;flex-shrink:0">
        View all →
    </a>
</div>
<style>
.ticker-visible { display: inline !important; animation: tickerIn .4s ease; }
.ticker-hidden  { display: none !important; }
@keyframes tickerIn { from { opacity:0; transform:translateY(4px); } to { opacity:1; transform:translateY(0); } }
</style>
<?php endif; ?>

<!-- ── HERO ────────────────────────────────────────────────── -->
<section class="hero">
    <div class="hero-pulse">LIVE · <?= count(array_filter($criticalReqs, fn($r)=>$r['urgency']==='critical')) ?> CRITICAL REQUESTS</div>
    <h1>One donation can <em>save three lives.</em><br>Respond now.</h1>
    <p>India's emergency blood donor network — connecting verified donors with hospitals in real-time.</p>
    <div class="hero-btns">
        <a href="<?= APP_URL ?>/requests.php"           class="btn btn-red btn-lg">See Live Requests</a>
        <a href="<?= APP_URL ?>/auth.php?tab=register"  class="btn btn-outline btn-lg">Become a Donor</a>
    </div>
</section>

<!-- ── STATS ───────────────────────────────────────────────── -->
<div class="stats-row">
    <div class="stat-box">
        <div class="stat-num"><?= number_format($stats['donors']) ?></div>
        <div class="stat-label">Verified Donors</div>
    </div>
    <div class="stat-box">
        <div class="stat-num"><?= number_format($stats['fulfilled']) ?></div>
        <div class="stat-label">Lives Saved</div>
    </div>
    <div class="stat-box">
        <div class="stat-num"><?= number_format($stats['requests']) ?></div>
        <div class="stat-label">Requests This Month</div>
    </div>
    <div class="stat-box">
        <div class="stat-num"><?= $stats['hospitals'] ?></div>
        <div class="stat-label">Verified Hospitals</div>
    </div>
</div>

<div class="container">

    <!-- ── BLOOD INVENTORY ─────────────────────────────────── -->
    <section class="section">
        <h2 class="section-title">Blood availability across India</h2>
        <div class="inventory-grid">
            <?php foreach ($inventory as $bt => $units):
                $color  = $units <= 5 ? '#ef4444' : ($units <= 15 ? '#f97316' : '#22c55e');
                $cls    = $units <= 5 ? 'inv-critical' : ($units <= 15 ? 'inv-low' : 'inv-ok');
                $maxUnits = 120;
            ?>
            <div class="inv-card">
                <div class="inv-type"><?= $bt ?></div>
                <div class="inv-bar-wrap">
                    <div class="inv-bar"
                         data-units="<?= $units ?>"
                         data-max="<?= $maxUnits ?>"
                         style="background:<?= $color ?>;height:0">
                    </div>
                </div>
                <div class="inv-units <?= $cls ?>"><?= $units ?> units</div>
            </div>
            <?php endforeach; ?>
        </div>
        <p class="text-muted mt-2" style="font-size:12px;text-align:right">
            Aggregated from <?= $stats['hospitals'] ?> verified hospitals · updated every hour
        </p>
    </section>

    <!-- ── CRITICAL REQUESTS ───────────────────────────────── -->
    <?php if ($criticalReqs): ?>
    <section class="section" style="padding-top:0">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px">
            <h2 class="section-title" style="margin:0">Urgent requests right now</h2>
            <a href="<?= APP_URL ?>/requests.php" class="btn btn-sm btn-outline">View all →</a>
        </div>

        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(320px,1fr));gap:14px">
            <?php foreach (array_slice($criticalReqs, 0, 4) as $r):
                $pct = min(100, (int)(($r['units_fulfilled'] ?? 0) / max(1, $r['units_needed']) * 100));
            ?>
            <div class="request-card <?= $r['urgency'] ?>" data-req-id="<?= $r['id'] ?>">
                <div class="req-header">
                    <div class="req-badges">
                        <span class="badge badge-<?= $r['urgency'] ?>"><?= ucfirst($r['urgency']) ?></span>
                        <span class="bt-pill"><?= htmlspecialchars($r['blood_type']) ?></span>
                        <span class="text-muted" style="font-size:12px"><?= timeAgo($r['created_at']) ?></span>
                    </div>
                    <div class="req-units">
                        <?= (int)($r['units_fulfilled']??0) ?>/<?= $r['units_needed'] ?>
                        <span>units</span>
                    </div>
                </div>

                <p class="req-note"><?= htmlspecialchars(mb_substr($r['notes'] ?? '—', 0, 120)) ?><?= strlen($r['notes']??'')>120?'…':'' ?></p>

                <div class="req-meta">
                    <span>📍 <?= htmlspecialchars($r['hospital_name'] ?? 'Hospital') ?></span>
                    <span><?= htmlspecialchars($r['city'] ?? '') ?></span>
                </div>

                <div class="progress-bar">
                    <div class="progress-fill <?= $r['urgency'] ?>" data-pct="<?= $pct ?>" style="width:0"></div>
                </div>

                <?php if (isset($_SESSION['user_id']) && $_SESSION['eligible']): ?>
                    <button class="btn btn-red btn-full btn-respond mt-2" data-req-id="<?= $r['id'] ?>" style="margin-top:12px">
                        I can donate — Respond
                    </button>
                <?php else: ?>
                    <a href="<?= APP_URL ?>/auth.php?tab=register" class="btn btn-outline btn-full" style="margin-top:12px">
                        Register to respond
                    </a>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
    </section>
    <?php endif; ?>

    <!-- ── RECENT DONORS ───────────────────────────────────── -->
    <?php if ($recentDonors): ?>
    <section class="section" style="padding-top:0">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px">
            <h2 class="section-title" style="margin:0">Recent donors who joined</h2>
            <a href="<?= APP_URL ?>/donor-search.php" class="btn btn-sm btn-outline">Find a donor →</a>
        </div>
        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:10px">
            <?php foreach ($recentDonors as $d): ?>
            <div class="donor-card">
                <div class="donor-avatar"><?= strtoupper(substr($d['name'],0,1)) ?></div>
                <div style="flex:1">
                    <div class="donor-name"><?= htmlspecialchars($d['name']) ?></div>
                    <div class="donor-sub">📍 <?= htmlspecialchars($d['city'] ?? '—') ?></div>
                </div>
                <span class="bt-pill"><?= htmlspecialchars($d['blood_type'] ?? '?') ?></span>
            </div>
            <?php endforeach; ?>
        </div>
    </section>
    <?php endif; ?>

    <!-- ── HOW IT WORKS ────────────────────────────────────── -->
    <section class="section" style="padding-top:0">
        <h2 class="section-title text-center">How RaktSetu works</h2>
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:20px;text-align:center">
            <?php
            $steps = [
                ['🏥','Hospital posts request','Blood type, urgency, and units needed are listed instantly.'],
                ['🔍','System finds donors','Our engine matches verified donors nearby by blood type and location.'],
                ['📲','Donors are alerted','Matched donors receive an immediate notification to respond.'],
                ['❤️','Life is saved','Donor confirms, donates, and the request is fulfilled.'],
            ];
            foreach ($steps as $i => [$icon,$title,$desc]):
            ?>
            <div class="card" style="padding:24px 20px">
                <div style="font-size:32px;margin-bottom:12px"><?= $icon ?></div>
                <div style="font-size:13px;font-weight:700;color:var(--dark);margin-bottom:6px"><?= $title ?></div>
                <div style="font-size:12px;color:var(--gray-500);line-height:1.6"><?= $desc ?></div>
            </div>
            <?php endforeach; ?>
        </div>
    </section>

</div>

<?php require_once 'includes/footer.php'; ?>
