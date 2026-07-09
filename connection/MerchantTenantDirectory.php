<?php

class MerchantTenantDirectory
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function directoryCards(): array
    {
        if (!gjc_table_exists($this->db, 'merchant')) {
            return [];
        }

        $hasOperationalStatus = in_array('operational_status', gjc_table_columns($this->db, 'merchant'), true);
        $statusSelect = $hasOperationalStatus ? "COALESCE(m.operational_status, 'active')" : "'active'";
        $hasLeases = gjc_table_exists($this->db, 'merchant_leases');
        $hasRentPayments = gjc_table_exists($this->db, 'merchant_rent_payments');
        $leaseSelect = $hasLeases
            ? "ml.id AS lease_id,
                ml.monthly_rent,
                ml.lease_start,
                ml.lease_end,
                ml.next_due_date,
                ml.status AS lease_status"
            : "NULL AS lease_id,
                0 AS monthly_rent,
                NULL AS lease_start,
                NULL AS lease_end,
                NULL AS next_due_date,
                NULL AS lease_status";
        $paidSelect = ($hasLeases && $hasRentPayments)
            ? "COALESCE((
                    SELECT SUM(rp.amount_paid)
                    FROM merchant_rent_payments rp
                    WHERE rp.lease_id = ml.id
                      AND rp.period_covered = DATE_FORMAT(CURDATE(), '%Y-%m')
                ), 0)"
            : "0";
        $leaseJoin = $hasLeases
            ? "LEFT JOIN merchant_leases ml ON ml.id = (
                SELECT l2.id
                FROM merchant_leases l2
                WHERE l2.merchant_user_id = m.userID
                ORDER BY
                    CASE WHEN l2.status = 'active' THEN 0 ELSE 1 END,
                    l2.lease_end DESC,
                    l2.id DESC
                LIMIT 1
            )"
            : "";

        $sql = "
            SELECT
                m.merchantID,
                m.userID AS merchant_user_id,
                m.stall_name,
                {$statusSelect} AS operational_status,
                TRIM(CONCAT(COALESCE(u.first_name, ''), ' ', COALESCE(u.last_name, ''))) AS proprietor_name,
                u.email AS proprietor_email,
                {$leaseSelect},
                {$paidSelect} AS paid_this_month
            FROM merchant m
            LEFT JOIN users u ON u.userID = m.userID
            {$leaseJoin}
            ORDER BY m.stall_name ASC, m.merchantID ASC";

        $rows = $this->db->query($sql)->fetchAll(PDO::FETCH_ASSOC);

        return array_map(function (array $row): array {
            $monthlyRent = (float) ($row['monthly_rent'] ?? 0);
            $paid = (float) ($row['paid_this_month'] ?? 0);

            return [
                'merchant_id' => (int) $row['merchantID'],
                'merchant_user_id' => (int) $row['merchant_user_id'],
                'stall_name' => (string) $row['stall_name'],
                'proprietor_name' => trim((string) $row['proprietor_name']) ?: ((string) ($row['proprietor_email'] ?? 'Unassigned proprietor')),
                'operational_status' => (string) $row['operational_status'],
                'lease_status' => $this->monthLeaseStatus((string) ($row['lease_status'] ?? ''), $monthlyRent, $paid),
                'lease_status_raw' => (string) ($row['lease_status'] ?? 'none'),
                'lease_id' => (int) ($row['lease_id'] ?? 0),
            ];
        }, $rows);
    }

    public function merchantsForPicker(): array
    {
        if (!gjc_table_exists($this->db, 'merchant')) {
            return [];
        }

        $hasLeases = gjc_table_exists($this->db, 'merchant_leases');
        $activeLeaseSelect = $hasLeases
            ? "EXISTS (SELECT 1 FROM merchant_leases l WHERE l.merchant_user_id = m.userID AND l.status = 'active')"
            : "0";

        $sql = "
            SELECT
                m.userID AS merchant_user_id,
                m.stall_name,
                TRIM(CONCAT(COALESCE(u.first_name, ''), ' ', COALESCE(u.last_name, ''))) AS proprietor_name,
                u.email AS proprietor_email,
                {$activeLeaseSelect} AS has_active_lease
            FROM merchant m
            LEFT JOIN users u ON u.userID = m.userID
            ORDER BY m.stall_name ASC";

        $rows = $this->db->query($sql)->fetchAll(PDO::FETCH_ASSOC);

        return array_map(static fn (array $row): array => [
            'merchant_user_id' => (int) $row['merchant_user_id'],
            'stall_name' => (string) $row['stall_name'],
            'proprietor_name' => trim((string) $row['proprietor_name']) ?: ((string) ($row['proprietor_email'] ?? 'Unnamed proprietor')),
            'proprietor_email' => (string) ($row['proprietor_email'] ?? ''),
            'has_active_lease' => (bool) $row['has_active_lease'],
        ], $rows);
    }

    public function stallSummary(int $merchantId): ?array
    {
        $hasOperationalStatus = in_array('operational_status', gjc_table_columns($this->db, 'merchant'), true);
        $statusSelect = $hasOperationalStatus ? "COALESCE(m.operational_status, 'active')" : "'active'";

        $stmt = $this->db->prepare(
            "SELECT
                m.merchantID,
                m.userID AS merchant_user_id,
                m.stall_name,
                {$statusSelect} AS operational_status,
                TRIM(CONCAT(COALESCE(u.first_name, ''), ' ', COALESCE(u.last_name, ''))) AS proprietor_name,
                u.email AS proprietor_email,
                u.contact_number
             FROM merchant m
             LEFT JOIN users u ON u.userID = m.userID
             WHERE m.merchantID = ?
             LIMIT 1"
        );
        $stmt->execute([$merchantId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return null;
        }

        return [
            'merchant_id' => (int) $row['merchantID'],
            'merchant_user_id' => (int) $row['merchant_user_id'],
            'stall_name' => (string) $row['stall_name'],
            'operational_status' => (string) $row['operational_status'],
            'proprietor_name' => trim((string) $row['proprietor_name']) ?: ((string) ($row['proprietor_email'] ?? 'Unassigned proprietor')),
            'proprietor_email' => (string) ($row['proprietor_email'] ?? ''),
            'contact_number' => (string) ($row['contact_number'] ?? ''),
        ];
    }

    public function activeLease(int $merchantUserId): ?array
    {
        if (!gjc_table_exists($this->db, 'merchant_leases')) {
            return null;
        }

        $stmt = $this->db->prepare(
            "SELECT id
             FROM merchant_leases
             WHERE merchant_user_id = ?
             ORDER BY
                CASE WHEN status = 'active' THEN 0 ELSE 1 END,
                lease_end DESC,
                id DESC
             LIMIT 1"
        );
        $stmt->execute([$merchantUserId]);
        $id = (int) $stmt->fetchColumn();

        return $id > 0 ? $this->leaseById($id) : null;
    }

    public function leaseById(int $leaseId): ?array
    {
        if ($leaseId <= 0 || !gjc_table_exists($this->db, 'merchant_leases')) {
            return null;
        }

        $stmt = $this->db->prepare("SELECT * FROM merchant_leases WHERE id = ? LIMIT 1");
        $stmt->execute([$leaseId]);
        $lease = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$lease) {
            return null;
        }

        $paid = $this->leasePaidTotal((int) $lease['id']);
        $expected = $this->expectedRentThroughToday($lease);
        $balance = max(0, $expected - $paid);

        return [
            'id' => (int) $lease['id'],
            'merchant_user_id' => (int) $lease['merchant_user_id'],
            'stall_number' => (string) $lease['stall_number'],
            'stall_name' => (string) $lease['stall_name'],
            'monthly_rent' => (float) $lease['monthly_rent'],
            'deposit_amount' => (float) $lease['deposit_amount'],
            'lease_start' => (string) $lease['lease_start'],
            'lease_end' => (string) $lease['lease_end'],
            'next_due_date' => (string) $lease['next_due_date'],
            'status' => (string) $lease['status'],
            'contract_notes' => (string) ($lease['contract_notes'] ?? ''),
            'lifespan_months' => $this->monthSpan((string) $lease['lease_start'], (string) $lease['lease_end']),
            'expected_rent_to_date' => $expected,
            'paid_total' => $paid,
            'balance_due' => $balance,
            'current_month_status' => $this->currentMonthStatus((int) $lease['id'], (float) $lease['monthly_rent'], (string) $lease['status']),
        ];
    }

    public function pagedRentPayments(int $leaseId, string $from, string $to, int $page, int $perPage): array
    {
        if (!gjc_table_exists($this->db, 'merchant_rent_payments')) {
            return ['rows' => [], 'page' => 1, 'per_page' => $perPage, 'total' => 0, 'total_pages' => 1];
        }

        $page = max(1, $page);
        $perPage = min(50, max(5, $perPage));
        $offset = ($page - 1) * $perPage;

        $where = ['lease_id = ?'];
        $params = [$leaseId];

        if ($from !== '' && $this->isDate($from)) {
            $where[] = 'payment_date >= ?';
            $params[] = $from;
        }

        if ($to !== '' && $this->isDate($to)) {
            $where[] = 'payment_date <= ?';
            $params[] = $to;
        }

        $whereSql = implode(' AND ', $where);
        $hasPaymentMethod = in_array('payment_method', gjc_table_columns($this->db, 'merchant_rent_payments'), true);
        $paymentMethodSelect = $hasPaymentMethod ? 'payment_method' : "'cash' AS payment_method";

        $countStmt = $this->db->prepare("SELECT COUNT(*) FROM merchant_rent_payments WHERE {$whereSql}");
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $dataStmt = $this->db->prepare(
            "SELECT id, amount_paid, period_covered, payment_date, {$paymentMethodSelect}, reference_no, notes, created_at
             FROM merchant_rent_payments
             WHERE {$whereSql}
             ORDER BY payment_date DESC, id DESC
             LIMIT ? OFFSET ?"
        );

        $i = 1;
        foreach ($params as $param) {
            $dataStmt->bindValue($i++, $param);
        }
        $dataStmt->bindValue($i++, $perPage, PDO::PARAM_INT);
        $dataStmt->bindValue($i, $offset, PDO::PARAM_INT);
        $dataStmt->execute();

        return [
            'rows' => $dataStmt->fetchAll(PDO::FETCH_ASSOC),
            'page' => $page,
            'per_page' => $perPage,
            'total' => $total,
            'total_pages' => max(1, (int) ceil($total / $perPage)),
        ];
    }

    public function pagedInventory(int $merchantUserId, string $search, string $category, string $restriction, int $page, int $perPage): array
    {
        if (!gjc_table_exists($this->db, 'merchant_inventory')) {
            return ['rows' => [], 'page' => 1, 'per_page' => $perPage, 'total' => 0, 'total_pages' => 1];
        }

        $page = max(1, $page);
        $perPage = min(50, max(5, $perPage));
        $offset = ($page - 1) * $perPage;

        $where = ['merchant_user_id = ?'];
        $params = [$merchantUserId];

        if ($search !== '') {
            $where[] = '(product_name LIKE ? OR sku LIKE ?)';
            $params[] = '%' . $search . '%';
            $params[] = '%' . $search . '%';
        }

        if ($category !== '') {
            $where[] = 'category = ?';
            $params[] = $category;
        }

        if ($restriction === 'restricted') {
            $where[] = 'is_restricted = 1';
        } elseif ($restriction === 'allowed') {
            $where[] = 'is_restricted = 0';
        }

        $whereSql = implode(' AND ', $where);

        $countStmt = $this->db->prepare("SELECT COUNT(*) FROM merchant_inventory WHERE {$whereSql}");
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $dataStmt = $this->db->prepare(
            "SELECT id, sku, product_name, category, unit, price, stock_qty, is_available,
                    is_restricted, restriction_note, updated_at
             FROM merchant_inventory
             WHERE {$whereSql}
             ORDER BY product_name ASC, id ASC
             LIMIT ? OFFSET ?"
        );

        $i = 1;
        foreach ($params as $param) {
            $dataStmt->bindValue($i++, $param);
        }
        $dataStmt->bindValue($i++, $perPage, PDO::PARAM_INT);
        $dataStmt->bindValue($i, $offset, PDO::PARAM_INT);
        $dataStmt->execute();

        return [
            'rows' => $dataStmt->fetchAll(PDO::FETCH_ASSOC),
            'page' => $page,
            'per_page' => $perPage,
            'total' => $total,
            'total_pages' => max(1, (int) ceil($total / $perPage)),
        ];
    }

    /**
     * Paged merchant management activity from the systemic audit trail — the
     * same action set the dashboard notification badge counts: product/menu
     * changes, staff and profile changes, and banned-item attempts, by the
     * stall owner or their staff. Routine sales are excluded on purpose,
     * matching the stall detail view's revenue-privacy rule.
     */
    public function pagedActivity(int $merchantUserId, int $page, int $perPage): array
    {
        if (!gjc_table_exists($this->db, 'systemic_audit_trail')) {
            return ['rows' => [], 'page' => 1, 'per_page' => $perPage, 'total' => 0, 'total_pages' => 1];
        }

        $page = max(1, $page);
        $perPage = min(50, max(5, $perPage));
        $offset = ($page - 1) * $perPage;

        $actorSql = in_array('merchant_owner_id', gjc_table_columns($this->db, 'users'), true)
            ? 'a.user_id IN (SELECT u2.userID FROM users u2 WHERE u2.userID = ? OR u2.merchant_owner_id = ?)'
            : 'a.user_id = ?';
        $params = $actorSql === 'a.user_id = ?' ? [$merchantUserId] : [$merchantUserId, $merchantUserId];

        $whereSql = "{$actorSql} AND a.action_type IN ('MENU_MUTATION', 'USER_ACCOUNT', 'PRODUCT_RESTRICTION')";

        $countStmt = $this->db->prepare("SELECT COUNT(*) FROM systemic_audit_trail a WHERE {$whereSql}");
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $dataStmt = $this->db->prepare(
            "SELECT a.log_id, a.user_role, a.action_type, a.affected_table,
                    a.old_value, a.new_value, a.timestamp,
                    TRIM(CONCAT(COALESCE(u.first_name, ''), ' ', COALESCE(u.last_name, ''))) AS actor_name
             FROM systemic_audit_trail a
             LEFT JOIN users u ON u.userID = a.user_id
             WHERE {$whereSql}
             ORDER BY a.timestamp DESC, a.log_id DESC
             LIMIT ? OFFSET ?"
        );

        $i = 1;
        foreach ($params as $param) {
            $dataStmt->bindValue($i++, $param, PDO::PARAM_INT);
        }
        $dataStmt->bindValue($i++, $perPage, PDO::PARAM_INT);
        $dataStmt->bindValue($i, $offset, PDO::PARAM_INT);
        $dataStmt->execute();

        return [
            'rows' => $dataStmt->fetchAll(PDO::FETCH_ASSOC),
            'page' => $page,
            'per_page' => $perPage,
            'total' => $total,
            'total_pages' => max(1, (int) ceil($total / $perPage)),
        ];
    }

    public function toggleProductRestriction(int $itemId, bool $restricted, int $adminId, string $note): bool
    {
        if (!gjc_table_exists($this->db, 'merchant_inventory')) {
            return false;
        }

        $set = [
            'is_restricted = ?',
            'is_available = CASE WHEN ? = 1 THEN 0 ELSE is_available END',
            'restriction_note = ?',
        ];
        $params = [
            $restricted ? 1 : 0,
            $restricted ? 1 : 0,
            $restricted ? ($note ?: 'Restricted by school nutritional compliance review.') : null,
        ];

        $columns = gjc_table_columns($this->db, 'merchant_inventory');
        if (in_array('restricted_by', $columns, true)) {
            $set[] = 'restricted_by = ?';
            $params[] = $adminId;
        }
        if (in_array('restricted_at', $columns, true)) {
            $set[] = 'restricted_at = NOW()';
        }

        $params[] = $itemId;

        $stmt = $this->db->prepare(
            'UPDATE merchant_inventory SET ' . implode(', ', $set) . ' WHERE id = ?'
        );
        $stmt->execute($params);

        return $stmt->rowCount() > 0;
    }

    public function updateLease(array $input): bool
    {
        if (!gjc_table_exists($this->db, 'merchant_leases')) {
            return false;
        }

        $stmt = $this->db->prepare(
            "UPDATE merchant_leases
             SET monthly_rent = ?,
                 deposit_amount = ?,
                 lease_start = ?,
                 lease_end = ?,
                 next_due_date = ?,
                 status = ?,
                 contract_notes = ?
             WHERE id = ?"
        );

        $stmt->execute([
            (float) $input['monthly_rent'],
            (float) $input['deposit_amount'],
            (string) $input['lease_start'],
            (string) $input['lease_end'],
            (string) $input['next_due_date'],
            (string) $input['status'],
            (string) $input['contract_notes'],
            (int) $input['lease_id'],
        ]);

        return $stmt->rowCount() >= 0;
    }

    public function recordRentPayment(int $leaseId, float $amount, string $period, string $paymentDate, string $method, string $notes, int $adminId): string
    {
        $reference = 'RENT-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));

        $columns = ['lease_id', 'amount_paid', 'period_covered', 'payment_date'];
        $values = ['?', '?', '?', '?'];
        $params = [
            $leaseId,
            $amount,
            $period,
            $paymentDate,
        ];

        if (in_array('payment_method', gjc_table_columns($this->db, 'merchant_rent_payments'), true)) {
            $columns[] = 'payment_method';
            $values[] = '?';
            $params[] = $method;
        }

        array_push($columns, 'received_by', 'reference_no', 'notes');
        array_push($values, '?', '?', '?');
        array_push($params, $adminId, $reference, $notes ?: null);

        $stmt = $this->db->prepare(
            'INSERT INTO merchant_rent_payments (' . implode(', ', $columns) . ')
             VALUES (' . implode(', ', $values) . ')'
        );
        $stmt->execute($params);

        return $reference;
    }

    private function leasePaidTotal(int $leaseId): float
    {
        if (!gjc_table_exists($this->db, 'merchant_rent_payments')) {
            return 0.0;
        }

        $stmt = $this->db->prepare("SELECT COALESCE(SUM(amount_paid), 0) FROM merchant_rent_payments WHERE lease_id = ?");
        $stmt->execute([$leaseId]);
        return (float) $stmt->fetchColumn();
    }

    private function expectedRentThroughToday(array $lease): float
    {
        if ((string) $lease['status'] === 'pending') {
            return 0.0;
        }

        $start = strtotime((string) $lease['lease_start']);
        $end = strtotime((string) $lease['lease_end']);
        $today = time();

        if (!$start || !$end || $today < $start) {
            return 0.0;
        }

        $effectiveEnd = min($today, $end);
        return $this->monthSpan(date('Y-m-d', $start), date('Y-m-d', $effectiveEnd)) * (float) $lease['monthly_rent'];
    }

    private function currentMonthStatus(int $leaseId, float $monthlyRent, string $leaseStatus): string
    {
        if (!gjc_table_exists($this->db, 'merchant_rent_payments')) {
            return $this->monthLeaseStatus($leaseStatus, $monthlyRent, 0.0);
        }

        $period = date('Y-m');
        $stmt = $this->db->prepare(
            "SELECT COALESCE(SUM(amount_paid), 0)
             FROM merchant_rent_payments
             WHERE lease_id = ? AND period_covered = ?"
        );
        $stmt->execute([$leaseId, $period]);
        $paid = (float) $stmt->fetchColumn();

        return $this->monthLeaseStatus($leaseStatus, $monthlyRent, $paid);
    }

    private function monthLeaseStatus(string $leaseStatus, float $monthlyRent, float $paid): string
    {
        if ($leaseStatus === '') {
            return 'No lease';
        }

        if ($leaseStatus !== 'active') {
            return ucfirst($leaseStatus);
        }

        if ($monthlyRent <= 0) {
            return 'No rent due';
        }

        if ($paid >= $monthlyRent) {
            return 'Paid';
        }

        if ($paid > 0) {
            return 'Partially paid';
        }

        return 'Unpaid';
    }

    private function monthSpan(string $start, string $end): int
    {
        $startTs = strtotime($start);
        $endTs = strtotime($end);

        if (!$startTs || !$endTs || $endTs < $startTs) {
            return 0;
        }

        $startParts = getdate($startTs);
        $endParts = getdate($endTs);

        return (($endParts['year'] - $startParts['year']) * 12) + ($endParts['mon'] - $startParts['mon']) + 1;
    }

    private function isDate(string $date): bool
    {
        $dt = DateTime::createFromFormat('Y-m-d', $date);
        return $dt && $dt->format('Y-m-d') === $date;
    }
}
