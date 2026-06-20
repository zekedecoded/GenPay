-- ============================================================
--  Phase 3 — Unified 4-Step Application Pipeline
--  Source requirement: adviser feedback session (SIR EMMAN 4.mp3)
--
--  Collapses the old pending/awaiting_meetup/awaiting_approval/
--  approved status chain (admin/stall_applications.php) and the
--  separate payment_verifications/merchant_accounts contract-review
--  flow (admin/stall_verify.php, now retired) into one linear
--  pipeline: review -> meeting -> down_payment -> approval -> active.
--
--  Also drops the requirement that an applicant pick a specific
--  stall before being allowed to submit (stall_id is now assigned
--  by the admin at Step 4 / Approval).
-- ============================================================

START TRANSACTION;

-- ------------------------------------------------------------
-- 1. stall_applications: nullable stall_id + step tracker
-- ------------------------------------------------------------
ALTER TABLE `stall_applications`
    MODIFY `stall_id` VARCHAR(10) NULL DEFAULT NULL
        COMMENT 'Assigned by admin at Step 4 (Approval/Award); NULL until then',
    ADD COLUMN `current_step` TINYINT UNSIGNED NOT NULL DEFAULT 1
        COMMENT '1=Review 2=Meeting 3=Down Payment 4=Approval' AFTER `status`;

-- ------------------------------------------------------------
-- 2. Migrate the status ENUM to the new 4-step vocabulary
--    (widen first so old + new values co-exist during data migration)
-- ------------------------------------------------------------
ALTER TABLE `stall_applications`
    MODIFY `status` ENUM(
        'pending','awaiting_meetup','awaiting_approval','active','rejected','expired',
        'initially_approved','approved',
        'review','meeting','down_payment','approval'
    ) NOT NULL DEFAULT 'review';

UPDATE `stall_applications` SET `status` = 'review',       `current_step` = 1 WHERE `status` = 'pending';
UPDATE `stall_applications` SET `status` = 'meeting',      `current_step` = 2 WHERE `status` IN ('initially_approved','awaiting_meetup');
UPDATE `stall_applications` SET `status` = 'approval',     `current_step` = 4 WHERE `status` = 'awaiting_approval';
UPDATE `stall_applications` SET `status` = 'active',       `current_step` = 4 WHERE `status` IN ('approved', 'active');

-- ------------------------------------------------------------
-- 3. Archive table for early rejections (Step 1 / Step 2 only)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `archived_rejections` (
    `id`                       INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `original_application_id` INT UNSIGNED NOT NULL COMMENT 'Original stall_applications.id',
    `rejected_at_step`        TINYINT UNSIGNED NOT NULL COMMENT '1=Review or 2=Meeting',
    `business_name`           VARCHAR(120)  NOT NULL,
    `proprietor_name`         VARCHAR(120)  NOT NULL,
    `contact_number`          VARCHAR(15)   NOT NULL,
    `email`                   VARCHAR(255)  NOT NULL,
    `profile_picture`         VARCHAR(500)  NULL,
    `business_permit`         VARCHAR(500)  NULL,
    `sanitary_permit`         VARCHAR(500)  NULL,
    `gjc_requirements`        VARCHAR(500)  NULL,
    `clearance`               VARCHAR(500)  NULL,
    `rejection_reason`        TEXT          NOT NULL,
    `rejected_by`             INT UNSIGNED  NOT NULL COMMENT 'FK -> users.userID',
    `rejected_at`             DATETIME      NOT NULL,
    `archived_at`             DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `reactivated`             TINYINT(1)    NOT NULL DEFAULT 0,
    PRIMARY KEY (`id`),
    KEY `idx_ar_original` (`original_application_id`),
    KEY `idx_ar_email`    (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
  COMMENT='Applications declined during Step 1 (Review) or Step 2 (Meeting); kept for reference/reactivation';

-- ------------------------------------------------------------
-- 4. Move any already-rejected applications into the archive,
--    then drop them from the live pipeline table.
--    (Old data has no recorded step; best-effort default to 1.)
-- ------------------------------------------------------------
INSERT INTO `archived_rejections`
    (original_application_id, rejected_at_step, business_name, proprietor_name,
     contact_number, email, profile_picture, business_permit, sanitary_permit,
     gjc_requirements, clearance, rejection_reason, rejected_by, rejected_at)
SELECT
    id, 1, business_name, proprietor_name, contact_number, email,
    profile_picture, business_permit, sanitary_permit, gjc_requirements, clearance,
    COALESCE(rejection_reason, 'Rejected (reason not recorded prior to pipeline migration)'),
    COALESCE(reviewed_by, 0),
    COALESCE(reviewed_at, updated_at)
FROM `stall_applications`
WHERE `status` = 'rejected';

DELETE FROM `stall_applications` WHERE `status` = 'rejected';

-- ------------------------------------------------------------
-- 5. Narrow the ENUM down to the final 4-step vocabulary only
-- ------------------------------------------------------------
ALTER TABLE `stall_applications`
    MODIFY `status` ENUM('review','meeting','down_payment','approval','active','expired')
        NOT NULL DEFAULT 'review';

COMMIT;
-- ============================================================
-- END OF PHASE 3
-- ============================================================
