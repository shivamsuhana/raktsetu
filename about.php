<?php
// ============================================================
//  RaktSetu — about.php
//  About the platform · Features · Impact · How it works
// ============================================================

require_once 'config/db.php';
require_once 'includes/auth-guard.php';

$pageTitle = 'About RaktSetu · ' . APP_NAME;
$db        = getDB();

// Live stats for display
$totalDonors    = $db->query("SELECT COUNT(*) FROM users WHERE role='donor' AND is_verified=1")->fetchColumn();
$totalFulfilled = $db->query("SELECT COUNT(*) FROM blood_requests WHERE status='fulfilled'")->fetchColumn();
$totalHospitals = $db->query("SELECT COUNT(*) FROM hospitals WHERE is_verified=1")->fetchColumn();
$totalDonations = $db->query("SELECT COUNT(*) FROM donations WHERE verified_by_hospital=1")->fetchColumn();

require_once 'includes/header.php';
?>

<!-- ── HERO ─────────────────────────────────────────────────── -->
<section style="background:var(--dark);color:white;padding:64px 20px;text-align:center">
    <div style="max-width:680px;margin:0 auto">
        <div style="font-size:48px;margin-bottom:16px">🩸</div>
        <h1 style="font-size:clamp(28px,5vw,44px);font-weight:800;line-height:1.15;letter-spacing:-.8px">
            India's emergency blood<br>donor network
        </h1>
        <p style="font-size:17px;color:#9ca3af;margin-top:16px;line-height:1.7;max-width:540px;margin-left:auto;margin-right:auto">
            RaktSetu bridges the gap between blood donors and hospitals during critical emergencies —
            matching the right donor to the right patient in real time.
        </p>
        <?php if (!isLoggedIn()): ?>
        <div style="display:flex;gap:12px;justify-content:center;margin-top:28px;flex-wrap:wrap">
            <a href="<?= APP_URL ?>/auth.php?tab=register" class="btn btn-red btn-lg">Become a Donor</a>
            <a href="<?= APP_URL ?>/requests.php" class="btn btn-lg"
               style="background:rgba(255,255,255,.1);color:white;border-color:rgba(255,255,255,.2)">
                View Live Requests
            </a>
        </div>
        <?php endif; ?>
    </div>
</section>

<!-- ── LIVE IMPACT STATS ─────────────────────────────────────── -->
<div style="background:var(--red);color:white">
    <div class="container">
        <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:1px;background:rgba(255,255,255,.15)">
            <?php
            $impactStats = [
                [number_format($totalDonors),    'Verified donors'],
                [number_format($totalFulfilled), 'Emergencies resolved'],
                [$totalHospitals,                'Partner hospitals'],
                [number_format($totalDonations * 3), 'Lives impacted*'],
            ];
            foreach ($impactStats as [$val,$label]):
            ?>
            <div style="padding:24px;text-align:center;background:transparent">
                <div style="font-size:30px;font-weight:800"><?= $val ?></div>
                <div style="font-size:12px;opacity:.85;margin-top:4px"><?= $label ?></div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<div class="container" style="padding-top:56px;padding-bottom:64px">

    <!-- ── THE PROBLEM ─────────────────────────────────────── -->
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:48px;align-items:center;margin-bottom:64px">
        <div>
            <p style="font-size:11px;font-weight:700;color:var(--red);text-transform:uppercase;letter-spacing:.1em;margin-bottom:10px">The Problem</p>
            <h2 style="font-size:28px;font-weight:700;color:var(--dark);line-height:1.25;margin-bottom:16px">
                Every year, lakhs of people die because the right blood isn't available in time.
            </h2>
            <p style="font-size:15px;color:var(--gray-500);line-height:1.8;margin-bottom:12px">
                India needs about 15 million units of blood annually but collects only 11 million.
                The gap isn't always about supply — it's about <strong>connection</strong>.
                Donors exist but they're unreachable. Hospitals need blood but they can't find willing donors fast enough.
            </p>
            <p style="font-size:15px;color:var(--gray-500);line-height:1.8">
                Families send desperate WhatsApp broadcasts to 200 contacts.
                Hospital staff call blood bank after blood bank. Every minute of delay is critical.
                There was no system built for this — until now.
            </p>
        </div>
        <div style="display:flex;flex-direction:column;gap:14px">
            <?php
            $problems = [
                ['🔴', '12 deaths per minute', 'in India due to inadequate blood supply'],
                ['🔴', 'Only 11M units', 'collected vs 15M needed annually'],
                ['🔴', '38% of requests', 'go unfulfilled due to coordination failure'],
                ['🔴', 'Average 4+ hours', 'wasted searching for the right blood type'],
            ];
            foreach ($problems as [$icon,$title,$desc]):
            ?>
            <div style="display:flex;gap:14px;padding:16px;background:var(--red-light);border-radius:var(--radius-md);border-left:4px solid var(--red)">
                <span style="font-size:20px;flex-shrink:0"><?= $icon ?></span>
                <div>
                    <p style="font-size:14px;font-weight:700;color:var(--dark)"><?= $title ?></p>
                    <p style="font-size:13px;color:var(--gray-500);margin-top:2px"><?= $desc ?></p>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- ── THE SOLUTION ────────────────────────────────────── -->
    <div style="text-align:center;margin-bottom:40px">
        <p style="font-size:11px;font-weight:700;color:var(--red);text-transform:uppercase;letter-spacing:.1em;margin-bottom:10px">The Solution</p>
        <h2 style="font-size:28px;font-weight:700;color:var(--dark);max-width:560px;margin:0 auto">How RaktSetu solves this</h2>
    </div>

    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:20px;margin-bottom:64px">
        <?php
        $solutions = [
            ['🔍','Smart matching','Our engine matches donors by blood type and proximity — not just city, but within kilometres. The right donor, instantly.'],
            ['⚡','Real-time alerts','The moment a request is posted, all matching eligible donors receive an immediate alert. No more WhatsApp broadcasts.'],
            ['🏥','Hospital network','Verified partner hospitals update blood inventory in real time. Families can see availability before even calling.'],
            ['📊','Full transparency','Donors see their eligibility status, donation history, and impact. Families can track donor responses live.'],
            ['🛡️','Verified donors','Every donor goes through ID verification. You know exactly who is responding to your emergency.'],
            ['📱','Mobile-first','Works perfectly on any phone, tablet or desktop — designed for emergency use on the go.'],
        ];
        foreach ($solutions as [$icon,$title,$desc]):
        ?>
        <div class="card" style="padding:24px">
            <div style="font-size:32px;margin-bottom:12px"><?= $icon ?></div>
            <p style="font-size:15px;font-weight:700;color:var(--dark);margin-bottom:8px"><?= $title ?></p>
            <p style="font-size:13px;color:var(--gray-500);line-height:1.7"><?= $desc ?></p>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- ── HOW IT WORKS (step by step) ─────────────────────── -->
    <div style="background:var(--gray-50);border-radius:var(--radius-xl);padding:48px 40px;margin-bottom:64px">
        <div style="text-align:center;margin-bottom:36px">
            <p style="font-size:11px;font-weight:700;color:var(--red);text-transform:uppercase;letter-spacing:.1em;margin-bottom:10px">Step by step</p>
            <h2 style="font-size:26px;font-weight:700;color:var(--dark)">From emergency to donation in minutes</h2>
        </div>

        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:0;position:relative">
            <?php
            $steps = [
                ['1','Hospital posts request','The attending doctor or family posts a blood request with type, urgency, and units needed.','🏥'],
                ['2','Matching engine runs','Our PHP matching engine selects all eligible verified donors within 50km with the right blood type.','🔍'],
                ['3','Donors are alerted','Every matched donor receives an instant notification with the request details.','📲'],
                ['4','Donor responds','The donor confirms availability with one click. The requester is notified immediately.','✅'],
                ['5','Donation happens','Donor visits the hospital. Staff logs and verifies the donation in the system.','🩸'],
                ['6','Request fulfilled','The system marks the request fulfilled and updates the donor\'s eligibility cooldown.','🎉'],
            ];
            foreach ($steps as $i => [$num,$title,$desc,$icon]):
            ?>
            <div style="text-align:center;padding:20px 16px;position:relative">
                <!-- Step number -->
                <div style="width:48px;height:48px;background:var(--red);color:white;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:18px;font-weight:800;margin:0 auto 14px">
                    <?= $icon ?>
                </div>
                <p style="font-size:11px;font-weight:700;color:var(--red);margin-bottom:6px">Step <?= $num ?></p>
                <p style="font-size:14px;font-weight:700;color:var(--dark);margin-bottom:8px"><?= $title ?></p>
                <p style="font-size:12px;color:var(--gray-500);line-height:1.6"><?= $desc ?></p>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- ── BLOOD TYPE COMPATIBILITY ─────────────────────────── -->
    <div style="margin-bottom:64px">
        <div style="text-align:center;margin-bottom:28px">
            <h2 style="font-size:24px;font-weight:700;color:var(--dark)">Blood type compatibility</h2>
            <p class="text-muted" style="margin-top:6px">Who can donate to whom</p>
        </div>
        <div class="card" style="overflow-x:auto">
            <table style="width:100%;border-collapse:collapse;font-size:13px;text-align:center">
                <thead>
                    <tr>
                        <th style="padding:10px;background:var(--gray-50);color:var(--gray-500);font-weight:600;border:1px solid var(--gray-100)">
                            Donor ↓ / Recipient →
                        </th>
                        <?php foreach (BLOOD_TYPES as $bt): ?>
                            <th style="padding:10px;background:var(--dark);color:white;font-weight:700;border:1px solid var(--gray-800)"><?= $bt ?></th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    // Compatibility matrix [donor => [can donate to...]]
                    $compat = [
                        'O-'  => ['O-','O+','A-','A+','B-','B+','AB-','AB+'],
                        'O+'  => ['O+','A+','B+','AB+'],
                        'A-'  => ['A-','A+','AB-','AB+'],
                        'A+'  => ['A+','AB+'],
                        'B-'  => ['B-','B+','AB-','AB+'],
                        'B+'  => ['B+','AB+'],
                        'AB-' => ['AB-','AB+'],
                        'AB+' => ['AB+'],
                    ];
                    foreach (BLOOD_TYPES as $donor):
                    ?>
                    <tr>
                        <td style="padding:10px;background:var(--dark);color:white;font-weight:700;border:1px solid var(--gray-800)"><?= $donor ?></td>
                        <?php foreach (BLOOD_TYPES as $recipient):
                            $canDonate = in_array($recipient, $compat[$donor]);
                        ?>
                        <td style="padding:10px;border:1px solid var(--gray-100);
                                   background:<?= $canDonate ? '#f0fdf4' : 'white' ?>;
                                   color:<?= $canDonate ? 'var(--success)' : 'var(--gray-300)' ?>;
                                   font-weight:<?= $canDonate ? '700' : '400' ?>">
                            <?= $canDonate ? '✓' : '–' ?>
                        </td>
                        <?php endforeach; ?>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <p style="font-size:11px;color:var(--gray-500);margin-top:12px">* Always consult a medical professional. Emergency compatibility may differ from standard transfusion guidelines.</p>
        </div>
    </div>

    <!-- ── WHY DONATE ───────────────────────────────────────── -->
    <div style="background:var(--red);border-radius:var(--radius-xl);padding:48px 40px;color:white;text-align:center;margin-bottom:64px">
        <h2 style="font-size:26px;font-weight:800;margin-bottom:12px">Why donate blood?</h2>
        <p style="font-size:16px;opacity:.9;max-width:520px;margin:0 auto 32px;line-height:1.7">
            One donation takes 15 minutes and can save up to 3 lives. Your body replenishes blood within 24–48 hours.
        </p>
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:16px;margin-bottom:32px">
            <?php
            $facts = [
                ['Every 2 seconds', 'someone in India needs blood'],
                ['15 minutes', 'is all a donation takes'],
                ['3 lives', 'saved per donation'],
                ['90 days', 'safe minimum gap between donations'],
            ];
            foreach ($facts as [$num,$label]):
            ?>
            <div style="background:rgba(255,255,255,.12);border-radius:var(--radius-md);padding:20px">
                <div style="font-size:22px;font-weight:800;margin-bottom:4px"><?= $num ?></div>
                <div style="font-size:12px;opacity:.85"><?= $label ?></div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php if (!isLoggedIn()): ?>
            <a href="<?= APP_URL ?>/auth.php?tab=register" class="btn btn-lg"
               style="background:white;color:var(--red);border-color:white;font-weight:700">
                Register as a Donor — It's Free
            </a>
        <?php else: ?>
            <a href="<?= APP_URL ?>/requests.php" class="btn btn-lg"
               style="background:white;color:var(--red);border-color:white;font-weight:700">
                See Who Needs Blood Now
            </a>
        <?php endif; ?>
    </div>

    <!-- ── TECH STACK (for viva) ─────────────────────────────── -->
    <div style="text-align:center;margin-bottom:28px">
        <h2 style="font-size:24px;font-weight:700;color:var(--dark)">Built with</h2>
        <p class="text-muted" style="margin-top:6px">Modern web technologies, open-source stack</p>
    </div>
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:14px">
        <?php
        $techStack = [
            ['HTML5',       'Semantic, accessible markup structure',             '#f97316'],
            ['CSS3',        'Responsive · Box Model · Positioning · Floats',     '#3b82f6'],
            ['JavaScript',  'DOM manipulation · AJAX · Real-time polling',        '#eab308'],
            ['PHP 8',       'Sessions · Cookies · Mail · File upload · PDO',     '#8b5cf6'],
            ['MySQL',       'Relational DB · Full CRUD · Haversine queries',     '#10b981'],
            ['Git + GitHub','Version control · Incremental commits · CI',        '#111827'],
        ];
        foreach ($techStack as [$tech,$desc,$col]):
        ?>
        <div class="card" style="padding:18px;text-align:center">
            <div style="width:10px;height:10px;background:<?= $col ?>;border-radius:50%;margin:0 auto 10px"></div>
            <p style="font-size:14px;font-weight:700;color:var(--dark);margin-bottom:6px"><?= $tech ?></p>
            <p style="font-size:11px;color:var(--gray-500);line-height:1.5"><?= $desc ?></p>
        </div>
        <?php endforeach; ?>
    </div>

</div>

<?php require_once 'includes/footer.php'; ?>
