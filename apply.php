<?php
// ============================================================
//  apply.php - Public Stall Application Form
// ============================================================
require_once __DIR__ . "/connection/config.php";
require_once __DIR__ . "/connection/pdo.php";
require_once __DIR__ . "/connection/app.php";
require_once __DIR__ . "/connection/StallManager.php";

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

// PRG landing: consume the one-time session flash set after a successful POST.
if (isset($_SESSION['apply_success']) && isset($_GET['submitted'])) {
    $flash        = $_SESSION['apply_success'];
    $appId        = (int) $flash['app_id'];
    $successEmail = $flash['email'];
    $success      = true;
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
                 VALUES (?,?,?,?,?,?,?, ?,?,?,?, ?,?,?, 'pending_path','pending_path','pending_path','pending_path','pending_path', 1, 'review', 1)"
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

            // PRG: store confirmation in session, then redirect so a page
            // refresh replays a GET instead of re-posting the form.
            if (session_status() === PHP_SESSION_NONE) session_start();
            $_SESSION['apply_success'] = [
                'app_id' => $appId,
                'email'  => $old['email'],
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
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:ital,wght@0,400;0,500;0,600;0,700;0,800;1,400&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer">
    <style>
        *, *::before, *::after { box-sizing: border-box; }

        :root {
            --forest:   #0d2818;
            --green-8:  #064420;
            --green-7:  #15803d;
            --green-5:  #22c55e;
            --green-4:  #4ade80;
            --green-1:  #dcfce7;
            --green-0:  #f0fdf4;
            --amber:    #d97706;
            --red-6:    #dc2626;
            --red-1:    #fee2e2;
            --gray-9:   #111827;
            --gray-7:   #374151;
            --gray-6:   #4b5563;
            --gray-5:   #6b7280;
            --gray-3:   #d1d5db;
            --gray-2:   #e5e7eb;
            --gray-1:   #f3f4f6;
            --gray-0:   #f9fafb;
        }

        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background: #eef3ee;
            color: var(--gray-9);
            min-height: 100vh;
            margin: 0;
            display: flex;
            flex-direction: column;
        }

        /* ── Close button ────────────────────────────────────────────── */
        .app-close {
            position: fixed;
            top: 16px;
            right: 20px;
            z-index: 100;
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: rgba(0,0,0,.06);
            border: none;
            display: grid;
            place-items: center;
            cursor: pointer;
            color: var(--gray-6);
            font-size: 18px;
            text-decoration: none;
            transition: background .15s, color .15s;
        }
        .app-close:hover {
            background: rgba(0,0,0,.12);
            color: var(--gray-9);
        }

        /* ── Page body ──────────────────────────────────────────────── */
        .app-body {
            max-width: 700px;
            width: 100%;
            margin: 0 auto;
            padding: 32px 16px 48px;
            flex: 1;
        }

        /* ── Form intro ─────────────────────────────────────────────── */
        .form-intro {
            margin-bottom: 24px;
        }
        .form-intro h1 {
            font-size: 22px;
            font-weight: 800;
            color: var(--forest);
            margin: 0 0 4px;
            letter-spacing: -0.02em;
        }
        .form-intro p {
            font-size: 13px;
            color: var(--gray-5);
            margin: 0;
            line-height: 1.55;
        }

        /* ── Section card ───────────────────────────────────────────── */
        .form-section {
            background: #fff;
            border-radius: 14px;
            border: 1px solid #dde8dd;
            margin-bottom: 12px;
            overflow: hidden;
        }
        .form-section-head {
            padding: 16px 20px 14px;
            border-bottom: 1px solid #f0f4f0;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .section-num {
            width: 22px;
            height: 22px;
            border-radius: 50%;
            background: var(--green-1);
            color: var(--green-7);
            font-size: 11px;
            font-weight: 800;
            display: grid;
            place-items: center;
            flex-shrink: 0;
        }
        .section-title {
            font-size: 13px;
            font-weight: 700;
            color: var(--gray-7);
            letter-spacing: 0.01em;
        }
        .section-sub {
            font-size: 11px;
            color: var(--gray-5);
            margin-left: auto;
        }
        .form-section-body {
            padding: 20px;
        }

        /* ── Fields ─────────────────────────────────────────────────── */
        .field-label {
            display: block;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: var(--gray-6);
            margin-bottom: 6px;
        }
        .field-label .req { color: var(--red-6); margin-left: 2px; }

        .field-input,
        .field-select {
            width: 100%;
            border: 1.5px solid var(--gray-3);
            border-radius: 8px;
            padding: 9px 12px;
            font-family: inherit;
            font-size: 14px;
            font-weight: 500;
            color: var(--gray-9);
            background: #fff;
            outline: none;
            transition: border-color .15s, box-shadow .15s;
            appearance: none;
            -webkit-appearance: none;
        }
        .field-input::placeholder { color: var(--gray-5); font-weight: 400; }
        .field-input:focus,
        .field-select:focus {
            border-color: var(--green-7);
            box-shadow: 0 0 0 3px rgba(21,128,61,.1);
        }
        .field-input.err,
        .field-select.err {
            border-color: var(--red-6);
            background: #fff8f8;
        }
        .field-input.err:focus,
        .field-select.err:focus {
            box-shadow: 0 0 0 3px rgba(220,38,38,.1);
        }

        /* Custom select arrow */
        .field-select-wrap {
            position: relative;
        }
        .field-select-wrap::after {
            content: '';
            pointer-events: none;
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            width: 0;
            height: 0;
            border-left: 4px solid transparent;
            border-right: 4px solid transparent;
            border-top: 5px solid var(--gray-5);
        }
        .field-select { padding-right: 32px; }

        .field-hint {
            font-size: 11px;
            color: var(--gray-5);
            margin-top: 5px;
        }
        .field-error {
            font-size: 11px;
            font-weight: 600;
            color: var(--red-6);
            margin-top: 5px;
            display: flex;
            align-items: center;
            gap: 4px;
        }

        /* ── Field grid ─────────────────────────────────────────────── */
        .frow {
            display: grid;
            gap: 12px;
            margin-bottom: 12px;
        }
        .frow:last-child { margin-bottom: 0; }
        .frow-2 { grid-template-columns: 1fr 1fr; }
        .frow-3 { grid-template-columns: 1fr 1fr 1fr; }
        .frow-4 { grid-template-columns: 1fr 1fr 1fr 1fr; }
        .frow-2-1 { grid-template-columns: 2fr 1fr; }

        /* ── Stall preference card ──────────────────────────────────── */
        .stall-pref-note {
            background: var(--green-0);
            border: 1px solid var(--green-1);
            border-radius: 8px;
            padding: 10px 14px;
            font-size: 12px;
            color: var(--green-8);
            display: flex;
            align-items: flex-start;
            gap: 8px;
            margin-bottom: 12px;
        }
        .stall-pref-note i { flex-shrink: 0; margin-top: 1px; font-size: 13px; }

        /* ── File uploads ───────────────────────────────────────────── */
        .file-tile {
            border: 1.5px dashed var(--gray-3);
            border-radius: 10px;
            padding: 18px 12px;
            text-align: center;
            cursor: pointer;
            transition: border-color .15s, background .15s;
            background: var(--gray-0);
            position: relative;
        }
        .file-tile:hover { border-color: var(--green-4); background: var(--green-0); }
        .file-tile.has-file { border-style: solid; border-color: var(--green-5); background: var(--green-0); }
        .file-tile.err { border-color: var(--red-6); background: #fff8f8; border-style: solid; }
        .file-tile.dragover { border-color: var(--green-5); background: var(--green-1); transform: scale(1.01); }
        .file-tile input[type=file] { position: absolute; inset: 0; opacity: 0; cursor: pointer; width: 100%; }
        .file-tile-icon { font-size: 22px; color: var(--gray-5); margin-bottom: 6px; }
        .file-tile.has-file .file-tile-icon { color: var(--green-7); }
        .file-tile-label { font-size: 12px; font-weight: 700; color: var(--gray-7); }
        .file-tile-sub { font-size: 10px; color: var(--gray-5); margin-top: 3px; }
        .file-tile-name { font-size: 10px; color: var(--green-7); font-weight: 600; margin-top: 6px; word-break: break-all; display: none; }

        /* Profile photo tile */
        .photo-tile { display: flex; flex-direction: column; align-items: center; }
        .photo-ring {
            width: 80px; height: 80px; border-radius: 50%;
            background: var(--gray-1);
            display: grid; place-items: center;
            margin-bottom: 8px; overflow: hidden;
            border: 2px solid var(--gray-2);
            transition: border-color .15s;
        }
        .file-tile.has-file .photo-ring { border-color: var(--green-5); }
        .photo-ring-icon { font-size: 28px; color: var(--gray-5); }
        .photo-preview {
            width: 80px; height: 80px; object-fit: cover;
            border-radius: 50%; display: none;
        }

        /* ── Terms ──────────────────────────────────────────────────── */
        .terms-scroll {
            height: 300px; overflow-y: auto;
            border: 1.5px solid var(--gray-2);
            border-radius: 8px;
            background: var(--gray-0);
            font-size: 13px;
            line-height: 1.7;
            color: var(--gray-6);
            scroll-behavior: smooth;
            padding: 16px;
            margin-bottom: 12px;
        }
        .terms-scroll p { margin-bottom: 10px; }
        .terms-scroll::-webkit-scrollbar { width: 4px; }
        .terms-scroll::-webkit-scrollbar-thumb { background: var(--gray-3); border-radius: 99px; }

        .terms-gate {
            border: 1.5px solid var(--gray-2);
            border-radius: 8px;
            padding: 12px 14px;
            display: flex;
            align-items: flex-start;
            gap: 10px;
            transition: border-color .15s, background .15s;
        }
        .terms-gate.unlocked { border-color: var(--green-5); background: var(--green-0); }
        .terms-gate.err { border-color: var(--red-6); background: #fff8f8; }
        .terms-gate input[type=checkbox] {
            width: 16px; height: 16px; flex-shrink: 0;
            margin-top: 2px; accent-color: var(--green-7); cursor: pointer;
        }
        .terms-gate input[type=checkbox]:disabled { opacity: .35; cursor: not-allowed; }
        .terms-gate label {
            font-size: 13px; font-weight: 600; color: var(--gray-7);
            line-height: 1.5; cursor: pointer;
        }
        .terms-btn {
            background: none; border: none; padding: 0; font-family: inherit;
            font-size: inherit; font-weight: 700; color: var(--green-7);
            text-decoration: underline; text-decoration-color: var(--green-4);
            text-underline-offset: 2px; cursor: pointer;
        }
        .terms-btn:hover { color: var(--green-8); }
        .scroll-hint {
            font-size: 11px; font-weight: 600; color: var(--amber);
            text-align: center; margin-top: 8px;
        }
        .terms-lock-note { font-size: 11px; color: var(--gray-5); display: block; margin-top: 2px; }

        /* ── Submit footer ──────────────────────────────────────────── */
        .form-submit-wrap {
            background: #fff;
            border-radius: 14px;
            border: 1px solid #dde8dd;
            padding: 20px;
            margin-top: 12px;
        }
        .btn-apply {
            display: block; width: 100%;
            background: var(--green-8);
            color: #fff;
            border: none;
            border-radius: 10px;
            padding: 14px;
            font-family: inherit;
            font-size: 15px;
            font-weight: 700;
            letter-spacing: 0.01em;
            cursor: pointer;
            transition: background .15s, opacity .15s;
        }
        .btn-apply:hover:not(:disabled) { background: var(--forest); }
        .btn-apply:disabled { opacity: .45; cursor: not-allowed; }
        .submit-note {
            text-align: center; font-size: 11px; color: var(--gray-5);
            margin-top: 10px; margin-bottom: 0;
        }

        /* ── Success state ──────────────────────────────────────────── */
        .success-card {
            background: #fff;
            border-radius: 16px;
            border: 1px solid #dde8dd;
            padding: 48px 32px;
            text-align: center;
        }
        .success-icon { font-size: 56px; color: var(--green-5); margin-bottom: 16px; }
        .success-title { font-size: 22px; font-weight: 800; color: var(--forest); margin-bottom: 8px; }
        .success-sub { font-size: 14px; color: var(--gray-6); max-width: 400px; margin: 0 auto 20px; line-height: 1.6; }
        .success-ref {
            display: inline-block;
            background: var(--green-1);
            color: var(--green-8);
            font-size: 16px;
            font-weight: 800;
            letter-spacing: 0.04em;
            padding: 10px 24px;
            border-radius: 8px;
        }

        /* ── General alert ──────────────────────────────────────────── */
        .alert-error {
            background: var(--red-1);
            border: 1px solid #fca5a5;
            border-radius: 10px;
            padding: 12px 16px;
            font-size: 13px;
            font-weight: 500;
            color: #7f1d1d;
            display: flex;
            align-items: flex-start;
            gap: 8px;
            margin-bottom: 16px;
        }

        /* ── Responsive ─────────────────────────────────────────────── */
        @media (max-width: 600px) {
            .frow-3 { grid-template-columns: 1fr 1fr; }
            .frow-4 { grid-template-columns: 1fr 1fr; }
            .frow-2-1 { grid-template-columns: 1fr; }
            .frow-2 { grid-template-columns: 1fr; }
            .app-body { padding: 20px 12px 40px; }
        }
        @media (max-width: 400px) {
            .frow-3 { grid-template-columns: 1fr; }
        }
    </style>
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
        <p class="success-sub">
            Your stall application has been received. Our team will review your documents
            and contact you at <strong><?= htmlspecialchars($successEmail ?? "") ?></strong>
            about next steps.
        </p>
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
