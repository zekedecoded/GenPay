<?php
// ============================================================
//  apply.php - Public Stall Application Form
// ============================================================
require_once __DIR__ . "/connection/config.php";
require_once __DIR__ . "/connection/pdo.php";
require_once __DIR__ . "/connection/app.php";
require_once __DIR__ . "/connection/StallManager.php";
require_once __DIR__ . "/connection/mailer.php";

gjc_ensure_stall_application_workflow_schema($db);

// ── Upload constants ──────────────────────────────────────────
const MAX_FILE_BYTES = 5 * 1024 * 1024;
const ALLOWED_MIMES  = ["image/jpeg", "image/png", "application/pdf"];
const ALLOWED_EXT    = ["jpg", "jpeg", "png", "pdf"];
const IMG_ONLY_MIMES = ["image/jpeg", "image/png"];
const IMG_ONLY_EXT   = ["jpg", "jpeg", "png"];

if (session_status() === PHP_SESSION_NONE) session_start();

$formErrors = [];
$old        = [];
$success    = false;
$appId      = null;
$successEmail = null;
$successMeeting = null;

// PRG landing: consume the one-time session flash set after a successful POST.
if (isset($_SESSION['apply_success']) && isset($_GET['submitted'])) {
    $flash          = $_SESSION['apply_success'];
    $appId          = (int) $flash['app_id'];
    $successEmail   = $flash['email'];
    $successMeeting = $flash['meeting'] ?? null;
    $success        = true;
    unset($_SESSION['apply_success']);
}

// ── POST - validate, upload, insert ──────────────────────────
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $old = [
        "first_name"          => trim($_POST["first_name"] ?? ""),
        "middle_name"         => trim($_POST["middle_name"] ?? ""),
        "last_name"           => trim($_POST["last_name"] ?? ""),
        "suffix"              => trim($_POST["suffix"] ?? ""),
        "sex"                 => trim($_POST["sex"] ?? ""),
        "street"              => trim($_POST["street"] ?? ""),
        "barangay"            => trim($_POST["barangay"] ?? ""),
        "city"                => trim($_POST["city"] ?? ""),
        "province"            => trim($_POST["province"] ?? ""),
        "business_name"       => trim($_POST["business_name"] ?? ""),
        "contact_number"      => trim($_POST["contact_number"] ?? ""),
        "email"               => trim($_POST["email"] ?? ""),
        "preferred_stall_id"  => trim($_POST["preferred_stall_id"] ?? ""),
    ];

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
        $formErrors["contact_number"] = "Must be in 09XXXXXXXXX format (11 digits starting with 09).";
    }
    if (!filter_var($old["email"], FILTER_VALIDATE_EMAIL)) {
        $formErrors["email"] = "Please enter a valid email address.";
    }
    if (empty($_POST["terms_accepted"])) {
        $formErrors["terms"] = "You must scroll through and accept the Terms & Conditions.";
    }

    $fileRules = [
        "profile_picture" => ["label" => "Profile Picture",   "mimes" => IMG_ONLY_MIMES, "exts" => IMG_ONLY_EXT],
        "business_permit" => ["label" => "Business Permit",   "mimes" => ALLOWED_MIMES,  "exts" => ALLOWED_EXT],
        "sanitary_permit" => ["label" => "Sanitary Permit",   "mimes" => ALLOWED_MIMES,  "exts" => ALLOWED_EXT],
        "gjc_requirements"=> ["label" => "GJC Requirements",  "mimes" => ALLOWED_MIMES,  "exts" => ALLOWED_EXT],
        "clearance"       => ["label" => "Clearance",         "mimes" => ALLOWED_MIMES,  "exts" => ALLOWED_EXT],
    ];
    $fileData = [];

    foreach ($fileRules as $field => $rule) {
        $file = $_FILES[$field] ?? null;
        if (!$file || $file["error"] === UPLOAD_ERR_NO_FILE || empty($file["tmp_name"])) {
            $formErrors[$field] = $rule["label"] . " is required.";
            continue;
        }
        if ($file["error"] !== UPLOAD_ERR_OK) {
            $formErrors[$field] = $rule["label"] . " upload error (code " . $file["error"] . ").";
            continue;
        }
        if ($file["size"] > MAX_FILE_BYTES) {
            $formErrors[$field] = $rule["label"] . " exceeds the 5 MB limit.";
            continue;
        }
        $ext = strtolower(pathinfo($file["name"], PATHINFO_EXTENSION));
        if (!in_array($ext, $rule["exts"], true)) {
            $formErrors[$field] = $rule["label"] . " must be " . implode(", ", array_map("strtoupper", $rule["exts"])) . ".";
            continue;
        }
        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file["tmp_name"]);
        if (!in_array($mime, $rule["mimes"], true)) {
            $formErrors[$field] = $rule["label"] . " has an invalid file type.";
            continue;
        }
        $fileData[$field] = ["tmp" => $file["tmp_name"], "ext" => $ext];
    }

    // Requirement 2 — hard filter: the Business Permit is mandatory. This is an
    // explicit server-side gate (independent of the HTML 'required'/JS checks),
    // so a submission can never be accepted without a valid business permit
    // upload even if the client-side validation is bypassed.
    if (empty($fileData["business_permit"]) && !isset($formErrors["business_permit"])) {
        $formErrors["business_permit"] = "Business Permit is required.";
    }

    if (empty($formErrors)) {
        $tmpToken = "tmp_" . bin2hex(random_bytes(8));
        $tmpDir   = BASE_PATH . "/uploads/stall_applications/" . $tmpToken;

        try {
            $db->beginTransaction();

            if (!mkdir($tmpDir, 0755, true)) {
                throw new RuntimeException("DIR_CREATE");
            }

            $tmpPaths = [];
            foreach ($fileData as $field => $info) {
                $fname = $field . "_" . time() . mt_rand(1000, 9999) . "." . $info["ext"];
                $dest  = $tmpDir . "/" . $fname;
                if (!move_uploaded_file($info["tmp"], $dest)) {
                    throw new RuntimeException("MOVE_" . strtoupper($field));
                }
                $tmpPaths[$field] = $fname;
            }

            $proprietorName = trim(implode(" ", array_filter(
                [$old["first_name"], $old["middle_name"], $old["last_name"], $old["suffix"]],
                fn($part) => $part !== ""
            )));

            $ins = $db->prepare(
                "INSERT INTO stall_applications
                    (business_name, proprietor_name, first_name, middle_name, last_name, suffix, sex,
                     street, barangay, city, province, contact_number, email, preferred_stall_id,
                     profile_picture, business_permit, sanitary_permit, gjc_requirements,
                     clearance, terms_accepted, status, current_step)
                 VALUES (?,?,?,?,?,?,?, ?,?,?,?, ?,?,?, 'pending_path','pending_path','pending_path','pending_path','pending_path', 1, 'pending_verification', 1)"
            );
            $ins->execute([
                $old["business_name"],
                $proprietorName,
                $old["first_name"],
                $old["middle_name"] !== "" ? $old["middle_name"] : null,
                $old["last_name"],
                $old["suffix"]   !== "" ? $old["suffix"]   : null,
                $old["sex"],
                $old["street"],
                $old["barangay"],
                $old["city"],
                $old["province"],
                $old["contact_number"],
                $old["email"],
                $old["preferred_stall_id"] !== "" ? $old["preferred_stall_id"] : null,
            ]);
            $appId = (int) $db->lastInsertId();

            $finalDir = BASE_PATH . "/uploads/stall_applications/" . $appId;
            if (!rename($tmpDir, $finalDir)) {
                throw new RuntimeException("DIR_RENAME");
            }

            $realPaths = [];
            foreach ($tmpPaths as $field => $fname) {
                $realPaths[$field] = "uploads/stall_applications/" . $appId . "/" . $fname;
            }

            $db->prepare(
                "UPDATE stall_applications
                 SET profile_picture=?, business_permit=?, sanitary_permit=?,
                     gjc_requirements=?, clearance=?
                 WHERE id=?"
            )->execute([
                $realPaths["profile_picture"],
                $realPaths["business_permit"],
                $realPaths["sanitary_permit"],
                $realPaths["gjc_requirements"],
                $realPaths["clearance"],
                $appId,
            ]);

            $db->commit();

            // ── One-stop scheduling: assign the verification meeting slot the
            // moment the application is submitted (clinic-appointment model) and
            // email the confirmation immediately. Runs AFTER commit so the slot
            // UPDATE autocommits and is visible to concurrent submissions under
            // the advisory lock. Non-fatal: a scheduling/email hiccup never loses
            // the submission — the applicant is told we will email the schedule.
            $meetingInfo = null;
            try {
                $slot = gjc_assign_meeting_slot($db, $appId);
                if ($slot !== null) {
                    $meetingDt = new DateTime($slot['datetime']);
                    $mailResult = gjc_send_stall_meeting_email(
                        $old['email'],
                        $proprietorName,
                        $old['business_name'],
                        $meetingDt,
                        $slot['location']
                    );
                    if ($mailResult['sent']) {
                        $db->prepare(
                            "UPDATE stall_applications SET meetup_scheduled_email_sent_at = NOW() WHERE id = ?"
                        )->execute([$appId]);
                    }
                    $meetingInfo = [
                        'datetime'  => $slot['datetime'],
                        'location'  => $slot['location'],
                        'email_ok'  => $mailResult['sent'],
                    ];
                }
            } catch (Throwable $schedEx) {
                // Leave $meetingInfo null — admin can schedule/re-send manually.
                $meetingInfo = null;
            }

            // PRG: store confirmation in session, then redirect so a page
            // refresh replays a GET instead of re-posting the form.
            if (session_status() === PHP_SESSION_NONE) session_start();
            $_SESSION['apply_success'] = [
                'app_id'  => $appId,
                'email'   => $old['email'],
                'meeting' => $meetingInfo,
            ];
            header('Location: ' . BASE_URL . '/apply?submitted=1');
            exit;
        } catch (Throwable $e) {
            $db->rollBack();
            if (is_dir($tmpDir)) {
                array_map("unlink", glob($tmpDir . "/*") ?: []);
                @rmdir($tmpDir);
            }
            $formErrors["general"] = "A server error occurred. Please try again. (ref: " . $e->getMessage() . ")";
        }
    }
}

// ── Fetch vacant stalls for dropdown ─────────────────────────
$availableStalls = [];
try {
    $stallMgr = new StallManager($db);
    $stallMgr->flushExpiredPending();
    $availableStalls = array_values(array_filter(
        $stallMgr->allStalls(),
        fn($s) => $s["status"] === "vacant"
    ));
} catch (Throwable $e) {
    $availableStalls = [];
}

// ── Fetch active restrictions for Terms modal ─────────────────
try {
    $activeRestrictions = $db->query(
        "SELECT product_name, category, reason, match_type
         FROM restricted_products
         WHERE is_active = 1
         ORDER BY category ASC, product_name ASC"
    )->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
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
    <meta name="description" content="Submit your stall application at General de Jesus College.">
    <link rel="stylesheet" href="<?= CSS_URL ?>/bootstrap.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&family=Plus+Jakarta+Sans:ital,wght@0,400;0,500;0,600;0,700;0,800;1,400&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer">
    <link rel="stylesheet" href="<?= CSS_URL ?>/apply.css?v=3">
</head>
<body>

<a href="<?= BASE_URL ?>/stalls" class="app-close" title="Back to stall map">
    <i class="fa-solid fa-xmark"></i>
</a>

<div class="app-body">

<?php if ($success): ?>

    <div class="success-card">
        <div class="success-icon"><i class="fa-solid fa-circle-check"></i></div>
        <div class="success-title">Application Submitted</div>

        <?php if ($successMeeting && !empty($successMeeting['datetime'])):
            $mDt = new DateTime($successMeeting['datetime']); ?>
        <p class="success-sub">
            Your stall application has been received and your verification meeting is
            <strong>booked</strong>. Everything — document check, contract signing, and
            payment — happens at this one meeting.
        </p>

        <div class="success-meeting" style="text-align:left;background:#fff;border:1px solid #bbf7d0;border-radius:12px;padding:18px 20px;margin:18px 0">
            <div style="font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:#059669;margin-bottom:10px">
                <i class="fa-solid fa-calendar-check"></i> Your Meeting Schedule
            </div>
            <div style="display:flex;gap:10px;margin-bottom:6px">
                <i class="fa-solid fa-calendar-day" style="color:#064420;width:18px;margin-top:3px"></i>
                <div><strong><?= htmlspecialchars($mDt->format("l, F j, Y")) ?></strong></div>
            </div>
            <div style="display:flex;gap:10px;margin-bottom:6px">
                <i class="fa-solid fa-clock" style="color:#064420;width:18px;margin-top:3px"></i>
                <div><strong><?= htmlspecialchars($mDt->format("g:i A")) ?></strong> (1 hour)</div>
            </div>
            <div style="display:flex;gap:10px">
                <i class="fa-solid fa-location-dot" style="color:#064420;width:18px;margin-top:3px"></i>
                <div><?= htmlspecialchars($successMeeting["location"] ?? "GJC Finance Office") ?></div>
            </div>
        </div>

        <div class="success-reminder" style="text-align:left;background:#fffbeb;border:1px solid #fde68a;border-radius:12px;padding:14px 18px;margin:0 0 18px;color:#92400e;font-size:14px;line-height:1.6">
            <i class="fa-solid fa-triangle-exclamation"></i>
            <strong>Please bring the original copies</strong> of all documents you uploaded
            (business permit, sanitary permit, GJC requirements, and clearance) for verification.
        </div>

        <p class="success-sub" style="font-size:13px">
            <?php if (!empty($successMeeting["email_ok"])): ?>
            A confirmation with these details has been emailed to
            <strong><?= htmlspecialchars($successEmail ?? "") ?></strong>.
            <?php else: ?>
            We could not send the confirmation email just now, but your meeting above is confirmed.
            Please take a screenshot for your records.
            <?php endif; ?>
        </p>
        <?php else: ?>
        <p class="success-sub">
            Your stall application has been received. We will email your verification meeting
            schedule to <strong><?= htmlspecialchars($successEmail ?? "") ?></strong> shortly.
        </p>
        <?php endif; ?>

        <div class="success-ref">Application #<?= str_pad($appId, 5, "0", STR_PAD_LEFT) ?></div>
    </div>

<?php else: ?>

    <!-- Form heading -->
    <div class="form-intro">
        <h1>Stall Application</h1>
        <p>Complete all sections and upload the required documents. A specific stall will be assigned during the review process.</p>
    </div>

    <?php if (!empty($formErrors["general"])): ?>
    <div class="alert-error">
        <i class="fa-solid fa-triangle-exclamation"></i>
        <?= htmlspecialchars($formErrors["general"]) ?>
    </div>
    <?php endif; ?>

    <form method="POST" action="<?= BASE_URL ?>/apply" enctype="multipart/form-data" id="applyForm" novalidate>

        <!-- ── Section 1: Personal Information ─────────────────────── -->
        <div class="form-section">
            <div class="form-section-head">
                <div class="section-num">1</div>
                <div class="section-title">Personal Information</div>
            </div>
            <div class="form-section-body">
                <div class="frow frow-3">
                    <div>
                        <label class="field-label" for="first_name">First Name <span class="req">*</span></label>
                        <input type="text" id="first_name" name="first_name"
                               class="field-input <?= isset($formErrors['first_name']) ? 'err' : '' ?>"
                               placeholder="Juan"
                               value="<?= htmlspecialchars($old['first_name'] ?? '') ?>"
                               maxlength="60">
                        <?php if (isset($formErrors['first_name'])): ?>
                        <div class="field-error"><i class="fa-solid fa-circle-exclamation"></i><?= htmlspecialchars($formErrors['first_name']) ?></div>
                        <?php endif; ?>
                    </div>
                    <div>
                        <label class="field-label" for="middle_name">Middle Name</label>
                        <input type="text" id="middle_name" name="middle_name"
                               class="field-input <?= isset($formErrors['middle_name']) ? 'err' : '' ?>"
                               placeholder="Santos"
                               value="<?= htmlspecialchars($old['middle_name'] ?? '') ?>"
                               maxlength="60">
                        <?php if (isset($formErrors['middle_name'])): ?>
                        <div class="field-error"><i class="fa-solid fa-circle-exclamation"></i><?= htmlspecialchars($formErrors['middle_name']) ?></div>
                        <?php endif; ?>
                    </div>
                    <div>
                        <label class="field-label" for="last_name">Last Name <span class="req">*</span></label>
                        <input type="text" id="last_name" name="last_name"
                               class="field-input <?= isset($formErrors['last_name']) ? 'err' : '' ?>"
                               placeholder="Dela Cruz"
                               value="<?= htmlspecialchars($old['last_name'] ?? '') ?>"
                               maxlength="60">
                        <?php if (isset($formErrors['last_name'])): ?>
                        <div class="field-error"><i class="fa-solid fa-circle-exclamation"></i><?= htmlspecialchars($formErrors['last_name']) ?></div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="frow frow-2">
                    <div>
                        <label class="field-label" for="suffix">Suffix</label>
                        <input type="text" id="suffix" name="suffix"
                               class="field-input <?= isset($formErrors['suffix']) ? 'err' : '' ?>"
                               placeholder="Jr., Sr., III"
                               value="<?= htmlspecialchars($old['suffix'] ?? '') ?>"
                               maxlength="20">
                        <?php if (isset($formErrors['suffix'])): ?>
                        <div class="field-error"><i class="fa-solid fa-circle-exclamation"></i><?= htmlspecialchars($formErrors['suffix']) ?></div>
                        <?php endif; ?>
                    </div>
                    <div>
                        <label class="field-label" for="sex">Sex <span class="req">*</span></label>
                        <div class="field-select-wrap">
                            <select id="sex" name="sex"
                                    class="field-select <?= isset($formErrors['sex']) ? 'err' : '' ?>">
                                <option value="" disabled <?= ($old['sex'] ?? '') === '' ? 'selected' : '' ?>>Select</option>
                                <option value="male"   <?= ($old['sex'] ?? '') === 'male'   ? 'selected' : '' ?>>Male</option>
                                <option value="female" <?= ($old['sex'] ?? '') === 'female' ? 'selected' : '' ?>>Female</option>
                            </select>
                        </div>
                        <?php if (isset($formErrors['sex'])): ?>
                        <div class="field-error"><i class="fa-solid fa-circle-exclamation"></i><?= htmlspecialchars($formErrors['sex']) ?></div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="frow">
                    <div>
                        <label class="field-label" for="street">Street <span class="req">*</span></label>
                        <input type="text" id="street" name="street"
                               class="field-input <?= isset($formErrors['street']) ? 'err' : '' ?>"
                               placeholder="House No., Street Name"
                               value="<?= htmlspecialchars($old['street'] ?? '') ?>"
                               maxlength="150">
                        <?php if (isset($formErrors['street'])): ?>
                        <div class="field-error"><i class="fa-solid fa-circle-exclamation"></i><?= htmlspecialchars($formErrors['street']) ?></div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="frow frow-2">
                    <div>
                        <label class="field-label" for="barangay">Barangay <span class="req">*</span></label>
                        <input type="text" id="barangay" name="barangay"
                               class="field-input <?= isset($formErrors['barangay']) ? 'err' : '' ?>"
                               placeholder="e.g. San Isidro"
                               value="<?= htmlspecialchars($old['barangay'] ?? '') ?>"
                               maxlength="100">
                        <?php if (isset($formErrors['barangay'])): ?>
                        <div class="field-error"><i class="fa-solid fa-circle-exclamation"></i><?= htmlspecialchars($formErrors['barangay']) ?></div>
                        <?php endif; ?>
                    </div>
                    <div>
                        <label class="field-label" for="city">City / Municipality <span class="req">*</span></label>
                        <input type="text" id="city" name="city"
                               class="field-input <?= isset($formErrors['city']) ? 'err' : '' ?>"
                               placeholder="e.g. Cabanatuan City"
                               value="<?= htmlspecialchars($old['city'] ?? '') ?>"
                               maxlength="100">
                        <?php if (isset($formErrors['city'])): ?>
                        <div class="field-error"><i class="fa-solid fa-circle-exclamation"></i><?= htmlspecialchars($formErrors['city']) ?></div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="frow">
                    <div>
                        <label class="field-label" for="province">Province <span class="req">*</span></label>
                        <input type="text" id="province" name="province"
                               class="field-input <?= isset($formErrors['province']) ? 'err' : '' ?>"
                               placeholder="e.g. Nueva Ecija"
                               value="<?= htmlspecialchars($old['province'] ?? '') ?>"
                               maxlength="100">
                        <?php if (isset($formErrors['province'])): ?>
                        <div class="field-error"><i class="fa-solid fa-circle-exclamation"></i><?= htmlspecialchars($formErrors['province']) ?></div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- ── Section 2: Business & Contact ───────────────────────── -->
        <div class="form-section">
            <div class="form-section-head">
                <div class="section-num">2</div>
                <div class="section-title">Business & Contact</div>
            </div>
            <div class="form-section-body">
                <div class="frow">
                    <div>
                        <label class="field-label" for="business_name">Business Name <span class="req">*</span></label>
                        <input type="text" id="business_name" name="business_name"
                               class="field-input <?= isset($formErrors['business_name']) ? 'err' : '' ?>"
                               placeholder="e.g. Maria's Snack Corner"
                               value="<?= htmlspecialchars($old['business_name'] ?? '') ?>"
                               maxlength="120">
                        <?php if (isset($formErrors['business_name'])): ?>
                        <div class="field-error"><i class="fa-solid fa-circle-exclamation"></i><?= htmlspecialchars($formErrors['business_name']) ?></div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="frow frow-2">
                    <div>
                        <label class="field-label" for="contact_number">Contact Number <span class="req">*</span></label>
                        <input type="tel" id="contact_number" name="contact_number"
                               class="field-input <?= isset($formErrors['contact_number']) ? 'err' : '' ?>"
                               placeholder="09XXXXXXXXX"
                               value="<?= htmlspecialchars($old['contact_number'] ?? '') ?>"
                               maxlength="11" pattern="09[0-9]{9}">
                        <div class="field-hint">11 digits starting with 09</div>
                        <?php if (isset($formErrors['contact_number'])): ?>
                        <div class="field-error"><i class="fa-solid fa-circle-exclamation"></i><?= htmlspecialchars($formErrors['contact_number']) ?></div>
                        <?php endif; ?>
                    </div>
                    <div>
                        <label class="field-label" for="email">Email Address <span class="req">*</span></label>
                        <input type="email" id="email" name="email"
                               class="field-input <?= isset($formErrors['email']) ? 'err' : '' ?>"
                               placeholder="you@example.com"
                               value="<?= htmlspecialchars($old['email'] ?? '') ?>">
                        <?php if (isset($formErrors['email'])): ?>
                        <div class="field-error"><i class="fa-solid fa-circle-exclamation"></i><?= htmlspecialchars($formErrors['email']) ?></div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- ── Section 3: Preferred Stall ──────────────────────────── -->
        <div class="form-section">
            <div class="form-section-head">
                <div class="section-num">3</div>
                <div class="section-title">Preferred Stall</div>
                <div class="section-sub">Optional</div>
            </div>
            <div class="form-section-body">
                <div class="stall-pref-note">
                    <i class="fa-solid fa-circle-info"></i>
                    <span>This is a <strong>preference only</strong> — it does not reserve or lock the stall.
                    Final assignment is made by the administration during the approval stage.</span>
                </div>
                <div class="frow">
                    <div>
                        <label class="field-label" for="preferred_stall_id">Preferred Stall</label>
                        <div class="field-select-wrap">
                            <select id="preferred_stall_id" name="preferred_stall_id" class="field-select">
                                <option value="">No preference — any available stall</option>
                                <?php foreach ($availableStalls as $stall): ?>
                                <option value="<?= htmlspecialchars($stall['stall_id']) ?>"
                                    <?= ($old['preferred_stall_id'] ?? '') === $stall['stall_id'] ? 'selected' : '' ?>>
                                    Stall <?= htmlspecialchars($stall['stall_id']) ?>
                                    <?= $stall['area_sqm'] > 0 ? '— ' . $stall['area_sqm'] . ' sqm' : '' ?>
                                </option>
                                <?php endforeach; ?>
                                <?php if (empty($availableStalls)): ?>
                                <option disabled>No vacant stalls at this time</option>
                                <?php endif; ?>
                            </select>
                        </div>
                        <div class="field-hint">
                            <?= count($availableStalls) ?> stall<?= count($availableStalls) !== 1 ? 's' : '' ?> currently vacant.
                            <a href="<?= BASE_URL ?>/stalls" target="_blank" style="color:var(--green-7);font-weight:600;">View stall map</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ── Section 4: Profile Photo ────────────────────────────── -->
        <div class="form-section">
            <div class="form-section-head">
                <div class="section-num">4</div>
                <div class="section-title">Profile Photo</div>
                <div class="section-sub">JPG / PNG · max 5 MB</div>
            </div>
            <div class="form-section-body">
                <div class="file-tile photo-tile <?= isset($formErrors['profile_picture']) ? 'err' : '' ?>"
                     id="drop-profile_picture"
                     onclick="document.getElementById('file-profile_picture').click()"
                     ondragover="handleDragOver(event,this)"
                     ondragleave="handleDragLeave(event,this)"
                     ondrop="handleDrop(event,this,'profile_picture',true)">
                    <input type="file" id="file-profile_picture" name="profile_picture"
                           accept=".jpg,.jpeg,.png,image/jpeg,image/png"
                           style="display:none"
                           onchange="handleFile(this,'profile_picture',true)">
                    <div class="photo-ring">
                        <i class="fa-solid fa-user photo-ring-icon" id="icon-profile_picture"></i>
                        <img class="photo-preview" id="preview-profile_picture" alt="">
                    </div>
                    <div class="file-tile-label">Click or drag a photo here</div>
                    <div class="file-tile-sub">Clear front-facing photo of the proprietor</div>
                    <div class="file-tile-name" id="name-profile_picture"></div>
                </div>
                <?php if (isset($formErrors['profile_picture'])): ?>
                <div class="field-error mt-2"><i class="fa-solid fa-circle-exclamation"></i><?= htmlspecialchars($formErrors['profile_picture']) ?></div>
                <?php endif; ?>
            </div>
        </div>

        <!-- ── Section 5: Required Documents ───────────────────────── -->
        <div class="form-section">
            <div class="form-section-head">
                <div class="section-num">5</div>
                <div class="section-title">Required Documents</div>
                <div class="section-sub">PDF / JPG / PNG · max 5 MB each</div>
            </div>
            <div class="form-section-body">
                <div class="frow frow-2">
                    <?php
                    $docFields = [
                        "business_permit"  => ["icon" => "fa-briefcase",      "label" => "Business Permit"],
                        "sanitary_permit"  => ["icon" => "fa-pump-soap",       "label" => "Sanitary Permit"],
                        "gjc_requirements" => ["icon" => "fa-graduation-cap",  "label" => "GJC Requirements"],
                        "clearance"        => ["icon" => "fa-clipboard-check", "label" => "Clearance"],
                    ];
                    foreach ($docFields as $field => $meta): ?>
                    <div>
                        <label class="field-label"><?= $meta['label'] ?> <span class="req">*</span></label>
                        <div class="file-tile <?= isset($formErrors[$field]) ? 'err' : '' ?>"
                             id="drop-<?= $field ?>"
                             onclick="document.getElementById('file-<?= $field ?>').click()"
                             ondragover="handleDragOver(event,this)"
                             ondragleave="handleDragLeave(event,this)"
                             ondrop="handleDrop(event,this,'<?= $field ?>',false)">
                            <input type="file" id="file-<?= $field ?>" name="<?= $field ?>"
                                   accept=".jpg,.jpeg,.png,.pdf,image/jpeg,image/png,application/pdf"
                                   style="display:none"
                                   onchange="handleFile(this,'<?= $field ?>',false)">
                            <div class="file-tile-icon" id="icon-<?= $field ?>">
                                <i class="fa-solid <?= $meta['icon'] ?>"></i>
                            </div>
                            <div class="file-tile-label"><?= $meta['label'] ?></div>
                            <div class="file-tile-sub">PDF, JPG, PNG</div>
                            <div class="file-tile-name" id="name-<?= $field ?>"></div>
                        </div>
                        <?php if (isset($formErrors[$field])): ?>
                        <div class="field-error mt-1"><i class="fa-solid fa-circle-exclamation"></i><?= htmlspecialchars($formErrors[$field]) ?></div>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- ── Section 6: Terms & Conditions ───────────────────────── -->
        <div class="form-section">
            <div class="form-section-head">
                <div class="section-num">6</div>
                <div class="section-title">Terms & Conditions</div>
            </div>
            <div class="form-section-body">
                <div class="terms-gate <?= isset($formErrors['terms']) ? 'err' : '' ?>" id="termsCheckWrap">
                    <input type="checkbox" id="terms_accepted" name="terms_accepted" value="1" disabled
                           <?= !empty($_POST['terms_accepted']) ? 'checked' : '' ?>>
                    <label for="terms_accepted">
                        I have read and agree to the
                        <button type="button" class="terms-btn" data-bs-toggle="modal" data-bs-target="#termsModal">GJC Campus Stall Rental Terms &amp; Conditions</button>.
                        <span class="terms-lock-note">Open and scroll to the bottom to unlock this checkbox.</span>
                    </label>
                </div>
                <?php if (isset($formErrors['terms'])): ?>
                <div class="field-error mt-2"><i class="fa-solid fa-circle-exclamation"></i><?= htmlspecialchars($formErrors['terms']) ?></div>
                <?php endif; ?>
            </div>
        </div>

        <!-- ── Submit ────────────────────────────────────────────────── -->
        <div class="form-submit-wrap">
            <button type="submit" class="btn-apply" id="submitBtn" disabled>
                Submit Application
            </button>
            <p class="submit-note">All sections and documents are required. A stall will be assigned during the review process.</p>
        </div>

    </form>

    <!-- Terms modal -->
    <div class="modal fade" id="termsModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content" style="border-radius:14px;border:none">
                <div class="modal-header border-0 pb-0" style="padding:20px 24px 12px">
                    <h5 class="modal-title fw-bold" style="font-size:16px">GJC Campus Stall Rental — Terms &amp; Conditions</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" style="padding:8px 24px 20px">
                    <div class="terms-scroll" id="termsBox">
                        <p>By submitting this application, you agree to the following terms set forth by the administration of General de Jesus College (GJC):</p>
                        <p><strong>1. Eligibility.</strong> Applicants must be of legal age (18+) and must not have any outstanding financial obligations to GJC. Applications from individuals with existing violations of school policy may be rejected at the institution's discretion.</p>
                        <p><strong>2. Application Review.</strong> All submitted applications are subject to review by the GJC administration. Submission of this form does not guarantee approval. The school reserves the right to approve, reject, or defer any application without prior notice.</p>
                        <p><strong>3. Document Accuracy.</strong> All uploaded documents must be authentic, current, and valid. Submission of falsified or expired documents is grounds for immediate rejection and may result in legal action.</p>
                        <p><strong>4. Prohibited Products.</strong> Approved vendors must comply with GJC's product restriction policy administered through the GenPay platform. Selling, displaying, or offering any product on the restricted list is grounds for stall suspension or lease termination.</p>
                        <?php if (!empty($activeRestrictions)): ?>
                        <ul style="margin:0 0 10px;padding-left:22px">
                            <?php foreach ($activeRestrictions as $r): ?>
                            <li><strong><?= htmlspecialchars($r['product_name']) ?></strong> (<?= htmlspecialchars(ucfirst($r['category'])) ?>) — <?= htmlspecialchars($r['reason']) ?></li>
                            <?php endforeach; ?>
                        </ul>
                        <?php else: ?>
                        <p>No products are currently on the restricted list.</p>
                        <?php endif; ?>
                        <p><strong>5. Lease Obligations.</strong> Approved vendors will be required to sign a formal lease agreement and pay the applicable monthly rental rate. Failure to pay rent on time may result in stall suspension or termination.</p>
                        <p><strong>6. Operational Standards.</strong> Vendors must maintain cleanliness, observe proper waste disposal, and adhere to campus operating hours.</p>
                        <p><strong>7. Data Privacy.</strong> Personal information and documents submitted through this form are collected solely for the purpose of processing your application, in accordance with the Data Privacy Act of 2012 (RA 10173).</p>
                        <p><strong>8. Stall Preference.</strong> Indicating a preferred stall does not constitute a reservation or guarantee of assignment. Final stall allocation is at the discretion of the administration.</p>
                        <p style="font-weight:700;color:#064420;">By checking the box, you confirm that you have read, understood, and agree to all of the above terms and conditions.</p>
                    </div>
                    <div class="scroll-hint" id="scrollHint"><i class="fa-solid fa-circle-chevron-down"></i> Scroll to the bottom to unlock the checkbox</div>
                </div>
                <div class="modal-footer border-0" style="padding:0 24px 20px">
                    <button type="button" class="btn btn-success rounded-pill px-4" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

<?php endif; ?>
</div><!-- /.app-body -->

<footer style="background:var(--forest,#0d2818);color:rgba(255,255,255,.45);text-align:center;padding:18px;font-size:11px;letter-spacing:0.03em;">
    &copy; <?= date("Y") ?> General de Jesus College &mdash; GenPay
</footer>

<script src="<?= JS_URL ?>/bootstrap.bundle.min.js"></script>
<script>
// ── File upload handler ────────────────────────────────────────
function handleFile(input, field, isImage) {
    const file    = input.files[0];
    const drop    = document.getElementById('drop-' + field);
    const icon    = document.getElementById('icon-' + field);
    const nameEl  = document.getElementById('name-' + field);
    const preview = document.getElementById('preview-' + field);
    if (!file) return;

    drop.classList.add('has-file');
    drop.classList.remove('err');
    nameEl.style.display = 'block';
    nameEl.textContent   = file.name + ' (' + (file.size / 1024).toFixed(1) + ' KB)';

    if (isImage && preview && file.type.startsWith('image/')) {
        const reader = new FileReader();
        reader.onload = e => {
            preview.src = e.target.result;
            preview.style.display = 'block';
            if (icon) icon.style.display = 'none';
        };
        reader.readAsDataURL(file);
    }
    checkSubmitReady();
}

function handleDragOver(e, el)  { e.preventDefault(); el.classList.add('dragover'); }
function handleDragLeave(e, el) { el.classList.remove('dragover'); }
function handleDrop(e, el, field, isImage) {
    e.preventDefault();
    el.classList.remove('dragover');
    const files = e.dataTransfer.files;
    if (!files || !files.length) return;
    const input = document.getElementById('file-' + field);
    input.files = files;
    handleFile(input, field, isImage);
}

// ── Terms scroll-lock ──────────────────────────────────────────
const termsBox  = document.getElementById('termsBox');
const termsChk  = document.getElementById('terms_accepted');
const hint      = document.getElementById('scrollHint');
const gateWrap  = document.getElementById('termsCheckWrap');

if (termsBox) {
    termsBox.addEventListener('scroll', function () {
        if (this.scrollTop + this.clientHeight >= this.scrollHeight - 10 && termsChk.disabled) {
            termsChk.disabled = false;
            if (hint) hint.style.display = 'none';
            gateWrap.classList.add('unlocked');
        }
    });
}
if (termsChk) termsChk.addEventListener('change', checkSubmitReady);

// ── Submit gating ─────────────────────────────────────────────
function checkSubmitReady() {
    const allFiles = ['profile_picture','business_permit','sanitary_permit','gjc_requirements','clearance']
        .every(f => document.getElementById('file-' + f)?.files?.length > 0);
    document.getElementById('submitBtn').disabled = !(termsChk?.checked && allFiles);
}

// ── Contact number: digits only ───────────────────────────────
const tel = document.getElementById('contact_number');
if (tel) tel.addEventListener('input', function () {
    this.value = this.value.replace(/\D/g, '').slice(0, 11);
});

// ── Submit: loading state ─────────────────────────────────────
document.getElementById('applyForm')?.addEventListener('submit', function () {
    const btn = document.getElementById('submitBtn');
    btn.disabled  = true;
    btn.textContent = 'Submitting… Please wait';
});

// ── On load: restore terms state after validation error ───────
if (termsChk?.checked) {
    termsChk.disabled = false;
    if (hint) hint.style.display = 'none';
    gateWrap?.classList.add('unlocked');
    checkSubmitReady();
}
</script>
</body>
</html>
