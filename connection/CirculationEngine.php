<?php


require_once __DIR__ . '/pdo.php';
require_once __DIR__ . '/audit_logger.php';

class CirculationEngine
{
    private PDO $db;

    const TXN_CASH_IN         = 'cash_in';
    const TXN_PAYMENT         = 'payment';
    const TXN_VOUCHER_PAYMENT = 'voucher_payment';
    const TXN_MERCHANT_SETTLE = 'merchant_settle';
    const TXN_VOUCHER_CREATE  = 'voucher_create';
    const TXN_VOUCHER_EXPIRE  = 'voucher_expire';
    const TXN_CAP_INCREASE    = 'cap_increase';

    // Service-fee rates per top-up source
    const FEE_SYSTEM_RATE   = 0.02;   // 2 % → stays in vault (both routes)
    const FEE_MERCHANT_RATE = 0.01;   // 1 % → merchant wallet (merchant route only)

    public function __construct(PDO $pdo)
    {
        $this->db = $pdo;
    }
    
    
    public function cashIn(int $studentWalletId, float $amount, int $initiatedBy): array
    {
        $this->assertPositive($amount);

        $this->db->beginTransaction();
        try {
            
            $settings = $this->lockSettings();

            if ($settings['cashier_vault_points'] < $amount) {
                throw new RuntimeException(
                    "VAULT_INSUFFICIENT: Vault only has Php " .
                    number_format($settings['cashier_vault_points'], 2) .
                    " - cannot load Php " . number_format($amount, 2) .
                    ". Request a vault replenishment from the Super-Admin."
                );
            }

            
            $this->db->prepare(
                "UPDATE system_settings
                    SET cashier_vault_points = cashier_vault_points - ?
                  WHERE id = 1"
            )->execute([$amount]);

            
            $this->db->prepare(
                "UPDATE student_wallets
                    SET balance = balance + ?
                  WHERE id = ?"
            )->execute([$amount, $studentWalletId]);

            $vaultAfter = $settings['cashier_vault_points'] - $amount;

            
            $this->validateCirculation($settings['total_circulation_cap']);

            $ref = $this->logTransaction(
                self::TXN_CASH_IN, $initiatedBy, $amount,
                $settings['cashier_vault_points'], $vaultAfter,
                studentWalletId: $studentWalletId
            );

            $this->db->commit();
            return ['success' => true, 'reference' => $ref, 'vault_after' => $vaultAfter];

        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * Top-up with tiered service fee.
     *
     * source = 'finance'  → 2 % system fee, 0 % merchant fee
     * source = 'merchant' → 2 % system fee, 1 % merchant fee (credited to merchant wallet)
     *
     * Circulation stays balanced: vault drains by (credited + merchantFee);
     * systemFee remains in vault as accumulated revenue.
     */
    public function cashInWithFee(
        int    $studentWalletId,
        float  $cashAmount,
        string $source,
        int    $initiatedBy,
        int    $merchantWalletId = 0,
        string $notes            = ''
    ): array {
        $this->assertPositive($cashAmount);

        if (!in_array($source, ['finance', 'merchant'], true)) {
            throw new \InvalidArgumentException("Invalid top-up source: {$source}");
        }

        $systemFee   = round($cashAmount * self::FEE_SYSTEM_RATE,   2);
        $merchantFee = ($source === 'merchant')
                     ? round($cashAmount * self::FEE_MERCHANT_RATE, 2)
                     : 0.0;
        // Assign any rounding remainder to credited amount
        $credited    = round($cashAmount - $systemFee - $merchantFee, 2);
        // Vault pays out: student credit + merchant cut
        $vaultDrain  = $credited + $merchantFee;

        $this->db->beginTransaction();
        try {
            $settings = $this->lockSettings();

            if ($settings['cashier_vault_points'] < $vaultDrain) {
                throw new \RuntimeException(
                    "VAULT_INSUFFICIENT: Vault has ₱" .
                    number_format($settings['cashier_vault_points'], 2) .
                    " — cannot load ₱" . number_format($cashAmount, 2) .
                    " (needs ₱" . number_format($vaultDrain, 2) . " after fees)."
                );
            }

            // 1. Vault drains by (credited + merchantFee); systemFee stays in vault
            $this->db->prepare(
                "UPDATE system_settings
                    SET cashier_vault_points = cashier_vault_points - ?
                  WHERE id = 1"
            )->execute([$vaultDrain]);

            // 2. Credit student wallet
            $this->db->prepare(
                "UPDATE student_wallets SET balance = balance + ? WHERE id = ?"
            )->execute([$credited, $studentWalletId]);

            // 3. Credit merchant wallet (1 % cut on merchant route)
            if ($merchantFee > 0 && $merchantWalletId > 0) {
                $this->db->prepare(
                    "UPDATE merchant_wallets SET balance = balance + ? WHERE id = ?"
                )->execute([$merchantFee, $merchantWalletId]);
            }

            $vaultAfter = $settings['cashier_vault_points'] - $vaultDrain;

            $this->validateCirculation($settings['total_circulation_cap']);

            $feeNote = "Top-up via {$source}. Cash: ₱{$cashAmount}, "
                     . "System fee (2%): ₱{$systemFee}"
                     . ($merchantFee > 0 ? ", Merchant fee (1%): ₱{$merchantFee}" : "")
                     . ", Credited: ₱{$credited}."
                     . ($notes !== '' ? " Note: {$notes}" : '');

            $ref = $this->logTransaction(
                self::TXN_CASH_IN,
                $initiatedBy,
                $credited,
                $settings['cashier_vault_points'],
                $vaultAfter,
                studentWalletId:  $studentWalletId,
                merchantWalletId: $merchantFee > 0 ? $merchantWalletId : null,
                notes:            $feeNote,
                topUpSource:      $source,
                baseAmount:       $cashAmount,
                feeAmount:        $systemFee + $merchantFee,
                creditedAmount:   $credited
            );

            $this->logFeeRevenue(
                $ref, $source, $cashAmount,
                $systemFee, $merchantFee,
                $merchantFee > 0 ? $merchantWalletId : null,
                $initiatedBy
            );

            $this->db->commit();
            return [
                'success'         => true,
                'reference'       => $ref,
                'vault_after'     => $vaultAfter,
                'cash_amount'     => $cashAmount,
                'system_fee'      => $systemFee,
                'merchant_fee'    => $merchantFee,
                'fee_amount'      => $systemFee + $merchantFee,
                'credited_amount' => $credited,
            ];

        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }


    public function studentPay(
        int $studentWalletId,
        int $merchantWalletId,
        float $amount,
        int $initiatedBy
    ): array {
        $this->assertPositive($amount);

        $this->db->beginTransaction();
        try {
            $settings = $this->lockSettings();

            
            $student = $this->db->prepare(
                "SELECT balance FROM student_wallets WHERE id = ? FOR UPDATE"
            );
            $student->execute([$studentWalletId]);
            $studentRow = $student->fetch();

            if (!$studentRow || $studentRow['balance'] < $amount) {
                throw new RuntimeException(
                    "STUDENT_INSUFFICIENT_BALANCE: Student balance Php " .
                    number_format($studentRow['balance'] ?? 0, 2) .
                    " is below the required Php " . number_format($amount, 2) . "."
                );
            }

            
            $this->db->prepare(
                "UPDATE student_wallets SET balance = balance - ? WHERE id = ?"
            )->execute([$amount, $studentWalletId]);

            
            $this->db->prepare(
                "UPDATE merchant_wallets SET balance = balance + ? WHERE id = ?"
            )->execute([$amount, $merchantWalletId]);

            
            $this->validateCirculation($settings['total_circulation_cap']);

            $ref = $this->logTransaction(
                self::TXN_PAYMENT, $initiatedBy, $amount,
                $settings['cashier_vault_points'], $settings['cashier_vault_points'],
                studentWalletId: $studentWalletId,
                merchantWalletId: $merchantWalletId
            );

            $this->db->commit();
            return ['success' => true, 'reference' => $ref];

        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    
    /**
     * Merchant sends GenCoins from their own wallet to a student.
     * Deducts cashAmount from merchant, credits credited (97%) to student,
     * returns merchantFee (1%) to merchant, and adds systemFee (2%) to vault.
     */
    public function merchantSendToStudent(
        int    $merchantWalletId,
        int    $studentWalletId,
        float  $cashAmount,
        int    $initiatedBy,
        string $notes = ''
    ): array {
        $this->assertPositive($cashAmount);

        $systemFee   = round($cashAmount * self::FEE_SYSTEM_RATE,   2);
        $merchantFee = round($cashAmount * self::FEE_MERCHANT_RATE, 2);
        $credited    = round($cashAmount - $systemFee - $merchantFee, 2);

        $this->db->beginTransaction();
        try {
            $settings = $this->lockSettings();

            // Lock and verify merchant balance
            $mStmt = $this->db->prepare(
                "SELECT balance FROM merchant_wallets WHERE id = ? FOR UPDATE"
            );
            $mStmt->execute([$merchantWalletId]);
            $mRow = $mStmt->fetch();

            if (!$mRow || (float)$mRow['balance'] < $cashAmount) {
                throw new \RuntimeException(sprintf(
                    "MERCHANT_INSUFFICIENT_BALANCE: Wallet has ₱%s — cannot send ₱%s.",
                    number_format((float)($mRow['balance'] ?? 0), 2),
                    number_format($cashAmount, 2)
                ));
            }

            // 1. Debit merchant wallet by full amount
            $this->db->prepare(
                "UPDATE merchant_wallets SET balance = balance - ? WHERE id = ?"
            )->execute([$cashAmount, $merchantWalletId]);

            // 2. Credit student wallet
            $this->db->prepare(
                "UPDATE student_wallets SET balance = balance + ? WHERE id = ?"
            )->execute([$credited, $studentWalletId]);

            // 3. Return 1% cut to merchant
            $this->db->prepare(
                "UPDATE merchant_wallets SET balance = balance + ? WHERE id = ?"
            )->execute([$merchantFee, $merchantWalletId]);

            // 4. System fee (2%) goes to vault as revenue
            $this->db->prepare(
                "UPDATE system_settings SET cashier_vault_points = cashier_vault_points + ? WHERE id = 1"
            )->execute([$systemFee]);

            $vaultAfter = $settings['cashier_vault_points'] + $systemFee;

            $this->validateCirculation($settings['total_circulation_cap']);

            $feeNote = "Merchant send to student. Sent: ₱{$cashAmount}, "
                     . "System fee (2%): ₱{$systemFee}, "
                     . "Merchant cut (1%): ₱{$merchantFee}, "
                     . "Credited: ₱{$credited}."
                     . ($notes !== '' ? " Note: {$notes}" : '');

            $ref = $this->logTransaction(
                self::TXN_CASH_IN,
                $initiatedBy,
                $credited,
                $settings['cashier_vault_points'],
                $vaultAfter,
                studentWalletId:  $studentWalletId,
                merchantWalletId: $merchantWalletId,
                notes:            $feeNote,
                topUpSource:      'merchant',
                baseAmount:       $cashAmount,
                feeAmount:        $systemFee + $merchantFee,
                creditedAmount:   $credited
            );

            $this->logFeeRevenue(
                $ref, 'merchant', $cashAmount,
                $systemFee, $merchantFee,
                $merchantWalletId,
                $initiatedBy
            );

            $this->db->commit();
            return [
                'success'         => true,
                'reference'       => $ref,
                'cash_amount'     => $cashAmount,
                'system_fee'      => $systemFee,
                'merchant_fee'    => $merchantFee,
                'fee_amount'      => $systemFee + $merchantFee,
                'credited_amount' => $credited,
            ];

        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function merchantSettle(int $merchantWalletId, float $amount, int $initiatedBy): array
    {
        $this->assertPositive($amount);

        $this->db->beginTransaction();
        try {
            $settings = $this->lockSettings();

            $merchant = $this->db->prepare(
                "SELECT balance FROM merchant_wallets WHERE id = ? FOR UPDATE"
            );
            $merchant->execute([$merchantWalletId]);
            $merchantRow = $merchant->fetch();

            if (!$merchantRow || $merchantRow['balance'] < $amount) {
                throw new RuntimeException(
                    "MERCHANT_INSUFFICIENT_BALANCE: Cannot settle Php " .
                    number_format($amount, 2) . "."
                );
            }

            
            $this->db->prepare(
                "UPDATE merchant_wallets SET balance = balance - ? WHERE id = ?"
            )->execute([$amount, $merchantWalletId]);

            
            $this->db->prepare(
                "UPDATE system_settings
                    SET cashier_vault_points = cashier_vault_points + ?
                  WHERE id = 1"
            )->execute([$amount]);

            $vaultAfter = $settings['cashier_vault_points'] + $amount;

            $this->validateCirculation($settings['total_circulation_cap']);

            $ref = $this->logTransaction(
                self::TXN_MERCHANT_SETTLE, $initiatedBy, $amount,
                $settings['cashier_vault_points'], $vaultAfter,
                merchantWalletId: $merchantWalletId
            );

            $this->db->commit();
            return ['success' => true, 'reference' => $ref, 'vault_after' => $vaultAfter];

        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    
    public function createVoucher(
        float $amount,
        string $visitorName,
        string $visitorContact,
        int $expiryHours,
        int $issuedBy
    ): array {
        $this->assertPositive($amount);

        $this->db->beginTransaction();
        try {
            $settings = $this->lockSettings();

            if ($settings['cashier_vault_points'] < $amount) {
                throw new RuntimeException(
                    "VAULT_INSUFFICIENT: Cannot issue voucher. Vault has Php " .
                    number_format($settings['cashier_vault_points'], 2) . "."
                );
            }

            $code      = $this->generateVoucherCode();
            $expiresAt = date('Y-m-d H:i:s', strtotime("+{$expiryHours} hours"));

            
            $this->db->prepare(
                "UPDATE system_settings
                    SET cashier_vault_points = cashier_vault_points - ?
                  WHERE id = 1"
            )->execute([$amount]);

            
            $this->db->prepare(
                "INSERT INTO vouchers
                    (voucher_code, issued_by, visitor_name, visitor_contact,
                     original_amount, remaining_balance, expires_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?)"
            )->execute([$code, $issuedBy, $visitorName, $visitorContact,
                        $amount, $amount, $expiresAt]);

            $voucherId  = (int) $this->db->lastInsertId();
            $vaultAfter = $settings['cashier_vault_points'] - $amount;

            $this->validateCirculation($settings['total_circulation_cap']);

            $ref = $this->logTransaction(
                self::TXN_VOUCHER_CREATE, $issuedBy, $amount,
                $settings['cashier_vault_points'], $vaultAfter,
                voucherId: $voucherId
            );

            $this->db->commit();
            return [
                'success'      => true,
                'voucher_code' => $code,
                'voucher_id'   => $voucherId,
                'expires_at'   => $expiresAt,
                'reference'    => $ref,
                'non_refundable_notice' =>
                    'This voucher is NON-REFUNDABLE. Any unspent balance cannot be converted to cash.',
            ];

        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    
    public function voucherPay(
        string $voucherCode,
        int $merchantWalletId,
        float $amount,
        int $initiatedBy
    ): array {
        $this->assertPositive($amount);

        $this->db->beginTransaction();
        try {
            $settings = $this->lockSettings();

            
            $vStmt = $this->db->prepare(
                "SELECT * FROM vouchers WHERE voucher_code = ? AND status = 'active' FOR UPDATE"
            );
            $vStmt->execute([$voucherCode]);
            $voucher = $vStmt->fetch();

            if (!$voucher) {
                throw new RuntimeException(
                    "INVALID_VOUCHER: Code not found or already used/expired."
                );
            }
            if (new DateTime() > new DateTime($voucher['expires_at'])) {
                throw new RuntimeException(
                    "VOUCHER_EXPIRED: This voucher expired at {$voucher['expires_at']}."
                );
            }
            if ($voucher['remaining_balance'] < $amount) {
                throw new RuntimeException(
                    "VOUCHER_INSUFFICIENT: Voucher has Php " .
                    number_format($voucher['remaining_balance'], 2) .
                    " remaining - cannot pay Php " . number_format($amount, 2) . "." .
                    " Note: change cannot be given as cash (non-refundable)."
                );
            }

            $newBalance = $voucher['remaining_balance'] - $amount;
            $newStatus  = ($newBalance == 0) ? 'used' : 'active';

            
            $this->db->prepare(
                "UPDATE vouchers
                    SET remaining_balance = ?,
                        status = ?
                  WHERE id = ?"
            )->execute([$newBalance, $newStatus, $voucher['id']]);

            
            $this->db->prepare(
                "UPDATE merchant_wallets SET balance = balance + ? WHERE id = ?"
            )->execute([$amount, $merchantWalletId]);

            $this->validateCirculation($settings['total_circulation_cap']);

            $ref = $this->logTransaction(
                self::TXN_VOUCHER_PAYMENT, $initiatedBy, $amount,
                $settings['cashier_vault_points'], $settings['cashier_vault_points'],
                merchantWalletId: $merchantWalletId,
                voucherId: $voucher['id']
            );

            $this->db->commit();

            $notice = ($newBalance > 0)
                ? "Php " . number_format($newBalance, 2) . " remains on voucher. " .
                  "Change CANNOT be given as cash - it stays on the voucher (non-refundable)."
                : "Voucher fully consumed.";

            return [
                'success'           => true,
                'reference'         => $ref,
                'remaining_balance' => $newBalance,
                'voucher_notice'    => $notice,
            ];

        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    
    public function increaseCirculationCap(
        float $increaseBy,
        int $superAdminId,
        string $reason
    ): array {
        $this->assertPositive($increaseBy);

        if (trim($reason) === '') {
            throw new InvalidArgumentException(
                "A mandatory reason/justification is required to increase the money supply."
            );
        }

        $this->db->beginTransaction();
        try {
            $settings = $this->lockSettings();
            $oldCap   = $settings['total_circulation_cap'];
            $newCap   = $oldCap + $increaseBy;

            
            $this->db->prepare(
                "UPDATE system_settings
                    SET total_circulation_cap   = ?,
                        cashier_vault_points    = cashier_vault_points + ?,
                        last_cap_increased_by   = ?,
                        last_cap_increased_at   = NOW()
                  WHERE id = 1"
            )->execute([$newCap, $increaseBy, $superAdminId]);

            
            $this->db->prepare(
                "INSERT INTO cap_increase_log
                    (super_admin_id, old_cap, new_cap, amount_added, reason)
                 VALUES (?, ?, ?, ?, ?)"
            )->execute([$superAdminId, $oldCap, $newCap, $increaseBy, $reason]);

            $vaultAfter = $settings['cashier_vault_points'] + $increaseBy;

            $this->validateCirculation($newCap);

            $ref = $this->logTransaction(
                self::TXN_CAP_INCREASE, $superAdminId, $increaseBy,
                $settings['cashier_vault_points'], $vaultAfter,
                notes: "Cap raised from Php {$oldCap} to Php {$newCap}. Reason: {$reason}"
            );

            $this->db->commit();
            return [
                'success'     => true,
                'old_cap'     => $oldCap,
                'new_cap'     => $newCap,
                'vault_after' => $vaultAfter,
                'reference'   => $ref,
            ];

        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    
    public function expireVoucher(int $voucherId, int $initiatedBy): array
    {
        $this->db->beginTransaction();
        try {
            $settings = $this->lockSettings();
            $vaultBefore = (float) $settings['cashier_vault_points'];

            $vStmt = $this->db->prepare(
                "SELECT * FROM vouchers WHERE id = ? AND status = 'active' FOR UPDATE"
            );
            $vStmt->execute([$voucherId]);
            $voucher = $vStmt->fetch();

            if (!$voucher) {
                throw new RuntimeException("VOUCHER_NOT_FOUND or already inactive.");
            }

            $recycled = $voucher['remaining_balance'];

            
            
            $this->db->prepare(
                "UPDATE vouchers SET status = 'expired' WHERE id = ?"
            )->execute([$voucherId]);

            $vaultAfterExpire = $this->getVaultBalance();
            $triggerRecycled = abs($vaultAfterExpire - ($vaultBefore + (float) $recycled)) <= 0.01;

            if ((float) $recycled > 0 && !$triggerRecycled) {
                $this->db->prepare(
                    "UPDATE system_settings
                        SET cashier_vault_points = cashier_vault_points + ?
                      WHERE id = 1"
                )->execute([$recycled]);
            }

            $vaultAfter = $this->getVaultBalance();

            $this->validateCirculation($settings['total_circulation_cap']);

            $ref = $this->logTransaction(
                self::TXN_VOUCHER_EXPIRE, $initiatedBy, max($recycled, 0.01),
                $vaultBefore, $vaultAfter,
                voucherId: $voucherId,
                notes: "Recycled Php {$recycled} from expired voucher #{$voucherId}"
            );

            $this->db->commit();
            return ['success' => true, 'recycled' => $recycled, 'reference' => $ref];

        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }
    
    
    public function getCirculationSnapshot(): array
    {
        // Circulation health is computed in PHP (buildCirculationSnapshot),
        // so the app has no dependency on any database view.
        return $this->buildCirculationSnapshot();
    }
    
    
    private function validateCirculation(float $expectedCap): void
    {
        $row = $this->db->query(
            "SELECT
                (SELECT cashier_vault_points FROM system_settings WHERE id = 1)
                + COALESCE((SELECT SUM(balance) FROM student_wallets), 0)
                + COALESCE((SELECT SUM(balance) FROM merchant_wallets), 0)
                + COALESCE((SELECT SUM(remaining_balance)
                            FROM vouchers WHERE status = 'active'), 0)
             AS total_in_circulation"
        )->fetchColumn();

        $total = (float) $row;
        $drift = abs($total - $expectedCap);

        
        if ($drift > 0.01) {
            throw new RuntimeException(sprintf(
                "CIRCULATION_INTEGRITY_FAILURE: Expected cap Php %s but total " .
                "in circulation is Php %s (drift Php %s). Transaction aborted.",
                number_format($expectedCap, 2),
                number_format($total, 2),
                number_format($drift, 2)
            ));
        }
    }

    
    private function lockSettings(): array
    {
        $stmt = $this->db->query(
            "SELECT * FROM system_settings WHERE id = 1 FOR UPDATE"
        );
        $row = $stmt->fetch();
        if (!$row) {
            throw new RuntimeException("SYSTEM_SETTINGS_MISSING: Cannot find singleton row.");
        }
        return $row;
    }

    private function getVaultBalance(): float
    {
        return (float) $this->db
            ->query("SELECT cashier_vault_points FROM system_settings WHERE id = 1")
            ->fetchColumn();
    }

    private function buildCirculationSnapshot(): array
    {
        $row = $this->db->query(
            "SELECT
                ss.total_circulation_cap AS cap,
                ss.cashier_vault_points AS vault,
                COALESCE((SELECT SUM(balance) FROM student_wallets), 0) AS student_wallets_total,
                COALESCE((SELECT SUM(balance) FROM merchant_wallets), 0) AS merchant_wallets_total,
                COALESCE((SELECT SUM(remaining_balance) FROM vouchers WHERE status = 'active'), 0) AS active_vouchers_total,
                (
                    ss.cashier_vault_points
                    + COALESCE((SELECT SUM(balance) FROM student_wallets), 0)
                    + COALESCE((SELECT SUM(balance) FROM merchant_wallets), 0)
                    + COALESCE((SELECT SUM(remaining_balance) FROM vouchers WHERE status = 'active'), 0)
                ) AS total_in_circulation,
                (
                    ss.total_circulation_cap
                    - ss.cashier_vault_points
                    - COALESCE((SELECT SUM(balance) FROM student_wallets), 0)
                    - COALESCE((SELECT SUM(balance) FROM merchant_wallets), 0)
                    - COALESCE((SELECT SUM(remaining_balance) FROM vouchers WHERE status = 'active'), 0)
                ) AS circulation_drift,
                ss.updated_at AS as_of
             FROM system_settings ss
             WHERE ss.id = 1"
        )->fetch();

        return $row ?: [];
    }

    
    private function logTransaction(
        string  $type,
        int     $initiatedBy,
        float   $amount,
        float   $vaultBefore,
        float   $vaultAfter,
        int     $studentWalletId  = null,
        int     $merchantWalletId = null,
        int     $voucherId        = null,
        string  $notes            = null,
        // Fee-related optional fields (populated only for cashInWithFee calls)
        string  $topUpSource      = null,
        float   $baseAmount       = null,
        float   $feeAmount        = null,
        float   $creditedAmount   = null
    ): string {

        $total = (float) $this->db->query(
            "SELECT
                (SELECT cashier_vault_points FROM system_settings WHERE id = 1)
                + COALESCE((SELECT SUM(balance) FROM student_wallets), 0)
                + COALESCE((SELECT SUM(balance) FROM merchant_wallets), 0)
                + COALESCE((SELECT SUM(remaining_balance)
                            FROM vouchers WHERE status = 'active'), 0)"
        )->fetchColumn();

        $ref = 'TXN-' . strtoupper(date('Ymd')) . '-' . str_pad(
            (string) random_int(1, 99999), 5, '0', STR_PAD_LEFT
        );

        $this->db->prepare(
            "INSERT INTO transactions
                (reference_no, transaction_type, initiated_by, student_wallet_id,
                 merchant_wallet_id, voucher_id, amount, vault_before, vault_after,
                 total_in_circulation, status, notes)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'completed', ?)"
        )->execute([
            $ref, $type, $initiatedBy, $studentWalletId,
            $merchantWalletId, $voucherId, $amount,
            $vaultBefore, $vaultAfter, $total, $notes,
        ]);

        // Persist fee fields if the migration has already added the columns
        if ($topUpSource !== null) {
            try {
                $txnId = (int) $this->db->lastInsertId();
                $this->db->prepare(
                    "UPDATE transactions
                        SET top_up_source   = ?,
                            base_amount     = ?,
                            fee_amount      = ?,
                            credited_amount = ?
                      WHERE id = ?"
                )->execute([$topUpSource, $baseAmount, $feeAmount, $creditedAmount, $txnId]);
            } catch (\Throwable $ignored) {
                // Migration hasn't run yet — non-fatal, notes column already has the info
            }
        }

        logAudit(
            $this->db,
            $initiatedBy,
            gjc_audit_role_from_user($this->db, $initiatedBy),
            'TRANSACTION',
            'e_wallet_transactions',
            null,
            [
                'reference_no' => $ref,
                'transaction_type' => $type,
                'amount' => $amount,
                'student_wallet_id' => $studentWalletId,
                'merchant_wallet_id' => $merchantWalletId,
                'voucher_id' => $voucherId,
                'vault_before' => $vaultBefore,
                'vault_after' => $vaultAfter,
                'total_in_circulation' => $total,
                'status' => 'completed',
            ]
        );

        return $ref;
    }

    private function logFeeRevenue(
        string $ref,
        string $source,
        float  $cashAmount,
        float  $systemFee,
        float  $merchantFee,
        ?int   $merchantWalletId,
        int    $processedBy
    ): void {
        try {
            $this->db->prepare(
                "INSERT INTO fee_revenue_log
                    (transaction_ref, top_up_source, cash_amount,
                     system_fee, merchant_fee, merchant_wallet_id, processed_by)
                 VALUES (?, ?, ?, ?, ?, ?, ?)"
            )->execute([
                $ref, $source, $cashAmount,
                $systemFee, $merchantFee, $merchantWalletId, $processedBy,
            ]);
        } catch (\Throwable $ignored) {
            // Table hasn't been created yet — will self-heal on next page load
        }
    }

    private function generateVoucherCode(): string
    {
        return 'VCH-' . strtoupper(bin2hex(random_bytes(6)));
    }

    private function assertPositive(float $amount): void
    {
        if ($amount <= 0) {
            throw new InvalidArgumentException("Amount must be greater than zero.");
        }
    }
}

