<?php
// Partial: Logout confirmation modal — included by every sidebar that has
// a logout link (admin/student/merchant, including the inline duplicate
// sidebars). Relies on BASE_URL from connection/config.php already being
// loaded by the including page.
?>
<div class="logout-modal-overlay" id="logoutModalOverlay">
    <div class="logout-modal" role="dialog" aria-modal="true" aria-labelledby="logoutModalTitle" onclick="event.stopPropagation();">
        <div class="logout-modal-icon"><i class="fa-solid fa-arrow-right-from-bracket"></i></div>
        <h3 class="logout-modal-title" id="logoutModalTitle">Log out?</h3>
        <p class="logout-modal-sub">You'll need to sign in again to access your account.</p>
        <div class="logout-modal-actions">
            <button type="button" class="logout-modal-cancel" onclick="closeLogoutModal()">Cancel</button>
            <a href="<?= BASE_URL ?>/logout.php" class="logout-modal-confirm">Log out</a>
        </div>
    </div>
</div>
<script>
function openLogoutModal(e) {
    if (e) e.preventDefault();
    var overlay = document.getElementById('logoutModalOverlay');
    if (!overlay) return;
    overlay.classList.add('is-open');
    document.addEventListener('keydown', logoutModalEscHandler);
}
function closeLogoutModal() {
    var overlay = document.getElementById('logoutModalOverlay');
    if (!overlay) return;
    overlay.classList.remove('is-open');
    document.removeEventListener('keydown', logoutModalEscHandler);
}
function logoutModalEscHandler(e) {
    if (e.key === 'Escape') closeLogoutModal();
}
document.addEventListener('DOMContentLoaded', function () {
    var overlay = document.getElementById('logoutModalOverlay');
    if (overlay) {
        overlay.addEventListener('click', function () { closeLogoutModal(); });
    }
});
</script>
