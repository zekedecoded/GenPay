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
        'middle_name' => '',
        'suffix' => '',
        'contact_number' => maintenance_phone_digits((string) $data['phone']),
        'phone' => $data['phone'],
        'email' => $data['email'],
        'username' => $data['username'],
        'roleID' => 5,
        'sub_role' => 'merchant_admin',
        'password' => password_hash((string) $data['password'], PASSWORD_DEFAULT),
        'profile_img' => '',
        'force_password_change' => 0,
        'is_first_login' => 0,
        'password_changed' => 1,
        'temp_password' => null,
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

function maintenance_insert_merchant_record(PDO $db, int $userId, string $businessName, string $notes): int
{
    if (!gjc_table_exists($db, 'merchant')) {
        return 0;
    }

    $columns = maintenance_table_columns_fresh($db, 'merchant');
    $payload = [
        'userID' => $userId,
        'stall_name' => $businessName,
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
                'last_name' => trim((string) ($_POST['merchant_last_name'] ?? '')),
                'email' => strtolower(trim((string) ($_POST['merchant_email'] ?? ''))),
                'phone' => trim((string) ($_POST['merchant_phone'] ?? '')),
                'username' => strtolower(trim((string) ($_POST['merchant_username'] ?? ''))),
                'password' => (string) ($_POST['merchant_password'] ?? ''),
                'confirm_password' => (string) ($_POST['merchant_confirm_password'] ?? ''),
                'business_name' => trim((string) ($_POST['merchant_business_name'] ?? '')),
                'notes' => trim((string) ($_POST['merchant_notes'] ?? '')),
            ];

            if ($merchantData['first_name'] === '' || $merchantData['last_name'] === '' || $merchantData['email'] === '' || $merchantData['phone'] === '' || $merchantData['username'] === '' || $merchantData['password'] === '' || $merchantData['business_name'] === '') {
                throw new RuntimeException('All merchant fields except Notes are required.');
            }
            if (!filter_var($merchantData['email'], FILTER_VALIDATE_EMAIL)) {
                throw new RuntimeException('Please enter a valid merchant email address.');
            }
            if ($merchantData['password'] !== $merchantData['confirm_password']) {
                throw new RuntimeException('Password and Confirm Password do not match.');
            }
            if (strlen($merchantData['password']) < 6) {
                throw new RuntimeException('Password must be at least 6 characters.');
            }
            if (!preg_match('/^[a-z0-9._-]{3,80}$/', $merchantData['username'])) {
                throw new RuntimeException('Username must be 3-80 characters using letters, numbers, dots, underscores, or hyphens.');
            }

            maintenance_ensure_merchant_bypass_schema($db);

            $emailCheck = $db->prepare("SELECT userID FROM users WHERE LOWER(email) = LOWER(?) LIMIT 1");
            $emailCheck->execute([$merchantData['email']]);
            if ($emailCheck->fetchColumn()) {
                throw new RuntimeException('A user with this email already exists.');
            }

            $usernameCheck = $db->prepare("SELECT userID FROM users WHERE LOWER(username) = LOWER(?) LIMIT 1");
            $usernameCheck->execute([$merchantData['username']]);
            if ($usernameCheck->fetchColumn()) {
                throw new RuntimeException('A user with this username already exists.');
            }

            $db->beginTransaction();
            try {
                $newMerchantUserId = maintenance_insert_merchant_user($db, $merchantData);
                $newMerchantId = maintenance_insert_merchant_record($db, $newMerchantUserId, $merchantData['business_name'], $merchantData['notes']);
                gjc_merchant_wallet($db, $newMerchantUserId);
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
                    'email' => $merchantData['email'],
                    'phone' => $merchantData['phone'],
                    'username' => $merchantData['username'],
                    'business_name' => $merchantData['business_name'],
                    'notes' => $merchantData['notes'],
                    'roleID' => 5,
                    'sub_role' => 'merchant_admin',
                    'forced_password_change' => false,
                    'email_sent' => false,
                ]
            );

            $merchantSuccess = [
                'user_id' => $newMerchantUserId,
                'merchant_id' => $newMerchantId,
                'business_name' => $merchantData['business_name'],
                'username' => $merchantData['username'],
                'email' => $merchantData['email'],
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
    <link rel="stylesheet" href="<?= CSS_URL ?>/admin.css?v=3">
    <link rel="stylesheet" href="<?= CSS_URL ?>/responsive.css">
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
            color: #374151;
            font-size: 12px;
            font-weight: 600;
            margin-bottom: 5px;
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
        .merchant-bypass-form textarea {
            width: 100%;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            background: #fff;
            padding: 8px 10px;
            font-size: 13px;
            outline: none;
            transition: border-color .15s;
        }
        .merchant-bypass-form input:focus,
        .merchant-bypass-form textarea:focus { border-color: #6b7280; }

        .merchant-bypass-form textarea { min-height: 80px; resize: vertical; }

        .maintenance-form-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 12px;
        }
        .maintenance-form-grid .full { grid-column: 1 / -1; }

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
        .maintenance-btn.primary { background: #166534; color: #fff; }
        .maintenance-btn.warning { background: #d97706; color: #fff; }
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
        .maintenance-alert.success { background: #f0fdf4; border: 1px solid #bbf7d0; color: #166534; }
        .maintenance-alert.error   { background: #fff1f2; border: 1px solid #fecdd3; color: #9f1239; }

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
            background: #fee2e2; color: #b91c1c; font-size: 11px; font-weight: 700;
            padding: 3px 10px; border-radius: 99px; letter-spacing: .4px;
        }
        .rp-add-btn {
            display: inline-flex; align-items: center; gap: 7px;
            background: #dc2626; color: #fff; border: none; border-radius: 10px;
            padding: 9px 18px; font-size: 13px; font-weight: 700; cursor: pointer;
            transition: background .15s;
        }
        .rp-add-btn:hover { background: #b91c1c; }

        .rp-empty {
            text-align: center; padding: 48px 24px;
            background: #fff; border-radius: 16px;
            border: 2px dashed #fecaca; color: #9ca3af;
        }
        .rp-empty i { font-size: 40px; color: #fca5a5; margin-bottom: 12px; }
        .rp-empty p { font-size: 14px; margin: 0; }

        .rp-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(230px, 1fr));
            gap: 16px;
        }

        .rp-card {
            border-radius: 16px; overflow: hidden;
            box-shadow: 0 2px 12px rgba(0,0,0,.08);
            display: flex; flex-direction: column;
            transition: transform .15s, box-shadow .15s;
        }
        .rp-card:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(0,0,0,.12); }

        .rp-card--active .rp-card-top {
            background: linear-gradient(135deg, #dc2626 0%, #b91c1c 100%);
            color: #fff;
        }
        .rp-card--inactive .rp-card-top {
            background: linear-gradient(135deg, #94a3b8 0%, #64748b 100%);
            color: #fff;
        }

        .rp-card-top {
            padding: 20px 16px 16px; text-align: center; position: relative;
        }
        .rp-status-ribbon {
            position: absolute; top: 10px; right: 10px;
            font-size: 9px; font-weight: 800; letter-spacing: .8px; text-transform: uppercase;
            background: rgba(255,255,255,.22); padding: 3px 8px; border-radius: 99px;
        }
        .rp-card-icon-wrap {
            width: 64px; height: 64px; background: rgba(255,255,255,.18);
            border-radius: 50%; display: flex; align-items: center; justify-content: center;
            margin: 0 auto 10px; font-size: 28px;
        }
        .rp-card-name {
            font-size: 15px; font-weight: 800; margin: 0 0 2px; line-height: 1.3;
            word-break: break-word;
        }
        .rp-card-category {
            font-size: 11px; opacity: .8; text-transform: capitalize; font-weight: 600;
        }

        .rp-card-body {
            background: #fff; padding: 12px 14px; flex: 1;
        }
        .rp-card-reason {
            font-size: 12px; color: #4b5563; line-height: 1.5; margin: 0 0 10px;
        }
        .rp-card-tags { display: flex; gap: 5px; flex-wrap: wrap; }
        .rp-tag {
            font-size: 10px; font-weight: 700; padding: 2px 8px;
            border-radius: 99px; text-transform: uppercase; letter-spacing: .4px;
        }
        .rp-tag--match-exact { background: #fef3c7; color: #92400e; }
        .rp-tag--match-contains { background: #e0e7ff; color: #3730a3; }

        .rp-card-footer {
            background: #f9fafb; border-top: 1px solid #f3f4f6;
            padding: 10px 12px;
            display: flex; gap: 6px; align-items: center;
        }
        .rp-toggle-btn {
            flex: 1; padding: 6px 10px; font-size: 11px; font-weight: 700;
            border-radius: 8px; border: none; cursor: pointer; transition: background .15s;
        }
        .rp-toggle-btn--ban { background: #dcfce7; color: #15803d; }
        .rp-toggle-btn--ban:hover { background: #bbf7d0; }
        .rp-toggle-btn--lift { background: #fee2e2; color: #b91c1c; }
        .rp-toggle-btn--lift:hover { background: #fecaca; }
        .rp-remove-btn {
            width: 30px; height: 30px; border: none; border-radius: 8px;
            background: #f1f5f9; color: #94a3b8; cursor: pointer; font-size: 12px;
            display: flex; align-items: center; justify-content: center; transition: all .15s;
        }
        .rp-remove-btn:hover { background: #fee2e2; color: #dc2626; }

        /* Flag modal */
        .rp-modal-field { margin-bottom: 14px; }
        .rp-modal-label { display: block; font-size: 12px; font-weight: 700; color: #374151; margin-bottom: 5px; }
        .rp-modal-input {
            width: 100%; padding: 9px 12px; border: 1.5px solid #e5e7eb;
            border-radius: 8px; font-size: 13px; outline: none; transition: border-color .15s;
        }
        .rp-modal-input:focus { border-color: #dc2626; }
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
            <div class="modal-dialog modal-dialog-centered modal-lg">
                <div class="modal-content" style="border-radius:16px;border:none">
                    <div class="modal-header border-0" style="padding:20px 24px 0">
                        <div>
                            <h5 class="modal-title fw-bold" style="font-size:17px">
                                <i class="fa-solid fa-store me-2" style="color:#16a34a"></i>Add Merchant
                            </h5>
                            <p style="font-size:12px;color:#6b7280;margin:3px 0 0">Credentials are set directly — no verification email is sent.</p>
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
                            <div class="maintenance-form-grid">
                                <div>
                                    <label for="merchant_first_name">First Name *</label>
                                    <input type="text" id="merchant_first_name" name="merchant_first_name" required>
                                </div>
                                <div>
                                    <label for="merchant_last_name">Last Name *</label>
                                    <input type="text" id="merchant_last_name" name="merchant_last_name" required>
                                </div>
                                <div>
                                    <label for="merchant_email">Email *</label>
                                    <input type="email" id="merchant_email" name="merchant_email" required>
                                </div>
                                <div>
                                    <label for="merchant_phone">Phone *</label>
                                    <input type="text" id="merchant_phone" name="merchant_phone" required>
                                </div>
                                <div>
                                    <label for="merchant_username">Username *</label>
                                    <input type="text" id="merchant_username" name="merchant_username" minlength="3" maxlength="80" required>
                                </div>
                                <div>
                                    <label for="merchant_business_name">Business Name *</label>
                                    <input type="text" id="merchant_business_name" name="merchant_business_name" required>
                                </div>
                                <div>
                                    <label for="merchant_password">Password *</label>
                                    <input type="password" id="merchant_password" name="merchant_password" minlength="6" required>
                                </div>
                                <div>
                                    <label for="merchant_confirm_password">Confirm Password *</label>
                                    <input type="password" id="merchant_confirm_password" name="merchant_confirm_password" minlength="6" required>
                                </div>
                                <div class="full">
                                    <label for="merchant_notes">Notes</label>
                                    <textarea id="merchant_notes" name="merchant_notes" placeholder="Internal notes only"></textarea>
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
                    <i class="fa-solid fa-ban" style="font-size:16px;color:#dc2626"></i>
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

            <?php if (empty($restrictedProducts)): ?>
            <div class="rp-empty">
                <i class="fa-solid fa-circle-check" style="color:#86efac"></i>
                <p><strong>No prohibited products flagged.</strong><br>All product categories are currently allowed.</p>
            </div>
            <?php else: ?>
            <div class="rp-grid" id="rp-grid">
                <?php foreach ($restrictedProducts as $rp): ?>
                <?php
                    $catKey  = strtolower(trim($rp['category']));
                    $icon    = $categoryIcons[$catKey] ?? 'fa-ban';
                    $active  = (int) $rp['is_active'];
                    $cardCls = $active ? 'rp-card--active' : 'rp-card--inactive';
                ?>
                <div class="rp-card <?= $cardCls ?>" id="rp-card-<?= (int)$rp['id'] ?>">
                    <div class="rp-card-top">
                        <span class="rp-status-ribbon"><?= $active ? 'BANNED' : 'LIFTED' ?></span>
                        <div class="rp-card-icon-wrap">
                            <i class="fa-solid <?= maintenance_e($icon) ?>"></i>
                        </div>
                        <div class="rp-card-name"><?= maintenance_e($rp['product_name']) ?></div>
                        <div class="rp-card-category"><?= maintenance_e($rp['category']) ?></div>
                    </div>
                    <div class="rp-card-body">
                        <p class="rp-card-reason"><?= maintenance_e($rp['reason']) ?></p>
                        <div class="rp-card-tags">
                            <span class="rp-tag rp-tag--match-<?= maintenance_e($rp['match_type']) ?>">
                                <?= $rp['match_type'] === 'exact' ? 'Exact match' : 'Contains' ?>
                            </span>
                        </div>
                    </div>
                    <div class="rp-card-footer">
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
                        <button class="rp-remove-btn" title="Remove permanently"
                                onclick="rpRemove(<?= (int)$rp['id'] ?>, '<?= addslashes($rp['product_name']) ?>')">
                            <i class="fa-solid fa-trash"></i>
                        </button>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </section>

        <!-- Flag Product Modal -->
        <div class="modal fade" id="flagProductModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered" style="max-width:460px">
                <div class="modal-content" style="border-radius:16px;border:none">
                    <div class="modal-header border-0 pb-0" style="padding:20px 24px 10px">
                        <div>
                            <h5 class="modal-title fw-bold" style="color:#dc2626">
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
    const card = document.getElementById('rp-card-' + id);
    if (!card) return;
    const f = new FormData();
    f.append('action', 'toggle_restriction');
    f.append('id', id);
    f.append('is_active', newActive);
    try {
        const res  = await fetch(RP_API, { method: 'POST', body: f });
        const data = await res.json();
        if (data.success) {
            // Swap card classes and re-render ribbon + footer button
            card.classList.toggle('rp-card--active',   newActive === 1);
            card.classList.toggle('rp-card--inactive', newActive === 0);
            card.querySelector('.rp-status-ribbon').textContent = newActive ? 'BANNED' : 'LIFTED';
            const footer = card.querySelector('.rp-card-footer');
            const btn    = footer.querySelector('.rp-toggle-btn');
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
            const card = document.getElementById('rp-card-' + id);
            if (card) card.remove();
            rpUpdateCount(-1);
            if (!document.querySelector('.rp-card')) rpShowEmpty();
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
        alertEl.innerHTML = '<div class="rp-modal-alert" style="background:#fee2e2;color:#b91c1c">Product name and reason are required.</div>';
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
            alertEl.innerHTML = '<div class="rp-modal-alert" style="background:#dcfce7;color:#15803d"><i class="fa-solid fa-circle-check me-1"></i>Product flagged successfully.</div>';
            document.getElementById('rp-name').value   = '';
            document.getElementById('rp-reason').value = '';
            rpInjectCard({ product_name: name, category, match_type: matchType, reason, is_active: 1 });
            rpUpdateCount(1);
            rpHideEmpty();
        } else {
            alertEl.innerHTML = `<div class="rp-modal-alert" style="background:#fee2e2;color:#b91c1c">${data.message || 'Failed.'}</div>`;
        }
    } catch {
        alertEl.innerHTML = '<div class="rp-modal-alert" style="background:#fee2e2;color:#b91c1c">Network error.</div>';
    }
    btn.disabled = false;
    btn.innerHTML = '<i class="fa-solid fa-ban me-1"></i> Flag This Product';
}

function rpInjectCard(rp) {
    let grid = document.getElementById('rp-grid');
    if (!grid) {
        const sec = document.querySelector('.rp-section');
        grid = document.createElement('div');
        grid.className = 'rp-grid';
        grid.id = 'rp-grid';
        sec.appendChild(grid);
    }
    const icon     = rpIcon(rp.category);
    const matchTag = rp.match_type === 'exact'
        ? '<span class="rp-tag rp-tag--match-exact">Exact match</span>'
        : '<span class="rp-tag rp-tag--match-contains">Contains</span>';

    const card = document.createElement('div');
    card.className = 'rp-card rp-card--active';
    card.id = 'rp-card-new-' + Date.now();
    card.innerHTML = `
        <div class="rp-card-top">
            <span class="rp-status-ribbon">BANNED</span>
            <div class="rp-card-icon-wrap"><i class="fa-solid ${icon}"></i></div>
            <div class="rp-card-name">${rp.product_name}</div>
            <div class="rp-card-category">${rp.category}</div>
        </div>
        <div class="rp-card-body">
            <p class="rp-card-reason">${rp.reason}</p>
            <div class="rp-card-tags">${matchTag}</div>
        </div>
        <div class="rp-card-footer">
            <span style="font-size:11px;color:#94a3b8;font-style:italic">Reload to manage</span>
        </div>`;
    grid.prepend(card);
}

function rpUpdateCount(delta) {
    const badge = document.getElementById('rp-count');
    if (!badge) return;
    const match = badge.textContent.match(/\d+/);
    const current = match ? parseInt(match[0]) : 0;
    const next = Math.max(0, current + delta);
    badge.textContent = next + ' item' + (next !== 1 ? 's' : '');
}

function rpShowEmpty() {
    const grid = document.getElementById('rp-grid');
    if (grid) grid.remove();
    const sec = document.querySelector('.rp-section');
    if (!sec.querySelector('.rp-empty')) {
        sec.insertAdjacentHTML('beforeend',
            `<div class="rp-empty"><i class="fa-solid fa-circle-check" style="color:#86efac"></i>
             <p><strong>No prohibited products flagged.</strong><br>All product categories are currently allowed.</p></div>`
        );
    }
}

function rpHideEmpty() {
    const empty = document.querySelector('.rp-empty');
    if (empty) empty.remove();
}

</script>
</body>
</html>
