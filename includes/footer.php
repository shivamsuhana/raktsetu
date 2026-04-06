<!-- ============================================================
     RaktSetu — Footer
     ============================================================ -->
<footer class="site-footer">
    <div class="footer-inner">
        <div class="footer-grid">
            <div class="footer-brand">
                <span class="logo-drop">🩸</span>
                <span class="logo-text">RaktSetu</span>
                <p>Connecting blood donors with those in need, saving lives one donation at a time.</p>
                <p class="helpline">Emergency helpline: <a href="tel:1800112">1800-11-2</a></p>
            </div>

            <div class="footer-col">
                <h4>Quick links</h4>
                <ul>
                    <li><a href="<?= APP_URL ?>/requests.php">Live Emergencies</a></li>
                    <li><a href="<?= APP_URL ?>/donor-search.php">Find a Donor</a></li>
                    <li><a href="<?= APP_URL ?>/post-request.php">Post a Request</a></li>
                    <li><a href="<?= APP_URL ?>/contact.php">Contact Us</a></li>
                </ul>
            </div>

            <div class="footer-col">
                <h4>Blood types</h4>
                <div class="bt-grid-footer">
                    <?php foreach (['A+','A-','B+','B-','O+','O-','AB+','AB-'] as $bt): ?>
                        <a href="<?= APP_URL ?>/donor-search.php?bt=<?= urlencode($bt) ?>" class="bt-tag"><?= $bt ?></a>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <div class="footer-bottom">
            <p>© <?= date('Y') ?> RaktSetu · Built for Web Technologies (23CSE404)</p>
        </div>
    </div>
</footer>

<script src="<?= APP_URL ?>/js/main.js"></script>
<script src="<?= APP_URL ?>/js/charts.js"></script>

<script>
function toggleUserMenu() {
    document.getElementById('userDropdown').classList.toggle('open');
}
function toggleMobileNav() {
    document.getElementById('mobileNav').classList.toggle('open');
}
document.addEventListener('click', function(e) {
    const menu = document.getElementById('userMenu');
    if (menu && !menu.contains(e.target)) {
        const d = document.getElementById('userDropdown');
        if (d) d.classList.remove('open');
    }
});
// Auto-dismiss flash messages
document.querySelectorAll('.flash').forEach(el => {
    setTimeout(() => el.style.opacity = '0', 4000);
    setTimeout(() => el.remove(), 4500);
});
</script>
</body>
</html>
