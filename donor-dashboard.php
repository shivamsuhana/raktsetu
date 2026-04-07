<?php
// ============================================================
//  RaktSetu — donor-dashboard.php
//  Session-protected · Eligibility engine · Donation history
// ============================================================

require_once 'config/db.php';
require_once 'includes/auth-guard.php';

requireLogin(); // Redirect to auth.php if not logged in

$pageTitle = 'My Dashboard · ' . APP_NAME;
$db        = getDB();
$uid       = (int)$_SESSION['user_id'];

// ── Fetch full user profile ──────────────────────────────────
$user = $db->prepare("SELECT * FROM users WHERE id = ?");
$user->execute([$uid]);
$user = $user->fetch();

// ── Handle profile update (POST) ────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_profile') {
    $name   = trim($_POST['name']   ?? '');
    $phone  = trim($_POST['phone']  ?? '');
    $city   = trim($_POST['city']   ?? '');
    $state  = trim($_POST['state']  ?? '');
    $bt     = in_array($_POST['blood_type'] ?? '', BLOOD_TYPES) ? $_POST['blood_type'] : $user['blood_type'];

    $pErr = [];
    if (strlen($name) < 2)  $pErr[] = 'Name must be at least 2 characters.';
    if ($phone && !preg_match('/^[6-9]\d{9}$/', $phone)) $pErr[] = 'Enter a valid 10-digit mobile number.';
    if (empty($city))       $pErr[] = 'City is required.';

    if (empty($pErr)) {
        $db->prepare("
            UPDATE users SET name=?, phone=?, city=?, state=?, blood_type=? WHERE id=?
        ")->execute([
            htmlspecialchars($name, ENT_QUOTES),
            $phone ?: null,
            htmlspecialchars($city, ENT_QUOTES),
            htmlspecialchars($state, ENT_QUOTES),
            $bt, $uid
        ]);
        $_SESSION['user_name']  = $name;
        $_SESSION['blood_type'] = $bt;
        $_SESSION['city']       = $city;
        $_SESSION['flash'][]    = ['type'=>'success','msg'=>'Profile updated successfully.'];
        header('Location: donor-dashboard.php');
        exit;
    } else {
        $_SESSION['flash'][] = ['type'=>'error','msg'=>implode(' ', $pErr)];
    }
}

// ── Eligibility engine using PHP date functions ──────────────
$eligibilityStatus = 'eligible';
$daysUntilEligible = 0;
$nextEligibleDate  = null;

if ($user['last_donated']) {
    $lastDate      = new DateTime($user['last_donated']);
    $today         = new DateTime();
    $daysSince     = (int)$today->diff($lastDate)->days;
    $daysUntilElig = DONATION_COOLDOWN_DAYS - $daysSince;

    if ($daysUntilElig > 0) {
        $eligibilityStatus = 'ineligible';
        $daysUntilEligible = $daysUntilElig;
        $nextEligibleDate  = (clone $lastDate)->modify('+' . DONATION_COOLDOWN_DAYS . ' days');

        // Auto-update DB if status changed
        if ($user['is_eligible']) {
            $db->prepare("UPDATE users SET is_eligible=0 WHERE id=?")->execute([$uid]);
            $_SESSION['eligible'] = 0;
        }
    } else {
        if (!$user['is_eligible']) {
            $db->prepare("UPDATE users SET is_eligible=1 WHERE id=?")->execute([$uid]);
            $_SESSION['eligible'] = 1;
        }
    }
}

// ── Donation history (READ from DB) ─────────────────────────
$donations = $db->prepare("
    SELECT d.*, h.name AS hospital_name, h.city AS hospital_city,
           br.blood_type, br.urgency
    FROM donations d
    LEFT JOIN hospitals h     ON h.id = d.hospital_id
    LEFT JOIN blood_requests br ON br.id = d.request_id
    WHERE d.donor_id = ?
    ORDER BY d.donated_on DESC
    LIMIT 20
");
$donations->execute([$uid]);
$donations = $donations->fetchAll();

// ── Active responses (requests this donor has responded to) ──
$responses = $db->prepare("
    SELECT dr.*, br.blood_type, br.urgency, br.status AS req_status,
           br.units_needed, br.notes, h.name AS hospital_name, h.city
    FROM donor_responses dr
    JOIN blood_requests br ON br.id = dr.request_id
    LEFT JOIN hospitals h  ON h.id = br.hospital_id
    WHERE dr.donor_id = ?
    ORDER BY dr.responded_at DESC
    LIMIT 10
");
$responses->execute([$uid]);
$responses = $responses->fetchAll();

// ── Unread alerts ────────────────────────────────────────────
$alerts = $db->prepare("
    SELECT * FROM alerts WHERE user_id = ?
    ORDER BY created_at DESC LIMIT 15
");
$alerts->execute([$uid]);
$alerts = $alerts->fetchAll();

// Mark all alerts as read
$db->prepare("UPDATE alerts SET is_read=1 WHERE user_id=? AND is_read=0")->execute([$uid]);

// ── Stats ────────────────────────────────────────────────────
$totalDonations   = count($donations);
$verifiedDonations = count(array_filter($donations, function($d) { return $d['verified_by_hospital']; }));
$activeResponses  = count(array_filter($responses, function($r) { return in_array($r['req_status'],['open','in_progress']); }));

require_once 'includes/header.php';
?>

<div class="container" style="padding-top:32px;padding-bottom:60px">

    <!-- ── Page header ─────────────────────────────────────── -->
    <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:28px;flex-wrap:wrap;gap:12px">
        <div>
            <h1 style="font-size:24px;font-weight:700;color:var(--dark)">
                Welcome back, <?= htmlspecialchars(($parts = explode(' ', $user['name'] ?? '')) && isset($parts[0]) && $parts[0]!=='' ? $parts[0] : 'User') ?> 👋
            </h1>
            <p class="text-muted" style="margin-top:4px">
                <?= htmlspecialchars($user['email']) ?>
                <span class="bt-pill" style="margin-left:6px"><?= htmlspecialchars($user['blood_type'] ?? '?') ?></span>
                <?php if ($user['is_verified']): ?>
                    <span style="color:var(--success);font-size:12px;margin-left:4px">✓ Verified</span>
                <?php else: ?>
                    <span style="color:var(--warning);font-size:12px;margin-left:4px">⏳ Pending verification</span>
                <?php endif; ?>
            </p>
        </div>
        <a href="<?= APP_URL ?>/requests.php" class="btn btn-red">
            View Live Requests
        </a>
    </div>

    <div class="dash-grid">

        <!-- ── LEFT SIDEBAR ──────────────────────────────── -->
        <div>

            <!-- Eligibility box -->
            <div class="elig-box <?= $eligibilityStatus ?>" style="margin-bottom:16px">
                <?php if ($eligibilityStatus === 'eligible'): ?>
                    <div class="elig-num">✓</div>
                    <div style="font-size:16px;font-weight:700;color:var(--success);margin-top:4px">Eligible to Donate</div>
                    <div class="elig-label">You can respond to blood requests right now.</div>
                    <a href="<?= APP_URL ?>/requests.php" class="btn btn-full"
                       style="margin-top:14px;background:var(--success);color:white;border-color:var(--success)">
                        Find a Request
                    </a>
                <?php else: ?>
                    <div style="font-size:13px;color:var(--warning);font-weight:600;margin-bottom:6px">Next eligible in</div>
                    <div id="countdown"
                         data-target="<?= $nextEligibleDate->format('Y-m-d') ?>"
                         style="font-size:36px;font-weight:800;color:var(--warning)">
                        <?= $daysUntilEligible ?>d
                    </div>
                    <div class="elig-label">
                        You can donate again on<br>
                        <strong><?= $nextEligibleDate->format('d F Y') ?></strong>
                    </div>
                    <div style="margin-top:14px;font-size:11px;color:var(--warning)">
                        90-day cooldown after donation on
                        <?= date('d M Y', strtotime($user['last_donated'])) ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Stats grid -->
            <div class="card" style="margin-bottom:16px">
                <p class="card-title">Your impact</p>
                <div class="stat-card-grid">
                    <div class="stat-item">
                        <div class="stat-item-num" style="color:var(--red)"><?= $totalDonations ?></div>
                        <div class="stat-item-label">Total donations</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-item-num" style="color:var(--success)"><?= $verifiedDonations ?></div>
                        <div class="stat-item-label">Verified</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-item-num" style="color:var(--info)"><?= $totalDonations * 3 ?></div>
                        <div class="stat-item-label">Lives saved*</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-item-num" style="color:var(--warning)"><?= $activeResponses ?></div>
                        <div class="stat-item-label">Active responses</div>
                    </div>
                </div>
                <p style="font-size:10px;color:var(--gray-300);margin-top:10px">* Each donation can benefit up to 3 people.</p>
            </div>

            <!-- Quick profile -->
            <div class="card">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px">
                    <p class="card-title" style="margin:0">Profile</p>
                    <button onclick="toggleProfileForm()"
                            style="font-size:12px;color:var(--red);background:none;border:none;cursor:pointer;font-weight:600">
                        Edit
                    </button>
                </div>

                <!-- View mode -->
                <div id="profileView">
                    <?php
                    $profileFields = [
                        ['City', $user['city']],
                        ['State', $user['state'] ?: '—'],
                        ['Phone', $user['phone'] ?: '—'],
                        ['Blood type', $user['blood_type'] ?? '?'],
                        ['Role', ucfirst(str_replace('_',' ',$user['role']))],
                        ['Member since', date('d M Y', strtotime($user['created_at']))],
                    ];
                    foreach ($profileFields as [$label,$val]):
                    ?>
                    <div style="display:flex;justify-content:space-between;padding:8px 0;border-bottom:1px solid var(--gray-100);font-size:13px">
                        <span style="color:var(--gray-500)"><?= $label ?></span>
                        <span style="font-weight:500;color:var(--dark)"><?= htmlspecialchars($val) ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>

                <!-- Edit mode (hidden by default) -->
                <form id="profileForm" method="POST" style="display:none;margin-top:10px">
                    <input type="hidden" name="action" value="update_profile">
                    <div class="form-group">
                        <label class="form-label">Full name</label>
                        <input class="form-control" type="text" name="name"
                               value="<?= htmlspecialchars($user['name']) ?>" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Phone</label>
                        <input class="form-control" type="tel" name="phone"
                               value="<?= htmlspecialchars($user['phone'] ?? '') ?>" placeholder="9876543210">
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">City</label>
                            <input class="form-control" type="text" name="city"
                                   value="<?= htmlspecialchars($user['city'] ?? '') ?>" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">State</label>
                            <input class="form-control" type="text" name="state"
                                   value="<?= htmlspecialchars($user['state'] ?? '') ?>">
                        </div>
                    </div>
                    <?php if ($user['role'] === 'donor'): ?>
                    <div class="form-group">
                        <label class="form-label">Blood type</label>
                        <select class="form-control" name="blood_type">
                            <?php foreach (BLOOD_TYPES as $bt): ?>
                                <option value="<?= $bt ?>" <?= $user['blood_type']===$bt?'selected':'' ?>><?= $bt ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php endif; ?>
                    <div style="display:flex;gap:8px">
                        <button type="submit" class="btn btn-red btn-sm" style="flex:1">Save</button>
                        <button type="button" onclick="toggleProfileForm()" class="btn btn-sm" style="flex:1">Cancel</button>
                    </div>
                </form>
            </div>

        </div>

        <!-- ── RIGHT MAIN CONTENT ─────────────────────────── -->
        <div>

            <!-- Alerts section -->
            <?php if ($alerts): ?>
            <div class="card" style="margin-bottom:20px" id="alerts">
                <p class="card-title">Notifications
                    <span style="font-size:12px;font-weight:400;color:var(--gray-500);margin-left:6px"><?= count($alerts) ?> recent</span>
                </p>
                <div style="display:flex;flex-direction:column;gap:1px">
                    <?php foreach (array_slice($alerts, 0, 5) as $alert):
                        $icons = [
                            'new_request'       => '🚨',
                            'donor_confirmed'   => '✅',
                            'request_fulfilled' => '🎉',
                            'system'            => 'ℹ️',
                        ];
                        $icon = $icons[$alert['type']] ?? 'ℹ️';
                    ?>
                    <div style="display:flex;gap:10px;padding:12px 0;border-bottom:1px solid var(--gray-100);align-items:flex-start">
                        <span style="font-size:18px;flex-shrink:0"><?= $icon ?></span>
                        <div style="flex:1">
                            <p style="font-size:13px;color:var(--dark)"><?= htmlspecialchars($alert['message']) ?></p>
                            <p style="font-size:11px;color:var(--gray-500);margin-top:2px">
                                <?= date('d M Y, g:ia', strtotime($alert['created_at'])) ?>
                            </p>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Active responses -->
            <?php if ($responses): ?>
            <div class="card" style="margin-bottom:20px">
                <p class="card-title">My responses to requests</p>
                <div style="display:flex;flex-direction:column;gap:10px">
                    <?php foreach ($responses as $r): ?>
                    <div style="display:flex;gap:12px;align-items:center;padding:12px;background:var(--gray-50);border-radius:var(--radius-md)">
                        <span class="bt-pill"><?= htmlspecialchars($r['blood_type']) ?></span>
                        <div style="flex:1">
                            <div style="font-size:13px;color:var(--dark);font-weight:500">
                                <?= htmlspecialchars($r['hospital_name'] ?? 'Hospital') ?> · <?= htmlspecialchars($r['city'] ?? '') ?>
                            </div>
                            <div style="font-size:11px;color:var(--gray-500);margin-top:2px">
                                Responded <?= date('d M Y', strtotime($r['responded_at'])) ?>
                            </div>
                        </div>
                        <div style="text-align:right">
                            <span class="badge badge-<?= $r['req_status'] === 'fulfilled' ? 'success' : $r['urgency'] ?>">
                                <?= ucfirst(str_replace('_',' ',$r['req_status'])) ?>
                            </span>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Donation history -->
            <div class="card">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px">
                    <p class="card-title" style="margin:0">Donation history</p>
                    <?php if ($totalDonations > 0): ?>
                        <span class="badge badge-success"><?= $totalDonations ?> total</span>
                    <?php endif; ?>
                </div>

                <?php if (empty($donations)): ?>
                    <div style="text-align:center;padding:32px;color:var(--gray-500)">
                        <div style="font-size:36px;margin-bottom:10px">🩸</div>
                        <p style="font-size:14px;font-weight:600;color:var(--dark)">No donations yet</p>
                        <p style="font-size:13px;margin-top:4px">Respond to a live request to make your first donation.</p>
                        <a href="<?= APP_URL ?>/requests.php" class="btn btn-red btn-sm" style="margin-top:16px">
                            Find a Request
                        </a>
                    </div>
                <?php else: ?>
                    <div class="table-wrap">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Blood type</th>
                                    <th>Hospital</th>
                                    <th>Urgency</th>
                                    <th>Verified</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($donations as $d): ?>
                                <tr>
                                    <td><?= date('d M Y', strtotime($d['donated_on'])) ?></td>
                                    <td><span class="bt-pill"><?= htmlspecialchars($d['blood_type'] ?? '?') ?></span></td>
                                    <td>
                                        <?= htmlspecialchars($d['hospital_name'] ?? '—') ?>
                                        <?php if ($d['hospital_city']): ?>
                                            <span style="color:var(--gray-500);font-size:11px">, <?= htmlspecialchars($d['hospital_city']) ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($d['urgency']): ?>
                                            <span class="badge badge-<?= $d['urgency'] ?>"><?= ucfirst($d['urgency']) ?></span>
                                        <?php else: ?>—<?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($d['verified_by_hospital']): ?>
                                            <span style="color:var(--success);font-weight:600">✓ Yes</span>
                                        <?php else: ?>
                                            <span style="color:var(--warning)">Pending</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>

        </div>
    </div>
</div>

<script>
function toggleProfileForm() {
    const view = document.getElementById('profileView');
    const form = document.getElementById('profileForm');
    const isHidden = form.style.display === 'none';
    form.style.display = isHidden ? 'block' : 'none';
    view.style.display = isHidden ? 'none' : 'block';
}
</script>

<?php require_once 'includes/footer.php'; ?>
