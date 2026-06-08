<?php
require_once __DIR__ . '/../connection/config.php';
session_start();

$error = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    $role     = $_POST['role'] ?? 'student';

    
    if ($username === "student123" && $password === "123456") {

        if ($role === "student") {
            header('Location: ' . STUDENT_URL . '/dashboard.php');
        } else {
            header("Location: parent/dashboard.php");
        }
        exit();

    } else {
        $error = "Invalid username or password!";
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Login | GJC EduPay</title>

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

            <h1 class="brand-title">GJC EduPay</h1>
            <p class="sub-text">Cashless Payment System</p>

            
            <div class="role-switch">
                <div class="slider"></div>
                <button type="button" onclick="selectRole('student')" class="role active"
                    id="studentBtn">Student</button>
                <button type="button" onclick="selectRole('parent')" class="role" id="parentBtn">Parent</button>
            </div>

            <?php if ($error): ?>
            <div class="error-box"><?php echo $error; ?></div>
            <?php endif; ?>

            <form method="POST">

                <input type="hidden" name="role" id="role" value="student">

                <div class="input-group-box">
                    <input type="text" name="username" required placeholder=" ">
                    <label>Student ID or Email</label>
                </div>

                <div class="input-group-box">
                    <input type="password" name="password" id="pass" required placeholder=" ">
                    <label>Password</label>
                    <span class="eye" onclick="togglePass()">
                        <img src="<?= ICONS_URL ?>/eye.png" id="eyeIcon">
                    </span>
                </div>

                <div class="options">
                    <label><input type="checkbox"> Remember me</label>
                    <a href="#" data-bs-toggle="modal" data-bs-target="#forgotModal">
                        Forgot Password?
                    </a>

                    
                    <div class="modal fade" id="forgotModal" tabindex="-1">
                        <div class="modal-dialog modal-dialog-centered">
                            <div class="modal-content custom-modal">

                                <div class="modal-body text-center">

                                    <div class="modal-icon">🔒</div>

                                    <h5 class="modal-title">Forgot Password?</h5>

                                    <p class="modal-desc">
                                        Password reset is handled manually by the Finance Office for security purposes.
                                    </p>

                                    <ul class="modal-steps text-start">
                                        <li>Visit the Finance Office</li>
                                        <li>Bring your valid school ID</li>
                                        <li>Request password reset assistance</li>
                                        <li>Wait for verification</li>
                                    </ul>

                                    <button class="btn-close-modal" data-bs-dismiss="modal">
                                        Got it
                                    </button>

                                </div>

                            </div>
                        </div>
                    </div>
                </div>

                <button class="login-btn">SIGN IN</button>

            </form>

            <div class="signup-text">
                Doesn’t have an account yet? <a href="register.php">Sign Up</a>
            </div>

        </div>

    </div>

    <script>
    function togglePass() {
        let p = document.getElementById("pass");
        p.type = p.type === "password" ? "text" : "password";
    }

    function selectRole(role) {
        document.getElementById("role").value = role;

        document.getElementById("studentBtn").classList.toggle("active", role === "student");
        document.getElementById("parentBtn").classList.toggle("active", role === "parent");

        document.querySelector(".slider").style.left = role === "student" ? "0%" : "50%";
    }
    </script>

</body>

</html>
