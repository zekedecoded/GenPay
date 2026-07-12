<?php
require_once __DIR__ . '/../connection/config.php';
require_once __DIR__ . '/../connection/pdo.php';
require_once __DIR__ . '/../connection/app.php';

gjc_require_role(['student']);

$currentUser = gjc_current_user($db);
$studentName = $currentUser['name'];
$credit      = gjc_student_waiver_credit($db, (int) $currentUser['id']);

$e = static fn($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$currentPage = 'fees';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <link rel="icon" type="image/png" sizes="32x32" href="<?= ICONS_URL ?>/gp_logo.png">
    <link rel="icon" type="image/png" sizes="192x192" href="<?= ICONS_URL ?>/gp_logo.png">
    <link rel="apple-touch-icon" sizes="180x180" href="<?= ICONS_URL ?>/gp_logo.png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fee Waiver Credit | GenPay</title>

    <link rel="stylesheet" href="<?= CSS_URL ?>/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=IBM+Plex+Mono:wght@500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= CSS_URL ?>/student_dashboard.css?v=12">
    <link rel="stylesheet" href="<?= CSS_URL ?>/student_profile.css?v=2">
</head>

<body class="sd-body">

    <div class="sd-layout">

        <?php require __DIR__ . '/../includes/partials/sidebar_student.php'; ?>

        <main class="sd-main">

            <?php
            $topbarTitle = 'Fee Waiver Credit';
            $topbarSubtitle = 'A credit finance applies toward your tuition, once your signed waiver is on file.';
            require __DIR__ . '/../includes/partials/topbar_student.php';
            ?>

            <div class="sd-content">

                <?php if ($credit['status'] === 'pending'): ?>
                <div class="pf-alert">
                    <i class="fa-solid fa-hourglass-half"></i>
                    A Fee Waiver Credit of <?= gjc_money($credit['amount']) ?> is awaiting the signed waiver upload by finance.
                </div>
                <?php endif; ?>

                <section class="sd-stats">
                    <div class="sd-stat">
                        <div class="sd-stat-top">
                            <span>Fee Waiver Credit</span>
                            <span class="sd-stat-icon is-txns"><i class="fa-solid fa-hand-holding-dollar"></i></span>
                        </div>
                        <?php if ($credit['status'] === 'posted'): ?>
                            <h2 class="sd-num"><?= gjc_money($credit['amount']) ?></h2>
                            <p>Confirmed and on file with finance.</p>
                            <?php if ($credit['waiver_file']): ?>
                            <p><a href="<?= ADMIN_URL ?>/doc.php?f=<?= urlencode($credit['waiver_file']) ?>" onclick="return gjcViewWaiver(this.href);">View signed waiver &rarr;</a></p>
                            <?php endif; ?>
                        <?php elseif ($credit['status'] === 'pending'): ?>
                            <h2 class="sd-num">Pending</h2>
                            <p>Awaiting the signed waiver upload by finance.</p>
                        <?php else: ?>
                            <h2 class="sd-num">&mdash;</h2>
                            <p>No Fee Waiver Credit has been requested for you yet.</p>
                        <?php endif; ?>
                    </div>
                </section>

            </div>
        </main>
    </div>

    <!-- Signed Waiver Viewer (inline, no new tab/window) -->
    <div class="modal fade" id="gjcWaiverModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content" style="border-radius:16px;border:none;overflow:hidden">
                <div class="modal-header border-0" style="padding:16px 20px">
                    <h5 class="modal-title fw-bold" style="font-size:15px">
                        <i class="fa-solid fa-file-lines me-2"></i>Signed Waiver
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" style="padding:0">
                    <iframe id="gjcWaiverFrame" src="" style="width:100%;height:70vh;border:0;display:block"></iframe>
                </div>
            </div>
        </div>
    </div>

    <script src="<?= JS_URL ?>/bootstrap.bundle.min.js"></script>
    <script>
    // Show the signed waiver inline in a modal instead of opening a new tab/window.
    function gjcViewWaiver(url) {
        document.getElementById('gjcWaiverFrame').src = url;
        new bootstrap.Modal(document.getElementById('gjcWaiverModal')).show();
        return false;
    }
    document.getElementById('gjcWaiverModal').addEventListener('hidden.bs.modal', function () {
        document.getElementById('gjcWaiverFrame').src = '';
    });
    </script>
</body>
</html>
