<?php

/**
 * MintingGuard.php
 * ─────────────────────────────────────────────────────────────────────────────
 * Middleware layer that sits between the admin UI and CirculationEngine.
 * Enforces the monthly minting soft-limit (₱50,000/month) and requires
 * a second-factor PIN when the limit would be exceeded.
 *
 * Usage:
 *   $guard = new MintingGuard($db);
 *   $result = $guard->attemptMint($superAdminId, 10000, 'Q2 budget allocation', $pin);
 * ─────────────────────────────────────────────────────────────────────────────
 */

declare(strict_types=1);

require_once __DIR__ . '/CirculationEngine.php';

class MintingGuard
{
    /** Monthly soft limit (₱) — requires PIN above this */
    public const SOFT_LIMIT = 50_000.00;

    /** Hard absolute cap — no mint allowed beyond this per month, even with PIN */
    public const HARD_LIMIT = 500_000.00;

    /** Super-Admin role ID */
    private const ROLE_SUPER_ADMIN = 3;

    private CirculationEngine $engine;

    public function __construct(private PDO $db)
    {
        $this->engine = new CirculationEngine($db);
    }

    // ══════════════════════════════════════════════════════════════════════════
    //  PRIMARY ENTRYPOINT
    // ══════════════════════════════════════════════════════════════════════════

    /**
     * Attempt to mint new points into the economy.
     *
     * @param int         $superAdminId  Must be Super-Admin
     * @param float       $amount        Points to mint (> 0)
     * @param string      $reason        Audit justification (required)
     * @param string|null $pin           Plaintext PIN — required if soft limit exceeded
     *
     * @throws RuntimeException on any validation or DB failure
     * @return array {
     *   success, new_cap, new_vault, minted_this_month,
     *   remaining_soft_limit, soft_limit_exceeded, reference
     * }
     */
    public function attemptMint(
        int $superAdminId,
        float $amount,
        string $reason,
        ?string $pin = null
    ): array {
        // ── 1. Role check ────────────────────────────────────────────────────
        $this->assertSuperAdmin($superAdminId);

        // ── 2. Amount sanity ─────────────────────────────────────────────────
        if ($amount <= 0) {
            throw new RuntimeException('Mint amount must be greater than zero.');
        }
        if (trim($reason) === '') {
            throw new RuntimeException('A justification reason is required for all minting operations.');
        }

        // ── 3. Monthly limit calculation ─────────────────────────────────────
        $mintedSoFar     = $this->getMintedThisMonth();
        $projectedTotal  = $mintedSoFar + $amount;

        // ── 4. Hard limit — no way through ───────────────────────────────────
        if ($projectedTotal > self::HARD_LIMIT) {
            throw new RuntimeException(sprintf(
                'HARD_LIMIT_EXCEEDED: Monthly minting of ₱%s would exceed the absolute ceiling ' .
                'of ₱%s/month (already minted ₱%s this month). ' .
                'Contact the Board of Administrators to authorize an exceptional increase.',
                number_format($amount, 2),
                number_format(self::HARD_LIMIT, 2),
                number_format($mintedSoFar, 2)
            ));
        }

        // ── 5. Soft limit — requires PIN ──────────────────────────────────────
        $softLimitExceeded = ($projectedTotal > self::SOFT_LIMIT);
        if ($softLimitExceeded) {
            $this->verifyMintPin($superAdminId, $pin, $mintedSoFar, $amount);
        }

        // ── 6. Delegate to CirculationEngine ─────────────────────────────────
        $result = $this->engine->increaseCirculationCap($amount, $superAdminId, $reason);

        return array_merge($result, [
            'success'               => true,
            'minted_this_month'     => $projectedTotal,
            'remaining_soft_limit'  => max(0, self::SOFT_LIMIT - $projectedTotal),
            'soft_limit_exceeded'   => $softLimitExceeded,
            'mint_events_this_month'=> $this->getMintEventCountThisMonth(),
        ]);
    }


    // ══════════════════════════════════════════════════════════════════════════
    //  PIN MANAGEMENT
    // ══════════════════════════════════════════════════════════════════════════

    /**
     * Set (or update) the mint PIN for a super-admin.
     * Stores a bcrypt hash in users.mint_pin.
     *
     * @param int    $superAdminId  Must be the current session's super-admin
     * @param string $newPin        Plaintext new PIN (4-8 digits recommended)
     * @param string $currentPassword Admin's current account password for confirmation
     */
    public function setMintPin(int $superAdminId, string $newPin, string $currentPassword): bool
    {
        $this->assertSuperAdmin($superAdminId);

        // Verify current account password first
        $stmt = $this->db->prepare("SELECT password FROM users WHERE id = ?");
        $stmt->execute([$superAdminId]);
        $hash = $stmt->fetchColumn();

        // Support both plaintext (legacy) and bcrypt (modern)
        $passwordValid = ($currentPassword === $hash) || password_verify($currentPassword, $hash);
        if (!$passwordValid) {
            throw new RuntimeException('INVALID_PASSWORD: Current account password is incorrect.');
        }

        if (strlen($newPin) < 4 || strlen($newPin) > 12) {
            throw new RuntimeException('PIN must be between 4 and 12 characters.');
        }

        $pinHash = password_hash($newPin, PASSWORD_BCRYPT);
        $this->db->prepare("UPDATE users SET mint_pin = ? WHERE id = ?")->execute([$pinHash, $superAdminId]);

        return true;
    }


    // ══════════════════════════════════════════════════════════════════════════
    //  DASHBOARD DATA
    // ══════════════════════════════════════════════════════════════════════════

    /**
     * Returns the full monthly minting summary for the admin dashboard widget.
     */
    public function getMonthlyMintingReport(): array
    {
        $stmt = $this->db->prepare("
            SELECT
                COALESCE(SUM(amount_added), 0)  AS minted_this_month,
                COUNT(*)                         AS mint_events,
                MIN(created_at)                  AS first_mint_at,
                MAX(created_at)                  AS last_mint_at
            FROM cap_increase_log
            WHERE MONTH(created_at) = MONTH(CURDATE())
              AND YEAR(created_at)  = YEAR(CURDATE())
        ");
        $stmt->execute();
        $row = $stmt->fetch();

        $minted = (float)$row['minted_this_month'];

        return [
            'minted_this_month'      => $minted,
            'mint_events'            => (int)$row['mint_events'],
            'first_mint_at'          => $row['first_mint_at'],
            'last_mint_at'           => $row['last_mint_at'],
            'soft_limit'             => self::SOFT_LIMIT,
            'hard_limit'             => self::HARD_LIMIT,
            'remaining_soft_limit'   => max(0, self::SOFT_LIMIT - $minted),
            'soft_limit_used_pct'    => min(100, round(($minted / self::SOFT_LIMIT) * 100, 1)),
            'soft_limit_exceeded'    => $minted >= self::SOFT_LIMIT,
            'hard_limit_exceeded'    => $minted >= self::HARD_LIMIT,
            'requires_pin'           => $minted >= self::SOFT_LIMIT,
        ];
    }

    /**
     * Full cap increase audit log (paginated).
     */
    public function getCapIncreaseLog(int $limit = 20, int $offset = 0): array
    {
        $stmt = $this->db->prepare("
            SELECT
                c.*,
                u.email  AS admin_email,
                u.name   AS admin_name
            FROM cap_increase_log c
            LEFT JOIN users u ON u.id = c.super_admin_id
            ORDER BY c.created_at DESC
            LIMIT ? OFFSET ?
        ");
        $stmt->execute([$limit, $offset]);
        return $stmt->fetchAll();
    }


    // ══════════════════════════════════════════════════════════════════════════
    //  PRIVATE HELPERS
    // ══════════════════════════════════════════════════════════════════════════

    private function assertSuperAdmin(int $userId): void
    {
        $stmt = $this->db->prepare("SELECT roleID FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $role = $stmt->fetchColumn();
        if ((int)$role !== self::ROLE_SUPER_ADMIN) {
            throw new RuntimeException('ACCESS_DENIED: Only Super-Admins can mint points.');
        }
    }

    private function getMintedThisMonth(): float
    {
        $stmt = $this->db->query("
            SELECT COALESCE(SUM(amount_added), 0)
            FROM cap_increase_log
            WHERE MONTH(created_at) = MONTH(CURDATE())
              AND YEAR(created_at)  = YEAR(CURDATE())
        ");
        return (float)$stmt->fetchColumn();
    }

    private function getMintEventCountThisMonth(): int
    {
        $stmt = $this->db->query("
            SELECT COUNT(*)
            FROM cap_increase_log
            WHERE MONTH(created_at) = MONTH(CURDATE())
              AND YEAR(created_at)  = YEAR(CURDATE())
        ");
        return (int)$stmt->fetchColumn();
    }

    private function verifyMintPin(int $adminId, ?string $pin, float $minted, float $requested): void
    {
        if ($pin === null || trim($pin) === '') {
            throw new RuntimeException(sprintf(
                'PIN_REQUIRED: Monthly minting has reached ₱%s. ' .
                'Your requested ₱%s would exceed the soft limit of ₱%s/month. ' .
                'Provide your Mint PIN to authorize this exceptional increase.',
                number_format($minted, 2),
                number_format($requested, 2),
                number_format(self::SOFT_LIMIT, 2)
            ));
        }

        $stmt = $this->db->prepare("SELECT mint_pin FROM users WHERE id = ?");
        $stmt->execute([$adminId]);
        $hash = $stmt->fetchColumn();

        if (!$hash) {
            throw new RuntimeException(
                'NO_MINT_PIN_SET: You have not configured a Mint PIN yet. ' .
                'Set one in Super-Admin Settings before minting above the monthly limit.'
            );
        }

        if (!password_verify($pin, $hash)) {
            // Log the failed attempt
            error_log("[MintingGuard] Failed PIN attempt by admin #{$adminId} at " . date('Y-m-d H:i:s'));
            throw new RuntimeException('MINT_PIN_INVALID: The Mint PIN you entered is incorrect.');
        }
    }
}
