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

maintenance_ensure_student_registry($db);
maintenance_ensure_merchant_bypass_schema($db);

$previewRows = $_SESSION['bulk_student_import_rows'] ?? [];
$previewFileName = (string) ($_SESSION['bulk_student_import_filename'] ?? '');
$importError = '';
$importSummary = null;
$merchantError = '';
$merchantSuccess = null;

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
        .maintenance-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 20px;
        }

        .maintenance-placeholder {
            background: #fff;
            border: 1px solid #e5e7eb;
            border-radius: 14px;
            min-height: 320px;
            padding: 24px;
            box-shadow: 0 18px 40px rgba(15, 23, 42, .06);
        }

        .maintenance-placeholder h3 {
            color: #064420;
            font-size: 18px;
            font-weight: 900;
            margin: 0;
        }

        .maintenance-placeholder .section-tag {
            display: inline-flex;
            align-items: center;
            border-radius: 999px;
            background: #fef3c7;
            color: #92400e;
            font-size: 11px;
            font-weight: 900;
            padding: 5px 10px;
            margin-bottom: 14px;
            text-transform: uppercase;
        }

        .maintenance-empty-space {
            margin-top: 22px;
            min-height: 210px;
            border: 2px dashed #d1d5db;
            border-radius: 12px;
            background: #f8fafc;
        }

        .maintenance-help {
            margin: 8px 0 18px;
            color: #64748b;
            font-size: 13px;
            font-weight: 600;
            line-height: 1.5;
        }

        .student-import-form,
        .merchant-bypass-form {
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            padding: 16px;
            background: #f8fafc;
        }

        .student-import-form label,
        .merchant-bypass-form label {
            display: block;
            color: #064420;
            font-size: 12px;
            font-weight: 900;
            margin-bottom: 8px;
            text-transform: uppercase;
        }

        .student-import-form input[type="file"],
        .merchant-bypass-form input,
        .merchant-bypass-form textarea {
            width: 100%;
            border: 1px solid #d1d5db;
            border-radius: 10px;
            background: #fff;
            padding: 10px;
            font-weight: 700;
        }

        .merchant-bypass-form textarea {
            min-height: 94px;
            resize: vertical;
        }

        .maintenance-form-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 14px;
        }

        .maintenance-form-grid .full {
            grid-column: 1 / -1;
        }

        .maintenance-btn-row {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 14px;
        }

        .maintenance-btn {
            border: 0;
            border-radius: 10px;
            padding: 10px 16px;
            font-size: 13px;
            font-weight: 900;
        }

        .maintenance-btn.primary {
            background: #0b5c2c;
            color: #fff;
        }

        .maintenance-btn.warning {
            background: #e6bc2f;
            color: #064420;
        }

        .maintenance-btn.muted {
            border: 1px solid #d1d5db;
            background: #fff;
            color: #475569;
        }

        .maintenance-alert {
            border-radius: 12px;
            padding: 14px 16px;
            margin: 16px 0;
            font-size: 13px;
            font-weight: 800;
        }

        .maintenance-alert.success {
            background: #ecfdf5;
            border: 1px solid #bbf7d0;
            color: #166534;
        }

        .maintenance-alert.error {
            background: #fff1f2;
            border: 1px solid #fecdd3;
            color: #9f1239;
        }

        .import-summary-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 10px;
            margin-top: 12px;
        }

        .import-summary-card {
            border-radius: 10px;
            background: #fff;
            border: 1px solid #d1fae5;
            padding: 10px 12px;
        }

        .import-summary-card span {
            display: block;
            color: #64748b;
            font-size: 10px;
            font-weight: 900;
            text-transform: uppercase;
        }

        .import-summary-card strong {
            color: #064420;
            font-size: 20px;
            font-weight: 900;
        }

        .student-preview {
            margin-top: 18px;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            overflow: hidden;
        }

        .student-preview-head {
            display: flex;
            justify-content: space-between;
            gap: 12px;
            padding: 12px 14px;
            background: #fffbeb;
            border-bottom: 1px solid #fde68a;
            color: #713f12;
            font-size: 13px;
            font-weight: 900;
        }

        .student-preview table {
            margin: 0;
            font-size: 12px;
        }

        .student-preview th {
            color: #475569;
            font-size: 10px;
            font-weight: 900;
            text-transform: uppercase;
        }

        @media (max-width: 900px) {
            .maintenance-grid {
                grid-template-columns: 1fr;
            }

            .import-summary-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .maintenance-form-grid {
                grid-template-columns: 1fr;
            }
        }
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

                <?php if ($merchantError !== ''): ?>
                <div class="maintenance-alert error">
                    <?= maintenance_e($merchantError) ?>
                </div>
                <?php endif; ?>

                <?php if ($merchantSuccess): ?>
                <div class="maintenance-alert success">
                    Merchant account created for
                    <strong><?= maintenance_e($merchantSuccess['business_name']) ?></strong>.
                    User ID: <strong><?= (int) $merchantSuccess['user_id'] ?></strong>,
                    Username: <strong><?= maintenance_e($merchantSuccess['username']) ?></strong>,
                    Email: <strong><?= maintenance_e($merchantSuccess['email']) ?></strong>.
                </div>
                <?php endif; ?>

                <form class="merchant-bypass-form" method="POST" autocomplete="off">
                    <input type="hidden" name="merchant_bypass_action" value="create">
                    <div class="maintenance-form-grid">
                        <div>
                            <label for="merchant_first_name">First Name</label>
                            <input type="text" id="merchant_first_name" name="merchant_first_name" required>
                        </div>
                        <div>
                            <label for="merchant_last_name">Last Name</label>
                            <input type="text" id="merchant_last_name" name="merchant_last_name" required>
                        </div>
                        <div>
                            <label for="merchant_email">Email</label>
                            <input type="email" id="merchant_email" name="merchant_email" required>
                        </div>
                        <div>
                            <label for="merchant_phone">Phone</label>
                            <input type="text" id="merchant_phone" name="merchant_phone" required>
                        </div>
                        <div>
                            <label for="merchant_username">Username</label>
                            <input type="text" id="merchant_username" name="merchant_username" minlength="3" maxlength="80" required>
                        </div>
                        <div>
                            <label for="merchant_business_name">Business Name</label>
                            <input type="text" id="merchant_business_name" name="merchant_business_name" required>
                        </div>
                        <div>
                            <label for="merchant_password">Password</label>
                            <input type="password" id="merchant_password" name="merchant_password" minlength="6" required>
                        </div>
                        <div>
                            <label for="merchant_confirm_password">Confirm Password</label>
                            <input type="password" id="merchant_confirm_password" name="merchant_confirm_password" minlength="6" required>
                        </div>
                        <div class="full">
                            <label for="merchant_notes">Notes</label>
                            <textarea id="merchant_notes" name="merchant_notes" placeholder="Internal notes only"></textarea>
                        </div>
                    </div>
                    <div class="maintenance-btn-row">
                        <button class="maintenance-btn primary" type="submit">Create Merchant</button>
                    </div>
                </form>
            </div>
        </section>
    </main>
</div>

<script src="<?= JS_URL ?>/bootstrap.bundle.min.js"></script>
<script>
function toggleSidebar() {
    document.getElementById('sidebar').classList.toggle('collapsed');
}
</script>
</body>
</html>
