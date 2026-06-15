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

function gjc_ensure_stall_application_workflow_schema(PDO $db): void
{
    if (!gjc_table_exists($db, "stall_applications")) {
        return;
    }

    $columns = gjc_table_columns($db, "stall_applications");

    try {
        $db->exec(
            "ALTER TABLE stall_applications
             MODIFY status ENUM(
                'pending',
                'awaiting_meetup',
                'awaiting_approval',
                'active',
                'rejected',
                'expired',
                'initially_approved',
                'approved'
             ) NOT NULL DEFAULT 'pending'",
        );
    } catch (Throwable $ignored) {
    }

    $adds = [
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
    ];

    foreach ($adds as $column => $definition) {
        if (!in_array($column, $columns, true)) {
            $db->exec(
                "ALTER TABLE stall_applications ADD COLUMN {$column} {$definition}",
            );
        }
    }
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

function gjc_require_role(array $roles): void
{
    $role = gjc_current_role();
    if (!in_array($role, $roles, true)) {
        header("Location: " . BASE_URL . "/login.php");
        exit();
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

    $totalUsers = gjc_table_exists($db, "users")
        ? (int) $db->query("SELECT COUNT(*) FROM users")->fetchColumn()
        : 0;

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

function gjc_is_super_admin(): bool
{
    $sub = gjc_sub_role();
    $role = gjc_current_role();
    return $sub === 'super_admin' || $role === 'finance';
}

function gjc_is_merchant_admin(): bool
{
    return gjc_sub_role() === "merchant_admin";
}

function gjc_is_merchant_staff(): bool
{
    return gjc_sub_role() === "merchant_staff";
}

function gjc_can_view_sales_metrics(): bool
{
    // Merchant Staff are blocked from overall store sales metrics
    return !gjc_is_merchant_staff();
}

function gjc_require_sub_role(array $allowedSubRoles): void
{
    $sub = gjc_sub_role();
    if (!in_array($sub, $allowedSubRoles, true)) {
        header("Content-Type: application/json");
        echo json_encode([
            "success" => false,
            "message" => "Access denied: insufficient role.",
        ]);
        exit();
    }
}

function gjc_token_display(float $phpAmount): string
{
    // Cosmetic display: ₱10 = 1 Token
    $tokens = $phpAmount / 10.0;
    return number_format($tokens, 1) . " T";
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

function gjc_ensure_new_tables(PDO $db): void
{
    // Ensure migration v2 tables exist (graceful fallback)
    $tables = [
        "merchant_leases",
        "merchant_rent_payments",
        "restricted_products",
        "merchant_inventory",
        "merchant_applications",
        "p2p_transfers",
        "school_revenue_ledger",
    ];
    foreach ($tables as $table) {
        if (!gjc_table_exists($db, $table)) {
            // Tables created by migration_v2.sql — just silently skip if missing
            return;
        }
    }
}
