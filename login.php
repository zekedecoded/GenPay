<?php
session_start();
require_once __DIR__ . "/connection/config.php";

$error = "";

require_once __DIR__ . "/connection/pdo.php";
require_once __DIR__ . "/connection/app.php";
require_once __DIR__ . "/record.php";

use Classes\Record;

$Record = new Record($db);
$message = $Record->loginUser();
$error = $message ?: $error;
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <link rel="icon" type="image/png" href="/general_de_jesus_edupay/assets/icons/gp_logo.png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | GJC EduPay</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap"
        rel="stylesheet">

    <link rel="stylesheet" href="<?= CSS_URL ?>/login.css">
    <link rel="stylesheet" href="<?= CSS_URL ?>/responsive.css">
</head>

<body>

    <div class="login-wrapper">
        <div class="login-card">

            <div class="badge-top">Secure Campus Wallet Access</div>

            <div class="text-center mb-3">
                <img src="<?= ICONS_URL ?>/GenDeJesusFavicon.png" alt="GJC Seal"
                    style="width: 150px; height: 150px; object-fit: contain;">
            </div>

            <h1 class="brand-title">GJC EduPay</h1>
            <p class="sub-text">Cashless Payment System</p>

            <?php if ($error): ?>
                <div class="error-box"><?php echo $error; ?></div>
            <?php endif; ?>

            <form method="POST">

                <div class="input-group-box">
                    <input type="text" name="email" required placeholder=" ">
                    <label>Student ID or Email</label>
                </div>

                <div class="input-group-box">
                    <input type="password" name="password" id="pass" required placeholder=" ">
                    <label>Password</label>

                    <button type="button" class="eye" onclick="togglePass()" aria-label="Show password">
                        <img src="<?= ICONS_URL ?>/eye.png" alt="">
                    </button>
                </div>

                <div class="options">
                    <label><input type="checkbox"> Remember me</label>

                    <a href="#" data-bs-toggle="modal" data-bs-target="#forgotModal">
                        Forgot Password?
                    </a>
                </div>

                <button class="login-btn" name="login">SIGN IN</button>

            </form>

            <div class="signup-text">
                Doesn’t have an account yet?<br>

                <a href="#" data-bs-toggle="modal" data-bs-target="#studentModal">
                    Register as Student
                </a>

                &nbsp;|&nbsp;

                <a href="#" data-bs-toggle="modal" data-bs-target="#guestModal">
                    Register as Guest
                </a>
            </div>

        </div>
    </div>


    <div class="modal fade" id="studentModal">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content custom-modal">
                <div class="modal-body text-center">

                    <div class="modal-icon">
                        <img src="<?= ICONS_URL ?>/signup.png">
                    </div>

                    <h5 class="modal-title">Student Registration</h5>

                    <p class="modal-desc">
                        Follow these steps to register your account:
                    </p>

                    <ul class="modal-steps text-start">
                        <li>Go to Finance Department</li>
                        <li>Provide your School ID</li>
                        <li>Request account creation</li>
                        <li>Wait for activation</li>
                    </ul>

                    <button class="btn-close-modal" data-bs-dismiss="modal">Got it</button>

                </div>
            </div>
        </div>
    </div>


    <div class="modal fade" id="guestModal">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content custom-modal">
                <div class="modal-body">

                    <h4 class="modal-title text-center">Visitor Registration</h4>

                    <div class="guest-grid">

                        <div class="visitor-card">
                            <h6 class="card-title">How it works</h6>

                            <div class="step"><img src="<?= ICONS_URL ?>/form.png"> Fill out the form</div>
                            <div class="step"><img src="<?= ICONS_URL ?>/wallet.png"> Load wallet at cashier</div>
                            <div class="step"><img src="<?= ICONS_URL ?>/qr.png"> Use QR for payments</div>
                            <div class="step"><img src="<?= ICONS_URL ?>/refund.png"> Refund unused balance</div>

                        </div>

                        <div class="form-card">

                            <form>

                                <div class="input-modern">
                                    <input type="text" required>
                                    <label>Full Name</label>
                                </div>

                                <div class="input-modern">
                                    <input type="text" required>
                                    <label>Mobile Number</label>
                                </div>

                                <div class="input-modern">
                                    <input type="text">
                                    <label>Purpose of Visit</label>
                                </div>

                                <div class="btn-group-custom">
                                    <button type="button" class="login-btn">
                                        Create Visitor Account
                                    </button>
                                </div>

                            </form>

                        </div>

                    </div>

                </div>
            </div>
        </div>
    </div>


    <div class="modal fade" id="forgotModal">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content custom-modal">

                <div class="modal-body text-center">

                    <div class="modal-icon">
                        <img src="<?= ICONS_URL ?>/lock.png">
                    </div>

                    <h5 class="modal-title">Forgot Password?</h5>

                    <ul class="modal-steps text-start">
                        <li>Go to Finance Office</li>
                        <li>Bring ID</li>
                        <li>Request reset</li>
                        <li>Wait for verification</li>
                    </ul>

                    <button class="btn-close-modal" data-bs-dismiss="modal">Got it</button>

                </div>

            </div>
        </div>
    </div>

    <script>
        function togglePass() {
            const password = document.getElementById("pass");
            const button = document.querySelector(".eye");
            const shouldShow = password.type === "password";

            password.type = shouldShow ? "text" : "password";
            button.setAttribute("aria-label", shouldShow ? "Hide password" : "Show password");
            button.classList.toggle("is-visible", shouldShow);
        }
    </script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>

</body>

</html>
