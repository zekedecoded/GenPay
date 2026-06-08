<?php
    session_start();
    require_once __DIR__ . '/connection/config.php';

    $role = (int) ($_SESSION['roleID'] ?? 0);

    
    if ($role === 3) {
        header('Location: ' . ADMIN_URL . '/dashboard.php');
        exit;
    } elseif ($role === 2) {
        header('Location: ' . MERCHANT_URL . '/dashboard.php');
        exit;
    } elseif ($role === 1) {
        header('Location: ' . STUDENT_URL . '/dashboard.php');
        exit;
    }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GJC EduPay | General de Jesus College</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= CSS_URL ?>/gjc-clear.css?v=1">
    <style>
        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background-color: #f8fbf7;
            color: #102018;
            margin: 0;
            padding: 0;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }
        .navbar {
            background-color: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            padding: 15px 50px;
            position: fixed;
            width: 100%;
            top: 0;
            z-index: 1000;
        }
        .navbar-brand {
            font-weight: 800;
            color: #064420 !important;
            font-size: 24px;
            display: flex;
            align-items: center;
            gap: 12px;
            text-decoration: none;
        }
        .navbar-brand img {
            width: 45px;
            height: 45px;
            object-fit: contain;
        }
        .hero {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            text-align: center;
            padding: 140px 20px 80px 20px;
            background: linear-gradient(rgba(15, 61, 46, 0.85), rgba(15, 61, 46, 0.85)), url('<?= IMAGES_URL ?>/GenSimeon-Bldg.jpg') center/cover no-repeat;
            color: #ffffff;
            margin-top: 0;
        }
        .hero-content {
            max-width: 800px;
        }
        .hero h1 {
            font-size: 56px;
            font-weight: 800;
            color: #ffffff;
            margin-bottom: 24px;
            line-height: 1.2;
        }
        .hero h1 span {
            color: #4ade80;
            background: linear-gradient(135deg, #4ade80, #22c55e);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        .hero p {
            font-size: 20px;
            color: #e2e8f0;
            margin-bottom: 40px;
            line-height: 1.6;
        }
        .btn-primary-custom {
            background: linear-gradient(135deg, #4ade80, #22c55e);
            color: #064420;
            padding: 15px 40px;
            font-size: 18px;
            font-weight: 800;
            border-radius: 50px;
            text-decoration: none;
            transition: all 0.3s ease;
            display: inline-block;
            border: none;
            box-shadow: 0 10px 20px rgba(34, 197, 94, 0.3);
        }
        .btn-primary-custom:hover {
            transform: translateY(-3px);
            box-shadow: 0 15px 25px rgba(34, 197, 94, 0.4);
            color: #064420;
        }
        .features {
            padding: 80px 20px;
            background: #ffffff;
        }
        .feature-card {
            padding: 40px 30px;
            border-radius: 20px;
            background: #f9fcf8;
            border: 1px solid rgba(6, 68, 32, 0.05);
            text-align: center;
            transition: transform 0.3s ease;
            height: 100%;
        }
        .feature-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 20px 40px rgba(0,0,0,0.05);
        }
        .feature-icon {
            width: 80px;
            height: 80px;
            background: #eaf5ee;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            font-size: 32px;
            flex: 0 0 auto;
            overflow: hidden;
        }
        .feature-icon img {
            width: 40px;
            height: 40px;
            object-fit: contain;
            display: block;
        }
        .feature-icon-qr {
            filter: brightness(0) saturate(100%) invert(18%) sepia(44%) saturate(1073%) hue-rotate(102deg) brightness(91%) contrast(95%);
        }
        .feature-card h3 {
            font-size: 22px;
            font-weight: 700;
            margin-bottom: 15px;
            color: #032014;
        }
        .feature-card p {
            color: #66756c;
            line-height: 1.6;
        }
        footer {
            background: #032014;
            color: #ffffff;
            text-align: center;
            padding: 30px 20px;
            font-size: 14px;
            opacity: 0.9;
        }
        @media (max-width: 768px) {
            .hero h1 { font-size: 40px; }
            .hero p { font-size: 16px; }
            .features {
                width: 100%;
                max-width: 100%;
                padding: 48px 16px;
                overflow: hidden;
            }
            .features .row {
                margin-left: 0;
                margin-right: 0;
            }
            .features .row > [class*="col-"] {
                width: 100%;
                max-width: 100%;
                padding-left: 0;
                padding-right: 0;
            }
            .feature-card {
                width: 100%;
                max-width: 100%;
                padding: 28px 22px;
                text-align: left;
            }
            .feature-icon {
                width: 72px;
                height: 72px;
                margin: 0 0 18px;
            }
            .feature-icon img {
                width: 36px;
                height: 36px;
            }
        }
    </style>
</head>
<body>

    <nav class="navbar">
        <a class="navbar-brand" href="#">
            <img src="<?= ICONS_URL ?>/GenDeJesusFavicon.png" alt="GJC Logo" onerror="this.src='<?= ICONS_URL ?>/logo.png'">
            GJC EduPay
        </a>
        <div>
            <a href="<?= BASE_URL ?>/login.php" class="btn btn-primary-custom" style="padding: 10px 25px; font-size: 16px;">Login to Portal</a>
        </div>
    </nav>

    <div class="hero">
        <div class="hero-content">
            <h1>The Future of Campus <br><span>Cashless Payments</span></h1>
            <p>Experience seamless, secure, and instant transactions within General de Jesus College. Pay for meals, services, and manage your school finances with just a scan.</p>
            <a href="<?= BASE_URL ?>/login.php" class="btn-primary-custom">Get Started Now</a>
        </div>
    </div>

    <div class="features container">
        <div class="row g-4">
            <div class="col-md-4">
                <div class="feature-card">
                    <div class="feature-icon">
                        <img src="<?= ICONS_URL ?>/qr.png" alt="QR Code" class="feature-icon-qr">
                    </div>
                    <h3>QR Code Payments</h3>
                    <p>No need for cash. Just scan the merchant's QR code or present your visitor voucher to pay instantly.</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="feature-card">
                    <div class="feature-icon">
                        <img src="<?= ICONS_URL ?>/cyber-security.png" alt="Security">
                    </div>
                    <h3>Secure & Reliable</h3>
                    <p>Built with enterprise-grade token economy security to ensure your money and transactions are safe at all times.</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="feature-card">
                    <div class="feature-icon">
                        <img src="<?= ICONS_URL ?>/analytics.png" alt="Tracking">
                    </div>
                    <h3>Real-time Tracking</h3>
                    <p>Monitor your wallet balance, transaction history, encashments, and points flow effortlessly.</p>
                </div>
            </div>
        </div>
    </div>

    <footer>
        &copy; <?php echo date("Y"); ?> General de Jesus College. All rights reserved.
    </footer>

</body>
</html>
