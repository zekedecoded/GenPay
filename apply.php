<?php
// ============================================================
//  apply.php - Public Stall Application Form (general submission)
//  Stall assignment now happens at Step 4 (Approval/Award) of the
//  admin pipeline, not at submission time - no stall pick or lock here.
// ============================================================
require_once __DIR__ . "/connection/config.php";
require_once __DIR__ . "/connection/pdo.php";
require_once __DIR__ . "/connection/app.php";

gjc_ensure_stall_application_workflow_schema($db);

// ── Upload constants ──────────────────────────────────────────
const MAX_FILE_BYTES = 5 * 1024 * 1024; // 5 MB
const ALLOWED_MIMES = ["image/jpeg", "image/png", "application/pdf"];
const ALLOWED_EXT = ["jpg", "jpeg", "png", "pdf"];
const IMG_ONLY_MIMES = ["image/jpeg", "image/png"];
const IMG_ONLY_EXT = ["jpg", "jpeg", "png"];

$formErrors = [];
$old = [];
$success = false;
$appId = null;

// ── POST - validate, upload, insert ──────────────────────────
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $old = [
        "first_name" => trim($_POST["first_name"] ?? ""),
        "middle_name" => trim($_POST["middle_name"] ?? ""),
        "last_name" => trim($_POST["last_name"] ?? ""),
        "suffix" => trim($_POST["suffix"] ?? ""),
        "sex" => trim($_POST["sex"] ?? ""),
        "street" => trim($_POST["street"] ?? ""),
        "barangay" => trim($_POST["barangay"] ?? ""),
        "city" => trim($_POST["city"] ?? ""),
        "province" => trim($_POST["province"] ?? ""),
        "business_name" => trim($_POST["business_name"] ?? ""),
        "contact_number" => trim($_POST["contact_number"] ?? ""),
        "email" => trim($_POST["email"] ?? ""),
    ];

    // Text field validation
    if (empty($old["first_name"])) {
        $formErrors["first_name"] = "First name is required.";
    } elseif (mb_strlen($old["first_name"]) > 60) {
        $formErrors["first_name"] = "Must not exceed 60 characters.";
    }

    if (mb_strlen($old["middle_name"]) > 60) {
        $formErrors["middle_name"] = "Must not exceed 60 characters.";
    }

    if (empty($old["last_name"])) {
        $formErrors["last_name"] = "Last name is required.";
    } elseif (mb_strlen($old["last_name"]) > 60) {
        $formErrors["last_name"] = "Must not exceed 60 characters.";
    }

    if (mb_strlen($old["suffix"]) > 20) {
        $formErrors["suffix"] = "Must not exceed 20 characters.";
    }

    if (!in_array($old["sex"], ["male", "female"], true)) {
        $formErrors["sex"] = "Please select a sex.";
    }

    if (empty($old["street"])) {
        $formErrors["street"] = "Street is required.";
    } elseif (mb_strlen($old["street"]) > 150) {
        $formErrors["street"] = "Must not exceed 150 characters.";
    }

    if (empty($old["barangay"])) {
        $formErrors["barangay"] = "Barangay is required.";
    } elseif (mb_strlen($old["barangay"]) > 100) {
        $formErrors["barangay"] = "Must not exceed 100 characters.";
    }

    if (empty($old["city"])) {
        $formErrors["city"] = "City/Municipality is required.";
    } elseif (mb_strlen($old["city"]) > 100) {
        $formErrors["city"] = "Must not exceed 100 characters.";
    }

    if (empty($old["province"])) {
        $formErrors["province"] = "Province is required.";
    } elseif (mb_strlen($old["province"]) > 100) {
        $formErrors["province"] = "Must not exceed 100 characters.";
    }

    if (empty($old["business_name"])) {
        $formErrors["business_name"] = "Business name is required.";
    } elseif (mb_strlen($old["business_name"]) > 120) {
        $formErrors["business_name"] = "Must not exceed 120 characters.";
    }

    if (!preg_match('/^09\d{9}$/', $old["contact_number"])) {
        $formErrors["contact_number"] =
            "Must be in 09XXXXXXXXX format (11 digits starting with 09).";
    }

    if (!filter_var($old["email"], FILTER_VALIDATE_EMAIL)) {
        $formErrors["email"] = "Please enter a valid email address.";
    }

    if (empty($_POST["terms_accepted"])) {
        $formErrors["terms"] =
            "You must scroll through and accept the Terms & Conditions.";
    }

    // File field rules
    $fileRules = [
        "profile_picture" => [
            "label" => "Profile Picture",
            "mimes" => IMG_ONLY_MIMES,
            "exts" => IMG_ONLY_EXT,
        ],
        "business_permit" => [
            "label" => "Business Permit",
            "mimes" => ALLOWED_MIMES,
            "exts" => ALLOWED_EXT,
        ],
        "sanitary_permit" => [
            "label" => "Sanitary Permit",
            "mimes" => ALLOWED_MIMES,
            "exts" => ALLOWED_EXT,
        ],
        "gjc_requirements" => [
            "label" => "GJC Requirements",
            "mimes" => ALLOWED_MIMES,
            "exts" => ALLOWED_EXT,
        ],
        "clearance" => [
            "label" => "Clearance",
            "mimes" => ALLOWED_MIMES,
            "exts" => ALLOWED_EXT,
        ],
    ];
    $fileData = [];

    foreach ($fileRules as $field => $rule) {
        $file = $_FILES[$field] ?? null;
        if (
            !$file ||
            $file["error"] === UPLOAD_ERR_NO_FILE ||
            empty($file["tmp_name"])
        ) {
            $formErrors[$field] = $rule["label"] . " is required.";
            continue;
        }
        if ($file["error"] !== UPLOAD_ERR_OK) {
            $formErrors[$field] =
                $rule["label"] . " upload error (code " . $file["error"] . ").";
            continue;
        }
        if ($file["size"] > MAX_FILE_BYTES) {
            $formErrors[$field] = $rule["label"] . " exceeds the 5 MB limit.";
            continue;
        }
        $ext = strtolower(pathinfo($file["name"], PATHINFO_EXTENSION));
        if (!in_array($ext, $rule["exts"], true)) {
            $formErrors[$field] =
                $rule["label"] .
                " must be " .
                implode(", ", array_map("strtoupper", $rule["exts"])) .
                ".";
            continue;
        }
        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file["tmp_name"]);
        if (!in_array($mime, $rule["mimes"], true)) {
            $formErrors[$field] = $rule["label"] . " has an invalid file type.";
            continue;
        }
        $fileData[$field] = ["tmp" => $file["tmp_name"], "ext" => $ext];
    }

    // ── Process if clean ──────────────────────────────────────
    if (empty($formErrors)) {
        $tmpToken = "tmp_" . bin2hex(random_bytes(8));
        $tmpDir = BASE_PATH . "/uploads/stall_applications/" . $tmpToken;

        try {
            $db->beginTransaction();

            // Create temp dir
            if (!mkdir($tmpDir, 0755, true)) {
                throw new RuntimeException("DIR_CREATE");
            }

            // Move files into temp dir
            $tmpPaths = [];
            foreach ($fileData as $field => $info) {
                $fname =
                    $field .
                    "_" .
                    time() .
                    mt_rand(1000, 9999) .
                    "." .
                    $info["ext"];
                $dest = $tmpDir . "/" . $fname;
                if (!move_uploaded_file($info["tmp"], $dest)) {
                    throw new RuntimeException("MOVE_" . strtoupper($field));
                }
                $tmpPaths[$field] = $fname;
            }

            // Insert record (paths updated after we have the ID) — no stall_id yet;
            // assigned by the admin at Step 4 (Approval/Award) of the pipeline.
            $proprietorName = trim(
                implode(
                    " ",
                    array_filter(
                        [
                            $old["first_name"],
                            $old["middle_name"],
                            $old["last_name"],
                            $old["suffix"],
                        ],
                        fn($part) => $part !== "",
                    ),
                ),
            );

            $ins = $db->prepare(
                "INSERT INTO stall_applications
                    (business_name, proprietor_name, first_name, middle_name, last_name, suffix, sex,
                     street, barangay, city, province, contact_number, email,
                     profile_picture, business_permit, sanitary_permit, gjc_requirements,
                     clearance, terms_accepted, status, current_step)
                 VALUES (?,?,?,?,?,?,?, ?,?,?,?, ?,?, 'pending_path','pending_path','pending_path','pending_path','pending_path', 1, 'review', 1)",
            );
            $ins->execute([
                $old["business_name"],
                $proprietorName,
                $old["first_name"],
                $old["middle_name"] !== "" ? $old["middle_name"] : null,
                $old["last_name"],
                $old["suffix"] !== "" ? $old["suffix"] : null,
                $old["sex"],
                $old["street"],
                $old["barangay"],
                $old["city"],
                $old["province"],
                $old["contact_number"],
                $old["email"],
            ]);
            $appId = (int) $db->lastInsertId();

            // Rename tmp dir to final ID
            $finalDir = BASE_PATH . "/uploads/stall_applications/" . $appId;
            if (!rename($tmpDir, $finalDir)) {
                throw new RuntimeException("DIR_RENAME");
            }

            // Build real relative paths and update record
            $realPaths = [];
            foreach ($tmpPaths as $field => $fname) {
                $realPaths[$field] =
                    "uploads/stall_applications/" . $appId . "/" . $fname;
            }

            $db->prepare(
                "UPDATE stall_applications
                 SET profile_picture=?, business_permit=?, sanitary_permit=?,
                     gjc_requirements=?, clearance=?
                 WHERE id=?",
            )->execute([
                $realPaths["profile_picture"],
                $realPaths["business_permit"],
                $realPaths["sanitary_permit"],
                $realPaths["gjc_requirements"],
                $realPaths["clearance"],
                $appId,
            ]);

            $db->commit();
            $success = true;
        } catch (Throwable $e) {
            $db->rollBack();
            // Clean up temp files
            if (is_dir($tmpDir)) {
                array_map("unlink", glob($tmpDir . "/*") ?: []);
                @rmdir($tmpDir);
            }
            $formErrors["general"] =
                "A server error occurred. Please try again. (ref: " .
                $e->getMessage() .
                ")";
        }
    }
}

try {
    $activeRestrictions = $db->query(
        "SELECT product_name, category, reason, match_type
         FROM restricted_products
         WHERE is_active = 1
         ORDER BY category ASC, product_name ASC"
    )->fetchAll(PDO::FETCH_ASSOC);
} catch (\Throwable $e) {
    $activeRestrictions = [];
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
    <title>Apply for a Stall | GenPay</title>
    <meta name="description" content="Submit your stall application at General de Jesus College. Fill in your business details and upload required documents.">
    <link rel="stylesheet" href="<?= CSS_URL ?>/bootstrap.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer">
    <style>
        :root {
            --green-900: #052e16; --green-800: #064420; --green-700: #15803d;
            --green-500: #22c55e; --green-400: #4ade80; --green-100: #dcfce7;
            --red-500: #ef4444; --red-100: #fee2e2; --red-700: #b91c1c;
            --amber-500: #f59e0b;
            --gray-50: #f9fafb; --gray-100: #f3f4f6; --gray-200: #e5e7eb;
            --gray-400: #9ca3af; --gray-600: #4b5563; --gray-700: #374151; --gray-800: #1f2937;
        }

        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background: linear-gradient(160deg, #f0fdf4 0%, #f9fafb 60%);
            color: var(--gray-800); min-height: 100vh;
        }

        .page-wrap { max-width: 760px; }

        .form-close {
            /* Positioned here (not via Bootstrap top-0/end-0/mt-4/me-3 utilities)
               because the .page-wrap container's own padding throws those off by
               a few px, leaving the button clipped by the card's rounded corner. */
            position: absolute; top: 6px; right: 12px;
            width: 36px; height: 36px;
            background: #fff; color: var(--gray-600);
            box-shadow: 0 4px 24px rgba(0,0,0,.1); transition: all .2s;
        }
        .form-close:hover { background: var(--red-100); color: var(--red-700); transform: rotate(90deg); }

        /* ── Brand-styled section headings (replace generic Bootstrap hr/legend) ── */
        .section-heading {
            font-size: 11px; font-weight: 800; text-transform: uppercase;
            letter-spacing: .1em; color: var(--green-700);
        }
        .section-heading::after { content: ''; flex: 1; height: 1px; background: var(--green-100); }
        .req { color: var(--red-500); margin-left: 2px; }

        /* ── Bootstrap form-control restyle to match brand ── */
        .form-control, .form-select {
            border: 2px solid var(--gray-200); border-radius: 10px;
            font-size: 15px; padding: .65rem 1rem;
            background-color: var(--gray-50);
            width: 100%; min-width: 0;
        }
        .form-control:focus, .form-select:focus {
            border-color: var(--green-500); background-color: #fff;
            box-shadow: 0 0 0 3px rgba(34,197,94,.15);
        }
        .form-control.is-invalid:focus, .form-select.is-invalid:focus { box-shadow: 0 0 0 3px rgba(239,68,68,.12); }
        .form-label { font-size: 13px; font-weight: 700; color: var(--gray-700); }
        .invalid-feedback { font-size: 12px; font-weight: 600; }
        .form-text { font-size: 11px; }

        /* ── FILE UPLOADS (no Bootstrap equivalent for drag/drop tiles) ── */
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
            border: 3px solid #fff; box-shadow: 0 4px 14px rgba(0,0,0,.14);
        }

        /* ── TERMS ── */
        .terms-link {
            background: none; border: none; padding: 0; cursor: pointer;
            font-family: inherit; font-size: inherit; font-weight: 700;
            color: var(--green-700); text-decoration: underline;
            text-decoration-color: var(--green-400); text-underline-offset: 2px;
        }
        .terms-link:hover { color: var(--green-800); }
        .terms-lock-note { font-size: 11px; font-weight: 600; color: var(--gray-400); }

        .terms-scroll-box {
            height: 320px; overflow-y: auto;
            background: var(--gray-50); border-radius: 10px;
            border: 2px solid var(--gray-200); font-size: 13px;
            line-height: 1.7; color: var(--gray-600);
            scroll-behavior: smooth;
        }
        .terms-scroll-box p { margin-bottom: 10px; }

        .terms-check-wrap {
            border: 2px solid var(--gray-200); border-radius: 10px; transition: border-color .2s;
            display: flex; align-items: flex-start; gap: 10px;
        }
        .terms-check-wrap.is-invalid { border-color: var(--red-500); background: var(--red-100); }
        .terms-check-wrap.is-active { border-color: var(--green-400); background: var(--green-100); }
        /* Override Bootstrap's .form-check-input negative margin/float, which
           fights with the flex layout above and pushes the checkbox out of
           position - pin it to a fixed size and let flex handle placement. */
        .terms-check-wrap .form-check-input {
            margin: 2px 0 0 0; flex: 0 0 18px; width: 18px; height: 18px;
            accent-color: var(--green-500); cursor: pointer;
        }
        .terms-check-wrap .form-check-input:disabled { opacity: .4; cursor: not-allowed; }
        .terms-check-label { flex: 1 1 auto; margin: 0; font-size: 13px; font-weight: 600; color: var(--gray-700); line-height: 1.5; }
        .scroll-hint { font-size: 12px; color: var(--amber-500); font-weight: 700; }

        /* ── SUBMIT ── */
        .btn-submit {
            background: linear-gradient(135deg, var(--green-400), var(--green-500));
            color: var(--green-900); font-size: 17px; font-weight: 800;
            box-shadow: 0 6px 24px rgba(34,197,94,.35); border: none;
        }
        .btn-submit:hover:not(:disabled) {
            transform: translateY(-2px); box-shadow: 0 10px 30px rgba(34,197,94,.45);
        }
        .btn-submit:disabled { opacity: .55; transform: none; }

        /* ── SUCCESS ── */
        .success-icon { font-size: 72px; }
        .success-title { color: var(--green-800); }
        .success-ref { background: var(--green-100); color: var(--green-700); letter-spacing: .05em; }
    </style>
</head>
<body>

<div class="page-wrap container py-5 position-relative">
<a href="<?= BASE_URL ?>/stalls" class="form-close rounded-circle d-flex align-items-center justify-content-center" aria-label="Close and return to stall map"><i class="fa-solid fa-xmark"></i></a>

<?php if ($success): ?>
    <!-- Success state -->
    <div class="card shadow-sm rounded-4 text-center p-5">
        <div class="success-icon mb-3"><i class="fa-solid fa-circle-check text-success"></i></div>
        <h2 class="success-title fw-bold mb-2">Application Submitted!</h2>
        <p class="success-sub text-muted mx-auto mb-4" style="max-width:440px;">
            Your general stall application has been received.
            Our team will review your documents and contact you via email about next steps,
            including stall assignment.
        </p>
        <div class="success-ref d-inline-block rounded-3 fw-bold fs-5 px-4 py-2 mx-auto">Application #<?= str_pad(
            $appId,
            5,
            "0",
            STR_PAD_LEFT,
        ) ?></div>
    </div>

<?php else: ?>
    <!-- General error alert -->
    <?php if (!empty($formErrors["general"])): ?>
    <div class="alert alert-danger d-flex align-items-start gap-2" role="alert">
        <i class="fa-solid fa-triangle-exclamation mt-1"></i>
        <span><?= htmlspecialchars($formErrors["general"]) ?></span>
    </div>
    <?php endif; ?>

    <!-- THE FORM -->
    <form method="POST"
          action="<?= BASE_URL ?>/apply"
          enctype="multipart/form-data"
          id="applyForm"
          novalidate>

        <div class="card shadow-sm rounded-4">
            <div class="card-body p-4 p-md-5">

                <!-- Proprietor Information & Address -->
                <div class="section-heading d-flex align-items-center gap-2 mb-3"><i class="fa-solid fa-id-card"></i> Personal Information</div>

                <div class="row g-3 mb-3">
                    <div class="col-6 col-md">
                        <label class="form-label" for="first_name">First Name <span class="req">*</span></label>
                        <input type="text" id="first_name" name="first_name"
                               placeholder="Juan"
                               value="<?= htmlspecialchars(
                                   $old["first_name"] ?? "",
                               ) ?>"
                               class="form-control <?= isset(
                                   $formErrors["first_name"],
                               )
                                   ? "is-invalid"
                                   : "" ?>"
                               maxlength="60">
                        <?php if (isset($formErrors["first_name"])): ?>
                        <div class="invalid-feedback d-block"><i class="fa-solid fa-circle-exclamation"></i> <?= htmlspecialchars(
                            $formErrors["first_name"],
                        ) ?></div>
                        <?php endif; ?>
                    </div>
                    <div class="col-6 col-md">
                        <label class="form-label" for="middle_name">Middle Name</label>
                        <input type="text" id="middle_name" name="middle_name"
                               placeholder="Santos"
                               value="<?= htmlspecialchars(
                                   $old["middle_name"] ?? "",
                               ) ?>"
                               class="form-control <?= isset(
                                   $formErrors["middle_name"],
                               )
                                   ? "is-invalid"
                                   : "" ?>"
                               maxlength="60">
                        <?php if (isset($formErrors["middle_name"])): ?>
                        <div class="invalid-feedback d-block"><i class="fa-solid fa-circle-exclamation"></i> <?= htmlspecialchars(
                            $formErrors["middle_name"],
                        ) ?></div>
                        <?php endif; ?>
                    </div>
                    <div class="col-6 col-md">
                        <label class="form-label" for="last_name">Last Name <span class="req">*</span></label>
                        <input type="text" id="last_name" name="last_name"
                               placeholder="Dela Cruz"
                               value="<?= htmlspecialchars(
                                   $old["last_name"] ?? "",
                               ) ?>"
                               class="form-control <?= isset(
                                   $formErrors["last_name"],
                               )
                                   ? "is-invalid"
                                   : "" ?>"
                               maxlength="60">
                        <?php if (isset($formErrors["last_name"])): ?>
                        <div class="invalid-feedback d-block"><i class="fa-solid fa-circle-exclamation"></i> <?= htmlspecialchars(
                            $formErrors["last_name"],
                        ) ?></div>
                        <?php endif; ?>
                    </div>
                    <div class="col-6 col-md">
                        <label class="form-label" for="suffix">Suffix</label>
                        <input type="text" id="suffix" name="suffix"
                               placeholder="Jr., Sr., III"
                               value="<?= htmlspecialchars(
                                   $old["suffix"] ?? "",
                               ) ?>"
                               class="form-control <?= isset(
                                   $formErrors["suffix"],
                               )
                                   ? "is-invalid"
                                   : "" ?>"
                               maxlength="20">
                        <?php if (isset($formErrors["suffix"])): ?>
                        <div class="invalid-feedback d-block"><i class="fa-solid fa-circle-exclamation"></i> <?= htmlspecialchars(
                            $formErrors["suffix"],
                        ) ?></div>
                        <?php endif; ?>
                    </div>
                    <div class="col-6 col-md">
                        <label class="form-label" for="sex">Sex <span class="req">*</span></label>
                        <select id="sex" name="sex"
                                class="form-select <?= isset($formErrors["sex"])
                                    ? "is-invalid"
                                    : "" ?>">
                            <option value="" disabled <?= ($old["sex"] ??
                                "") ===
                            ""
                                ? "selected"
                                : "" ?>>Select</option>
                            <option value="male" <?= ($old["sex"] ?? "") ===
                            "male"
                                ? "selected"
                                : "" ?>>Male</option>
                            <option value="female" <?= ($old["sex"] ?? "") ===
                            "female"
                                ? "selected"
                                : "" ?>>Female</option>
                        </select>
                        <?php if (isset($formErrors["sex"])): ?>
                        <div class="invalid-feedback d-block"><i class="fa-solid fa-circle-exclamation"></i> <?= htmlspecialchars(
                            $formErrors["sex"],
                        ) ?></div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="row g-3 mb-3">
                    <div class="col-12">
                        <label class="form-label" for="street">Street <span class="req">*</span></label>
                        <input type="text" id="street" name="street"
                               placeholder="House No., Street Name"
                               value="<?= htmlspecialchars(
                                   $old["street"] ?? "",
                               ) ?>"
                               class="form-control <?= isset(
                                   $formErrors["street"],
                               )
                                   ? "is-invalid"
                                   : "" ?>"
                               maxlength="150">
                        <?php if (isset($formErrors["street"])): ?>
                        <div class="invalid-feedback d-block"><i class="fa-solid fa-circle-exclamation"></i> <?= htmlspecialchars(
                            $formErrors["street"],
                        ) ?></div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="row g-3 mb-3">
                    <div class="col-12 col-md-6">
                        <label class="form-label" for="barangay">Barangay <span class="req">*</span></label>
                        <input type="text" id="barangay" name="barangay"
                               placeholder="e.g. San Isidro"
                               value="<?= htmlspecialchars(
                                   $old["barangay"] ?? "",
                               ) ?>"
                               class="form-control <?= isset(
                                   $formErrors["barangay"],
                               )
                                   ? "is-invalid"
                                   : "" ?>"
                               maxlength="100">
                        <?php if (isset($formErrors["barangay"])): ?>
                        <div class="invalid-feedback d-block"><i class="fa-solid fa-circle-exclamation"></i> <?= htmlspecialchars(
                            $formErrors["barangay"],
                        ) ?></div>
                        <?php endif; ?>
                    </div>
                    <div class="col-12 col-md-6">
                        <label class="form-label" for="city">City / Municipality <span class="req">*</span></label>
                        <input type="text" id="city" name="city"
                               placeholder="e.g. Cabanatuan City"
                               value="<?= htmlspecialchars(
                                   $old["city"] ?? "",
                               ) ?>"
                               class="form-control <?= isset(
                                   $formErrors["city"],
                               )
                                   ? "is-invalid"
                                   : "" ?>"
                               maxlength="100">
                        <?php if (isset($formErrors["city"])): ?>
                        <div class="invalid-feedback d-block"><i class="fa-solid fa-circle-exclamation"></i> <?= htmlspecialchars(
                            $formErrors["city"],
                        ) ?></div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="row g-3 mb-4">
                    <div class="col-12">
                        <label class="form-label" for="province">Province <span class="req">*</span></label>
                        <input type="text" id="province" name="province"
                               placeholder="e.g. Nueva Ecija"
                               value="<?= htmlspecialchars(
                                   $old["province"] ?? "",
                               ) ?>"
                               class="form-control <?= isset(
                                   $formErrors["province"],
                               )
                                   ? "is-invalid"
                                   : "" ?>"
                               maxlength="100">
                        <?php if (isset($formErrors["province"])): ?>
                        <div class="invalid-feedback d-block"><i class="fa-solid fa-circle-exclamation"></i> <?= htmlspecialchars(
                            $formErrors["province"],
                        ) ?></div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Business & Contact Information -->
                <div class="section-heading d-flex align-items-center gap-2 mb-3"><i class="fa-solid fa-store"></i> Business & Contact Information</div>

                <div class="row g-3 mb-3">
                    <div class="col-12">
                        <label class="form-label" for="business_name">Business Name <span class="req">*</span></label>
                        <input type="text" id="business_name" name="business_name"
                               placeholder="e.g. Maria's Snack Corner"
                               value="<?= htmlspecialchars(
                                   $old["business_name"] ?? "",
                               ) ?>"
                               class="form-control <?= isset(
                                   $formErrors["business_name"],
                               )
                                   ? "is-invalid"
                                   : "" ?>"
                               maxlength="120">
                        <?php if (isset($formErrors["business_name"])): ?>
                        <div class="invalid-feedback d-block"><i class="fa-solid fa-circle-exclamation"></i> <?= htmlspecialchars(
                            $formErrors["business_name"],
                        ) ?></div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="row g-3 mb-4">
                    <div class="col-12 col-md-6">
                        <label class="form-label" for="contact_number">Contact Number <span class="req">*</span></label>
                        <input type="tel" id="contact_number" name="contact_number"
                               placeholder="09XXXXXXXXX"
                               value="<?= htmlspecialchars(
                                   $old["contact_number"] ?? "",
                               ) ?>"
                               class="form-control <?= isset(
                                   $formErrors["contact_number"],
                               )
                                   ? "is-invalid"
                                   : "" ?>"
                               maxlength="11" pattern="09[0-9]{9}">
                        <div class="form-text text-muted">Format: 09XXXXXXXXX (11 digits)</div>
                        <?php if (isset($formErrors["contact_number"])): ?>
                        <div class="invalid-feedback d-block"><i class="fa-solid fa-circle-exclamation"></i> <?= htmlspecialchars(
                            $formErrors["contact_number"],
                        ) ?></div>
                        <?php endif; ?>
                    </div>
                    <div class="col-12 col-md-6">
                        <label class="form-label" for="email">Email Address <span class="req">*</span></label>
                        <input type="email" id="email" name="email"
                               placeholder="youremail@example.com"
                               value="<?= htmlspecialchars(
                                   $old["email"] ?? "",
                               ) ?>"
                               class="form-control <?= isset(
                                   $formErrors["email"],
                               )
                                   ? "is-invalid"
                                   : "" ?>">
                        <?php if (isset($formErrors["email"])): ?>
                        <div class="invalid-feedback d-block"><i class="fa-solid fa-circle-exclamation"></i> <?= htmlspecialchars(
                            $formErrors["email"],
                        ) ?></div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Profile Picture -->
                <div class="section-heading d-flex align-items-center gap-2 mb-3"><i class="fa-solid fa-camera"></i> Profile Picture</div>
                <p class="form-text text-muted mb-3">Upload a clear photo of the proprietor. JPG or PNG only, max 5 MB.</p>
                <div class="mb-4">
                    <div class="file-drop avatar-drop <?= isset(
                        $formErrors["profile_picture"],
                    )
                        ? "is-invalid"
                        : "" ?>"
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
                            <div class="file-icon" id="icon-profile_picture"><i class="fa-solid fa-user"></i></div>
                            <img class="file-preview" id="preview-profile_picture" alt="Preview">
                        </div>
                        <div class="file-label">Click or drag a photo here</div>
                        <div class="file-sub">JPG, PNG • Max 5 MB</div>
                        <div class="file-name-display" id="name-profile_picture"></div>
                    </div>
                    <?php if (isset($formErrors["profile_picture"])): ?>
                    <div class="invalid-feedback d-block"><i class="fa-solid fa-circle-exclamation"></i> <?= htmlspecialchars(
                        $formErrors["profile_picture"],
                    ) ?></div>
                    <?php endif; ?>
                </div>

                <!-- Required Documents -->
                <div class="section-heading d-flex align-items-center gap-2 mb-3"><i class="fa-solid fa-folder-open"></i> Required Documents</div>
                <p class="form-text text-muted mb-3">Upload PDF, JPG, or PNG. Max 5 MB per file.</p>
                <div class="row g-3 mb-4">
                    <?php
                    $docFields = [
                        "business_permit" => [
                            "icon" => "fa-briefcase",
                            "label" => "Business Permit",
                        ],
                        "sanitary_permit" => [
                            "icon" => "fa-pump-soap",
                            "label" => "Sanitary Permit",
                        ],
                        "gjc_requirements" => [
                            "icon" => "fa-graduation-cap",
                            "label" => "GJC Requirements",
                        ],
                        "clearance" => [
                            "icon" => "fa-clipboard-check",
                            "label" => "Clearance",
                        ],
                    ];
                    foreach ($docFields as $field => $meta): ?>
                    <div class="col-12 col-md-6">
                        <label class="form-label"><?= $meta[
                            "label"
                        ] ?> <span class="req">*</span></label>
                        <div class="file-drop <?= isset($formErrors[$field])
                            ? "is-invalid"
                            : "" ?>"
                             id="drop-<?= $field ?>"
                             onclick="document.getElementById('file-<?= $field ?>').click()">
                            <input type="file" id="file-<?= $field ?>" name="<?= $field ?>"
                                   accept=".jpg,.jpeg,.png,.pdf,image/jpeg,image/png,application/pdf"
                                   style="display:none"
                                   onchange="handleFile(this,'<?= $field ?>',false)">
                            <div class="file-icon" id="icon-<?= $field ?>"><i class="fa-solid <?= $meta[
    "icon"
] ?>"></i></div>
                            <div class="file-label"><?= $meta["label"] ?></div>
                            <div class="file-sub">PDF, JPG, PNG • Max 5 MB</div>
                            <div class="file-name-display" id="name-<?= $field ?>"></div>
                        </div>
                        <?php if (isset($formErrors[$field])): ?>
                        <div class="invalid-feedback d-block"><i class="fa-solid fa-circle-exclamation"></i> <?= htmlspecialchars(
                            $formErrors[$field],
                        ) ?></div>
                        <?php endif; ?>
                    </div>
                    <?php endforeach;
                    ?>
                </div>

                <!-- Terms & Conditions -->
                <div class="section-heading d-flex align-items-center gap-2 mb-3"><i class="fa-solid fa-file-contract"></i> Terms & Conditions</div>

                <div class="terms-check-wrap p-3 <?= isset(
                    $formErrors["terms"],
                )
                    ? "is-invalid"
                    : "" ?>"
                     id="termsCheckWrap">
                    <input type="checkbox" class="form-check-input" id="terms_accepted" name="terms_accepted"
                           value="1" disabled
                           <?= !empty($_POST["terms_accepted"])
                               ? "checked"
                               : "" ?>>
                    <label class="terms-check-label" for="terms_accepted">
                        I have read and agree to the
                        <button type="button" class="terms-link" data-bs-toggle="modal" data-bs-target="#termsModal">GJC Campus Stall Rental Terms &amp; Conditions</button>.
                        <span class="terms-lock-note d-block">Open and scroll to the bottom to unlock this checkbox.</span>
                    </label>
                </div>
                <?php if (isset($formErrors["terms"])): ?>
                <div class="invalid-feedback d-block mt-2"><i class="fa-solid fa-circle-exclamation"></i> <?= htmlspecialchars(
                    $formErrors["terms"],
                ) ?></div>
                <?php endif; ?>

            </div><!-- /.card-body -->

            <!-- Submit -->
            <div class="card-footer bg-light p-4">
                <button type="submit" class="btn btn-submit w-100 rounded-pill py-3" id="submitBtn" disabled>
                    Submit Application
                </button>
                <p class="text-center text-muted mt-2 mb-0" style="font-size:12px;">
                    All fields and documents are required. A specific stall will be assigned later in the review process.
                </p>
            </div>
        </div><!-- /.card -->

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
                    <div class="terms-scroll-box p-3 mb-3" id="termsBox">
                        <p>By submitting this application, you agree to the following terms set forth by the administration of General de Jesus College (GJC):</p>
                        <p><strong>1. Eligibility.</strong> Applicants must be of legal age (18+) and must not have any outstanding financial obligations to GJC. Applications from individuals with existing violations of school policy may be rejected at the institution's discretion.</p>
                        <p><strong>2. Application Review.</strong> All submitted applications are subject to review by the GJC administration. Submission of this form does not guarantee approval. The school reserves the right to approve, reject, or defer any application without prior notice.</p>
                        <p><strong>3. Document Accuracy.</strong> All uploaded documents must be authentic, current, and valid. Submission of falsified or expired documents is grounds for immediate rejection and may result in legal action.</p>
                        <p><strong>4. Prohibited Products.</strong> Approved vendors must comply with GJC's product restriction policy administered through the GenPay platform. Selling, displaying, or offering any product on the list below is grounds for stall suspension or lease termination. This list is maintained live by the administration and is binding as of the date of your application.</p>
                        <?php if (!empty($activeRestrictions)): ?>
                        <ul style="margin:0 0 10px;padding-left:22px">
                            <?php foreach ($activeRestrictions as $r): ?>
                            <li><strong><?= htmlspecialchars($r['product_name']) ?></strong> (<?= htmlspecialchars(ucfirst($r['category'])) ?>) — <?= htmlspecialchars($r['reason']) ?></li>
                            <?php endforeach; ?>
                        </ul>
                        <?php else: ?>
                        <p>No products are currently on the restricted list. Vendors are advised to verify this section before operating, as the administration may update it at any time.</p>
                        <?php endif; ?>
                        <p><strong>5. Lease Obligations.</strong> Approved vendors will be required to sign a formal lease agreement and pay the applicable monthly rental rate. Failure to pay rent on time may result in stall suspension or termination of the lease.</p>
                        <p><strong>6. Operational Standards.</strong> Vendors must maintain cleanliness, observe proper waste disposal, and adhere to campus operating hours. Any violation of operational standards may result in temporary closure or lease termination.</p>
                        <p><strong>7. Data Privacy.</strong> Personal information and documents submitted through this form are collected solely for the purpose of processing your application. Your data will be handled in accordance with the Data Privacy Act of 2012 (RA 10173) and will not be shared with third parties without your consent.</p>
                        <p><strong>8. Application Lock.</strong> Submitting this form reserves the stall for administrative review. Misuse of this system (e.g. submitting multiple applications for the same stall) may result in disqualification.</p>
                        <p style="font-weight:700; color:#064420;">By checking the box below, you confirm that you have read, understood, and agree to all of the above terms and conditions.</p>
                    </div>
                    <div class="scroll-hint text-center" id="scrollHint"><i class="fa-solid fa-circle-chevron-down"></i> Scroll to the bottom of the terms to unlock the checkbox</div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-success rounded-pill" data-bs-dismiss="modal"><i class="fa-solid fa-xmark"></i> Close</button>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>
</div><!-- /.page-wrap -->

<footer style="background:#052e16;color:rgba(255,255,255,.65);text-align:center;padding:20px;font-size:12px;">
    &copy; <?= date("Y") ?> General de Jesus College &mdash; GenPay
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
