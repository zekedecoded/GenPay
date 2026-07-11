<?php
require_once __DIR__ . '/../connection/config.php';
require_once __DIR__ . '/../connection/pdo.php';
require_once __DIR__ . '/../connection/app.php';
require_once __DIR__ . '/../connection/audit_logger.php';

gjc_require_role(['student']);

$currentUser = gjc_current_user($db);
$wallet = gjc_student_wallet($db, $currentUser['id']);
$notice = '';
$error = '';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $action = (string) ($_POST['profile_action'] ?? '');
    $columns = gjc_table_columns($db, 'users');
    $idColumn = gjc_column($db, 'users', ['id', 'userID']);

    if (!gjc_csrf_verify()) {
        $error = 'Security check failed. Please reload the page and try again.';
    } elseif (!$idColumn) {
        $error = 'Profile cannot be updated because the users table ID column was not found.';
    } elseif ($action === 'profile') {
        $firstName = trim((string) ($_POST['first_name'] ?? ''));
        $lastName = trim((string) ($_POST['last_name'] ?? ''));
        $phone = trim((string) ($_POST['phone'] ?? ''));

        if ($firstName === '' || $lastName === '') {
            $error = 'First name and last name are required.';
        } else {
            $updates = [];
            $values = [];

            foreach ([
                'first_name' => $firstName,
                'last_name' => $lastName,
                'name' => trim($firstName . ' ' . $lastName),
                'phone' => $phone,
            ] as $column => $value) {
                if (in_array($column, $columns, true)) {
                    $updates[] = "{$column} = ?";
                    $values[] = $value;
                }
            }

            if ($updates) {
                $values[] = $currentUser['id'];
                $stmt = $db->prepare('UPDATE users SET ' . implode(', ', $updates) . " WHERE {$idColumn} = ?");
                $stmt->execute($values);
                $notice = 'Profile updated successfully!';
            }
        }
    } elseif ($action === 'password') {
        $currentPassword = (string) ($_POST['current_password'] ?? '');
        $newPassword = (string) ($_POST['new_password'] ?? '');
        $confirmPassword = (string) ($_POST['confirm_password'] ?? '');
        $storedPassword = (string) ($currentUser['raw']['password'] ?? '');

        $validCurrentPassword = $storedPassword !== ''
            && (password_verify($currentPassword, $storedPassword) || hash_equals($storedPassword, $currentPassword));

        if (!in_array('password', $columns, true)) {
            $error = 'Password cannot be updated because the password column was not found.';
        } elseif (!$validCurrentPassword) {
            $error = 'Current password is incorrect.';
        } elseif (strlen($newPassword) < 6) {
            $error = 'New password must be at least 6 characters.';
        } elseif ($newPassword !== $confirmPassword) {
            $error = 'New passwords do not match.';
        } else {
            $stmt = $db->prepare("UPDATE users SET password = ? WHERE {$idColumn} = ?");
            $stmt->execute([password_hash($newPassword, PASSWORD_DEFAULT), $currentUser['id']]);
            logAudit(
                $db,
                (int) $currentUser['id'],
                gjc_current_role(),
                'PASSWORD_CHANGE',
                'users',
                ['password' => 'changed_by_student_profile'],
                ['password' => 'changed_by_student_profile']
            );
            $notice = 'Password updated successfully!';
        }
    }

    $currentUser = gjc_current_user($db);
}

$rawUser = $currentUser['raw'] ?? [];
$firstName = (string) ($rawUser['first_name'] ?? '');
$lastName = (string) ($rawUser['last_name'] ?? '');

if ($firstName === '' && $lastName === '') {
    $nameParts = preg_split('/\s+/', trim($currentUser['name']), 2);
    $firstName = $nameParts[0] ?? '';
    $lastName = $nameParts[1] ?? '';
}

$studentName = $currentUser['name'];
$studentInitial = strtoupper(substr($studentName, 0, 1));
$email = (string) ($currentUser['email'] ?? '');
$phone = (string) ($rawUser['phone'] ?? '');
$walletBalance = (float) $wallet['balance'];
$createdAt = (string) ($rawUser['created_at'] ?? '');
$memberSince = $createdAt !== '' ? date('F Y', strtotime($createdAt)) : 'N/A';
$profileImg = (string) ($rawUser['profile_img'] ?? '');
$profilePhotoUrl = ($profileImg !== '') ? (BASE_URL . '/' . ltrim($profileImg, '/')) : '';

// Real school-issued ID (GJC2026-0001); the padded userID is only a fallback
// for accounts that never got a student_info row.
$studentID = 'GJC-' . str_pad((string) $currentUser['id'], 5, '0', STR_PAD_LEFT);
if (gjc_table_exists($db, 'student_info')) {
    $sidStmt = $db->prepare("SELECT studentID FROM student_info WHERE userID = ? LIMIT 1");
    $sidStmt->execute([(int) $currentUser['id']]);
    $realID = trim((string) $sidStmt->fetchColumn());
    if ($realID !== '') {
        $studentID = $realID;
    }
}

// Live wallet controls (parent freeze / daily limit) instead of hardcoded text.
$isFrozen = false;
$dailyLimit = 0.0;
if ($wallet['id'] > 0 && $wallet['source'] === 'student_wallets') {
    $wcStmt = $db->prepare("SELECT is_frozen, daily_spend_limit FROM student_wallets WHERE id = ?");
    $wcStmt->execute([$wallet['id']]);
    if ($wc = $wcStmt->fetch(PDO::FETCH_ASSOC)) {
        $isFrozen = (int) $wc['is_frozen'] === 1;
        $dailyLimit = (float) $wc['daily_spend_limit'];
    }
}
$accountStatus = $isFrozen ? 'Frozen' : ucfirst((string) ($rawUser['status'] ?? 'Active'));
$transactionsEnabled = $wallet['id'] > 0 && !$isFrozen;

$e = static fn($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$currentPage = 'profile';
$csrfToken = gjc_csrf_token();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <link rel="icon" type="image/png" sizes="32x32" href="<?= ICONS_URL ?>/gp_logo.png">
    <link rel="icon" type="image/png" sizes="192x192" href="<?= ICONS_URL ?>/gp_logo.png">
    <link rel="apple-touch-icon" sizes="180x180" href="<?= ICONS_URL ?>/gp_logo.png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile | GenPay</title>

    <link rel="stylesheet" href="<?= CSS_URL ?>/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=IBM+Plex+Mono:wght@500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= CSS_URL ?>/student_dashboard.css?v=8">
    <link rel="stylesheet" href="<?= CSS_URL ?>/student_profile.css?v=2">
</head>

<body class="sd-body">

    <div class="sd-layout">

        <?php require __DIR__ . '/../includes/partials/sidebar_student.php'; ?>

        <main class="sd-main">

            <header class="sd-topbar">
                <div class="sd-topbar-greet">
                    <h1>My Profile</h1>
                    <p>Manage your student account details, status, and password security.</p>
                </div>
                <div class="sd-topbar-tools">
                    <div class="sd-avatar" id="topbarAvatar" style="<?= $profilePhotoUrl ? 'overflow:hidden;' : '' ?>">
                        <?php if ($profilePhotoUrl): ?>
                            <img id="topbarAvatarImg" src="<?= $e($profilePhotoUrl) ?>" alt=""
                                 style="width:100%;height:100%;object-fit:cover;display:block;">
                        <?php else: ?>
                            <?= $e($studentInitial) ?>
                        <?php endif; ?>
                    </div>
                </div>
            </header>

            <div class="sd-content">

                <?php if ($notice): ?>
                <div class="pf-alert">
                    <i class="fa-solid fa-circle-check"></i>
                    <?= $e($notice) ?>
                </div>
                <?php endif; ?>

                <?php if ($error): ?>
                <div class="pf-alert is-error">
                    <i class="fa-solid fa-circle-exclamation"></i>
                    <?= $e($error) ?>
                </div>
                <?php endif; ?>

                <!-- Hero -->
                <section class="pf-hero">
                    <div class="pf-hero-left">
                        <div class="pf-avatar-wrap">
                            <div class="pf-avatar" id="avatarCircle">
                                <?php if ($profilePhotoUrl): ?>
                                    <img id="avatarImg" src="<?= $e($profilePhotoUrl) ?>" alt="Profile Photo">
                                <?php else: ?>
                                    <span id="avatarInitial"><?= $e($studentInitial) ?></span>
                                <?php endif; ?>
                            </div>
                            <label for="photoInput" class="pf-avatar-edit" title="Change photo">
                                <i class="fa-solid fa-camera"></i>
                            </label>
                            <input type="file" id="photoInput" accept="image/jpeg,image/png,image/webp" style="display:none;">
                        </div>

                        <div class="pf-identity">
                            <span>Student Account</span>
                            <h2><?= $e($studentName) ?></h2>
                            <p><?= $e($email) ?> &middot; <?= $e($studentID) ?></p>
                            <span class="sd-role-badge">STUDENT</span>
                            <div class="pf-photo-msg" id="photoMsg"></div>
                        </div>
                    </div>

                    <div class="pf-wallet-box">
                        <span>Wallet Balance</span>
                        <h3><?= gjc_gc_amount($walletBalance) ?> GC</h3>
                        <p>&#8776; <?= gjc_money($walletBalance) ?> &middot; Member since <?= $e($memberSince) ?></p>
                    </div>
                </section>

                <!-- Profile form + account status -->
                <section class="pf-grid">

                    <div class="sd-panel">
                        <div class="sd-panel-head">
                            <div>
                                <h3>Update Profile</h3>
                                <p>Edit your personal account information.</p>
                            </div>
                        </div>

                        <form method="POST" class="pf-form">
                            <input type="hidden" name="profile_action" value="profile">
                            <input type="hidden" name="csrf_token" value="<?= $e($csrfToken) ?>">

                            <div class="pf-form-grid">
                                <div class="pf-field">
                                    <label>First Name</label>
                                    <input type="text" name="first_name" value="<?= $e($firstName) ?>" required>
                                </div>

                                <div class="pf-field">
                                    <label>Last Name</label>
                                    <input type="text" name="last_name" value="<?= $e($lastName) ?>" required>
                                </div>
                            </div>

                            <div class="pf-field">
                                <label>Phone Number</label>
                                <input type="text" name="phone" value="<?= $e($phone) ?>">
                            </div>

                            <div class="pf-field">
                                <label>Email Address</label>
                                <input type="email" value="<?= $e($email) ?>" disabled>
                                <small>Email cannot be changed. Contact Admin if needed.</small>
                            </div>

                            <button type="submit" class="pf-btn">
                                <i class="fa-solid fa-floppy-disk me-1"></i> Save Changes
                            </button>
                        </form>
                    </div>

                    <div class="sd-panel">
                        <div class="sd-panel-head">
                            <div>
                                <h3>Account Status</h3>
                                <p>Current access and transaction settings.</p>
                            </div>
                        </div>

                        <div class="pf-status-list">
                            <div>
                                <span>Account Status</span>
                                <strong class="pf-pill <?= $isFrozen ? 'red' : 'green' ?>"><?= $e($accountStatus) ?></strong>
                            </div>

                            <div>
                                <span>Transactions</span>
                                <strong class="pf-pill <?= $transactionsEnabled ? 'green' : 'gold' ?>">
                                    <?= $transactionsEnabled ? 'Enabled' : ($isFrozen ? 'Paused' : 'Wallet Pending') ?>
                                </strong>
                            </div>

                            <div>
                                <span>Spending Limit</span>
                                <strong class="pf-pill <?= $dailyLimit > 0 ? 'gold' : 'gray' ?>">
                                    <?= $dailyLimit > 0 ? '&#8369;' . number_format($dailyLimit, 2) . ' / day' : 'No Limit' ?>
                                </strong>
                            </div>
                        </div>

                        <div class="pf-note">
                            Some account settings are managed by the system administrator or your parent/guardian.
                        </div>
                    </div>

                </section>

                <!-- Change password -->
                <section class="sd-panel">
                    <div class="sd-panel-head">
                        <div>
                            <h3>Change Password</h3>
                            <p>Update your login password for better account security.</p>
                        </div>
                    </div>

                    <form method="POST" class="pf-form">
                        <input type="hidden" name="profile_action" value="password">
                        <input type="hidden" name="csrf_token" value="<?= $e($csrfToken) ?>">

                        <div class="pf-field">
                            <label>Current Password</label>
                            <input type="password" name="current_password" autocomplete="current-password" required>
                        </div>

                        <div class="pf-form-grid">
                            <div class="pf-field">
                                <label>New Password</label>
                                <input type="password" name="new_password" minlength="6" autocomplete="new-password" required>
                            </div>

                            <div class="pf-field">
                                <label>Confirm New Password</label>
                                <input type="password" name="confirm_password" minlength="6" autocomplete="new-password" required>
                            </div>
                        </div>

                        <button type="submit" class="pf-btn">
                            <i class="fa-solid fa-shield-halved me-1"></i> Update Password
                        </button>
                    </form>
                </section>

            </div>

        </main>

    </div>

    <?php require __DIR__ . '/../includes/partials/bottom_nav_student.php'; ?>

    <script src="<?= JS_URL ?>/bootstrap.bundle.min.js"></script>

    <script>
    /* ── Profile photo upload ─────────────────────────────────── */
    const PHOTO_API = '<?= BASE_URL ?>/api/profile_photo.php';

    document.getElementById('photoInput').addEventListener('change', async function() {
        const file = this.files[0];
        if (!file) return;

        const msg = document.getElementById('photoMsg');
        msg.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Uploading...';
        msg.style.color = 'rgba(255,255,255,.7)';

        const fd = new FormData();
        fd.append('action', 'upload');
        fd.append('photo', file);

        try {
            const res  = await fetch(PHOTO_API, {method: 'POST', body: fd, credentials: 'same-origin'});
            const data = await res.json();
            if (data.success) {
                const circle = document.getElementById('avatarCircle');
                let img = document.getElementById('avatarImg');
                const initial = document.getElementById('avatarInitial');
                if (initial) initial.remove();
                if (!img) {
                    img = document.createElement('img');
                    img.id = 'avatarImg';
                    img.alt = 'Profile Photo';
                    circle.appendChild(img);
                }
                img.src = data.photo_url;

                // Also sync topbar avatar
                const topbar = document.getElementById('topbarAvatar');
                if (topbar) {
                    topbar.style.overflow = 'hidden';
                    let tImg = document.getElementById('topbarAvatarImg');
                    if (!tImg) {
                        topbar.textContent = '';
                        tImg = document.createElement('img');
                        tImg.id = 'topbarAvatarImg';
                        tImg.alt = '';
                        tImg.style.cssText = 'width:100%;height:100%;object-fit:cover;display:block;';
                        topbar.appendChild(tImg);
                    }
                    tImg.src = data.photo_url;
                }

                msg.innerHTML = '<i class="fa-solid fa-check"></i> Photo updated.';
                msg.style.color = '#4ade80';
                setTimeout(() => { msg.innerHTML = ''; }, 3000);
            } else {
                msg.innerHTML = data.error || 'Upload failed.';
                msg.style.color = '#fca5a5';
            }
        } catch(err) {
            msg.innerHTML = 'Network error. Please try again.';
            msg.style.color = '#fca5a5';
        }
        this.value = '';
    });
    </script>

</body>

</html>
