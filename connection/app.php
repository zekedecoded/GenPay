<?php

require_once __DIR__ . "/config.php";

if (PHP_SAPI !== "cli" && session_status() === PHP_SESSION_NONE) {
    session_start();
}

function gjc_e($value): string
{
    return (string) $value;
}

function gjc_money($amount): string
{
    return '&#8369;' . number_format((float) $amount, 2);
}

// GenCoin conversion rate: ₱10 = 1 GC (same rate the POS wallet-load flow uses).
define('GJC_PESOS_PER_GC', 10);

function gjc_gc_amount($pesos): string
{
    $gc = (float) $pesos / GJC_PESOS_PER_GC;
    return rtrim(rtrim(number_format($gc, 2), '0'), '.');
}

// Two-line price tag: GC as the primary value, peso equivalent beneath.
function gjc_gc_price($pesos): string
{
    return '<span class="gc-price">'
        . '<span class="gc-price-main">' . gjc_gc_amount($pesos) . ' GC</span>'
        . '<span class="gc-price-sub">&asymp; ' . gjc_money($pesos) . '</span>'
        . '</span>';
}

function gjc_role_name($role): string
{
    if (is_numeric($role)) {
        return [
            1 => 'student',
            2 => 'merchant',
            3 => 'finance',
            4 => 'finance',
            5 => 'merchant',
            6 => 'merchant',
            7 => 'parent',
        ][(int) $role] ?? 'guest';
    }

    // legacy alias: anything stored as 'admin' is treated as 'finance'
    if (strtolower((string) $role) === 'admin') {
        return 'finance';
    }

    return strtolower((string) $role);
}

function gjc_user_id(): int
{
    return (int) ($_SESSION['userID'] ?? $_SESSION['user_id'] ?? 0);
}

function gjc_current_role(): string
{
    return gjc_role_name($_SESSION['roleID'] ?? $_SESSION['role'] ?? 0);
}

function gjc_table_exists(PDO $db, string $table): bool
{
    $stmt = $db->prepare(
        "SELECT COUNT(*) FROM information_schema.TABLES
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?"
    );
    $stmt->execute([$table]);
    return (int) $stmt->fetchColumn() > 0;
}

function gjc_table_columns(PDO $db, string $table): array
{
    static $cache = [];
    if (isset($cache[$table])) {
        return $cache[$table];
    }

    $stmt = $db->prepare(
        "SELECT COLUMN_NAME FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?",
    );
    $stmt->execute([$table]);
    return $cache[$table] = $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
}

/**
 * Normalizes a product name so a ban can't be dodged by cosmetic tweaks.
 * Folds case, leetspeak/homoglyphs (0→o, 3→e, @→a …), strips every
 * non-alphanumeric character (spaces, dashes, dots), then collapses runs of a
 * repeated character. So "Piattos", "piatos", "Pi@tt0s", "P-i-a-t-t-o-s" and
 * "Piattoss" all normalize to the same "piatos" — closing the drop/add-a-letter,
 * spacing, punctuation, and leetspeak evasions.
 */
function gjc_normalize_product_name(string $name): string
{
    $s = function_exists('mb_strtolower')
        ? mb_strtolower($name, 'UTF-8')
        : strtolower($name);
    $s = gjc_fold_confusables($s);                    // unicode look-alikes + accents -> ascii
    $s = strtr($s, [
        '0' => 'o', '1' => 'i', '3' => 'e', '4' => 'a', '5' => 's',
        '7' => 't', '8' => 'b', '9' => 'g',
        '@' => 'a', '$' => 's', '!' => 'i', '|' => 'i', '+' => 't',
        '£' => 'e', '€' => 'e',
    ]);
    $s = preg_replace('/[^a-z0-9]+/', '', $s);        // drop spaces / punctuation / zero-width
    $s = preg_replace('/(.)\1+/', '$1', (string) $s); // collapse runs: piattoss -> piatos
    return (string) $s;
}

/**
 * Folds common Unicode "confusable" characters — Cyrillic/Greek homoglyphs and
 * accented Latin letters — down to their plain ASCII look-alike. Catches
 * evasions like "Соbra" (Cyrillic с/о) or "Cóbrá" that survive plain lowercasing.
 * Keys are lowercase; run this after mb_strtolower().
 */
function gjc_fold_confusables(string $s): string
{
    static $map = null;
    if ($map === null) {
        $map = [
            // Cyrillic look-alikes
            'а'=>'a','в'=>'b','е'=>'e','ё'=>'e','к'=>'k','м'=>'m','н'=>'h','о'=>'o',
            'р'=>'p','с'=>'c','т'=>'t','у'=>'y','х'=>'x','і'=>'i','ј'=>'j','ѕ'=>'s',
            'ԁ'=>'d','ԛ'=>'q','г'=>'r','ѡ'=>'w',
            // Greek look-alikes
            'α'=>'a','β'=>'b','ε'=>'e','η'=>'n','ι'=>'i','κ'=>'k','ν'=>'v','ο'=>'o',
            'ρ'=>'p','τ'=>'t','υ'=>'u','χ'=>'x','ω'=>'w','γ'=>'y','ϲ'=>'c',
            // Accented / extended Latin
            'à'=>'a','á'=>'a','â'=>'a','ã'=>'a','ä'=>'a','å'=>'a','ā'=>'a','ă'=>'a','ą'=>'a',
            'è'=>'e','é'=>'e','ê'=>'e','ë'=>'e','ē'=>'e','ĕ'=>'e','ė'=>'e','ę'=>'e','ě'=>'e',
            'ì'=>'i','í'=>'i','î'=>'i','ï'=>'i','ĩ'=>'i','ī'=>'i','ĭ'=>'i',
            'ò'=>'o','ó'=>'o','ô'=>'o','õ'=>'o','ö'=>'o','ø'=>'o','ō'=>'o','ŏ'=>'o','ő'=>'o',
            'ù'=>'u','ú'=>'u','û'=>'u','ü'=>'u','ũ'=>'u','ū'=>'u','ŭ'=>'u','ů'=>'u',
            'ñ'=>'n','ń'=>'n','ň'=>'n','ç'=>'c','ć'=>'c','č'=>'c','ý'=>'y','ÿ'=>'y',
            'š'=>'s','ś'=>'s','ž'=>'z','ź'=>'z','ż'=>'z','ł'=>'l','đ'=>'d','ğ'=>'g',
            'ř'=>'r','ť'=>'t','ß'=>'b',
        ];
    }
    return strtr($s, $map);
}

/**
 * Generic filler words that carry no brand identity. When a ban is a multi-word
 * phrase, these are stripped so matching keys off the distinctive word(s) — e.g.
 * banning "Cobra Energy Drink" enforces on "cobra", not on every "energy"/"drink".
 */
function gjc_restriction_stopwords(): array
{
    static $stop = null;
    if ($stop === null) {
        $raw = [
            'the','and','a','an','of','with','for','in','on','plus','extra','no','zero',
            'original','classic','new','old','mini','jumbo','large','small','regular','size',
            'pack','packs','bottle','bottles','can','cans','cup','cups','box','sachet',
            'piece','pieces','pc','pcs','flavor','flavour','flavored','flavoured','brand',
            'energy','drink','drinks','soda','sodas','juice','water','iced','hot','cold',
        ];
        $stop = [];
        foreach ($raw as $w) {
            $n = gjc_normalize_product_name($w);
            if ($n !== '') {
                $stop[$n] = true;
            }
        }
    }
    return $stop;
}

/**
 * Breaks a ban name into its distinctive normalized word tokens (generic filler
 * and <3-char words dropped). Returns [] when the ban is made up entirely of
 * generic words, signalling callers to fall back to whole-phrase matching.
 */
function gjc_restriction_tokens(string $banName): array
{
    $stop  = gjc_restriction_stopwords();
    $words = preg_split('/[^\p{L}\p{N}]+/u', $banName, -1, PREG_SPLIT_NO_EMPTY) ?: [];
    $tokens = [];
    foreach ($words as $w) {
        $n = gjc_normalize_product_name($w);
        if ($n === '' || strlen($n) < 3 || isset($stop[$n])) {
            continue;
        }
        $tokens[$n] = true;
    }
    return array_keys($tokens);
}

/**
 * True if $token appears in $hay as a clean substring, or — for longer tokens —
 * within a small edit distance of some window of $hay (catches typo evasions
 * like "kobra"/"coxbra"). Distance budget scales with length and stays 0 for
 * short tokens so generic 3-4 letter fragments don't over-match.
 */
function gjc_fuzzy_token_in_haystack(string $token, string $hay): bool
{
    $tlen = strlen($token);
    if ($tlen < 3 || $hay === '') {
        return false;
    }
    if (strpos($hay, $token) !== false) {
        return true;
    }
    $maxDist = $tlen >= 8 ? 2 : ($tlen >= 5 ? 1 : 0);
    if ($maxDist === 0) {
        return false;
    }
    $hlen = strlen($hay);
    for ($winLen = max(1, $tlen - $maxDist); $winLen <= $tlen + $maxDist; $winLen++) {
        for ($i = 0; $i + $winLen <= $hlen; $i++) {
            if (levenshtein($token, substr($hay, $i, $winLen)) <= $maxDist) {
                return true;
            }
        }
    }
    return false;
}

/**
 * Core decision: does a single restriction match a product name? Everything runs
 * on the normalized form so leetspeak/spacing/homoglyph tricks are neutralised.
 *
 *  - 'exact'    → the whole normalized names must be identical.
 *  - 'contains' → matches if the normalized ban sits inside the product name, OR
 *                 any distinctive word of the ban is found (exact or fuzzy). This
 *                 is what catches "C0br4" (→"cobra") against a "Cobra Energy Drink"
 *                 ban even though the merchant never typed the full phrase.
 */
function gjc_restriction_matches(string $banName, string $matchType, string $productName): bool
{
    $normBan  = gjc_normalize_product_name($banName);
    $normName = gjc_normalize_product_name($productName);
    if ($normBan === '' || $normName === '') {
        return false;
    }

    if ($matchType === 'exact') {
        return $normName === $normBan;
    }

    // Whole-phrase substring (fast path + subset like "cobra" in "cobraenergydrink").
    if (strpos($normName, $normBan) !== false) {
        return true;
    }

    $tokens = gjc_restriction_tokens($banName);
    if (empty($tokens)) {
        // Ban is all generic words — fall back to a two-way phrase subset check.
        return strlen($normName) >= 4 && strpos($normBan, $normName) !== false;
    }
    foreach ($tokens as $t) {
        if (gjc_fuzzy_token_in_haystack($t, $normName)) {
            return true;
        }
    }
    return false;
}

/**
 * Cross-checks a product name against the admin-managed restricted_products
 * blacklist. Shared by inventory add/edit (and any future scanner) so every
 * enforcement point uses the exact same nutritional-compliance rule. Returns the
 * ban reason on the first match, or null when the product is clear.
 */
function gjc_check_restricted(PDO $db, string $productName): ?string
{
    if (!gjc_table_exists($db, 'restricted_products')) {
        return null;
    }
    if (gjc_normalize_product_name($productName) === '') {
        return null;
    }

    $restrictions = $db->query(
        "SELECT product_name, match_type, reason FROM restricted_products WHERE is_active = 1"
    )->fetchAll(PDO::FETCH_ASSOC);

    foreach ($restrictions as $r) {
        if (gjc_restriction_matches((string) $r['product_name'], (string) $r['match_type'], $productName)) {
            return $r['reason'];
        }
    }

    return null;
}

/**
 * Re-scans currently-listed inventory against every active ban and disables any
 * item that now matches under the smart rules. Idempotent (only touches items
 * not already restricted), so it's safe to call on page load — this is what
 * retroactively closes evasions that were added before a rule tightened.
 * Returns the number of items newly disabled.
 */
function gjc_enforce_restrictions_on_inventory(PDO $db): int
{
    if (!gjc_table_exists($db, 'merchant_inventory') || !gjc_table_exists($db, 'restricted_products')) {
        return 0;
    }
    $bans = $db->query(
        "SELECT product_name, match_type, reason FROM restricted_products WHERE is_active = 1"
    )->fetchAll(PDO::FETCH_ASSOC);
    if (!$bans) {
        return 0;
    }
    $items = $db->query(
        "SELECT id, product_name FROM merchant_inventory WHERE is_restricted = 0"
    )->fetchAll(PDO::FETCH_ASSOC);
    if (!$items) {
        return 0;
    }

    $upd = $db->prepare(
        "UPDATE merchant_inventory
            SET is_restricted = 1, is_available = 0, restriction_note = ?
          WHERE id = ?"
    );
    $disabled = 0;
    foreach ($items as $it) {
        foreach ($bans as $b) {
            if (gjc_restriction_matches((string) $b['product_name'], (string) $b['match_type'], (string) $it['product_name'])) {
                $upd->execute([$b['reason'], $it['id']]);
                $disabled++;
                break;
            }
        }
    }
    return $disabled;
}

function gjc_column(PDO $db, string $table, array $candidates): ?string
{
    $columns = gjc_table_columns($db, $table);
    foreach ($candidates as $candidate) {
        if (in_array($candidate, $columns, true)) {
            return $candidate;
        }
    }
    return null;
}

/**
 * Pipeline status vocabulary: review -> meeting -> down_payment -> approval -> active.
 * Maps 1:1 to the 4-step application pipeline (current_step 1-4).
 */
function gjc_ensure_stall_application_workflow_schema(PDO $db): void
{
    if (!gjc_table_exists($db, "stall_applications")) {
        return;
    }

    $columns = gjc_table_columns($db, "stall_applications");

    // One-time migration from the old pending/awaiting_*/approved chain to the
    // 4-step pipeline. Detected by the absence of the current_step column.
    if (!in_array("current_step", $columns, true)) {
        try {
            $db->exec(
                "ALTER TABLE stall_applications
                 MODIFY stall_id VARCHAR(10) NULL DEFAULT NULL,
                 ADD COLUMN current_step TINYINT UNSIGNED NOT NULL DEFAULT 1 AFTER status",
            );
        } catch (Throwable $ignored) {
        }

        // Widen the enum so old and new values coexist while data is migrated.
        try {
            $db->exec(
                "ALTER TABLE stall_applications
                 MODIFY status ENUM(
                    'pending','awaiting_meetup','awaiting_approval','active','rejected','expired',
                    'initially_approved','approved',
                    'review','meeting','down_payment','approval'
                 ) NOT NULL DEFAULT 'review'",
            );
        } catch (Throwable $ignored) {
        }

        $db->exec("UPDATE stall_applications SET status='review', current_step=1 WHERE status='pending'");
        $db->exec("UPDATE stall_applications SET status='meeting', current_step=2 WHERE status IN ('initially_approved','awaiting_meetup')");
        $db->exec("UPDATE stall_applications SET status='approval', current_step=4 WHERE status='awaiting_approval'");
        $db->exec("UPDATE stall_applications SET status='active', current_step=4 WHERE status IN ('approved', 'active')");

        // Early-rejection archiving: anything already rejected moves out of the
        // live pipeline into archived_rejections (step unknown for legacy rows -> 1).
        gjc_ensure_archived_rejections_schema($db);
        $db->exec(
            "INSERT INTO archived_rejections
                (original_application_id, rejected_at_step, business_name, proprietor_name,
                 contact_number, email, profile_picture, business_permit, sanitary_permit,
                 gjc_requirements, clearance, rejection_reason, rejected_by, rejected_at)
             SELECT id, 1, business_name, proprietor_name, contact_number, email,
                    profile_picture, business_permit, sanitary_permit, gjc_requirements, clearance,
                    COALESCE(rejection_reason, 'Rejected (reason not recorded prior to pipeline migration)'),
                    COALESCE(reviewed_by, 0), COALESCE(reviewed_at, updated_at)
             FROM stall_applications WHERE status = 'rejected'",
        );
        $db->exec("DELETE FROM stall_applications WHERE status = 'rejected'");

        // Narrow the enum down to the final 4-step vocabulary.
        try {
            $db->exec(
                "ALTER TABLE stall_applications
                 MODIFY status ENUM('review','meeting','down_payment','approval','active','expired')
                    NOT NULL DEFAULT 'review'",
            );
        } catch (Throwable $ignored) {
        }
    }

    // One-time rename from the earlier "gender" column to "sex".
    if (in_array("gender", $columns, true) && !in_array("sex", $columns, true)) {
        try {
            $db->exec("ALTER TABLE stall_applications CHANGE gender sex ENUM('male','female') NOT NULL DEFAULT 'male'");
            $columns = array_diff($columns, ["gender"]);
            $columns[] = "sex";
        } catch (Throwable $ignored) {
        }
    }

    $adds = [
        "first_name" => "VARCHAR(60) NOT NULL DEFAULT '' AFTER business_name",
        "middle_name" => "VARCHAR(60) NULL DEFAULT NULL AFTER first_name",
        "last_name" => "VARCHAR(60) NOT NULL DEFAULT '' AFTER middle_name",
        "suffix" => "VARCHAR(20) NULL DEFAULT NULL AFTER last_name",
        "sex" => "ENUM('male','female') NOT NULL DEFAULT 'male' AFTER suffix",
        "street" => "VARCHAR(150) NOT NULL DEFAULT '' AFTER sex",
        "barangay" => "VARCHAR(100) NOT NULL DEFAULT '' AFTER street",
        "city" => "VARCHAR(100) NOT NULL DEFAULT '' AFTER barangay",
        "province" => "VARCHAR(100) NOT NULL DEFAULT '' AFTER city",
        "meetup_scheduled_at" => "DATETIME NULL",
        "meetup_location" => "VARCHAR(255) NULL",
        "meetup_notes" => "TEXT NULL",
        "meetup_scheduled_by" => "INT UNSIGNED NULL",
        "meetup_scheduled_email_sent_at" => "DATETIME NULL",
        "down_payment_amount" => "DECIMAL(10,2) NULL",
        "down_payment_reference" => "VARCHAR(80) NULL",
        "down_payment_notes" => "TEXT NULL",
        "down_payment_recorded_by" => "INT UNSIGNED NULL",
        "down_payment_recorded_at" => "DATETIME NULL",
        "merchant_user_id" => "INT UNSIGNED NULL",
        "temp_password_plain" => "VARCHAR(100) NULL",
        "preferred_stall_id" => "VARCHAR(10) NULL DEFAULT NULL",
        // One-stop meeting: signed contract + payment recorded at the meeting.
        "contract_file" => "VARCHAR(500) NULL",
        "contract_uploaded_at" => "DATETIME NULL",
        "contract_uploaded_by" => "INT UNSIGNED NULL",
        "deposit_amount" => "DECIMAL(10,2) NULL",      // 2 months deposit
        "advance_amount" => "DECIMAL(10,2) NULL",      // 1 month advance
        "rental_start_date" => "DATE NULL",
        "payment_schedule_day" => "TINYINT UNSIGNED NULL", // 15 or 30
        "payment_method" => "VARCHAR(20) NULL",        // cash | gcash | maya
        "payment_ref_no" => "VARCHAR(60) NULL",        // e-wallet reference number
        "awarded_by" => "INT UNSIGNED NULL",
        "awarded_at" => "DATETIME NULL",
        "cancelled_by" => "INT UNSIGNED NULL",
        "cancelled_at" => "DATETIME NULL",
        "cancel_reason" => "TEXT NULL",
        // "New" badge: NULL until an admin first opens the application.
        "first_viewed_at" => "DATETIME NULL",
        "first_viewed_by" => "INT UNSIGNED NULL",
    ];

    foreach ($adds as $column => $definition) {
        if (!in_array($column, $columns, true)) {
            $db->exec(
                "ALTER TABLE stall_applications ADD COLUMN {$column} {$definition}",
            );
        }
    }

    // One-stop pipeline: the meeting is auto-scheduled at submission and the
    // old Review/Meeting/Down-Payment stages are collapsed into a single
    // in-person verification meeting. Widen the ENUM (so old values still
    // resolve), remap any legacy rows onto the two-status model, then narrow to
    // the final vocabulary. Idempotent — safe to run on every page load.
    try {
        $db->exec(
            "ALTER TABLE stall_applications
             MODIFY status ENUM(
                'pending_verification','awarded','cancelled','rejected','expired',
                'review','meeting','down_payment','approval','active','pending','approved'
             ) NOT NULL DEFAULT 'pending_verification'",
        );
        // Anything mid-pipeline (submitted, not yet awarded) -> Pending for Verification.
        $db->exec(
            "UPDATE stall_applications
                SET status = 'pending_verification'
              WHERE status IN ('review','meeting','down_payment','approval','pending')",
        );
        // Completed/awarded applications -> Awarded.
        $db->exec(
            "UPDATE stall_applications
                SET status = 'awarded'
              WHERE status IN ('active','approved')",
        );
        // Narrow to the final one-stop vocabulary.
        $db->exec(
            "ALTER TABLE stall_applications
             MODIFY status ENUM('pending_verification','awarded','rejected','cancelled','expired')
                NOT NULL DEFAULT 'pending_verification'",
        );
    } catch (Throwable $ignored) {
    }
}

/**
 * Archive table for applications declined during Step 1 (Review) or
 * Step 2 (Meeting). Kept separate from the live pipeline so finance staff
 * can reference or reactivate a declined application later.
 */
function gjc_ensure_archived_rejections_schema(PDO $db): void
{
    $db->exec(
        "CREATE TABLE IF NOT EXISTS archived_rejections (
            id                       INT UNSIGNED NOT NULL AUTO_INCREMENT,
            original_application_id INT UNSIGNED NOT NULL,
            rejected_at_step         TINYINT UNSIGNED NOT NULL,
            business_name            VARCHAR(120)  NOT NULL,
            proprietor_name          VARCHAR(120)  NOT NULL,
            contact_number           VARCHAR(15)   NOT NULL,
            email                    VARCHAR(255)  NOT NULL,
            profile_picture          VARCHAR(500)  NULL,
            business_permit          VARCHAR(500)  NULL,
            sanitary_permit          VARCHAR(500)  NULL,
            gjc_requirements         VARCHAR(500)  NULL,
            clearance                VARCHAR(500)  NULL,
            rejection_reason         TEXT          NOT NULL,
            rejected_by              INT UNSIGNED  NOT NULL,
            rejected_at              DATETIME      NOT NULL,
            archived_at              DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
            reactivated              TINYINT(1)    NOT NULL DEFAULT 0,
            PRIMARY KEY (id),
            KEY idx_ar_original (original_application_id),
            KEY idx_ar_email    (email)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci",
    );
}

function gjc_ensure_first_login_schema(PDO $db): void
{
    if (!gjc_table_exists($db, "users")) {
        return;
    }

    $columns = gjc_table_columns($db, "users");
    $adds = [
        "is_first_login" => "TINYINT(1) NOT NULL DEFAULT 0",
        "password_changed" => "TINYINT(1) NOT NULL DEFAULT 1",
        "temp_password" => "VARCHAR(100) NULL",
    ];

    foreach ($adds as $column => $definition) {
        if (!in_array($column, $columns, true)) {
            $db->exec("ALTER TABLE users ADD COLUMN {$column} {$definition}");
        }
    }
}

function gjc_current_user(PDO $db): array
{
    $id = gjc_user_id();
    if (!$id || !gjc_table_exists($db, "users")) {
        return [
            "id" => 0,
            "name" => "Guest",
            "email" => "",
            "role" => gjc_current_role(),
            "roleID" => (int) ($_SESSION["roleID"] ?? 0),
        ];
    }

    $idColumn = gjc_column($db, "users", ["id", "userID"]);
    if (!$idColumn) {
        return [
            "id" => $id,
            "name" => "User",
            "email" => "",
            "role" => gjc_current_role(),
            "roleID" => 0,
        ];
    }

    $stmt = $db->prepare("SELECT * FROM users WHERE {$idColumn} = ? LIMIT 1");
    $stmt->execute([$id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

    $name = trim((string) ($user["name"] ?? ""));
    if ($name === "") {
        $name = trim(
            (string) (($user["first_name"] ?? "") .
                " " .
                ($user["last_name"] ?? "")),
        );
    }
    if ($name === "") {
        $name = $user["email"] ?? "User";
    }

    $role = gjc_current_role();
    if (!empty($user["roleID"])) {
        $role = gjc_role_name($user["roleID"]);
    }

    return [
        "id" => (int) ($user[$idColumn] ?? $id),
        "name" => $name,
        "email" => $user["email"] ?? "",
        "role" => $role,
        "roleID" => (int) ($user["roleID"] ?? ($_SESSION["roleID"] ?? 0)),
        "raw" => $user,
    ];
}

function gjc_user_label(PDO $db, int $userId): string
{
    if (!$userId || !gjc_table_exists($db, "users")) {
        return "Unknown User";
    }

    $idColumn = gjc_column($db, "users", ["id", "userID"]);
    if (!$idColumn) {
        return "User #" . $userId;
    }

    $stmt = $db->prepare("SELECT * FROM users WHERE {$idColumn} = ? LIMIT 1");
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    $name = trim((string) ($user["name"] ?? ""));
    if ($name === "") {
        $name = trim(
            (string) (($user["first_name"] ?? "") .
                " " .
                ($user["last_name"] ?? "")),
        );
    }
    if ($name === "") {
        $name = $user["email"] ?? "User #" . $userId;
    }
    return $name;
}

// ── Remember me: persistent login via single-use rotating tokens ───────────
// Cookie 'gjc_remember' = "<selector>:<validator>"; only a SHA-256 hash of the
// validator is stored, so a leaked table can't forge cookies. Tokens rotate on
// every use and are deleted on logout.

function gjc_ensure_remember_token_schema(PDO $db): void
{
    $db->exec(
        "CREATE TABLE IF NOT EXISTS auth_remember_tokens (
            id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id     INT NOT NULL,
            selector    CHAR(24) NOT NULL,
            token_hash  CHAR(64) NOT NULL,
            expires_at  DATETIME NOT NULL,
            created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_art_selector (selector),
            KEY idx_art_user (user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci",
    );
}

/** Populate the login session vars for a users row (shared by password login and remember-me). */
function gjc_establish_login_session(array $user): void
{
    $userId = (int) ($user['id'] ?? $user['userID'] ?? 0);
    $roleId = (int) ($user['roleID'] ?? 0);

    $_SESSION['userID'] = $userId;
    $_SESSION['user_id'] = $userId;
    $_SESSION['roleID'] = $roleId;

    // Soft remap to sub_role (no DB changes to roleID); DB sub_role wins.
    $subRole = match ($roleId) {
        1 => 'student',
        2 => 'merchant_admin',
        3 => 'super_admin',
        4 => 'super_admin',
        5 => 'merchant_admin',
        6 => 'merchant_staff',
        7 => 'parent',
        default => 'student',
    };
    if (!empty($user['sub_role'])) {
        $subRole = (string) $user['sub_role'];
    }

    $_SESSION['sub_role'] = $subRole;
    $_SESSION['merchant_owner_id'] = (int) ($user['merchant_owner_id'] ?? 0);
    $_SESSION['role'] = [1 => 'student', 2 => 'merchant', 3 => 'finance', 4 => 'finance', 5 => 'merchant', 6 => 'merchant', 7 => 'parent'][$roleId] ?? 'user';
}

function gjc_issue_remember_token(PDO $db, int $userId, int $days = 30): void
{
    gjc_ensure_remember_token_schema($db);
    $selector  = bin2hex(random_bytes(12));
    $validator = bin2hex(random_bytes(32));
    $db->prepare(
        "INSERT INTO auth_remember_tokens (user_id, selector, token_hash, expires_at)
         VALUES (?, ?, ?, DATE_ADD(NOW(), INTERVAL ? DAY))"
    )->execute([$userId, $selector, hash('sha256', $validator), $days]);

    setcookie('gjc_remember', $selector . ':' . $validator, [
        'expires'  => time() + $days * 86400,
        'path'     => '/',
        'secure'   => !empty($_SERVER['HTTPS']),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

/** Delete the presented token (if any) and expire the cookie. Safe to call anytime. */
function gjc_clear_remember_token(PDO $db): void
{
    $raw = (string) ($_COOKIE['gjc_remember'] ?? '');
    if ($raw === '') {
        return;
    }
    if (strpos($raw, ':') !== false) {
        [$selector] = explode(':', $raw, 2);
        if (preg_match('/^[a-f0-9]{24}$/', $selector) && gjc_table_exists($db, 'auth_remember_tokens')) {
            $db->prepare("DELETE FROM auth_remember_tokens WHERE selector = ?")->execute([$selector]);
        }
    }
    setcookie('gjc_remember', '', ['expires' => time() - 3600, 'path' => '/', 'httponly' => true, 'samesite' => 'Lax']);
    unset($_COOKIE['gjc_remember']);
}

/**
 * Silently restore a login from the remember-me cookie when no session exists.
 * Invalid/expired/foreign tokens are discarded. Deactivated accounts and
 * accounts still on a forced password change never auto-login. The used token
 * is rotated (deleted + reissued) so a stolen cookie only works once.
 */
function gjc_attempt_remember_login(PDO $db): void
{
    if (gjc_user_id() > 0) {
        return;
    }
    $raw = (string) ($_COOKIE['gjc_remember'] ?? '');
    if ($raw === '' || strpos($raw, ':') === false) {
        return;
    }
    [$selector, $validator] = explode(':', $raw, 2);
    if (!preg_match('/^[a-f0-9]{24}$/', $selector) || !preg_match('/^[a-f0-9]{64}$/', $validator)) {
        gjc_clear_remember_token($db);
        return;
    }

    gjc_ensure_remember_token_schema($db);
    $stmt = $db->prepare("SELECT * FROM auth_remember_tokens WHERE selector = ? LIMIT 1");
    $stmt->execute([$selector]);
    $token = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$token
        || $token['expires_at'] <= date('Y-m-d H:i:s')
        || !hash_equals($token['token_hash'], hash('sha256', $validator))) {
        gjc_clear_remember_token($db);
        return;
    }

    $idCol = gjc_column($db, 'users', ['id', 'userID']) ?: 'userID';
    $userStmt = $db->prepare("SELECT * FROM users WHERE {$idCol} = ? LIMIT 1");
    $userStmt->execute([(int) $token['user_id']]);
    $user = $userStmt->fetch(PDO::FETCH_ASSOC);

    $mustChange = $user && (
        !empty($user['force_password_change'])
        || !empty($user['is_first_login'])
        || (isset($user['password_changed']) && (int) $user['password_changed'] === 0)
    );
    if (!$user || (!empty($user['status']) && $user['status'] === 'Inactive') || $mustChange) {
        gjc_clear_remember_token($db);
        return;
    }

    $db->prepare("DELETE FROM auth_remember_tokens WHERE id = ?")->execute([(int) $token['id']]);
    gjc_establish_login_session($user);
    session_regenerate_id(true);
    gjc_issue_remember_token($db, (int) $token['user_id']);
}

function gjc_require_role(array $roles): void
{
    // A returning visitor with a valid remember-me cookie gets their session
    // restored here, before the role check bounces them to the login page.
    global $db;
    if ($db instanceof PDO) {
        gjc_attempt_remember_login($db);
    }

    $role = gjc_current_role();
    if (!in_array($role, $roles, true)) {
        header("Location: " . BASE_URL . "/login.php");
        exit();
    }

    // If the account was deactivated after this session was created, force logout immediately.
    $userId = gjc_user_id();
    if ($userId) {
        global $db;
        if ($db instanceof PDO && gjc_table_exists($db, 'users')) {
            if (in_array('status', gjc_table_columns($db, 'users'), true)) {
                $idCol = gjc_column($db, 'users', ['id', 'userID']);
                if ($idCol) {
                    $chk = $db->prepare("SELECT status FROM users WHERE {$idCol} = ? LIMIT 1");
                    $chk->execute([$userId]);
                    if ($chk->fetchColumn() === 'Inactive') {
                        session_unset();
                        session_destroy();
                        header("Location: " . BASE_URL . "/login.php?reason=deactivated");
                        exit();
                    }
                }
            }
        }
    }

    $script = str_replace("\\", "/", $_SERVER["SCRIPT_NAME"] ?? "");
    if (!empty($_SESSION["force_change"]) && !str_ends_with($script, "/change_password.php")) {
        header("Location: " . BASE_URL . "/change_password.php");
        exit();
    }
}

function gjc_ensure_operational_tables(PDO $db): void
{
    $db->exec(
        "CREATE TABLE IF NOT EXISTS topup_requests (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            user_id INT UNSIGNED NULL,
            student_wallet_id INT UNSIGNED NULL,
            amount DECIMAL(15,2) NOT NULL,
            payment_method VARCHAR(80) NOT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'pending',
            reference_no VARCHAR(40) NULL UNIQUE,
            approved_by INT UNSIGNED NULL,
            approved_at DATETIME NULL,
            rejected_by INT UNSIGNED NULL,
            rejected_at DATETIME NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_topup_user (user_id),
            INDEX idx_topup_status (status)
        ) ENGINE=InnoDB",
    );

    $db->exec(
        "CREATE TABLE IF NOT EXISTS encashment_requests (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            user_id INT UNSIGNED NULL,
            merchant_wallet_id INT UNSIGNED NULL,
            amount DECIMAL(15,2) NOT NULL,
            method VARCHAR(80) NOT NULL DEFAULT 'Cashier Release',
            status VARCHAR(20) NOT NULL DEFAULT 'pending',
            reference_no VARCHAR(40) NULL UNIQUE,
            released_by INT UNSIGNED NULL,
            released_at DATETIME NULL,
            rejected_by INT UNSIGNED NULL,
            rejected_at DATETIME NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_encash_user (user_id),
            INDEX idx_encash_status (status)
        ) ENGINE=InnoDB",
    );

    $topupAdds = [
        "user_id" => "INT UNSIGNED NULL",
        "student_wallet_id" => "INT UNSIGNED NULL",
        "payment_method" => "VARCHAR(80) NOT NULL DEFAULT 'Cash at Cashier'",
        "reference_no" => "VARCHAR(40) NULL",
        "approved_by" => "INT UNSIGNED NULL",
        "approved_at" => "DATETIME NULL",
        "rejected_by" => "INT UNSIGNED NULL",
        "rejected_at" => "DATETIME NULL",
        "created_at" => "DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP",
    ];
    foreach ($topupAdds as $column => $definition) {
        if (
            !in_array($column, gjc_table_columns($db, "topup_requests"), true)
        ) {
            $db->exec(
                "ALTER TABLE topup_requests ADD COLUMN {$column} {$definition}",
            );
        }
    }

    $encashAdds = [
        "user_id" => "INT UNSIGNED NULL",
        "merchant_wallet_id" => "INT UNSIGNED NULL",
        "method" => "VARCHAR(80) NOT NULL DEFAULT 'Cashier Release'",
        "reference_no" => "VARCHAR(40) NULL",
        "released_by" => "INT UNSIGNED NULL",
        "released_at" => "DATETIME NULL",
        "rejected_by" => "INT UNSIGNED NULL",
        "rejected_at" => "DATETIME NULL",
        "created_at" => "DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP",
    ];
    foreach ($encashAdds as $column => $definition) {
        if (
            !in_array(
                $column,
                gjc_table_columns($db, "encashment_requests"),
                true,
            )
        ) {
            $db->exec(
                "ALTER TABLE encashment_requests ADD COLUMN {$column} {$definition}",
            );
        }
    }

    // Student cash-out (withdrawal) requests — mirrors encashment_requests
    // but keyed on the student wallet. Balance is deducted on release, not
    // at request time (same behaviour as merchant encashment).
    $db->exec(
        "CREATE TABLE IF NOT EXISTS withdrawal_requests (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            user_id INT UNSIGNED NULL,
            student_wallet_id INT UNSIGNED NULL,
            amount DECIMAL(15,2) NOT NULL,
            method VARCHAR(80) NOT NULL DEFAULT 'Cashier Release',
            status VARCHAR(20) NOT NULL DEFAULT 'pending',
            reference_no VARCHAR(40) NULL UNIQUE,
            released_by INT UNSIGNED NULL,
            released_at DATETIME NULL,
            rejected_by INT UNSIGNED NULL,
            rejected_at DATETIME NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_withdraw_user (user_id),
            INDEX idx_withdraw_status (status)
        ) ENGINE=InnoDB",
    );

    $withdrawAdds = [
        "user_id" => "INT UNSIGNED NULL",
        "student_wallet_id" => "INT UNSIGNED NULL",
        "method" => "VARCHAR(80) NOT NULL DEFAULT 'Cashier Release'",
        "reference_no" => "VARCHAR(40) NULL",
        "released_by" => "INT UNSIGNED NULL",
        "released_at" => "DATETIME NULL",
        "rejected_by" => "INT UNSIGNED NULL",
        "rejected_at" => "DATETIME NULL",
        "created_at" => "DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP",
    ];
    foreach ($withdrawAdds as $column => $definition) {
        if (
            !in_array(
                $column,
                gjc_table_columns($db, "withdrawal_requests"),
                true,
            )
        ) {
            $db->exec(
                "ALTER TABLE withdrawal_requests ADD COLUMN {$column} {$definition}",
            );
        }
    }

    gjc_ensure_fee_schema($db);
}

function gjc_ensure_fee_schema(PDO $db): void
{
    // Fee columns on transactions
    foreach ([
        'top_up_source  VARCHAR(20) NULL',
        'base_amount    DECIMAL(15,2) NULL',
        'fee_amount     DECIMAL(15,2) NULL',
        'credited_amount DECIMAL(15,2) NULL',
    ] as $col) {
        try { $db->exec("ALTER TABLE transactions ADD COLUMN $col"); }
        catch (\PDOException $e) { /* already exists */ }
    }

    // Fee columns on topup_requests
    foreach ([
        'top_up_source   VARCHAR(20) NULL',
        'fee_amount      DECIMAL(15,2) NULL',
        'credited_amount DECIMAL(15,2) NULL',
    ] as $col) {
        try { $db->exec("ALTER TABLE topup_requests ADD COLUMN $col"); }
        catch (\PDOException $e) { /* already exists */ }
    }

    // Fee revenue ledger
    $db->exec(
        "CREATE TABLE IF NOT EXISTS fee_revenue_log (
            id                 INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            transaction_ref    VARCHAR(40)  NOT NULL,
            top_up_source      VARCHAR(20)  NOT NULL,
            cash_amount        DECIMAL(15,2) NOT NULL,
            system_fee         DECIMAL(15,2) NOT NULL,
            merchant_fee       DECIMAL(15,2) NOT NULL DEFAULT 0,
            merchant_wallet_id INT UNSIGNED NULL,
            processed_by       INT UNSIGNED NOT NULL,
            created_at         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_fee_ref    (transaction_ref),
            INDEX idx_fee_source (top_up_source),
            INDEX idx_fee_date   (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci"
    );
}

function gjc_generate_student_id(PDO $db): string
{
    $year = date('Y');
    $stmt = $db->query(
        "SELECT MAX(CAST(SUBSTRING_INDEX(studentID, '-', -1) AS UNSIGNED))
           FROM student_info
          WHERE studentID REGEXP '^GJC[0-9]{4}-[0-9]+$'"
    );
    $max = (int) $stmt->fetchColumn();
    return sprintf('GJC%s-%04d', $year, $max + 1);
}

function gjc_ensure_student_info_record(PDO $db, int $userId, string $studentId): void
{
    $existing = $db->prepare("SELECT stud_infoID FROM student_info WHERE userID = ? LIMIT 1");
    $existing->execute([$userId]);
    if ($existing->fetchColumn()) {
        $db->prepare("UPDATE student_info SET studentID = ? WHERE userID = ?")->execute([$studentId, $userId]);
        return;
    }

    // Resolve a valid courseID — use the first existing one, or create a placeholder.
    $courseId = (int) $db->query("SELECT courseID FROM course ORDER BY courseID ASC LIMIT 1")->fetchColumn();
    if ($courseId === 0) {
        $db->exec("INSERT INTO course (course_code, course_name) VALUES ('GENERAL', 'General')");
        $courseId = (int) $db->lastInsertId();
    }

    $db->prepare("INSERT INTO student_info (userID, studentID, yr_lvl, courseID) VALUES (?, ?, '1', ?)")
       ->execute([$userId, $studentId, $courseId]);
}

function gjc_backfill_student_ids(PDO $db): void
{
    if (!gjc_table_exists($db, 'student_info') || !gjc_table_exists($db, 'users')) {
        return;
    }
    $stmt = $db->query(
        "SELECT u.userID
           FROM users u
           LEFT JOIN student_info si ON si.userID = u.userID
          WHERE u.roleID = 1
            AND (si.stud_infoID IS NULL
              OR si.studentID = ''
              OR si.studentID NOT REGEXP '^GJC[0-9]{4}-[0-9]+$')
          ORDER BY u.userID ASC"
    );
    $missing = $stmt->fetchAll(PDO::FETCH_COLUMN);
    foreach ($missing as $userId) {
        $newId = gjc_generate_student_id($db);
        gjc_ensure_student_info_record($db, (int) $userId, $newId);
    }
}

function gjc_ensure_merchant_qr_orders_schema(PDO $db): void
{
    $db->exec(
        "CREATE TABLE IF NOT EXISTS merchant_qr_orders (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            token VARCHAR(64) NOT NULL UNIQUE,
            merchant_user_id INT UNSIGNED NOT NULL,
            merchant_wallet_id INT UNSIGNED NOT NULL,
            description TEXT NULL,
            items_json TEXT NOT NULL,
            amount DECIMAL(15,2) NOT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'pending',
            expires_at DATETIME NOT NULL,
            paid_by INT UNSIGNED NULL,
            paid_ref VARCHAR(40) NULL,
            paid_at DATETIME NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_mqo_token (token),
            INDEX idx_mqo_status_expiry (status, expires_at),
            INDEX idx_mqo_merchant (merchant_user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci",
    );

    $adds = [
        "description" => "TEXT NULL",
        "items_json" => "TEXT NOT NULL",
        "amount" => "DECIMAL(15,2) NOT NULL DEFAULT 0.00",
        "status" => "VARCHAR(20) NOT NULL DEFAULT 'pending'",
        "expires_at" => "DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP",
        "paid_by" => "INT UNSIGNED NULL",
        "paid_ref" => "VARCHAR(40) NULL",
        "paid_at" => "DATETIME NULL",
        "created_at" => "DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP",
    ];

    $columns = gjc_table_columns($db, "merchant_qr_orders");
    foreach ($adds as $column => $definition) {
        if (!in_array($column, $columns, true)) {
            $db->exec("ALTER TABLE merchant_qr_orders ADD COLUMN {$column} {$definition}");
        }
    }
}

/**
 * Makes merchant_inventory.sku safe to use as a scan key for the student
 * cart: a SKU must be unique within a single merchant's catalog (NULL skus
 * are exempt, so optional SKUs still work). Attempts the index once; if
 * older duplicate data blocks it, the ALTER is skipped silently — app-layer
 * checks in merchant/api/inventory.php still prevent new collisions either way.
 */
function gjc_ensure_inventory_sku_index(PDO $db): void
{
    if (!gjc_table_exists($db, "merchant_inventory")) {
        return;
    }

    $stmt = $db->prepare(
        "SELECT COUNT(*) FROM information_schema.STATISTICS
          WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'merchant_inventory' AND INDEX_NAME = 'uq_merchant_sku'"
    );
    $stmt->execute();
    if ((int) $stmt->fetchColumn() > 0) {
        return;
    }

    try {
        $db->exec(
            "ALTER TABLE merchant_inventory ADD UNIQUE KEY uq_merchant_sku (merchant_user_id, sku)"
        );
    } catch (\Throwable $e) {
        // Existing duplicate SKUs from before this rule existed — leave the
        // index off for now; app-layer validation still blocks new clashes.
    }
}

/**
 * Re-reads every line of the student's session cart against
 * merchant_inventory right now, drops any item that became
 * unavailable/restricted/out-of-stock since it was scanned (reporting why),
 * and returns a ready-to-render/ready-to-charge snapshot. Shared by the
 * cart UI (student/api/cart.php) and checkout (student/api/checkout.php) so
 * both always agree on what the cart actually contains.
 */
function gjc_cart_snapshot(PDO $db): array
{
    $cart = $_SESSION['cart'] ?? ['merchant_user_id' => null, 'items' => []];
    $itemIds = array_keys($cart['items'] ?? []);

    if (empty($itemIds)) {
        $_SESSION['cart'] = ['merchant_user_id' => null, 'items' => []];
        return [
            'merchant_user_id' => null,
            'merchant_label' => null,
            'lines' => [],
            'total' => 0.0,
            'dropped' => [],
        ];
    }

    $placeholders = implode(',', array_fill(0, count($itemIds), '?'));
    $stmt = $db->prepare(
        "SELECT id, sku, product_name, price, stock_qty, is_available, is_restricted, restriction_note, merchant_user_id
           FROM merchant_inventory
          WHERE id IN ({$placeholders})"
    );
    $stmt->execute($itemIds);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $rowsById = [];
    foreach ($rows as $row) {
        $rowsById[(int) $row['id']] = $row;
    }

    $lines = [];
    $dropped = [];
    $total = 0.0;
    $merchantUserId = $cart['merchant_user_id'] ?? null;

    foreach ($cart['items'] as $itemId => $qty) {
        $row = $rowsById[(int) $itemId] ?? null;

        if (!$row) {
            $dropped[] = ['name' => "Item #{$itemId}", 'reason' => 'This product no longer exists.'];
            unset($_SESSION['cart']['items'][$itemId]);
            continue;
        }
        if ((int) $row['is_restricted'] === 1) {
            $dropped[] = ['name' => $row['product_name'], 'reason' => $row['restriction_note'] ?: 'This item now violates campus health guidelines.'];
            unset($_SESSION['cart']['items'][$itemId]);
            continue;
        }
        if ((int) $row['is_available'] !== 1) {
            $dropped[] = ['name' => $row['product_name'], 'reason' => 'The merchant marked this item unavailable.'];
            unset($_SESSION['cart']['items'][$itemId]);
            continue;
        }

        $qty = min((int) $qty, (int) $row['stock_qty']);
        if ($qty <= 0) {
            $dropped[] = ['name' => $row['product_name'], 'reason' => 'Out of stock.'];
            unset($_SESSION['cart']['items'][$itemId]);
            continue;
        }
        $_SESSION['cart']['items'][$itemId] = $qty;

        $lineTotal = round((float) $row['price'] * $qty, 2);
        $total += $lineTotal;
        $lines[] = [
            'id' => (int) $row['id'],
            'sku' => $row['sku'],
            'name' => $row['product_name'],
            'price' => round((float) $row['price'], 2),
            'qty' => $qty,
            'stock_qty' => (int) $row['stock_qty'],
            'line_total' => $lineTotal,
        ];
    }

    if (empty($_SESSION['cart']['items'])) {
        $_SESSION['cart']['merchant_user_id'] = null;
        $merchantUserId = null;
    }

    return [
        'merchant_user_id' => $merchantUserId,
        'merchant_label' => $merchantUserId ? gjc_merchant_display_name($db, $merchantUserId) : null,
        'lines' => $lines,
        'total' => round($total, 2),
        'dropped' => $dropped,
    ];
}

/**
 * Resolves a merchant's public display name (stall name, falling back to
 * their account name) from their user id. Shared by the cart snapshot and
 * the Shop Cart order endpoints so every screen shows the same label.
 */
function gjc_merchant_display_name(PDO $db, int $merchantUserId): ?string
{
    $stmt = $db->prepare(
        "SELECT m.stall_name, u.first_name, u.last_name
           FROM users u
           LEFT JOIN merchant m ON m.userID = u.userID
          WHERE u.userID = ?
          LIMIT 1"
    );
    $stmt->execute([$merchantUserId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        return null;
    }

    return $row['stall_name'] ?: trim($row['first_name'] . ' ' . $row['last_name']);
}

/**
 * Widens transactions.transaction_type to include 'refund' (used when a
 * merchant issues a Return). Existing rows/values are untouched — this only
 * adds a new label to the ENUM, mirroring the pattern already used for
 * systemic_audit_trail.action_type in connection/audit_logger.php.
 */
function gjc_ensure_transaction_refund_type(PDO $db): void
{
    try {
        $column = $db->query("SHOW COLUMNS FROM transactions LIKE 'transaction_type'")->fetch(PDO::FETCH_ASSOC);
        $type = (string) ($column['Type'] ?? '');
        if ($column && strpos($type, "'refund'") === false) {
            $db->exec(
                "ALTER TABLE transactions
                 MODIFY transaction_type ENUM(
                    'cash_in',
                    'payment',
                    'voucher_payment',
                    'merchant_settle',
                    'voucher_create',
                    'voucher_expire',
                    'cap_increase',
                    'refund'
                 ) NOT NULL"
            );
        }
    } catch (\Throwable $e) {
        // Leave the ENUM as-is; the insert below will surface the failure clearly.
    }
}

/**
 * Pending Shop Cart orders — a student submits a cart (no money moves yet),
 * the merchant sees it immediately in the Live Order Queue, and the student
 * later pays by scanning the merchant's static Wallet QR at the counter.
 */
function gjc_ensure_cart_orders_schema(PDO $db): void
{
    $db->exec(
        "CREATE TABLE IF NOT EXISTS cart_orders (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            reference_no VARCHAR(30) NOT NULL UNIQUE,
            student_user_id INT UNSIGNED NOT NULL,
            student_wallet_id INT UNSIGNED NOT NULL,
            merchant_user_id INT UNSIGNED NOT NULL,
            merchant_wallet_id INT UNSIGNED NOT NULL,
            description TEXT NULL,
            items_json TEXT NOT NULL,
            amount DECIMAL(15,2) NOT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'pending',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            paid_at DATETIME NULL,
            paid_ref VARCHAR(40) NULL,
            INDEX idx_cart_orders_student (student_user_id, status),
            INDEX idx_cart_orders_merchant (merchant_user_id, status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci"
    );
}

function gjc_student_wallet(PDO $db, int $userId): array
{
    if ($userId && gjc_table_exists($db, "student_wallets")) {
        $stmt = $db->prepare(
            "SELECT * FROM student_wallets WHERE user_id = ? LIMIT 1",
        );
        $stmt->execute([$userId]);
        $wallet = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$wallet) {
            $db->prepare(
                "INSERT IGNORE INTO student_wallets (user_id, balance) VALUES (?, 0)",
            )->execute([$userId]);
            $stmt->execute([$userId]);
            $wallet = $stmt->fetch(PDO::FETCH_ASSOC);
        }
        if ($wallet) {
            return [
                "id" => (int) $wallet["id"],
                "balance" => (float) $wallet["balance"],
                "source" => "student_wallets",
            ];
        }
    }

    if ($userId && gjc_table_exists($db, "wallet")) {
        $userColumn = gjc_column($db, "wallet", ["userID", "user_id"]);
        if ($userColumn) {
            $stmt = $db->prepare(
                "SELECT * FROM wallet WHERE {$userColumn} = ? LIMIT 1",
            );
            $stmt->execute([$userId]);
            $wallet = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($wallet) {
                return [
                    "id" => (int) ($wallet["walletID"] ?? ($wallet["id"] ?? 0)),
                    "balance" => (float) ($wallet["balance"] ?? 0),
                    "source" => "wallet",
                ];
            }
        }
    }

    return ["id" => 0, "balance" => 0.0, "source" => "none"];
}

function gjc_merchant_wallet(PDO $db, int $userId): array
{
    if ($userId && gjc_table_exists($db, "merchant_wallets")) {
        $stmt = $db->prepare(
            "SELECT * FROM merchant_wallets WHERE user_id = ? LIMIT 1",
        );
        $stmt->execute([$userId]);
        $wallet = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$wallet) {
            $db->prepare(
                "INSERT IGNORE INTO merchant_wallets (user_id, balance) VALUES (?, 0)",
            )->execute([$userId]);
            $stmt->execute([$userId]);
            $wallet = $stmt->fetch(PDO::FETCH_ASSOC);
        }
        if ($wallet) {
            return [
                "id" => (int) $wallet["id"],
                "balance" => (float) $wallet["balance"],
                "source" => "merchant_wallets",
            ];
        }
    }

    return ["id" => 0, "balance" => 0.0, "source" => "none"];
}

function gjc_merchant_owner_id(PDO $db, ?int $userId = null): int
{
    $userId = $userId ?: gjc_user_id();
    if (!$userId) {
        return 0;
    }

    if (!gjc_is_merchant_staff()) {
        return $userId;
    }

    $sessionOwnerId = (int) ($_SESSION["merchant_owner_id"] ?? 0);
    if ($sessionOwnerId > 0) {
        return $sessionOwnerId;
    }

    if (!gjc_table_exists($db, "users")) {
        return $userId;
    }

    $idColumn = gjc_column($db, "users", ["id", "userID"]);
    if (!$idColumn || !in_array("merchant_owner_id", gjc_table_columns($db, "users"), true)) {
        return $userId;
    }

    $stmt = $db->prepare("SELECT merchant_owner_id FROM users WHERE {$idColumn} = ? LIMIT 1");
    $stmt->execute([$userId]);
    $ownerId = (int) $stmt->fetchColumn();

    if ($ownerId > 0) {
        $_SESSION["merchant_owner_id"] = $ownerId;
        return $ownerId;
    }

    return $userId;
}

function gjc_reference(string $prefix): string
{
    return $prefix .
        "-" .
        date("Ymd") .
        "-" .
        strtoupper(bin2hex(random_bytes(3)));
}

/** Fixed bookable times for stall application Step 2 meetings - one applicant per slot. */
function gjc_meeting_time_slots(): array
{
    // One-hour meeting slots, 9:00 AM – 5:00 PM with a noon lunch break.
    // The last slot starts at 4:00 PM so no meeting runs past the 5:00 PM close.
    return ['09:00','10:00','11:00','13:00','14:00','15:00','16:00'];
}

/**
 * Holiday calendar (admin-managed) and default meeting location, used by the
 * auto-scheduler so it never books a weekend/holiday and always has a place
 * to put on the meeting email when no admin is filling the form by hand.
 */
function gjc_ensure_meeting_scheduling_schema(PDO $db): void
{
    $db->exec(
        "CREATE TABLE IF NOT EXISTS meeting_holidays (
            id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
            holiday_date DATE NOT NULL,
            name         VARCHAR(150) NOT NULL,
            created_by   INT UNSIGNED NULL,
            created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_mh_date (holiday_date)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci",
    );

    $db->exec(
        "CREATE TABLE IF NOT EXISTS meeting_settings (
            id                           TINYINT UNSIGNED NOT NULL,
            default_location             VARCHAR(255) NOT NULL DEFAULT 'GJC Finance Office',
            down_payment_default_amount  DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            updated_by                   INT UNSIGNED NULL,
            updated_at                   DATETIME NULL,
            PRIMARY KEY (id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci",
    );
    $db->exec("INSERT IGNORE INTO meeting_settings (id, default_location) VALUES (1, 'GJC Finance Office')");

    // One-time: add the down_payment_default_amount column to existing installs.
    try {
        $db->exec("ALTER TABLE meeting_settings ADD COLUMN down_payment_default_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00");
    } catch (\Throwable $ignored) {
    }

}

/**
 * Shared "last checked" timestamp per merchant card on the finance dashboard.
 * The notification badge counts audited merchant management actions newer than
 * this; opening the stall detail view stamps it, clearing the badge for every
 * finance admin (one team, one read state).
 */
function gjc_ensure_merchant_card_views_schema(PDO $db): void
{
    $db->exec(
        "CREATE TABLE IF NOT EXISTS merchant_card_views (
            merchant_id     INT NOT NULL,
            last_viewed_at  DATETIME NOT NULL,
            viewed_by       INT NULL,
            PRIMARY KEY (merchant_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci",
    );
}

/** Holiday dates the auto-scheduler must skip, as a Y-m-d => name map. */
function gjc_meeting_holiday_dates(PDO $db): array
{
    $rows = $db->query("SELECT holiday_date, name FROM meeting_holidays ORDER BY holiday_date ASC")
        ->fetchAll(PDO::FETCH_KEY_PAIR);
    return $rows;
}

function gjc_meeting_default_location(PDO $db): string
{
    $location = $db->query("SELECT default_location FROM meeting_settings WHERE id = 1")->fetchColumn();
    return $location !== false && $location !== '' ? $location : 'GJC Finance Office';
}

function gjc_down_payment_default_amount(PDO $db): float
{
    $amount = $db->query("SELECT down_payment_default_amount FROM meeting_settings WHERE id = 1")->fetchColumn();
    return $amount !== false ? (float) $amount : 0.0;
}

function gjc_wallet_user_stats(PDO $db): array
{
    $days = 30;
    $row  = $db->prepare(
        "SELECT
            COUNT(*) AS total,
            SUM(CASE WHEN last_txn >= DATE_SUB(NOW(), INTERVAL ? DAY) THEN 1 ELSE 0 END) AS active_count
         FROM (
             SELECT u.userID, MAX(t.created_at) AS last_txn
             FROM users u
             LEFT JOIN student_wallets sw ON sw.user_id = u.userID
             LEFT JOIN transactions t ON t.student_wallet_id = sw.id
             WHERE u.roleID = 1
             GROUP BY u.userID
         ) sub"
    );
    $row->execute([$days]);
    $data           = $row->fetch(PDO::FETCH_ASSOC);
    $total          = (int) ($data['total']        ?? 0);
    $active         = (int) ($data['active_count'] ?? 0);
    return [
        'total'    => $total,
        'active'   => $active,
        'inactive' => $total - $active,
        'days'     => $days,
    ];
}

function gjc_merchant_wallet_user_stats(PDO $db): array
{
    $days = 30;
    $row  = $db->prepare(
        "SELECT
            COUNT(*) AS total,
            SUM(CASE WHEN last_txn >= DATE_SUB(NOW(), INTERVAL ? DAY) THEN 1 ELSE 0 END) AS active_count
         FROM (
             SELECT mw.id, MAX(t.created_at) AS last_txn
             FROM merchant_wallets mw
             LEFT JOIN transactions t ON t.merchant_wallet_id = mw.id
             GROUP BY mw.id
         ) sub"
    );
    $row->execute([$days]);
    $data   = $row->fetch(PDO::FETCH_ASSOC);
    $total  = (int) ($data['total']        ?? 0);
    $active = (int) ($data['active_count'] ?? 0);
    return [
        'total'    => $total,
        'active'   => $active,
        'inactive' => $total - $active,
        'days'     => $days,
    ];
}

/**
 * Lists every wallet user (students + merchants) with name, type, current
 * balance, and last-activity date. Powers the Total Wallet Users drill-in
 * table on the economy page. Mirrors the 30-day "active" rule used by
 * gjc_wallet_user_stats() / gjc_merchant_wallet_user_stats().
 */
function gjc_wallet_users_list(PDO $db): array
{
    // Definitions mirror the economy pool cards: a "student wallet user" is any
    // student-role user (wallet row optional), a "merchant wallet user" is any
    // merchant_wallets row. This keeps the combined card count == the two pool
    // cards == this table's row count.
    $sql = "
        SELECT
            COALESCE(CONCAT(u.first_name, ' ', u.last_name), '(unknown)') AS name,
            'Student' AS type,
            COALESCE(sw.balance, 0) AS balance,
            (SELECT MAX(t.created_at) FROM transactions t
              WHERE t.student_wallet_id = sw.id) AS last_txn
        FROM users u
        LEFT JOIN student_wallets sw ON sw.user_id = u.userID
        WHERE u.roleID = 1
        UNION ALL
        SELECT
            COALESCE(CONCAT(u.first_name, ' ', u.last_name), '(unknown)') AS name,
            'Merchant' AS type,
            mw.balance AS balance,
            (SELECT MAX(t.created_at) FROM transactions t
              WHERE t.merchant_wallet_id = mw.id) AS last_txn
        FROM merchant_wallets mw
        LEFT JOIN users u ON u.userID = mw.user_id
        ORDER BY name ASC
    ";
    $rows = $db->query($sql)->fetchAll(PDO::FETCH_ASSOC);

    $cutoff = strtotime('-30 days');
    foreach ($rows as &$r) {
        $r['active'] = !empty($r['last_txn']) && strtotime($r['last_txn']) >= $cutoff;
    }
    unset($r);
    return $rows;
}

/**
 * Pure slot-finding core — no database, no clock, so it can be unit-tested
 * against the edge cases (Friday submission → Monday, full day rolls over,
 * weekend submission → Monday, day-before-holiday skip, etc.).
 *
 * Next-business-day model: a meeting is never booked the same day as
 * submission. Starting from the day AFTER `$now`, roll forward through business
 * days (skipping weekends and $holidays) and return the first free slot.
 *
 * @param DateTimeImmutable $now            The reference "now".
 * @param callable          $bookedProvider fn(string $ymd): string[] of taken 'H:i' times.
 * @param array             $holidays       Map of 'Y-m-d' => name to skip.
 * @param string[]          $slots          Ordered 'H:i' slot start times.
 * @return array{date:string,time:string}|null
 */
function gjc_compute_next_meeting_slot(
    DateTimeImmutable $now,
    callable $bookedProvider,
    array $holidays,
    array $slots,
    int $lookaheadDays = 180
): ?array {
    // Never same-day: the earliest a meeting can land is the day after
    // submission. Scan from tomorrow, rolling past weekends/holidays/full days.
    $cursor = $now->setTime(0, 0, 0)->modify('+1 day');

    for ($i = 0; $i < $lookaheadDays; $i++) {
        $dateStr   = $cursor->format('Y-m-d');
        $dayOfWeek = (int) $cursor->format('N'); // 6 = Saturday, 7 = Sunday

        if ($dayOfWeek < 6 && !isset($holidays[$dateStr])) {
            $booked = array_flip($bookedProvider($dateStr));
            foreach ($slots as $slot) {
                if (isset($booked[$slot])) {
                    continue;
                }
                return ['date' => $dateStr, 'time' => $slot];
            }
        }

        $cursor = $cursor->modify('+1 day');
    }

    return null;
}

/**
 * Finds the earliest open meeting slot from now, skipping weekends,
 * admin-defined holidays, and times already held by another live application.
 * A slot is "held" by any application with a meeting date on that day whose
 * status is not cancelled/expired/rejected — so cancellations free their slot.
 * Returns ['date' => 'Y-m-d', 'time' => 'H:i'] or null if nothing is free
 * within the lookahead window.
 */
function gjc_find_next_meeting_slot(PDO $db, int $lookaheadDays = 180): ?array
{
    $holidays = gjc_meeting_holiday_dates($db);
    $slots    = gjc_meeting_time_slots();

    $bookedStmt = $db->prepare(
        "SELECT TIME_FORMAT(meetup_scheduled_at, '%H:%i') AS t
           FROM stall_applications
          WHERE meetup_scheduled_at IS NOT NULL
            AND status NOT IN ('cancelled', 'expired', 'rejected')
            AND DATE(meetup_scheduled_at) = ?"
    );
    $provider = static function (string $date) use ($bookedStmt): array {
        $bookedStmt->execute([$date]);
        return $bookedStmt->fetchAll(PDO::FETCH_COLUMN);
    };

    return gjc_compute_next_meeting_slot(
        new DateTimeImmutable('now'),
        $provider,
        $holidays,
        $slots,
        $lookaheadDays
    );
}

/**
 * Atomically assign the next free meeting slot to an application and persist it.
 *
 * Concurrency guard: a named advisory lock (GET_LOCK) serialises the
 * find-then-write critical section so two near-simultaneous submissions can
 * never be handed the same slot — the second caller sees the first's committed
 * assignment and rolls to the next free slot.
 *
 * MUST be called outside an open transaction so the UPDATE autocommits and is
 * visible to the next lock holder before the lock is released.
 *
 * @return array{date:string,time:string,datetime:string,location:string}|null
 */
function gjc_assign_meeting_slot(PDO $db, int $appId, ?string $location = null): ?array
{
    $location = $location ?? gjc_meeting_default_location($db);

    $db->prepare("SELECT GET_LOCK('gjc_stall_scheduler', 10)")->execute();
    try {
        $slot = gjc_find_next_meeting_slot($db);
        if ($slot === null) {
            return null;
        }
        $scheduledAt = $slot['date'] . ' ' . $slot['time'] . ':00';
        $db->prepare(
            "UPDATE stall_applications
                SET meetup_scheduled_at = ?, meetup_location = ?
              WHERE id = ?"
        )->execute([$scheduledAt, $location, $appId]);

        return [
            'date'     => $slot['date'],
            'time'     => $slot['time'],
            'datetime' => $scheduledAt,
            'location' => $location,
        ];
    } finally {
        $db->prepare("SELECT RELEASE_LOCK('gjc_stall_scheduler')")->execute();
    }
}

function gjc_transaction_type_options(): array
{
    return [
        "" => "All Types",
        "payment" => "Payment",
        "topup" => "Top-up",
        "encashment" => "Encashment",
        "voucher_payment" => "Voucher Payment",
        "voucher_create" => "Voucher Creation",
        "voucher_expire" => "Voucher Expiry",
        "cap_increase" => "Cap Increase",
        "refund" => "Refund",
    ];
}

function gjc_transaction_status_options(): array
{
    return [
        "" => "All Status",
        "completed" => "Completed",
        "approved" => "Approved",
        "released" => "Released",
        "pending" => "Pending",
        "processing" => "Processing",
        "failed" => "Failed",
        "rejected" => "Rejected",
        "reversed" => "Reversed",
    ];
}

function gjc_transaction_type_label(string $type): string
{
    $labels = [
        "cash_in" => "Top-up",
        "payment" => "Payment",
        "voucher_payment" => "Voucher Payment",
        "merchant_settle" => "Encashment",
        "voucher_create" => "Voucher Creation",
        "voucher_expire" => "Voucher Expiry",
        "cap_increase" => "Cap Increase",
        "refund" => "Refund",
        "topup" => "Top-up",
        "encashment" => "Encashment",
    ];

    return $labels[$type] ?? ucwords(str_replace("_", " ", $type));
}

function gjc_transaction_type_slug(string $type): string
{
    $map = [
        "cash_in" => "topup",
        "merchant_settle" => "encashment",
    ];

    return $map[$type] ?? strtolower($type);
}

function gjc_transaction_status_label(string $status): string
{
    return ucwords(str_replace("_", " ", $status));
}

function gjc_transaction_status_slug(string $status): string
{
    return strtolower(str_replace([" ", "_"], "-", $status));
}

function gjc_transaction_is_pending(string $status): bool
{
    return in_array(strtolower($status), ["pending", "processing"], true);
}

function gjc_transaction_is_success(string $status): bool
{
    return in_array(
        strtolower($status),
        ["completed", "approved", "released"],
        true,
    );
}

function gjc_user_label_cached(PDO $db, int $userId): string
{
    static $cache = [];
    if (!isset($cache[$userId])) {
        $cache[$userId] = gjc_user_label($db, $userId);
    }
    return $cache[$userId];
}

function gjc_student_wallet_owner_label(PDO $db, int $walletId): string
{
    static $cache = [];
    if (!$walletId) {
        return "Student Wallet";
    }
    if (isset($cache[$walletId])) {
        return $cache[$walletId];
    }

    $stmt = $db->prepare(
        "SELECT user_id FROM student_wallets WHERE id = ? LIMIT 1",
    );
    $stmt->execute([$walletId]);
    $userId = (int) $stmt->fetchColumn();

    return $cache[$walletId] = $userId
        ? gjc_user_label_cached($db, $userId)
        : "Student Wallet #" . $walletId;
}

function gjc_merchant_wallet_owner_label(PDO $db, int $walletId): string
{
    static $cache = [];
    if (!$walletId) {
        return "Merchant Wallet";
    }
    if (isset($cache[$walletId])) {
        return $cache[$walletId];
    }

    $stmt = $db->prepare(
        "SELECT user_id FROM merchant_wallets WHERE id = ? LIMIT 1",
    );
    $stmt->execute([$walletId]);
    $userId = (int) $stmt->fetchColumn();

    return $cache[$walletId] = $userId
        ? gjc_user_label_cached($db, $userId)
        : "Merchant Wallet #" . $walletId;
}

function gjc_transaction_sender_receiver(PDO $db, array $row): array
{
    $type = (string) ($row["type"] ?? "");

    switch ($type) {
        case "cash_in":
            return [
                "sender" => "Cashier Vault",
                "receiver" => gjc_student_wallet_owner_label(
                    $db,
                    (int) ($row["student_wallet_id"] ?? 0),
                ),
            ];

        case "payment":
            return [
                "sender" => gjc_student_wallet_owner_label(
                    $db,
                    (int) ($row["student_wallet_id"] ?? 0),
                ),
                "receiver" => gjc_merchant_wallet_owner_label(
                    $db,
                    (int) ($row["merchant_wallet_id"] ?? 0),
                ),
            ];

        case "voucher_payment":
            return [
                "sender" => "Visitor Voucher",
                "receiver" => gjc_merchant_wallet_owner_label(
                    $db,
                    (int) ($row["merchant_wallet_id"] ?? 0),
                ),
            ];

        case "merchant_settle":
            return [
                "sender" => gjc_merchant_wallet_owner_label(
                    $db,
                    (int) ($row["merchant_wallet_id"] ?? 0),
                ),
                "receiver" => "Cashier Vault",
            ];

        case "voucher_create":
            return [
                "sender" => "Cashier Vault",
                "receiver" => "Visitor Voucher",
            ];

        case "voucher_expire":
            return [
                "sender" => "Expired Voucher",
                "receiver" => "Cashier Vault",
            ];

        case "cap_increase":
            return [
                "sender" => gjc_user_label_cached(
                    $db,
                    (int) ($row["initiated_by"] ?? 0),
                ),
                "receiver" => "Cashier Vault",
            ];

        default:
            return [
                "sender" => "System",
                "receiver" => "System",
            ];
    }
}

function gjc_build_transaction_row(PDO $db, array $base): array
{
    $source = (string) ($base["source"] ?? "ledger");
    $type = (string) ($base["type"] ?? "");
    $status = strtolower((string) ($base["status"] ?? "completed"));
    $createdAt = (string) ($base["created_at"] ?? date("Y-m-d H:i:s"));
    $party = [
        "sender" => $base["sender"] ?? "System",
        "receiver" => $base["receiver"] ?? "System",
    ];

    if ($source === "ledger") {
        $party = gjc_transaction_sender_receiver($db, $base);
    }

    return [
        "source" => $source,
        "id" => (int) ($base["id"] ?? 0),
        "ref" => (string) ($base["ref"] ?? ""),
        "type" => $type,
        "type_label" => gjc_transaction_type_label($type),
        "type_slug" => gjc_transaction_type_slug($type),
        "amount" => (float) ($base["amount"] ?? 0),
        "sender" => (string) $party["sender"],
        "receiver" => (string) $party["receiver"],
        "status" => $status,
        "status_label" => gjc_transaction_status_label($status),
        "status_slug" => gjc_transaction_status_slug($status),
        "created_at" => $createdAt,
        "time_label" => date("M d, Y h:i A", strtotime($createdAt)),
        "notes" => (string) ($base["notes"] ?? ""),
        "meta" => $base,
    ];
}

function gjc_fetch_admin_transactions(
    PDO $db,
    array $filters = [],
    int $limit = 100,
): array {
    gjc_ensure_operational_tables($db);

    $rows = [];

    if (gjc_table_exists($db, "transactions")) {
        $stmt = $db->query(
            "SELECT id, reference_no, transaction_type, initiated_by, student_wallet_id, merchant_wallet_id,
                    voucher_id, amount, status, notes, created_at, vault_before, vault_after,
                    total_in_circulation
               FROM transactions
              ORDER BY created_at DESC, id DESC
              LIMIT 500",
        );

        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $rows[] = gjc_build_transaction_row($db, [
                "source" => "ledger",
                "id" => $row["id"],
                "ref" => $row["reference_no"],
                "type" => $row["transaction_type"],
                "amount" => $row["amount"],
                "status" => $row["status"],
                "notes" => $row["notes"],
                "created_at" => $row["created_at"],
                "initiated_by" => $row["initiated_by"],
                "student_wallet_id" => $row["student_wallet_id"],
                "merchant_wallet_id" => $row["merchant_wallet_id"],
                "voucher_id" => $row["voucher_id"],
                "vault_before" => $row["vault_before"],
                "vault_after" => $row["vault_after"],
                "total_in_circulation" => $row["total_in_circulation"],
            ]);
        }
    }

    if (gjc_table_exists($db, "topup_requests")) {
        $stmt = $db->query(
            "SELECT id, reference_no, user_id, student_wallet_id, amount, payment_method, status,
                    approved_by, approved_at, rejected_by, rejected_at, created_at
               FROM topup_requests
              WHERE status <> 'approved' OR reference_no IS NULL OR reference_no = ''
              ORDER BY created_at DESC, id DESC
              LIMIT 300",
        );

        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $rows[] = gjc_build_transaction_row($db, [
                "source" => "topup_request",
                "id" => $row["id"],
                "ref" =>
                    $row["reference_no"] ?:
                    "TOPUP-REQ-" .
                        str_pad((string) $row["id"], 5, "0", STR_PAD_LEFT),
                "type" => "topup",
                "amount" => $row["amount"],
                "status" => $row["status"],
                "notes" => "Payment method: " . $row["payment_method"],
                "created_at" => $row["created_at"],
                "sender" => gjc_user_label_cached($db, (int) $row["user_id"]),
                "receiver" => "Cashier Review",
                "payment_method" => $row["payment_method"],
                "user_id" => $row["user_id"],
                "student_wallet_id" => $row["student_wallet_id"],
            ]);
        }
    }

    if (gjc_table_exists($db, "encashment_requests")) {
        $stmt = $db->query(
            "SELECT id, reference_no, user_id, merchant_wallet_id, amount, method, status,
                    released_by, released_at, rejected_by, rejected_at, created_at
               FROM encashment_requests
              WHERE status <> 'released' OR reference_no IS NULL OR reference_no = ''
              ORDER BY created_at DESC, id DESC
              LIMIT 300",
        );

        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $rows[] = gjc_build_transaction_row($db, [
                "source" => "encashment_request",
                "id" => $row["id"],
                "ref" =>
                    $row["reference_no"] ?:
                    "ENCASH-REQ-" .
                        str_pad((string) $row["id"], 5, "0", STR_PAD_LEFT),
                "type" => "encashment",
                "amount" => $row["amount"],
                "status" => $row["status"],
                "notes" => "Method: " . $row["method"],
                "created_at" => $row["created_at"],
                "sender" => gjc_user_label_cached($db, (int) $row["user_id"]),
                "receiver" => "Cashier Release",
                "method" => $row["method"],
                "user_id" => $row["user_id"],
                "merchant_wallet_id" => $row["merchant_wallet_id"],
            ]);
        }
    }

    $search = trim((string) ($filters["search"] ?? ""));
    $typeFilter = strtolower(trim((string) ($filters["type"] ?? "")));
    $statusFilter = strtolower(trim((string) ($filters["status"] ?? "")));

    $rows = array_values(
        array_filter($rows, function (array $row) use (
            $search,
            $typeFilter,
            $statusFilter,
        ): bool {
            if ($typeFilter !== "" && $row["type_slug"] !== $typeFilter) {
                return false;
            }

            if ($statusFilter !== "" && $row["status"] !== $statusFilter) {
                return false;
            }

            if ($search === "") {
                return true;
            }

            $needle = strtolower($search);
            $haystacks = [
                $row["ref"],
                $row["type_label"],
                $row["sender"],
                $row["receiver"],
                $row["status_label"],
                number_format((float) $row["amount"], 2, ".", ""),
                $row["notes"],
            ];

            foreach ($haystacks as $value) {
                if (strpos(strtolower((string) $value), $needle) !== false) {
                    return true;
                }
            }

            return false;
        }),
    );

    usort($rows, function (array $a, array $b): int {
        $timeCompare = strcmp($b["created_at"], $a["created_at"]);
        if ($timeCompare !== 0) {
            return $timeCompare;
        }
        return $b["id"] <=> $a["id"];
    });

    if ($limit > 0) {
        $rows = array_slice($rows, 0, $limit);
    }

    return $rows;
}

function gjc_admin_transaction_stats(array $rows): array
{
    $today = date("Y-m-d");
    $stats = [
        "total_transactions" => count($rows),
        "todays_volume" => 0.0,
        "pending_transactions" => 0,
        "completed_today" => 0,
    ];

    foreach ($rows as $row) {
        $isToday = substr((string) $row["created_at"], 0, 10) === $today;

        if (gjc_transaction_is_pending($row["status"])) {
            $stats["pending_transactions"]++;
        }

        if ($isToday && gjc_transaction_is_success($row["status"])) {
            $stats["todays_volume"] += (float) $row["amount"];
            $stats["completed_today"]++;
        }
    }

    return $stats;
}

function gjc_find_admin_transaction(
    PDO $db,
    string $source,
    ?string $ref = null,
    ?int $id = null,
): ?array {
    $transactions = gjc_fetch_admin_transactions($db, [], 0);

    foreach ($transactions as $transaction) {
        if ($transaction["source"] !== $source) {
            continue;
        }

        if ($ref !== null && $ref !== "" && $transaction["ref"] === $ref) {
            return $transaction;
        }

        if ($id !== null && $transaction["id"] === $id) {
            return $transaction;
        }
    }

    return null;
}

function gjc_count_users_by_role(PDO $db, string $roleName): int
{
    if (!gjc_table_exists($db, "users")) {
        return 0;
    }

    $roleName = strtolower(trim($roleName));

    if (gjc_table_exists($db, "role")) {
        $stmt = $db->prepare(
            "SELECT COUNT(*)
               FROM users u
               LEFT JOIN role r ON u.roleID = r.roleID
              WHERE LOWER(COALESCE(r.role_name, '')) = ?",
        );
        $stmt->execute([$roleName]);
        return (int) $stmt->fetchColumn();
    }

    $roleMap = [
        "student" => 1,
        "merchant" => 2,
        "finance" => 4,
        "admin" => 4,
        "super-admin" => 4,
    ];

    if (
        isset($roleMap[$roleName]) &&
        in_array("roleID", gjc_table_columns($db, "users"), true)
    ) {
        $stmt = $db->prepare("SELECT COUNT(*) FROM users WHERE roleID = ?");
        $stmt->execute([$roleMap[$roleName]]);
        return (int) $stmt->fetchColumn();
    }

    return 0;
}

function gjc_dashboard_transaction_series(
    array $transactions,
    int $days = 7,
): array {
    $days = max(1, $days);
    $labels = [];
    $totals = [];

    for ($offset = $days - 1; $offset >= 0; $offset--) {
        $date = date("Y-m-d", strtotime("-{$offset} days"));
        $labels[] = date("M d", strtotime($date));
        $totals[$date] = 0.0;
    }

    foreach ($transactions as $transaction) {
        if (!gjc_transaction_is_success($transaction["status"])) {
            continue;
        }

        $date = substr((string) $transaction["created_at"], 0, 10);
        if (array_key_exists($date, $totals)) {
            $totals[$date] += (float) $transaction["amount"];
        }
    }

    return [
        "labels" => $labels,
        "data" => array_map(
            function ($value) {
                return round($value, 2);
            },
            array_values($totals)
        ),
    ];
}

function gjc_admin_dashboard_data(PDO $db): array
{
    gjc_ensure_operational_tables($db);

    $snapshot = [];
    if (gjc_table_exists($db, "system_settings")) {
        try {
            require_once __DIR__ . "/CirculationEngine.php";
            $engine = new CirculationEngine($db);
            $snapshot = $engine->getCirculationSnapshot();
        } catch (\Throwable) {
            $snapshot = [];
        }
    }

    $transactions = gjc_fetch_admin_transactions($db, [], 0);
    $stats = gjc_admin_transaction_stats($transactions);
    $recentTransactions = array_slice($transactions, 0, 10);
    $chart = gjc_dashboard_transaction_series($transactions, 7);

    $studentWalletsTotal = (float) ($snapshot["student_wallets_total"] ?? 0);
    $merchantWalletsTotal = (float) ($snapshot["merchant_wallets_total"] ?? 0);
    $activeVouchersTotal = (float) ($snapshot["active_vouchers_total"] ?? 0);
    $circulatingBalance =
        $studentWalletsTotal + $merchantWalletsTotal + $activeVouchersTotal;

    $activeVisitors = 0;
    if (gjc_table_exists($db, "vouchers")) {
        $activeVisitors = (int) $db
            ->query("SELECT COUNT(*) FROM vouchers WHERE status = 'active'")
            ->fetchColumn();
    }

    $pendingTopups = 0;
    if (gjc_table_exists($db, "topup_requests")) {
        $pendingTopups = (int) $db
            ->query(
                "SELECT COUNT(*) FROM topup_requests WHERE status = 'pending'",
            )
            ->fetchColumn();
    }

    $pendingEncashments = 0;
    if (gjc_table_exists($db, "encashment_requests")) {
        $pendingEncashments = (int) $db
            ->query(
                "SELECT COUNT(*) FROM encashment_requests WHERE status = 'pending'",
            )
            ->fetchColumn();
    }

    $totalUsers = 0;
    if (gjc_table_exists($db, "users")) {
        $totalUsers = gjc_table_exists($db, "role")
            ? (int) $db->query(
                "SELECT COUNT(*)
                   FROM users u
                   LEFT JOIN role r ON u.roleID = r.roleID
                  WHERE LOWER(COALESCE(r.role_name, '')) != 'finance'"
            )->fetchColumn()
            : (int) $db->query(
                "SELECT COUNT(*) FROM users WHERE roleID NOT IN (3, 4)"
            )->fetchColumn();
    }

    return [
        "system_financials" => [
            "circulating_balance" => $circulatingBalance,
            "todays_volume" => (float) $stats["todays_volume"],
            "pending_topups" => $pendingTopups,
            "pending_encashments" => $pendingEncashments,
        ],
        "user_demographics" => [
            "total_users" => $totalUsers,
            "active_students" => gjc_count_users_by_role($db, "student"),
            "active_merchants" => gjc_count_users_by_role($db, "merchant"),
            "active_visitors" => $activeVisitors,
        ],
        "recent_transactions" => $recentTransactions,
        "transaction_chart" => $chart,
    ];
}

// ─── Phase 2: Role & Auth Layer helpers ──────────────────────────────────────

function gjc_sub_role(): string
{
    return strtolower((string) ($_SESSION["sub_role"] ?? ""));
}

function gjc_is_merchant_admin(): bool
{
    return gjc_sub_role() === "merchant_admin";
}

function gjc_is_merchant_staff(): bool
{
    return gjc_sub_role() === "merchant_staff";
}

function gjc_token_display(float $phpAmount): string
{
    // Cosmetic display: ₱10 = 1 GenCoin
    $tokens = $phpAmount / 10.0;
    return number_format($tokens, 1) . " GC";
}

function gjc_p2p_daily_sent(PDO $db, int $userId): float
{
    if (!gjc_table_exists($db, "p2p_transfers")) {
        return 0.0;
    }
    $stmt = $db->prepare(
        "SELECT COALESCE(SUM(amount), 0) FROM p2p_transfers
          WHERE from_user_id = ? AND DATE(created_at) = CURDATE() AND status = 'completed'",
    );
    $stmt->execute([$userId]);
    return (float) $stmt->fetchColumn();
}

// ─── Parent Account Module ────────────────────────────────────────────────────

function gjc_ensure_parent_schema(PDO $db): void
{
    $db->exec(
        "CREATE TABLE IF NOT EXISTS parents (
            id                    INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            user_id               INT UNSIGNED NOT NULL UNIQUE,
            low_balance_threshold DECIMAL(10,2) NOT NULL DEFAULT 50.00,
            created_at            DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB"
    );

    $db->exec(
        "CREATE TABLE IF NOT EXISTS parent_student_links (
            id              INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            parent_id       INT UNSIGNED NOT NULL,
            student_user_id INT UNSIGNED NOT NULL,
            linked_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_link (parent_id, student_user_id),
            INDEX idx_parent  (parent_id),
            INDEX idx_student (student_user_id)
        ) ENGINE=InnoDB"
    );

    $db->exec(
        "CREATE TABLE IF NOT EXISTS parent_alerts (
            id                INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            parent_id         INT UNSIGNED NOT NULL,
            student_user_id   INT UNSIGNED NOT NULL,
            student_wallet_id INT UNSIGNED NOT NULL,
            balance_at_alert  DECIMAL(10,2) NOT NULL,
            threshold         DECIMAL(10,2) NOT NULL,
            is_read           TINYINT(1) NOT NULL DEFAULT 0,
            created_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_parent_unread (parent_id, is_read)
        ) ENGINE=InnoDB"
    );

    // Add spending-control columns to student_wallets if absent
    if (gjc_table_exists($db, 'student_wallets')) {
        $cols = gjc_table_columns($db, 'student_wallets');
        $walletAdds = [
            'daily_spend_limit' => 'DECIMAL(10,2) NOT NULL DEFAULT 0 AFTER balance',
            'is_frozen'         => 'TINYINT(1) NOT NULL DEFAULT 0 AFTER daily_spend_limit',
        ];
        foreach ($walletAdds as $col => $def) {
            if (!in_array($col, $cols, true)) {
                try {
                    $db->exec("ALTER TABLE student_wallets ADD COLUMN {$col} {$def}");
                } catch (\Throwable $ignored) {}
            }
        }
    }
}

function gjc_check_parent_balance_alert(PDO $db, int $walletId): void
{
    if (!gjc_table_exists($db, 'parents') || !gjc_table_exists($db, 'parent_student_links') || !gjc_table_exists($db, 'parent_alerts')) {
        return;
    }

    $wStmt = $db->prepare("SELECT user_id, balance FROM student_wallets WHERE id = ?");
    $wStmt->execute([$walletId]);
    $wallet = $wStmt->fetch(PDO::FETCH_ASSOC);
    if (!$wallet) return;

    $pStmt = $db->prepare(
        "SELECT p.id AS parent_id, p.low_balance_threshold
           FROM parents p
           JOIN parent_student_links psl ON psl.parent_id = p.id
          WHERE psl.student_user_id = ?
            AND p.low_balance_threshold > 0
            AND p.low_balance_threshold >= ?"
    );
    $pStmt->execute([$wallet['user_id'], $wallet['balance']]);
    $parents = $pStmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($parents as $parent) {
        // Throttle: one unread alert per parent-student pair per day
        $chk = $db->prepare(
            "SELECT COUNT(*) FROM parent_alerts
              WHERE parent_id = ? AND student_user_id = ?
                AND DATE(created_at) = CURDATE() AND is_read = 0"
        );
        $chk->execute([$parent['parent_id'], $wallet['user_id']]);
        if ((int) $chk->fetchColumn() === 0) {
            $db->prepare(
                "INSERT INTO parent_alerts
                    (parent_id, student_user_id, student_wallet_id, balance_at_alert, threshold)
                 VALUES (?, ?, ?, ?, ?)"
            )->execute([
                $parent['parent_id'],
                $wallet['user_id'],
                $walletId,
                $wallet['balance'],
                $parent['low_balance_threshold'],
            ]);
        }
    }
}
