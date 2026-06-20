-- ═══════════════════════════════════════════════════════════
--  GenPay — Voucher Schema PATCH
--  Adds missing columns to existing 'vouchers' table,
--  creates voucher_payment_log, and installs triggers/view.
--  Safe to run multiple times (IF NOT EXISTS / CREATE OR REPLACE).
-- ═══════════════════════════════════════════════════════════

USE ewallet;

-- ────────────────────────────────────────────────────────────
--  1. ADD MISSING COLUMNS to existing vouchers table
-- ────────────────────────────────────────────────────────────
ALTER TABLE vouchers
    ADD COLUMN IF NOT EXISTS qr_code_hash      VARCHAR(64)   NULL  UNIQUE      AFTER id,
    ADD COLUMN IF NOT EXISTS visitor_name      VARCHAR(120)  NULL              AFTER qr_code_hash,
    ADD COLUMN IF NOT EXISTS visitor_contact   VARCHAR(60)   NULL              AFTER visitor_name,
    ADD COLUMN IF NOT EXISTS initial_value     DECIMAL(15,2) NULL              AFTER visitor_contact,
    ADD COLUMN IF NOT EXISTS is_refundable     TINYINT(1)    NOT NULL DEFAULT 0
        COMMENT '0=Non-refundable (default). Expired balance → vault, not returned to visitor.'
        AFTER issued_by,
    ADD COLUMN IF NOT EXISTS expired_at        DATETIME      NULL              AFTER expires_at,
    ADD COLUMN IF NOT EXISTS cancelled_at      DATETIME      NULL              AFTER expired_at,
    ADD COLUMN IF NOT EXISTS last_used_at      DATETIME      NULL              AFTER cancelled_at,
    ADD COLUMN IF NOT EXISTS use_count         SMALLINT UNSIGNED NOT NULL DEFAULT 0 AFTER last_used_at;

-- Ensure indexes exist
ALTER TABLE vouchers
    ADD INDEX IF NOT EXISTS idx_v_hash   (qr_code_hash),
    ADD INDEX IF NOT EXISTS idx_v_status (status),
    ADD INDEX IF NOT EXISTS idx_v_expiry (expires_at, status);

-- ────────────────────────────────────────────────────────────
--  2. VOUCHER PAYMENT LOG (immutable per-scan ledger)
-- ────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS voucher_payment_log (
    id                 INT           UNSIGNED NOT NULL AUTO_INCREMENT,
    voucher_id         INT           UNSIGNED NOT NULL,
    merchant_wallet_id INT           UNSIGNED NOT NULL,
    amount             DECIMAL(15,2) NOT NULL,
    balance_before     DECIMAL(15,2) NOT NULL,
    balance_after      DECIMAL(15,2) NOT NULL,
    scanned_by         INT           UNSIGNED NULL,
    transaction_ref    VARCHAR(64)   NULL,
    created_at         DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT pk_vpl PRIMARY KEY (id),
    INDEX idx_vpl_voucher  (voucher_id),
    INDEX idx_vpl_merchant (merchant_wallet_id)
) ENGINE=InnoDB
  COMMENT='Append-only record of every deduction from a visitor voucher.';

-- ────────────────────────────────────────────────────────────
--  3. TRIGGER: Block payments on inactive vouchers
-- ────────────────────────────────────────────────────────────
DROP TRIGGER IF EXISTS trg_block_expired_voucher_use;

DELIMITER $$
CREATE TRIGGER trg_block_expired_voucher_use
BEFORE UPDATE ON vouchers
FOR EACH ROW
BEGIN
    IF NEW.remaining_balance < OLD.remaining_balance
       AND OLD.status IN ('expired', 'cancelled', 'redeemed')
    THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'VOUCHER_INACTIVE: Cannot deduct from an expired, redeemed, or cancelled voucher.';
    END IF;
END$$
DELIMITER ;

-- ────────────────────────────────────────────────────────────
--  4. TRIGGER: Auto-recycle expired balance → vault
--  Only for non-refundable vouchers.
-- ────────────────────────────────────────────────────────────
DROP TRIGGER IF EXISTS trg_recycle_expired_voucher;

DELIMITER $$
CREATE TRIGGER trg_recycle_expired_voucher
AFTER UPDATE ON vouchers
FOR EACH ROW
BEGIN
    IF NEW.status = 'expired'
       AND OLD.status != 'expired'
       AND NEW.remaining_balance > 0
       AND NEW.is_refundable = 0
    THEN
        UPDATE system_settings
           SET cashier_vault_points = cashier_vault_points + NEW.remaining_balance
         WHERE id = 1;
    END IF;
END$$
DELIMITER ;

-- ────────────────────────────────────────────────────────────
--  5. VIEW: v_vouchers_active
-- ────────────────────────────────────────────────────────────
CREATE OR REPLACE VIEW v_vouchers_active AS
SELECT
    v.id,
    v.voucher_code,
    v.visitor_name,
    v.visitor_contact,
    v.initial_value,
    v.remaining_balance,
    v.status,
    v.is_refundable,
    v.created_at,
    v.expires_at,
    TIMESTAMPDIFF(MINUTE, NOW(), v.expires_at)  AS minutes_until_expiry,
    CASE
        WHEN v.status != 'active'          THEN v.status
        WHEN NOW() > v.expires_at           THEN 'expired_pending'
        WHEN v.remaining_balance <= 0       THEN 'fully_redeemed'
        ELSE 'active'
    END AS computed_status,
    u.name      AS issued_by_name,
    v.use_count
FROM vouchers v
LEFT JOIN users u ON u.id = v.issued_by;
