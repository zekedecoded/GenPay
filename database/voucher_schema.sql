-- ═══════════════════════════════════════════════════════════
--  GenPay — Visitor Voucher Schema
--  Run ONCE against the 'ewallet' database.
--  Safe to re-run (uses IF NOT EXISTS / CREATE OR REPLACE).
-- ═══════════════════════════════════════════════════════════

USE ewallet;

-- ────────────────────────────────────────────────────────────
--  1. VOUCHERS TABLE
-- ────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS vouchers (
    id                 INT           UNSIGNED NOT NULL AUTO_INCREMENT,

    -- QR payload — the hash embedded in the QR code
    qr_code_hash       VARCHAR(64)   NOT NULL UNIQUE
                           COMMENT 'SHA-256 of (voucher_id + secret_salt). Scanned by merchant.',

    -- Human-readable code for admin display (e.g. VCH-A1B2C3)
    voucher_code       VARCHAR(32)   NOT NULL UNIQUE,

    -- Visitor identity
    visitor_name       VARCHAR(120)  NOT NULL,
    visitor_contact    VARCHAR(60)   NULL,

    -- Money columns
    initial_value      DECIMAL(15,2) NOT NULL
                           COMMENT 'Points loaded at time of creation. Never changes.',
    remaining_balance  DECIMAL(15,2) NOT NULL
                           COMMENT 'Decreases with every voucher payment.',

    -- Lifecycle
    status             ENUM('active','redeemed','expired','cancelled')
                           NOT NULL DEFAULT 'active',
    created_at         DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    expires_at         DATETIME      NOT NULL
                           COMMENT 'created_at + 24 hours. Checked lazily on every scan.',
    expired_at         DATETIME      NULL
                           COMMENT 'Timestamp when expiry was actually triggered.',
    redeemed_at        DATETIME      NULL,
    cancelled_at       DATETIME      NULL,

    -- Closed-economy flags
    is_refundable      TINYINT(1)    NOT NULL DEFAULT 0
                           COMMENT '0 = Non-refundable (default). On expiry, balance goes to vault, NOT returned to visitor.',
    issued_by          INT           UNSIGNED NOT NULL
                           COMMENT 'Cashier / admin who created this voucher.',

    -- Audit
    last_used_at       DATETIME      NULL,
    use_count          SMALLINT      UNSIGNED NOT NULL DEFAULT 0,
    notes              TEXT          NULL,

    CONSTRAINT pk_vouchers PRIMARY KEY (id),
    CONSTRAINT chk_v_balance  CHECK (remaining_balance >= 0),
    CONSTRAINT chk_v_initial  CHECK (initial_value > 0),
    CONSTRAINT chk_v_expires  CHECK (expires_at > created_at),

    INDEX idx_v_hash   (qr_code_hash),
    INDEX idx_v_status (status),
    INDEX idx_v_issuer (issued_by),
    INDEX idx_v_expiry (expires_at, status)
) ENGINE=InnoDB
  COMMENT='Visitor QR vouchers. Non-refundable by default. Expiry handled lazily at scan time.';


-- ────────────────────────────────────────────────────────────
--  2. VOUCHER_PAYMENT_LOG TABLE
--  Immutable ledger for every successful voucher scan payment.
-- ────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS voucher_payment_log (
    id                 INT           UNSIGNED NOT NULL AUTO_INCREMENT,
    voucher_id         INT           UNSIGNED NOT NULL,
    merchant_wallet_id INT           UNSIGNED NOT NULL,
    amount             DECIMAL(15,2) NOT NULL,
    balance_before     DECIMAL(15,2) NOT NULL,
    balance_after      DECIMAL(15,2) NOT NULL,
    scanned_by         INT           UNSIGNED NULL
                           COMMENT 'Merchant user ID (optional — may be anonymous scan)',
    transaction_ref    VARCHAR(64)   NULL,
    created_at         DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT pk_vpl PRIMARY KEY (id),
    INDEX idx_vpl_voucher  (voucher_id),
    INDEX idx_vpl_merchant (merchant_wallet_id)
) ENGINE=InnoDB
  COMMENT='Append-only record of every deduction from a visitor voucher.';


-- ────────────────────────────────────────────────────────────
--  3. TRIGGER: Prevent voucher from being used after expiry
--  at the DB level (secondary safety net — primary is PHP).
-- ────────────────────────────────────────────────────────────
DROP TRIGGER IF EXISTS trg_block_expired_voucher_use;

DELIMITER $$
CREATE TRIGGER trg_block_expired_voucher_use
BEFORE UPDATE ON vouchers
FOR EACH ROW
BEGIN
    -- Block any balance reduction on an expired/cancelled voucher
    IF NEW.remaining_balance < OLD.remaining_balance
       AND OLD.status IN ('expired', 'cancelled', 'redeemed')
    THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'VOUCHER_INACTIVE: Cannot deduct from an expired, redeemed, or cancelled voucher.';
    END IF;
END$$
DELIMITER ;


-- ────────────────────────────────────────────────────────────
--  4. TRIGGER: Auto-recycle expired voucher balance → vault
--  Fires when status changes TO 'expired'.
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
        -- Non-refundable: remaining balance goes to vault (closed-economy rule)
        UPDATE system_settings
           SET cashier_vault_points = cashier_vault_points + NEW.remaining_balance
         WHERE id = 1;
    END IF;
END$$
DELIMITER ;


-- ────────────────────────────────────────────────────────────
--  5. VIEW: v_vouchers_active — dashboard quick view
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
    TIMESTAMPDIFF(MINUTE, NOW(), v.expires_at) AS minutes_until_expiry,
    CASE
        WHEN v.status != 'active'         THEN v.status
        WHEN NOW() > v.expires_at          THEN 'expired_pending'
        WHEN v.remaining_balance <= 0      THEN 'fully_redeemed'
        ELSE 'active'
    END AS computed_status,
    u.name AS issued_by_name,
    v.use_count
FROM vouchers v
LEFT JOIN users u ON u.id = v.issued_by;
