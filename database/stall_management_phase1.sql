-- ============================================================
--  GenPay - Stall Management Phase 1
--  Database: ewallet
--  Generated: 2026-06-15
--  All statements are additive / idempotent.
-- ============================================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+08:00";

-- ============================================================
-- STEP 0 - Apply pending merchant_tenant_directory_migration
-- ============================================================

ALTER TABLE `merchant`
    ADD COLUMN IF NOT EXISTS `operational_status`
        ENUM('active','temporarily_closed','suspended','inactive')
        NOT NULL DEFAULT 'active'
        AFTER `stall_name`,
    ADD COLUMN IF NOT EXISTS `created_at`
        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        AFTER `operational_status`,
    ADD COLUMN IF NOT EXISTS `updated_at`
        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP
        AFTER `created_at`;

ALTER TABLE `merchant_inventory`
    ADD COLUMN IF NOT EXISTS `restricted_by`
        INT UNSIGNED NULL DEFAULT NULL
        COMMENT 'FK -> users.userID (admin who restricted the item)'
        AFTER `approved_by`,
    ADD COLUMN IF NOT EXISTS `restricted_at`
        DATETIME NULL DEFAULT NULL
        AFTER `restricted_by`;

ALTER TABLE `merchant_rent_payments`
    ADD COLUMN IF NOT EXISTS `payment_method`
        VARCHAR(40) NOT NULL DEFAULT 'cash'
        AFTER `payment_date`;

-- ============================================================
-- STEP 1 - CREATE stalls
-- ============================================================

CREATE TABLE IF NOT EXISTS `stalls` (
    `stall_id`           VARCHAR(10)      NOT NULL COMMENT 'Alphanumeric e.g. A1, B3',
    `label`              VARCHAR(60)      NOT NULL COMMENT 'Display label e.g. Stall A1',
    `row_label`          CHAR(1)          NOT NULL COMMENT 'Grid row: A or B',
    `col_number`         TINYINT UNSIGNED NOT NULL COMMENT 'Grid column: 1-5',
    `area_sqm`           DECIMAL(6,2)     NULL DEFAULT NULL,
    `monthly_rate`       DECIMAL(15,2)    NULL DEFAULT NULL,
    `status`             ENUM('vacant','occupied','pending_application') NOT NULL DEFAULT 'vacant',
    `merchant_id`        INT              NULL DEFAULT NULL COMMENT 'FK -> merchant.merchantID',
    `pending_expires_at` DATETIME         NULL DEFAULT NULL COMMENT 'NOW()+15min when pending_application',
    `created_at`         DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`         DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`stall_id`),
    KEY `idx_stall_status`   (`status`),
    KEY `idx_stall_merchant` (`merchant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
  COMMENT='Physical stall registry - source of truth for the public stall map';

INSERT IGNORE INTO `stalls` (`stall_id`,`label`,`row_label`,`col_number`,`area_sqm`,`monthly_rate`,`status`) VALUES
    ('A1','Stall A1','A',1,12.00,2500.00,'vacant'),
    ('A2','Stall A2','A',2,12.00,2500.00,'vacant'),
    ('A3','Stall A3','A',3,12.00,2500.00,'vacant'),
    ('A4','Stall A4','A',4,12.00,2500.00,'vacant'),
    ('A5','Stall A5','A',5,12.00,2500.00,'vacant'),
    ('B1','Stall B1','B',1,12.00,2500.00,'vacant'),
    ('B2','Stall B2','B',2,12.00,2500.00,'vacant'),
    ('B3','Stall B3','B',3,12.00,2500.00,'vacant'),
    ('B4','Stall B4','B',4,12.00,2500.00,'vacant'),
    ('B5','Stall B5','B',5,12.00,2500.00,'vacant');

-- ============================================================
-- STEP 2 - CREATE stall_applications
-- ============================================================

CREATE TABLE IF NOT EXISTS `stall_applications` (
    `id`               INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `stall_id`         VARCHAR(10)   NOT NULL COMMENT 'FK -> stalls.stall_id',
    `business_name`    VARCHAR(120)  NOT NULL,
    `proprietor_name`  VARCHAR(120)  NOT NULL,
    `contact_number`   VARCHAR(15)   NOT NULL COMMENT '09XXXXXXXXX format',
    `email`            VARCHAR(255)  NOT NULL,
    `profile_picture`  VARCHAR(500)  NOT NULL COMMENT 'Relative path to upload',
    `business_permit`  VARCHAR(500)  NOT NULL COMMENT 'Relative path to upload',
    `sanitary_permit`  VARCHAR(500)  NOT NULL COMMENT 'Relative path to upload',
    `gjc_requirements` VARCHAR(500)  NOT NULL COMMENT 'Relative path to upload',
    `clearance`        VARCHAR(500)  NOT NULL COMMENT 'Relative path to upload',
    `terms_accepted`   TINYINT(1)   NOT NULL DEFAULT 0,
    `status`           ENUM('pending','approved','rejected','expired') NOT NULL DEFAULT 'pending',
    `reviewed_by`      INT UNSIGNED  NULL DEFAULT NULL COMMENT 'FK -> users.userID',
    `reviewed_at`      DATETIME      NULL DEFAULT NULL,
    `rejection_reason` TEXT          NULL DEFAULT NULL,
    `created_at`       DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`       DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_sa_stall`   (`stall_id`),
    KEY `idx_sa_status`  (`status`),
    KEY `idx_sa_email`   (`email`),
    KEY `idx_sa_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
  COMMENT='Public stall applications with file paths';

-- ============================================================
-- STEP 3 - ALTER merchant: add stall_id
-- ============================================================

ALTER TABLE `merchant`
    ADD COLUMN IF NOT EXISTS `stall_id` VARCHAR(10) NULL DEFAULT NULL
        COMMENT 'FK -> stalls.stall_id'
        AFTER `stall_name`;

-- ============================================================
-- STEP 4 - ALTER merchant_leases: add stall_id
-- ============================================================

ALTER TABLE `merchant_leases`
    ADD COLUMN IF NOT EXISTS `stall_id` VARCHAR(10) NULL DEFAULT NULL
        COMMENT 'FK -> stalls.stall_id - NULL for pre-registry leases'
        AFTER `stall_number`;

-- ============================================================
-- STEP 5 - Foreign Key Constraints (conditional)
-- ============================================================

SET @fk1 = (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME='merchant' AND CONSTRAINT_NAME='fk_merchant_stall');
SET @sql1 = IF(@fk1=0,
    'ALTER TABLE `merchant` ADD CONSTRAINT `fk_merchant_stall` FOREIGN KEY (`stall_id`) REFERENCES `stalls`(`stall_id`) ON UPDATE CASCADE ON DELETE SET NULL',
    'SELECT 1');
PREPARE s FROM @sql1; EXECUTE s; DEALLOCATE PREPARE s;

SET @fk2 = (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME='merchant_leases' AND CONSTRAINT_NAME='fk_lease_stall');
SET @sql2 = IF(@fk2=0,
    'ALTER TABLE `merchant_leases` ADD CONSTRAINT `fk_lease_stall` FOREIGN KEY (`stall_id`) REFERENCES `stalls`(`stall_id`) ON UPDATE CASCADE ON DELETE SET NULL',
    'SELECT 1');
PREPARE s FROM @sql2; EXECUTE s; DEALLOCATE PREPARE s;

SET @fk3 = (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME='stall_applications' AND CONSTRAINT_NAME='fk_stallapps_stall');
SET @sql3 = IF(@fk3=0,
    'ALTER TABLE `stall_applications` ADD CONSTRAINT `fk_stallapps_stall` FOREIGN KEY (`stall_id`) REFERENCES `stalls`(`stall_id`) ON UPDATE CASCADE ON DELETE RESTRICT',
    'SELECT 1');
PREPARE s FROM @sql3; EXECUTE s; DEALLOCATE PREPARE s;

COMMIT;
-- ============================================================
-- END OF PHASE 1
-- ============================================================
