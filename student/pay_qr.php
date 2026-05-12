<?php
require_once __DIR__ . '/../connection/config.php';
require_once __DIR__ . '/../connection/pdo.php';
require_once __DIR__ . '/../connection/app.php';
require_once __DIR__ . '/../connection/CirculationEngine.php';

header('Content-Type: application/json');

try {
    gjc_require_role(['student']);

    $payload = json_decode(file_get_contents('php://input'), true);
    if (!is_array($payload)) {
        $payload = $_POST;
    }

    $merchantWalletId = (int) ($payload['merchant_wallet_id'] ?? 0);
    $amount = (float) ($payload['amount'] ?? $payload['price'] ?? 0);

    if ($merchantWalletId <= 0 || $amount <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid QR payment details.']);
        exit;
    }

    $currentUser = gjc_current_user($db);
    $wallet = gjc_student_wallet($db, $currentUser['id']);
    if ($wallet['id'] <= 0) {
        http_response_code(422);
        echo json_encode(['success' => false, 'message' => 'Student wallet not found.']);
        exit;
    }

    $engine = new CirculationEngine($db);
    $result = $engine->studentPay($wallet['id'], $merchantWalletId, $amount, $currentUser['id']);

    echo json_encode([
        'success' => true,
        'message' => 'Payment completed.',
        'reference' => $result['reference'],
    ]);
} catch (RuntimeException $e) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'A server error occurred while processing payment.']);
}
