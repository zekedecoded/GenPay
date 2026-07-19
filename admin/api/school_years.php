<?php
// ============================================================
//  admin/api/school_years.php
//  School Year Cycles & Balance Lifecycle — per-year balance snapshots,
//  admin-driven year rollover, and graduate account locking.
//
//  No money ever moves here: rollover never calls CirculationEngine, never
//  writes to `transactions`, and never touches student_wallets.balance.
//  school_year_balances is the sole record of a year-end carry-over.
// ============================================================
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../../connection/config.php';
require_once __DIR__ . '/../../connection/pdo.php';
require_once __DIR__ . '/../../connection/app.php';
require_once __DIR__ . '/../../connection/audit_logger.php';

header('Content-Type: application/json; charset=UTF-8');
gjc_require_role(['finance']);
gjc_ensure_audit_table($db);
gjc_ensure_school_year_schema($db);

$action    = trim((string) ($_POST['action'] ?? ''));
$adminId   = gjc_user_id();
$adminRole = gjc_current_role();

function sy_json(array $payload): void
{
    echo json_encode($payload);
    exit;
}

/** One student's identity + wallet + graduation state, or null. */
function sy_fetch_student(PDO $db, int $studentUserId): ?array
{
    $stmt = $db->prepare(
        "SELECT u.userID AS student_user_id,
                TRIM(CONCAT(COALESCE(u.first_name,''), ' ', COALESCE(u.last_name,''))) AS name,
                si.studentID, si.yr_lvl, si.graduated_at,
                sw.id AS wallet_id, sw.balance, sw.is_frozen
           FROM users u
           LEFT JOIN student_info si ON si.userID = u.userID
           LEFT JOIN student_wallets sw ON sw.user_id = u.userID
          WHERE u.userID = ? AND u.roleID = 1
          LIMIT 1"
    );
    $stmt->execute([$studentUserId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        return null;
    }

    return [
        'student_user_id' => (int) $row['student_user_id'],
        'name'            => trim((string) $row['name']),
        'studentID'       => (string) ($row['studentID'] ?? ''),
        'yr_lvl'          => (string) ($row['yr_lvl'] ?? ''),
        'graduated_at'    => $row['graduated_at'],
        'wallet_id'       => $row['wallet_id'] !== null ? (int) $row['wallet_id'] : null,
        'balance'         => (float) ($row['balance'] ?? 0),
        'is_frozen'       => (bool) ($row['is_frozen'] ?? false),
    ];
}

try {
    switch ($action) {

        // ── Read-only: one student's info for the mark-graduate lookup ─
        case 'student_lookup': {
            $studentUserId = (int) ($_POST['student_user_id'] ?? 0);
            $student = $studentUserId > 0 ? sy_fetch_student($db, $studentUserId) : null;
            if (!$student) {
                sy_json(['success' => false, 'message' => 'Student not found.']);
            }
            sy_json(['success' => true, 'student' => $student]);
        }

        // ── Read-only: counts for the rollover confirm dialog ───────────
        case 'preview_rollover': {
            $newYearId = (int) ($_POST['new_school_year_id'] ?? 0);
            if ($newYearId <= 0) {
                sy_json(['success' => false, 'message' => 'Select a school year to roll over into.']);
            }

            $active = $db->query("SELECT id, school_year_name FROM school_years WHERE is_active = 1 LIMIT 1")
                ->fetch(PDO::FETCH_ASSOC);

            $newStmt = $db->prepare("SELECT id, school_year_name FROM school_years WHERE id = ?");
            $newStmt->execute([$newYearId]);
            $new = $newStmt->fetch(PDO::FETCH_ASSOC);
            if (!$new) {
                sy_json(['success' => false, 'message' => 'School year not found.']);
            }

            $walletCount = (int) $db->query("SELECT COUNT(*) FROM student_wallets")->fetchColumn();
            $graduateCount = (int) $db->query(
                "SELECT COUNT(*) FROM student_wallets sw
                   JOIN student_info si ON si.userID = sw.user_id
                  WHERE si.graduated_at IS NOT NULL"
            )->fetchColumn();

            sy_json([
                'success'              => true,
                'old_school_year_id'   => $active ? (int) $active['id'] : null,
                'old_school_year_name' => $active ? (string) $active['school_year_name'] : null,
                'new_school_year_id'   => (int) $new['id'],
                'new_school_year_name' => (string) $new['school_year_name'],
                'wallet_count'         => $walletCount,
                'graduate_count'       => $graduateCount,
                'non_graduate_count'   => $walletCount - $graduateCount,
            ]);
        }

        // ── Create a new (inactive) school year ─────────────────────────
        case 'create_year': {
            $name      = trim((string) ($_POST['school_year_name'] ?? ''));
            $startDate = trim((string) ($_POST['start_date'] ?? '')) ?: null;
            $endDate   = trim((string) ($_POST['end_date'] ?? '')) ?: null;

            if (!gjc_validate_school_year_name($name)) {
                sy_json(['success' => false, 'message' => 'Enter a school year in the format YYYY-YYYY, e.g. 2025-2026.']);
            }

            $dupStmt = $db->prepare("SELECT id FROM school_years WHERE school_year_name = ?");
            $dupStmt->execute([$name]);
            if ($dupStmt->fetchColumn()) {
                sy_json(['success' => false, 'message' => 'That school year already exists.']);
            }

            $stmt = $db->prepare(
                "INSERT INTO school_years (school_year_name, start_date, end_date, is_active) VALUES (?, ?, ?, 0)"
            );
            $stmt->execute([$name, $startDate, $endDate]);
            $newId = (int) $db->lastInsertId();

            logAudit(
                $db, $adminId, $adminRole, 'SCHOOL_YEAR_CREATED', 'school_years',
                null,
                ['school_year_id' => $newId, 'school_year_name' => $name]
            );

            sy_json(['success' => true, 'message' => "School year {$name} created.", 'school_year_id' => $newId]);
        }

        // ── The rollover: claim old off, claim new on, snapshot, never touch wallets ─
        // old_school_year_id may be 0/absent — that's the bootstrap case (no
        // school year has ever been active yet), which just activates the new
        // year and opens balances with nothing to archive out of.
        case 'rollover': {
            $oldYearId = (int) ($_POST['old_school_year_id'] ?? 0);
            $newYearId = (int) ($_POST['new_school_year_id'] ?? 0);

            if ($newYearId <= 0) {
                sy_json(['success' => false, 'message' => 'Select a school year to activate.']);
            }
            if ($oldYearId === $newYearId) {
                sy_json(['success' => false, 'message' => 'The new school year must be different from the current one.']);
            }

            $db->beginTransaction();
            try {
                $oldName = null;
                if ($oldYearId > 0) {
                    $oldNameStmt = $db->prepare("SELECT school_year_name FROM school_years WHERE id = ?");
                    $oldNameStmt->execute([$oldYearId]);
                    $oldName = $oldNameStmt->fetchColumn();
                    if ($oldName === false) {
                        throw new RuntimeException('The current school year record was not found.');
                    }
                }

                $newNameStmt = $db->prepare("SELECT school_year_name FROM school_years WHERE id = ? AND is_active = 0");
                $newNameStmt->execute([$newYearId]);
                $newName = $newNameStmt->fetchColumn();
                if ($newName === false) {
                    throw new RuntimeException('The selected new school year was not found or is already active.');
                }

                if ($oldYearId > 0) {
                    // Atomic test-and-set: only proceeds if the year the admin saw
                    // when the confirm dialog loaded is still the active one.
                    $offStmt = $db->prepare("UPDATE school_years SET is_active = 0 WHERE is_active = 1 AND id = ?");
                    $offStmt->execute([$oldYearId]);
                    if ($offStmt->rowCount() === 0) {
                        throw new RuntimeException('The current school year has changed since you loaded this page. Reload and try again.');
                    }
                } else {
                    // Bootstrap: there must genuinely be no active year right now,
                    // or we'd silently leave two years marked active at once.
                    $stillNone = (int) $db->query("SELECT COUNT(*) FROM school_years WHERE is_active = 1")->fetchColumn();
                    if ($stillNone > 0) {
                        throw new RuntimeException('A school year is already active. Reload and try again.');
                    }
                }

                $onStmt = $db->prepare("UPDATE school_years SET is_active = 1 WHERE id = ? AND is_active = 0");
                $onStmt->execute([$newYearId]);
                if ($onStmt->rowCount() === 0) {
                    throw new RuntimeException('Could not activate the new school year — it may already be active.');
                }

                // Archive every student wallet's ending balance into the closing
                // year (skipped entirely on bootstrap — there's no old year to
                // close out). ON DUPLICATE KEY UPDATE so a student who joined
                // mid-year (no starting_balance row yet) still gets archived.
                $archivedCount = 0;
                if ($oldYearId > 0) {
                    $archiveStmt = $db->prepare(
                        "INSERT INTO school_year_balances
                            (student_user_id, school_year_id, starting_balance, final_ending_balance, archived_at)
                         SELECT sw.user_id, ?, 0, sw.balance, NOW()
                           FROM student_wallets sw
                         ON DUPLICATE KEY UPDATE
                            final_ending_balance = VALUES(final_ending_balance),
                            archived_at = VALUES(archived_at)"
                    );
                    $archiveStmt->execute([$oldYearId]);
                    $archivedCount = $archiveStmt->rowCount();
                }

                // Open the new year from current balances, skipping graduated
                // students. INSERT IGNORE so a retried rollover can't duplicate.
                $openStmt = $db->prepare(
                    "INSERT IGNORE INTO school_year_balances (student_user_id, school_year_id, starting_balance)
                     SELECT sw.user_id, ?, sw.balance
                       FROM student_wallets sw
                       LEFT JOIN student_info si ON si.userID = sw.user_id
                      WHERE si.graduated_at IS NULL"
                );
                $openStmt->execute([$newYearId]);
                $openedCount = $openStmt->rowCount();

                $graduateCount = (int) $db->query(
                    "SELECT COUNT(*) FROM student_wallets sw
                       JOIN student_info si ON si.userID = sw.user_id
                      WHERE si.graduated_at IS NOT NULL"
                )->fetchColumn();

                logAudit(
                    $db, $adminId, $adminRole, 'SCHOOL_YEAR_ROLLOVER', 'school_years',
                    ['school_year_id' => $oldYearId, 'school_year_name' => $oldName],
                    [
                        'school_year_id'    => $newYearId,
                        'school_year_name'  => $newName,
                        'wallets_archived'  => $archivedCount,
                        'wallets_opened'    => $openedCount,
                        'graduates_excluded' => $graduateCount,
                    ]
                );

                $db->commit();
            } catch (Throwable $e) {
                if ($db->inTransaction()) {
                    $db->rollBack();
                }
                throw $e;
            }

            sy_json([
                'success'            => true,
                'message'            => $oldName !== null
                    ? "Rolled over from {$oldName} to {$newName}."
                    : "{$newName} is now the active school year.",
                'wallets_archived'   => $archivedCount,
                'wallets_opened'     => $openedCount,
                'graduates_excluded' => $graduateCount,
            ]);
        }

        // ── Mark one student as graduated + freeze their wallet ─────────
        case 'mark_graduate': {
            $studentUserId = (int) ($_POST['student_user_id'] ?? 0);
            if ($studentUserId <= 0) {
                sy_json(['success' => false, 'message' => 'Student not found.']);
            }

            $before = sy_fetch_student($db, $studentUserId);
            if (!$before) {
                sy_json(['success' => false, 'message' => 'Student not found.']);
            }

            $stmt = $db->prepare(
                "UPDATE student_info SET graduated_at = NOW() WHERE userID = ? AND graduated_at IS NULL"
            );
            $stmt->execute([$studentUserId]);
            if ($stmt->rowCount() === 0) {
                sy_json(['success' => false, 'message' => 'This student is already marked as graduated.']);
            }

            $db->prepare("UPDATE student_wallets SET is_frozen = 1 WHERE user_id = ?")->execute([$studentUserId]);

            logAudit(
                $db, $adminId, $adminRole, 'STUDENT_GRADUATED', 'student_info',
                ['student_user_id' => $studentUserId, 'graduated_at' => null],
                ['student_user_id' => $studentUserId, 'graduated_at' => date('Y-m-d H:i:s'), 'balance_at_graduation' => $before['balance']]
            );

            sy_json([
                'success'     => true,
                'message'     => $before['balance'] > 0
                    ? 'Student marked as graduated. Wallet is frozen — process a withdrawal/encashment for the remaining balance.'
                    : 'Student marked as graduated. Wallet is now frozen.',
                'balance'     => $before['balance'],
                'has_balance' => $before['balance'] > 0,
            ]);
        }

        // ── Bulk graduate everyone at a given year level ─────────────────
        case 'mark_graduate_bulk_year': {
            $yrLvl = trim((string) ($_POST['yr_lvl'] ?? ''));
            if ($yrLvl === '') {
                sy_json(['success' => false, 'message' => 'Year level is required.']);
            }

            $candStmt = $db->prepare(
                "SELECT sw.user_id, sw.balance,
                        TRIM(CONCAT(COALESCE(u.first_name,''), ' ', COALESCE(u.last_name,''))) AS name,
                        si.studentID
                   FROM student_info si
                   JOIN student_wallets sw ON sw.user_id = si.userID
                   JOIN users u ON u.userID = si.userID
                  WHERE si.yr_lvl = ? AND si.graduated_at IS NULL"
            );
            $candStmt->execute([$yrLvl]);
            $candidates = $candStmt->fetchAll(PDO::FETCH_ASSOC);

            if (!$candidates) {
                sy_json(['success' => true, 'message' => 'No eligible (non-graduated) students found at that year level.', 'results' => []]);
            }

            $ids = array_column($candidates, 'user_id');
            $placeholders = implode(',', array_fill(0, count($ids), '?'));

            $db->beginTransaction();
            try {
                $claimStmt = $db->prepare(
                    "UPDATE student_info SET graduated_at = NOW() WHERE userID IN ({$placeholders}) AND graduated_at IS NULL"
                );
                $claimStmt->execute($ids);
                $claimedCount = $claimStmt->rowCount();

                $db->prepare(
                    "UPDATE student_wallets SET is_frozen = 1 WHERE user_id IN ({$placeholders})"
                )->execute($ids);

                logAudit(
                    $db, $adminId, $adminRole, 'STUDENT_GRADUATED', 'student_info',
                    ['yr_lvl' => $yrLvl, 'graduated_at' => null],
                    ['yr_lvl' => $yrLvl, 'graduated_count' => $claimedCount, 'student_user_ids' => $ids]
                );

                $db->commit();
            } catch (Throwable $e) {
                if ($db->inTransaction()) {
                    $db->rollBack();
                }
                throw $e;
            }

            $results = array_map(static fn(array $c): array => [
                'student_user_id' => (int) $c['user_id'],
                'name'            => trim((string) $c['name']),
                'studentID'       => (string) $c['studentID'],
                'balance'         => (float) $c['balance'],
                'has_balance'     => (float) $c['balance'] > 0,
            ], $candidates);

            sy_json([
                'success'         => true,
                'message'         => "Marked {$claimedCount} student(s) at year level {$yrLvl} as graduated.",
                'graduated_count' => $claimedCount,
                'results'         => $results,
            ]);
        }

        // ── One-time mapping of legacy NULL school_year_id rows ──────────
        case 'backfill_legacy': {
            if (gjc_sub_role() !== 'super_admin') {
                sy_json(['success' => false, 'message' => 'Only a super admin can run the legacy backfill.']);
            }

            $years = $db->query(
                "SELECT id, school_year_name, start_date, end_date
                   FROM school_years
                  WHERE start_date IS NOT NULL AND end_date IS NOT NULL
                  ORDER BY start_date ASC"
            )->fetchAll(PDO::FETCH_ASSOC);

            if (!$years) {
                sy_json(['success' => false, 'message' => 'No school years have both a start and end date set — nothing to map against.']);
            }

            $totalUpdated = 0;
            foreach ($years as $year) {
                $stmt = $db->prepare(
                    "UPDATE transactions
                        SET school_year_id = ?
                      WHERE school_year_id IS NULL
                        AND created_at >= ?
                        AND created_at < DATE_ADD(?, INTERVAL 1 DAY)"
                );
                $stmt->execute([$year['id'], $year['start_date'], $year['end_date']]);
                $totalUpdated += $stmt->rowCount();
            }

            logAudit(
                $db, $adminId, $adminRole, 'SY_TXN_BACKFILL', 'transactions',
                null,
                ['years_mapped' => count($years), 'rows_updated' => $totalUpdated]
            );

            sy_json(['success' => true, 'message' => "Backfilled {$totalUpdated} legacy transaction(s) across " . count($years) . ' school year(s).']);
        }

        default:
            sy_json(['success' => false, 'message' => 'Unknown action.']);
    }
} catch (RuntimeException $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    sy_json(['success' => false, 'message' => $e->getMessage()]);
} catch (Throwable $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    sy_json(['success' => false, 'message' => 'A server error occurred.']);
}
