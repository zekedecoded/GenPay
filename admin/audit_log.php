<?php
require_once __DIR__ . "/../connection/config.php";
require_once __DIR__ . "/../connection/pdo.php";
require_once __DIR__ . "/../connection/app.php";
require_once __DIR__ . "/../connection/audit_logger.php";

gjc_require_role(["finance"]);
gjc_ensure_audit_table($db);
$currentUser = gjc_current_user($db);

$roles = ["", "Finance", "Student", "Merchant", "Vendor/Staff"];
$actions = [
    "",
    "LOGIN",
    "LOGOUT",
    "PASSWORD_CHANGE",
    "TRANSACTION",
    "MENU_MUTATION",
    "STALL_UPDATE",
    "USER_IMPORT",
    "MERCHANT_CREATE",
    "USER_ACCOUNT",
    "MERCHANT_ONBOARDING",
    "PRODUCT_RESTRICTION",
    "LOGIN_FAILED",
];

$userRole = (string) ($_GET["user_role"] ?? "");
$actionType = (string) ($_GET["action_type"] ?? "");
$dateFrom = trim((string) ($_GET["date_from"] ?? ""));
$dateTo = trim((string) ($_GET["date_to"] ?? ""));
$page = max(1, (int) ($_GET["page"] ?? 1));
$perPage = 20;
$offset = ($page - 1) * $perPage;

if (!in_array($userRole, $roles, true)) {
    $userRole = "";
}
if (!in_array($actionType, $actions, true)) {
    $actionType = "";
}

$where = [];
$params = [];

if ($userRole !== "") {
    $where[] = "sat.user_role = ?";
    $params[] = $userRole;
}
if ($actionType !== "") {
    $where[] = "sat.action_type = ?";
    $params[] = $actionType;
}
if ($dateFrom !== "") {
    $where[] = "DATE(sat.timestamp) >= ?";
    $params[] = $dateFrom;
}
if ($dateTo !== "") {
    $where[] = "DATE(sat.timestamp) <= ?";
    $params[] = $dateTo;
}

$whereSql = $where ? "WHERE " . implode(" AND ", $where) : "";

$countStmt = $db->prepare(
    "SELECT COUNT(*) FROM systemic_audit_trail sat {$whereSql}",
);
$countStmt->execute($params);
$totalRows = (int) $countStmt->fetchColumn();
$totalPages = max(1, (int) ceil($totalRows / $perPage));
if ($page > $totalPages) {
    $page = $totalPages;
    $offset = ($page - 1) * $perPage;
}

$sql = "SELECT sat.*,
               TRIM(CONCAT(COALESCE(u.first_name, ''), ' ', COALESCE(u.last_name, ''))) AS actor_name,
               u.email AS actor_email
          FROM systemic_audit_trail sat
          LEFT JOIN users u ON u.userID = sat.user_id
          {$whereSql}
         ORDER BY sat.timestamp DESC, sat.log_id DESC
         LIMIT {$perPage} OFFSET {$offset}";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

$transactionRefs = [];
foreach ($logs as $log) {
    if (($log["action_type"] ?? "") !== "TRANSACTION") {
        continue;
    }

    $payload = json_decode((string) ($log["new_value"] ?? ""), true);
    if (is_array($payload) && !empty($payload["reference_no"])) {
        $transactionRefs[] = (string) $payload["reference_no"];
    }
}
$transactionRefs = array_values(array_unique($transactionRefs));
$transactionDetailsByRef = [];
$p2pDetailsByRef = [];

if ($transactionRefs && gjc_table_exists($db, "transactions")) {
    $placeholders = implode(",", array_fill(0, count($transactionRefs), "?"));
    $txnStmt = $db->prepare(
        "SELECT t.*,
                TRIM(CONCAT(COALESCE(actor.first_name, ''), ' ', COALESCE(actor.last_name, ''))) AS initiated_by_name,
                actor.email AS initiated_by_email,
                sw.user_id AS student_user_id,
                TRIM(CONCAT(COALESCE(student.first_name, ''), ' ', COALESCE(student.last_name, ''))) AS student_name,
                student.email AS student_email,
                mw.user_id AS merchant_user_id,
                TRIM(CONCAT(COALESCE(merchant.first_name, ''), ' ', COALESCE(merchant.last_name, ''))) AS merchant_name,
                merchant.email AS merchant_email
           FROM transactions t
           LEFT JOIN users actor ON actor.userID = t.initiated_by
           LEFT JOIN student_wallets sw ON sw.id = t.student_wallet_id
           LEFT JOIN users student ON student.userID = sw.user_id
           LEFT JOIN merchant_wallets mw ON mw.id = t.merchant_wallet_id
           LEFT JOIN users merchant ON merchant.userID = mw.user_id
          WHERE t.reference_no IN ({$placeholders})",
    );
    $txnStmt->execute($transactionRefs);

    foreach ($txnStmt->fetchAll(PDO::FETCH_ASSOC) as $txn) {
        $transactionDetailsByRef[(string) $txn["reference_no"]] = [
            "ledger_id" => (int) $txn["id"],
            "reference_no" => (string) $txn["reference_no"],
            "transaction_type" => (string) $txn["transaction_type"],
            "amount" => (float) $txn["amount"],
            "status" => (string) $txn["status"],
            "created_at" => (string) $txn["created_at"],
            "created_at_label" => date(
                "M j, Y g:i A",
                strtotime((string) $txn["created_at"]),
            ),
            "initiated_by" => [
                "user_id" => (int) $txn["initiated_by"],
                "name" => trim(
                    (string) ($txn["initiated_by_name"] ?:
                    "User #" . $txn["initiated_by"]),
                ),
                "email" => (string) ($txn["initiated_by_email"] ?? ""),
            ],
            "student" => [
                "wallet_id" =>
                    $txn["student_wallet_id"] !== null
                        ? (int) $txn["student_wallet_id"]
                        : null,
                "user_id" =>
                    $txn["student_user_id"] !== null
                        ? (int) $txn["student_user_id"]
                        : null,
                "name" => trim((string) ($txn["student_name"] ?? "")),
                "email" => (string) ($txn["student_email"] ?? ""),
            ],
            "merchant" => [
                "wallet_id" =>
                    $txn["merchant_wallet_id"] !== null
                        ? (int) $txn["merchant_wallet_id"]
                        : null,
                "user_id" =>
                    $txn["merchant_user_id"] !== null
                        ? (int) $txn["merchant_user_id"]
                        : null,
                "name" => trim((string) ($txn["merchant_name"] ?? "")),
                "email" => (string) ($txn["merchant_email"] ?? ""),
            ],
            "voucher_id" =>
                $txn["voucher_id"] !== null ? (int) $txn["voucher_id"] : null,
            "vault_before" => (float) $txn["vault_before"],
            "vault_after" => (float) $txn["vault_after"],
            "total_in_circulation" => (float) $txn["total_in_circulation"],
            "notes" => (string) ($txn["notes"] ?? ""),
        ];
    }
}

if ($transactionRefs && gjc_table_exists($db, "p2p_transfers")) {
    $placeholders = implode(",", array_fill(0, count($transactionRefs), "?"));
    $p2pStmt = $db->prepare(
        "SELECT p.*,
                TRIM(CONCAT(COALESCE(from_user.first_name, ''), ' ', COALESCE(from_user.last_name, ''))) AS from_name,
                from_user.email AS from_email,
                TRIM(CONCAT(COALESCE(to_user.first_name, ''), ' ', COALESCE(to_user.last_name, ''))) AS to_name,
                to_user.email AS to_email
           FROM p2p_transfers p
           LEFT JOIN users from_user ON from_user.userID = p.from_user_id
           LEFT JOIN users to_user ON to_user.userID = p.to_user_id
          WHERE p.reference_no IN ({$placeholders})",
    );
    $p2pStmt->execute($transactionRefs);

    foreach ($p2pStmt->fetchAll(PDO::FETCH_ASSOC) as $p2p) {
        $p2pDetailsByRef[(string) $p2p["reference_no"]] = [
            "from" => [
                "wallet_id" => (int) $p2p["from_wallet_id"],
                "user_id" => (int) $p2p["from_user_id"],
                "name" => trim(
                    (string) ($p2p["from_name"] ?:
                    "User #" . $p2p["from_user_id"]),
                ),
                "email" => (string) ($p2p["from_email"] ?? ""),
            ],
            "to" => [
                "wallet_id" => (int) $p2p["to_wallet_id"],
                "user_id" => (int) $p2p["to_user_id"],
                "name" => trim(
                    (string) ($p2p["to_name"] ?: "User #" . $p2p["to_user_id"]),
                ),
                "email" => (string) ($p2p["to_email"] ?? ""),
            ],
            "message" => (string) ($p2p["message"] ?? ""),
            "status" => (string) $p2p["status"],
            "created_at" => (string) $p2p["created_at"],
            "created_at_label" => date(
                "M j, Y g:i A",
                strtotime((string) $p2p["created_at"]),
            ),
        ];
    }
}

foreach ($logs as &$log) {
    $payload = json_decode((string) ($log["new_value"] ?? ""), true);
    $ref = is_array($payload) ? (string) ($payload["reference_no"] ?? "") : "";
    $details = $ref !== "" ? $transactionDetailsByRef[$ref] ?? [] : [];
    if (!$details && is_array($payload) && $ref !== "") {
        $details = [
            "reference_no" => $ref,
            "ledger_id" => null,
            "transaction_type" =>
                (string) ($payload["transaction_type"] ?? "transaction"),
            "amount" => isset($payload["amount"])
                ? (float) $payload["amount"]
                : 0.0,
            "status" => (string) ($payload["status"] ?? ""),
            "created_at" => (string) ($log["timestamp"] ?? ""),
            "created_at_label" => date(
                "M j, Y g:i A",
                strtotime((string) ($log["timestamp"] ?? "now")),
            ),
            "initiated_by" => [
                "user_id" => (int) ($log["user_id"] ?? 0),
                "name" => trim(
                    (string) ($log["actor_name"] ?? "" ?:
                    "User #" . ($log["user_id"] ?? 0)),
                ),
                "email" => (string) ($log["actor_email"] ?? ""),
            ],
            "student" => [
                "wallet_id" => isset($payload["student_wallet_id"])
                    ? (int) $payload["student_wallet_id"]
                    : null,
                "user_id" => isset($payload["student_user_id"])
                    ? (int) $payload["student_user_id"]
                    : null,
                "name" => "",
                "email" => "",
            ],
            "merchant" => [
                "wallet_id" => isset($payload["merchant_wallet_id"])
                    ? (int) $payload["merchant_wallet_id"]
                    : null,
                "user_id" => isset($payload["merchant_user_id"])
                    ? (int) $payload["merchant_user_id"]
                    : null,
                "name" => "",
                "email" => "",
            ],
            "voucher_id" => isset($payload["voucher_id"])
                ? (int) $payload["voucher_id"]
                : null,
            "vault_before" => isset($payload["vault_before"])
                ? (float) $payload["vault_before"]
                : null,
            "vault_after" => isset($payload["vault_after"])
                ? (float) $payload["vault_after"]
                : null,
            "total_in_circulation" => isset($payload["total_in_circulation"])
                ? (float) $payload["total_in_circulation"]
                : null,
            "notes" => (string) ($payload["notes"] ?? ""),
        ];
    }
    if ($details) {
        $details["audit_payload"] = is_array($payload) ? $payload : [];
        if (!empty($payload["items"]) && is_array($payload["items"])) {
            $details["items"] = $payload["items"];
        }
        if ($ref !== "" && isset($p2pDetailsByRef[$ref])) {
            $details["p2p"] = $p2pDetailsByRef[$ref];
            $details["sender"] = $p2pDetailsByRef[$ref]["from"];
            $details["receiver"] = $p2pDetailsByRef[$ref]["to"];
            if (
                empty($details["notes"]) &&
                !empty($p2pDetailsByRef[$ref]["message"])
            ) {
                $details["notes"] = $p2pDetailsByRef[$ref]["message"];
            }
        }
    }
    $log["transaction_details_json"] = $details
        ? json_encode($details, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
        : "";
}
unset($log);

$queryBase = [
    "user_role" => $userRole,
    "action_type" => $actionType,
    "date_from" => $dateFrom,
    "date_to" => $dateTo,
];

function audit_e($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, "UTF-8");
}

function audit_render_row(array $log): string
{
    $rowClass = $log["action_type"] === "LOGIN" ? "audit-row-login" : "";
    $rowClass = $log["action_type"] === "PASSWORD_CHANGE" ? "audit-row-password" : $rowClass;
    $rowClass = $log["action_type"] === "LOGIN_FAILED" ? "audit-row-login-failed" : $rowClass;
    $actor = trim(
        (string) ($log["actor_name"] ?:
        $log["actor_email"] ?:
        "User #" . $log["user_id"]),
    );

    ob_start();
    ?>
    <tr class="<?= $rowClass ?>">
        <td><?= audit_e(
            date(
                "M j, Y g:i A",
                strtotime((string) $log["timestamp"]),
            ),
        ) ?></td>
        <td>
            <strong><?= audit_e($actor) ?></strong><br>
            <small><?= audit_e(
                (string) ($log["actor_email"] ?? ""),
            ) ?></small>
        </td>
        <td><?= audit_e($log["user_role"]) ?></td>
        <td><span class="audit-pill <?= audit_e(
            $log["action_type"],
        ) ?>"><?= audit_e(
    $log["action_type"],
) ?></span></td>
        <td><?= audit_e($log["affected_table"]) ?></td>
        <td>
            <button
                type="button"
                class="details-btn"
                data-bs-toggle="modal"
                data-bs-target="#auditDetailsModal"
                data-actor="<?= audit_e($actor) ?>"
                data-role="<?= audit_e(
                    $log["user_role"],
                ) ?>"
                data-action="<?= audit_e(
                    $log["action_type"],
                ) ?>"
                data-time="<?= audit_e(
                    date(
                        "M j, Y g:i A",
                        strtotime(
                            (string) $log["timestamp"],
                        ),
                    ),
                ) ?>"
                data-table="<?= audit_e(
                    $log["affected_table"],
                ) ?>"
                data-transaction-details="<?= audit_e(
                    $log["transaction_details_json"] ??
                        "",
                ) ?>"
                data-old-value="<?= audit_e(
                    $log["old_value"] ?? "",
                ) ?>"
                data-new-value="<?= audit_e(
                    $log["new_value"] ?? "",
                ) ?>">
        <i class="fa-solid fa-eye"></i> View
            </button>
        </td>
    </tr>
    <?php
    return ob_get_clean();
}

function audit_render_pagination(int $page, int $totalPages, int $totalRows, array $queryBase): string
{
    ob_start();
    ?>
    <div class="audit-pagination">
        <span>Page <?= (int) $page ?> of <?= (int) $totalPages ?> &middot; <?= (int) $totalRows ?> records</span>
        <div class="audit-pagination-links">
            <a class="<?= $page <= 1
                ? "disabled"
                : "" ?>" href="<?= audit_e(
    ADMIN_URL,
) ?>/audit_log.php?<?= audit_e(
    http_build_query($queryBase + ["page" => max(1, $page - 1)]),
) ?>"><i class="fa-solid fa-chevron-left"></i> Previous</a>
            <a class="<?= $page >= $totalPages
                ? "disabled"
                : "" ?>" href="<?= audit_e(
    ADMIN_URL,
) ?>/audit_log.php?<?= audit_e(
    http_build_query($queryBase + ["page" => min($totalPages, $page + 1)]),
) ?>">Next <i class="fa-solid fa-chevron-right"></i></a>
        </div>
    </div>
    <?php
    return ob_get_clean();
}

if (($_GET["ajax"] ?? "") === "1") {
    header("Content-Type: application/json");
    echo json_encode([
        "success" => true,
        "rows_html" => $logs
            ? implode("", array_map("audit_render_row", $logs))
            : '<tr><td colspan="6" class="text-center py-4">No audit records matched the selected filters.</td></tr>',
        "pagination_html" => audit_render_pagination($page, $totalPages, $totalRows, $queryBase),
        "total_rows" => $totalRows,
    ]);
    exit;
}

$currentPage = "audit_log";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="icon" type="image/png" sizes="32x32" href="<?= ICONS_URL ?>/gp_logo.png">
    <link rel="icon" type="image/png" sizes="192x192" href="<?= ICONS_URL ?>/gp_logo.png">
    <link rel="apple-touch-icon" sizes="180x180" href="<?= ICONS_URL ?>/gp_logo.png">
    <meta charset="UTF-8">
    <title>Audit Log | GenPay</title>
    <link rel="stylesheet" href="<?= CSS_URL ?>/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer">
    <link rel="stylesheet" href="<?= CSS_URL ?>/admin.css?v=5">
    <link rel="stylesheet" href="<?= CSS_URL ?>/responsive.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= CSS_URL ?>/audit_log.css?v=1">
</head>
<body>
    <div class="admin-layout">
        <?php require __DIR__ . "/../includes/partials/sidebar_admin.php"; ?>

        <main class="admin-main">
            <header class="topbar">
                <button class="menu-btn" onclick="toggleSidebar()"><i class="fa-solid fa-bars"></i></button>
                <div>
                    <h1>Systemic Audit Trail</h1>
                    <p>Read-only activity log for authentication, wallet, menu, and stall events.</p>
                </div>
                <div class="admin-user">
                    <span><?= audit_e($currentUser['name'] ?? 'Admin') ?></span>
                    <div class="avatar"><i class="fa-solid fa-user-tie"></i></div>
                </div>
            </header>

            <section class="audit-panel mb-4">
                <form method="GET" action="<?= audit_e(
                    ADMIN_URL,
                ) ?>/audit_log.php" class="audit-filter-grid">
                    <div class="audit-field">
                        <label>User Role</label>
                        <select name="user_role">
                            <?php foreach ($roles as $role): ?>
                            <option value="<?= audit_e(
                                $role,
                            ) ?>" <?= $userRole === $role ? "selected" : "" ?>>
                                <?= $role === ""
                                    ? "All roles"
                                    : audit_e($role) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="audit-field">
                        <label>Action Type</label>
                        <select name="action_type">
                            <?php foreach ($actions as $action): ?>
                            <option value="<?= audit_e(
                                $action,
                            ) ?>" <?= $actionType === $action
    ? "selected"
    : "" ?>>
                                <?= $action === ""
                                    ? "All actions"
                                    : audit_e($action) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="audit-field">
                        <label>Date From</label>
                        <input type="date" name="date_from" value="<?= audit_e(
                            $dateFrom,
                        ) ?>">
                    </div>
                    <div class="audit-field">
                        <label>Date To</label>
                        <input type="date" name="date_to" value="<?= audit_e(
                            $dateTo,
                        ) ?>">
                    </div>
                    <div class="d-flex gap-2">
                        <button class="audit-filter-btn" type="submit"><i class="fa-solid fa-filter"></i> Filter</button>
                        <a class="audit-reset" href="<?= audit_e(
                            ADMIN_URL,
                        ) ?>/audit_log.php"><i class="fa-solid fa-rotate-left"></i> Reset</a>
                    </div>
                </form>
            </section>

            <section class="audit-panel">
                <div class="audit-table-wrap">
                <div class="table-responsive">
                    <table class="table audit-table align-middle">
                        <thead>
                            <tr>
                                <th>Time</th>
                                <th>Actor</th>
                                <th>Role</th>
                                <th>Action</th>
                                <th>Table</th>
                                <th>Details</th>
                            </tr>
                        </thead>
                        <tbody id="auditLogBody">
                            <?php if (!$logs): ?>
                            <tr><td colspan="6" class="text-center py-4">No audit records matched the selected filters.</td></tr>
                            <?php else: foreach ($logs as $log): echo audit_render_row($log);
                            endforeach;
                            endif; ?>
                        </tbody>
                    </table>
                </div>
                </div>

                <div id="auditPaginationWrap">
                <div class="audit-pagination">
                    <span>Page <?= (int) $page ?> of <?= (int) $totalPages ?> &middot; <?= (int) $totalRows ?> records</span>
                    <div class="audit-pagination-links">
                        <a class="<?= $page <= 1
                            ? "disabled"
                            : "" ?>" href="<?= audit_e(
    ADMIN_URL,
) ?>/audit_log.php?<?= audit_e(
    http_build_query($queryBase + ["page" => max(1, $page - 1)]),
) ?>"><i class="fa-solid fa-chevron-left"></i> Previous</a>
                        <a class="<?= $page >= $totalPages
                            ? "disabled"
                            : "" ?>" href="<?= audit_e(
    ADMIN_URL,
) ?>/audit_log.php?<?= audit_e(
    http_build_query($queryBase + ["page" => min($totalPages, $page + 1)]),
) ?>">Next <i class="fa-solid fa-chevron-right"></i></a>
                    </div>
                </div>
                </div>
            </section>
        </main>
    </div>

    <div class="modal fade" id="auditDetailsModal" tabindex="-1" aria-labelledby="auditDetailsTitle" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header audit-modal-header">
                    <h5 class="modal-title" id="auditDetailsTitle"><i class="fa-solid fa-clipboard-list audit-modal-title-icon"></i>Audit Log Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="audit-meta-strip">
                        <div class="audit-meta-chip">
                            <i class="fa-solid fa-user"></i>
                            <div><span>Actor</span><strong id="auditModalActor">-</strong></div>
                        </div>
                        <div class="audit-meta-chip">
                            <i class="fa-solid fa-id-badge"></i>
                            <div><span>Role</span><strong id="auditModalRole">-</strong></div>
                        </div>
                        <div class="audit-meta-chip">
                            <i class="fa-solid fa-bolt"></i>
                            <div><span>Action</span><strong id="auditModalAction">-</strong></div>
                        </div>
                        <div class="audit-meta-chip">
                            <i class="fa-solid fa-clock"></i>
                            <div><span>Time</span><strong id="auditModalTime">-</strong></div>
                        </div>
                        <div class="audit-meta-chip">
                            <i class="fa-solid fa-table"></i>
                            <div><span>Table</span><strong id="auditModalTable">-</strong></div>
                        </div>
                    </div>

                    <div class="audit-summary">
                        <i class="fa-solid fa-circle-info"></i>
                        <span id="auditModalSummary">-</span>
                    </div>

                    <div class="audit-transaction-panel" id="auditTransactionPanel">
                        <div class="audit-transaction-head">
                            <h6><i class="fa-solid fa-receipt"></i> Transaction Details</h6>
                            <span class="audit-transaction-ref" id="auditTransactionReference">-</span>
                        </div>
                        <div class="audit-transaction-grid" id="auditTransactionGrid"></div>
                        <div class="audit-transaction-items" id="auditTransactionItems"></div>
                    </div>

                    <div class="audit-diff-grid" id="auditDiffGrid">
                        <div class="audit-diff-card old" id="auditOldCard">
                            <h6><i class="fa-solid fa-circle-minus"></i> Before</h6>
                            <div class="audit-detail-list" id="auditModalOldValue"></div>
                        </div>
                        <div class="audit-diff-card new" id="auditNewCard">
                            <h6><i class="fa-solid fa-circle-plus"></i> <span id="auditNewCardLabel">After</span></h6>
                            <div class="audit-detail-list" id="auditModalNewValue"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="<?= JS_URL ?>/bootstrap.bundle.min.js"></script>
    <script>
    function toggleSidebar() {
        document.getElementById("sidebar").classList.toggle("collapsed");
    }

    const auditSummaries = {
        LOGIN: 'User logged into the system.',
        LOGOUT: 'User session was ended.',
        PASSWORD_CHANGE: 'User changed their account password.',
        TRANSACTION: 'An e-wallet transaction was processed.',
        MENU_MUTATION: 'A menu item was added or modified.',
        STALL_UPDATE: 'A stall record was updated.',
        USER_IMPORT: 'Student accounts were imported in bulk.',
        MERCHANT_CREATE: 'A merchant account was manually created.',
        USER_ACCOUNT: 'A user account was created or deactivated.',
        MERCHANT_ONBOARDING: 'A merchant application moved through the onboarding pipeline.',
        PRODUCT_RESTRICTION: 'A restricted product entry was flagged or updated.',
        LOGIN_FAILED: 'A login attempt failed due to an incorrect password.'
    };

    function auditSetText(id, value) {
        document.getElementById(id).textContent = value || '-';
    }

    function auditParseJson(value) {
        if (!value || value === 'null') {
            return null;
        }
        try {
            return JSON.parse(value);
        } catch (error) {
            return null;
        }
    }

    function auditLabel(key) {
        return String(key)
            .replace(/_/g, ' ')
            .replace(/\b\w/g, letter => letter.toUpperCase())
            .replace(/\bId\b/g, 'ID')
            .replace(/\bIp\b/g, 'IP')
            .replace(/\bQr\b/g, 'QR');
    }

    function auditValueNode(value) {
        const span = document.createElement('span');
        span.className = 'audit-detail-value';

        if (value === null || value === undefined || value === '') {
            span.textContent = '-';
            return span;
        }

        if (typeof value !== 'object') {
            span.textContent = String(value);
            return span;
        }

        const nested = document.createElement('div');
        nested.className = 'audit-nested';

        if (Array.isArray(value)) {
            if (value.length === 0) {
                nested.textContent = 'No items recorded';
                return nested;
            }
            value.forEach((item, index) => {
                const itemWrap = document.createElement('div');
                itemWrap.className = 'audit-detail-value';
                itemWrap.style.marginBottom = '8px';
                if (item && typeof item === 'object') {
                    const title = document.createElement('strong');
                    title.textContent = `Item ${index + 1}`;
                    itemWrap.appendChild(title);
                    itemWrap.appendChild(auditObjectRows(item));
                } else {
                    itemWrap.textContent = String(item);
                }
                nested.appendChild(itemWrap);
            });
            return nested;
        }

        return auditObjectRows(value);
    }

    function auditObjectRows(object) {
        const wrap = document.createElement('div');
        wrap.className = 'audit-nested';
        Object.entries(object).forEach(([key, value]) => {
            const row = document.createElement('div');
            row.className = 'audit-detail-row';
            const label = document.createElement('span');
            label.className = 'audit-detail-label';
            label.textContent = auditLabel(key);
            row.appendChild(label);
            row.appendChild(auditValueNode(value));
            wrap.appendChild(row);
        });
        return wrap;
    }

    function auditRenderValue(containerId, data) {
        const container = document.getElementById(containerId);
        container.innerHTML = '';

        if (!data || (typeof data === 'object' && !Array.isArray(data) && Object.keys(data).length === 0)) {
            const empty = document.createElement('div');
            empty.className = 'audit-placeholder';
            empty.textContent = 'No data recorded';
            container.appendChild(empty);
            return;
        }

        if (typeof data !== 'object' || Array.isArray(data)) {
            container.appendChild(auditValueNode(data));
            return;
        }

        Object.entries(data).forEach(([key, value]) => {
            const row = document.createElement('div');
            row.className = 'audit-detail-row';
            const label = document.createElement('span');
            label.className = 'audit-detail-label';
            label.textContent = auditLabel(key);
            row.appendChild(label);
            row.appendChild(auditValueNode(value));
            container.appendChild(row);
        });
    }

    function auditMoney(value) {
        const amount = Number(value || 0);
        return 'PHP ' + amount.toLocaleString(undefined, {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
    }

    function auditPersonLabel(person) {
        if (!person || typeof person !== 'object') {
            return '-';
        }
        const parts = [];
        if (person.name) parts.push(person.name);
        if (person.user_id) parts.push('User #' + person.user_id);
        return parts.length ? parts.join(' / ') : '-';
    }

    function auditWalletSmall(person) {
        if (!person || typeof person !== 'object') {
            return '';
        }
        const details = [];
        if (person.email) details.push(person.email);
        if (person.wallet_id) details.push('Wallet #' + person.wallet_id);
        return details.join(' | ');
    }

    function auditTransactionCard(label, value, small) {
        const card = document.createElement('div');
        card.className = 'audit-transaction-card';

        const labelNode = document.createElement('span');
        labelNode.textContent = label;
        card.appendChild(labelNode);

        const valueNode = document.createElement('strong');
        valueNode.textContent = value || '-';
        card.appendChild(valueNode);

        if (small) {
            const smallNode = document.createElement('small');
            smallNode.textContent = small;
            card.appendChild(smallNode);
        }

        return card;
    }

    function auditAppendTransactionCard(grid, label, value, small) {
        if (!value || value === '-') {
            return;
        }
        grid.appendChild(auditTransactionCard(label, value, small));
    }

    function auditRenderTransaction(details) {
        const panel = document.getElementById('auditTransactionPanel');
        const grid = document.getElementById('auditTransactionGrid');
        const itemsWrap = document.getElementById('auditTransactionItems');
        const ref = document.getElementById('auditTransactionReference');

        grid.innerHTML = '';
        itemsWrap.innerHTML = '';
        ref.textContent = '-';
        panel.classList.remove('is-visible');

        if (!details || typeof details !== 'object' || !details.reference_no) {
            return;
        }

        panel.classList.add('is-visible');
        ref.textContent = details.reference_no;

        grid.appendChild(auditTransactionCard('Reference No', details.reference_no));
        grid.appendChild(auditTransactionCard('Type', auditLabel(details.transaction_type || 'transaction')));
        grid.appendChild(auditTransactionCard('Status', auditLabel(details.status || '-')));
        grid.appendChild(auditTransactionCard('Amount', auditMoney(details.amount)));
        grid.appendChild(auditTransactionCard('Created At', details.created_at_label || details.created_at || '-'));
        auditAppendTransactionCard(grid, 'Initiated By', auditPersonLabel(details.initiated_by), auditWalletSmall(details.initiated_by));
        auditAppendTransactionCard(grid, 'Student', auditPersonLabel(details.student), auditWalletSmall(details.student));
        auditAppendTransactionCard(grid, 'Merchant', auditPersonLabel(details.merchant), auditWalletSmall(details.merchant));
        auditAppendTransactionCard(grid, 'Sender', auditPersonLabel(details.sender), auditWalletSmall(details.sender));
        auditAppendTransactionCard(grid, 'Receiver', auditPersonLabel(details.receiver), auditWalletSmall(details.receiver));
        auditAppendTransactionCard(grid, 'Voucher ID', details.voucher_id ? '#' + details.voucher_id : '-');

        if (details.notes) {
            const notes = auditTransactionCard('Notes', details.notes);
            notes.classList.add('audit-transaction-wide');
            grid.appendChild(notes);
        }

        const items = Array.isArray(details.items) ? details.items : [];
        if (items.length) {
            const table = document.createElement('table');
            table.className = 'table table-sm align-middle';
            table.innerHTML = `
                <thead>
                    <tr>
                        <th>Item</th>
                        <th>Qty</th>
                        <th>Price</th>
                        <th>Subtotal</th>
                    </tr>
                </thead>
                <tbody></tbody>
            `;
            const tbody = table.querySelector('tbody');
            items.forEach(item => {
                const qty = Number(item.qty || item.quantity || 0);
                const price = Number(item.price || 0);
                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td></td>
                    <td></td>
                    <td></td>
                    <td></td>
                `;
                tr.children[0].textContent = item.name || item.product_name || ('Item #' + (item.id || '-'));
                tr.children[1].textContent = qty.toLocaleString();
                tr.children[2].textContent = auditMoney(price);
                tr.children[3].textContent = auditMoney(qty * price);
                tbody.appendChild(tr);
            });
            itemsWrap.appendChild(table);
        }
    }

    function auditIsEmptyValue(data) {
        if (data === null || data === undefined || data === '') return true;
        if (typeof data === 'object' && !Array.isArray(data) && Object.keys(data).length === 0) return true;
        if (Array.isArray(data) && data.length === 0) return true;
        return false;
    }

    document.getElementById('auditDetailsModal').addEventListener('show.bs.modal', function(event) {
        const button = event.relatedTarget;
        if (!button) return;

        const action = button.dataset.action || '-';
        auditSetText('auditModalActor', button.dataset.actor);
        auditSetText('auditModalRole', button.dataset.role);
        auditSetText('auditModalAction', action);
        auditSetText('auditModalTime', button.dataset.time);
        auditSetText('auditModalTable', button.dataset.table);
        auditSetText('auditModalSummary', auditSummaries[action] || 'Audit activity was recorded.');

        const oldData = auditParseJson(button.dataset.oldValue);
        const newData = auditParseJson(button.dataset.newValue);
        const hasOldData = !auditIsEmptyValue(oldData);

        const diffGrid = document.getElementById('auditDiffGrid');
        diffGrid.classList.toggle('is-hidden', action === 'TRANSACTION');
        diffGrid.classList.toggle('is-single', !hasOldData);
        document.getElementById('auditOldCard').style.display = hasOldData ? '' : 'none';
        document.getElementById('auditNewCardLabel').textContent = hasOldData ? 'After' : 'Record';

        auditRenderTransaction(auditParseJson(button.dataset.transactionDetails));
        auditRenderValue('auditModalOldValue', oldData);
        auditRenderValue('auditModalNewValue', newData);
    });

    // ── Live refresh: pick up new audit entries without a manual reload ─────────
    const auditLogBody = document.getElementById('auditLogBody');
    const auditPaginationWrap = document.getElementById('auditPaginationWrap');
    let lastAuditTotalRows = null;

    function auditFlashBody() {
        auditLogBody.classList.remove('queue-flash');
        void auditLogBody.offsetWidth;
        auditLogBody.classList.add('queue-flash');
    }

    async function refreshAuditLog() {
        try {
            const params = new URLSearchParams(window.location.search);
            params.set('ajax', '1');
            const res = await fetch(window.location.pathname + '?' + params.toString());
            const data = await res.json();
            if (!data.success) return;

            if (lastAuditTotalRows !== null && data.total_rows === lastAuditTotalRows) return;

            const isFirstLoad = lastAuditTotalRows === null;
            lastAuditTotalRows = data.total_rows;
            auditLogBody.innerHTML = data.rows_html;
            auditPaginationWrap.innerHTML = data.pagination_html;
            if (!isFirstLoad) auditFlashBody();
        } catch (error) {
            // Keep showing the last known rows on a transient network error.
        }
    }

    lastAuditTotalRows = <?= (int) $totalRows ?>;
    setInterval(refreshAuditLog, 5000);
    </script>
</body>
</html>
