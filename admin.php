<?php
// ============================================================
//  RaktSetu — admin.php  (Admin Panel)
//  Full CRUD · Role-protected · Analytics · Verification
// ============================================================

require_once 'config/db.php';
require_once 'includes/auth-guard.php';

requireRole('admin'); // Only admin

$pageTitle = 'Admin Panel · ' . APP_NAME;
$db        = getDB();

// ── HANDLE ACTIONS (POST) ────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // ── Verify / reject user ─────────────────────────────────
    if ($action === 'verify_user' || $action === 'reject_user') {
        $uid = (int)($_POST['user_id'] ?? 0);
        if ($uid) {
            if ($action === 'verify_user') {
                $db->prepare("UPDATE users SET is_verified=1 WHERE id=?")->execute([$uid]);
                $_SESSION['flash'][] = ['type'=>'success','msg'=>'User verified successfully.'];
            } else {
                $db->prepare("DELETE FROM users WHERE id=? AND role != 'admin'")->execute([$uid]);
                $_SESSION['flash'][] = ['type'=>'success','msg'=>'User removed.'];
            }
        }
    }

    // ── Verify / reject hospital ─────────────────────────────
    if ($action === 'verify_hospital') {
        $hid = (int)($_POST['hospital_id'] ?? 0);
        if ($hid) {
            $db->prepare("UPDATE hospitals SET is_verified=1 WHERE id=?")->execute([$hid]);
            $_SESSION['flash'][] = ['type'=>'success','msg'=>'Hospital verified.'];
        }
    }
    if ($action === 'delete_hospital') {
        $hid = (int)($_POST['hospital_id'] ?? 0);
        if ($hid) {
            $db->prepare("DELETE FROM hospitals WHERE id=?")->execute([$hid]);
            $_SESSION['flash'][] = ['type'=>'success','msg'=>'Hospital removed.'];
        }
    }

    // ── Add new hospital (CRUD: Create) ──────────────────────
    if ($action === 'add_hospital') {
        $name    = trim($_POST['h_name']    ?? '');
        $address = trim($_POST['h_address'] ?? '');
        $city    = trim($_POST['h_city']    ?? '');
        $state   = trim($_POST['h_state']   ?? '');
        $phone   = trim($_POST['h_phone']   ?? '');

        if ($name && $city) {
            // Optional cert upload
            $certPath = null;
            if (isset($_FILES['h_cert']) && $_FILES['h_cert']['error'] === UPLOAD_ERR_OK) {
                $ext  = pathinfo($_FILES['h_cert']['name'], PATHINFO_EXTENSION);
                $fn   = 'hcert_' . uniqid() . '.' . $ext;
                $dest = UPLOAD_PATH . 'hospital-certs/' . $fn;
                if (move_uploaded_file($_FILES['h_cert']['tmp_name'], $dest))
                    $certPath = 'uploads/hospital-certs/' . $fn;
            }

            $db->prepare("
                INSERT INTO hospitals (name, address, city, state, phone, cert_path, is_verified)
                VALUES (?, ?, ?, ?, ?, ?, 1)
            ")->execute([
                htmlspecialchars($name, ENT_QUOTES),
                htmlspecialchars($address, ENT_QUOTES),
                htmlspecialchars($city, ENT_QUOTES),
                htmlspecialchars($state, ENT_QUOTES),
                $phone ?: null,
                $certPath,
            ]);
            $_SESSION['flash'][] = ['type'=>'success','msg'=>"Hospital '$name' added and verified."];
        }
    }

    // ── Close / delete a request (CRUD: Update / Delete) ─────
    if ($action === 'close_request') {
        $rid = (int)($_POST['request_id'] ?? 0);
        if ($rid) {
            $db->prepare("UPDATE blood_requests SET status='closed' WHERE id=?")->execute([$rid]);
            $_SESSION['flash'][] = ['type'=>'success','msg'=>'Request closed.'];
        }
    }
    if ($action === 'delete_request') {
        $rid = (int)($_POST['request_id'] ?? 0);
        if ($rid) {
            $db->prepare("DELETE FROM blood_requests WHERE id=?")->execute([$rid]);
            $_SESSION['flash'][] = ['type'=>'success','msg'=>'Request deleted.'];
        }
    }

    header('Location: admin.php' . (isset($_GET['tab']) ? '?tab='.$_GET['tab'] : ''));
    exit;
}

// ── Active tab ───────────────────────────────────────────────
$tab = $_GET['tab'] ?? 'overview';

// ── Fetch data for each section ──────────────────────────────

// Overview stats
$stats = [
    'donors'    => $db->query("SELECT COUNT(*) FROM users WHERE role='donor'")->fetchColumn(),
    'verified'  => $db->query("SELECT COUNT(*) FROM users WHERE role='donor' AND is_verified=1")->fetchColumn(),
    'requests'  => $db->query("SELECT COUNT(*) FROM blood_requests WHERE MONTH(created_at)=MONTH(CURDATE())")->fetchColumn(),
    'fulfilled' => $db->query("SELECT COUNT(*) FROM blood_requests WHERE status='fulfilled'")->fetchColumn(),
    'hospitals' => $db->query("SELECT COUNT(*) FROM hospitals WHERE is_verified=1")->fetchColumn(),
    'donations' => $db->query("SELECT COUNT(*) FROM donations WHERE verified_by_hospital=1")->fetchColumn(),
    'pending_u' => $db->query("SELECT COUNT(*) FROM users WHERE is_verified=0 AND role!='admin'")->fetchColumn(),
    'pending_h' => $db->query("SELECT COUNT(*) FROM hospitals WHERE is_verified=0")->fetchColumn(),
];

// Users list (paginated)
$userPage  = max(1, (int)($_GET['up'] ?? 1));
$userTotal = (int)$db->query("SELECT COUNT(*) FROM users WHERE role != 'admin'")->fetchColumn();
$userPaged = $db->query("
    SELECT id, name, email, role, blood_type, city, is_verified, is_eligible, created_at
    FROM users WHERE role != 'admin'
    ORDER BY is_verified ASC, created_at DESC
    LIMIT 20 OFFSET " . (($userPage-1)*20)
)->fetchAll();

// Hospitals
$hospitalsList = $db->query("
    SELECT h.*, (SELECT COUNT(*) FROM blood_requests br WHERE br.hospital_id=h.id) AS req_count
    FROM hospitals h ORDER BY h.is_verified ASC, h.created_at DESC
")->fetchAll();

// Blood requests
$requestsList = $db->query("
    SELECT br.*, h.name AS hospital_name, h.city, u.name AS requester_name
    FROM blood_requests br
    LEFT JOIN hospitals h ON h.id = br.hospital_id
    LEFT JOIN users u ON u.id = br.requester_id
    ORDER BY FIELD(br.status,'open','in_progress','fulfilled','closed'),
             FIELD(br.urgency,'critical','high','normal'),
             br.created_at DESC
    LIMIT 50
")->fetchAll();

// Analytics — weekly requests
$weekly = $db->query("
    SELECT DATE(created_at) AS day,
           COUNT(*) AS total,
           SUM(status='fulfilled') AS fulfilled
    FROM blood_requests
    WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
    GROUP BY DATE(created_at)
    ORDER BY day
")->fetchAll();

// By blood type
$byType = $db->query("
    SELECT blood_type, COUNT(*) AS cnt
    FROM blood_requests
    GROUP BY blood_type
    ORDER BY cnt DESC
")->fetchAll();

// Pending verifications
$pendingUsers = $db->query("
    SELECT id,name,email,role,blood_type,city,created_at,id_proof_path
    FROM users WHERE is_verified=0 AND role!='admin' ORDER BY created_at DESC
")->fetchAll();
$pendingHospitals = $db->query("
    SELECT * FROM hospitals WHERE is_verified=0 ORDER BY created_at DESC
")->fetchAll();

require_once 'includes/header.php';
?>

<div style="display:grid;grid-template-columns:200px 1fr;min-height:calc(100vh - var(--nav-h))">

    <!-- ── SIDEBAR NAV ─────────────────────────────────────── -->
    <div style="background:var(--dark);padding:20px 0;position:sticky;top:var(--nav-h);height:calc(100vh - var(--nav-h))">
        <?php
        $tabs = [
            ['overview',  '📊', 'Overview'],
            ['users',     '👥', 'Users',     $stats['pending_u'] > 0 ? $stats['pending_u'] : null],
            ['hospitals', '🏥', 'Hospitals', $stats['pending_h'] > 0 ? $stats['pending_h'] : null],
            ['requests',  '🚨', 'Requests'],
            ['analytics', '📈', 'Analytics'],
        ];
        foreach ($tabs as [$id,$icon,$label,$badge]):
            $active = $tab === $id;
        ?>
        <a href="?tab=<?= $id ?>"
           style="display:flex;align-items:center;gap:8px;padding:11px 18px;font-size:13px;font-weight:500;
                  color:<?= $active?'white':'#6b7280' ?>;background:<?= $active?'var(--red)':'transparent' ?>;
                  text-decoration:none;transition:all .15s;border-left:3px solid <?= $active?'rgba(255,255,255,.3)':'transparent' ?>">
            <span style="font-size:15px"><?= $icon ?></span>
            <?= $label ?>
            <?php if ($badge): ?>
                <span style="margin-left:auto;background:<?= $active?'rgba(255,255,255,.25)':'var(--red)' ?>;color:white;font-size:10px;
                             width:18px;height:18px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:700">
                    <?= $badge ?>
                </span>
            <?php endif; ?>
        </a>
        <?php endforeach; ?>

        <div style="padding:18px;margin-top:auto;border-top:1px solid var(--gray-800);position:absolute;bottom:0;width:100%">
            <p style="font-size:11px;color:var(--gray-500)">Logged in as</p>
            <p style="font-size:13px;font-weight:600;color:white;margin-top:2px"><?= htmlspecialchars($_SESSION['user_name'] ?? 'Admin') ?></p>
            <a href="<?= APP_URL ?>/auth.php?action=logout" style="font-size:12px;color:var(--red-mid);margin-top:4px;display:block;text-decoration:none">Sign out</a>
        </div>
    </div>

    <!-- ── MAIN CONTENT ─────────────────────────────────────── -->
    <div style="padding:28px;background:var(--gray-50);overflow:auto">

        <?php if ($tab === 'overview'): ?>
        <!-- ── OVERVIEW ────────────────────────────────────── -->
        <h2 style="font-size:20px;font-weight:700;color:var(--dark);margin-bottom:20px">Overview</h2>
        <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:12px;margin-bottom:24px">
            <?php
            $overviewStats = [
                ['Total donors',       $stats['donors'],    'var(--dark)',    'var(--gray-50)'],
                ['Verified donors',    $stats['verified'],  'var(--success)', '#f0fdf4'],
                ['Requests this month',$stats['requests'],  'var(--red)',     'var(--red-light)'],
                ['Fulfilled',          $stats['fulfilled'], 'var(--success)', '#f0fdf4'],
                ['Hospitals verified', $stats['hospitals'], 'var(--info)',    '#eff6ff'],
                ['Verified donations', $stats['donations'], 'var(--red)',     'var(--red-light)'],
            ];
            foreach ($overviewStats as [$label,$val,$col,$bg]):
            ?>
            <div style="background:<?= $bg ?>;border-radius:var(--radius-lg);padding:18px;border:1px solid var(--gray-100)">
                <div style="font-size:28px;font-weight:800;color:<?= $col ?>"><?= number_format($val) ?></div>
                <div style="font-size:12px;color:var(--gray-500);margin-top:4px"><?= $label ?></div>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Pending actions -->
        <?php if ($pendingUsers || $pendingHospitals): ?>
        <div class="card" style="border-left:4px solid var(--red)">
            <p class="card-title">⚠️ Pending actions</p>
            <div style="display:flex;gap:14px;flex-wrap:wrap">
                <?php if ($pendingUsers): ?>
                    <a href="?tab=users" style="display:flex;align-items:center;gap:6px;padding:10px 16px;background:var(--red-light);border-radius:var(--radius-md);color:var(--red);font-size:13px;font-weight:600;text-decoration:none">
                        👥 <?= count($pendingUsers) ?> user<?= count($pendingUsers)>1?'s':'' ?> awaiting verification
                    </a>
                <?php endif; ?>
                <?php if ($pendingHospitals): ?>
                    <a href="?tab=hospitals" style="display:flex;align-items:center;gap:6px;padding:10px 16px;background:#fff7ed;border-radius:var(--radius-md);color:var(--warning);font-size:13px;font-weight:600;text-decoration:none">
                        🏥 <?= count($pendingHospitals) ?> hospital<?= count($pendingHospitals)>1?'s':'' ?> awaiting verification
                    </a>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <?php elseif ($tab === 'users'): ?>
        <!-- ── USERS ─────────────────────────────────────────── -->
        <h2 style="font-size:20px;font-weight:700;color:var(--dark);margin-bottom:20px">
            Users <span style="font-weight:400;color:var(--gray-500);font-size:16px">(<?= number_format($userTotal) ?> total)</span>
        </h2>

        <!-- Pending verifications -->
        <?php if ($pendingUsers): ?>
        <div class="card" style="margin-bottom:20px">
            <p class="card-title">Pending verifications <span style="background:var(--red);color:white;font-size:11px;padding:2px 8px;border-radius:20px;margin-left:6px"><?= count($pendingUsers) ?></span></p>
            <div style="display:flex;flex-direction:column;gap:10px">
                <?php foreach ($pendingUsers as $u): ?>
                <div style="display:flex;align-items:center;gap:12px;padding:12px;background:var(--gray-50);border-radius:var(--radius-md)">
                    <div style="width:36px;height:36px;border-radius:50%;background:var(--red-light);color:var(--red);display:flex;align-items:center;justify-content:center;font-weight:700;font-size:12px;flex-shrink:0">
                        <?= strtoupper(substr($u['name'],0,1)) ?>
                    </div>
                    <div style="flex:1;min-width:0">
                        <div style="font-size:13px;font-weight:600;color:var(--dark)"><?= htmlspecialchars($u['name']) ?></div>
                        <div style="font-size:11px;color:var(--gray-500)"><?= htmlspecialchars($u['email']) ?> · <?= ucfirst($u['role']) ?> · <?= htmlspecialchars($u['city'] ?? '—') ?></div>
                    </div>
                    <?php if ($u['blood_type']): ?><span class="bt-pill"><?= $u['blood_type'] ?></span><?php endif; ?>
                    <?php if ($u['id_proof_path']): ?>
                        <a href="<?= APP_URL . '/' . $u['id_proof_path'] ?>" target="_blank" class="btn btn-sm btn-outline">ID Proof</a>
                    <?php endif; ?>
                    <div style="display:flex;gap:6px">
                        <form method="POST" style="display:inline">
                            <input type="hidden" name="action"  value="verify_user">
                            <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                            <button class="btn btn-sm" style="background:var(--success);color:white;border-color:var(--success)">✓ Verify</button>
                        </form>
                        <form method="POST" style="display:inline" onsubmit="return confirm('Remove this user?')">
                            <input type="hidden" name="action"  value="reject_user">
                            <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                            <button class="btn btn-sm btn-outline" style="color:var(--red);border-color:var(--red)">✕</button>
                        </form>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- All users table -->
        <div class="card">
            <p class="card-title">All users</p>
            <div class="table-wrap">
                <table class="data-table">
                    <thead>
                        <tr><th>Name</th><th>Email</th><th>Role</th><th>Blood</th><th>City</th><th>Verified</th><th>Joined</th><th>Action</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($userPaged as $u): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($u['name']) ?></strong></td>
                            <td style="font-size:12px"><?= htmlspecialchars($u['email']) ?></td>
                            <td><span class="badge badge-gray"><?= ucfirst(str_replace('_',' ',$u['role'])) ?></span></td>
                            <td><?= $u['blood_type'] ? '<span class="bt-pill">'.htmlspecialchars($u['blood_type']).'</span>' : '—' ?></td>
                            <td><?= htmlspecialchars($u['city'] ?? '—') ?></td>
                            <td><?= $u['is_verified'] ? '<span style="color:var(--success)">✓ Yes</span>' : '<span style="color:var(--warning)">Pending</span>' ?></td>
                            <td style="font-size:11px"><?= date('d M Y', strtotime($u['created_at'])) ?></td>
                            <td>
                                <?php if (!$u['is_verified']): ?>
                                <form method="POST" style="display:inline">
                                    <input type="hidden" name="action" value="verify_user">
                                    <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                    <button class="btn btn-sm" style="background:var(--success);color:white;border-color:var(--success);padding:4px 10px">Verify</button>
                                </form>
                                <?php endif; ?>
                                <form method="POST" style="display:inline" onsubmit="return confirm('Delete this user?')">
                                    <input type="hidden" name="action" value="reject_user">
                                    <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                    <button class="btn btn-sm btn-outline" style="color:var(--red);border-color:var(--red);padding:4px 10px">Delete</button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <?php elseif ($tab === 'hospitals'): ?>
        <!-- ── HOSPITALS ─────────────────────────────────────── -->
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px">
            <h2 style="font-size:20px;font-weight:700;color:var(--dark)">Hospitals</h2>
            <button onclick="document.getElementById('addHospForm').classList.toggle('hidden')"
                    class="btn btn-red btn-sm">+ Add Hospital</button>
        </div>

        <!-- Add hospital form -->
        <div class="card hidden" id="addHospForm" style="margin-bottom:20px">
            <p class="card-title">Add new hospital</p>
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="add_hospital">
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Hospital name *</label>
                        <input class="form-control" type="text" name="h_name" required placeholder="AIIMS New Delhi">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Phone</label>
                        <input class="form-control" type="text" name="h_phone" placeholder="011-XXXXXXXX">
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Address</label>
                    <input class="form-control" type="text" name="h_address" placeholder="Full address">
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">City *</label>
                        <input class="form-control" type="text" name="h_city" required placeholder="New Delhi">
                    </div>
                    <div class="form-group">
                        <label class="form-label">State</label>
                        <input class="form-control" type="text" name="h_state" placeholder="Delhi">
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Verification certificate</label>
                    <input class="form-control" type="file" name="h_cert" accept=".pdf,.jpg,.jpeg,.png">
                </div>
                <button type="submit" class="btn btn-red">Add & Verify Hospital</button>
            </form>
        </div>

        <div class="card">
            <div class="table-wrap">
                <table class="data-table">
                    <thead>
                        <tr><th>Name</th><th>City</th><th>Phone</th><th>Requests</th><th>Verified</th><th>Actions</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($hospitalsList as $h): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($h['name']) ?></strong></td>
                            <td><?= htmlspecialchars($h['city']) ?><?= $h['state']?', '.htmlspecialchars($h['state']):'' ?></td>
                            <td style="font-size:12px"><?= htmlspecialchars($h['phone'] ?? '—') ?></td>
                            <td><?= $h['req_count'] ?></td>
                            <td><?= $h['is_verified'] ? '<span style="color:var(--success)">✓ Yes</span>' : '<span style="color:var(--warning)">Pending</span>' ?></td>
                            <td>
                                <?php if (!$h['is_verified']): ?>
                                <form method="POST" style="display:inline">
                                    <input type="hidden" name="action"      value="verify_hospital">
                                    <input type="hidden" name="hospital_id" value="<?= $h['id'] ?>">
                                    <button class="btn btn-sm" style="background:var(--success);color:white;border-color:var(--success);padding:4px 10px">Verify</button>
                                </form>
                                <?php endif; ?>
                                <?php if ($h['cert_path']): ?>
                                    <a href="<?= APP_URL.'/'.$h['cert_path'] ?>" target="_blank" class="btn btn-sm btn-outline" style="padding:4px 10px">Cert</a>
                                <?php endif; ?>
                                <form method="POST" style="display:inline" onsubmit="return confirm('Delete this hospital?')">
                                    <input type="hidden" name="action"      value="delete_hospital">
                                    <input type="hidden" name="hospital_id" value="<?= $h['id'] ?>">
                                    <button class="btn btn-sm btn-outline" style="color:var(--red);border-color:var(--red);padding:4px 10px">Delete</button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <?php elseif ($tab === 'requests'): ?>
        <!-- ── BLOOD REQUESTS ─────────────────────────────────── -->
        <h2 style="font-size:20px;font-weight:700;color:var(--dark);margin-bottom:20px">All blood requests</h2>
        <div class="card">
            <div class="table-wrap">
                <table class="data-table">
                    <thead>
                        <tr><th>#</th><th>Blood</th><th>Units</th><th>Hospital</th><th>Urgency</th><th>Status</th><th>Posted</th><th>Actions</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($requestsList as $r): ?>
                        <tr>
                            <td style="color:var(--gray-500)"><?= $r['id'] ?></td>
                            <td><span class="bt-pill"><?= htmlspecialchars($r['blood_type']) ?></span></td>
                            <td><?= (int)($r['units_fulfilled']??0) ?>/<?= $r['units_needed'] ?></td>
                            <td style="font-size:12px;max-width:160px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">
                                <?= htmlspecialchars($r['hospital_name'] ?? '—') ?>
                            </td>
                            <td><span class="badge badge-<?= $r['urgency'] ?>"><?= ucfirst($r['urgency']) ?></span></td>
                            <td>
                                <span style="font-size:12px;font-weight:600;color:<?=
                                    $r['status']==='fulfilled'?'var(--success)':
                                    ($r['status']==='closed'?'var(--gray-500)':'var(--warning)')
                                ?>">
                                    <?= ucfirst(str_replace('_',' ',$r['status'])) ?>
                                </span>
                            </td>
                            <td style="font-size:11px"><?= date('d M', strtotime($r['created_at'])) ?></td>
                            <td>
                                <?php if (in_array($r['status'],['open','in_progress'])): ?>
                                <form method="POST" style="display:inline">
                                    <input type="hidden" name="action"      value="close_request">
                                    <input type="hidden" name="request_id"  value="<?= $r['id'] ?>">
                                    <button class="btn btn-sm btn-outline" style="padding:4px 8px">Close</button>
                                </form>
                                <?php endif; ?>
                                <form method="POST" style="display:inline" onsubmit="return confirm('Delete request #<?= $r['id'] ?>?')">
                                    <input type="hidden" name="action"      value="delete_request">
                                    <input type="hidden" name="request_id"  value="<?= $r['id'] ?>">
                                    <button class="btn btn-sm btn-outline" style="padding:4px 8px;color:var(--red);border-color:var(--red)">Del</button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <?php elseif ($tab === 'analytics'): ?>
        <!-- ── ANALYTICS ─────────────────────────────────────── -->
        <h2 style="font-size:20px;font-weight:700;color:var(--dark);margin-bottom:20px">Analytics</h2>

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px">

            <!-- Weekly requests chart (canvas) -->
            <div class="card">
                <p class="card-title">Requests vs fulfilled — last 7 days</p>
                <canvas id="chartWeeklyLine" style="width:100%;display:block"></canvas>
            </div>

            <!-- By blood type (canvas horizontal) -->
            <div class="card">
                <p class="card-title">Requests by blood type</p>
                <canvas id="chartByType" style="width:100%;display:block"></canvas>
            </div>

            <!-- Key metrics -->
            <div class="card">
                <p class="card-title">Key metrics</p>
                <?php
                $total  = max(1, (int)$db->query("SELECT COUNT(*) FROM blood_requests")->fetchColumn());
                $fulfil = (int)$db->query("SELECT COUNT(*) FROM blood_requests WHERE status='fulfilled'")->fetchColumn();
                $rate   = round(($fulfil / $total) * 100);
                $avgResp = $db->query("
                    SELECT AVG(TIMESTAMPDIFF(MINUTE, br.created_at, dr.responded_at))
                    FROM donor_responses dr
                    JOIN blood_requests br ON br.id = dr.request_id
                ")->fetchColumn();
                ?>
                <div style="display:flex;flex-direction:column;gap:10px">
                    <?php
                    $kpis = [
                        ['Fulfillment rate',   $rate . '%',              $rate > 80 ? 'var(--success)' : 'var(--warning)'],
                        ['Total requests',      number_format($total),    'var(--dark)'],
                        ['Avg response time',   round($avgResp ?? 0) . ' min', 'var(--info)'],
                        ['Active donors',       number_format($stats['verified']), 'var(--success)'],
                    ];
                    foreach ($kpis as [$label,$val,$col]):
                    ?>
                    <div style="display:flex;justify-content:space-between;font-size:13px;padding:8px 0;border-bottom:1px solid var(--gray-100)">
                        <span style="color:var(--gray-500)"><?= $label ?></span>
                        <strong style="color:<?= $col ?>"><?= $val ?></strong>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Inventory summary -->
            <div class="card">
                <p class="card-title">National blood inventory</p>
                <?php
                $natInv = $db->query("
                    SELECT blood_type, SUM(units_available) AS total
                    FROM blood_inventory bi
                    JOIN hospitals h ON h.id=bi.hospital_id AND h.is_verified=1
                    GROUP BY blood_type
                ")->fetchAll(PDO::FETCH_KEY_PAIR);
                ?>
                <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:8px">
                    <?php foreach (BLOOD_TYPES as $bt):
                        $u  = (int)($natInv[$bt] ?? 0);
                        $c  = $u<=5?'#ef4444':($u<=15?'#f97316':'#22c55e');
                        $bg = $u<=5?'#fef2f2':($u<=15?'#fff7ed':'#f0fdf4');
                    ?>
                    <div style="text-align:center;background:<?= $bg ?>;border-radius:var(--radius-md);padding:10px 6px">
                        <div style="font-size:13px;font-weight:700;color:var(--dark)"><?= $bt ?></div>
                        <div style="font-size:20px;font-weight:800;color:<?= $c ?>;margin:3px 0"><?= $u ?></div>
                        <div style="font-size:10px;color:<?= $c ?>">units</div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <canvas id="chartFulfillment" style="width:100%;display:block;margin-top:16px"></canvas>
            </div>

        </div>
        <?php endif; ?>

    </div>
</div>

<?php if ($tab === 'analytics'): ?>
<script>
window.chartData = {
    weeklyLabels: <?= json_encode(array_map(fn($w) => date('D', strtotime($w['day'])), $weekly)) ?>,
    weeklyReqs:   <?= json_encode(array_map(fn($w) => (int)$w['total'], $weekly)) ?>,
    weeklyFulfilled: <?= json_encode(array_map(fn($w) => (int)$w['fulfilled'], $weekly)) ?>,
    btLabels: <?= json_encode(array_column($byType, 'blood_type')) ?>,
    btCounts: <?= json_encode(array_map(fn($b) => (int)$b['cnt'], $byType)) ?>,
    fulfillmentData: [
        <?= (int)$db->query("SELECT COUNT(*) FROM blood_requests WHERE status='fulfilled'")->fetchColumn() ?>,
        <?= (int)$db->query("SELECT COUNT(*) FROM blood_requests WHERE status IN ('open','in_progress')")->fetchColumn() ?>,
        <?= (int)$db->query("SELECT COUNT(*) FROM blood_requests WHERE status='closed'")->fetchColumn() ?>
    ]
};
</script>
<?php endif; ?>

<?php require_once 'includes/footer.php'; ?>
