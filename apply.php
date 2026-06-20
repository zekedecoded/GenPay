<?php
// ============================================================
//  apply.php - Public Stall Application Form (general submission)
//  Stall assignment now happens at Step 4 (Approval/Award) of the
//  admin pipeline, not at submission time - no stall pick or lock here.
// ============================================================
require_once __DIR__ . '/connection/config.php';
require_once __DIR__ . '/connection/pdo.php';

// ── Upload constants ──────────────────────────────────────────
const MAX_FILE_BYTES  = 5 * 1024 * 1024;  // 5 MB
const ALLOWED_MIMES   = ['image/jpeg','image/png','application/pdf'];
const ALLOWED_EXT     = ['jpg','jpeg','png','pdf'];
const IMG_ONLY_MIMES  = ['image/jpeg','image/png'];
const IMG_ONLY_EXT    = ['jpg','jpeg','png'];

$formErrors = [];
$old        = [];
$success    = false;
$appId      = null;

// ── POST - validate, upload, insert ──────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $old = [
        'business_name'   => trim($_POST['business_name']   ?? ''),
        'proprietor_name' => trim($_POST['proprietor_name'] ?? ''),
        'contact_number'  => trim($_POST['contact_number']  ?? ''),
        'email'           => trim($_POST['email']           ?? ''),
    ];

    // Text field validation
    if (empty($old['business_name'])) {
        $formErrors['business_name'] = 'Business name is required.';
    } elseif (mb_strlen($old['business_name']) > 120) {
        $formErrors['business_name'] = 'Must not exceed 120 characters.';
    }

    if (empty($old['proprietor_name'])) {
        $formErrors['proprietor_name'] = 'Proprietor name is required.';
    } elseif (mb_strlen($old['proprietor_name']) > 120) {
        $formErrors['proprietor_name'] = 'Must not exceed 120 characters.';
    }

    if (!preg_match('/^09\d{9}$/', $old['contact_number'])) {
        $formErrors['contact_number'] = 'Must be in 09XXXXXXXXX format (11 digits starting with 09).';
    }

    if (!filter_var($old['email'], FILTER_VALIDATE_EMAIL)) {
        $formErrors['email'] = 'Please enter a valid email address.';
    }

    if (empty($_POST['terms_accepted'])) {
        $formErrors['terms'] = 'You must scroll through and accept the Terms & Conditions.';
    }

    // File field rules
    $fileRules = [
        'profile_picture'  => ['label' => 'Profile Picture',   'mimes' => IMG_ONLY_MIMES, 'exts' => IMG_ONLY_EXT],
        'business_permit'  => ['label' => 'Business Permit',   'mimes' => ALLOWED_MIMES,  'exts' => ALLOWED_EXT],
        'sanitary_permit'  => ['label' => 'Sanitary Permit',   'mimes' => ALLOWED_MIMES,  'exts' => ALLOWED_EXT],
        'gjc_requirements' => ['label' => 'GJC Requirements',  'mimes' => ALLOWED_MIMES,  'exts' => ALLOWED_EXT],
        'clearance'        => ['label' => 'Clearance',         'mimes' => ALLOWED_MIMES,  'exts' => ALLOWED_EXT],
    ];
    $fileData = [];

    foreach ($fileRules as $field => $rule) {
        $file = $_FILES[$field] ?? null;
        if (!$file || $file['error'] === UPLOAD_ERR_NO_FILE || empty($file['tmp_name'])) {
            $formErrors[$field] = $rule['label'] . ' is required.'; continue;
        }
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $formErrors[$field] = $rule['label'] . ' upload error (code ' . $file['error'] . ').'; continue;
        }
        if ($file['size'] > MAX_FILE_BYTES) {
            $formErrors[$field] = $rule['label'] . ' exceeds the 5 MB limit.'; continue;
        }
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, $rule['exts'], true)) {
            $formErrors[$field] = $rule['label'] . ' must be ' . implode(', ', array_map('strtoupper', $rule['exts'])) . '.'; continue;
        }
        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
        if (!in_array($mime, $rule['mimes'], true)) {
            $formErrors[$field] = $rule['label'] . ' has an invalid file type.'; continue;
        }
        $fileData[$field] = ['tmp' => $file['tmp_name'], 'ext' => $ext];
    }

    // ── Process if clean ──────────────────────────────────────
    if (empty($formErrors)) {
        $tmpToken = 'tmp_' . bin2hex(random_bytes(8));
        $tmpDir   = BASE_PATH . '/uploads/stall_applications/' . $tmpToken;

        try {
            $db->beginTransaction();

            // Create temp dir
            if (!mkdir($tmpDir, 0755, true)) {
                throw new RuntimeException('DIR_CREATE');
            }

            // Move files into temp dir
            $tmpPaths = [];
            foreach ($fileData as $field => $info) {
                $fname = $field . '_' . time() . mt_rand(1000,9999) . '.' . $info['ext'];
                $dest  = $tmpDir . '/' . $fname;
                if (!move_uploaded_file($info['tmp'], $dest)) {
                    throw new RuntimeException('MOVE_' . strtoupper($field));
                }
                $tmpPaths[$field] = $fname;
            }

            // Insert record (paths updated after we have the ID) — no stall_id yet;
            // assigned by the admin at Step 4 (Approval/Award) of the pipeline.
            $ins = $db->prepare(
                "INSERT INTO stall_applications
                    (business_name, proprietor_name, contact_number, email,
                     profile_picture, business_permit, sanitary_permit, gjc_requirements,
                     clearance, terms_accepted, status, current_step)
                 VALUES (?,?,?,?, 'pending_path','pending_path','pending_path','pending_path','pending_path', 1, 'review', 1)"
            );
            $ins->execute([
                $old['business_name'],
                $old['proprietor_name'],
                $old['contact_number'],
                $old['email'],
            ]);
            $appId = (int) $db->lastInsertId();

            // Rename tmp dir to final ID
            $finalDir = BASE_PATH . '/uploads/stall_applications/' . $appId;
            if (!rename($tmpDir, $finalDir)) {
                throw new RuntimeException('DIR_RENAME');
            }

            // Build real relative paths and update record
            $realPaths = [];
            foreach ($tmpPaths as $field => $fname) {
                $realPaths[$field] = 'uploads/stall_applications/' . $appId . '/' . $fname;
            }

            $db->prepare(
                "UPDATE stall_applications
                 SET profile_picture=?, business_permit=?, sanitary_permit=?,
                     gjc_requirements=?, clearance=?
                 WHERE id=?"
            )->execute([
                $realPaths['profile_picture'],
                $realPaths['business_permit'],
                $realPaths['sanitary_permit'],
                $realPaths['gjc_requirements'],
                $realPaths['clearance'],
                $appId,
            ]);

            $db->commit();
            $success = true;

        } catch (Throwable $e) {
            $db->rollBack();
            // Clean up temp files
            if (is_dir($tmpDir)) {
                array_map('unlink', glob($tmpDir . '/*') ?: []);
                @rmdir($tmpDir);
            }
            $formErrors['general'] = 'A server error occurred. Please try again. (ref: ' . $e->getMessage() . ')';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="icon" type="image/png" href="/general_de_jesus_edupay/assets/icons/gp_logo.png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Apply for a Stall | GenPay</title>
    <meta name="description" content="Submit your stall application at General de Jesus College. Fill in your business details and upload required documents.">
    <link rel="stylesheet" href="<?= CSS_URL ?>/bootstrap.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        :root {
            --green-900: #052e16; --green-800: #064420; --green-700: #15803d;
            --green-500: #22c55e; --green-400: #4ade80; --green-100: #dcfce7;
            --gold: #d4a017; --gold-light: #f6d860;
            --red-500: #ef4444; --red-100: #fee2e2; --red-700: #b91c1c;
            --amber-500: #f59e0b; --amber-100: #fef3c7;
            --gray-50: #f9fafb; --gray-100: #f3f4f6; --gray-200: #e5e7eb;
            --gray-400: #9ca3af; --gray-600: #4b5563; --gray-800: #1f2937;
            --white: #ffffff; --radius: 16px;
            --shadow-md: 0 4px 24px rgba(0,0,0,.1);
            --shadow-lg: 0 20px 60px rgba(0,0,0,.14);
        }

        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background: linear-gradient(160deg, #f0fdf4 0%, #f9fafb 60%);
            color: var(--gray-800); min-height: 100vh;
        }

        /* ── NAVBAR ── */
        .navbar {
            position: fixed; top: 0; left: 0; right: 0; z-index: 100;
            display: flex; align-items: center; justify-content: space-between;
            padding: 14px 40px;
            background: rgba(255,255,255,.95); backdrop-filter: blur(14px);
            box-shadow: 0 2px 16px rgba(0,0,0,.07);
        }
        .navbar-brand {
            display: flex; align-items: center; gap: 10px;
            font-weight: 800; font-size: 18px; color: var(--green-800); text-decoration: none;
        }
        .navbar-brand img { width: 36px; height: 36px; object-fit: contain; }

        /* ── MAIN LAYOUT ── */
        .page-wrap {
            padding: 100px 20px 60px;
            max-width: 760px; margin: 0 auto;
        }

        /* ── ALERTS ── */
        .alert {
            padding: 16px 20px; border-radius: 12px;
            font-size: 14px; font-weight: 600; margin-bottom: 24px;
            display: flex; align-items: flex-start; gap: 10px;
        }
        .alert--error { background: var(--red-100); color: var(--red-700); border: 1px solid #fca5a5; }
        .alert--success { background: var(--green-100); color: var(--green-700); border: 1px solid #86efac; }
        .alert-icon { flex-shrink: 0; font-size: 18px; }

        /* ── FORM CARD ── */
        .form-card {
            background: var(--white); border-radius: 20px;
            box-shadow: var(--shadow-md); overflow: hidden;
        }
        .form-section {
            padding: 28px 32px;
            border-bottom: 1px solid var(--gray-100);
        }
        .form-section:last-child { border-bottom: none; }
        .section-heading {
            font-size: 11px; font-weight: 800; text-transform: uppercase;
            letter-spacing: .1em; color: var(--green-700); margin-bottom: 20px;
            display: flex; align-items: center; gap: 8px;
        }
        .section-heading::after {
            content: ''; flex: 1; height: 1px; background: var(--green-100);
        }

        /* ── INPUTS ── */
        .field { margin-bottom: 20px; }
        .field:last-child { margin-bottom: 0; }
        .field-row { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }

        label.field-label {
            display: block; font-size: 13px; font-weight: 700;
            color: var(--gray-700, #374151); margin-bottom: 6px;
        }
        label.field-label .req { color: var(--red-500); margin-left: 2px; }

        .input-wrap { position: relative; }
        input[type=text], input[type=email], input[type=tel] {
            width: 100%; padding: 12px 16px; border-radius: 10px;
            border: 2px solid var(--gray-200); font-family: inherit; font-size: 15px;
            color: var(--gray-800); background: var(--gray-50);
            transition: border-color .2s, box-shadow .2s; outline: none;
        }
        input:focus {
            border-color: var(--green-500);
            box-shadow: 0 0 0 3px rgba(34,197,94,.15);
            background: var(--white);
        }
        input.is-invalid { border-color: var(--red-500); }
        input.is-invalid:focus { box-shadow: 0 0 0 3px rgba(239,68,68,.12); }

        .field-error {
            font-size: 12px; font-weight: 600; color: var(--red-700);
            margin-top: 5px; display: flex; align-items: center; gap: 4px;
        }
        .field-hint {
            font-size: 11px; color: var(--gray-400); margin-top: 4px;
        }

        /* ── FILE UPLOADS ── */
        .file-grid {
            display: grid; grid-template-columns: 1fr 1fr; gap: 16px;
        }
        .file-field { }
        .file-drop {
            border: 2px dashed var(--gray-200); border-radius: 12px;
            padding: 20px 12px; text-align: center; cursor: pointer;
            transition: all .2s; background: var(--gray-50);
            position: relative; overflow: hidden;
        }
        .file-drop:hover { border-color: var(--green-400); background: var(--green-100); }
        .file-drop.has-file { border-color: var(--green-500); background: #f0fdf4; border-style: solid; }
        .file-drop.is-invalid { border-color: var(--red-500); background: var(--red-100); }
        .file-drop.is-dragover { border-color: var(--green-500); background: var(--green-100); transform: scale(1.01); }
        .file-drop input[type=file] {
            position: absolute; inset: 0; opacity: 0; cursor: pointer; width: 100%;
        }
        .file-icon { font-size: 28px; margin-bottom: 6px; }
        .file-label { font-size: 12px; font-weight: 700; color: var(--gray-600); }
        .file-sub { font-size: 10px; color: var(--gray-400); margin-top: 2px; }
        .file-name-display {
            font-size: 10px; color: var(--green-700); font-weight: 700;
            margin-top: 6px; word-break: break-all; display: none;
        }

        /* ── Profile picture: centered circular avatar preview, drag & drop ── */
        .avatar-drop {
            display: flex; flex-direction: column; align-items: center; justify-content: center;
        }
        .avatar-preview-wrap {
            width: 96px; height: 96px; margin: 0 auto 10px; position: relative;
            display: flex; align-items: center; justify-content: center;
        }
        .file-preview {
            width: 96px; height: 96px; object-fit: cover; object-position: center;
            border-radius: 50%; display: none;
            border: 3px solid var(--white); box-shadow: 0 4px 14px rgba(0,0,0,.14);
        }

        /* ── TERMS ── */
        .terms-link {
            background: none; border: none; padding: 0; cursor: pointer;
            font-family: inherit; font-size: inherit; font-weight: 700;
            color: var(--green-700); text-decoration: underline;
            text-decoration-color: var(--green-400); text-underline-offset: 2px;
        }
        .terms-link:hover { color: var(--green-800); }
        .terms-lock-note {
            display: block; font-size: 11px; font-weight: 600;
            color: var(--gray-400); margin-top: 4px;
        }

        .terms-scroll-box {
            height: 320px; overflow-y: auto; padding: 16px;
            background: var(--gray-50); border-radius: 10px;
            border: 2px solid var(--gray-200); font-size: 13px;
            line-height: 1.7; color: var(--gray-600); margin-bottom: 14px;
            scroll-behavior: smooth;
        }
        .terms-scroll-box p { margin-bottom: 10px; }

        .terms-check-wrap {
            display: flex; align-items: flex-start; gap: 10px;
            padding: 14px 16px; border-radius: 10px;
            border: 2px solid var(--gray-200); transition: border-color .2s;
        }
        .terms-check-wrap.is-invalid { border-color: var(--red-500); background: var(--red-100); }
        .terms-check-wrap.is-active { border-color: var(--green-400); background: var(--green-100); }

        .terms-check-wrap input[type=checkbox] {
            width: 18px; height: 18px; flex-shrink: 0; margin-top: 2px;
            accent-color: var(--green-500); cursor: pointer;
        }
        .terms-check-wrap input[type=checkbox]:disabled { opacity: .4; cursor: not-allowed; }
        .terms-check-label { font-size: 13px; font-weight: 600; color: var(--gray-700, #374151); line-height: 1.5; }
        .scroll-hint {
            font-size: 12px; color: var(--amber-500); font-weight: 700;
            text-align: center;
        }
        .btn-modal-close {
            padding: 10px 22px; border-radius: 50px; font-family: inherit;
            font-size: 13px; font-weight: 700; cursor: pointer;
            background: var(--green-800); color: #fff; border: none;
        }
        .btn-modal-close:hover { background: var(--green-900); }

        /* ── SUBMIT ── */
        .submit-section { padding: 24px 32px; background: var(--gray-50); }
        .btn-submit {
            width: 100%; padding: 17px; border: none; border-radius: 50px;
            background: linear-gradient(135deg, var(--green-400), var(--green-500));
            color: var(--green-900); font-size: 17px; font-weight: 800;
            cursor: pointer; transition: all .2s;
            box-shadow: 0 6px 24px rgba(34,197,94,.35);
            font-family: inherit;
        }
        .btn-submit:hover:not(:disabled) {
            transform: translateY(-2px); box-shadow: 0 10px 30px rgba(34,197,94,.45);
        }
        .btn-submit:disabled {
            opacity: .55; cursor: not-allowed; transform: none;
        }

        /* ── SUCCESS ── */
        .success-card {
            background: var(--white); border-radius: 20px; padding: 56px 40px;
            box-shadow: var(--shadow-md); text-align: center;
        }
        .success-icon { font-size: 72px; margin-bottom: 20px; }
        .success-title {
            font-size: 28px; font-weight: 800; color: var(--green-800); margin-bottom: 10px;
        }
        .success-sub {
            font-size: 15px; color: var(--gray-600); line-height: 1.6;
            max-width: 440px; margin: 0 auto 28px;
        }
        .success-ref {
            display: inline-block; background: var(--green-100); color: var(--green-700);
            border-radius: 10px; padding: 10px 24px; font-weight: 800;
            font-size: 18px; letter-spacing: .05em; margin-bottom: 28px;
        }
        /* ── RESPONSIVE ── */
        @media (max-width: 600px) {
            .navbar { padding: 12px 20px; }
            .page-wrap { padding: 88px 16px 48px; }
            .form-section { padding: 22px 20px; }
            .field-row { grid-template-columns: 1fr; }
            .file-grid { grid-template-columns: 1fr; }
            .submit-section { padding: 20px; }
        }
    </style>
</head>
<body>

<!-- NAVBAR -->
<nav class="navbar">
    <a href="<?= BASE_URL ?>" class="navbar-brand">
        <img src="<?= ICONS_URL ?>/GenPay_logo.png" alt="GenPay Logo">
        GenPay
    </a>
    <a href="<?= BASE_URL ?>/stalls" class="btn-close" aria-label="Back to stall map"></a>
</nav>

<div class="page-wrap">

<?php if ($success): ?>
    <!-- Success state -->
    <div class="success-card">
        <div class="success-icon"></div>
        <div class="success-title">Application Submitted!</div>
        <div class="success-sub">
            Your general stall application has been received.
            Our team will review your documents and contact you via email about next steps,
            including stall assignment.
        </div>
        <div class="success-ref">Application #<?= str_pad($appId, 5, '0', STR_PAD_LEFT) ?></div>
    </div>

<?php else: ?>
    <!-- General error alert -->
    <?php if (!empty($formErrors['general'])): ?>
    <div class="alert alert--error">
        <span class="alert-icon"></span>
        <span><?= htmlspecialchars($formErrors['general']) ?></span>
    </div>
    <?php endif; ?>

    <!-- THE FORM -->
    <form method="POST"
          action="<?= BASE_URL ?>/apply"
          enctype="multipart/form-data"
          id="applyForm"
          novalidate>

        <div class="form-card">

            <!-- Section 1: Business Info -->
            <div class="form-section">
                <div class="section-heading"> Business Information</div>

                <div class="field-row">
                    <div class="field">
                        <label class="field-label" for="business_name">Business Name <span class="req">*</span></label>
                        <input type="text" id="business_name" name="business_name"
                               placeholder="e.g. Maria's Snack Corner"
                               value="<?= htmlspecialchars($old['business_name'] ?? '') ?>"
                               class="<?= isset($formErrors['business_name']) ? 'is-invalid' : '' ?>"
                               maxlength="120">
                        <?php if (isset($formErrors['business_name'])): ?>
                        <div class="field-error"> <?= htmlspecialchars($formErrors['business_name']) ?></div>
                        <?php endif; ?>
                    </div>
                    <div class="field">
                        <label class="field-label" for="proprietor_name">Proprietor / Owner Name <span class="req">*</span></label>
                        <input type="text" id="proprietor_name" name="proprietor_name"
                               placeholder="Full legal name"
                               value="<?= htmlspecialchars($old['proprietor_name'] ?? '') ?>"
                               class="<?= isset($formErrors['proprietor_name']) ? 'is-invalid' : '' ?>"
                               maxlength="120">
                        <?php if (isset($formErrors['proprietor_name'])): ?>
                        <div class="field-error"> <?= htmlspecialchars($formErrors['proprietor_name']) ?></div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="field-row">
                    <div class="field">
                        <label class="field-label" for="contact_number">Contact Number <span class="req">*</span></label>
                        <input type="tel" id="contact_number" name="contact_number"
                               placeholder="09XXXXXXXXX"
                               value="<?= htmlspecialchars($old['contact_number'] ?? '') ?>"
                               class="<?= isset($formErrors['contact_number']) ? 'is-invalid' : '' ?>"
                               maxlength="11" pattern="09[0-9]{9}">
                        <div class="field-hint">Format: 09XXXXXXXXX (11 digits)</div>
                        <?php if (isset($formErrors['contact_number'])): ?>
                        <div class="field-error"> <?= htmlspecialchars($formErrors['contact_number']) ?></div>
                        <?php endif; ?>
                    </div>
                    <div class="field">
                        <label class="field-label" for="email">Email Address <span class="req">*</span></label>
                        <input type="email" id="email" name="email"
                               placeholder="youremail@example.com"
                               value="<?= htmlspecialchars($old['email'] ?? '') ?>"
                               class="<?= isset($formErrors['email']) ? 'is-invalid' : '' ?>">
                        <?php if (isset($formErrors['email'])): ?>
                        <div class="field-error"> <?= htmlspecialchars($formErrors['email']) ?></div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Section 2: Profile Picture -->
            <div class="form-section">
                <div class="section-heading"> Profile Picture</div>
                <p class="field-hint" style="margin-bottom:14px;">Upload a clear photo of the proprietor. JPG or PNG only, max 5 MB.</p>
                <div class="field">
                    <div class="file-drop avatar-drop <?= isset($formErrors['profile_picture']) ? 'is-invalid' : '' ?>"
                         id="drop-profile_picture"
                         onclick="document.getElementById('file-profile_picture').click()"
                         ondragover="handleDragOver(event, this)"
                         ondragleave="handleDragLeave(event, this)"
                         ondrop="handleDrop(event, this, 'profile_picture', true)">
                        <input type="file" id="file-profile_picture" name="profile_picture"
                               accept=".jpg,.jpeg,.png,image/jpeg,image/png"
                               style="display:none"
                               onchange="handleFile(this,'profile_picture',true)">
                        <div class="avatar-preview-wrap">
                            <div class="file-icon" id="icon-profile_picture"></div>
                            <img class="file-preview" id="preview-profile_picture" alt="Preview">
                        </div>
                        <div class="file-label">Click or drag a photo here</div>
                        <div class="file-sub">JPG, PNG • Max 5 MB</div>
                        <div class="file-name-display" id="name-profile_picture"></div>
                    </div>
                    <?php if (isset($formErrors['profile_picture'])): ?>
                    <div class="field-error"> <?= htmlspecialchars($formErrors['profile_picture']) ?></div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Section 3: Required Documents -->
            <div class="form-section">
                <div class="section-heading"> Required Documents</div>
                <p class="field-hint" style="margin-bottom:16px;">Upload PDF, JPG, or PNG. Max 5 MB per file.</p>
                <div class="file-grid">
                    <?php
                    $docFields = [
                        'business_permit'  => ['icon'=>'','label'=>'Business Permit'],
                        'sanitary_permit'  => ['icon'=>'','label'=>'Sanitary Permit'],
                        'gjc_requirements' => ['icon'=>'','label'=>'GJC Requirements'],
                        'clearance'        => ['icon'=>'','label'=>'Clearance'],
                    ];
                    foreach ($docFields as $field => $meta):
                    ?>
                    <div class="file-field">
                        <label class="field-label"><?= $meta['label'] ?> <span class="req">*</span></label>
                        <div class="file-drop <?= isset($formErrors[$field]) ? 'is-invalid' : '' ?>"
                             id="drop-<?= $field ?>"
                             onclick="document.getElementById('file-<?= $field ?>').click()">
                            <input type="file" id="file-<?= $field ?>" name="<?= $field ?>"
                                   accept=".jpg,.jpeg,.png,.pdf,image/jpeg,image/png,application/pdf"
                                   style="display:none"
                                   onchange="handleFile(this,'<?= $field ?>',false)">
                            <div class="file-icon" id="icon-<?= $field ?>"><?= $meta['icon'] ?></div>
                            <div class="file-label"><?= $meta['label'] ?></div>
                            <div class="file-sub">PDF, JPG, PNG • Max 5 MB</div>
                            <div class="file-name-display" id="name-<?= $field ?>"></div>
                        </div>
                        <?php if (isset($formErrors[$field])): ?>
                        <div class="field-error"> <?= htmlspecialchars($formErrors[$field]) ?></div>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Section 4: Terms & Conditions -->
            <div class="form-section">
                <div class="section-heading"> Terms & Conditions</div>

                <div class="terms-check-wrap <?= isset($formErrors['terms']) ? 'is-invalid' : '' ?>"
                     id="termsCheckWrap">
                    <input type="checkbox" id="terms_accepted" name="terms_accepted"
                           value="1" disabled
                           <?= !empty($_POST['terms_accepted']) ? 'checked' : '' ?>>
                    <label class="terms-check-label" for="terms_accepted">
                        I have read and agree to the
                        <button type="button" class="terms-link" data-bs-toggle="modal" data-bs-target="#termsModal">GJC Campus Stall Rental Terms &amp; Conditions</button>.
                        <span class="terms-lock-note">Open and scroll to the bottom to unlock this checkbox.</span>
                    </label>
                </div>
                <?php if (isset($formErrors['terms'])): ?>
                <div class="field-error" style="margin-top:8px;"> <?= htmlspecialchars($formErrors['terms']) ?></div>
                <?php endif; ?>
            </div>

        </div><!-- /.form-card -->

        <!-- Submit -->
        <div class="submit-section">
            <button type="submit" class="btn-submit" id="submitBtn" disabled>
                Submit Application
            </button>
            <p style="text-align:center;font-size:12px;color:var(--gray-400);margin-top:10px;">
                All fields and documents are required. A specific stall will be assigned later in the review process.
            </p>
        </div>

    </form>

    <!-- Terms & Conditions modal: scroll to the bottom to unlock the checkbox -->
    <div class="modal fade" id="termsModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">GJC Campus Stall Rental &mdash; Terms &amp; Conditions</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="terms-scroll-box" id="termsBox">
                        <p>By submitting this application, you agree to the following terms set forth by the administration of General de Jesus College (GJC):</p>
                        <p><strong>1. Eligibility.</strong> Applicants must be of legal age (18+) and must not have any outstanding financial obligations to GJC. Applications from individuals with existing violations of school policy may be rejected at the institution's discretion.</p>
                        <p><strong>2. Application Review.</strong> All submitted applications are subject to review by the GJC administration. Submission of this form does not guarantee approval. The school reserves the right to approve, reject, or defer any application without prior notice.</p>
                        <p><strong>3. Document Accuracy.</strong> All uploaded documents must be authentic, current, and valid. Submission of falsified or expired documents is grounds for immediate rejection and may result in legal action.</p>
                        <p><strong>4. Nutritional Compliance.</strong> Approved vendors must comply with GJC's nutritional policy. Products flagged under the Restricted Products list (including high-sugar beverages, energy drinks, and items of low nutritional value) are prohibited from being sold on campus.</p>
                        <p><strong>5. Lease Obligations.</strong> Approved vendors will be required to sign a formal lease agreement and pay the applicable monthly rental rate. Failure to pay rent on time may result in stall suspension or termination of the lease.</p>
                        <p><strong>6. Operational Standards.</strong> Vendors must maintain cleanliness, observe proper waste disposal, and adhere to campus operating hours. Any violation of operational standards may result in temporary closure or lease termination.</p>
                        <p><strong>7. Data Privacy.</strong> Personal information and documents submitted through this form are collected solely for the purpose of processing your application. Your data will be handled in accordance with the Data Privacy Act of 2012 (RA 10173) and will not be shared with third parties without your consent.</p>
                        <p><strong>8. Application Lock.</strong> Submitting this form reserves the stall for administrative review. Misuse of this system (e.g. submitting multiple applications for the same stall) may result in disqualification.</p>
                        <p style="font-weight:700; color:#064420;">By checking the box below, you confirm that you have read, understood, and agree to all of the above terms and conditions.</p>
                    </div>
                    <div class="scroll-hint" id="scrollHint">&#8681; Scroll to the bottom of the terms to unlock the checkbox</div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-modal-close" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>
</div><!-- /.page-wrap -->

<footer style="background:#052e16;color:rgba(255,255,255,.65);text-align:center;padding:20px;font-size:12px;">
    &copy; <?= date('Y') ?> General de Jesus College &mdash; GenPay
</footer>

<script src="<?= JS_URL ?>/bootstrap.bundle.min.js"></script>
<script>
// ── File upload handler ──────────────────────────────────────
function handleFile(input, field, isImage) {
    const file    = input.files[0];
    const drop    = document.getElementById('drop-' + field);
    const icon    = document.getElementById('icon-' + field);
    const nameEl  = document.getElementById('name-' + field);
    const preview = document.getElementById('preview-' + field);

    if (!file) return;

    drop.classList.add('has-file');
    nameEl.style.display = 'block';
    nameEl.textContent   = file.name + ' (' + (file.size / 1024).toFixed(1) + ' KB)';

    if (isImage && preview && file.type.startsWith('image/')) {
        const reader = new FileReader();
        reader.onload = e => {
            preview.src = e.target.result;
            preview.style.display = 'block';
        };
        reader.readAsDataURL(file);
        if (icon) icon.style.display = 'none';
    } else {
        const ext = file.name.split('.').pop().toUpperCase();
        if (icon) { icon.textContent = ext === 'PDF' ? '' : ''; }
    }
    checkSubmitReady();
}

// ── Drag & drop (profile picture) ─────────────────────────────
function handleDragOver(e, dropEl) {
    e.preventDefault();
    dropEl.classList.add('is-dragover');
}
function handleDragLeave(e, dropEl) {
    dropEl.classList.remove('is-dragover');
}
function handleDrop(e, dropEl, field, isImage) {
    e.preventDefault();
    dropEl.classList.remove('is-dragover');
    const dropped = e.dataTransfer.files;
    if (!dropped || !dropped.length) return;
    const input = document.getElementById('file-' + field);
    input.files = dropped; // wire the dropped file into the real <input type=file>
    handleFile(input, field, isImage);
}

// ── Terms modal scroll-lock ───────────────────────────────────
const termsBox   = document.getElementById('termsBox');
const termsChk   = document.getElementById('terms_accepted');
const scrollHint = document.getElementById('scrollHint');
const termsWrap  = document.getElementById('termsCheckWrap');

termsBox.addEventListener('scroll', function () {
    const atBottom = this.scrollTop + this.clientHeight >= this.scrollHeight - 10;
    if (atBottom && termsChk.disabled) {
        termsChk.disabled = false;
        scrollHint.style.display = 'none';
        termsWrap.classList.add('is-active');
    }
});

termsChk.addEventListener('change', checkSubmitReady);

// ── Submit button gating ─────────────────────────────────────
function checkSubmitReady() {
    const allFiles = ['profile_picture','business_permit','sanitary_permit','gjc_requirements','clearance']
        .every(f => document.getElementById('file-' + f)?.files?.length > 0);
    const termsOk = termsChk.checked;
    document.getElementById('submitBtn').disabled = !(termsOk && allFiles);
}

// ── Contact number: enforce 09XXXXXXXXX ──────────────────────
const contactInput = document.getElementById('contact_number');
if (contactInput) {
    contactInput.addEventListener('input', function () {
        this.value = this.value.replace(/\D/g, '').slice(0, 11);
    });
}

// ── Form submit: show loading state ─────────────────────────
document.getElementById('applyForm')?.addEventListener('submit', function () {
    const btn = document.getElementById('submitBtn');
    btn.disabled = true;
    btn.textContent = 'Submitting… Please wait';
});

// ── On load: if terms already checked (PHP error redisplay) ──
if (termsChk && termsChk.checked) {
    termsChk.disabled = false;
    if (scrollHint) scrollHint.style.display = 'none';
    termsWrap.classList.add('is-active');
    checkSubmitReady();
}
</script>

</body>
</html>
