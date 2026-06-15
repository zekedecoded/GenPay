<?php
require_once __DIR__ . '/../connection/config.php';
require_once __DIR__ . '/../connection/pdo.php';
require_once __DIR__ . '/../connection/app.php';

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
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <link rel="icon" type="image/png" href="/general_de_jesus_edupay/assets/icons/gp_logo.png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile | EduPay</title>

    <link rel="stylesheet" href="<?= CSS_URL ?>/bootstrap.min.css">
    <link rel="stylesheet" href="<?= CSS_URL ?>/student.css?v=31">
    <link rel="stylesheet" href="<?= CSS_URL ?>/responsive.css">

    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap"
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
                    <h4>GJC EduPay</h4>
                    <span>Student Portal</span>
                </div>
            </div>

            <nav class="student-menu">
                <a href="<?= DASHBOARD_URL ?>">
                    <img src="<?= ICONS_URL ?>/dashboard.png" class="student-nav-icon" alt="">
                    <span class="student-nav-text">Dashboard</span>
                </a>

                <a href="<?= STUDENT_URL ?>/scan.php">
                    <img src="<?= ICONS_URL ?>/qr.png" class="student-nav-icon" alt="">
                    <span class="student-nav-text">Scan &amp; Pay</span>
                </a>

                <a href="<?= STUDENT_URL ?>/history.php">
                    <img src="<?= ICONS_URL ?>/transactions.png" class="student-nav-icon" alt="">
                    <span class="student-nav-text">History</span>
                </a>

                <a href="<?= STUDENT_URL ?>/profile.php" class="active">
                    <img src="<?= ICONS_URL ?>/users.png" class="student-nav-icon" alt="">
                    <span class="student-nav-text">Profile</span>
                </a>
            </nav>

            <a href="<?= BASE_URL ?>/logout.php" class="student-logout">
                <img src="<?= ICONS_URL ?>/logout.png" class="student-logout-icon" alt="">
                <span>Logout</span>
            </a>

        </aside>

        <main class="student-main">

            <header class="student-topbar">
                <button class="student-menu-btn" onclick="toggleStudentSidebar()">☰</button>

                <div>
                    <h1>My Profile</h1>
                    <p>Manage your student account details, status, and password security.</p>
                </div>

                <div class="student-user">
                    <span><?php echo gjc_e($studentName); ?></span>
                    <div class="student-avatar">
                        <?php echo gjc_e($studentInitial); ?>
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
                    <div class="profile-avatar-large">
                        <?php echo gjc_e($studentInitial); ?>
                    </div>

                    <div>
                        <span>Student Account</span>
                        <h2><?php echo gjc_e($studentName); ?></h2>
                        <p><?php echo gjc_e($email); ?> &middot; ID: <?php echo gjc_e($studentID); ?></p>
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
    </script>

</body>

</html>
