<?php
    session_start();
    require_once __DIR__ . '/connection/config.php';

    $role = (int) ($_SESSION['roleID'] ?? 0);

    
    if ($role === 3) {
        header('Location: ' . DASHBOARD_URL);
        exit;
    } elseif ($role === 2) {
        header('Location: ' . DASHBOARD_URL);
        exit;
    } elseif ($role === 1) {
        header('Location: ' . DASHBOARD_URL);
        exit;
    }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="icon" type="image/png" sizes="32x32" href="<?= ICONS_URL ?>/gp_logo.png">
    <link rel="icon" type="image/png" sizes="192x192" href="<?= ICONS_URL ?>/gp_logo.png">
    <link rel="apple-touch-icon" sizes="180x180" href="<?= ICONS_URL ?>/gp_logo.png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GenPay | General de Jesus College</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer">
    <link rel="stylesheet" href="<?= CSS_URL ?>/gjc-clear.css?v=3">
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
        .nav-actions {
            display: flex;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
            justify-content: flex-end;
        }
        .nav-actions .btn-outline-custom {
            padding: 10px 22px;
            font-size: 15px;
            background: transparent;
            border: 2px solid #064420;
            color: #064420;
            box-shadow: none;
        }
        .nav-actions .btn-login {
            padding: 10px 25px;
            font-size: 16px;
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
        .feature-icon i {
            font-size: 34px;
            color: var(--gjc-green-600);
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
        .how-to-apply {
            padding: 80px 20px;
            background: #f9fcf8;
        }
        .how-to-apply .section-title {
            text-align: center;
            font-size: 36px;
            font-weight: 800;
            color: #032014;
            margin-bottom: 12px;
        }
        .how-to-apply .section-sub {
            text-align: center;
            color: #66756c;
            font-size: 16px;
            max-width: 600px;
            margin: 0 auto 50px;
            line-height: 1.6;
        }
        .step-card {
            background: #ffffff;
            border-radius: 20px;
            padding: 30px 26px;
            height: 100%;
            border: 1px solid rgba(6, 68, 32, 0.05);
            box-shadow: 0 4px 16px rgba(0,0,0,0.04);
        }
        .step-number {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(135deg, #4ade80, #22c55e);
            color: #064420;
            font-weight: 800;
            font-size: 18px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 16px;
        }
        .step-card h4 {
            font-size: 18px;
            font-weight: 700;
            color: #032014;
            margin-bottom: 10px;
        }
        .step-card p {
            color: #66756c;
            font-size: 14px;
            line-height: 1.6;
            margin-bottom: 0;
        }
        .requirements-box {
            background: #ffffff;
            border-radius: 20px;
            padding: 36px 36px;
            margin-top: 40px;
            border: 1px solid rgba(6, 68, 32, 0.05);
        }
        .requirements-box h4 {
            font-size: 20px;
            font-weight: 800;
            color: #064420;
            margin-bottom: 20px;
        }
        .requirements-list {
            list-style: none;
            padding: 0;
            margin: 0;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px 24px;
        }
        .requirements-list li {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            color: #102018;
            font-size: 14px;
            line-height: 1.5;
        }
        .requirements-list li i {
            color: #22c55e;
            flex-shrink: 0;
            margin-top: 3px;
        }
        .requirements-note {
            margin-top: 22px;
            padding-top: 18px;
            border-top: 1px solid rgba(6, 68, 32, 0.08);
            font-size: 13px;
            color: #66756c;
            line-height: 1.6;
        }
        .how-to-apply-cta {
            text-align: center;
            margin-top: 40px;
        }
        @media (max-width: 768px) {
            .requirements-list { grid-template-columns: 1fr; }
            .how-to-apply { padding: 56px 16px; }
            .how-to-apply .section-title { font-size: 26px; }
            .requirements-box { padding: 26px 22px; }
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
            .navbar { padding: 12px 20px; }
            .navbar-brand { font-size: 19px; gap: 8px; }
            .navbar-brand img { width: 36px; height: 36px; }
            .nav-actions { gap: 8px; }
            .nav-actions .btn-outline-custom,
            .nav-actions .btn-login {
                padding: 8px 14px;
                font-size: 13px;
            }
            .hero {
                padding: 190px 20px 60px 20px;
            }
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
            .feature-icon i {
                font-size: 30px;
            }
        }
    </style>
</head>
<body>

    <nav class="navbar">
        <a class="navbar-brand" href="#">
            <img src="<?= ICONS_URL ?>/GenDeJesusFavicon.png" alt="GJC Logo" onerror="this.src='<?= ICONS_URL ?>/logo.png'">
            GenPay
        </a>
        <div class="nav-actions">
            <a href="<?= BASE_URL ?>/stalls" class="btn btn-primary-custom btn-outline-custom">View Stalls</a>
            <a href="<?= BASE_URL ?>/login.php" class="btn btn-primary-custom btn-login">Login<span class="d-none d-sm-inline"> to Portal</span></a>
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
                        <i class="fa-solid fa-qrcode"></i>
                    </div>
                    <h3>QR Code Payments</h3>
                    <p>No need for cash. Just scan the merchant's QR code or present your visitor voucher to pay instantly.</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fa-solid fa-shield-halved"></i>
                    </div>
                    <h3>Secure & Reliable</h3>
                    <p>Built with enterprise-grade token economy security to ensure your money and transactions are safe at all times.</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fa-solid fa-chart-line"></i>
                    </div>
                    <h3>Real-time Tracking</h3>
                    <p>Monitor your wallet balance, transaction history, encashments, and points flow effortlessly.</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fa-solid fa-store"></i>
                    </div>
                    <h3>Stall Directory</h3>
                    <p>Browse available stalls in real-time and submit your vendor application online - no visits required.</p>
                    <a href="<?= BASE_URL ?>/stalls" style="display:inline-block;margin-top:14px;font-weight:700;color:#064420;text-decoration:none;font-size:14px;">View Stalls <i class="fa-solid fa-arrow-right"></i></a>
                </div>
            </div>
        </div>
    </div>

    <div class="how-to-apply">
        <div class="container">
            <div class="section-title">How to Apply for a Stall</div>
            <p class="section-sub">Follow these steps to submit your vendor application online. Review will take place after submission, and a stall will be assigned to approved applicants.</p>

            <div class="row g-4">
                <div class="col-md-4">
                    <div class="step-card">
                        <div class="step-number">1</div>
                        <h4>Prepare Your Documents</h4>
                        <p>Gather your business details and scan or photograph the required documents listed below before starting the form.</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="step-card">
                        <div class="step-number">2</div>
                        <h4>Fill Out & Submit</h4>
                        <p>Complete the online application form with your business information, upload your documents, and accept the Terms & Conditions.</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="step-card">
                        <div class="step-number">3</div>
                        <h4>Wait for Review</h4>
                        <p>The GJC administration will review your application and documents, then contact you via email regarding approval and stall assignment.</p>
                    </div>
                </div>
            </div>

            <div class="requirements-box">
                <h4>Requirements Checklist</h4>
                <ul class="requirements-list">
                    <li><i class="fa-solid fa-circle-check"></i> Business name and proprietor / owner full name</li>
                    <li><i class="fa-solid fa-circle-check"></i> Valid contact number (09XXXXXXXXX format) and email address</li>
                    <li><i class="fa-solid fa-circle-check"></i> Profile picture of the proprietor (JPG or PNG, max 5 MB)</li>
                    <li><i class="fa-solid fa-circle-check"></i> Business Permit (PDF, JPG, or PNG, max 5 MB)</li>
                    <li><i class="fa-solid fa-circle-check"></i> Sanitary Permit (PDF, JPG, or PNG, max 5 MB)</li>
                    <li><i class="fa-solid fa-circle-check"></i> GJC Requirements document (PDF, JPG, or PNG, max 5 MB)</li>
                    <li><i class="fa-solid fa-circle-check"></i> Clearance document (PDF, JPG, or PNG, max 5 MB)</li>
                    <li><i class="fa-solid fa-circle-check"></i> Acceptance of the Terms & Conditions</li>
                </ul>
                <div class="requirements-note">
                    All fields and documents above are required to submit an application. Submission does not guarantee approval - applications are reviewed at the institution's discretion, and a specific stall will be assigned later in the review process.
                </div>
            </div>

            <div class="how-to-apply-cta">
                <a href="<?= BASE_URL ?>/apply" class="btn-primary-custom">Start Your Application</a>
            </div>
        </div>
    </div>

    <footer>
        &copy; <?php echo date("Y"); ?> General de Jesus College. All rights reserved.
    </footer>

</body>
</html>
