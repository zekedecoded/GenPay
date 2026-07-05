-- ============================================================================
--  GenPay — Stall Application "One-Stop Shop" migration (Phase 4)
-- ----------------------------------------------------------------------------
--  Collapses the old multi-stage pipeline
--      Submit -> Admin accept -> Review -> Meeting -> Down payment -> Award
--  into a single in-person verification meeting that is AUTO-SCHEDULED at
--  submission:
--      Submit (meeting booked immediately) -> Awarded
--
--  This file is the manual/record copy of what
--  gjc_ensure_stall_application_workflow_schema() in connection/app.php applies
--  automatically and idempotently on page load. Running it by hand is optional;
--  it is safe to run once against an existing `ewallet` database.
--
--  Target: MySQL/MariaDB, database `ewallet`, table `stall_applications`.
--  Every statement is written to be re-runnable without error.
-- ============================================================================

-- ----------------------------------------------------------------------------
-- 1) New columns for the one-stop meeting (contract + payment recorded at the
--    single meeting, plus award/cancel audit trail). Uses IF NOT EXISTS so a
--    re-run is a no-op. (MySQL 8.0.29+/MariaDB 10.5+ support IF NOT EXISTS on
--    ADD COLUMN; on older servers the ensure-function in app.php handles this.)
-- ----------------------------------------------------------------------------
ALTER TABLE stall_applications
    ADD COLUMN IF NOT EXISTS meetup_scheduled_at             DATETIME     NULL,
    ADD COLUMN IF NOT EXISTS meetup_location                 VARCHAR(255) NULL,
    ADD COLUMN IF NOT EXISTS meetup_notes                    TEXT         NULL,
    ADD COLUMN IF NOT EXISTS meetup_scheduled_by             INT UNSIGNED NULL,
    ADD COLUMN IF NOT EXISTS meetup_scheduled_email_sent_at  DATETIME     NULL,
    ADD COLUMN IF NOT EXISTS contract_file                   VARCHAR(500) NULL,
    ADD COLUMN IF NOT EXISTS contract_uploaded_at            DATETIME     NULL,
    ADD COLUMN IF NOT EXISTS contract_uploaded_by            INT UNSIGNED NULL,
    ADD COLUMN IF NOT EXISTS deposit_amount                  DECIMAL(10,2) NULL,   -- 2 months deposit
    ADD COLUMN IF NOT EXISTS advance_amount                  DECIMAL(10,2) NULL,   -- 1 month advance
    ADD COLUMN IF NOT EXISTS rental_start_date               DATE         NULL,
    ADD COLUMN IF NOT EXISTS payment_schedule_day            TINYINT UNSIGNED NULL, -- 15 or 30
    ADD COLUMN IF NOT EXISTS awarded_by                      INT UNSIGNED NULL,
    ADD COLUMN IF NOT EXISTS awarded_at                      DATETIME     NULL,
    ADD COLUMN IF NOT EXISTS cancelled_by                    INT UNSIGNED NULL,
    ADD COLUMN IF NOT EXISTS cancelled_at                    DATETIME     NULL,
    ADD COLUMN IF NOT EXISTS cancel_reason                   TEXT         NULL;

-- ----------------------------------------------------------------------------
-- 2) Widen the status ENUM so BOTH the legacy values and the new vocabulary are
--    briefly valid. This lets us remap existing rows without truncation errors.
-- ----------------------------------------------------------------------------
ALTER TABLE stall_applications
    MODIFY status ENUM(
        -- legacy values (pre one-stop)
        'pending','review','meeting','down_payment','approval','active','approved','declined',
        -- final vocabulary
        'pending_verification','awarded','rejected','cancelled','expired'
    ) NOT NULL DEFAULT 'pending_verification';

-- ----------------------------------------------------------------------------
-- 3) Remap legacy rows onto the two-status model.
--      * Anything still mid-pipeline  -> pending_verification
--      * Anything already a live tenant -> awarded
--    Rejected/cancelled/expired are already terminal and keep their meaning.
-- ----------------------------------------------------------------------------
UPDATE stall_applications
   SET status = 'pending_verification'
 WHERE status IN ('pending','review','meeting','down_payment','approval');

UPDATE stall_applications
   SET status = 'awarded',
       awarded_at = COALESCE(awarded_at, reviewed_at, NOW())
 WHERE status IN ('active','approved');

UPDATE stall_applications
   SET status = 'rejected'
 WHERE status = 'declined';

-- ----------------------------------------------------------------------------
-- 4) Narrow the ENUM to the final vocabulary now that no legacy values remain.
-- ----------------------------------------------------------------------------
ALTER TABLE stall_applications
    MODIFY status ENUM(
        'pending_verification','awarded','rejected','cancelled','expired'
    ) NOT NULL DEFAULT 'pending_verification';

-- ----------------------------------------------------------------------------
-- 5) Helpful index for the admin "Today's Schedule" view and the scheduler's
--    per-day booked-slot lookup.
-- ----------------------------------------------------------------------------
-- (Wrapped-safe: ignore the "duplicate key name" error on a re-run.)
ALTER TABLE stall_applications
    ADD INDEX idx_stall_apps_meetup (meetup_scheduled_at);

-- ============================================================================
--  Done. After this, admin/stall_applications.php shows the two-status model
--  (Pending for Verification / Awarded) with the Today's Schedule appointment
--  log, and merchants can view/download their signed contract from the
--  merchant dashboard (merchant/contract.php).
-- ============================================================================
