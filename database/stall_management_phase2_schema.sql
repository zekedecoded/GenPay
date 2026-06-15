-- ============================================================
--  GJC EduPay — Stall Management Phase 2 Schema Extension
--  Database: ewallet
--  Generated: 2026-06-15
--  All statements are additive / idempotent.
-- ============================================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+08:00";

-- ============================================================
-- STEP 1 — ALTER users: add force_password_change
-- ============================================================

ALTER TABLE `users`
    ADD COLUMN IF NOT EXISTS `force_password_change`
        TINYINT(1) NOT NULL DEFAULT 0
        COMMENT '1 = merchant must change password on next login'
        AFTER `sub_role`;

-- ============================================================
-- STEP 2a — ALTER stall_applications: extend status ENUM
--           Non-breaking — old values preserved
-- ============================================================

ALTER TABLE `stall_applications`
    MODIFY COLUMN `status`
        ENUM('pending','approved','rejected','expired','initially_approved','active')
        NOT NULL DEFAULT 'pending';

-- ============================================================
-- STEP 2b — ALTER stall_applications: add contract columns
-- ============================================================

ALTER TABLE `stall_applications`
    ADD COLUMN IF NOT EXISTS `contract_ref`
        VARCHAR(40) NULL DEFAULT NULL
        COMMENT 'Auto-generated: SA-{zero-padded-id}-{year}'
        AFTER `rejection_reason`,

    ADD COLUMN IF NOT EXISTS `signed_at`
        DATETIME NULL DEFAULT NULL
        COMMENT 'Set when admin confirms contract in Step 2.2'
        AFTER `contract_ref`,

    ADD COLUMN IF NOT EXISTS `initially_approved_by`
        INT(11) NULL DEFAULT NULL
        COMMENT 'FK -> users.userID (admin who clicked Initial Approval)'
        AFTER `signed_at`,

    ADD COLUMN IF NOT EXISTS `initially_approved_at`
        DATETIME NULL DEFAULT NULL
        AFTER `initially_approved_by`;

-- ============================================================
-- STEP 3 — CREATE payment_verifications
-- ============================================================

CREATE TABLE IF NOT EXISTS `payment_verifications` (
    `id`               INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `application_id`   INT UNSIGNED  NOT NULL
        COMMENT 'FK -> stall_applications.id',
    `amount`           DECIMAL(10,2) NOT NULL DEFAULT 150.00
        COMMENT 'Processing fee in PHP',
    `gcash_ref_number` VARCHAR(60)   NOT NULL
        COMMENT 'Admin-entered GCash reference number',
    `verified_by`      INT(11)       NOT NULL
        COMMENT 'FK -> users.userID (admin who recorded the payment)',
    `verified_at`      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `notes`            TEXT          NULL DEFAULT NULL,
    `created_at`       DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_pv_application`  (`application_id`),
    KEY `idx_pv_verified_by` (`verified_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
  COMMENT='Admin-recorded GCash payment proof for stall application processing fee';

-- ============================================================
-- STEP 4 — CREATE merchant_accounts (bridge / audit table)
-- ============================================================

CREATE TABLE IF NOT EXISTS `merchant_accounts` (
    `id`                  INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `application_id`      INT UNSIGNED  NOT NULL
        COMMENT 'FK -> stall_applications.id',
    `user_id`             INT UNSIGNED  NOT NULL
        COMMENT 'FK -> users.userID (the new merchant account)',
    `temp_password_plain` VARCHAR(100)  NULL DEFAULT NULL
        COMMENT 'Plaintext temp password - cleared after merchant changes password',
    `created_by`          INT(11)       NOT NULL
        COMMENT 'FK -> users.userID (admin who executed Final Approval)',
    `created_at`          DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_ma_application` (`application_id`),
    UNIQUE KEY `uq_ma_user`        (`user_id`),
    KEY `idx_ma_created_by` (`created_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
  COMMENT='Audit bridge: stall application -> users account created on Final Approval';

-- ============================================================
-- STEP 5 — Foreign Key Constraints (conditional)
-- ============================================================

-- payment_verifications.application_id -> stall_applications.id
SET @fk1 = (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME='payment_verifications'
    AND CONSTRAINT_NAME='fk_pv_application');
SET @sql1 = IF(@fk1=0,
    'ALTER TABLE `payment_verifications` ADD CONSTRAINT `fk_pv_application`
     FOREIGN KEY (`application_id`) REFERENCES `stall_applications`(`id`)
     ON UPDATE CASCADE ON DELETE RESTRICT',
    'SELECT 1');
PREPARE s FROM @sql1; EXECUTE s; DEALLOCATE PREPARE s;

-- payment_verifications.verified_by -> users.userID
SET @fk2 = (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME='payment_verifications'
    AND CONSTRAINT_NAME='fk_pv_verified_by');
SET @sql2 = IF(@fk2=0,
    'ALTER TABLE `payment_verifications` ADD CONSTRAINT `fk_pv_verified_by`
     FOREIGN KEY (`verified_by`) REFERENCES `users`(`userID`)
     ON UPDATE CASCADE ON DELETE RESTRICT',
    'SELECT 1');
PREPARE s FROM @sql2; EXECUTE s; DEALLOCATE PREPARE s;

-- merchant_accounts.application_id -> stall_applications.id
SET @fk3 = (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME='merchant_accounts'
    AND CONSTRAINT_NAME='fk_ma_application');
SET @sql3 = IF(@fk3=0,
    'ALTER TABLE `merchant_accounts` ADD CONSTRAINT `fk_ma_application`
     FOREIGN KEY (`application_id`) REFERENCES `stall_applications`(`id`)
     ON UPDATE CASCADE ON DELETE RESTRICT',
    'SELECT 1');
PREPARE s FROM @sql3; EXECUTE s; DEALLOCATE PREPARE s;

-- merchant_accounts.user_id -> users.userID
SET @fk4 = (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME='merchant_accounts'
    AND CONSTRAINT_NAME='fk_ma_user');
SET @sql4 = IF(@fk4=0,
    'ALTER TABLE `merchant_accounts` ADD CONSTRAINT `fk_ma_user`
     FOREIGN KEY (`user_id`) REFERENCES `users`(`userID`)
     ON UPDATE CASCADE ON DELETE RESTRICT',
    'SELECT 1');
PREPARE s FROM @sql4; EXECUTE s; DEALLOCATE PREPARE s;

-- merchant_accounts.created_by -> users.userID
SET @fk5 = (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME='merchant_accounts'
    AND CONSTRAINT_NAME='fk_ma_created_by');
SET @sql5 = IF(@fk5=0,
    'ALTER TABLE `merchant_accounts` ADD CONSTRAINT `fk_ma_created_by`
     FOREIGN KEY (`created_by`) REFERENCES `users`(`userID`)
     ON UPDATE CASCADE ON DELETE RESTRICT',
    'SELECT 1');
PREPARE s FROM @sql5; EXECUTE s; DEALLOCATE PREPARE s;

COMMIT;
-- ============================================================
-- END OF PHASE 2 SCHEMA MIGRATION
-- ============================================================
