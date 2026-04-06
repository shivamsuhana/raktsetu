<?php
// ============================================================
//  RaktSetu — 404.php  (Custom Error Page)
// ============================================================

http_response_code(404);
require_once 'config/db.php';
require_once 'includes/auth-guard.php';

$pageTitle = '404 — Page Not Found · ' . APP_NAME;
require_once 'includes/header.php';
?>

<div style="min-height:calc(100vh - var(--nav-h));display:flex;align-items:center;justify-content:center;padding:40px 20px">
    <div style="text-align:center;max-width:480px">
        <div style="font-size:72px;margin-bottom:16px">🩸</div>
        <h1 style="font-size:80px;font-weight:900;color:var(--red);line-height:1;letter-spacing:-4px">404</h1>
        <h2 style="font-size:22px;font-weight:700;color:var(--dark);margin-top:8px">Page not found</h2>
        <p style="color:var(--gray-500);margin-top:10px;line-height:1.7">
            The page you're looking for doesn't exist, or may have been moved.
            In an emergency, please don't waste time — go back to the live board.
        </p>
        <div style="display:flex;gap:12px;justify-content:center;margin-top:28px;flex-wrap:wrap">
            <a href="<?= APP_URL ?>/index.php"    class="btn btn-red">Back to Home</a>
            <a href="<?= APP_URL ?>/requests.php" class="btn btn-outline">Live Requests</a>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
