<?php
require_once __DIR__ . '/../connection/config.php';
require_once __DIR__ . '/../connection/pdo.php';
require_once __DIR__ . '/../connection/app.php';
require_once __DIR__ . '/../connection/audit_logger.php';

gjc_require_role(['finance']);
gjc_ensure_audit_table($db);

$currentUser = gjc_current_user($db);
$currentPage = 'maintenance';

function maintenance_e($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function maintenance_ensure_student_registry(PDO $db): void
{
    $db->exec(
        "CREATE TABLE IF NOT EXISTS imported_student_registry (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            import_batch_id VARCHAR(14) NOT NULL,
            user_id INT NULL,
            student_id_number VARCHAR(80) NOT NULL,
            first_name VARCHAR(100) NOT NULL,
            last_name VARCHAR(100) NOT NULL,
            course_program VARCHAR(255) NOT NULL,
            email VARCHAR(255) NOT NULL,
            phone_number VARCHAR(40) NULL,
            import_status ENUM('imported', 'duplicate', 'failed') NOT NULL,
            message VARCHAR(255) NULL,
            imported_by INT NOT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_import_batch (import_batch_id),
            INDEX idx_import_student (student_id_number),
            INDEX idx_import_email (email),
            INDEX idx_import_status (import_status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci"
    );
}

function maintenance_parse_student_csv(string $path): array
{
    $expected = ['student_id_number', 'first_name', 'last_name', 'course_program', 'email', 'phone_number'];
    $handle = fopen($path, 'r');
    if (!$handle) {
        throw new RuntimeException('Unable to read the uploaded CSV file.');
    }

    $header = fgetcsv($handle);
    if (!$header) {
        fclose($handle);
        throw new RuntimeException('The CSV file is empty.');
    }

    $normalizedHeader = array_map(
        static fn($value) => strtolower(trim((string) $value)),
        $header
    );
    if ($normalizedHeader !== $expected) {
        fclose($handle);
        throw new RuntimeException('CSV header must be: ' . implode(', ', $expected));
    }

    $rows = [];
    $rowNumber = 1;
    while (($data = fgetcsv($handle)) !== false) {
        $rowNumber++;
        if (count(array_filter($data, static fn($value) => trim((string) $value) !== '')) === 0) {
            continue;
        }

        $data = array_pad($data, count($expected), '');
        $row = array_combine($expected, array_slice($data, 0, count($expected)));
        foreach ($row as $key => $value) {
            $row[$key] = trim((string) $value);
        }
        $row['row_number'] = $rowNumber;
        $rows[] = $row;
    }

    fclose($handle);

    if (!$rows) {
        throw new RuntimeException('No student rows were found in the CSV file.');
    }

    return $rows;
}

function maintenance_phone_digits(string $phone): string
{
    $digits = preg_replace('/\D+/', '', $phone) ?: '0';
    return substr($digits, 0, 18);
}

function maintenance_course_id(PDO $db, string $courseProgram): int
{
    $courseProgram = trim($courseProgram);
    $stmt = $db->prepare(
        "SELECT courseID
           FROM course
          WHERE UPPER(course_code) = UPPER(?)
             OR LOWER(course_name) = LOWER(?)
          LIMIT 1"
    );
    $stmt->execute([$courseProgram, $courseProgram]);
    $courseId = (int) $stmt->fetchColumn();
    if ($courseId > 0) {
        return $courseId;
    }

    $code = strtoupper(substr(preg_replace('/[^A-Za-z0-9-]+/', '', $courseProgram) ?: 'COURSE', 0, 255));
    $db->prepare("INSERT INTO course (course_code, course_name) VALUES (?, ?)")
        ->execute([$code, substr($courseProgram, 0, 255)]);

    return (int) $db->lastInsertId();
}

function maintenance_insert_student_user(PDO $db, array $row): int
{
    $columns = gjc_table_columns($db, 'users');
    $studentId = (string) $row['student_id_number'];
    $payload = [
        'last_name' => $row['last_name'],
        'first_name' => $row['first_name'],
        'middle_name' => '',
        'suffix' => '',
        'contact_number' => maintenance_phone_digits((string) $row['phone_number']),
        'phone' => $row['phone_number'],
        'email' => $row['email'],
        'roleID' => 1,
        'sub_role' => 'student',
        'school_id' => $studentId,
        'password' => password_hash($studentId, PASSWORD_DEFAULT),
        'profile_img' => '',
        'force_password_change' => 1,
        'is_first_login' => 1,
        'password_changed' => 0,
        'temp_password' => $studentId,
    ];

    $insert = [];
    $values = [];
    foreach ($payload as $column => $value) {
        if (in_array($column, $columns, true)) {
            $insert[] = $column;
            $values[] = $value;
        }
    }

    $sql = 'INSERT INTO users (' . implode(', ', $insert) . ') VALUES (' . implode(', ', array_fill(0, count($insert), '?')) . ')';
    $stmt = $db->prepare($sql);
    $stmt->execute($values);

    return (int) $db->lastInsertId();
}

function maintenance_table_columns_fresh(PDO $db, string $table): array
{
    $stmt = $db->prepare(
        "SELECT COLUMN_NAME FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?"
    );
    $stmt->execute([$table]);
    return $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
}

function maintenance_ensure_merchant_bypass_schema(PDO $db): void
{
    $userColumns = maintenance_table_columns_fresh($db, 'users');
    if (!in_array('username', $userColumns, true)) {
        $db->exec("ALTER TABLE users ADD COLUMN username VARCHAR(80) NULL AFTER email");
    }

    if (gjc_table_exists($db, 'merchant')) {
        $merchantColumns = maintenance_table_columns_fresh($db, 'merchant');
        if (!in_array('notes', $merchantColumns, true)) {
            $db->exec("ALTER TABLE merchant ADD COLUMN notes TEXT NULL");
        }
    }
}

function maintenance_insert_merchant_user(PDO $db, array $data): int
{
    $columns = maintenance_table_columns_fresh($db, 'users');
    $payload = [
        'last_name' => $data['last_name'],
        'first_name' => $data['first_name'],
        'middle_name' => $data['middle_name'] ?? '',
        'suffix' => $data['suffix'] ?? '',
        'contact_number' => maintenance_phone_digits((string) $data['phone']),
        'phone' => $data['phone'],
        'email' => $data['email'],
        'roleID' => 2,
        'sub_role' => 'merchant_admin',
        'password' => password_hash((string) $data['temp_password'], PASSWORD_BCRYPT),
        'profile_img' => '',
        'force_password_change' => 1,
        'is_first_login' => 1,
        'password_changed' => 0,
        'temp_password' => $data['temp_password'],
    ];

    $insert = [];
    $values = [];
    foreach ($payload as $column => $value) {
        if (in_array($column, $columns, true)) {
            $insert[] = $column;
            $values[] = $value;
        }
    }

    $sql = 'INSERT INTO users (' . implode(', ', $insert) . ') VALUES (' . implode(', ', array_fill(0, count($insert), '?')) . ')';
    $stmt = $db->prepare($sql);
    $stmt->execute($values);

    return (int) $db->lastInsertId();
}

function maintenance_insert_merchant_record(PDO $db, int $userId, string $businessName, string $notes, ?string $stallId = null): int
{
    if (!gjc_table_exists($db, 'merchant')) {
        return 0;
    }

    $columns = maintenance_table_columns_fresh($db, 'merchant');
    $payload = [
        'userID' => $userId,
        'stall_name' => $businessName,
        'stall_id' => ($stallId !== null && $stallId !== '') ? $stallId : null,
        'operational_status' => 'active',
        'notes' => $notes !== '' ? $notes : null,
    ];

    $insert = [];
    $values = [];
    foreach ($payload as $column => $value) {
        if (in_array($column, $columns, true)) {
            $insert[] = $column;
            $values[] = $value;
        }
    }

    if (!$insert) {
        return 0;
    }

    $sql = 'INSERT INTO merchant (' . implode(', ', $insert) . ') VALUES (' . implode(', ', array_fill(0, count($insert), '?')) . ')';
    $stmt = $db->prepare($sql);
    $stmt->execute($values);

    return (int) $db->lastInsertId();
}

function maintenance_temp_password(int $length = 10): string
{
    // Ambiguous characters (0/O, 1/l/I) left out so the password is easy to read aloud.
    $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnpqrstuvwxyz23456789';
    $max = strlen($alphabet) - 1;
    $out = '';
    for ($i = 0; $i < $length; $i++) {
        $out .= $alphabet[random_int(0, $max)];
    }
    return $out;
}

/**
 * Records an admin-created merchant as an already-approved stall application, so
 * it matches a merchant onboarded through the public form + approval. This is the
 * only home for fields like sex / proprietor_name (no column on users/merchant).
 */
function maintenance_insert_approved_application(PDO $db, array $data, int $userId, int $adminId): int
{
    if (!gjc_table_exists($db, 'stall_applications')) {
        return 0;
    }

    $proprietorName = trim(implode(' ', array_filter(
        [$data['first_name'], $data['middle_name'], $data['last_name'], $data['suffix']],
        fn($part) => $part !== ''
    )));
    $stallId = $data['stall_id'] !== '' ? $data['stall_id'] : null;

    // Address + document columns are left to their '' defaults (not collected here).
    $stmt = $db->prepare(
        "INSERT INTO stall_applications
            (business_name, proprietor_name, first_name, middle_name, last_name, suffix, sex,
             contact_number, email, preferred_stall_id, stall_id,
             profile_picture, business_permit, sanitary_permit, gjc_requirements, clearance,
             terms_accepted, status, current_step, reviewed_by, reviewed_at,
             merchant_user_id, temp_password_plain)
         VALUES (?, ?, ?, ?, ?, ?, ?,  ?, ?, ?, ?,  '', '', '', '', '',  1, 'active', 5, ?, NOW(),  ?, ?)"
    );
    $stmt->execute([
        $data['business_name'],
        $proprietorName,
        $data['first_name'],
        $data['middle_name'] !== '' ? $data['middle_name'] : null,
        $data['last_name'],
        $data['suffix'] !== '' ? $data['suffix'] : null,
        $data['sex'],
        $data['phone'],
        $data['email'],
        $stallId,
        $stallId,
        $adminId,
        $userId,
        $data['temp_password'],
    ]);

    return (int) $db->lastInsertId();
}

function maintenance_ensure_restricted_products_schema(PDO $db): void
{
    $db->exec(
        "CREATE TABLE IF NOT EXISTS restricted_products (
            id          INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            product_name VARCHAR(120) NOT NULL,
            category    VARCHAR(60)  NOT NULL DEFAULT 'general',
            reason      VARCHAR(255) NOT NULL,
            match_type  ENUM('exact','contains') NOT NULL DEFAULT 'contains',
            is_active   TINYINT(1)   NOT NULL DEFAULT 1,
            flagged_by  INT UNSIGNED NOT NULL DEFAULT 0,
            created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci"
    );
}

maintenance_ensure_student_registry($db);
maintenance_ensure_merchant_bypass_schema($db);
maintenance_ensure_restricted_products_schema($db);

$previewRows = $_SESSION['bulk_student_import_rows'] ?? [];
$previewFileName = (string) ($_SESSION['bulk_student_import_filename'] ?? '');
$importError = '';
$importSummary = null;
$merchantError = '';
$merchantSuccess = null;

$restrictedProducts = $db->query(
    "SELECT * FROM restricted_products ORDER BY is_active DESC, category ASC, product_name ASC"
)->fetchAll(PDO::FETCH_ASSOC);

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $action = (string) ($_POST['student_import_action'] ?? '');
    $merchantAction = (string) ($_POST['merchant_bypass_action'] ?? '');

    try {
        maintenance_ensure_student_registry($db);

        if ($action === 'preview') {
            if (empty($_FILES['students_csv']) || (int) $_FILES['students_csv']['error'] !== UPLOAD_ERR_OK) {
                throw new RuntimeException('Please choose a valid CSV file.');
            }

            $previewRows = maintenance_parse_student_csv((string) $_FILES['students_csv']['tmp_name']);
            $_SESSION['bulk_student_import_rows'] = $previewRows;
            $_SESSION['bulk_student_import_filename'] = (string) ($_FILES['students_csv']['name'] ?? 'students.csv');
            $previewFileName = (string) $_SESSION['bulk_student_import_filename'];
        }

        if ($action === 'clear_preview') {
            unset($_SESSION['bulk_student_import_rows'], $_SESSION['bulk_student_import_filename']);
            $previewRows = [];
            $previewFileName = '';
        }

        if ($action === 'import') {
            $rows = $_SESSION['bulk_student_import_rows'] ?? [];
            if (!$rows) {
                throw new RuntimeException('Upload and preview a CSV before importing.');
            }

            $batchId = date('YmdHis');
            $summary = [
                'batch_id' => $batchId,
                'total_rows' => count($rows),
                'imported' => 0,
                'duplicates' => 0,
                'failed' => 0,
            ];
            $createdUserIds = [];
            $seenStudentIds = [];
            $seenEmails = [];

            $emailCheck = $db->prepare("SELECT userID FROM users WHERE LOWER(email) = LOWER(?) LIMIT 1");
            $studentCheck = $db->prepare("SELECT userID FROM student_info WHERE studentID = ? LIMIT 1");
            $studentInfoInsert = $db->prepare(
                "INSERT INTO student_info (userID, studentID, yr_lvl, courseID) VALUES (?, ?, '1', ?)"
            );
            $registryInsert = $db->prepare(
                "INSERT INTO imported_student_registry
                    (import_batch_id, user_id, student_id_number, first_name, last_name,
                     course_program, email, phone_number, import_status, message, imported_by)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
            );

            $db->beginTransaction();
            foreach ($rows as $row) {
                $message = '';
                $status = 'failed';
                $userId = null;
                $studentId = (string) $row['student_id_number'];
                $email = strtolower((string) $row['email']);

                $db->exec('SAVEPOINT student_import_row');
                try {
                    if ($studentId === '' || $row['first_name'] === '' || $row['last_name'] === '' || $row['course_program'] === '' || $email === '') {
                        throw new RuntimeException('Required fields are missing.');
                    }
                    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                        throw new RuntimeException('Invalid email address.');
                    }

                    $isDuplicate = isset($seenStudentIds[$studentId]) || isset($seenEmails[$email]);
                    if (!$isDuplicate) {
                        $emailCheck->execute([$email]);
                        $isDuplicate = (bool) $emailCheck->fetchColumn();
                    }
                    if (!$isDuplicate) {
                        $studentCheck->execute([$studentId]);
                        $isDuplicate = (bool) $studentCheck->fetchColumn();
                    }

                    if ($isDuplicate) {
                        $status = 'duplicate';
                        $message = 'Skipped duplicate student ID or email.';
                        $summary['duplicates']++;
                    } else {
                        $courseId = maintenance_course_id($db, (string) $row['course_program']);
                        $userId = maintenance_insert_student_user($db, $row);
                        $studentInfoInsert->execute([$userId, $studentId, $courseId]);
                        gjc_student_wallet($db, $userId);

                        $status = 'imported';
                        $message = 'Student account imported.';
                        $summary['imported']++;
                        $createdUserIds[] = $userId;
                    }
                    $db->exec('RELEASE SAVEPOINT student_import_row');
                } catch (Throwable $rowError) {
                    try {
                        $db->exec('ROLLBACK TO SAVEPOINT student_import_row');
                        $db->exec('RELEASE SAVEPOINT student_import_row');
                    } catch (Throwable) {
                    }
                    if ($status !== 'duplicate') {
                        $status = 'failed';
                        $message = substr($rowError->getMessage(), 0, 255);
                        $summary['failed']++;
                    }
                }

                $seenStudentIds[$studentId] = true;
                if ($email !== '') {
                    $seenEmails[$email] = true;
                }

                $registryInsert->execute([
                    $batchId,
                    $userId,
                    $studentId,
                    (string) $row['first_name'],
                    (string) $row['last_name'],
                    (string) $row['course_program'],
                    (string) $row['email'],
                    (string) $row['phone_number'],
                    $status,
                    $message,
                    (int) $currentUser['id'],
                ]);
            }
            $db->commit();

            logAudit(
                $db,
                (int) $currentUser['id'],
                gjc_current_role(),
                'USER_IMPORT',
                'imported_student_registry',
                null,
                [
                    'import_batch_id' => $summary['batch_id'],
                    'total_rows' => $summary['total_rows'],
                    'imported' => $summary['imported'],
                    'duplicates' => $summary['duplicates'],
                    'failed' => $summary['failed'],
                    'created_user_ids' => $createdUserIds,
                ]
            );

            $importSummary = $summary;
            unset($_SESSION['bulk_student_import_rows'], $_SESSION['bulk_student_import_filename']);
            $previewRows = [];
            $previewFileName = '';
        }

        if ($merchantAction === 'create') {
            $merchantData = [
                'first_name' => trim((string) ($_POST['merchant_first_name'] ?? '')),
                'middle_name' => trim((string) ($_POST['merchant_middle_name'] ?? '')),
                'last_name' => trim((string) ($_POST['merchant_last_name'] ?? '')),
                'suffix' => trim((string) ($_POST['merchant_suffix'] ?? '')),
                'sex' => strtolower(trim((string) ($_POST['merchant_sex'] ?? ''))),
                'email' => strtolower(trim((string) ($_POST['merchant_email'] ?? ''))),
                'phone' => trim((string) ($_POST['merchant_phone'] ?? '')),
                'business_name' => trim((string) ($_POST['merchant_business_name'] ?? '')),
                'stall_id' => trim((string) ($_POST['merchant_stall_id'] ?? '')),
                'notes' => trim((string) ($_POST['merchant_notes'] ?? '')),
                'temp_password' => maintenance_temp_password(),
            ];

            if ($merchantData['first_name'] === '' || $merchantData['last_name'] === '' || $merchantData['email'] === '' || $merchantData['phone'] === '' || $merchantData['business_name'] === '') {
                throw new RuntimeException('First name, last name, email, phone, and business name are required.');
            }
            if (!filter_var($merchantData['email'], FILTER_VALIDATE_EMAIL)) {
                throw new RuntimeException('Please enter a valid merchant email address.');
            }
            if (!in_array($merchantData['sex'], ['male', 'female'], true)) {
                throw new RuntimeException('Please select the proprietor\'s sex.');
            }

            maintenance_ensure_merchant_bypass_schema($db);

            $emailCheck = $db->prepare("SELECT userID FROM users WHERE LOWER(email) = LOWER(?) LIMIT 1");
            $emailCheck->execute([$merchantData['email']]);
            if ($emailCheck->fetchColumn()) {
                throw new RuntimeException('A user with this email already exists.');
            }

            $db->beginTransaction();
            try {
                $newMerchantUserId = maintenance_insert_merchant_user($db, $merchantData);
                $newMerchantId = maintenance_insert_merchant_record(
                    $db,
                    $newMerchantUserId,
                    $merchantData['business_name'],
                    $merchantData['notes'],
                    $merchantData['stall_id'] !== '' ? $merchantData['stall_id'] : null
                );
                gjc_merchant_wallet($db, $newMerchantUserId);

                if ($merchantData['stall_id'] !== '') {
                    $occupyStall = $db->prepare(
                        "UPDATE stalls
                            SET status = 'occupied', merchant_id = ?, pending_expires_at = NULL
                          WHERE stall_id = ? AND status = 'vacant'"
                    );
                    // stalls.merchant_id references merchant.merchantID (not the user id).
                    $occupyStall->execute([$newMerchantId, $merchantData['stall_id']]);
                    if ($occupyStall->rowCount() === 0) {
                        throw new RuntimeException('That stall is no longer available — please pick another.');
                    }
                }

                maintenance_insert_approved_application($db, $merchantData, $newMerchantUserId, (int) $currentUser['id']);

                $db->commit();
            } catch (Throwable $merchantCreateError) {
                $db->rollBack();
                throw $merchantCreateError;
            }

            logAudit(
                $db,
                (int) $currentUser['id'],
                gjc_current_role(),
                'MERCHANT_CREATE',
                'users',
                null,
                [
                    'created_user_id' => $newMerchantUserId,
                    'merchant_id' => $newMerchantId,
                    'first_name' => $merchantData['first_name'],
                    'last_name' => $merchantData['last_name'],
                    'sex' => $merchantData['sex'],
                    'email' => $merchantData['email'],
                    'phone' => $merchantData['phone'],
                    'business_name' => $merchantData['business_name'],
                    'stall_id' => $merchantData['stall_id'] !== '' ? $merchantData['stall_id'] : null,
                    'notes' => $merchantData['notes'],
                    'roleID' => 2,
                    'sub_role' => 'merchant_admin',
                    'forced_password_change' => true,
                    'email_sent' => false,
                ]
            );

            $merchantSuccess = [
                'user_id' => $newMerchantUserId,
                'merchant_id' => $newMerchantId,
                'business_name' => $merchantData['business_name'],
                'email' => $merchantData['email'],
                'temp_password' => $merchantData['temp_password'],
            ];
        }
    } catch (Throwable $error) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        if ($merchantAction === 'create') {
            $merchantError = $error->getMessage();
        } else {
            $importError = $error->getMessage();
        }
    }
}

$vacantStalls = $db->query(
    "SELECT stall_id, label, monthly_rate FROM stalls WHERE status = 'vacant' ORDER BY label ASC"
)->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="icon" type="image/png" sizes="32x32" href="<?= ICONS_URL ?>/gp_logo.png">
    <link rel="icon" type="image/png" sizes="192x192" href="<?= ICONS_URL ?>/gp_logo.png">
    <link rel="apple-touch-icon" sizes="180x180" href="<?= ICONS_URL ?>/gp_logo.png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Maintenance | GenPay Admin</title>
    <link rel="stylesheet" href="<?= CSS_URL ?>/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer">
    <link rel="stylesheet" href="<?= CSS_URL ?>/admin.css?v=4">
    <link rel="stylesheet" href="<?= CSS_URL ?>/responsive.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.8/css/dataTables.bootstrap5.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        /* ── Layout ─────────────────────────────────────────────────────────── */
        .maintenance-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 14px;
        }

        .maintenance-placeholder {
            background: #fff;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            padding: 20px;
        }

        .maintenance-placeholder .section-tag {
            display: inline-block;
            background: #f1f5f9;
            color: #64748b;
            font-size: 10px;
            font-weight: 700;
            padding: 2px 8px;
            border-radius: 6px;
            text-transform: uppercase;
            letter-spacing: .4px;
            margin-bottom: 8px;
        }

        .maintenance-placeholder h3 {
            color: #111827;
            font-size: 15px;
            font-weight: 700;
            margin: 0 0 4px;
        }

        .maintenance-help {
            margin: 4px 0 14px;
            color: #6b7280;
            font-size: 12px;
            line-height: 1.55;
        }

        /* ── Form elements ───────────────────────────────────────────────────── */
        .student-import-form label,
        .merchant-bypass-form label {
            display: block;
            color: var(--gjc-ink);
            font-size: 12px;
            font-weight: 600;
            margin-bottom: 6px;
        }

        .student-import-form input[type="file"] {
            width: 100%;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            background: #f9fafb;
            padding: 8px 10px;
            font-size: 13px;
        }

        .merchant-bypass-form input,
        .merchant-bypass-form textarea,
        .merchant-bypass-form select {
            width: 100%;
            border: 1px solid var(--gjc-line);
            border-radius: var(--gjc-radius);
            background: #fff;
            padding: 10px 14px;
            font-size: 14px;
            color: var(--gjc-ink);
            outline: none;
            box-sizing: border-box;
            transition: border-color .15s, box-shadow .15s;
        }
        .merchant-bypass-form input:focus,
        .merchant-bypass-form textarea:focus,
        .merchant-bypass-form select:focus {
            border-color: var(--gjc-green-700);
            box-shadow: 0 0 0 3px rgba(17, 106, 56, 0.14);
        }
        .merchant-bypass-form select {
            appearance: none;
            -webkit-appearance: none;
            cursor: pointer;
            padding-right: 38px;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='14' height='14' viewBox='0 0 24 24' fill='none' stroke='%2366756c' stroke-width='3' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='6 9 12 15 18 9'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 13px center;
        }

        .merchant-bypass-form textarea { min-height: 80px; resize: vertical; }

        .maintenance-form-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 12px;
        }
        .maintenance-form-grid .full { grid-column: 1 / -1; }

        /* Grouped field sections for the merchant form */
        .mbf-section { margin-bottom: 18px; }
        .mbf-section-eyebrow {
            display: flex;
            align-items: center;
            gap: 7px;
            font-size: 11px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.6px;
            color: var(--gjc-green-700);
            margin-bottom: 12px;
            padding-bottom: 7px;
            border-bottom: 1px solid var(--gjc-line);
        }
        .mbf-section-eyebrow i { font-size: 12px; color: var(--gjc-green-600); }

        /* ── Buttons ─────────────────────────────────────────────────────────── */
        .maintenance-btn-row {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-top: 12px;
        }

        .maintenance-btn {
            border: 0;
            border-radius: 8px;
            padding: 8px 16px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            transition: opacity .15s;
        }
        .maintenance-btn:hover { opacity: .88; }
        .maintenance-btn.primary { background: var(--gjc-success); color: #fff; }
        .maintenance-btn.warning { background: var(--gjc-warning); color: #fff; }
        .maintenance-btn.muted   { background: #f1f5f9; color: #374151; border: 1px solid #e5e7eb; }

        /* ── Alerts ──────────────────────────────────────────────────────────── */
        .maintenance-alert {
            border-radius: 8px;
            padding: 10px 14px;
            margin: 12px 0;
            font-size: 13px;
            font-weight: 500;
            line-height: 1.5;
        }
        .maintenance-alert.success { background: #f0fdf4; border: 1px solid #bbf7d0; color: var(--gjc-success); }
        .maintenance-alert.error   { background: var(--gjc-danger-bg); border: 1px solid var(--gjc-danger-border); color: var(--gjc-danger); }

        /* ── Import summary ──────────────────────────────────────────────────── */
        .import-summary-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 8px;
            margin-top: 10px;
        }
        .import-summary-card {
            border-radius: 8px;
            background: #f9fafb;
            border: 1px solid #e5e7eb;
            padding: 8px 12px;
        }
        .import-summary-card span {
            display: block;
            color: #6b7280;
            font-size: 10px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: .3px;
        }
        .import-summary-card strong { color: #111827; font-size: 18px; font-weight: 700; }

        /* ── Student preview table ───────────────────────────────────────────── */
        .student-preview {
            margin-top: 14px;
            border: 1px solid #e5e7eb;
            border-radius: 10px;
            overflow: hidden;
        }
        .student-preview-head {
            display: flex;
            justify-content: space-between;
            gap: 10px;
            padding: 8px 12px;
            background: #fafafa;
            border-bottom: 1px solid #e5e7eb;
            color: #374151;
            font-size: 12px;
            font-weight: 600;
        }
        .student-preview table { margin: 0; font-size: 12px; }
        .student-preview th { color: #6b7280; font-size: 10px; font-weight: 600; text-transform: uppercase; }

        /* ── Responsive ──────────────────────────────────────────────────────── */
        @media (max-width: 900px) {
            .maintenance-grid          { grid-template-columns: 1fr; }
            .import-summary-grid       { grid-template-columns: repeat(2, minmax(0, 1fr)); }
            .maintenance-form-grid     { grid-template-columns: 1fr; }
        }

        /* ── Prohibited Products ───────────────────────────────────────────── */
        .rp-section { margin-top: 14px; }
        .rp-section-header {
            display: flex; align-items: center; justify-content: space-between;
            flex-wrap: wrap; gap: 12px; margin-bottom: 20px;
        }
        .rp-section-title { display: flex; align-items: center; gap: 8px; }
        .rp-section-title h3 { margin: 0; font-size: 15px; font-weight: 700; color: #111827; }
        .rp-count-badge {
            background: var(--gjc-danger-bg); color: var(--gjc-danger); font-size: 11px; font-weight: 700;
            padding: 3px 10px; border-radius: 99px; letter-spacing: .4px;
        }
        .rp-add-btn {
            display: inline-flex; align-items: center; gap: 7px;
            background: var(--gjc-alert); color: #fff; border: none; border-radius: 10px;
            padding: 9px 18px; font-size: 13px; font-weight: 700; cursor: pointer;
            transition: background .15s;
        }
        .rp-add-btn:hover { background: var(--gjc-danger); }

        .rp-empty {
            text-align: center; padding: 48px 24px;
            background: #fff; border-radius: 16px;
            border: 2px dashed #fecaca; color: #9ca3af;
        }
        .rp-empty i { font-size: 40px; color: var(--gjc-danger-border); margin-bottom: 12px; }
        .rp-empty p { font-size: 14px; margin: 0; }

        .rp-tag {
            font-size: 10px; font-weight: 700; padding: 2px 8px;
            border-radius: 99px; text-transform: uppercase; letter-spacing: .4px;
        }
        .rp-tag--match-exact { background: #fef3c7; color: var(--gjc-warning); }
        .rp-tag--match-contains { background: #e0e7ff; color: var(--gjc-info); }

        .rp-status-badge {
            display: inline-block; font-size: 10px; font-weight: 800; letter-spacing: .8px;
            text-transform: uppercase; padding: 3px 10px; border-radius: 99px;
        }
        .rp-status--banned { background: var(--gjc-danger-bg); color: var(--gjc-danger); }
        .rp-status--lifted { background: #f1f5f9; color: #64748b; }

        .rp-table { font-size: 13px; border-collapse: separate; border-spacing: 0; }
        .rp-table thead th {
            font-size: 11px; font-weight: 700; text-transform: uppercase;
            letter-spacing: .5px; color: #6b7280; border-bottom: 2px solid #f3f4f6;
            padding: 10px 12px; white-space: nowrap;
        }
        .rp-table tbody tr { transition: background .12s; }
        .rp-table tbody tr:hover { background: #fafafa; }
        .rp-table tbody td { padding: 12px 12px; border-bottom: 1px solid #f3f4f6; vertical-align: middle; }

        .rp-toggle-btn {
            padding: 5px 12px; font-size: 11px; font-weight: 700;
            border-radius: 8px; border: none; cursor: pointer; transition: background .15s;
        }
        .rp-toggle-btn--ban { background: var(--gjc-success-bg); color: var(--gjc-green-600); }
        .rp-toggle-btn--ban:hover { background: #bbf7d0; }
        .rp-toggle-btn--lift { background: var(--gjc-danger-bg); color: var(--gjc-danger); }
        .rp-toggle-btn--lift:hover { background: #fecaca; }
        .rp-remove-btn {
            width: 28px; height: 28px; border: none; border-radius: 8px;
            background: #f1f5f9; color: #94a3b8; cursor: pointer; font-size: 12px;
            display: inline-flex; align-items: center; justify-content: center; transition: all .15s;
            vertical-align: middle;
        }
        .rp-remove-btn:hover { background: var(--gjc-danger-bg); color: var(--gjc-alert); }

        /* Flag modal */
        .rp-modal-field { margin-bottom: 14px; }
        .rp-modal-label { display: block; font-size: 12px; font-weight: 700; color: #374151; margin-bottom: 5px; }
        .rp-modal-input {
            width: 100%; padding: 9px 12px; border: 1.5px solid #e5e7eb;
            border-radius: 8px; font-size: 13px; outline: none; transition: border-color .15s;
        }
        .rp-modal-input:focus { border-color: var(--gjc-alert); }
        .rp-modal-alert { font-size: 13px; padding: 8px 12px; border-radius: 8px; margin-top: 10px; }

    </style>
</head>
<body>
<div class="admin-layout">
    <?php require __DIR__ . '/../includes/partials/sidebar_admin.php'; ?>

    <main class="admin-main">
        <header class="topbar">
            <button class="menu-btn" onclick="toggleSidebar()"><i class="fa-solid fa-bars"></i></button>
            <div>
                <h1>Maintenance</h1>
                <p>Administrative maintenance workspace.</p>
            </div>
            <div class="admin-user">
                <span><?= gjc_e($currentUser['name']) ?></span>
                <div class="avatar">
                    <i class="fa-solid fa-user-tie"></i>
                </div>
            </div>
        </header>

        <section class="maintenance-grid">
            <div class="maintenance-placeholder">
                <span class="section-tag">Section A</span>
                <h3>Bulk Student Import</h3>
                <p class="maintenance-help">
                    Upload a CSV with these exact columns:
                    <strong>student_id_number, first_name, last_name, course_program, email, phone_number</strong>.
                    The first five rows are shown before anything is created.
                </p>

                <?php if ($importError !== ''): ?>
                <div class="maintenance-alert error">
                    <?= maintenance_e($importError) ?>
                </div>
                <?php endif; ?>

                <?php if ($importSummary): ?>
                <div class="maintenance-alert success">
                    Import batch <strong><?= maintenance_e($importSummary['batch_id']) ?></strong> completed.
                    Duplicates were skipped and logged in the import registry.
                    <div class="import-summary-grid">
                        <div class="import-summary-card">
                            <span>Total Rows</span>
                            <strong><?= (int) $importSummary['total_rows'] ?></strong>
                        </div>
                        <div class="import-summary-card">
                            <span>Imported</span>
                            <strong><?= (int) $importSummary['imported'] ?></strong>
                        </div>
                        <div class="import-summary-card">
                            <span>Duplicates</span>
                            <strong><?= (int) $importSummary['duplicates'] ?></strong>
                        </div>
                        <div class="import-summary-card">
                            <span>Failed</span>
                            <strong><?= (int) $importSummary['failed'] ?></strong>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <form class="student-import-form" method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="student_import_action" value="preview">
                    <label for="students_csv">CSV File</label>
                    <input type="file" id="students_csv" name="students_csv" accept=".csv,text/csv" required>
                    <p class="maintenance-help mb-0 mt-2">
                        Imported students use their student ID number as the initial password and must change it on first login.
                    </p>
                    <div class="maintenance-btn-row">
                        <button class="maintenance-btn primary" type="submit">Preview CSV</button>
                    </div>
                </form>

                <?php if ($previewRows): ?>
                <div class="student-preview">
                    <div class="student-preview-head">
                        <span>Preview: <?= maintenance_e($previewFileName ?: 'Uploaded CSV') ?></span>
                        <span><?= count($previewRows) ?> parsed rows</span>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-sm align-middle">
                            <thead>
                                <tr>
                                    <th>Row</th>
                                    <th>Student ID</th>
                                    <th>First Name</th>
                                    <th>Last Name</th>
                                    <th>Course</th>
                                    <th>Email</th>
                                    <th>Phone</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach (array_slice($previewRows, 0, 5) as $row): ?>
                                <tr>
                                    <td><?= (int) $row['row_number'] ?></td>
                                    <td><?= maintenance_e($row['student_id_number']) ?></td>
                                    <td><?= maintenance_e($row['first_name']) ?></td>
                                    <td><?= maintenance_e($row['last_name']) ?></td>
                                    <td><?= maintenance_e($row['course_program']) ?></td>
                                    <td><?= maintenance_e($row['email']) ?></td>
                                    <td><?= maintenance_e($row['phone_number']) ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="maintenance-btn-row">
                    <form method="POST">
                        <input type="hidden" name="student_import_action" value="import">
                        <button class="maintenance-btn warning" type="submit">Import Students</button>
                    </form>
                    <form method="POST">
                        <input type="hidden" name="student_import_action" value="clear_preview">
                        <button class="maintenance-btn muted" type="submit">Clear Preview</button>
                    </form>
                </div>
                <?php endif; ?>
            </div>

            <div class="maintenance-placeholder">
                <span class="section-tag">Section B</span>
                <h3>Add Merchant</h3>
                <p class="maintenance-help">
                    Manually create a merchant account and set credentials directly. No email will be sent and the merchant will not be forced to change the password.
                </p>

                <?php if ($merchantSuccess): ?>
                <div class="maintenance-alert success">
                    Merchant account created for
                    <strong><?= maintenance_e($merchantSuccess['business_name']) ?></strong>.
                    User ID: <strong><?= (int) $merchantSuccess['user_id'] ?></strong>,
                    Username: <strong><?= maintenance_e($merchantSuccess['username']) ?></strong>,
                    Email: <strong><?= maintenance_e($merchantSuccess['email']) ?></strong>.
                </div>
                <?php endif; ?>

                <div class="maintenance-btn-row">
                    <button class="maintenance-btn primary" data-bs-toggle="modal" data-bs-target="#addMerchantModal">
                        <i class="fa-solid fa-store" style="margin-right:6px"></i>Add Merchant
                    </button>
                </div>
            </div>
        </section>

        <!-- Add Merchant Modal -->
        <div class="modal fade" id="addMerchantModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-lg">
                <div class="modal-content" style="border-radius:16px;border:none">
                    <div class="modal-header border-0" style="padding:20px 24px 0">
                        <div>
                            <h5 class="modal-title fw-bold" style="font-size:17px">
                                <i class="fa-solid fa-store me-2" style="color:var(--gjc-success)"></i>Add Merchant
                            </h5>
                            <p style="font-size:12px;color:var(--gjc-muted);margin:3px 0 0">Credentials are set directly — no verification email is sent.</p>
                        </div>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body" style="padding:16px 24px 24px">
                        <?php if ($merchantError !== ''): ?>
                        <div class="maintenance-alert error mb-3">
                            <?= maintenance_e($merchantError) ?>
                        </div>
                        <?php endif; ?>

                        <form class="merchant-bypass-form" method="POST" autocomplete="off">
                            <input type="hidden" name="merchant_bypass_action" value="create">
                            <div class="mbf-section">
                                <div class="mbf-section-eyebrow"><i class="fa-solid fa-user"></i> Proprietor</div>
                                <div class="maintenance-form-grid">
                                    <div>
                                        <label for="merchant_first_name">First Name *</label>
                                        <input type="text" id="merchant_first_name" name="merchant_first_name" maxlength="60" required>
                                    </div>
                                    <div>
                                        <label for="merchant_middle_name">Middle Name</label>
                                        <input type="text" id="merchant_middle_name" name="merchant_middle_name" maxlength="60">
                                    </div>
                                    <div>
                                        <label for="merchant_last_name">Last Name *</label>
                                        <input type="text" id="merchant_last_name" name="merchant_last_name" maxlength="60" required>
                                    </div>
                                    <div>
                                        <label for="merchant_suffix">Suffix</label>
                                        <input type="text" id="merchant_suffix" name="merchant_suffix" maxlength="20" placeholder="e.g. Jr., Sr., III">
                                    </div>
                                </div>
                            </div>

                            <div class="mbf-section">
                                <div class="mbf-section-eyebrow"><i class="fa-solid fa-store"></i> Business &amp; contact</div>
                                <div class="maintenance-form-grid">
                                    <div class="full">
                                        <label for="merchant_business_name">Business Name *</label>
                                        <input type="text" id="merchant_business_name" name="merchant_business_name" required>
                                    </div>
                                    <div class="full">
                                        <label for="merchant_stall_id">Stall</label>
                                        <select id="merchant_stall_id" name="merchant_stall_id">
                                            <option value="">No stall — assign later</option>
                                            <?php foreach ($vacantStalls as $st): ?>
                                            <option value="<?= maintenance_e($st['stall_id']) ?>"><?= maintenance_e($st['label']) ?> — <?= gjc_money((float) $st['monthly_rate']) ?>/mo</option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div>
                                        <label for="merchant_email">Email *</label>
                                        <input type="email" id="merchant_email" name="merchant_email" required>
                                    </div>
                                    <div>
                                        <label for="merchant_phone">Phone *</label>
                                        <input type="text" id="merchant_phone" name="merchant_phone" required>
                                    </div>
                                </div>
                            </div>

                            <div class="mbf-section">
                                <div class="mbf-section-eyebrow"><i class="fa-solid fa-key"></i> Login credentials</div>
                                <div class="maintenance-form-grid">
                                    <div class="full">
                                        <label for="merchant_username">Username *</label>
                                        <input type="text" id="merchant_username" name="merchant_username" minlength="3" maxlength="80" required>
                                    </div>
                                    <div>
                                        <label for="merchant_password">Password *</label>
                                        <input type="password" id="merchant_password" name="merchant_password" minlength="6" required>
                                    </div>
                                    <div>
                                        <label for="merchant_confirm_password">Confirm Password *</label>
                                        <input type="password" id="merchant_confirm_password" name="merchant_confirm_password" minlength="6" required>
                                    </div>
                                </div>
                            </div>

                            <div class="mbf-section">
                                <div class="mbf-section-eyebrow"><i class="fa-solid fa-note-sticky"></i> Internal notes</div>
                                <div class="maintenance-form-grid">
                                    <div class="full">
                                        <label for="merchant_notes">Notes</label>
                                        <textarea id="merchant_notes" name="merchant_notes" placeholder="e.g. onboarding context or special terms — admins only"></textarea>
                                    </div>
                                </div>
                            </div>
                            <div class="maintenance-btn-row mt-2">
                                <button class="maintenance-btn primary" type="submit">Create Merchant</button>
                                <button type="button" class="maintenance-btn muted" data-bs-dismiss="modal">Cancel</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- Section C: Prohibited Products -->
        <section class="rp-section">
            <div class="rp-section-header">
                <div class="rp-section-title">
                    <i class="fa-solid fa-ban" style="font-size:16px;color:var(--gjc-alert)"></i>
                    <h3>Prohibited Products</h3>
                    <span class="rp-count-badge" id="rp-count">
                        <?= count($restrictedProducts) ?> item<?= count($restrictedProducts) !== 1 ? 's' : '' ?>
                    </span>
                </div>
                <button class="rp-add-btn" data-bs-toggle="modal" data-bs-target="#flagProductModal">
                    <i class="fa-solid fa-plus"></i> Flag Product
                </button>
            </div>

            <?php
            $categoryIcons = [
                'beverage'   => 'fa-mug-hot',
                'drink'      => 'fa-bottle-water',
                'snack'      => 'fa-cookie-bite',
                'food'       => 'fa-burger',
                'alcohol'    => 'fa-wine-glass',
                'tobacco'    => 'fa-smoking',
                'supplement' => 'fa-pills',
                'medicine'   => 'fa-capsules',
                'candy'      => 'fa-candy-cane',
                'drug'       => 'fa-syringe',
                'general'    => 'fa-ban',
            ];
            ?>

            <div class="table-responsive">
                <table class="table rp-table align-middle js-datatable" id="rp-datatable" data-page-length="10" data-empty-message="No prohibited products flagged. All product categories are currently allowed.">
                    <thead>
                        <tr>
                            <th>Product / Keyword</th>
                            <th>Category</th>
                            <th>Match Type</th>
                            <th>Reason</th>
                            <th>Status</th>
                            <th data-orderable="false">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="rp-tbody">
                    <?php foreach ($restrictedProducts as $rp): ?>
                    <?php $active = (int) $rp['is_active']; ?>
                    <tr id="rp-card-<?= (int)$rp['id'] ?>">
                        <td><strong><?= maintenance_e($rp['product_name']) ?></strong></td>
                        <td style="text-transform:capitalize"><?= maintenance_e($rp['category']) ?></td>
                        <td>
                            <span class="rp-tag rp-tag--match-<?= maintenance_e($rp['match_type']) ?>">
                                <?= $rp['match_type'] === 'exact' ? 'Exact match' : 'Contains' ?>
                            </span>
                        </td>
                        <td style="max-width:260px;font-size:13px"><?= maintenance_e($rp['reason']) ?></td>
                        <td>
                            <span class="rp-status-badge <?= $active ? 'rp-status--banned' : 'rp-status--lifted' ?>">
                                <?= $active ? 'BANNED' : 'LIFTED' ?>
                            </span>
                        </td>
                        <td>
                            <?php if ($active): ?>
                            <button class="rp-toggle-btn rp-toggle-btn--ban"
                                    onclick="rpToggle(<?= (int)$rp['id'] ?>, 0)">
                                <i class="fa-solid fa-lock-open me-1"></i>Lift Ban
                            </button>
                            <?php else: ?>
                            <button class="rp-toggle-btn rp-toggle-btn--lift"
                                    onclick="rpToggle(<?= (int)$rp['id'] ?>, 1)">
                                <i class="fa-solid fa-ban me-1"></i>Reinstate
                            </button>
                            <?php endif; ?>
                            <button class="rp-remove-btn ms-1" title="Remove permanently"
                                    onclick="rpRemove(<?= (int)$rp['id'] ?>, '<?= addslashes($rp['product_name']) ?>')">
                                <i class="fa-solid fa-trash"></i>
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <!-- Flag Product Modal -->
        <div class="modal fade" id="flagProductModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered" style="max-width:460px">
                <div class="modal-content" style="border-radius:16px;border:none">
                    <div class="modal-header border-0 pb-0" style="padding:20px 24px 10px">
                        <div>
                            <h5 class="modal-title fw-bold" style="color:var(--gjc-alert)">
                                <i class="fa-solid fa-ban me-2"></i>Flag Prohibited Product
                            </h5>
                            <p style="font-size:12px;color:#6b7280;margin:4px 0 0">Flagged products will be blocked from being sold on the platform.</p>
                        </div>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body" style="padding:16px 24px 24px">
                        <div class="rp-modal-field">
                            <label class="rp-modal-label">Product Name *</label>
                            <input type="text" id="rp-name" class="rp-modal-input" placeholder="e.g. Energy Drink">
                        </div>
                        <div class="rp-modal-field">
                            <label class="rp-modal-label">Category</label>
                            <select id="rp-category" class="rp-modal-input">
                                <option value="general">General</option>
                                <option value="beverage">Beverage</option>
                                <option value="snack">Snack</option>
                                <option value="food">Food</option>
                                <option value="alcohol">Alcohol</option>
                                <option value="tobacco">Tobacco</option>
                                <option value="supplement">Supplement</option>
                                <option value="medicine">Medicine</option>
                                <option value="candy">Candy</option>
                            </select>
                        </div>
                        <div class="rp-modal-field">
                            <label class="rp-modal-label">Match Type</label>
                            <select id="rp-match-type" class="rp-modal-input">
                                <option value="contains">Contains — block anything with this word</option>
                                <option value="exact">Exact — block only exact name</option>
                            </select>
                        </div>
                        <div class="rp-modal-field">
                            <label class="rp-modal-label">Reason *</label>
                            <input type="text" id="rp-reason" class="rp-modal-input"
                                   placeholder="e.g. High sugar content — DepEd health guidelines">
                        </div>
                        <div id="rp-modal-alert"></div>
                        <button type="button" id="rp-flag-btn" class="rp-add-btn w-100 justify-content-center mt-2"
                                style="border-radius:10px;padding:11px"
                                onclick="rpFlagProduct()">
                            <i class="fa-solid fa-ban me-1"></i> Flag This Product
                        </button>
                    </div>
                </div>
            </div>
        </div>

    </main>
</div>

<script src="<?= JS_URL ?>/bootstrap.bundle.min.js"></script>
<?php require __DIR__ . '/../includes/partials/datatables_assets.php'; ?>
<script>
function toggleSidebar() {
    document.getElementById('sidebar').classList.toggle('collapsed');
}

<?php if ($merchantError !== ''): ?>
document.addEventListener('DOMContentLoaded', function () {
    new bootstrap.Modal(document.getElementById('addMerchantModal')).show();
});
<?php endif; ?>

// ── Prohibited Products ─────────────────────────────────────────────────────
const RP_API = '<?= ADMIN_URL ?>/api/restricted_products.php';

const RP_ICONS = {
    beverage:'fa-mug-hot', drink:'fa-bottle-water', snack:'fa-cookie-bite',
    food:'fa-burger', alcohol:'fa-wine-glass', tobacco:'fa-smoking',
    supplement:'fa-pills', medicine:'fa-capsules', candy:'fa-candy-cane',
    general:'fa-ban'
};

function rpIcon(cat) {
    return RP_ICONS[cat.toLowerCase()] || 'fa-ban';
}

async function rpToggle(id, newActive) {
    const row = document.getElementById('rp-card-' + id);
    if (!row) return;
    const f = new FormData();
    f.append('action', 'toggle_restriction');
    f.append('id', id);
    f.append('is_active', newActive);
    try {
        const res  = await fetch(RP_API, { method: 'POST', body: f });
        const data = await res.json();
        if (data.success) {
            const badge = row.querySelector('.rp-status-badge');
            badge.className = 'rp-status-badge ' + (newActive ? 'rp-status--banned' : 'rp-status--lifted');
            badge.textContent = newActive ? 'BANNED' : 'LIFTED';
            const btn = row.querySelector('.rp-toggle-btn');
            if (newActive) {
                btn.className = 'rp-toggle-btn rp-toggle-btn--ban';
                btn.innerHTML = '<i class="fa-solid fa-lock-open me-1"></i>Lift Ban';
                btn.setAttribute('onclick', `rpToggle(${id}, 0)`);
            } else {
                btn.className = 'rp-toggle-btn rp-toggle-btn--lift';
                btn.innerHTML = '<i class="fa-solid fa-ban me-1"></i>Reinstate';
                btn.setAttribute('onclick', `rpToggle(${id}, 1)`);
            }
        } else {
            alert(data.message || 'Failed to update.');
        }
    } catch { alert('Network error.'); }
}

async function rpRemove(id, name) {
    if (!confirm(`Permanently remove "${name}" from the prohibited list?`)) return;
    const f = new FormData();
    f.append('action', 'delete_restriction');
    f.append('id', id);
    try {
        const res  = await fetch(RP_API, { method: 'POST', body: f });
        const data = await res.json();
        if (data.success) {
            const row = document.getElementById('rp-card-' + id);
            if (row) {
                $('#rp-datatable').DataTable().row(row).remove().draw();
            }
            rpUpdateCount(-1);
        } else {
            alert(data.message || 'Failed to remove.');
        }
    } catch { alert('Network error.'); }
}

async function rpFlagProduct() {
    const name      = document.getElementById('rp-name').value.trim();
    const category  = document.getElementById('rp-category').value;
    const matchType = document.getElementById('rp-match-type').value;
    const reason    = document.getElementById('rp-reason').value.trim();
    const alertEl   = document.getElementById('rp-modal-alert');
    const btn       = document.getElementById('rp-flag-btn');

    if (!name || !reason) {
        alertEl.innerHTML = '<div class="rp-modal-alert" style="background:var(--gjc-danger-bg);color:var(--gjc-danger)">Product name and reason are required.</div>';
        return;
    }

    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Flagging…';
    alertEl.innerHTML = '';

    const f = new FormData();
    f.append('action',       'flag_product');
    f.append('product_name', name);
    f.append('category',     category);
    f.append('match_type',   matchType);
    f.append('reason',       reason);

    try {
        const res  = await fetch(RP_API, { method: 'POST', body: f });
        const data = await res.json();
        if (data.success) {
            alertEl.innerHTML = '<div class="rp-modal-alert" style="background:var(--gjc-success-bg);color:var(--gjc-green-600)"><i class="fa-solid fa-circle-check me-1"></i>Product flagged successfully.</div>';
            document.getElementById('rp-name').value   = '';
            document.getElementById('rp-reason').value = '';
            rpInjectCard({ product_name: name, category, match_type: matchType, reason, is_active: 1 });
            rpUpdateCount(1);
        } else {
            alertEl.innerHTML = `<div class="rp-modal-alert" style="background:var(--gjc-danger-bg);color:var(--gjc-danger)">${data.message || 'Failed.'}</div>`;
        }
    } catch {
        alertEl.innerHTML = '<div class="rp-modal-alert" style="background:var(--gjc-danger-bg);color:var(--gjc-danger)">Network error.</div>';
    }
    btn.disabled = false;
    btn.innerHTML = '<i class="fa-solid fa-ban me-1"></i> Flag This Product';
}

function rpInjectCard(rp) {
    const matchTag = rp.match_type === 'exact'
        ? '<span class="rp-tag rp-tag--match-exact">Exact match</span>'
        : '<span class="rp-tag rp-tag--match-contains">Contains</span>';

    const $row = $('<tr>');
    $row.append($('<td>').html('<strong>' + $('<span>').text(rp.product_name).html() + '</strong>'));
    $row.append($('<td style="text-transform:capitalize">').text(rp.category));
    $row.append($('<td>').html(matchTag));
    $row.append($('<td style="max-width:260px;font-size:13px">').text(rp.reason));
    $row.append($('<td>').html('<span class="rp-status-badge rp-status--banned">BANNED</span>'));
    $row.append($('<td>').html('<span style="font-size:11px;color:#94a3b8;font-style:italic">Reload to manage</span>'));

    $('#rp-datatable').DataTable().row.add($row[0]).draw();
}

function rpUpdateCount(delta) {
    const badge = document.getElementById('rp-count');
    if (!badge) return;
    const match = badge.textContent.match(/\d+/);
    const current = match ? parseInt(match[0]) : 0;
    const next = Math.max(0, current + delta);
    badge.textContent = next + ' item' + (next !== 1 ? 's' : '');
}


</script>
</body>
</html>
