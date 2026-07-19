<?php


require_once __DIR__ . '/pdo.php';
require_once __DIR__ . '/CirculationEngine.php';
require_once __DIR__ . '/audit_logger.php';

class VoucherEngine
{
    
    private const QR_PEPPER = 'GJC_EDUPAY_VOUCHER_v1';

    
    public const DEFAULT_EXPIRY_HOURS = 24;

    private CirculationEngine $ce;

    public function __construct(private PDO $db)
    {
        $this->ce = new CirculationEngine($db);
    }
    
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
            $settings = $this->db->query(
                "SELECT * FROM system_settings WHERE id = 1 FOR UPDATE"
            )->fetch();

            if ((float) $settings['cashier_vault_points'] < $amount) {
                throw new RuntimeException(sprintf(
                    'VAULT_INSUFFICIENT: Vault only has Php %s but voucher requires Php %s.',
                    number_format((float) $settings['cashier_vault_points'], 2),
                    number_format($amount, 2)
                ));
            }

            $voucherCode = $this->generateCode();
            $expiresAt = date('Y-m-d H:i:s', strtotime("+{$expiryHours} hours"));

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

            $qrHash = $this->buildQrHash($voucherId, $voucherCode);

            $this->db->prepare(
                "UPDATE vouchers SET qr_code_hash = ? WHERE id = ?"
            )->execute([$qrHash, $voucherId]);

            $this->db->prepare(
                "UPDATE system_settings
                    SET cashier_vault_points = cashier_vault_points - ?
                  WHERE id = 1"
            )->execute([$amount]);

            $this->validateCirculation((float) $settings['total_circulation_cap']);

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
                     status, notes, school_year_id)
                 VALUES (?, 'voucher_create', ?, ?, ?, ?, ?,
                    (SELECT cashier_vault_points +
                        COALESCE((SELECT SUM(balance) FROM student_wallets),0) +
                        COALESCE((SELECT SUM(balance) FROM merchant_wallets),0) +
                        COALESCE((SELECT SUM(remaining_balance) FROM vouchers WHERE status='active'),0)
                     FROM system_settings WHERE id=1),
                    'completed', ?, ?)"
            )->execute([
                        $ref,
                        $issuedBy,
                        $voucherId,
                        $amount,
                        (float) $settings['cashier_vault_points'],
                        (float) $settings['cashier_vault_points'] - $amount,
                        "Voucher {$voucherCode} issued to {$visitorName} - exp {$expiresAt}",
                        gjc_active_school_year_id($this->db),
                    ]);

            $this->db->commit();
            logAudit(
                $this->db,
                $issuedBy,
                gjc_audit_role_from_user($this->db, $issuedBy),
                'TRANSACTION',
                'e_wallet_transactions',
                null,
                [
                    'reference_no' => $ref,
                    'transaction_type' => 'voucher_create',
                    'voucher_id' => $voucherId,
                    'amount' => $amount,
                    'vault_before' => (float) $settings['cashier_vault_points'],
                    'vault_after' => (float) $settings['cashier_vault_points'] - $amount,
                    'status' => 'completed',
                ]
            );

            
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
    
    
    public function scanValidate(string $qrHash, int $scannedBy = 0): array
    {
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

        $expectedHash = $this->buildQrHash((int) $voucher['id'], $voucher['voucher_code']);
        if (!hash_equals($expectedHash, $qrHash)) {
            return [
                'valid' => false,
                'voucher' => null,
                'expired' => false,
                'error' => 'TAMPERED_QR: Hash mismatch. This QR code has been altered and is invalid.',
            ];
        }

        if (in_array($voucher['status'], ['redeemed', 'cancelled'])) {
            return [
                'valid' => false,
                'voucher' => $voucher,
                'expired' => false,
                'error' => "VOUCHER_{$voucher['status']}: This voucher has already been {$voucher['status']}.",
            ];
        }

        if ($voucher['status'] === 'active' && strtotime($voucher['expires_at']) < time()) {
            
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
                    'The remaining balance of Php %s has been returned to the vault%s.',
                    date('M d, Y h:i A', strtotime($voucher['expires_at'])),
                    number_format((float) $voucher['remaining_balance'], 2),
                    $voucher['is_refundable'] ? ' (refundable)' : ' (non-refundable - no cash back)'
                ),
            ];
        }

        if ($voucher['status'] === 'expired') {
            return [
                'valid' => false,
                'voucher' => $voucher,
                'expired' => true,
                'error' => 'VOUCHER_EXPIRED: This voucher has already expired.',
            ];
        }

        if ((float) $voucher['remaining_balance'] <= 0) {
            
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

        $minutesLeft = (int) floor((strtotime($voucher['expires_at']) - time()) / 60);
        return [
            'valid' => true,
            'voucher' => $voucher,
            'expired' => false,
            'remaining' => (float) $voucher['remaining_balance'],
            'minutes_left' => $minutesLeft,
            'error' => null,
            'warning' => $minutesLeft < 30
                ? "This voucher expires in {$minutesLeft} minutes."
                : null,
        ];
    }
    
    
    public function voucherPay(
        string $qrHash,
        int $merchantWalletId,
        float $amount,
        int $scannedBy
    ): array {
        if ($amount <= 0) {
            throw new InvalidArgumentException('Payment amount must be greater than zero.');
        }

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
                    'INSUFFICIENT_VOUCHER_BALANCE: Voucher only has Php %s but payment requires Php %s.',
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

            $this->db->prepare(
                "UPDATE vouchers
                    SET remaining_balance = remaining_balance - ?,
                        last_used_at      = NOW(),
                        use_count         = use_count + 1,
                        status            = IF(remaining_balance - ? <= 0, 'redeemed', status),
                        redeemed_at       = IF(remaining_balance - ? <= 0, NOW(), redeemed_at)
                  WHERE id = ?"
            )->execute([$amount, $amount, $amount, $voucher['id']]);

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

            
            
            $this->validateCirculation((float) $settings['total_circulation_cap']);

            $this->db->commit();
            logAudit(
                $this->db,
                $scannedBy,
                gjc_audit_role_from_user($this->db, $scannedBy),
                'TRANSACTION',
                'e_wallet_transactions',
                null,
                [
                    'reference_no' => $ref,
                    'transaction_type' => 'voucher_payment',
                    'voucher_id' => (int) $voucher['id'],
                    'merchant_wallet_id' => $merchantWalletId,
                    'amount' => $amount,
                    'balance_before' => $balBefore,
                    'balance_after' => $balAfter,
                    'status' => 'completed',
                ]
            );

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

    

    
    private function triggerLazyExpiry(int $voucherId, int $triggeredBy): float
    {
        $this->db->beginTransaction();
        try {
            $settings = $this->db->query(
                "SELECT * FROM system_settings WHERE id = 1 FOR UPDATE"
            )->fetch();
            if (!$settings) {
                throw new RuntimeException('SYSTEM_SETTINGS_MISSING: Cannot find singleton row.');
            }
            $vaultBefore = (float) ($settings['cashier_vault_points'] ?? 0);

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

            
            
            $this->db->prepare(
                "UPDATE vouchers
                    SET status      = 'expired',
                        expired_at  = NOW()
                  WHERE id = ?"
            )->execute([$voucherId]);

            $vaultAfterExpire = $this->getVaultBalance();
            $triggerRecycled = abs($vaultAfterExpire - ($vaultBefore + $recycled)) <= 0.01;

            if ($recycled > 0 && !$triggerRecycled) {
                $this->db->prepare(
                    "UPDATE system_settings
                        SET cashier_vault_points = cashier_vault_points + ?
                      WHERE id = 1"
                )->execute([$recycled]);
            }

            $vaultAfter = $this->getVaultBalance();
            $this->validateCirculation((float) $settings['total_circulation_cap']);

            
            $ref = 'EXP-' . strtoupper(date('Ymd')) . '-' . str_pad(
                (string) $voucherId,
                5,
                '0',
                STR_PAD_LEFT
            );

            $this->db->prepare(
                "INSERT INTO transactions
                    (reference_no, transaction_type, initiated_by, voucher_id,
                     amount, vault_before, vault_after, total_in_circulation, status, notes, school_year_id)
                 VALUES (?, 'voucher_expire', ?, ?, ?, ?, ?,
                    (SELECT cashier_vault_points +
                        COALESCE((SELECT SUM(balance) FROM student_wallets),0)+
                        COALESCE((SELECT SUM(balance) FROM merchant_wallets),0)+
                        COALESCE((SELECT SUM(remaining_balance) FROM vouchers WHERE status='active'),0)
                     FROM system_settings WHERE id=1),
                    'completed', ?, ?)"
            )->execute([
                        $ref,
                        $triggeredBy,
                        $voucherId,
                        max($recycled, 0.01),
                        $vaultBefore,
                        $vaultAfter,
                        "LAZY EXPIRY: Voucher #{$voucherId} ({$voucher['voucher_code']}) expired. " .
                        "Recycled Php {$recycled} to vault. Non-refundable: " .
                        ($voucher['is_refundable'] ? 'No' : 'Yes'),
                        gjc_active_school_year_id($this->db),
                    ]);

            $this->db->commit();
            logAudit(
                $this->db,
                $triggeredBy,
                gjc_audit_role_from_user($this->db, $triggeredBy),
                'TRANSACTION',
                'e_wallet_transactions',
                null,
                [
                    'reference_no' => $ref,
                    'transaction_type' => 'voucher_expire',
                    'voucher_id' => $voucherId,
                    'amount' => max($recycled, 0.01),
                    'vault_before' => $vaultBefore,
                    'vault_after' => $vaultAfter,
                    'status' => 'completed',
                ]
            );
            return $recycled;

        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    

    public function adminExpireVoucher(int $voucherId, int $adminId): array
    {
        $recycled = $this->triggerLazyExpiry($voucherId, $adminId);
        return [
            'success' => true,
            'recycled' => $recycled,
            'message' => "Voucher #{$voucherId} expired. Php " . number_format($recycled, 2) . " returned to vault.",
        ];
    }

    


    
    public function listVouchers(string $status = 'all', int $limit = 25, int $offset = 0): array
    {
        $where = $status === 'all' ? '' : "WHERE status = " . $this->db->quote($status);

        // Same result the old `v_vouchers_active` view produced, but computed
        // inline so the app has no dependency on a database view.
        $stmt = $this->db->prepare(
            "SELECT * FROM (
                SELECT
                    v.id, v.voucher_code, v.visitor_name, v.visitor_contact,
                    v.initial_value, v.remaining_balance, v.status, v.is_refundable,
                    v.created_at, v.expires_at,
                    TIMESTAMPDIFF(MINUTE, NOW(), v.expires_at) AS minutes_until_expiry,
                    CASE
                        WHEN v.status <> 'active'        THEN v.status
                        WHEN NOW() > v.expires_at        THEN 'expired_pending'
                        WHEN v.remaining_balance <= 0    THEN 'fully_redeemed'
                        ELSE 'active'
                    END AS computed_status,
                    CONCAT(u.first_name, ' ', u.last_name) AS issued_by_name,
                    v.use_count
                FROM vouchers v
                LEFT JOIN users u ON u.userID = v.issued_by
            ) AS voucher_list
            {$where}
            ORDER BY created_at DESC
            LIMIT ? OFFSET ?"
        );

        $stmt->execute([$limit, $offset]);
        return $stmt->fetchAll();
    }

    
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
    
    private function buildQrHash(int $voucherId, string $voucherCode): string
    {
        return hash('sha256', self::QR_PEPPER . '|' . $voucherId . '|' . $voucherCode);
    }

    private function generateCode(): string
    {
        return 'VCH-' . strtoupper(bin2hex(random_bytes(4)));
    }

    private function getVaultBalance(): float
    {
        return (float) $this->db
            ->query("SELECT cashier_vault_points FROM system_settings WHERE id = 1")
            ->fetchColumn();
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
                "CIRCULATION_INTEGRITY_FAILURE: Cap Php %s vs actual Php %s (drift Php %s). Transaction aborted.",
                number_format($expectedCap, 2),
                number_format($total, 2),
                number_format($drift, 2)
            ));
        }
    }
}

