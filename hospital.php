<?php
// ============================================================
//  RaktSetu — hospital.php  (Hospital Staff Portal)
//  Demonstrates: role-based session guard, CRUD on inventory,
//  file upload (move_uploaded_file), PHP date functions
// ============================================================

require_once 'config/db.php';
require_once 'includes/auth-guard.php';

requireRole('hospital_staff'); // Only hospital_staff can access

$pageTitle = 'Hospital Portal · ' . APP_NAME;
$db        = getDB();
$uid       = (int)$_SESSION['user_id'];
$flash     = [];

// Find which hospital this staff member belongs to
// (linked by city for this demo — in production link via staff_hospital table)
$myHospital = $db->prepare("
    SELECT h.* FROM hospitals h
    JOIN users u ON u.city = h.city
    WHERE u.id = ? AND h.is_verified = 1
    LIMIT 1
");
$myHospital->execute([$uid]);
$hospital = $myHospital->fetch();

// ── HANDLE POST ACTIONS ──────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $hospital) {
    $action = $_POST['action'] ?? '';

    // ── UPDATE blood inventory (CRUD: Update) ─────────────────
    if ($action === 'update_inventory') {
        $bloodType = $_POST['blood_type'] ?? '';
        $units     = max(0, (int)($_POST['units_available'] ?? 0));

        if (in_array($bloodType, BLOOD_TYPES)) {
            $db->prepare("
                INSERT INTO blood_inventory (hospital_id, blood_type, units_available)
                VALUES (?, ?, ?)
                ON DUPLICATE KEY UPDATE units_available = ?, last_updated = NOW()
            ")->execute([$hospital['id'], $bloodType, $units, $units]);
            $_SESSION['flash'][] = ['type'=>'success','msg'=>"$bloodType inventory updated to $units units."];
        }
        header('Location: hospital.php');
        exit;
    }

    // ── VERIFY donation (CRUD: Update) ────────────────────────
    if ($action === 'verify_donation') {
        $donationId = (int)($_POST['donation_id'] ?? 0);
        if ($donationId) {
            $db->prepare("
                UPDATE donations SET verified_by_hospital=1 WHERE id=? AND hospital_id=?
            ")->execute([$donationId, $hospital['id']]);

            // Update donor's last_donated and eligibility
            $donorId = $db->prepare("SELECT donor_id FROM donations WHERE id=?")->execute([$donationId]);
            $donorId = $db->prepare("SELECT donor_id, donated_on FROM donations WHERE id=?")->execute([$donationId]);
            $donRow  = $db->query("SELECT donor_id, donated_on FROM donations WHERE id=$donationId")->fetch();
            if ($donRow) {
                $db->prepare("UPDATE users SET last_donated=?, is_eligible=0 WHERE id=?")
                   ->execute([$donRow['donated_on'], $donRow['donor_id']]);
            }

            // Close request if fully fulfilled
            $db->prepare("
                UPDATE blood_requests br
                SET br.units_fulfilled = (
                    SELECT COUNT(*) FROM donations d WHERE d.request_id = br.id AND d.verified_by_hospital=1
                ),
                br.status = CASE
                    WHEN br.units_fulfilled >= br.units_needed THEN 'fulfilled'
                    ELSE br.status
                END
                WHERE br.id = (SELECT request_id FROM donations WHERE id=?)
            ")->execute([$donationId]);

            $_SESSION['flash'][] = ['type'=>'success','msg'=>'Donation verified and donor eligibility updated.'];
        }
        header('Location: hospital.php');
        exit;
    }

    // ── LOG a new donation (CRUD: Create) ─────────────────────
    if ($action === 'log_donation') {
        $donorId   = (int)($_POST['donor_id']   ?? 0);
        $requestId = (int)($_POST['request_id'] ?? 0) ?: null;
        $donDate   = $_POST['donated_on'] ?? date('Y-m-d');

        if ($donorId) {
            // Handle optional certificate upload
            $certPath = null;
            if (isset($_FILES['certificate']) && $_FILES['certificate']['error'] === UPLOAD_ERR_OK) {
                $allowed = ['image/jpeg','image/png','application/pdf'];
                if (in_array($_FILES['certificate']['type'], $allowed) && $_FILES['certificate']['size'] < 5*1024*1024) {
                    $ext      = pathinfo($_FILES['certificate']['name'], PATHINFO_EXTENSION);
                    $filename = 'cert_' . uniqid() . '.' . $ext;
                    $dest     = UPLOAD_PATH . 'hospital-certs/' . $filename;
                    if (move_uploaded_file($_FILES['certificate']['tmp_name'], $dest)) {
                        $certPath = 'uploads/hospital-certs/' . $filename;
                    }
                }
            }

            $db->prepare("
                INSERT INTO donations (donor_id, request_id, hospital_id, donated_on, certificate_path)
                VALUES (?, ?, ?, ?, ?)
            ")->execute([$donorId, $requestId, $hospital['id'], $donDate, $certPath]);

            $_SESSION['flash'][] = ['type'=>'success','msg'=>'Donation logged successfully.'];
        }
        header('Location: hospital.php');
        exit;
    }
}

// ── Fetch data ───────────────────────────────────────────────
$inventory = [];
if ($hospital) {
    // Blood inventory for this hospital
    $invRows = $db->prepare("
        SELECT blood_type, units_available, last_updated
        FROM blood_inventory WHERE hospital_id = ?
        ORDER BY FIELD(blood_type,'A+','A-','B+','B-','O+','O-','AB+','AB-')
    ");
    $invRows->execute([$hospital['id']]);
    $invMap = $invRows->fetchAll(PDO::FETCH_KEY_PAIR);

    // Full inventory with 0 defaults
    foreach (BLOOD_TYPES as $bt) {
        $inventory[$bt] = (int)($invMap[$bt] ?? 0);
    }

    // Active requests at this hospital
    $activeReqs = $db->prepare("
        SELECT br.*, u.name AS requester_name
        FROM blood_requests br
        LEFT JOIN users u ON u.id = br.requester_id
        WHERE br.hospital_id = ? AND br.status IN ('open','in_progress')
        ORDER BY FIELD(br.urgency,'critical','high','normal'), br.created_at DESC
    ");
    $activeReqs->execute([$hospital['id']]);
    $activeReqs = $activeReqs->fetchAll();

    // Pending donations (unverified) at this hospital
    $pendingDons = $db->prepare("
        SELECT d.*, u.name AS donor_name, u.blood_type, u.phone,
               br.blood_type AS req_blood_type, br.urgency
        FROM donations d
        JOIN users u ON u.id = d.donor_id
        LEFT JOIN blood_requests br ON br.id = d.request_id
        WHERE d.hospital_id = ? AND d.verified_by_hospital = 0
        ORDER BY d.created_at DESC
    ");
    $pendingDons->execute([$hospital['id']]);
    $pendingDons = $pendingDons->fetchAll();

    // Verified donors for log dropdown
    $availableDonors = $db->query("
        SELECT id, name, blood_type, city FROM users
        WHERE role='donor' AND is_verified=1
        ORDER BY name LIMIT 200
    ")->fetchAll();
}

require_once 'includes/header.php';
?>

<div class="container" style="padding-top:32px;padding-bottom:60px">

    <?php if (!$hospital): ?>
    <!-- No hospital linked -->
    <div class="card text-center" style="padding:60px;max-width:500px;margin:0 auto">
        <div style="font-size:48px;margin-bottom:12px">🏥</div>
        <h2 style="font-size:20px;font-weight:700;color:var(--dark)">No hospital linked</h2>
        <p class="text-muted" style="margin-top:8px">Your account isn't linked to a verified hospital yet. Contact the admin.</p>
        <a href="<?= APP_URL ?>/contact.php" class="btn btn-red" style="margin-top:20px">Contact Admin</a>
    </div>

    <?php else: ?>

    <!-- ── Header ────────────────────────────────────────────── -->
    <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:28px;flex-wrap:wrap;gap:12px">
        <div>
            <p style="font-size:12px;color:var(--gray-500);font-weight:600;text-transform:uppercase;letter-spacing:.06em">Hospital Portal</p>
            <h1 style="font-size:22px;font-weight:700;color:var(--dark)"><?= htmlspecialchars($hospital['name']) ?></h1>
            <p class="text-muted">📍 <?= htmlspecialchars($hospital['city']) ?><?= $hospital['state'] ? ', '.$hospital['state'] : '' ?></p>
        </div>
        <a href="<?= APP_URL ?>/post-request.php" class="btn btn-red">🚨 Post Emergency</a>
    </div>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:24px;margin-bottom:28px">

        <!-- ── BLOOD INVENTORY (CRUD: Read + Update) ─────────── -->
        <div class="card">
            <p class="card-title">Blood inventory — update units</p>
            <form method="POST" style="display:flex;gap:8px;margin-bottom:16px;flex-wrap:wrap">
                <input type="hidden" name="action" value="update_inventory">
                <select class="form-control" name="blood_type" style="width:auto;flex:0 0 90px">
                    <?php foreach (BLOOD_TYPES as $bt): ?>
                        <option value="<?= $bt ?>"><?= $bt ?></option>
                    <?php endforeach; ?>
                </select>
                <input class="form-control" type="number" name="units_available"
                       min="0" max="999" placeholder="Units" style="width:auto;flex:0 0 90px">
                <button class="btn btn-dark btn-sm" type="submit">Update</button>
            </form>

            <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:8px">
                <?php foreach ($inventory as $bt => $units):
                    $col = $units <= 5 ? '#ef4444' : ($units <= 15 ? '#f97316' : '#22c55e');
                    $bg  = $units <= 5 ? '#fef2f2' : ($units <= 15 ? '#fff7ed' : '#f0fdf4');
                ?>
                <div style="text-align:center;background:<?= $bg ?>;border-radius:var(--radius-md);padding:12px 6px">
                    <div style="font-size:14px;font-weight:700;color:var(--dark)"><?= $bt ?></div>
                    <div style="font-size:22px;font-weight:800;color:<?= $col ?>;margin:4px 0"><?= $units ?></div>
                    <div style="font-size:10px;color:<?= $col ?>">units</div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- ── LOG DONATION (CRUD: Create) ───────────────────── -->
        <div class="card">
            <p class="card-title">Log a donation</p>
            <form method="POST" action="hospital.php" enctype="multipart/form-data">
                <input type="hidden" name="action" value="log_donation">
                <div class="form-group">
                    <label class="form-label">Donor *</label>
                    <select class="form-control" name="donor_id" required>
                        <option value="">Select donor…</option>
                        <?php foreach ($availableDonors as $d): ?>
                            <option value="<?= $d['id'] ?>"><?= htmlspecialchars($d['name']) ?> (<?= $d['blood_type'] ?> · <?= htmlspecialchars($d['city']) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Linked request (optional)</label>
                    <select class="form-control" name="request_id">
                        <option value="">— Not linked to request —</option>
                        <?php foreach ($activeReqs as $r): ?>
                            <option value="<?= $r['id'] ?>">
                                #<?= $r['id'] ?> · <?= $r['blood_type'] ?> · <?= ucfirst($r['urgency']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Donation date *</label>
                        <input class="form-control" type="date" name="donated_on"
                               value="<?= date('Y-m-d') ?>" max="<?= date('Y-m-d') ?>" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Certificate <span class="text-muted">(PDF/image)</span></label>
                        <input class="form-control" type="file" name="certificate" accept=".pdf,.jpg,.jpeg,.png">
                    </div>
                </div>
                <button type="submit" class="btn btn-red btn-full">Log Donation</button>
            </form>
        </div>
    </div>

    <!-- ── PENDING VERIFICATIONS (CRUD: Update) ─────────────── -->
    <?php if ($pendingDons): ?>
    <div class="card" style="margin-bottom:24px">
        <p class="card-title">Pending donation verifications
            <span style="background:var(--red);color:white;font-size:11px;padding:2px 7px;border-radius:20px;margin-left:6px"><?= count($pendingDons) ?></span>
        </p>
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Donor</th>
                        <th>Blood type</th>
                        <th>Date</th>
                        <th>Request</th>
                        <th>Certificate</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($pendingDons as $d): ?>
                    <tr>
                        <td>
                            <strong><?= htmlspecialchars($d['donor_name']) ?></strong>
                            <?php if ($d['phone']): ?>
                                <br><span style="font-size:11px;color:var(--gray-500)"><?= htmlspecialchars($d['phone']) ?></span>
                            <?php endif; ?>
                        </td>
                        <td><span class="bt-pill"><?= htmlspecialchars($d['blood_type']) ?></span></td>
                        <td><?= date('d M Y', strtotime($d['donated_on'])) ?></td>
                        <td>
                            <?php if ($d['request_id']): ?>
                                <span class="badge badge-<?= $d['urgency'] ?? 'gray' ?>">#<?= $d['request_id'] ?></span>
                            <?php else: ?>
                                <span style="color:var(--gray-500)">Walk-in</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($d['certificate_path']): ?>
                                <a href="<?= APP_URL . '/' . $d['certificate_path'] ?>" target="_blank"
                                   class="btn btn-sm btn-outline">View</a>
                            <?php else: ?>
                                <span style="color:var(--gray-500)">None</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <form method="POST" style="display:inline">
                                <input type="hidden" name="action" value="verify_donation">
                                <input type="hidden" name="donation_id" value="<?= $d['id'] ?>">
                                <button class="btn btn-sm" style="background:var(--success);color:white;border-color:var(--success)" type="submit">
                                    ✓ Verify
                                </button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

    <!-- ── ACTIVE REQUESTS at this hospital ─────────────────── -->
    <?php if ($activeReqs): ?>
    <div class="card">
        <p class="card-title">Active blood requests at <?= htmlspecialchars($hospital['name']) ?></p>
        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:12px">
            <?php foreach ($activeReqs as $r):
                $pct = min(100, (int)(($r['units_fulfilled']??0)/max(1,$r['units_needed'])*100));
            ?>
            <div class="request-card <?= $r['urgency'] ?>">
                <div class="req-header">
                    <div class="req-badges">
                        <span class="badge badge-<?= $r['urgency'] ?>"><?= ucfirst($r['urgency']) ?></span>
                        <span class="bt-pill"><?= htmlspecialchars($r['blood_type']) ?></span>
                    </div>
                    <div class="req-units"><?= (int)($r['units_fulfilled']??0) ?>/<?= $r['units_needed'] ?> <span>units</span></div>
                </div>
                <p class="req-note"><?= htmlspecialchars(mb_substr($r['notes']??'—',0,100)) ?></p>
                <div class="progress-bar">
                    <div class="progress-fill <?= $r['urgency'] ?>" data-pct="<?= $pct ?>" style="width:0"></div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <?php endif; ?>
</div>

<?php require_once 'includes/footer.php'; ?>
