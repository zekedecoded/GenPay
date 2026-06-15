<?php
// Mock session and post variables
session_start();
$_SESSION['userID'] = 12;
$_SESSION['user_id'] = 12;
$_SESSION['roleID'] = 4;
$_SESSION['sub_role'] = 'super_admin';
$_SESSION['role'] = 'finance';

$_POST['action'] = 'initial_approval';
$_POST['app_id'] = 1;

// Capture output
ob_start();
try {
    require __DIR__ . '/../admin/api/stall_applications.php';
} catch (Exception $e) {
    echo "Exception: " . $e->getMessage() . "\n";
}
$output = ob_get_clean();

echo "=== API OUTPUT ===\n";
echo $output . "\n";
