<?php

require_once __DIR__ . '/config.php';

if (PHP_SAPI !== 'cli' && session_status() === PHP_SESSION_NONE) {
    session_start();
}

function gjc_e($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function gjc_money($amount): string
{
    return '&#8369;' . number_format((float) $amount, 2);
}

function gjc_role_name($role): string
{
    if (is_numeric($role)) {
        return [1 => 'student', 2 => 'merchant', 3 => 'admin'][(int) $role] ?? 'guest';
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
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?"
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

function gjc_current_user(PDO $db): array
{
    $id = gjc_user_id();
    if (!$id || !gjc_table_exists($db, 'users')) {
        return [
            'id' => 0,
            'name' => 'Guest',
            'email' => '',
            'role' => gjc_current_role(),
            'roleID' => (int) ($_SESSION['roleID'] ?? 0),
        ];
    }

    $idColumn = gjc_column($db, 'users', ['id', 'userID']);
    if (!$idColumn) {
        return ['id' => $id, 'name' => 'User', 'email' => '', 'role' => gjc_current_role(), 'roleID' => 0];
    }

    $stmt = $db->prepare("SELECT * FROM users WHERE {$idColumn} = ? LIMIT 1");
    $stmt->execute([$id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

    $name = trim((string) ($user['name'] ?? ''));
    if ($name === '') {
        $name = trim((string) (($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')));
    }
    if ($name === '') {
        $name = $user['email'] ?? 'User';
    }

    $role = gjc_current_role();
    if (!empty($user['roleID'])) {
        $role = gjc_role_name($user['roleID']);
    }

    return [
        'id' => (int) ($user[$idColumn] ?? $id),
        'name' => $name,
        'email' => $user['email'] ?? '',
        'role' => $role,
        'roleID' => (int) ($user['roleID'] ?? ($_SESSION['roleID'] ?? 0)),
        'raw' => $user,
    ];
}

function gjc_user_label(PDO $db, int $userId): string
{
    if (!$userId || !gjc_table_exists($db, 'users')) {
        return 'Unknown User';
    }

    $idColumn = gjc_column($db, 'users', ['id', 'userID']);
    if (!$idColumn) {
        return 'User #' . $userId;
    }

    $stmt = $db->prepare("SELECT * FROM users WHERE {$idColumn} = ? LIMIT 1");
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    $name = trim((string) ($user['name'] ?? ''));
    if ($name === '') {
        $name = trim((string) (($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')));
    }
    if ($name === '') {
        $name = $user['email'] ?? ('User #' . $userId);
    }
    return $name;
}

function gjc_require_role(array $roles): void
{
    $role = gjc_current_role();
    if (!in_array($role, $roles, true)) {
        header('Location: ' . BASE_URL . '/login.php');
        exit;
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
        ) ENGINE=InnoDB"
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
        ) ENGINE=InnoDB"
    );

    $topupAdds = [
        'user_id' => 'INT UNSIGNED NULL',
        'student_wallet_id' => 'INT UNSIGNED NULL',
        'payment_method' => "VARCHAR(80) NOT NULL DEFAULT 'Cash at Cashier'",
        'reference_no' => 'VARCHAR(40) NULL',
        'approved_by' => 'INT UNSIGNED NULL',
        'approved_at' => 'DATETIME NULL',
        'rejected_by' => 'INT UNSIGNED NULL',
        'rejected_at' => 'DATETIME NULL',
        'created_at' => 'DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP',
    ];
    foreach ($topupAdds as $column => $definition) {
        if (!in_array($column, gjc_table_columns($db, 'topup_requests'), true)) {
            $db->exec("ALTER TABLE topup_requests ADD COLUMN {$column} {$definition}");
        }
    }

    $encashAdds = [
        'user_id' => 'INT UNSIGNED NULL',
        'merchant_wallet_id' => 'INT UNSIGNED NULL',
        'method' => "VARCHAR(80) NOT NULL DEFAULT 'Cashier Release'",
        'reference_no' => 'VARCHAR(40) NULL',
        'released_by' => 'INT UNSIGNED NULL',
        'released_at' => 'DATETIME NULL',
        'rejected_by' => 'INT UNSIGNED NULL',
        'rejected_at' => 'DATETIME NULL',
        'created_at' => 'DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP',
    ];
    foreach ($encashAdds as $column => $definition) {
        if (!in_array($column, gjc_table_columns($db, 'encashment_requests'), true)) {
            $db->exec("ALTER TABLE encashment_requests ADD COLUMN {$column} {$definition}");
        }
    }
}

function gjc_student_wallet(PDO $db, int $userId): array
{
    if ($userId && gjc_table_exists($db, 'student_wallets')) {
        $stmt = $db->prepare("SELECT * FROM student_wallets WHERE user_id = ? LIMIT 1");
        $stmt->execute([$userId]);
        $wallet = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$wallet) {
            $db->prepare("INSERT IGNORE INTO student_wallets (user_id, balance) VALUES (?, 0)")->execute([$userId]);
            $stmt->execute([$userId]);
            $wallet = $stmt->fetch(PDO::FETCH_ASSOC);
        }
        if ($wallet) {
            return ['id' => (int) $wallet['id'], 'balance' => (float) $wallet['balance'], 'source' => 'student_wallets'];
        }
    }

    if ($userId && gjc_table_exists($db, 'wallet')) {
        $userColumn = gjc_column($db, 'wallet', ['userID', 'user_id']);
        if ($userColumn) {
            $stmt = $db->prepare("SELECT * FROM wallet WHERE {$userColumn} = ? LIMIT 1");
            $stmt->execute([$userId]);
            $wallet = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($wallet) {
                return [
                    'id' => (int) ($wallet['walletID'] ?? $wallet['id'] ?? 0),
                    'balance' => (float) ($wallet['balance'] ?? 0),
                    'source' => 'wallet',
                ];
            }
        }
    }

    return ['id' => 0, 'balance' => 0.0, 'source' => 'none'];
}

function gjc_merchant_wallet(PDO $db, int $userId): array
{
    if ($userId && gjc_table_exists($db, 'merchant_wallets')) {
        $stmt = $db->prepare("SELECT * FROM merchant_wallets WHERE user_id = ? LIMIT 1");
        $stmt->execute([$userId]);
        $wallet = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$wallet) {
            $db->prepare("INSERT IGNORE INTO merchant_wallets (user_id, balance) VALUES (?, 0)")->execute([$userId]);
            $stmt->execute([$userId]);
            $wallet = $stmt->fetch(PDO::FETCH_ASSOC);
        }
        if ($wallet) {
            return ['id' => (int) $wallet['id'], 'balance' => (float) $wallet['balance'], 'source' => 'merchant_wallets'];
        }
    }

    return ['id' => 0, 'balance' => 0.0, 'source' => 'none'];
}

function gjc_reference(string $prefix): string
{
    return $prefix . '-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));
}
