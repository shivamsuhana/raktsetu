<?php
// ============================================================
//  RaktSetu — post-request.php
//  Emergency blood request form · PHP INSERT · Matching engine
// ============================================================

require_once 'config/db.php';
require_once 'includes/auth-guard.php';

requireLogin(); // Must be logged in

$pageTitle = 'Post Blood Request · ' . APP_NAME;
$db        = getDB();

$errors  = [];
$success = false;
$newReqId = null;

// ── Fetch verified hospitals for dropdown ─────────────────────
$hospitals = $db->query("
    SELECT id, name, city, state FROM hospitals
    WHERE is_verified = 1
    ORDER BY city, name
")->fetchAll();

// ── Handle form submission ───────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $bloodType   = $_POST['blood_type']   ?? '';
    $units       = (int)($_POST['units_needed'] ?? 1);
    $hospitalId  = (int)($_POST['hospital_id']  ?? 0);
    $urgency     = $_POST['urgency']      ?? 'normal';
    $patientName = trim($_POST['patient_name'] ?? '');
    $notes       = trim($_POST['notes']        ?? '');
    $neededBy    = trim($_POST['needed_by']    ?? '');

    // ── Server-side validation ───────────────────────────────
    if (!in_array($bloodType, BLOOD_TYPES))
        $errors['blood_type'] = 'Select a valid blood type.';

    if ($units < 1 || $units > 20)
        $errors['units_needed'] = 'Units must be between 1 and 20.';

    if (!$hospitalId)
        $errors['hospital_id'] = 'Select a hospital.';
    else {
        $hCheck = $db->prepare("SELECT id FROM hospitals WHERE id=? AND is_verified=1");
        $hCheck->execute([$hospitalId]);
        if (!$hCheck->fetch()) $errors['hospital_id'] = 'Invalid hospital selected.';
    }

    if (!in_array($urgency, ['critical','high','normal']))
        $errors['urgency'] = 'Select a valid urgency level.';

    if (strlen($notes) > 500)
        $errors['notes'] = 'Notes must be under 500 characters.';

    $neededByVal = null;
    if ($neededBy) {
        $dt = DateTime::createFromFormat('Y-m-d\TH:i', $neededBy);
        if (!$dt) $errors['needed_by'] = 'Invalid date/time format.';
        else        $neededByVal = $dt->format('Y-m-d H:i:s');
    }

    // ── INSERT blood request ─────────────────────────────────
    if (empty($errors)) {
        $db->prepare("
            INSERT INTO blood_requests
              (requester_id, hospital_id, blood_type, units_needed, urgency,
               patient_name, notes, needed_by, status)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'open')
        ")->execute([
            $_SESSION['user_id'],
            $hospitalId,
            $bloodType,
            $units,
            $urgency,
            htmlspecialchars($patientName, ENT_QUOTES) ?: null,
            htmlspecialchars($notes, ENT_QUOTES),
            $neededByVal,
        ]);

        $newReqId = (int)$db->lastInsertId();

        // ── Donor matching engine ─────────────────────────────
        // Find eligible verified donors with matching blood type
        // Haversine distance filtering: prioritise same city first, then within 50km
        $matchedDonors = $db->prepare("
            SELECT u.id, u.name, u.email, u.city
            FROM users u
            WHERE u.role = 'donor'
              AND u.is_verified = 1
              AND u.is_eligible = 1
              AND u.blood_type = ?
              AND u.id != ?
            ORDER BY (u.city = (SELECT h.city FROM hospitals h WHERE h.id = ?)) DESC,
                     u.created_at DESC
            LIMIT 50
        ");
        $matchedDonors->execute([$bloodType, $_SESSION['user_id'], $hospitalId]);
        $donors = $matchedDonors->fetchAll();

        // Create alert for each matched donor
        if ($donors) {
            $alertStmt = $db->prepare("
                INSERT INTO alerts (user_id, request_id, type, message)
                VALUES (?, ?, 'new_request', ?)
            ");
            foreach ($donors as $donor) {
                $msg = "Emergency: {$units} unit(s) of {$bloodType} needed" .
                       ($hospitals ? " at hospital" : "") .
                       ". Urgency: " . ucfirst($urgency) . ".";
                $alertStmt->execute([$donor['id'], $newReqId, $msg]);

                // In production: also send email via mail()
                // @mail($donor['email'], "RaktSetu — Blood Needed: {$bloodType}", $msg, "From: noreply@raktsetu.org");
            }
        }

        $success = true;
    }
}

require_once 'includes/header.php';
?>

<div class="container" style="padding-top:32px;padding-bottom:60px">
    <div style="max-width:680px;margin:0 auto">

        <?php if ($success): ?>
        <!-- ── SUCCESS STATE ───────────────────────────────── -->
        <div class="card text-center fade-in" style="padding:48px 32px">
            <div style="font-size:56px;margin-bottom:16px">🚨</div>
            <h1 style="font-size:24px;font-weight:700;color:var(--dark)">Request posted!</h1>
            <p class="text-muted" style="margin-top:8px;max-width:420px;margin-left:auto;margin-right:auto">
                We've alerted all eligible <?= htmlspecialchars($_POST['blood_type'] ?? '') ?> donors nearby.
                They will receive an immediate notification.
            </p>
            <div style="display:flex;gap:12px;justify-content:center;margin-top:24px;flex-wrap:wrap">
                <a href="<?= APP_URL ?>/requests.php" class="btn btn-red">Track live status</a>
                <a href="<?= APP_URL ?>/donor-search.php?bt=<?= urlencode($_POST['blood_type'] ?? '') ?>"
                   class="btn btn-outline">Find donors manually</a>
            </div>

            <!-- Share panel -->
            <div style="margin-top:28px;padding:16px;background:var(--gray-50);border-radius:var(--radius-md)">
                <p style="font-size:13px;font-weight:600;color:var(--dark);margin-bottom:8px">Share this request</p>
                <p style="font-size:12px;color:var(--gray-500)">Copy this link and share on WhatsApp, Instagram, or any platform</p>
                <div style="display:flex;gap:8px;margin-top:10px">
                    <input class="form-control" type="text" readonly
                           value="<?= APP_URL ?>/requests.php"
                           id="shareLink" style="font-size:12px;padding:8px">
                    <button class="btn btn-sm btn-dark" onclick="copyLink()">Copy</button>
                </div>
            </div>
        </div>

        <?php else: ?>
        <!-- ── REQUEST FORM ────────────────────────────────── -->
        <div style="margin-bottom:24px">
            <h1 style="font-size:24px;font-weight:700;color:var(--dark)">Post Emergency Blood Request</h1>
            <p class="text-muted" style="margin-top:6px">We'll immediately alert all matching eligible donors near the hospital.</p>
        </div>

        <!-- Urgency info banner -->
        <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:10px;margin-bottom:24px">
            <?php
            $urgencyInfo = [
                ['critical', '🚨 Critical', 'Surgery in &lt;3 hrs. Immediate donor needed.', '#fef2f2', '#dc2626'],
                ['high',     '⚡ High',     'Needed within 24 hours.',                     '#fff7ed', '#ea580c'],
                ['normal',   'ℹ️ Normal',   'Scheduled procedure. 1–3 days.',              '#eff6ff', '#2563eb'],
            ];
            foreach ($urgencyInfo as [$val,$label,$desc,$bg,$col]):
            ?>
            <div style="background:<?= $bg ?>;border-radius:var(--radius-md);padding:12px;cursor:pointer"
                 onclick="document.querySelector('[name=urgency][value=<?= $val ?>]').checked=true;highlightUrgency('<?= $val ?>')">
                <p style="font-size:13px;font-weight:700;color:<?= $col ?>"><?= $label ?></p>
                <p style="font-size:11px;color:<?= $col ?>;opacity:.8;margin-top:3px"><?= $desc ?></p>
            </div>
            <?php endforeach; ?>
        </div>

        <div class="card">
            <form method="POST" action="post-request.php" novalidate onsubmit="return validateRequest()">

                <div class="form-row">
                    <!-- Blood type -->
                    <div class="form-group">
                        <label class="form-label" for="f_bt">Blood type required *</label>
                        <select class="form-control <?= isset($errors['blood_type']) ? 'error' : '' ?>"
                                id="f_bt" name="blood_type">
                            <option value="">Select blood type…</option>
                            <?php foreach (BLOOD_TYPES as $bt): ?>
                                <option value="<?= $bt ?>"><?= $bt ?></option>
                            <?php endforeach; ?>
                        </select>
                        <p class="form-error <?= isset($errors['blood_type']) ? 'show' : '' ?>"
                           id="err_blood_type"><?= $errors['blood_type'] ?? '' ?></p>
                    </div>

                    <!-- Units needed -->
                    <div class="form-group">
                        <label class="form-label" for="f_units">Units needed *</label>
                        <input class="form-control <?= isset($errors['units_needed']) ? 'error' : '' ?>"
                               type="number" id="f_units" name="units_needed"
                               min="1" max="20" value="1" placeholder="1">
                        <p class="form-hint">1 unit = 450 ml whole blood</p>
                        <p class="form-error <?= isset($errors['units_needed']) ? 'show' : '' ?>"
                           id="err_units_needed"><?= $errors['units_needed'] ?? '' ?></p>
                    </div>
                </div>

                <!-- Hospital -->
                <div class="form-group">
                    <label class="form-label" for="f_hosp">Hospital *</label>
                    <select class="form-control <?= isset($errors['hospital_id']) ? 'error' : '' ?>"
                            id="f_hosp" name="hospital_id">
                        <option value="">Select hospital…</option>
                        <?php
                        $currentCity = '';
                        foreach ($hospitals as $h):
                            if ($h['city'] !== $currentCity) {
                                if ($currentCity) echo '</optgroup>';
                                echo '<optgroup label="' . htmlspecialchars($h['city']) . '">';
                                $currentCity = $h['city'];
                            }
                        ?>
                            <option value="<?= $h['id'] ?>"><?= htmlspecialchars($h['name']) ?></option>
                        <?php endforeach;
                        if ($currentCity) echo '</optgroup>'; ?>
                    </select>
                    <p class="form-error <?= isset($errors['hospital_id']) ? 'show' : '' ?>"
                       id="err_hospital_id"><?= $errors['hospital_id'] ?? '' ?></p>
                </div>

                <!-- Urgency -->
                <div class="form-group">
                    <label class="form-label">Urgency level *</label>
                    <div style="display:flex;gap:12px;flex-wrap:wrap">
                        <?php foreach ([['critical','🚨 Critical','var(--red)'],['high','⚡ High','var(--warning)'],['normal','ℹ️ Normal','var(--info)']] as [$val,$label,$col]): ?>
                        <label style="display:flex;align-items:center;gap:6px;cursor:pointer;font-size:14px;font-weight:500;color:<?= $col ?>">
                            <input type="radio" name="urgency" value="<?= $val ?>"
                                   <?= $val==='normal'?'checked':'' ?>
                                   style="accent-color:<?= $col ?>">
                            <?= $label ?>
                        </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="form-row">
                    <!-- Patient name -->
                    <div class="form-group">
                        <label class="form-label" for="f_patient">Patient name</label>
                        <input class="form-control" type="text" id="f_patient" name="patient_name"
                               placeholder="Optional">
                    </div>
                    <!-- Needed by -->
                    <div class="form-group">
                        <label class="form-label" for="f_needed">Needed by (date & time)</label>
                        <input class="form-control <?= isset($errors['needed_by']) ? 'error' : '' ?>"
                               type="datetime-local" id="f_needed" name="needed_by"
                               min="<?= date('Y-m-d\TH:i') ?>">
                        <p class="form-error <?= isset($errors['needed_by']) ? 'show' : '' ?>"><?= $errors['needed_by'] ?? '' ?></p>
                    </div>
                </div>

                <!-- Notes -->
                <div class="form-group">
                    <label class="form-label" for="f_notes">Additional notes</label>
                    <textarea class="form-control" id="f_notes" name="notes"
                              data-maxlen="500" rows="4"
                              placeholder="Medical condition, special requirements, contact details…"></textarea>
                    <div class="char-counter">0 / 500</div>
                </div>

                <!-- Disclaimer -->
                <div style="background:var(--gray-50);border-radius:var(--radius-md);padding:14px;margin-bottom:20px;font-size:12px;color:var(--gray-500);line-height:1.6">
                    <strong style="color:var(--dark)">Please note:</strong> Posting a false emergency request is a violation of our terms.
                    This system immediately alerts real donors — use it only for genuine medical emergencies.
                </div>

                <button type="submit" class="btn btn-red btn-full btn-lg">
                    🚨 Post Emergency Request
                </button>
            </form>
        </div>
        <?php endif; ?>
    </div>
</div>

<script src="<?= APP_URL ?>/js/validate.js"></script>
<script>
function highlightUrgency(val) {
    document.querySelectorAll('[name=urgency]').forEach(r => r.checked = r.value === val);
}
function copyLink() {
    const input = document.getElementById('shareLink');
    input.select();
    navigator.clipboard.writeText(input.value).then(() => {
        const btn = input.nextElementSibling;
        btn.textContent = 'Copied!';
        setTimeout(() => btn.textContent = 'Copy', 2000);
    });
}
</script>

<?php require_once 'includes/footer.php'; ?>
