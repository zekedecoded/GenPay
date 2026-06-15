<?php
require_once __DIR__ . '/../connection/pdo.php';

try {
    $email = 'ezekielclarence06@gmail.com';
    echo "=== CARDO IN USERS ===\n";
    $stmt = $db->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($user) {
        print_r($user);
    } else {
        echo "Cardo not found in users table!\n";
    }

    echo "\n=== CARDO IN MERCHANT ===\n";
    $stmt2 = $db->prepare("SELECT * FROM merchant WHERE userID = ?");
    $stmt2->execute([$user['userID'] ?? 0]);
    $merch = $stmt2->fetch(PDO::FETCH_ASSOC);
    if ($merch) {
        print_r($merch);
    } else {
        echo "Cardo not found in merchant table!\n";
    }

    echo "\n=== CARDO WALLET ===\n";
    $stmt3 = $db->prepare("SELECT * FROM merchant_wallets WHERE user_id = ?");
    $stmt3->execute([$user['userID'] ?? 0]);
    $wal = $stmt3->fetch(PDO::FETCH_ASSOC);
    if ($wal) {
        print_r($wal);
    } else {
        echo "Cardo wallet not found!\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
