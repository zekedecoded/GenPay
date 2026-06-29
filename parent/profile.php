<?php
require_once __DIR__ . '/../connection/config.php';
require_once __DIR__ . '/../connection/pdo.php';
require_once __DIR__ . '/../connection/app.php';
require_once __DIR__ . '/../connection/audit_logger.php';

gjc_require_role(['parent']);

$currentUser = gjc_current_user($db);
$rawUser     = $currentUser['raw'] ?? [];
$idCol       = gjc_column($db, 'users', ['id', 'userID']);
$columns     = gjc_table_columns($db, 'users');
$notice      = '';
$error       = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $idCol) {
    $action = (string) ($_POST['profile_action'] ?? '');

    if ($action === 'profile') {
        $firstName = trim((string) ($_POST['first_name'] ?? ''));
        $lastName  = trim((string) ($_POST['last_name']  ?? ''));
        $phone     = trim((string) ($_POST['phone']       ?? ''));

        if ($firstName === '' || $lastName === '') {
            $error = 'First name and last name are required.';
        } else {
            $updates = [];
            $values  = [];
            foreach ([
                'first_name' => $firstName,
                'last_name'  => $lastName,
                'name'       => trim($firstName . ' ' . $lastName),
                'phone'      => $phone,
            ] as $col => $val) {
                if (in_array($col, $columns, true)) {
                    $updates[] = "{$col} = ?";
                    $values[]  = $val;
                }
            }
            if ($updates) {
                $values[] = $currentUser['id'];
                $db->prepare('UPDATE users SET ' . implode(', ', $updates) . " WHERE {$idCol} = ?")->execute($values);
                $notice = 'Profile updated successfully!';
            }
        }

    } elseif ($action === 'password') {
        $current  = (string) ($_POST['current_password']  ?? '');
        $new      = (string) ($_POST['new_password']      ?? '');
        $confirm  = (string) ($_POST['confirm_password']  ?? '');
        $stored   = (string) ($rawUser['password']         ?? '');

        $valid = $stored !== '' &&
                 (password_verify($current, $stored) || hash_equals($stored, $current));

        if (!in_array('password', $columns, true)) {
            $error = 'Password column not found.';
        } elseif (!$valid) {
            $error = 'Current password is incorrect.';
        } elseif (strlen($new) < 6) {
            $error = 'New password must be at least 6 characters.';
        } elseif ($new !== $confirm) {
            $error = 'New passwords do not match.';
        } else {
            $db->prepare("UPDATE users SET password = ? WHERE {$idCol} = ?")
               ->execute([password_hash($new, PASSWORD_DEFAULT), $currentUser['id']]);
            logAudit($db, (int)$currentUser['id'], gjc_current_role(),
                'PASSWORD_CHANGE', 'users',
                ['password' => 'changed_by_parent_profile'],
                ['password' => 'changed_by_parent_profile']);
            $notice = 'Password updated successfully!';
        }
    }

    $currentUser = gjc_current_user($db);
    $rawUser     = $currentUser['raw'] ?? [];
}

$firstName  = (string) ($rawUser['first_name'] ?? '');
$lastName   = (string) ($rawUser['last_name']  ?? '');
if ($firstName === '' && $lastName === '') {
    $parts     = preg_split('/\s+/', trim($currentUser['name']), 2);
    $firstName = $parts[0] ?? '';
    $lastName  = $parts[1] ?? '';
}
$fullName      = $currentUser['name'];
$initial       = strtoupper(substr($fullName, 0, 1));
$email         = (string) ($currentUser['email']        ?? '');
$phone         = (string) ($rawUser['phone']            ?? '');
$createdAt     = (string) ($rawUser['created_at']       ?? '');
$memberSince   = $createdAt !== '' ? date('F Y', strtotime($createdAt)) : 'N/A';
$profileImg    = (string) ($rawUser['profile_img']      ?? '');
$profilePhotoUrl = ($profileImg !== '') ? (BASE_URL . '/' . ltrim($profileImg, '/')) : '';

$currentPage = 'profile';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="icon" type="image/png" sizes="32x32" href="<?= ICONS_URL ?>/gp_logo.png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile | GenPay Parent Portal</title>
    <link rel="stylesheet" href="<?= CSS_URL ?>/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer">
    <link rel="stylesheet" href="<?= CSS_URL ?>/student.css">
    <link rel="stylesheet" href="<?= CSS_URL ?>/responsive.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        .profile-hero {
            background: linear-gradient(135deg, #064420, #0b5c2c);
            border-radius: 16px;
            padding: 28px;
            color: #fff;
            display: flex;
            align-items: center;
            gap: 24px;
            margin-bottom: 22px;
            flex-wrap: wrap;
        }
        .profile-avatar-wrap { position: relative; flex-shrink: 0; }
        .profile-avatar-lg {
            width: 84px; height: 84px;
            border-radius: 50%;
            background: rgba(255,255,255,.18);
            border: 3px solid rgba(255,255,255,.35);
            display: flex; align-items: center; justify-content: center;
            font-size: 32px; font-weight: 800; color: #fff;
            overflow: hidden;
        }
        .photo-edit-btn {
            position: absolute; bottom: 0; right: 0;
            width: 28px; height: 28px;
            border-radius: 50%;
            background: #fff; color: #0b5c2c;
            display: flex; align-items: center; justify-content: center;
            cursor: pointer; border: 2px solid rgba(255,255,255,.6);
            font-size: 12px; box-shadow: 0 1px 4px rgba(0,0,0,.3);
        }
        .profile-hero-info h2 { font-size: 20px; font-weight: 800; margin: 0 0 2px; }
        .profile-hero-info p  { font-size: 13px; opacity: .75; margin: 0 0 8px; }
        .hero-badge { background: rgba(255,255,255,.15); border-radius: 20px; padding: 4px 12px; font-size: 12px; font-weight: 600; display: inline-block; }
        .pcard { background: #fff; border-radius: 14px; border: 1.5px solid #e2e8f0; padding: 24px 26px; margin-bottom: 18px; box-shadow: 0 2px 8px rgba(0,0,0,.04); }
        .pcard h5 { font-size: 15px; font-weight: 700; color: #0d1f14; margin: 0 0 4px; }
        .pcard p  { font-size: 13px; color: #64748b; margin: 0 0 18px; }
        .pfield { margin-bottom: 14px; }
        .pfield label { font-size: 12px; font-weight: 700; color: #475569; text-transform: uppercase; letter-spacing: .04em; display: block; margin-bottom: 5px; }
        .pfield input { width: 100%; border: 1.5px solid #cbd5e1; border-radius: 8px; padding: 9px 12px; font-size: 14px; font-weight: 500; outline: none; transition: border .15s; }
        .pfield input:focus { border-color: #0b5c2c; box-shadow: 0 0 0 3px rgba(11,92,44,.1); }
        .pfield input:disabled { background: #f8fafc; color: #94a3b8; cursor: not-allowed; }
        .pfield small { font-size: 11px; color: #94a3b8; display: block; margin-top: 4px; }
        .pgrid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
        @media(max-width:540px) { .pgrid { grid-template-columns: 1fr; } }
        .btn-primary-save { background: linear-gradient(135deg,#064420,#0b5c2c); color: #fff; border: none; border-radius: 8px; padding: 10px 22px; font-size: 14px; font-weight: 700; cursor: pointer; margin-top: 6px; }
        .btn-primary-save:hover { filter: brightness(1.1); }
        .flash { padding: 10px 16px; border-radius: 8px; font-size: 13px; font-weight: 600; margin-bottom: 16px; }
        .flash.ok  { background: #f0fdf4; color: var(--gjc-green-600); border: 1px solid var(--gjc-success-border); }
        .flash.err { background: #fef2f2; color: var(--gjc-danger); border: 1px solid var(--gjc-danger-border); }
        #photoMsg { font-size: 12px; margin-top: 6px; min-height: 16px; }
    </style>
</head>
<body>
<div class="student-layout">

    <?php require __DIR__ . '/../includes/partials/sidebar_parent.php'; ?>

    <main class="student-main">

        <header class="student-topbar">
            <button class="student-menu-btn" onclick="toggleParentSidebar()">
                <i class="fa-solid fa-bars"></i>
            </button>
            <div>
                <h1>My Profile</h1>
                <p>Manage your account details and security.</p>
            </div>
            <div class="student-user">
                <span><?= htmlspecialchars($fullName) ?></span>
                <div class="student-avatar" id="topbarAvatar" style="<?= $profilePhotoUrl ? 'padding:0;overflow:hidden;' : '' ?>">
                    <?php if ($profilePhotoUrl): ?>
                        <img id="topbarAvatarImg" src="<?= htmlspecialchars($profilePhotoUrl) ?>" alt=""
                             style="width:100%;height:100%;object-fit:cover;border-radius:50%;display:block;">
                    <?php else: ?>
                        <span id="topbarAvatarInitial"><?= htmlspecialchars($initial) ?></span>
                    <?php endif; ?>
                </div>
            </div>
        </header>

        <div style="padding: 24px 28px; max-width: 680px;">

            <?php if ($notice): ?>
            <div class="flash ok"><i class="fa-solid fa-circle-check me-1"></i><?= htmlspecialchars($notice) ?></div>
            <?php elseif ($error): ?>
            <div class="flash err"><i class="fa-solid fa-circle-xmark me-1"></i><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <!-- Hero card -->
            <div class="profile-hero">
                <div class="profile-avatar-wrap">
                    <div class="profile-avatar-lg" id="avatarCircle" <?= $profilePhotoUrl ? 'style="padding:0;"' : '' ?>>
                        <?php if ($profilePhotoUrl): ?>
                            <img id="avatarImg" src="<?= htmlspecialchars($profilePhotoUrl) ?>" alt="Profile Photo"
                                 style="width:100%;height:100%;object-fit:cover;border-radius:50%;display:block;">
                        <?php else: ?>
                            <span id="avatarInitial"><?= htmlspecialchars($initial) ?></span>
                        <?php endif; ?>
                    </div>
                    <label for="photoInput" class="photo-edit-btn" title="Change photo">
                        <i class="fa-solid fa-camera"></i>
                    </label>
                    <input type="file" id="photoInput" accept="image/jpeg,image/png,image/webp" style="display:none;">
                </div>

                <div class="profile-hero-info">
                    <h2><?= htmlspecialchars($fullName) ?></h2>
                    <p><?= htmlspecialchars($email) ?></p>
                    <span class="hero-badge"><i class="fa-solid fa-user-shield me-1"></i>Parent / Guardian</span>
                    <div id="photoMsg"></div>
                </div>

                <div style="margin-left:auto;text-align:right;opacity:.75;font-size:12px;">
                    Member since<br><strong style="font-size:14px;opacity:1;"><?= htmlspecialchars($memberSince) ?></strong>
                </div>
            </div>

            <!-- Edit profile -->
            <div class="pcard">
                <h5><i class="fa-solid fa-pen me-2" style="color:#0b5c2c"></i>Edit Profile</h5>
                <p>Update your name and contact number.</p>
                <form method="POST">
                    <input type="hidden" name="profile_action" value="profile">
                    <div class="pgrid">
                        <div class="pfield">
                            <label>First Name</label>
                            <input type="text" name="first_name" value="<?= htmlspecialchars($firstName) ?>" required>
                        </div>
                        <div class="pfield">
                            <label>Last Name</label>
                            <input type="text" name="last_name" value="<?= htmlspecialchars($lastName) ?>" required>
                        </div>
                    </div>
                    <div class="pfield">
                        <label>Phone Number</label>
                        <input type="text" name="phone" value="<?= htmlspecialchars($phone) ?>" placeholder="e.g. 09171234567">
                    </div>
                    <div class="pfield">
                        <label>Email Address</label>
                        <input type="email" value="<?= htmlspecialchars($email) ?>" disabled>
                        <small>Email cannot be changed. Contact Finance if needed.</small>
                    </div>
                    <button type="submit" class="btn-primary-save"><i class="fa-solid fa-floppy-disk me-1"></i>Save Changes</button>
                </form>
            </div>

            <!-- Change password -->
            <div class="pcard">
                <h5><i class="fa-solid fa-lock me-2" style="color:#0b5c2c"></i>Change Password</h5>
                <p>Keep your account secure by updating your password regularly.</p>
                <form method="POST">
                    <input type="hidden" name="profile_action" value="password">
                    <div class="pfield">
                        <label>Current Password</label>
                        <input type="password" name="current_password" required autocomplete="current-password">
                    </div>
                    <div class="pgrid">
                        <div class="pfield">
                            <label>New Password</label>
                            <input type="password" name="new_password" minlength="6" required autocomplete="new-password">
                        </div>
                        <div class="pfield">
                            <label>Confirm New Password</label>
                            <input type="password" name="confirm_password" minlength="6" required autocomplete="new-password">
                        </div>
                    </div>
                    <button type="submit" class="btn-primary-save"><i class="fa-solid fa-key me-1"></i>Update Password</button>
                </form>
            </div>

        </div>
    </main>
</div>

<?php require __DIR__ . '/../includes/partials/logout_modal.php'; ?>
<script src="<?= JS_URL ?>/bootstrap.bundle.min.js"></script>
<script>
function toggleParentSidebar() {
    document.getElementById('parentSidebar').classList.toggle('collapsed');
}

/* ── Profile photo upload ─────────────────────────────────── */
const PHOTO_API = '<?= BASE_URL ?>/api/profile_photo.php';

document.getElementById('photoInput').addEventListener('change', async function() {
    const file = this.files[0];
    if (!file) return;

    const msg = document.getElementById('photoMsg');
    msg.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Uploading...';
    msg.style.color = 'rgba(255,255,255,.8)';

    const fd = new FormData();
    fd.append('action', 'upload');
    fd.append('photo', file);

    try {
        const res  = await fetch(PHOTO_API, {method: 'POST', body: fd, credentials: 'same-origin'});
        const data = await res.json();
        if (data.success) {
            const circle  = document.getElementById('avatarCircle');
            const initial = document.getElementById('avatarInitial');
            circle.style.padding = '0';
            if (initial) initial.remove();
            let img = document.getElementById('avatarImg');
            if (!img) {
                img = document.createElement('img');
                img.id = 'avatarImg';
                img.alt = 'Profile Photo';
                img.style.cssText = 'width:100%;height:100%;object-fit:cover;border-radius:50%;display:block;';
                circle.appendChild(img);
            }
            img.src = data.photo_url;

            // Also update topbar avatar
            const topbar = document.getElementById('topbarAvatar');
            if (topbar) {
                topbar.style.padding  = '0';
                topbar.style.overflow = 'hidden';
                const tInit = document.getElementById('topbarAvatarInitial');
                if (tInit) tInit.remove();
                let tImg = document.getElementById('topbarAvatarImg');
                if (!tImg) {
                    tImg = document.createElement('img');
                    tImg.id = 'topbarAvatarImg';
                    tImg.alt = '';
                    tImg.style.cssText = 'width:100%;height:100%;object-fit:cover;border-radius:50%;display:block;';
                    topbar.appendChild(tImg);
                }
                tImg.src = data.photo_url;
            }

            msg.innerHTML = '<i class="fa-solid fa-check"></i> Photo updated.';
            msg.style.color = 'rgba(255,255,255,.9)';
            setTimeout(() => { msg.innerHTML = ''; }, 3000);
        } else {
            msg.innerHTML = data.error || 'Upload failed.';
            msg.style.color = 'var(--gjc-danger-border)';
        }
    } catch(err) {
        msg.innerHTML = 'Network error. Please try again.';
        msg.style.color = 'var(--gjc-danger-border)';
    }
    this.value = '';
});
</script>
</body>
</html>
