<?php
/**
 * StallManager — Data access layer for the Stall Management feature.
 *
 * Responsibilities:
 *  - Flush expired pending_application stalls (application-level event scheduler)
 *  - Fetch all stalls with live status for the map visualizer
 *  - Fetch a single stall detail for the peek modal
 *  - Lock a stall to pending_application for 15 minutes on apply
 *  - Release a stall lock (on submit success or manual clear)
 */
class StallManager
{
    private PDO $db;

    /** Number of minutes a pending_application lock holds before auto-expiry */
    const PENDING_MINUTES = 15;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    /**
     * Flush expired pending_application locks back to vacant.
     * Call this at the top of stalls.php and apply.php before any render.
     */
    public function flushExpiredPending(): int
    {
        $stmt = $this->db->prepare(
            "UPDATE stalls
             SET status              = 'vacant',
                 pending_expires_at  = NULL
             WHERE status = 'pending_application'
               AND pending_expires_at IS NOT NULL
               AND pending_expires_at < NOW()"
        );
        $stmt->execute();
        return $stmt->rowCount();
    }

    /**
     * Return all 10 stalls ordered by grid position (row A→B, col 1→5).
     * Joins merchant for occupied stall display name.
     */
    public function allStalls(): array
    {
        $rows = $this->db->query(
            "SELECT
                s.stall_id,
                s.label,
                s.row_label,
                s.col_number,
                s.area_sqm,
                s.monthly_rate,
                s.status,
                s.merchant_id,
                s.pending_expires_at,
                m.stall_name   AS merchant_stall_name,
                u.profile_img  AS merchant_logo
             FROM stalls s
             LEFT JOIN merchant m ON m.merchantID = s.merchant_id
             LEFT JOIN users    u ON u.userID      = m.userID
             ORDER BY s.row_label ASC, s.col_number ASC"
        )->fetchAll(PDO::FETCH_ASSOC);

        return array_map([$this, 'normaliseStall'], $rows);
    }

    /**
     * Return a single stall or null if not found.
     */
    public function getStall(string $stallId): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT
                s.stall_id,
                s.label,
                s.row_label,
                s.col_number,
                s.area_sqm,
                s.monthly_rate,
                s.status,
                s.merchant_id,
                s.pending_expires_at,
                m.stall_name   AS merchant_stall_name,
                u.profile_img  AS merchant_logo
             FROM stalls s
             LEFT JOIN merchant m ON m.merchantID = s.merchant_id
             LEFT JOIN users    u ON u.userID      = m.userID
             WHERE s.stall_id = ?
             LIMIT 1"
        );
        $stmt->execute([strtoupper(trim($stallId))]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? $this->normaliseStall($row) : null;
    }

    /**
     * Lock a stall to pending_application for PENDING_MINUTES.
     * Returns false if the stall is not vacant at the moment of locking
     * (race condition guard — uses a conditional UPDATE).
     */
    public function lockStall(string $stallId): bool
    {
        $stmt = $this->db->prepare(
            "UPDATE stalls
             SET status             = 'pending_application',
                 pending_expires_at = DATE_ADD(NOW(), INTERVAL ? MINUTE)
             WHERE stall_id = ?
               AND status   = 'vacant'"
        );
        $stmt->execute([self::PENDING_MINUTES, strtoupper(trim($stallId))]);
        return $stmt->rowCount() === 1;
    }

    /**
     * Release a pending lock immediately (e.g., after successful form submit
     * the stall moves to 'occupied', or on error rollback it returns to 'vacant').
     */
    public function releaseStall(string $stallId, string $newStatus = 'vacant'): void
    {
        $allowed = ['vacant', 'occupied'];
        if (!in_array($newStatus, $allowed, true)) {
            $newStatus = 'vacant';
        }
        $stmt = $this->db->prepare(
            "UPDATE stalls
             SET status             = ?,
                 pending_expires_at = NULL
             WHERE stall_id = ?"
        );
        $stmt->execute([$newStatus, strtoupper(trim($stallId))]);
    }

    // ----------------------------------------------------------------
    // Private helpers
    // ----------------------------------------------------------------

    private function normaliseStall(array $row): array
    {
        $expiresAt   = $row['pending_expires_at'] ?? null;
        $secondsLeft = null;

        if ($expiresAt && $row['status'] === 'pending_application') {
            $secondsLeft = max(0, strtotime($expiresAt) - time());
        }

        return [
            'stall_id'             => (string) $row['stall_id'],
            'label'                => (string) $row['label'],
            'row_label'            => (string) $row['row_label'],
            'col_number'           => (int)    $row['col_number'],
            'area_sqm'             => $row['area_sqm'] !== null ? (float) $row['area_sqm'] : null,
            'monthly_rate'         => $row['monthly_rate'] !== null ? (float) $row['monthly_rate'] : null,
            'status'               => (string) $row['status'],
            'merchant_id'          => $row['merchant_id'] !== null ? (int) $row['merchant_id'] : null,
            'pending_expires_at'   => $expiresAt,
            'pending_seconds_left' => $secondsLeft,
            'merchant_stall_name'  => (string) ($row['merchant_stall_name'] ?? ''),
            'merchant_logo'        => (string) ($row['merchant_logo'] ?? ''),
        ];
    }
}
