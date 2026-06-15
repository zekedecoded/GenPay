<?php
/**
 * admin/api/transactions_paginated.php
 * Server-side paginated transaction data endpoint.
 * Called via AJAX from admin transaction list pages.
 *
 * Query params (GET or POST):
 *   page        int   default 1
 *   per_page    int   default 20, max 100
 *   date_from   date  Y-m-d
 *   date_to     date  Y-m-d
 *   type        string  transaction_type filter
 *   status      string  status filter
 *   search      string  reference_no / user name search
 */

session_start();
require_once __DIR__ . '/../../connection/config.php';
require_once __DIR__ . '/../../connection/pdo.php';
require_once __DIR__ . '/../../connection/app.php';

header('Content-Type: application/json');
gjc_require_role(['finance']);

// â”€â”€ Parse & sanitize inputs â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
$input    = array_merge($_GET, $_POST);
$page     = max(1, (int) ($input['page'] ?? 1));
$perPage  = min(100, max(5, (int) ($input['per_page'] ?? 20)));
$offset   = ($page - 1) * $perPage;

$dateFrom = trim((string) ($input['date_from'] ?? ''));
$dateTo   = trim((string) ($input['date_to'] ?? ''));
$type     = trim((string) ($input['type'] ?? ''));
$status   = trim((string) ($input['status'] ?? ''));
$search   = trim((string) ($input['search'] ?? ''));

// Whitelist allowed transaction types
$allowedTypes = [
    'cash_in', 'payment', 'voucher_payment', 'merchant_settle',
    'voucher_create', 'voucher_expire', 'cap_increase', 'p2p_transfer', 'service_fee'
];
$allowedStatuses = ['pending', 'completed', 'failed', 'cancelled'];

if ($type   && !in_array($type,   $allowedTypes,    true)) $type   = '';
if ($status && !in_array($status, $allowedStatuses, true)) $status = '';

// Validate dates
if ($dateFrom && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom)) $dateFrom = '';
if ($dateTo   && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo))   $dateTo   = '';

// â”€â”€ Build query â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
$where  = ['1=1'];
$params = [];

if ($dateFrom) {
    $where[]  = 't.created_at >= ?';
    $params[] = $dateFrom . ' 00:00:00';
}
if ($dateTo) {
    $where[]  = 't.created_at <= ?';
    $params[] = $dateTo . ' 23:59:59';
}
if ($type) {
    $where[]  = 't.transaction_type = ?';
    $params[] = $type;
}
if ($status) {
    $where[]  = 't.status = ?';
    $params[] = $status;
}
if ($search) {
    $where[]  = '(t.reference_no LIKE ? OR CONCAT(u.first_name, " ", u.last_name) LIKE ?)';
    $likeVal  = '%' . $search . '%';
    $params[] = $likeVal;
    $params[] = $likeVal;
}

$whereClause = implode(' AND ', $where);

$baseJoin = "FROM transactions t
             LEFT JOIN users u ON u.userID = t.initiated_by";

// Count total rows
$countSql  = "SELECT COUNT(*) {$baseJoin} WHERE {$whereClause}";
$countStmt = $db->prepare($countSql);
$countStmt->execute($params);
$totalRows  = (int) $countStmt->fetchColumn();
$totalPages = max(1, (int) ceil($totalRows / $perPage));

// Fetch rows
$dataSql = "SELECT
                t.id,
                t.reference_no,
                t.transaction_type,
                t.amount,
                t.vault_before,
                t.vault_after,
                t.total_in_circulation,
                t.status,
                t.notes,
                t.created_at,
                CONCAT(u.first_name, ' ', u.last_name) AS initiated_by_name,
                u.userID AS initiated_by_id
            {$baseJoin}
            WHERE {$whereClause}
            ORDER BY t.created_at DESC
            LIMIT ? OFFSET ?";

$dataParams = array_merge($params, [$perPage, $offset]);
$dataStmt   = $db->prepare($dataSql);
$dataStmt->execute($dataParams);
$rows = $dataStmt->fetchAll(PDO::FETCH_ASSOC);

// Format rows for JSON output
$formatted = array_map(function (array $row): array {
    return [
        'id'                   => (int)    $row['id'],
        'reference_no'         => (string) $row['reference_no'],
        'transaction_type'     => (string) $row['transaction_type'],
        'amount'               => (float)  $row['amount'],
        'vault_before'         => (float)  $row['vault_before'],
        'vault_after'          => (float)  $row['vault_after'],
        'total_in_circulation' => (float)  $row['total_in_circulation'],
        'status'               => (string) $row['status'],
        'notes'                => (string) ($row['notes'] ?? ''),
        'created_at'           => (string) $row['created_at'],
        'initiated_by_name'    => trim((string) $row['initiated_by_name']),
        'initiated_by_id'      => (int)    ($row['initiated_by_id'] ?? 0),
    ];
}, $rows);

echo json_encode([
    'success'     => true,
    'data'        => [
        'rows'        => $formatted,
        'total'       => $totalRows,
        'page'        => $page,
        'per_page'    => $perPage,
        'total_pages' => $totalPages,
        'has_next'    => $page < $totalPages,
        'has_prev'    => $page > 1,
    ],
]);
