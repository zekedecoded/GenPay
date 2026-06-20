<?php


header('Content-Type: application/json');

session_start();
require_once __DIR__ . '/../../connection/config.php';
require_once __DIR__ . '/../../connection/pdo.php';
require_once __DIR__ . '/../../connection/MintingGuard.php';

if (!isset($_SESSION['userID']) || ($_SESSION['sub_role'] ?? '') !== 'super_admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'ACCESS_DENIED: Super-Admin only.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method Not Allowed.']);
    exit;
}

$body = [];
$contentType = $_SERVER['CONTENT_TYPE'] ?? '';
if (str_contains($contentType, 'application/json')) {
    $body = json_decode(file_get_contents('php://input'), true) ?? [];
} else {
    $body = $_POST;
}

$amount  = isset($body['amount'])  ? (float)$body['amount']    : 0.0;
$reason  = isset($body['reason'])  ? trim((string)$body['reason']) : '';
$pin     = isset($body['pin'])     ? trim((string)$body['pin'])    : null;
if ($pin === '') $pin = null;

try {
    $guard  = new MintingGuard($db);
    $result = $guard->attemptMint($_SESSION['userID'], $amount, $reason, $pin);
    echo json_encode($result);
} catch (RuntimeException | InvalidArgumentException $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
} catch (Throwable $e) {
    http_response_code(500);
    error_log('[mint.php] Unexpected error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Internal server error. Check server logs.']);
}

