-- ============================================================
--  GenPay — Token Economic System
--  MySQL Schema  |  Database: ewallet
--  Author: Junior Backend Developer (Fintech / DB Integrity)
--  Date:   2026-04-29
-- ============================================================
--
--  DESIGN PRINCIPLE — Closed-Loop Economy
--  ────────────────────────────────────────────────────────────
--  At every point in time the following invariant MUST hold:
--
--    cashier_vault_points
--  + SUM(student_wallets.balance)
--  + SUM(merchant_wallets.balance)
--  + SUM(vouchers.remaining_balance WHERE status = 'active')
--  = system_settings.total_circulation_cap
--
--  Money is NEVER created inside a transaction.
--  It only MOVES between pools.
-- ============================================================

USE ewallet;

-- ────────────────────────────────────────────────────────────
--  1.  SYSTEM SETTINGS  (singleton row — id always = 1)
--  Stores the immutable money-supply cap and the Cashier Vault.
--  Only SUPER-ADMIN can UPDATE total_circulation_cap.
-- ────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS system_settings (
    id                      TINYINT       UNSIGNED NOT NULL DEFAULT 1,

    -- The ceiling of the entire closed-loop economy (₱).
    -- Increasing this is the ONLY legal way to "mint" new points.
    total_circulation_cap   DECIMAL(15,2) NOT NULL DEFAULT 200000.00
        COMMENT 'Total money supply cap — super-admin only',

    -- The Cashier Vault: points that have been minted but not yet
    -- distributed to students or visitors.
    cashier_vault_points    DECIMAL(15,2) NOT NULL DEFAULT 200000.00
        COMMENT 'Unsold points sitting in the cashiers vault',

    -- Audit fields
    last_cap_increased_by   INT           UNSIGNED NULL
        COMMENT 'FK → users.id of the super-admin who last raised the cap',
    last_cap_increased_at   DATETIME      NULL,
    updated_at              DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP
                                          ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT pk_system_settings PRIMARY KEY (id),
    CONSTRAINT chk_vault_positive  CHECK (cashier_vault_points >= 0),
    CONSTRAINT chk_cap_positive    CHECK (total_circulation_cap > 0),
    CONSTRAINT chk_vault_lte_cap   CHECK (cashier_vault_points <= total_circulation_cap),

    -- Prevent a second row from ever being inserted
    CONSTRAINT ck_singleton CHECK (id = 1)
) ENGINE=InnoDB COMMENT='Singleton row — one record controls the entire economy';

-- Seed the singleton with a ₱200,000 cap (all points start in the vault)
INSERT IGNORE INTO system_settings (id, total_circulation_cap, cashier_vault_points)
VALUES (1, 200000.00, 200000.00);


-- ────────────────────────────────────────────────────────────
--  2.  STUDENT WALLETS
--  Each registered student gets exactly ONE wallet row.
--  The `balance` column represents points currently held.
-- ────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS student_wallets (
    id              INT           UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id         INT           UNSIGNED NOT NULL UNIQUE
        COMMENT 'FK → users.id (student role)',
    balance         DECIMAL(15,2) NOT NULL DEFAULT 0.00
        COMMENT 'Current spendable balance in PHP points',
    created_at      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP
                                  ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT pk_student_wallets   PRIMARY KEY (id),
    CONSTRAINT chk_student_balance  CHECK (balance >= 0)
) ENGINE=InnoDB COMMENT='Student digital wallets — points received from the vault';


-- ────────────────────────────────────────────────────────────
--  3.  MERCHANT WALLETS
--  Each merchant gets exactly ONE wallet row.
--  Points accumulate here when students pay; merchants then
--  encash (settle), which returns points to the vault.
-- ────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS merchant_wallets (
    id              INT           UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id         INT           UNSIGNED NOT NULL UNIQUE
        COMMENT 'FK → users.id (merchant role)',
    balance         DECIMAL(15,2) NOT NULL DEFAULT 0.00
        COMMENT 'Collected points pending settlement',
    created_at      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP
                                  ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT pk_merchant_wallets   PRIMARY KEY (id),
    CONSTRAINT chk_merchant_balance  CHECK (balance >= 0)
) ENGINE=InnoDB COMMENT='Merchant wallets — points flow in from students, out to vault on settlement';


-- ────────────────────────────────────────────────────────────
--  4.  VOUCHERS  (Visitor / Guest One-Time QR Codes)
--  These are NON-REFUNDABLE by design.
--  Points are pulled from the vault on creation and returned
--  to the vault only when the voucher EXPIRES (unspent balance).
-- ────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS vouchers (
    id                  INT           UNSIGNED NOT NULL AUTO_INCREMENT,

    -- QR payload — UUID-style unique code printed/scanned at entry
    voucher_code        VARCHAR(64)   NOT NULL UNIQUE,

    -- Who issued and authorized the voucher
    issued_by           INT           UNSIGNED NOT NULL
        COMMENT 'FK → users.id (cashier or admin who created it)',
    visitor_name        VARCHAR(120)  NOT NULL,
    visitor_contact     VARCHAR(60)   NULL,

    -- Economy values
    original_amount     DECIMAL(10,2) NOT NULL
        COMMENT 'Points pulled from the vault at creation time',
    remaining_balance   DECIMAL(10,2) NOT NULL
        COMMENT 'Unspent points — stays in the economy, non-refundable',

    -- Lifecycle
    status              ENUM('active','used','expired','void') NOT NULL DEFAULT 'active',
    is_non_refundable   TINYINT(1)    NOT NULL DEFAULT 1
        COMMENT 'Always 1 — architectural constant, never override',

    expires_at          DATETIME      NOT NULL
        COMMENT 'Cashier sets validity window (e.g. school event day)',
    created_at          DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP
                                      ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT pk_vouchers          PRIMARY KEY (id),
    CONSTRAINT chk_voucher_amount   CHECK (original_amount > 0),
    CONSTRAINT chk_voucher_balance  CHECK (remaining_balance >= 0),
    CONSTRAINT chk_voucher_lte_orig CHECK (remaining_balance <= original_amount),
    -- Hard rule: is_non_refundable must always be 1 (architectural constant)
    CONSTRAINT chk_non_refundable   CHECK (is_non_refundable = 1)
) ENGINE=InnoDB COMMENT='Visitor one-time QR vouchers — pulled from vault, non-refundable';


-- ────────────────────────────────────────────────────────────
--  5.  TRANSACTIONS  (Immutable Ledger)
--  Every point movement is journaled here for audit and
--  the circulation-integrity check.
-- ────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS transactions (
    id                  BIGINT        UNSIGNED NOT NULL AUTO_INCREMENT,

    -- Human-readable reference (e.g. TXN-20260429-00001)
    reference_no        VARCHAR(30)   NOT NULL UNIQUE,

    -- What kind of movement is this?
    transaction_type    ENUM(
        'cash_in',          -- vault → student wallet
        'payment',          -- student wallet → merchant wallet
        'voucher_payment',  -- voucher → merchant wallet
        'merchant_settle',  -- merchant wallet → vault (encashment)
        'voucher_create',   -- vault → voucher pool
        'voucher_expire',   -- voucher remaining → vault (recycle)
        'cap_increase'      -- super-admin mints new points into vault
    ) NOT NULL,

    -- Generic actor columns (nullable depending on type)
    initiated_by        INT           UNSIGNED NOT NULL
        COMMENT 'FK → users.id — who triggered this transaction',
    student_wallet_id   INT           UNSIGNED NULL,
    merchant_wallet_id  INT           UNSIGNED NULL,
    voucher_id          INT           UNSIGNED NULL,

    -- Money values
    amount              DECIMAL(15,2) NOT NULL,
    vault_before        DECIMAL(15,2) NOT NULL COMMENT 'Vault snapshot before',
    vault_after         DECIMAL(15,2) NOT NULL COMMENT 'Vault snapshot after',

    -- Integrity snapshot (must equal cap)
    total_in_circulation DECIMAL(15,2) NOT NULL
        COMMENT 'vault_after + all wallet balances + all active voucher balances',

    -- Result
    status              ENUM('pending','completed','failed','reversed') NOT NULL DEFAULT 'completed',
    notes               TEXT          NULL,
    created_at          DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT pk_transactions PRIMARY KEY (id),
    CONSTRAINT chk_txn_amount  CHECK (amount > 0)
) ENGINE=InnoDB COMMENT='Immutable transaction ledger — every peso movement recorded';

CREATE INDEX idx_txn_type       ON transactions (transaction_type);
CREATE INDEX idx_txn_student    ON transactions (student_wallet_id);
CREATE INDEX idx_txn_merchant   ON transactions (merchant_wallet_id);
CREATE INDEX idx_txn_voucher    ON transactions (voucher_id);
CREATE INDEX idx_txn_created    ON transactions (created_at);


-- ────────────────────────────────────────────────────────────
--  6.  CAP INCREASE LOG
--  Separate, append-only audit trail for every time a
--  super-admin increases the total_circulation_cap.
-- ────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS cap_increase_log (
    id              INT           UNSIGNED NOT NULL AUTO_INCREMENT,
    super_admin_id  INT           UNSIGNED NOT NULL
        COMMENT 'FK → users.id — must be super-admin role',
    old_cap         DECIMAL(15,2) NOT NULL,
    new_cap         DECIMAL(15,2) NOT NULL,
    amount_added    DECIMAL(15,2) NOT NULL,
    reason          TEXT          NOT NULL
        COMMENT 'Mandatory justification for audit compliance',
    created_at      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT pk_cap_increase_log PRIMARY KEY (id),
    CONSTRAINT chk_new_cap_gt_old  CHECK (new_cap > old_cap)
) ENGINE=InnoDB COMMENT='Append-only log of every money-supply increase';


-- ────────────────────────────────────────────────────────────
--  7.  DB-LEVEL SAFETY TRIGGER
--  Prevents direct manipulation of system_settings that would
--  set cashier_vault_points above total_circulation_cap.
--  The PHP CirculationEngine enforces full integrity; this is
--  the last line of defence at the DB layer.
-- ────────────────────────────────────────────────────────────
DROP TRIGGER IF EXISTS trg_guard_vault_update;

DELIMITER $$

CREATE TRIGGER trg_guard_vault_update
BEFORE UPDATE ON system_settings
FOR EACH ROW
BEGIN
    -- Vault can never exceed the cap
    IF NEW.cashier_vault_points > NEW.total_circulation_cap THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'VAULT_EXCEEDS_CAP: cashier_vault_points cannot exceed total_circulation_cap';
    END IF;

    -- Cap can never be decreased (only super-admin may increase, never lower)
    IF NEW.total_circulation_cap < OLD.total_circulation_cap THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'CAP_DECREASE_FORBIDDEN: total_circulation_cap can only be increased';
    END IF;
END$$

DELIMITER ;


-- ────────────────────────────────────────────────────────────
--  8.  VOUCHER EXPIRY TRIGGER
--  When a voucher is marked 'expired', its remaining_balance
--  is automatically recycled back into the vault.
--  (The PHP engine does this explicitly; the trigger is a
--   safety net for direct SQL updates during maintenance.)
-- ────────────────────────────────────────────────────────────
DROP TRIGGER IF EXISTS trg_recycle_expired_voucher;

DELIMITER $$

CREATE TRIGGER trg_recycle_expired_voucher
AFTER UPDATE ON vouchers
FOR EACH ROW
BEGIN
    -- Only act when status changes TO 'expired' and there is balance left
    IF NEW.status = 'expired'
       AND OLD.status != 'expired'
       AND NEW.remaining_balance > 0 THEN

        UPDATE system_settings
        SET cashier_vault_points = cashier_vault_points + NEW.remaining_balance
        WHERE id = 1;

    END IF;
END$$

DELIMITER ;


-- ────────────────────────────────────────────────────────────
--  SUMMARY VIEW — Circulation Snapshot
--  Useful for the admin dashboard / integrity checks.
-- ────────────────────────────────────────────────────────────
CREATE OR REPLACE VIEW v_circulation_snapshot AS
SELECT
    ss.total_circulation_cap                        AS cap,
    ss.cashier_vault_points                         AS vault,
    COALESCE(sw.student_total, 0)                   AS student_wallets_total,
    COALESCE(mw.merchant_total, 0)                  AS merchant_wallets_total,
    COALESCE(vo.voucher_total, 0)                   AS active_vouchers_total,

    -- The grand sum MUST always equal cap
    (ss.cashier_vault_points
     + COALESCE(sw.student_total, 0)
     + COALESCE(mw.merchant_total, 0)
     + COALESCE(vo.voucher_total, 0))               AS total_in_circulation,

    -- Drift = 0 means economy is balanced
    (ss.total_circulation_cap
     - ss.cashier_vault_points
     - COALESCE(sw.student_total, 0)
     - COALESCE(mw.merchant_total, 0)
     - COALESCE(vo.voucher_total, 0))               AS circulation_drift,

    ss.updated_at                                   AS as_of

FROM system_settings ss
CROSS JOIN (SELECT SUM(balance) AS student_total  FROM student_wallets)  sw
CROSS JOIN (SELECT SUM(balance) AS merchant_total FROM merchant_wallets) mw
CROSS JOIN (SELECT SUM(remaining_balance) AS voucher_total
            FROM vouchers WHERE status = 'active')                       vo
WHERE ss.id = 1;
