<?php
require_once __DIR__ . '/connection/config.php';
require_once __DIR__ . '/connection/pdo.php';
require_once __DIR__ . '/connection/app.php';
require_once __DIR__ . '/connection/audit_logger.php';

$logoutUserId = gjc_user_id();
$logoutRole = gjc_current_role();
if ($logoutUserId > 0) {
    logAudit(
        $db,
        $logoutUserId,
        $logoutRole,
        'LOGOUT',
        'users',
        ['session' => 'active'],
        ['session' => 'destroyed']
    );
}

$_SESSION = [];
session_destroy();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="icon" type="image/png" href="/general_de_jesus_edupay/assets/icons/gp_logo.png">
    <meta charset="UTF-8">
    <title>Logging Out | GenPay</title>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        body { background: #f8fbf7; display: flex; align-items: center; justify-content: center; height: 100vh; margin: 0; font-family: sans-serif; }
    </style>
</head>
<body>
    <script>
        Swal.fire({
            icon: 'success',
            title: 'Logged Out Successfully',
            text: 'Redirecting to home page...',
            timer: 1500,
            showConfirmButton: false,
            allowOutsideClick: false,
            background: '#ffffff',
            color: '#032014',
            iconColor: '#0b5c2c'
        }).then(() => {
            window.location.href = 'index.php';
        });
    </script>
</body>
</html>
