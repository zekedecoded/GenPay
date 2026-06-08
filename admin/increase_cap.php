<?php


session_start();
require_once __DIR__ . '/../connection/config.php';
require_once __DIR__ . '/../connection/pdo.php';
require_once __DIR__ . '/../connection/CirculationEngine.php';

header('Content-Type: application/json');


if (!isset($_SESSION['user_id'], $_SESSION['role'])
    || $_SESSION['role'] !== 'super-admin') {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'message' => 'ACCESS DENIED: Only a Super-Admin can increase the money supply.',
    ]);
    exit;
}


$increaseBy = filter_input(INPUT_POST, 'increase_by', FILTER_VALIDATE_FLOAT);
$reason     = trim(filter_input(INPUT_POST, 'reason', FILTER_SANITIZE_SPECIAL_CHARS) ?? '');

if (!$increaseBy || $increaseBy <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'A positive increase amount is required.']);
    exit;
}
if (strlen($reason) < 10) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'A detailed reason (min 10 characters) is required for audit compliance.']);
    exit;
}


try {
    $engine = new CirculationEngine($db);
    $result = $engine->increaseCirculationCap($increaseBy, $_SESSION['user_id'], $reason);

    echo json_encode([
        'success'     => true,
        'message'     => "Money supply increased by ₱" . number_format($increaseBy, 2) . ". New cap: ₱" . number_format($result['new_cap'], 2) . ".",
        'old_cap'     => $result['old_cap'],
        'new_cap'     => $result['new_cap'],
        'vault_after' => $result['vault_after'],
        'reference'   => $result['reference'],
    ]);

} catch (RuntimeException | InvalidArgumentException $e) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
} catch (\Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'A server error occurred.']);
}
