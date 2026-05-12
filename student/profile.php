<?php
require_once __DIR__ . '/../connection/config.php';
$studentName = "Test Student";
$firstName = "Test";
$lastName = "Student";
$studentID = "2024-00001";
$email = "student@test.com";
$phone = "09123456789";
$walletBalance = 0;
$memberSince = "April 2026";
$accountStatus = "Active";
$transactionsStatus = "Enabled";
$spendingLimit = "No Limit";
$profileUpdated = true;
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>My Profile | EduPay</title>

    <link rel="stylesheet" href="<?= CSS_URL ?>/bootstrap.min.css">
    <link rel="stylesheet" href="<?= CSS_URL ?>/student.css?v=30">
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
                <a href="<?= STUDENT_URL ?>/dashboard.php">
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
                    <span><?php echo $studentName; ?></span>
                    <div class="student-avatar">
                        <?php echo strtoupper(substr($studentName, 0, 1)); ?>
                    </div>
                </div>
            </header>

            <?php if ($profileUpdated): ?>
            <div class="profile-alert mb-4">
                Profile updated successfully!
            </div>
            <?php endif; ?>

            <section class="profile-hero-card mb-4">

                <div class="profile-hero-left">
                    <div class="profile-avatar-large">
                        <?php echo strtoupper(substr($studentName, 0, 1)); ?>
                    </div>

                    <div>
                        <span>Student Account</span>
                        <h2><?php echo $studentName; ?></h2>
                        <p><?php echo $email; ?> · ID: <?php echo $studentID; ?></p>
                    </div>
                </div>

                <div class="profile-wallet-box">
                    <span>Wallet Balance</span>
                    <h3>₱<?php echo number_format($walletBalance, 2); ?></h3>
                    <p>Member since <?php echo $memberSince; ?></p>
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

                    <form action="#" method="POST" class="profile-form">

                        <div class="profile-form-grid">
                            <div class="profile-field">
                                <label>First Name</label>
                                <input type="text" name="first_name" value="<?php echo $firstName; ?>">
                            </div>

                            <div class="profile-field">
                                <label>Last Name</label>
                                <input type="text" name="last_name" value="<?php echo $lastName; ?>">
                            </div>
                        </div>

                        <div class="profile-field">
                            <label>Phone Number</label>
                            <input type="text" name="phone" value="<?php echo $phone; ?>">
                        </div>

                        <div class="profile-field">
                            <label>Email Address</label>
                            <input type="email" value="<?php echo $email; ?>" disabled>
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
                            <strong class="profile-pill green"><?php echo $accountStatus; ?></strong>
                        </div>

                        <div>
                            <span>Transactions</span>
                            <strong class="profile-pill green"><?php echo $transactionsStatus; ?></strong>
                        </div>

                        <div>
                            <span>Spending Limit</span>
                            <strong class="profile-pill gray"><?php echo $spendingLimit; ?></strong>
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

                <form action="#" method="POST" class="profile-password-form">

                    <div class="profile-field">
                        <label>Current Password</label>
                        <input type="password" name="current_password">
                    </div>

                    <div class="profile-form-grid">
                        <div class="profile-field">
                            <label>New Password</label>
                            <input type="password" name="new_password">
                        </div>

                        <div class="profile-field">
                            <label>Confirm New Password</label>
                            <input type="password" name="confirm_password">
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
