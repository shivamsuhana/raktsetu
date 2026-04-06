<?php
// ============================================================
//  RaktSetu — auth.php
//  Login · Register · Logout
//  Demonstrates: sessions, cookies, PHP built-ins, form handling
// ============================================================

session_start();
require_once 'config/db.php';

$pageTitle = 'Sign In / Register · ' . APP_NAME;

// ── Logout ───────────────────────────────────────────────────
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    $_SESSION = [];
    session_destroy();
    // Clear remember-me cookie
    setcookie(COOKIE_NAME, '', time() - 3600, '/', '', false, true);
    header('Location: ' . APP_URL . '/index.php');
    exit;
}

// ── Already logged in ────────────────────────────────────────
if (isset($_SESSION['user_id'])) {
    header('Location: ' . APP_URL . '/donor-dashboard.php');
    exit;
}

$activeTab = isset($_GET['tab']) && $_GET['tab'] === 'register' ? 'register' : 'login';
$errors    = [];
$old       = [];  // repopulate form on error

// ── Handle POST ──────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // ── REGISTER ─────────────────────────────────────────────
    if ($action === 'register') {
        $activeTab = 'register';

        $name       = trim($_POST['name'] ?? '');
        $email      = trim($_POST['email'] ?? '');
        $phone      = trim($_POST['phone'] ?? '');
        $password   = $_POST['password'] ?? '';
        $confirm    = $_POST['confirm_password'] ?? '';
        $role       = $_POST['role'] ?? 'donor';
        $blood_type = $_POST['blood_type'] ?? '';
        $city       = trim($_POST['city'] ?? '');
        $state      = trim($_POST['state'] ?? '');

        $old = compact('name','email','phone','role','blood_type','city','state');

        // ── Server-side validation using PHP built-ins ───────
        if (empty($name))   $errors['name'] = 'Name is required.';
        if (strlen($name) < 2) $errors['name'] = 'Name must be at least 2 characters.';

        if (!filter_var($email, FILTER_VALIDATE_EMAIL))
            $errors['email'] = 'Enter a valid email address.';

        if ($phone && !preg_match('/^[6-9]\d{9}$/', $phone))
            $errors['phone'] = 'Enter a valid 10-digit Indian mobile number.';

        if (strlen($password) < 8)
            $errors['password'] = 'Password must be at least 8 characters.';

        if ($password !== $confirm)
            $errors['confirm_password'] = 'Passwords do not match.';

        if (!in_array($role, ['donor','patient','hospital_staff']))
            $errors['role'] = 'Invalid role selected.';

        if ($role === 'donor' && empty($blood_type))
            $errors['blood_type'] = 'Blood type is required for donors.';

        if (empty($city))  $errors['city']  = 'City is required.';

        // Check duplicate email
        if (empty($errors)) {
            $stmt = getDB()->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->fetch()) $errors['email'] = 'An account with this email already exists.';
        }

        // ── Handle ID proof upload ───────────────────────────
        $idProofPath = null;
        if (isset($_FILES['id_proof']) && $_FILES['id_proof']['error'] === UPLOAD_ERR_OK) {
            $allowed = ['image/jpeg','image/png','application/pdf'];
            $maxSize = 5 * 1024 * 1024; // 5 MB

            if (!in_array($_FILES['id_proof']['type'], $allowed))
                $errors['id_proof'] = 'Only JPG, PNG, or PDF files allowed.';
            elseif ($_FILES['id_proof']['size'] > $maxSize)
                $errors['id_proof'] = 'File size must be under 5 MB.';
            else {
                $ext         = pathinfo($_FILES['id_proof']['name'], PATHINFO_EXTENSION);
                $filename    = 'id_' . uniqid() . '.' . $ext;
                $destination = UPLOAD_PATH . 'id-proofs/' . $filename;

                if (move_uploaded_file($_FILES['id_proof']['tmp_name'], $destination)) {
                    $idProofPath = 'uploads/id-proofs/' . $filename;
                } else {
                    $errors['id_proof'] = 'Upload failed. Please try again.';
                }
            }
        }

        // ── Insert user ──────────────────────────────────────
        if (empty($errors)) {
            $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);

            $stmt = getDB()->prepare("
                INSERT INTO users
                  (name, email, phone, password_hash, role, blood_type, city, state, id_proof_path, is_verified)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 0)
            ");
            $stmt->execute([
                htmlspecialchars($name, ENT_QUOTES),
                $email,
                $phone ?: null,
                $hash,
                $role,
                $blood_type ?: null,
                htmlspecialchars($city, ENT_QUOTES),
                htmlspecialchars($state, ENT_QUOTES),
                $idProofPath,
            ]);

            $newId = (int)getDB()->lastInsertId();

            // Auto-login after registration
            $_SESSION['user_id']    = $newId;
            $_SESSION['user_name']  = $name;
            $_SESSION['user_email'] = $email;
            $_SESSION['user_role']  = $role;
            $_SESSION['blood_type'] = $blood_type;
            $_SESSION['city']       = $city;
            $_SESSION['verified']   = 0;
            $_SESSION['eligible']   = 1;
            $_SESSION['flash'][]    = ['type'=>'success','msg'=>'Welcome to RaktSetu! Your account has been created.'];

            $redirect = $_GET['redirect'] ?? (APP_URL . '/donor-dashboard.php');
            header('Location: ' . $redirect);
            exit;
        }
    }

    // ── LOGIN ─────────────────────────────────────────────────
    if ($action === 'login') {
        $email    = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $remember = isset($_POST['remember']);

        $old = ['email' => $email];

        if (!filter_var($email, FILTER_VALIDATE_EMAIL))
            $errors['email'] = 'Enter a valid email.';

        if (empty($password))
            $errors['password'] = 'Password is required.';

        if (empty($errors)) {
            $stmt = getDB()->prepare("
                SELECT id, name, email, password_hash, role, blood_type, city, is_verified, is_eligible
                FROM users WHERE email = ? LIMIT 1
            ");
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if (!$user || !password_verify($password, $user['password_hash'])) {
                $errors['email'] = 'Incorrect email or password.';
            } else {
                // ── Set session ──────────────────────────────
                $_SESSION['user_id']    = $user['id'];
                $_SESSION['user_name']  = $user['name'];
                $_SESSION['user_email'] = $user['email'];
                $_SESSION['user_role']  = $user['role'];
                $_SESSION['blood_type'] = $user['blood_type'];
                $_SESSION['city']       = $user['city'];
                $_SESSION['verified']   = $user['is_verified'];
                $_SESSION['eligible']   = $user['is_eligible'];

                // ── Remember-me cookie (30 days) ──────────────
                if ($remember) {
                    $token = bin2hex(random_bytes(32));
                    $stmt2 = getDB()->prepare("UPDATE users SET remember_token = ? WHERE id = ?");
                    $stmt2->execute([$token, $user['id']]);
                    setcookie(
                        COOKIE_NAME,
                        $token,
                        time() + (COOKIE_DAYS * 86400),
                        '/',
                        '',
                        false,  // Set to true in production (HTTPS)
                        true    // httpOnly — not accessible via JS
                    );
                }

                $_SESSION['flash'][] = ['type'=>'success','msg'=>'Welcome back, ' . htmlspecialchars($user['name']) . '!'];
                $redirect = $_GET['redirect'] ?? (APP_URL . '/donor-dashboard.php');
                header('Location: ' . $redirect);
                exit;
            }
        }
    }
}

require_once 'includes/header.php';
?>

<div class="auth-wrap">
    <div class="auth-card fade-in">

        <!-- Logo -->
        <div class="text-center mb-2">
            <span style="font-size:36px">🩸</span>
            <h1 style="font-size:22px;font-weight:700;color:var(--dark);margin-top:6px">
                <?= $activeTab === 'register' ? 'Create your account' : 'Welcome back' ?>
            </h1>
            <p class="text-muted" style="font-size:13px;margin-top:4px">
                <?= $activeTab === 'register' ? 'Join 18,000+ donors saving lives' : 'Sign in to RaktSetu' ?>
            </p>
        </div>

        <!-- Tabs -->
        <div class="auth-tabs">
            <button class="auth-tab <?= $activeTab==='login' ? 'active' : '' ?>"
                    onclick="switchTab('login')">Sign In</button>
            <button class="auth-tab <?= $activeTab==='register' ? 'active' : '' ?>"
                    onclick="switchTab('register')">Register</button>
        </div>

        <!-- ── LOGIN FORM ──────────────────────────────────── -->
        <form class="auth-form <?= $activeTab==='login' ? 'active' : '' ?>"
              id="loginForm" method="POST" action="auth.php" novalidate>
            <input type="hidden" name="action" value="login">

            <div class="form-group">
                <label class="form-label" for="login_email">Email address</label>
                <input class="form-control <?= isset($errors['email']) && $activeTab==='login' ? 'error' : '' ?>"
                       type="email" id="login_email" name="email"
                       value="<?= htmlspecialchars($old['email'] ?? '') ?>"
                       placeholder="you@example.com" autocomplete="email">
                <?php if (isset($errors['email']) && $activeTab==='login'): ?>
                    <p class="form-error show"><?= $errors['email'] ?></p>
                <?php endif; ?>
            </div>

            <div class="form-group">
                <label class="form-label" for="login_pass">Password</label>
                <div class="search-box" style="padding:0;border-color:<?= isset($errors['password']) ? 'var(--red)' : 'var(--gray-300)' ?>">
                    <input class="form-control" type="password" id="login_pass" name="password"
                           placeholder="Enter your password" autocomplete="current-password"
                           style="border:none;padding:10px 14px">
                    <button type="button" onclick="togglePwd('login_pass')"
                            style="padding:0 12px;background:none;border:none;cursor:pointer;color:var(--gray-500);font-size:14px">👁</button>
                </div>
                <?php if (isset($errors['password']) && $activeTab==='login'): ?>
                    <p class="form-error show"><?= $errors['password'] ?></p>
                <?php endif; ?>
            </div>

            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;font-size:13px">
                <label style="display:flex;align-items:center;gap:6px;cursor:pointer;color:var(--gray-700)">
                    <input type="checkbox" name="remember" style="accent-color:var(--red)">
                    Remember me for 30 days
                </label>
            </div>

            <button type="submit" class="btn btn-red btn-full">Sign In</button>

            <p class="text-center mt-2" style="font-size:13px;color:var(--gray-500)">
                Don't have an account?
                <button type="button" onclick="switchTab('register')"
                        style="background:none;border:none;color:var(--red);cursor:pointer;font-weight:600;font-family:var(--font)">
                    Register free
                </button>
            </p>
        </form>

        <!-- ── REGISTER FORM ───────────────────────────────── -->
        <form class="auth-form <?= $activeTab==='register' ? 'active' : '' ?>"
              id="registerForm" method="POST" action="auth.php"
              enctype="multipart/form-data" novalidate>
            <input type="hidden" name="action" value="register">

            <div class="form-row">
                <div class="form-group">
                    <label class="form-label" for="r_name">Full name *</label>
                    <input class="form-control <?= isset($errors['name']) ? 'error' : '' ?>"
                           type="text" id="r_name" name="name"
                           value="<?= htmlspecialchars($old['name'] ?? '') ?>"
                           placeholder="Arjun Mehta">
                    <p class="form-error <?= isset($errors['name']) ? 'show' : '' ?>"><?= $errors['name'] ?? '' ?></p>
                </div>

                <div class="form-group">
                    <label class="form-label" for="r_phone">Mobile number</label>
                    <input class="form-control <?= isset($errors['phone']) ? 'error' : '' ?>"
                           type="tel" id="r_phone" name="phone"
                           value="<?= htmlspecialchars($old['phone'] ?? '') ?>"
                           placeholder="9876543210">
                    <p class="form-error <?= isset($errors['phone']) ? 'show' : '' ?>"><?= $errors['phone'] ?? '' ?></p>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label" for="r_email">Email address *</label>
                <input class="form-control <?= isset($errors['email']) ? 'error' : '' ?>"
                       type="email" id="r_email" name="email"
                       value="<?= htmlspecialchars($old['email'] ?? '') ?>"
                       placeholder="you@example.com">
                <p class="form-error <?= isset($errors['email']) ? 'show' : '' ?>"><?= $errors['email'] ?? '' ?></p>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label class="form-label" for="r_role">I am a *</label>
                    <select class="form-control" id="r_role" name="role" onchange="handleRoleChange(this.value)">
                        <option value="donor"          <?= ($old['role']??'donor')==='donor'          ? 'selected' : '' ?>>Blood Donor</option>
                        <option value="patient"        <?= ($old['role']??'')==='patient'             ? 'selected' : '' ?>>Patient / Family</option>
                        <option value="hospital_staff" <?= ($old['role']??'')==='hospital_staff'      ? 'selected' : '' ?>>Hospital Staff</option>
                    </select>
                </div>

                <div class="form-group" id="bloodTypeGroup">
                    <label class="form-label" for="r_bt">Blood type *</label>
                    <select class="form-control <?= isset($errors['blood_type']) ? 'error' : '' ?>"
                            id="r_bt" name="blood_type">
                        <option value="">Select…</option>
                        <?php foreach (BLOOD_TYPES as $bt): ?>
                            <option value="<?= $bt ?>" <?= ($old['blood_type']??'')===$bt ? 'selected' : '' ?>><?= $bt ?></option>
                        <?php endforeach; ?>
                    </select>
                    <p class="form-error <?= isset($errors['blood_type']) ? 'show' : '' ?>"><?= $errors['blood_type'] ?? '' ?></p>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label class="form-label" for="r_city">City *</label>
                    <input class="form-control <?= isset($errors['city']) ? 'error' : '' ?>"
                           type="text" id="r_city" name="city"
                           value="<?= htmlspecialchars($old['city'] ?? '') ?>"
                           placeholder="New Delhi">
                    <p class="form-error <?= isset($errors['city']) ? 'show' : '' ?>"><?= $errors['city'] ?? '' ?></p>
                </div>
                <div class="form-group">
                    <label class="form-label" for="r_state">State</label>
                    <input class="form-control" type="text" id="r_state" name="state"
                           value="<?= htmlspecialchars($old['state'] ?? '') ?>"
                           placeholder="Delhi">
                </div>
            </div>

            <div class="form-group">
                <label class="form-label" for="r_pass">Password *</label>
                <div class="search-box" style="padding:0;border-color:<?= isset($errors['password']) ? 'var(--red)' : 'var(--gray-300)' ?>">
                    <input class="form-control" type="password" id="r_pass" name="password"
                           placeholder="Min 8 characters"
                           style="border:none;padding:10px 14px" oninput="checkStrength(this.value)">
                    <button type="button" onclick="togglePwd('r_pass')"
                            style="padding:0 12px;background:none;border:none;cursor:pointer;color:var(--gray-500);font-size:14px">👁</button>
                </div>
                <div class="strength-bar"><div class="strength-fill" id="strengthFill"></div></div>
                <p class="form-error <?= isset($errors['password']) ? 'show' : '' ?>"><?= $errors['password'] ?? '' ?></p>
            </div>

            <div class="form-group">
                <label class="form-label" for="r_confirm">Confirm password *</label>
                <input class="form-control <?= isset($errors['confirm_password']) ? 'error' : '' ?>"
                       type="password" id="r_confirm" name="confirm_password" placeholder="Repeat password">
                <p class="form-error <?= isset($errors['confirm_password']) ? 'show' : '' ?>"><?= $errors['confirm_password'] ?? '' ?></p>
            </div>

            <div class="form-group" id="idProofGroup">
                <label class="form-label" for="r_id">ID Proof <span class="text-muted">(optional — JPG/PNG/PDF, max 5 MB)</span></label>
                <input class="form-control" type="file" id="r_id" name="id_proof" accept=".jpg,.jpeg,.png,.pdf">
                <p class="form-hint">Aadhar, PAN, or Voter ID helps us verify your account faster.</p>
                <p class="form-error <?= isset($errors['id_proof']) ? 'show' : '' ?>"><?= $errors['id_proof'] ?? '' ?></p>
            </div>

            <button type="submit" class="btn btn-red btn-full" onclick="return validateRegister()">
                Create Account — Join RaktSetu
            </button>

            <p class="text-center mt-2" style="font-size:12px;color:var(--gray-500);line-height:1.5">
                By registering you agree to donate voluntarily. Your data is used only to connect you with emergencies.
            </p>
        </form>

    </div>
</div>

<script src="<?= APP_URL ?>/js/validate.js"></script>
<script>
function switchTab(tab) {
    document.querySelectorAll('.auth-tab').forEach((t,i) => t.classList.toggle('active', (i===0&&tab==='login')||(i===1&&tab==='register')));
    document.querySelectorAll('.auth-form').forEach(f => f.classList.remove('active'));
    document.getElementById(tab === 'login' ? 'loginForm' : 'registerForm').classList.add('active');
    history.replaceState(null,'', tab==='register' ? '?tab=register' : '?');
}
function togglePwd(id) {
    const el = document.getElementById(id);
    el.type = el.type === 'password' ? 'text' : 'password';
}
function handleRoleChange(role) {
    const btg = document.getElementById('bloodTypeGroup');
    const bt  = document.getElementById('r_bt');
    if (role === 'donor') { btg.style.display = ''; bt.required = true; }
    else                  { btg.style.display = 'none'; bt.required = false; bt.value = ''; }
}
function checkStrength(val) {
    let score = 0;
    if (val.length >= 8)  score++;
    if (/[A-Z]/.test(val)) score++;
    if (/[0-9]/.test(val)) score++;
    if (/[^A-Za-z0-9]/.test(val)) score++;
    const fill = document.getElementById('strengthFill');
    const colors = ['#ef4444','#f97316','#eab308','#22c55e'];
    const widths  = ['25%','50%','75%','100%'];
    fill.style.width      = widths[score-1] || '0%';
    fill.style.background = colors[score-1] || '#ef4444';
}
</script>

<?php require_once 'includes/footer.php'; ?>
