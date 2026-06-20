-- ============================================================
-- GenPay - Admin Merchant/Tenant Directory Migration
-- Generated: 2026-06-09
-- Purpose:
--   Adds metadata and audit columns needed for the Super Admin
--   merchant stall directory and stall detail compliance view.
--
-- Privacy rule:
--   This migration does not add, expose, or aggregate merchant
--   cash sales or transaction-history data for admin stall details.
-- ============================================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+08:00";

ALTER TABLE `merchant`
    ADD COLUMN IF NOT EXISTS `operational_status` ENUM('active','temporarily_closed','suspended','inactive')
        NOT NULL DEFAULT 'active'
        AFTER `stall_name`,
    ADD COLUMN IF NOT EXISTS `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        AFTER `operational_status`,
    ADD COLUMN IF NOT EXISTS `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP
        AFTER `created_at`;

ALTER TABLE `merchant_inventory`
    ADD COLUMN IF NOT EXISTS `restricted_by` INT UNSIGNED NULL DEFAULT NULL
        COMMENT 'FK -> users.userID (admin who restricted/unrestricted the item)'
        AFTER `approved_by`,
    ADD COLUMN IF NOT EXISTS `restricted_at` DATETIME NULL DEFAULT NULL
        AFTER `restricted_by`;

ALTER TABLE `merchant_rent_payments`
    ADD COLUMN IF NOT EXISTS `payment_method` VARCHAR(40) NOT NULL DEFAULT 'cash'
        AFTER `payment_date`;

DELIMITER $$

CREATE PROCEDURE add_index_if_missing(
    IN p_table_name VARCHAR(64),
    IN p_index_name VARCHAR(64),
    IN p_index_sql TEXT
)
BEGIN
    IF NOT EXISTS (
        SELECT 1
        FROM information_schema.STATISTICS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = p_table_name
          AND INDEX_NAME = p_index_name
    ) THEN
        SET @ddl = p_index_sql;
        PREPARE stmt FROM @ddl;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;
    END IF;
END$$

DELIMITER ;

CALL add_index_if_missing('merchant', 'idx_merchant_status',
    'CREATE INDEX idx_merchant_status ON merchant (operational_status)');

CALL add_index_if_missing('merchant_inventory', 'idx_inventory_merchant_name',
    'CREATE INDEX idx_inventory_merchant_name ON merchant_inventory (merchant_user_id, product_name)');

CALL add_index_if_missing('merchant_inventory', 'idx_inventory_merchant_restricted',
    'CREATE INDEX idx_inventory_merchant_restricted ON merchant_inventory (merchant_user_id, is_restricted, is_available)');

CALL add_index_if_missing('merchant_rent_payments', 'idx_rent_lease_period',
    'CREATE INDEX idx_rent_lease_period ON merchant_rent_payments (lease_id, period_covered, payment_date)');

DROP PROCEDURE add_index_if_missing;

COMMIT;

-- ============================================================
-- END
-- ============================================================
