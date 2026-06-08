<?php
require_once __DIR__ . '/connection/config.php';
session_start();


if (!isset($_SESSION['force_change'])) {
    header('Location: ' . BASE_URL . '/login.php');
    exit();
}

$error = "";
$success = "";


if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $new = $_POST['new_pass'] ?? '';
    $confirm = $_POST['confirm_pass'] ?? '';

    if ($new !== $confirm) {
        $error = "Passwords do not match.";
    } elseif (strlen($new) < 6) {
        $error = "Password must be at least 6 characters.";
    } else {

        

        unset($_SESSION['force_change']);

        $success = "Password updated successfully. Redirecting...";

        header('refresh:2;url=' . BASE_URL . '/login.php');
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Change Password | GJC EduPay</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap"
        rel="stylesheet">

    
    <link rel="stylesheet" href="<?= CSS_URL ?>/change_password.css">
    <link rel="stylesheet" href="<?= CSS_URL ?>/responsive.css">

</head>

<body>

    <div class="wrapper">

        <div class="card-box">

            <div class="badge-top">Security Update Required</div>

            <div class="title">Set Your New Password</div>

            <div class="desc">
                This is your first login. For security reasons, you are required to change your default password before
                accessing your account dashboard.
            </div>

            <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>

            <?php if ($success): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
            <?php endif; ?>

            <form method="POST">

                <div class="input-group-box">
                    <input type="password" name="new_pass" required placeholder=" ">
                    <label>New Password</label>
                </div>

                <div class="input-group-box">
                    <input type="password" name="confirm_pass" required placeholder=" ">
                    <label>Confirm Password</label>
                </div>

                <button type="submit">Update Password</button>

            </form>

        </div>

    </div>

</body>

</html>
