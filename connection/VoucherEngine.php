<?php
/**
 * VoucherEngine.php
 * ═══════════════════════════════════════════════════════════════════════════
 * Visitor Voucher Module — GJC EduPay
 *
 * Implements the full visitor lifecycle:
 *   1. createVoucher()  — Cashier mints QR voucher (Vault → Voucher pool)
 *   2. scanValidate()   — LAZY EXPIRY MIDDLEWARE: checks hash, time, balance
 *   3. voucherPay()     — Merchant accepts voucher payment (Voucher → Merchant)
 *   4. expireVoucher()  — Admin/lazy recycler (Voucher remainder → Vault)
 *   5. listVouchers()   — Dashboard data
 *
 * CLOSED-ECONOMY RULE:
 *   Every peso that enters the voucher pool must come from the Vault.
 *   On expiry, unused balance always returns to the Vault (never lost,
 *   never created). is_refundable = 0 means visitor cannot get cash back.
 * ═══════════════════════════════════════════════════════════════════════════
 */

declare(strict_types=1);
require_once __DIR__ . '/pdo.php';
require_once __DIR__ . '/CirculationEngine.php';

class VoucherEngine
{
    /** Secret pepper added to QR hash to prevent forgery */
    private const QR_PEPPER = 'GJC_EDUPAY_VOUCHER_v1';

    /** Default voucher TTL in hours */
    public const DEFAULT_EXPIRY_HOURS = 24;

    private CirculationEngine $ce;

    public function __construct(private PDO $db)
    {
        $this->ce = new CirculationEngine($db);
    }

    // ═══════════════════════════════════════════════════════
    //  1. CREATE VOUCHER (Cashier mints QR)
    // ═══════════════════════════════════════════════════════

    /**
     * "Mint" a visitor voucher.
     *
     * Flow: Vault ──amount──► Voucher pool
     *
     * @param float  $amount        Points to load (must be > 0)
     * @param string $visitorName   Full name of visitor
     * @param string $visitorContact Phone / ID (optional)
     * @param int    $issuedBy      Cashier's user ID
     * @param int    $expiryHours   Defaults to 24 hrs
     * @param bool   $isRefundable  Defaults to false (non-refundable on expiry)
     *
     * @return array {
     *   voucher_id, voucher_code, qr_code_hash, expires_at,
     *   initial_value, qr_payload (JSON string for QR generation)
     * }
     */
    public function createVoucher(
        float $amount,
        string $visitorName,
        string $visitorContact = '',
        int $issuedBy = 0,
        int $expiryHours = self::DEFAULT_EXPIRY_HOURS,
        bool $isRefundable = false
    ): array {
        if ($amount <= 0) {
            throw new InvalidArgumentException('Voucher value must be greater than zero.');
        }
        if (trim($visitorName) === '') {
            throw new InvalidArgumentException('Visitor name is required.');
        }
        if ($expiryHours < 1 || $expiryHours > 168) {
            throw new InvalidArgumentException('Expiry must be between 1 and 168 hours.');
        }

        $this->db->beginTransaction();
        try {
            // ── Lock vault ──────────────────────────────────────────────
            $settings = $this->db->query(
                "SELECT * FROM system_settings WHERE id = 1 FOR UPDATE"
            )->fetch();

            if ((float) $settings['cashier_vault_points'] < $amount) {
                throw new RuntimeException(sprintf(
                    'VAULT_INSUFFICIENT: Vault only has ₱%s but voucher requires ₱%s.',
                    number_format((float) $settings['cashier_vault_points'], 2),
                    number_format($amount, 2)
                ));
            }

            // ── Generate unique code & hash ──────────────────────────────
            $voucherCode = $this->generateCode();
            $expiresAt = date('Y-m-d H:i:s', strtotime("+{$expiryHours} hours"));

            // ── Insert voucher row ───────────────────────────────────────
            $this->db->prepare(
                "INSERT INTO vouchers
                    (qr_code_hash, voucher_code, visitor_name, visitor_contact,
                     initial_value, remaining_balance, status,
                     expires_at, is_refundable, issued_by)
                 VALUES ('__PENDING__', ?, ?, ?, ?, ?, 'active', ?, ?, ?)"
            )->execute([
                        $voucherCode,
                        $visitorName,
                        $visitorContact,
                        $amount,
                        $amount,
                        $expiresAt,
                        $isRefundable ? 1 : 0,
                        $issuedBy,
                    ]);
            $voucherId = (int) $this->db->lastInsertId();

            // ── Build QR hash using the real ID ──────────────────────────
            $qrHash = $this->buildQrHash($voucherId, $voucherCode);

            $this->db->prepare(
                "UPDATE vouchers SET qr_code_hash = ? WHERE id = ?"
            )->execute([$qrHash, $voucherId]);

            // ── Deduct from vault ────────────────────────────────────────
            $this->db->prepare(
                "UPDATE system_settings
                    SET cashier_vault_points = cashier_vault_points - ?
                  WHERE id = 1"
            )->execute([$amount]);

            // ── Integrity check ──────────────────────────────────────────
            $this->validateCirculation((float) $settings['total_circulation_cap']);

            // ── Audit log ────────────────────────────────────────────────
            $ref = 'VOU-' . strtoupper(date('Ymd')) . '-' . str_pad(
                (string) $voucherId,
                5,
                '0',
                STR_PAD_LEFT
            );
            $this->db->prepare(
                "INSERT INTO transactions
                    (reference_no, transaction_type, initiated_by, voucher_id,
                     amount, vault_before, vault_after, total_in_circulation,
                     status, notes)
                 VALUES (?, 'voucher_create', ?, ?, ?, ?, ?,
                    (SELECT cashier_vault_points +
                        COALESCE((SELECT SUM(balance) FROM student_wallets),0) +
                        COALESCE((SELECT SUM(balance) FROM merchant_wallets),0) +
                        COALESCE((SELECT SUM(remaining_balance) FROM vouchers WHERE status='active'),0)
                     FROM system_settings WHERE id=1),
                    'completed', ?)"
            )->execute([
                        $ref,
                        $issuedBy,
                        $voucherId,
                        $amount,
                        (float) $settings['cashier_vault_points'],
                        (float) $settings['cashier_vault_points'] - $amount,
                        "Voucher {$voucherCode} issued to {$visitorName} · exp {$expiresAt}",
                    ]);

            $this->db->commit();

            // QR payload — this is what gets encoded into the QR image
            $qrPayload = json_encode([
                'type' => 'VISITOR_VOUCHER',
                'hash' => $qrHash,
                'code' => $voucherCode,
                'exp' => $expiresAt,
                'issuer' => 'GJC-EDUPAY',
            ]);

            return [
                'success' => true,
                'voucher_id' => $voucherId,
                'voucher_code' => $voucherCode,
                'qr_code_hash' => $qrHash,
                'qr_payload' => $qrPayload,
                'initial_value' => $amount,
                'expires_at' => $expiresAt,
                'is_refundable' => $isRefundable,
                'reference' => $ref,
            ];

        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    // ═══════════════════════════════════════════════════════
    //  2. SCAN VALIDATE — THE LAZY EXPIRY MIDDLEWARE
    // ═══════════════════════════════════════════════════════

    /**
     * THE CRITICAL MIDDLEWARE — Call this BEFORE any voucher payment.
     *
     * Two-step validation:
     *   Step A: Hash integrity — is this QR genuine?
     *   Step B: Lazy expiry   — has it passed 24 hours?
     *           If yes → immediately expire it and return an error.
     *           Expiry is handled RIGHT HERE, not by a cron job.
     *
     * @param string $qrHash        The raw hash scanned from the QR code
     * @param int    $scannedBy     Merchant's user ID
     *
     * @return array {
     *   valid (bool), voucher (array|null),
     *   error (string|null), expired (bool)
     * }
     */
    public function scanValidate(string $qrHash, int $scannedBy = 0): array
    {
        // ── Step A: Load voucher by hash ─────────────────────────────────
        $stmt = $this->db->prepare(
            "SELECT * FROM vouchers WHERE qr_code_hash = ?"
        );
        $stmt->execute([trim($qrHash)]);
        $voucher = $stmt->fetch();

        if (!$voucher) {
            return [
                'valid' => false,
                'voucher' => null,
                'expired' => false,
                'error' => 'INVALID_QR: This QR code was not found in the system. It may be forged or corrupted.',
            ];
        }

        // ── Hash integrity double-check ───────────────────────────────────
        $expectedHash = $this->buildQrHash((int) $voucher['id'], $voucher['voucher_code']);
        if (!hash_equals($expectedHash, $qrHash)) {
            return [
                'valid' => false,
                'voucher' => null,
                'expired' => false,
                'error' => 'TAMPERED_QR: Hash mismatch. This QR code has been altered and is invalid.',
            ];
        }

        // ── Already in a terminal state? ─────────────────────────────────
        if (in_array($voucher['status'], ['redeemed', 'cancelled'])) {
            return [
                'valid' => false,
                'voucher' => $voucher,
                'expired' => false,
                'error' => "VOUCHER_{$voucher['status']}: This voucher has already been {$voucher['status']}.",
            ];
        }

        // ── Step B: LAZY EXPIRY CHECK ─────────────────────────────────────
        if ($voucher['status'] === 'active' && strtotime($voucher['expires_at']) < time()) {
            // Trigger expiry right now — no cron needed
            try {
                $recycled = $this->triggerLazyExpiry((int) $voucher['id'], $scannedBy);
            } catch (\Throwable $e) {
                $recycled = 0;
                error_log('[VoucherEngine] Lazy expiry failed for #' . $voucher['id'] . ': ' . $e->getMessage());
            }

            return [
                'valid' => false,
                'voucher' => $voucher,
                'expired' => true,
                'recycled' => $recycled,
                'error' => sprintf(
                    'VOUCHER_EXPIRED: This voucher expired at %s. ' .
                    'The remaining balance of ₱%s has been returned to the vault%s.',
                    date('M d, Y h:i A', strtotime($voucher['expires_at'])),
                    number_format((float) $voucher['remaining_balance'], 2),
                    $voucher['is_refundable'] ? ' (refundable)' : ' (non-refundable — no cash back)'
                ),
            ];
        }

        // ── Already DB-flagged expired ────────────────────────────────────
        if ($voucher['status'] === 'expired') {
            return [
                'valid' => false,
                'voucher' => $voucher,
                'expired' => true,
                'error' => 'VOUCHER_EXPIRED: This voucher has already expired.',
            ];
        }

        // ── Balance exhausted? ────────────────────────────────────────────
        if ((float) $voucher['remaining_balance'] <= 0) {
            // Mark as redeemed
            $this->db->prepare(
                "UPDATE vouchers SET status = 'redeemed', redeemed_at = NOW() WHERE id = ?"
            )->execute([(int) $voucher['id']]);
            return [
                'valid' => false,
                'voucher' => $voucher,
                'expired' => false,
                'error' => 'VOUCHER_EXHAUSTED: This voucher has no remaining balance.',
            ];
        }

        // ── ALL CLEAR ─────────────────────────────────────────────────────
        $minutesLeft = (int) floor((strtotime($voucher['expires_at']) - time()) / 60);
        return [
            'valid' => true,
            'voucher' => $voucher,
            'expired' => false,
            'remaining' => (float) $voucher['remaining_balance'],
            'minutes_left' => $minutesLeft,
            'error' => null,
            'warning' => $minutesLeft < 30
                ? "⚠ This voucher expires in {$minutesLeft} minutes."
                : null,
        ];
    }

    // ═══════════════════════════════════════════════════════
    //  3. VOUCHER PAY (Voucher → Merchant Wallet)
    // ═══════════════════════════════════════════════════════

    /**
     * Process a payment from a visitor voucher to a merchant.
     *
     * Flow: Voucher.remaining_balance ──amount──► merchant_wallets.balance
     *
     * ALWAYS call scanValidate() first. This method assumes the voucher
     * is already confirmed valid by the middleware.
     *
     * @param string $qrHash          Hash from the scanned QR
     * @param int    $merchantWalletId Target merchant wallet
     * @param float  $amount           Amount to deduct
     * @param int    $scannedBy        Merchant user ID
     */
    public function voucherPay(
        string $qrHash,
        int $merchantWalletId,
        float $amount,
        int $scannedBy
    ): array {
        if ($amount <= 0) {
            throw new InvalidArgumentException('Payment amount must be greater than zero.');
        }

        // ── Always validate before paying ────────────────────────────────
        $validation = $this->scanValidate($qrHash, $scannedBy);
        if (!$validation['valid']) {
            return array_merge($validation, ['success' => false]);
        }

        $voucher = $validation['voucher'];

        if ((float) $voucher['remaining_balance'] < $amount) {
            return [
                'success' => false,
                'valid' => false,
                'error' => sprintf(
                    'INSUFFICIENT_VOUCHER_BALANCE: Voucher only has ₱%s but payment requires ₱%s.',
                    number_format((float) $voucher['remaining_balance'], 2),
                    number_format($amount, 2)
                ),
            ];
        }

        $this->db->beginTransaction();
        try {
            $settings = $this->db->query(
                "SELECT * FROM system_settings WHERE id = 1 FOR UPDATE"
            )->fetch();

            // Lock voucher row
            $vStmt = $this->db->prepare(
                "SELECT * FROM vouchers WHERE id = ? AND status = 'active' FOR UPDATE"
            );
            $vStmt->execute([$voucher['id']]);
            $freshVoucher = $vStmt->fetch();
            if (!$freshVoucher || (float) $freshVoucher['remaining_balance'] < $amount) {
                throw new RuntimeException('RACE_CONDITION: Voucher state changed. Please retry.');
            }

            $balBefore = (float) $freshVoucher['remaining_balance'];
            $balAfter = $balBefore - $amount;

            // ── Deduct from voucher ──────────────────────────────────────
            $this->db->prepare(
                "UPDATE vouchers
                    SET remaining_balance = remaining_balance - ?,
                        last_used_at      = NOW(),
                        use_count         = use_count + 1,
                        status            = IF(remaining_balance - ? <= 0, 'redeemed', status),
                        redeemed_at       = IF(remaining_balance - ? <= 0, NOW(), redeemed_at)
                  WHERE id = ?"
            )->execute([$amount, $amount, $amount, $voucher['id']]);

            // ── Credit merchant wallet ───────────────────────────────────
            $mStmt = $this->db->prepare(
                "SELECT * FROM merchant_wallets WHERE id = ? FOR UPDATE"
            );
            $mStmt->execute([$merchantWalletId]);
            $mWallet = $mStmt->fetch();
            if (!$mWallet) {
                throw new RuntimeException("MERCHANT_WALLET_NOT_FOUND: ID #{$merchantWalletId}");
            }

            $this->db->prepare(
                "UPDATE merchant_wallets SET balance = balance + ? WHERE id = ?"
            )->execute([$amount, $merchantWalletId]);

            // ── Voucher payment log ──────────────────────────────────────
            $ref = 'VPY-' . strtoupper(date('Ymd')) . '-' . str_pad(
                (string) random_int(1, 99999),
                5,
                '0',
                STR_PAD_LEFT
            );

            $this->db->prepare(
                "INSERT INTO voucher_payment_log
                    (voucher_id, merchant_wallet_id, amount,
                     balance_before, balance_after, scanned_by, transaction_ref)
                 VALUES (?, ?, ?, ?, ?, ?, ?)"
            )->execute([
                        $voucher['id'],
                        $merchantWalletId,
                        $amount,
                        $balBefore,
                        $balAfter,
                        $scannedBy,
                        $ref,
                    ]);

            // ── Integrity check ──────────────────────────────────────────
            // Note: vault does NOT change during voucher payment.
            // Points move from voucher pool → merchant pool. Vault stays same.
            $this->validateCirculation((float) $settings['total_circulation_cap']);

            $this->db->commit();

            return [
                'success' => true,
                'reference' => $ref,
                'amount_paid' => $amount,
                'voucher_code' => $freshVoucher['voucher_code'],
                'visitor_name' => $freshVoucher['visitor_name'],
                'balance_before' => $balBefore,
                'balance_after' => $balAfter,
                'voucher_exhausted' => $balAfter <= 0,
                'minutes_remaining' => $validation['minutes_left'],
            ];

        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    //  4. LAZY EXPIRY TRIGGER (called inside scanValidate)

    /**
     * Called immediately when an expired voucher is scanned.
     * Returns remaining balance to vault (non-refundable → vault; refundable → vault too, but flagged).
     */
    private function triggerLazyExpiry(int $voucherId, int $triggeredBy): float
    {
        $this->db->beginTransaction();
        try {
            $vStmt = $this->db->prepare(
                "SELECT * FROM vouchers WHERE id = ? FOR UPDATE"
            );
            $vStmt->execute([$voucherId]);
            $voucher = $vStmt->fetch();

            if (!$voucher || $voucher['status'] === 'expired') {
                $this->db->rollBack();
                return 0.0;
            }

            $recycled = (float) $voucher['remaining_balance'];

            // Mark expired (DB trigger will also add to vault as safety net)
            $this->db->prepare(
                "UPDATE vouchers
                    SET status      = 'expired',
                        expired_at  = NOW()
                  WHERE id = ?"
            )->execute([$voucherId]);

            // Explicit vault credit (engine is authoritative over trigger)
            if ($recycled > 0) {
                $this->db->prepare(
                    "UPDATE system_settings
                        SET cashier_vault_points = cashier_vault_points + ?
                      WHERE id = 1"
                )->execute([$recycled]);
            }

            // Audit log
            $ref = 'EXP-' . strtoupper(date('Ymd')) . '-' . str_pad(
                (string) $voucherId,
                5,
                '0',
                STR_PAD_LEFT
            );
            $settings = $this->db->query(
                "SELECT * FROM system_settings WHERE id = 1"
            )->fetch();

            $this->db->prepare(
                "INSERT INTO transactions
                    (reference_no, transaction_type, initiated_by, voucher_id,
                     amount, vault_before, vault_after, total_in_circulation, status, notes)
                 VALUES (?, 'voucher_expire', ?, ?, ?, ?, ?,
                    (SELECT cashier_vault_points +
                        COALESCE((SELECT SUM(balance) FROM student_wallets),0)+
                        COALESCE((SELECT SUM(balance) FROM merchant_wallets),0)+
                        COALESCE((SELECT SUM(remaining_balance) FROM vouchers WHERE status='active'),0)
                     FROM system_settings WHERE id=1),
                    'completed', ?)"
            )->execute([
                        $ref,
                        $triggeredBy,
                        $voucherId,
                        $recycled,
                        (float) $settings['cashier_vault_points'] - $recycled,
                        (float) $settings['cashier_vault_points'],
                        "LAZY EXPIRY: Voucher #{$voucherId} ({$voucher['voucher_code']}) expired. " .
                        "Recycled ₱{$recycled} to vault. Non-refundable: " .
                        ($voucher['is_refundable'] ? 'No' : 'Yes'),
                    ]);

            $this->db->commit();
            return $recycled;

        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    //  5. ADMIN: Force-expire a voucher

    public function adminExpireVoucher(int $voucherId, int $adminId): array
    {
        $recycled = $this->triggerLazyExpiry($voucherId, $adminId);
        return [
            'success' => true,
            'recycled' => $recycled,
            'message' => "Voucher #{$voucherId} expired. ₱" . number_format($recycled, 2) . " returned to vault.",
        ];
    }

    //  6. LIST / DASHBOARD DATA


    /** Paginated list for admin dashboard */
    public function listVouchers(string $status = 'all', int $limit = 25, int $offset = 0): array
    {
        $where = $status === 'all' ? '' : "WHERE status = " . $this->db->quote($status);

        $stmt = $this->db->prepare(
            "SELECT * FROM v_vouchers_active {$where}
         ORDER BY created_at DESC
         LIMIT ? OFFSET ?"
        );

        $stmt->execute([$limit, $offset]);
        return $stmt->fetchAll();
    }

    /** Vouchers expiring in the next N minutes */
    public function expiringSoon(int $minutes = 60): array
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM vouchers
              WHERE status = 'active'
                AND expires_at BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL ? MINUTE)
              ORDER BY expires_at ASC"
        );
        $stmt->execute([$minutes]);
        return $stmt->fetchAll();
    }

    /** Payment history for a single voucher */
    public function getVoucherPayments(int $voucherId): array
    {
        $stmt = $this->db->prepare(
            "SELECT p.*, mw.user_id AS merchant_user_id
               FROM voucher_payment_log p
               LEFT JOIN merchant_wallets mw ON mw.id = p.merchant_wallet_id
              WHERE p.voucher_id = ?
              ORDER BY p.created_at ASC"
        );
        $stmt->execute([$voucherId]);
        return $stmt->fetchAll();
    }

    /** Summary stats for admin dashboard */
    public function getSummaryStats(): array
    {
        return $this->db->query(
            "SELECT
                COUNT(*)                                                AS total_all_time,
                SUM(status = 'active')                                  AS active_count,
                SUM(status = 'redeemed')                                AS redeemed_count,
                SUM(status = 'expired')                                 AS expired_count,
                COALESCE(SUM(CASE WHEN status='active'
                    THEN remaining_balance END), 0)                     AS active_pool_value,
                COALESCE(SUM(initial_value), 0)                        AS total_ever_issued,
                COALESCE(SUM(initial_value - remaining_balance), 0)    AS total_ever_spent
             FROM vouchers"
        )->fetch();
    }

    // ═══════════════════════════════════════════════════════
    //  PRIVATE HELPERS
    // ═══════════════════════════════════════════════════════

    private function buildQrHash(int $voucherId, string $voucherCode): string
    {
        return hash('sha256', self::QR_PEPPER . '|' . $voucherId . '|' . $voucherCode);
    }

    private function generateCode(): string
    {
        return 'VCH-' . strtoupper(bin2hex(random_bytes(4)));
    }

    private function validateCirculation(float $expectedCap): void
    {
        $total = (float) $this->db->query(
            "SELECT
                (SELECT cashier_vault_points FROM system_settings WHERE id = 1)
                + COALESCE((SELECT SUM(balance) FROM student_wallets), 0)
                + COALESCE((SELECT SUM(balance) FROM merchant_wallets), 0)
                + COALESCE((SELECT SUM(remaining_balance) FROM vouchers WHERE status = 'active'), 0)"
        )->fetchColumn();

        $drift = abs($total - $expectedCap);
        if ($drift > 0.01) {
            throw new RuntimeException(sprintf(
                "CIRCULATION_INTEGRITY_FAILURE: Cap ₱%s vs actual ₱%s (drift ₱%s). Transaction aborted.",
                number_format($expectedCap, 2),
                number_format($total, 2),
                number_format($drift, 2)
            ));
        }
    }
}
