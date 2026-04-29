<?php
/**
 * api/mint.php
 * ─────────────────────────────────────────────────────────────────────────────
 * POST endpoint — Super-Admin increases the circulation cap.
 *
 * Required POST fields:
 *   amount   (float)         — points to mint
 *   reason   (string)        — audit justification
 *   pin      (string|null)   — Mint PIN (required only if monthly limit exceeded)
 *
 * Session requirement: $_SESSION['roleID'] === 1 (Super-Admin)
 *
 * Response JSON:
 *   { success, new_cap, new_vault, minted_this_month,
 *     remaining_soft_limit, soft_limit_exceeded, reference }
 *   OR
 *   { success: false, error: "..." }
 * ─────────────────────────────────────────────────────────────────────────────
 */

declare(strict_types=1);
header('Content-Type: application/json');

session_start();
require_once __DIR__ . '/../connection/config.php';
require_once __DIR__ . '/../connection/pdo.php';
require_once __DIR__ . '/../connection/MintingGuard.php';

// ── Auth guard ───────────────────────────────────────────────────────────────
if (!isset($_SESSION['userID'], $_SESSION['roleID']) || (int)$_SESSION['roleID'] !== 3) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'ACCESS_DENIED: Super-Admin only.']);
    exit;
}

// ── Only allow POST ──────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method Not Allowed.']);
    exit;
}

// ── Parse input (support both form-data and JSON body) ───────────────────────
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

// ── Execute ──────────────────────────────────────────────────────────────────
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
