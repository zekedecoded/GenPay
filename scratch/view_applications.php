<?php
require_once __DIR__ . '/../connection/pdo.php';

try {
    echo "=== STALL APPLICATIONS ===\n";
    $stmt = $db->query("SELECT id, proprietor_name, business_name, stall_id, status, contract_ref, signed_at FROM stall_applications");
    $apps = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($apps as $app) {
        printf("ID: %d | Name: %s | Business: %s | Stall: %s | Status: %s | Ref: %s | Signed: %s\n",
            $app['id'],
            $app['proprietor_name'],
            $app['business_name'],
            $app['stall_id'],
            $app['status'],
            $app['contract_ref'] ?? 'NULL',
            $app['signed_at'] ?? 'NULL'
        );
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
