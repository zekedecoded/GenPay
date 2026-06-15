<?php
require_once __DIR__ . '/../connection/config.php';
require_once __DIR__ . '/../connection/pdo.php';
require_once __DIR__ . '/../connection/app.php';

gjc_require_role(['finance']);

$filters = [
    'search' => trim((string) ($_GET['search'] ?? '')),
    'type' => trim((string) ($_GET['type'] ?? '')),
    'status' => trim((string) ($_GET['status'] ?? '')),
];

$transactions = gjc_fetch_admin_transactions($db, $filters, 0);

header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="transactions-' . date('Ymd-His') . '.csv"');

$output = fopen('php://output', 'w');
fputcsv($output, ['Reference', 'Type', 'Amount', 'Sender', 'Receiver', 'Status', 'Time', 'Source', 'Notes']);

foreach ($transactions as $transaction) {
    fputcsv($output, [
        $transaction['ref'],
        $transaction['type_label'],
        number_format((float) $transaction['amount'], 2, '.', ''),
        $transaction['sender'],
        $transaction['receiver'],
        $transaction['status_label'],
        $transaction['created_at'],
        $transaction['source'],
        $transaction['notes'],
    ]);
}

fclose($output);
