<?php
// Partial: force the browser Back button to always return to the dashboard.
//
// Included (defensively) from every role chrome partial — sidebars, topbars
// and bottom navs. The static guard below ensures the <script> is emitted
// only once per page even when a page renders several chrome partials.
//
// Requires: DASHBOARD_URL (connection/config.php). The unified /dashboard.php
// router resolves to the correct role dashboard, so one URL covers all roles.
if (defined('GJC_BACK_GUARD_EMITTED')) {
    return;
}
define('GJC_BACK_GUARD_EMITTED', true);
?>
<script>
(function () {
    "use strict";

    var DASHBOARD_URL = <?= json_encode(DASHBOARD_URL, JSON_UNESCAPED_SLASHES) ?>;

    // Normalise a URL's path so "/genpay/dashboard" and
    // "/genpay/dashboard.php" compare equal (.htaccess strips the .php).
    function normPath(url) {
        try {
            var p = new URL(url, window.location.href).pathname;
            return p.replace(/\.php$/i, "").replace(/\/+$/, "");
        } catch (e) {
            return url;
        }
    }

    var onDashboard = normPath(window.location.href) === normPath(DASHBOARD_URL);

    // Seed a history entry so the first Back press fires popstate on this
    // page instead of unwinding out of the app.
    history.pushState(null, document.title, window.location.href);

    window.addEventListener("popstate", function () {
        if (onDashboard) {
            // Already home: re-seed so Back can't reach the pre-auth/login page.
            history.pushState(null, document.title, window.location.href);
        } else {
            // replace() (not assign) so a subsequent Back doesn't bounce
            // between the page and the dashboard.
            window.location.replace(DASHBOARD_URL);
        }
    });
})();
</script>
