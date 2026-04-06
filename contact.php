<?php
// ============================================================
//  RaktSetu — contact.php
//  Demonstrates: PHP mail(), filter_var(), htmlspecialchars(),
//  trim(), server-side validation, DB insert, JS validation
// ============================================================

require_once 'config/db.php';
require_once 'includes/auth-guard.php';

$pageTitle = 'Contact Us · ' . APP_NAME;

$errors  = [];
$success = false;
$old     = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $name    = trim($_POST['name']    ?? '');
    $email   = trim($_POST['email']   ?? '');
    $subject = trim($_POST['subject'] ?? '');
    $message = trim($_POST['message'] ?? '');

    $old = compact('name','email','subject','message');

    // ── Server-side validation using PHP built-in functions ─
    if (strlen($name) < 2)
        $errors['name'] = 'Please enter your full name (min 2 characters).';

    if (!filter_var($email, FILTER_VALIDATE_EMAIL))
        $errors['email'] = 'Please enter a valid email address.';

    if (strlen($message) < 20)
        $errors['message'] = 'Message is too short (minimum 20 characters).';

    if (strlen($message) > 2000)
        $errors['message'] = 'Message is too long (maximum 2000 characters).';

    if (empty($errors)) {
        // Sanitize before storing / emailing
        $safeName    = htmlspecialchars($name,    ENT_QUOTES, 'UTF-8');
        $safeSubject = htmlspecialchars($subject, ENT_QUOTES, 'UTF-8');
        $safeMsg     = htmlspecialchars($message, ENT_QUOTES, 'UTF-8');

        // ── Store in database (Create operation) ─────────────
        $db = getDB();
        $db->prepare("
            INSERT INTO contact_messages (name, email, subject, message)
            VALUES (?, ?, ?, ?)
        ")->execute([$safeName, $email, $safeSubject, $message]);

        // ── Send email via PHP mail() ─────────────────────────
        $to      = 'admin@raktsetu.org';
        $subject_line = "RaktSetu Contact: " . $safeSubject;
        $body    = "Name: $safeName\nEmail: $email\n\nMessage:\n$safeMsg";
        $headers = "From: noreply@raktsetu.org\r\nReply-To: $email\r\n";

        @mail($to, $subject_line, $body, $headers); // @ suppresses in localhost

        $success = true;
        $old     = [];
    }
}

require_once 'includes/header.php';
?>

<div class="container" style="padding-top:40px;padding-bottom:60px">

    <div style="max-width:700px;margin:0 auto 32px;text-align:center">
        <h1 style="font-size:28px;font-weight:700;color:var(--dark)">Contact Us</h1>
        <p class="text-muted">Questions, partnerships, or need help with a request? We respond within 24 hours.</p>
    </div>

    <div class="contact-grid">

        <!-- ── CONTACT INFO ─────────────────────────────── -->
        <div class="card">
            <p class="card-title">Get in touch</p>

            <?php
            $contactItems = [
                ['📍','Address','AIIMS Campus, Ansari Nagar, New Delhi — 110029'],
                ['📞','Phone','1800-11-2 (Toll Free, 24x7)'],
                ['✉️','Email','help@raktsetu.org'],
                ['⏰','Hours','24 × 7 — emergencies never stop'],
            ];
            foreach ($contactItems as [$icon,$label,$value]):
            ?>
            <div class="contact-info-item">
                <span class="ci-icon"><?= $icon ?></span>
                <div>
                    <div class="ci-label"><?= $label ?></div>
                    <div class="ci-value"><?= htmlspecialchars($value) ?></div>
                </div>
            </div>
            <?php endforeach; ?>

            <div style="margin-top:20px;padding:16px;background:var(--red-light);border-radius:var(--radius-md)">
                <p style="font-size:13px;font-weight:700;color:var(--red);margin-bottom:4px">🚨 Blood Emergency?</p>
                <p style="font-size:12px;color:var(--red)">Don't use this form. <a href="<?= APP_URL ?>/post-request.php" style="font-weight:700">Post an emergency request</a> for an immediate response.</p>
            </div>
        </div>

        <!-- ── CONTACT FORM ──────────────────────────────── -->
        <div class="card">
            <?php if ($success): ?>
                <div style="text-align:center;padding:24px 0" class="fade-in">
                    <div style="font-size:48px">✅</div>
                    <h2 style="font-size:20px;font-weight:700;color:var(--dark);margin:12px 0 8px">Message sent!</h2>
                    <p class="text-muted">We'll reply to <strong><?= htmlspecialchars($_POST['email'] ?? '') ?></strong> within 24 hours.</p>
                    <a href="<?= APP_URL ?>/index.php" class="btn btn-red" style="margin-top:20px">Back to home</a>
                </div>
            <?php else: ?>
                <p class="card-title">Send us a message</p>

                <form method="POST" action="contact.php" novalidate onsubmit="return validateContact()">

                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label" for="c_name">Full name *</label>
                            <input class="form-control <?= isset($errors['name']) ? 'error' : '' ?>"
                                   type="text" id="c_name" name="name"
                                   value="<?= htmlspecialchars($old['name'] ?? (isLoggedIn() ? $_SESSION['user_name'] : '')) ?>"
                                   placeholder="Arjun Mehta">
                            <p class="form-error <?= isset($errors['name']) ? 'show' : '' ?>" id="err_name"><?= $errors['name'] ?? '' ?></p>
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="c_email">Email *</label>
                            <input class="form-control <?= isset($errors['email']) ? 'error' : '' ?>"
                                   type="email" id="c_email" name="email"
                                   value="<?= htmlspecialchars($old['email'] ?? (isLoggedIn() ? $_SESSION['user_email'] : '')) ?>"
                                   placeholder="you@example.com">
                            <p class="form-error <?= isset($errors['email']) ? 'show' : '' ?>" id="err_email"><?= $errors['email'] ?? '' ?></p>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="c_subject">Subject</label>
                        <select class="form-control" id="c_subject" name="subject">
                            <option value="">Select a topic…</option>
                            <option value="General enquiry"      <?= ($old['subject']??'')==='General enquiry'       ? 'selected':'' ?>>General enquiry</option>
                            <option value="Partnership"          <?= ($old['subject']??'')==='Partnership'            ? 'selected':'' ?>>Hospital / NGO partnership</option>
                            <option value="Technical issue"      <?= ($old['subject']??'')==='Technical issue'        ? 'selected':'' ?>>Technical issue</option>
                            <option value="Account help"         <?= ($old['subject']??'')==='Account help'           ? 'selected':'' ?>>Account help</option>
                            <option value="Feedback"             <?= ($old['subject']??'')==='Feedback'               ? 'selected':'' ?>>Feedback / suggestion</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="c_message">Message *</label>
                        <textarea class="form-control <?= isset($errors['message']) ? 'error' : '' ?>"
                                  id="c_message" name="message"
                                  data-maxlen="2000"
                                  rows="6"
                                  placeholder="Write your message here…"><?= htmlspecialchars($old['message'] ?? '') ?></textarea>
                        <div class="char-counter">0 / 2000</div>
                        <p class="form-error <?= isset($errors['message']) ? 'show' : '' ?>" id="err_message"><?= $errors['message'] ?? '' ?></p>
                    </div>

                    <button type="submit" class="btn btn-red btn-full">Send Message</button>
                </form>
            <?php endif; ?>
        </div>

    </div>
</div>

<script src="<?= APP_URL ?>/js/validate.js"></script>
<?php require_once 'includes/footer.php'; ?>
