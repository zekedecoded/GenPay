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

    if (!$idColumn) {
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
$studentID = (string) ($rawUser['school_id'] ?? ('GJC-' . str_pad((string) $currentUser['id'], 5, '0', STR_PAD_LEFT)));
$email = (string) ($currentUser['email'] ?? '');
$phone = (string) ($rawUser['phone'] ?? '');
$walletBalance = (float) $wallet['balance'];
$createdAt = (string) ($rawUser['created_at'] ?? '');
$memberSince = $createdAt !== '' ? date('F Y', strtotime($createdAt)) : 'N/A';
$accountStatus = ucfirst((string) ($rawUser['status'] ?? 'Active'));
$transactionsStatus = $wallet['id'] > 0 ? 'Enabled' : 'Wallet Pending';
$spendingLimit = 'No Limit';
$profileImg = (string) ($rawUser['profile_img'] ?? '');
$profilePhotoUrl = ($profileImg !== '') ? (BASE_URL . '/' . ltrim($profileImg, '/')) : '';
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
    <link rel="stylesheet" href="<?= CSS_URL ?>/student.css?v=56">
    <link rel="stylesheet" href="<?= CSS_URL ?>/responsive.css">

    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap"
        rel="stylesheet">
</head>

<body>

    <div class="student-layout">

        <aside class="student-sidebar" id="studentSidebar">

            <div class="student-brand">
                <div class="student-brand-logo">
                    <img src="<?= ICONS_URL ?>/GenDeJesusFavicon.png" alt="GJC Logo">
                </div>

                <div class="student-brand-text">
                    <h4>GenPay</h4>
                    <span>Student Portal</span>
                </div>
            </div>

            <nav class="student-menu">
                <a href="<?= DASHBOARD_URL ?>">
                    <i class="fa-solid fa-gauge-high student-nav-icon"></i>
                    <span class="student-nav-text">Dashboard</span>
                </a>

                <a href="<?= STUDENT_URL ?>/cart.php">
                    <i class="fa-solid fa-cart-shopping student-nav-icon"></i>
                    <span class="student-nav-text">Shop Cart</span>
                </a>

                <a href="<?= STUDENT_URL ?>/transfer.php">
                    <i class="fa-solid fa-money-bill-transfer student-nav-icon"></i>
                    <span class="student-nav-text">Send GenCoin</span>
                </a>

                <a href="<?= STUDENT_URL ?>/withdraw.php">
                    <i class="fa-solid fa-money-bill-wave student-nav-icon"></i>
                    <span class="student-nav-text">Withdraw</span>
                </a>

                <a href="<?= STUDENT_URL ?>/topup_request.php">
                    <i class="fa-solid fa-circle-plus student-nav-icon"></i>
                    <span class="student-nav-text">Top-Up</span>
                </a>

                <a href="<?= STUDENT_URL ?>/history.php">
                    <i class="fa-solid fa-receipt student-nav-icon"></i>
                    <span class="student-nav-text">History</span>
                </a>

                <a href="<?= STUDENT_URL ?>/profile.php" class="active">
                    <i class="fa-solid fa-user student-nav-icon"></i>
                    <span class="student-nav-text">Profile</span>
                </a>
            </nav>

            <a href="<?= BASE_URL ?>/logout.php" class="student-logout"
               onclick="openLogoutModal(event);">
                <i class="fa-solid fa-arrow-right-from-bracket student-logout-icon"></i>
                <span>Logout</span>
            </a>

        </aside>
        <?php require __DIR__ . '/../includes/partials/logout_modal.php'; ?>

        <main class="student-main">

            <header class="student-topbar">
                <button class="student-menu-btn" onclick="toggleStudentSidebar()">Menu</button>

                <div>
                    <h1>My Profile</h1>
                    <p>Manage your student account details, status, and password security.</p>
                </div>

                <div class="student-user">
                    <span><?php echo gjc_e($studentName); ?></span>
                    <div class="student-avatar" id="topbarAvatar" style="<?= $profilePhotoUrl ? 'padding:0;overflow:hidden;' : '' ?>">
                        <?php if ($profilePhotoUrl): ?>
                            <img id="topbarAvatarImg" src="<?= htmlspecialchars($profilePhotoUrl) ?>" alt=""
                                 style="width:100%;height:100%;object-fit:cover;border-radius:50%;display:block;">
                        <?php else: ?>
                            <?php echo gjc_e($studentInitial); ?>
                        <?php endif; ?>
                    </div>
                </div>
            </header>

            <?php if ($notice): ?>
            <div class="profile-alert mb-4">
                <?php echo gjc_e($notice); ?>
            </div>
            <?php endif; ?>

            <?php if ($error): ?>
            <div class="profile-alert profile-alert-error mb-4">
                <?php echo gjc_e($error); ?>
            </div>
            <?php endif; ?>

            <section class="profile-hero-card mb-4">

                <div class="profile-hero-left">
                    <div class="profile-avatar-wrap" style="position:relative;flex-shrink:0;">
                        <div class="profile-avatar-large" id="avatarCircle" style="<?= $profilePhotoUrl ? 'padding:0;overflow:hidden;' : '' ?>">
                            <?php if ($profilePhotoUrl): ?>
                                <img id="avatarImg" src="<?= htmlspecialchars($profilePhotoUrl) ?>" alt="Profile Photo"
                                     style="width:100%;height:100%;object-fit:cover;border-radius:50%;display:block;">
                            <?php else: ?>
                                <span id="avatarInitial"><?php echo gjc_e($studentInitial); ?></span>
                            <?php endif; ?>
                        </div>
                        <label for="photoInput" title="Change photo"
                               style="position:absolute;bottom:0;right:0;width:28px;height:28px;border-radius:50%;background:#0b5c2c;color:#fff;display:flex;align-items:center;justify-content:center;cursor:pointer;border:2px solid #fff;font-size:12px;box-shadow:0 1px 4px rgba(0,0,0,.25);">
                            <i class="fa-solid fa-camera"></i>
                        </label>
                        <input type="file" id="photoInput" accept="image/jpeg,image/png,image/webp" style="display:none;">
                    </div>

                    <div>
                        <span>Student Account</span>
                        <h2><?php echo gjc_e($studentName); ?></h2>
                        <p><?php echo gjc_e($email); ?> &middot; ID: <?php echo gjc_e($studentID); ?></p>
                        <div id="photoMsg" style="font-size:12px;margin-top:4px;"></div>
                    </div>
                </div>

                <div class="profile-wallet-box">
                    <span>Wallet Balance</span>
                    <h3><?php echo gjc_money($walletBalance); ?></h3>
                    <p>Member since <?php echo gjc_e($memberSince); ?></p>
                </div>

            </section>

            <section class="profile-layout-grid mb-4">

                <div class="student-premium-panel profile-form-panel">
                    <div class="student-panel-header">
                        <div>
                            <h3>Update Profile</h3>
                            <p>Edit your personal account information.</p>
                        </div>
                    </div>

                    <form method="POST" class="profile-form">
                        <input type="hidden" name="profile_action" value="profile">

                        <div class="profile-form-grid">
                            <div class="profile-field">
                                <label>First Name</label>
                                <input type="text" name="first_name" value="<?php echo gjc_e($firstName); ?>" required>
                            </div>

                            <div class="profile-field">
                                <label>Last Name</label>
                                <input type="text" name="last_name" value="<?php echo gjc_e($lastName); ?>" required>
                            </div>
                        </div>

                        <div class="profile-field">
                            <label>Phone Number</label>
                            <input type="text" name="phone" value="<?php echo gjc_e($phone); ?>">
                        </div>

                        <div class="profile-field">
                            <label>Email Address</label>
                            <input type="email" value="<?php echo gjc_e($email); ?>" disabled>
                            <small>Email cannot be changed. Contact Admin if needed.</small>
                        </div>

                        <button type="submit" class="profile-save-btn">
                            Save Changes
                        </button>

                    </form>
                </div>

                <div class="student-premium-panel profile-status-panel">
                    <div class="student-panel-header">
                        <div>
                            <h3>Account Status</h3>
                            <p>Current access and transaction settings.</p>
                        </div>
                    </div>

                    <div class="profile-status-list">
                        <div>
                            <span>Account Status</span>
                            <strong class="profile-pill green"><?php echo gjc_e($accountStatus); ?></strong>
                        </div>

                        <div>
                            <span>Transactions</span>
                            <strong class="profile-pill green"><?php echo gjc_e($transactionsStatus); ?></strong>
                        </div>

                        <div>
                            <span>Spending Limit</span>
                            <strong class="profile-pill gray"><?php echo gjc_e($spendingLimit); ?></strong>
                        </div>
                    </div>

                    <div class="profile-note">
                        Some account settings are managed by the system administrator.
                    </div>
                </div>

            </section>

            <section class="student-premium-panel">

                <div class="student-panel-header">
                    <div>
                        <h3>Change Password</h3>
                        <p>Update your login password for better account security.</p>
                    </div>
                </div>

                <form method="POST" class="profile-password-form">
                    <input type="hidden" name="profile_action" value="password">

                    <div class="profile-field">
                        <label>Current Password</label>
                        <input type="password" name="current_password" required>
                    </div>

                    <div class="profile-form-grid">
                        <div class="profile-field">
                            <label>New Password</label>
                            <input type="password" name="new_password" minlength="6" required>
                        </div>

                        <div class="profile-field">
                            <label>Confirm New Password</label>
                            <input type="password" name="confirm_password" minlength="6" required>
                        </div>
                    </div>

                    <button type="submit" class="profile-password-btn">
                        Update Password
                    </button>

                </form>

            </section>

        </main>

    </div>

    <script src="<?= JS_URL ?>/bootstrap.bundle.min.js"></script>

    <script>
    function toggleStudentSidebar() {
        document.getElementById("studentSidebar").classList.toggle("collapsed");
    }

    document.querySelector(".student-menu a.active")?.scrollIntoView({ inline: "center", block: "nearest" });

    /* ── Profile photo upload ─────────────────────────────────── */
    const PHOTO_API = '<?= BASE_URL ?>/api/profile_photo.php';

    document.getElementById('photoInput').addEventListener('change', async function() {
        const file = this.files[0];
        if (!file) return;

        const msg = document.getElementById('photoMsg');
        msg.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Uploading...';
        msg.style.color = '#64748b';

        const fd = new FormData();
        fd.append('action', 'upload');
        fd.append('photo', file);

        try {
            const res  = await fetch(PHOTO_API, {method: 'POST', body: fd, credentials: 'same-origin'});
            const data = await res.json();
            if (data.success) {
                const circle = document.getElementById('avatarCircle');
                circle.style.padding  = '0';
                circle.style.overflow = 'hidden';
                let img = document.getElementById('avatarImg');
                const initial = document.getElementById('avatarInitial');
                if (initial) initial.remove();
                if (!img) {
                    img = document.createElement('img');
                    img.id = 'avatarImg';
                    img.style.cssText = 'width:100%;height:100%;object-fit:cover;border-radius:50%;display:block;';
                    img.alt = 'Profile Photo';
                    circle.appendChild(img);
                }
                img.src = data.photo_url;

                // Also sync topbar avatar
                const topbar = document.getElementById('topbarAvatar');
                if (topbar) {
                    topbar.style.padding  = '0';
                    topbar.style.overflow = 'hidden';
                    let tImg = document.getElementById('topbarAvatarImg');
                    if (!tImg) {
                        topbar.textContent = '';
                        tImg = document.createElement('img');
                        tImg.id = 'topbarAvatarImg';
                        tImg.alt = '';
                        tImg.style.cssText = 'width:100%;height:100%;object-fit:cover;border-radius:50%;display:block;';
                        topbar.appendChild(tImg);
                    }
                    tImg.src = data.photo_url;
                }

                msg.innerHTML = '<i class="fa-solid fa-check" style="color:var(--gjc-green-600)"></i> Photo updated.';
                msg.style.color = 'var(--gjc-green-600)';
                setTimeout(() => { msg.innerHTML = ''; }, 3000);
            } else {
                msg.innerHTML = data.error || 'Upload failed.';
                msg.style.color = 'var(--gjc-danger)';
            }
        } catch(err) {
            msg.innerHTML = 'Network error. Please try again.';
            msg.style.color = 'var(--gjc-danger)';
        }
        this.value = '';
    });
</script>

</body>

</html>
