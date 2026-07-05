<?php
require_once __DIR__ . '/../connection/config.php';
require_once __DIR__ . '/../connection/pdo.php';
require_once __DIR__ . '/../connection/app.php';
require_once __DIR__ . '/../connection/audit_logger.php';
require_once __DIR__ . '/../connection/mailer.php';

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
            parent_name VARCHAR(255) NULL,
            parent_email VARCHAR(255) NULL,
            parent_contact VARCHAR(40) NULL,
            parent_user_id INT NULL,
            parent_status VARCHAR(20) NOT NULL DEFAULT 'none',
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

    // Bring pre-parent installs up to date. A fresh read avoids the stale
    // static cache in gjc_table_columns() when this runs twice per request.
    $columns = maintenance_table_columns_fresh($db, 'imported_student_registry');
    $adds = [
        'parent_name'    => "VARCHAR(255) NULL AFTER phone_number",
        'parent_email'   => "VARCHAR(255) NULL AFTER parent_name",
        'parent_contact' => "VARCHAR(40) NULL AFTER parent_email",
        'parent_user_id' => "INT NULL AFTER parent_contact",
        'parent_status'  => "VARCHAR(20) NOT NULL DEFAULT 'none' AFTER parent_user_id",
    ];
    foreach ($adds as $column => $definition) {
        if (!in_array($column, $columns, true)) {
            try {
                $db->exec("ALTER TABLE imported_student_registry ADD COLUMN {$column} {$definition}");
            } catch (\Throwable $ignored) {
            }
        }
    }
}

/** Required student columns and the optional ones (suffix + guardian details). */
function maintenance_student_csv_columns(): array
{
    return [
        'required' => ['student_id_number', 'first_name', 'last_name', 'course_program', 'email', 'phone_number'],
        'optional' => ['suffix', 'parent_name', 'parent_email', 'parent_contact'],
    ];
}

function maintenance_parse_student_csv(string $path): array
{
    $cols     = maintenance_student_csv_columns();
    $required = $cols['required'];
    $known    = array_merge($required, $cols['optional']);

    $handle = fopen($path, 'r');
    if (!$handle) {
        throw new RuntimeException('Unable to read the uploaded CSV file.');
    }

    $header = fgetcsv($handle);
    if (!$header) {
        fclose($handle);
        throw new RuntimeException('The CSV file is empty.');
    }

    // Strip a UTF-8 BOM Excel likes to prepend to the first header cell.
    if (isset($header[0])) {
        $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $header[0]);
    }

    // Map each header to its position. Columns may appear in any order; every
    // required column must be present and any extras must be recognised.
    $index = [];
    foreach ($header as $pos => $name) {
        $name = strtolower(trim((string) $name));
        if ($name === '') {
            continue;
        }
        if (!in_array($name, $known, true)) {
            fclose($handle);
            throw new RuntimeException("Unrecognised CSV column '{$name}'. Allowed columns: " . implode(', ', $known));
        }
        if (isset($index[$name])) {
            fclose($handle);
            throw new RuntimeException("Duplicate CSV column '{$name}'.");
        }
        $index[$name] = $pos;
    }

    $missing = array_values(array_diff($required, array_keys($index)));
    if ($missing) {
        fclose($handle);
        throw new RuntimeException('CSV is missing required column(s): ' . implode(', ', $missing));
    }

    $rows = [];
    $rowNumber = 1;
    while (($data = fgetcsv($handle)) !== false) {
        $rowNumber++;
        if (count(array_filter($data, static fn($value) => trim((string) $value) !== '')) === 0) {
            continue;
        }

        // Pull each known column by its header position; absent optional columns
        // default to '' so downstream code can rely on every key existing.
        $row = [];
        foreach ($known as $col) {
            $pos = $index[$col] ?? null;
            $row[$col] = $pos === null ? '' : trim((string) ($data[$pos] ?? ''));
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

/** Splits a single "Full Name" into first/last, falling back to the email local part. */
function maintenance_split_name(string $fullName, string $fallbackEmail = ''): array
{
    $fullName = trim(preg_replace('/\s+/', ' ', $fullName) ?? '');
    if ($fullName === '') {
        $local = trim((string) strstr($fallbackEmail, '@', true));
        return ['first' => $local !== '' ? $local : 'Guardian', 'last' => ''];
    }
    $pos = strpos($fullName, ' ');
    if ($pos === false) {
        return ['first' => $fullName, 'last' => ''];
    }
    return [
        'first' => substr($fullName, 0, $pos),
        'last'  => substr($fullName, $pos + 1),
    ];
}

/**
 * Validates every parsed row up-front so the whole batch can be judged before a
 * single account is written. Each row becomes ready / duplicate / error, with
 * in-file duplicates caught alongside ones already in the database, and the
 * intended guardian action worked out. This is what makes the preview an
 * all-or-nothing gate rather than a row-by-row commit.
 */
function maintenance_validate_student_rows(PDO $db, array $rows): array
{
    $emailCheck   = $db->prepare("SELECT userID FROM users WHERE LOWER(email) = LOWER(?) LIMIT 1");
    $studentCheck = $db->prepare("SELECT userID FROM student_info WHERE studentID = ? LIMIT 1");

    $seenStudentIds = [];
    $seenEmails     = [];

    $analysis = [];
    $counts = ['total' => 0, 'ready' => 0, 'duplicate' => 0, 'error' => 0, 'with_parent' => 0];

    foreach ($rows as $row) {
        $counts['total']++;
        $studentId = (string) $row['student_id_number'];
        $email     = strtolower((string) $row['email']);
        $status    = 'ready';
        $message   = 'Ready to import.';

        // ── Required fields, then email shape ──────────────────────────────
        $missing = [];
        foreach ([
            'student_id_number' => 'Student ID',
            'first_name'        => 'First name',
            'last_name'         => 'Last name',
            'course_program'    => 'Course',
            'email'             => 'Email',
        ] as $field => $label) {
            if (trim((string) $row[$field]) === '') {
                $missing[] = $label;
            }
        }
        if ($missing) {
            $status  = 'error';
            $message = 'Missing required field(s): ' . implode(', ', $missing) . '.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $status  = 'error';
            $message = 'Invalid student email address.';
        }

        // ── Duplicate detection: in-file first, then the database ──────────
        if ($status === 'ready') {
            if (isset($seenStudentIds[$studentId])) {
                $status  = 'duplicate';
                $message = 'Duplicate student ID earlier in this file.';
            } elseif (isset($seenEmails[$email])) {
                $status  = 'duplicate';
                $message = 'Duplicate email earlier in this file.';
            } else {
                $studentCheck->execute([$studentId]);
                if ($studentCheck->fetchColumn()) {
                    $status  = 'duplicate';
                    $message = 'Student ID already exists.';
                } else {
                    $emailCheck->execute([$email]);
                    if ($emailCheck->fetchColumn()) {
                        $status  = 'duplicate';
                        $message = 'Email already exists.';
                    }
                }
            }
        }

        // Remember identifiers so later rows in the same file collide with them.
        if ($studentId !== '') {
            $seenStudentIds[$studentId] = true;
        }
        if ($email !== '') {
            $seenEmails[$email] = true;
        }

        // ── Guardian (optional) ────────────────────────────────────────────
        $parentEmail = strtolower(trim((string) $row['parent_email']));
        $parentName  = trim((string) $row['parent_name']);
        $parentPhone = trim((string) $row['parent_contact']);
        $parentAction  = 'none';
        $parentMessage = '';

        if ($parentEmail !== '' || $parentName !== '' || $parentPhone !== '') {
            if ($parentEmail === '') {
                $parentAction  = 'skip';
                $parentMessage = 'Guardian skipped — parent_email is required to create a guardian account.';
            } elseif (!filter_var($parentEmail, FILTER_VALIDATE_EMAIL)) {
                $parentAction  = 'skip';
                $parentMessage = 'Guardian skipped — invalid parent_email.';
            } elseif ($parentEmail === $email) {
                $parentAction  = 'skip';
                $parentMessage = 'Guardian skipped — parent_email matches the student email.';
            } else {
                $parentAction  = 'link';
                $parentMessage = 'Guardian account will be created/linked.';
                $counts['with_parent']++;
            }
        }

        $counts[$status]++;
        $analysis[] = [
            'row_number'     => (int) $row['row_number'],
            'data'           => $row,
            'status'         => $status,
            'message'        => $message,
            'parent_action'  => $parentAction,
            'parent_message' => $parentMessage,
        ];
    }

    return [
        'rows'       => $analysis,
        'counts'     => $counts,
        'has_errors' => $counts['error'] > 0,
    ];
}

/**
 * Creates or reuses a guardian (parent) login account keyed by email and makes
 * sure a row exists in `parents`, returning what the caller needs to link it to
 * a student. New guardians get a random temp password and must change it on
 * first login, mirroring how imported students are provisioned. An email already
 * in use by a non-guardian account is reported back as a conflict rather than
 * being altered. $cache (keyed by lowercased email) means the same guardian
 * across several student rows is provisioned once and merely linked to each.
 */
function maintenance_provision_parent(PDO $db, array $parent, array &$cache): array
{
    $email = strtolower(trim((string) $parent['email']));
    if (isset($cache[$email])) {
        return $cache[$email];
    }

    $lookup = $db->prepare("SELECT userID, roleID FROM users WHERE LOWER(email) = LOWER(?) LIMIT 1");
    $lookup->execute([$email]);
    $existing = $lookup->fetch(PDO::FETCH_ASSOC);

    if ($existing) {
        if ((int) $existing['roleID'] !== 7) {
            return $cache[$email] = [
                'ok'     => false,
                'reason' => 'parent_email already belongs to a non-guardian account.',
            ];
        }
        $parentUserId = (int) $existing['userID'];
        $tempPassword = null;
        $reused       = true;
    } else {
        $names        = maintenance_split_name((string) $parent['name'], $email);
        $tempPassword = maintenance_temp_password();
        $columns      = maintenance_table_columns_fresh($db, 'users');
        $payload = [
            'last_name'             => $names['last'],
            'first_name'            => $names['first'],
            'name'                  => trim($names['first'] . ' ' . $names['last']),
            'middle_name'           => '',
            'suffix'                => '',
            'contact_number'        => maintenance_phone_digits((string) $parent['contact']),
            'phone'                 => (string) $parent['contact'],
            'email'                 => $email,
            'roleID'                => 7,
            'sub_role'              => 'parent',
            'password'              => password_hash($tempPassword, PASSWORD_DEFAULT),
            'profile_img'           => '',
            'force_password_change' => 1,
            'is_first_login'        => 1,
            'password_changed'      => 0,
            'temp_password'         => $tempPassword,
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
        $db->prepare($sql)->execute($values);
        $parentUserId = (int) $db->lastInsertId();
        $reused       = false;
    }

    // Ensure the parents row exists and read back its id (parent_student_links.parent_id).
    $db->prepare("INSERT IGNORE INTO parents (user_id) VALUES (?)")->execute([$parentUserId]);
    $pidStmt = $db->prepare("SELECT id FROM parents WHERE user_id = ? LIMIT 1");
    $pidStmt->execute([$parentUserId]);
    $parentId = (int) $pidStmt->fetchColumn();

    return $cache[$email] = [
        'ok'             => true,
        'parent_id'      => $parentId,
        'parent_user_id' => $parentUserId,
        'temp_password'  => $tempPassword,
        'reused'         => $reused,
    ];
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
        'suffix' => (string) ($row['suffix'] ?? ''),
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
 * Emails a newly-created guardian their login credentials. Mirrors the merchant
 * credentials mail in admin/api/stall_applications.php. Returns true on success;
 * any SMTP failure is swallowed and reported as false so a bad address never
 * derails an import that has already committed.
 */
function maintenance_send_guardian_credentials(string $email, string $name, string $tempPassword): bool
{
    if ($email === '' || $tempPassword === '') {
        return false;
    }

    $loginUrl  = defined('BASE_URL') ? rtrim(BASE_URL, '/') . '/login' : '/login';
    $safeName  = htmlspecialchars($name !== '' ? $name : 'Guardian', ENT_QUOTES, 'UTF-8');
    $safeEmail = htmlspecialchars($email, ENT_QUOTES, 'UTF-8');
    $safePass  = htmlspecialchars($tempPassword, ENT_QUOTES, 'UTF-8');

    try {
        $mail = gjc_mailer();
        $mail->addAddress($email, $name !== '' ? $name : 'Guardian');
        $mail->Subject = 'GenPay - Parent Account Credentials';
        $mail->Body = '
            <div style="font-family:Arial,sans-serif;max-width:560px;margin:0 auto;background:#f0fdf4;padding:28px;border-radius:14px">
                <h2 style="color:#064420;margin-top:0">Your GenPay Parent Account</h2>
                <p style="color:#374151;line-height:1.7">Dear <strong>' . $safeName . '</strong>,</p>
                <p style="color:#374151;line-height:1.7">A GenPay parent account has been created so you can monitor and manage your child\'s wallet.</p>
                <div style="background:#052e16;border-radius:10px;padding:16px;margin:16px 0;color:#dcfce7">
                    <p style="margin:0 0 8px;font-size:12px;font-weight:700;color:#86efac;text-transform:uppercase">Login Credentials</p>
                    <p style="margin:0"><strong>Email:</strong> ' . $safeEmail . '</p>
                    <p style="margin:6px 0 0"><strong>Temporary Password:</strong> ' . $safePass . '</p>
                </div>
                <p style="color:#b91c1c;font-weight:700">You must change this password on first login before accessing your dashboard.</p>
                <p style="color:#374151">Login page: <a href="' . $loginUrl . '" style="color:#16a34a">' . $loginUrl . '</a></p>
                <p style="font-size:12px;color:#6b7280">GenPay Team</p>
            </div>';
        $mail->AltBody = "Dear {$name},\n\nA GenPay parent account has been created so you can monitor and manage your child's wallet.\n\nEmail: {$email}\nTemporary Password: {$tempPassword}\n\nLog in at {$loginUrl}. You must change your password on first login.\n\nGenPay Team";
        $mail->send();
        return true;
    } catch (Throwable $mailEx) {
        error_log('[maintenance] guardian credentials email failed for ' . $email . ': ' . $mailEx->getMessage());
        return false;
    }
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
         VALUES (?, ?, ?, ?, ?, ?, ?,  ?, ?, ?, ?,  '', '', '', '', '',  1, 'awarded', 5, ?, NOW(),  ?, ?)"
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
gjc_ensure_parent_schema($db);

$previewRows = $_SESSION['bulk_student_import_rows'] ?? [];
$previewFileName = (string) ($_SESSION['bulk_student_import_filename'] ?? '');
$previewReport = null;
$importError = '';
$importSummary = null;
$merchantError = '';
$merchantSuccess = null;
$parentLinkError = '';
$parentLinkSuccess = '';

$restrictedProducts = $db->query(
    "SELECT * FROM restricted_products ORDER BY is_active DESC, category ASC, product_name ASC"
)->fetchAll(PDO::FETCH_ASSOC);

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $action = (string) ($_POST['student_import_action'] ?? '');
    $merchantAction = (string) ($_POST['merchant_bypass_action'] ?? '');
    $parentLinkAction = (string) ($_POST['parent_link_action'] ?? '');

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

            gjc_ensure_parent_schema($db);

            // Re-validate the whole batch against the current database. If any row
            // is invalid we refuse before opening a transaction — nothing is
            // written unless every row is clean (duplicates are the one allowed
            // skip, since detecting them is the point of the unique student ID).
            $report = maintenance_validate_student_rows($db, $rows);
            if ($report['has_errors']) {
                throw new RuntimeException(
                    $report['counts']['error'] . ' row(s) still have validation errors. '
                    . 'Fix them and re-upload — nothing was imported.'
                );
            }

            $batchId  = date('YmdHis');
            $fileName = (string) ($_SESSION['bulk_student_import_filename'] ?? 'students.csv');
            $summary = [
                'batch_id'        => $batchId,
                'file_name'       => $fileName,
                'total_rows'      => count($rows),
                'imported'        => 0,
                'duplicates'      => 0,
                'failed'          => 0,
                'parents_created' => 0,
                'parents_linked'  => 0,
                'parents_emailed' => 0,
                'parents_email_failed' => 0,
            ];
            $createdUserIds = [];
            $parentCache    = [];
            // Guardians whose accounts were freshly created in this batch; their
            // temp-password emails are sent AFTER commit so SMTP latency never
            // holds a DB lock open and a bad address can't roll back the import.
            $guardianEmailQueue = [];

            $studentInfoInsert = $db->prepare(
                "INSERT INTO student_info (userID, studentID, yr_lvl, courseID) VALUES (?, ?, '1', ?)"
            );
            $registryInsert = $db->prepare(
                "INSERT INTO imported_student_registry
                    (import_batch_id, user_id, student_id_number, first_name, last_name,
                     course_program, email, phone_number, parent_name, parent_email,
                     parent_contact, parent_user_id, parent_status, import_status, message, imported_by)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
            );
            $linkInsert = $db->prepare(
                "INSERT IGNORE INTO parent_student_links (parent_id, student_user_id) VALUES (?, ?)"
            );

            // One all-or-nothing transaction: every insert commits together, or a
            // mid-import failure rolls the whole batch back leaving no partial data.
            $db->beginTransaction();
            try {
                foreach ($report['rows'] as $entry) {
                    $row       = $entry['data'];
                    $studentId = (string) $row['student_id_number'];
                    $userId       = null;
                    $parentUserId = null;
                    $parentStatus = 'none';

                    if ($entry['status'] === 'duplicate') {
                        $summary['duplicates']++;
                        $registryStatus = 'duplicate';
                        $message = $entry['message'];
                    } else {
                        // status === 'ready' (error rows were rejected above)
                        $courseId = maintenance_course_id($db, (string) $row['course_program']);
                        $userId   = maintenance_insert_student_user($db, $row);
                        $studentInfoInsert->execute([$userId, $studentId, $courseId]);
                        gjc_student_wallet($db, $userId);

                        $summary['imported']++;
                        $createdUserIds[] = $userId;
                        $registryStatus = 'imported';
                        $message = 'Student account imported.';

                        // Optional guardian: provision (or reuse) and link.
                        if ($entry['parent_action'] === 'link') {
                            $provision = maintenance_provision_parent($db, [
                                'name'    => $row['parent_name'],
                                'email'   => $row['parent_email'],
                                'contact' => $row['parent_contact'],
                            ], $parentCache);

                            if ($provision['ok']) {
                                $parentUserId = $provision['parent_user_id'];
                                $linkInsert->execute([$provision['parent_id'], $userId]);
                                $summary['parents_linked']++;
                                if ($provision['reused']) {
                                    $parentStatus = 'linked';
                                    $message .= ' Guardian linked.';
                                } else {
                                    $summary['parents_created']++;
                                    $parentStatus = 'created';
                                    $message .= ' Guardian created & linked.';
                                    // Queue the credentials email (sent after commit).
                                    if (!empty($provision['temp_password'])) {
                                        $guardianEmailQueue[$provision['parent_user_id']] = [
                                            'email'    => (string) $row['parent_email'],
                                            'name'     => (string) $row['parent_name'],
                                            'password' => (string) $provision['temp_password'],
                                        ];
                                    }
                                }
                            } else {
                                $parentStatus = 'skipped';
                                $message .= ' Guardian skipped: ' . $provision['reason'];
                            }
                        } elseif ($entry['parent_action'] === 'skip') {
                            $parentStatus = 'skipped';
                            $message .= ' ' . $entry['parent_message'];
                        }
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
                        (string) $row['parent_name'],
                        (string) $row['parent_email'],
                        (string) $row['parent_contact'],
                        $parentUserId,
                        $parentStatus,
                        $registryStatus,
                        substr($message, 0, 255),
                        (int) $currentUser['id'],
                    ]);
                }
                $db->commit();
            } catch (Throwable $importFailure) {
                $db->rollBack();
                throw $importFailure;
            }

            // The batch is committed. Now email each newly-created guardian their
            // temporary password. A failure here never affects imported data — it
            // only bumps the "email failed" counter shown in the summary.
            foreach ($guardianEmailQueue as $guardian) {
                if (maintenance_send_guardian_credentials($guardian['email'], $guardian['name'], $guardian['password'])) {
                    $summary['parents_emailed']++;
                } else {
                    $summary['parents_email_failed']++;
                }
            }

            logAudit(
                $db,
                (int) $currentUser['id'],
                gjc_current_role(),
                'USER_IMPORT',
                'imported_student_registry',
                null,
                [
                    'import_batch_id'  => $summary['batch_id'],
                    'file_name'        => $summary['file_name'],
                    'total_rows'       => $summary['total_rows'],
                    'imported'         => $summary['imported'],
                    'duplicates'       => $summary['duplicates'],
                    'failed'           => $summary['failed'],
                    'parents_created'  => $summary['parents_created'],
                    'parents_linked'   => $summary['parents_linked'],
                    'parents_emailed'  => $summary['parents_emailed'],
                    'parents_email_failed' => $summary['parents_email_failed'],
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

        if ($parentLinkAction === 'link') {
            $parentUserId    = (int) ($_POST['parent_user_id'] ?? 0);
            $studentSchoolId = strtoupper(trim((string) ($_POST['student_school_id'] ?? '')));
            if ($parentUserId <= 0 || $studentSchoolId === '') {
                throw new RuntimeException('Choose a parent account and enter a student school ID.');
            }

            // The selected account must still be a parent (roleID 7).
            $parentChk = $db->prepare("SELECT userID FROM users WHERE userID = ? AND roleID = 7 LIMIT 1");
            $parentChk->execute([$parentUserId]);
            if (!$parentChk->fetchColumn()) {
                throw new RuntimeException('That parent account no longer exists.');
            }

            // Resolve the student by their school ID (same key parents self-link with).
            $studentStmt = $db->prepare(
                "SELECT u.userID, u.first_name, u.last_name
                   FROM users u
                   JOIN student_info si ON si.userID = u.userID
                  WHERE si.studentID = ? AND u.roleID = 1
                  LIMIT 1"
            );
            $studentStmt->execute([$studentSchoolId]);
            $student = $studentStmt->fetch(PDO::FETCH_ASSOC);
            if (!$student) {
                throw new RuntimeException("No student found with school ID {$studentSchoolId}.");
            }
            $studentUserId = (int) $student['userID'];
            $studentName   = trim($student['first_name'] . ' ' . $student['last_name']);

            // Ensure the parents row exists, then read its id (parent_student_links.parent_id).
            $db->prepare("INSERT IGNORE INTO parents (user_id) VALUES (?)")->execute([$parentUserId]);
            $pidStmt = $db->prepare("SELECT id FROM parents WHERE user_id = ? LIMIT 1");
            $pidStmt->execute([$parentUserId]);
            $parentRowId = (int) $pidStmt->fetchColumn();

            $dupStmt = $db->prepare("SELECT id FROM parent_student_links WHERE parent_id = ? AND student_user_id = ?");
            $dupStmt->execute([$parentRowId, $studentUserId]);
            if ($dupStmt->fetchColumn()) {
                throw new RuntimeException($studentName . ' is already linked to that parent.');
            }

            $db->prepare("INSERT INTO parent_student_links (parent_id, student_user_id) VALUES (?, ?)")
               ->execute([$parentRowId, $studentUserId]);

            logAudit(
                $db,
                (int) $currentUser['id'],
                gjc_current_role(),
                'USER_ACCOUNT',
                'parent_student_links',
                null,
                [
                    'event' => 'parent_link',
                    'parent_user_id' => $parentUserId,
                    'parent_id' => $parentRowId,
                    'student_user_id' => $studentUserId,
                    'student_school_id' => $studentSchoolId,
                ]
            );

            $parentLinkSuccess = "Linked {$studentName} ({$studentSchoolId}) to the selected parent.";
        }

        if ($parentLinkAction === 'unlink') {
            $linkId = (int) ($_POST['link_id'] ?? 0);
            if ($linkId <= 0) {
                throw new RuntimeException('Invalid link.');
            }

            // Capture who was linked before deleting, for the audit trail.
            $infoStmt = $db->prepare(
                "SELECT psl.parent_id, p.user_id AS parent_user_id, psl.student_user_id
                   FROM parent_student_links psl
                   JOIN parents p ON p.id = psl.parent_id
                  WHERE psl.id = ?
                  LIMIT 1"
            );
            $infoStmt->execute([$linkId]);
            $linkRow = $infoStmt->fetch(PDO::FETCH_ASSOC);

            $db->prepare("DELETE FROM parent_student_links WHERE id = ?")->execute([$linkId]);

            if ($linkRow) {
                logAudit(
                    $db,
                    (int) $currentUser['id'],
                    gjc_current_role(),
                    'USER_ACCOUNT',
                    'parent_student_links',
                    $linkRow,
                    ['event' => 'parent_unlink', 'link_id' => $linkId]
                );
            }

            $parentLinkSuccess = 'Parent–student link removed.';
        }
    } catch (Throwable $error) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        if ($merchantAction === 'create') {
            $merchantError = $error->getMessage();
        } elseif ($parentLinkAction !== '') {
            $parentLinkError = $error->getMessage();
        } else {
            $importError = $error->getMessage();
        }
    }
}

// Validate whatever is staged for preview against the current database so the
// operator always sees an up-to-date verdict (works on POST preview and on any
// later page reload while a preview is held in the session).
$previewReport = $previewRows ? maintenance_validate_student_rows($db, $previewRows) : null;

// Parent accounts available to link, and the links that already exist.
$parentAccounts = $db->query(
    "SELECT u.userID, u.first_name, u.last_name, u.email
       FROM users u
      WHERE u.roleID = 7
      ORDER BY u.last_name, u.first_name"
)->fetchAll(PDO::FETCH_ASSOC);

$parentLinks = $db->query(
    "SELECT psl.id AS link_id, psl.linked_at,
            pu.first_name AS p_first, pu.last_name AS p_last, pu.email AS p_email,
            su.first_name AS s_first, su.last_name AS s_last,
            si.studentID
       FROM parent_student_links psl
       JOIN parents p  ON p.id = psl.parent_id
       JOIN users  pu  ON pu.userID = p.user_id
       JOIN users  su  ON su.userID = psl.student_user_id
       LEFT JOIN student_info si ON si.userID = su.userID
      ORDER BY psl.linked_at DESC, psl.id DESC"
)->fetchAll(PDO::FETCH_ASSOC);

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
    <link rel="stylesheet" href="<?= CSS_URL ?>/admin.css?v=5">
    <link rel="stylesheet" href="<?= CSS_URL ?>/responsive.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.8/css/dataTables.bootstrap5.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= CSS_URL ?>/maintenance.css?v=2">
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
                    Required: <strong>student_id_number, first_name, last_name, course_program, email, phone_number</strong>.
                    Optional: <strong>suffix, parent_name, parent_email, parent_contact</strong> (guardian is auto-created &amp; linked).
                </p>

                <?php if ($importError !== ''): ?>
                <div class="maintenance-alert error">
                    <?= maintenance_e($importError) ?>
                </div>
                <?php endif; ?>

                <?php if ($importSummary): ?>
                <div class="maintenance-alert success">
                    Import batch <strong><?= maintenance_e($importSummary['batch_id']) ?></strong>
                    from <strong><?= maintenance_e($importSummary['file_name']) ?></strong> completed.
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
                            <span>Guardians Created</span>
                            <strong><?= (int) $importSummary['parents_created'] ?></strong>
                        </div>
                        <div class="import-summary-card">
                            <span>Guardians Linked</span>
                            <strong><?= (int) $importSummary['parents_linked'] ?></strong>
                        </div>
                        <div class="import-summary-card">
                            <span>Credentials Emailed</span>
                            <strong><?= (int) ($importSummary['parents_emailed'] ?? 0) ?></strong>
                        </div>
                    </div>
                    <?php if ((int) ($importSummary['parents_email_failed'] ?? 0) > 0): ?>
                        <p style="margin:10px 0 0;color:var(--gjc-danger);font-size:12px">
                            <i class="fa-solid fa-triangle-exclamation"></i>
                            <?= (int) $importSummary['parents_email_failed'] ?> credential email(s) could not be sent.
                            Those guardians' temporary passwords are still saved and can be retrieved from the import registry.
                        </p>
                    <?php endif; ?>
                </div>
                <?php endif; ?>

                <form class="student-import-form" method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="student_import_action" value="preview">
                    <label for="students_csv">CSV File</label>
                    <input type="file" id="students_csv" name="students_csv" accept=".csv,text/csv" required>
                    <p class="maintenance-help mb-0 mt-2">
                        Imported students use their student ID number as the initial password. Guardians (when provided)
                        get a generated temporary password emailed to them automatically. Both are forced to change it on first login.
                    </p>
                    <div class="maintenance-btn-row">
                        <button class="maintenance-btn primary" type="submit">Preview CSV</button>
                    </div>
                </form>

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
                    <strong><?= maintenance_e($merchantSuccess['business_name']) ?></strong>
                    (User ID <strong><?= (int) $merchantSuccess['user_id'] ?></strong>).
                    They sign in with email <strong><?= maintenance_e($merchantSuccess['email']) ?></strong>
                    and temporary password
                    <strong style="font-family:monospace;letter-spacing:.5px"><?= maintenance_e($merchantSuccess['temp_password']) ?></strong>.
                    Share these with the merchant — they will be asked to set a new password on first login.
                </div>
                <?php endif; ?>

                <div class="maintenance-btn-row">
                    <button class="maintenance-btn primary" data-bs-toggle="modal" data-bs-target="#addMerchantModal">
                        <i class="fa-solid fa-store" style="margin-right:6px"></i>Add Merchant
                    </button>
                </div>
            </div>
        </section>

        <!-- Import Preview Modal -->
        <?php if ($previewRows && $previewReport): $rc = $previewReport['counts']; ?>
        <div class="modal fade" id="previewModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered modal-xl">
                <div class="modal-content" style="border-radius:16px;border:none">
                    <div class="modal-header border-0" style="padding:20px 24px 6px">
                        <div>
                            <h5 class="modal-title fw-bold" style="font-size:17px">
                                <i class="fa-solid fa-file-csv me-2" style="color:var(--gjc-success)"></i>Import Preview
                            </h5>
                            <p style="font-size:12px;color:var(--gjc-muted);margin:3px 0 0">
                                <?= maintenance_e($previewFileName ?: 'Uploaded CSV') ?> — <?= (int) $rc['total'] ?> parsed row<?= $rc['total'] !== 1 ? 's' : '' ?>, reviewed before anything is written.
                            </p>
                        </div>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body" style="padding:12px 24px 8px">
                        <div class="import-summary-grid">
                            <div class="import-summary-card"><span>Total</span><strong><?= (int) $rc['total'] ?></strong></div>
                            <div class="import-summary-card"><span>Ready</span><strong><?= (int) $rc['ready'] ?></strong></div>
                            <div class="import-summary-card"><span>Duplicates</span><strong><?= (int) $rc['duplicate'] ?></strong></div>
                            <div class="import-summary-card"><span>Errors</span><strong><?= (int) $rc['error'] ?></strong></div>
                            <div class="import-summary-card"><span>With Guardian</span><strong><?= (int) $rc['with_parent'] ?></strong></div>
                        </div>

                        <?php if ($previewReport['has_errors']): ?>
                        <div class="maintenance-alert error">
                            <?= (int) $rc['error'] ?> row(s) have validation errors (highlighted below). Fix them in the CSV
                            and upload again — the import stays blocked until every row is valid.
                        </div>
                        <?php endif; ?>

                        <div class="table-responsive" style="max-height:52vh;overflow:auto">
                            <table class="table table-sm align-middle">
                                <thead>
                                    <tr>
                                        <th>Row</th>
                                        <th>Status</th>
                                        <th>Student ID</th>
                                        <th>Name</th>
                                        <th>Course</th>
                                        <th>Email</th>
                                        <th>Guardian</th>
                                        <th>Notes</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach (array_slice($previewReport['rows'], 0, 100) as $entry): $r = $entry['data']; ?>
                                    <tr class="<?= $entry['status'] === 'error' ? 'table-danger' : ($entry['status'] === 'duplicate' ? 'table-warning' : '') ?>">
                                        <td><?= (int) $entry['row_number'] ?></td>
                                        <td>
                                            <?php if ($entry['status'] === 'ready'): ?>
                                                <span class="badge bg-success">Ready</span>
                                            <?php elseif ($entry['status'] === 'duplicate'): ?>
                                                <span class="badge bg-warning text-dark">Duplicate</span>
                                            <?php else: ?>
                                                <span class="badge bg-danger">Error</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= maintenance_e($r['student_id_number']) ?></td>
                                        <td><?= maintenance_e(trim(preg_replace('/\s+/', ' ', $r['first_name'] . ' ' . $r['last_name'] . ' ' . ($r['suffix'] ?? '')))) ?></td>
                                        <td><?= maintenance_e($r['course_program']) ?></td>
                                        <td><?= maintenance_e($r['email']) ?></td>
                                        <td>
                                            <?php if ($entry['parent_action'] === 'link'): ?>
                                                <span title="<?= maintenance_e($r['parent_email']) ?>">
                                                    <i class="fa-solid fa-user-shield" style="color:var(--gjc-success)"></i>
                                                    <?= maintenance_e($r['parent_name'] ?: $r['parent_email']) ?>
                                                </span>
                                            <?php elseif ($entry['parent_action'] === 'skip'): ?>
                                                <span class="text-warning" title="<?= maintenance_e($entry['parent_message']) ?>">
                                                    <i class="fa-solid fa-triangle-exclamation"></i> skipped
                                                </span>
                                            <?php else: ?>
                                                <span class="text-muted">—</span>
                                            <?php endif; ?>
                                        </td>
                                        <td style="font-size:12px"><?= maintenance_e(trim($entry['message'] . ' ' . ($entry['parent_action'] === 'skip' ? $entry['parent_message'] : ''))) ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php if (count($previewReport['rows']) > 100): ?>
                        <p class="maintenance-help mb-0">Showing the first 100 of <?= count($previewReport['rows']) ?> rows.</p>
                        <?php endif; ?>
                    </div>
                    <div class="modal-footer border-0" style="padding:8px 24px 22px">
                        <form method="POST" style="margin:0">
                            <input type="hidden" name="student_import_action" value="clear_preview">
                            <button class="maintenance-btn muted" type="submit">Clear Preview</button>
                        </form>
                        <?php if ($previewReport['has_errors']): ?>
                            <button class="maintenance-btn warning" type="button" disabled
                                    title="Resolve the highlighted validation errors first">
                                Import Blocked — Fix <?= (int) $rc['error'] ?> Error<?= $rc['error'] !== 1 ? 's' : '' ?>
                            </button>
                        <?php elseif ((int) $rc['ready'] === 0): ?>
                            <button class="maintenance-btn muted" type="button" disabled>
                                No New Students to Import
                            </button>
                        <?php else: ?>
                            <form method="POST" style="margin:0">
                                <input type="hidden" name="student_import_action" value="import">
                                <button class="maintenance-btn warning" type="submit">
                                    Import <?= (int) $rc['ready'] ?> Student<?= $rc['ready'] !== 1 ? 's' : '' ?><?php if ($rc['duplicate']): ?> (<?= (int) $rc['duplicate'] ?> dup skipped)<?php endif; ?>
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Add Merchant Modal -->
        <div class="modal fade" id="addMerchantModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-lg">
                <div class="modal-content" style="border-radius:16px;border:none">
                    <div class="modal-header border-0" style="padding:20px 24px 0">
                        <div>
                            <h5 class="modal-title fw-bold" style="font-size:17px">
                                <i class="fa-solid fa-store me-2" style="color:var(--gjc-success)"></i>Add Merchant
                            </h5>
                            <p style="font-size:12px;color:var(--gjc-muted);margin:3px 0 0">Same details as the public application, but approved instantly — the account is created and ready to use right away.</p>
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
                                    <div>
                                        <label for="merchant_sex">Sex *</label>
                                        <select id="merchant_sex" name="merchant_sex" required>
                                            <option value="">Select…</option>
                                            <option value="male">Male</option>
                                            <option value="female">Female</option>
                                        </select>
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
                                <div class="mbf-credentials-note">
                                    <i class="fa-solid fa-circle-info"></i>
                                    <span>The merchant signs in with the email above. A temporary password is generated automatically and shown here once the account is created — share it with the merchant, who will be prompted to set a new password on first login.</span>
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

        <!-- Section D: Link Parent to Student -->
        <section class="maintenance-placeholder" style="margin-top:14px">
            <span class="section-tag">Section D</span>
            <h3>Link Parent to Student</h3>
            <p class="maintenance-help">
                Connect an existing parent account to a student by the student's school ID. Parents can also self-link
                from their own dashboard — this is the admin shortcut for accounts made via Add User or bulk import.
            </p>

            <?php if ($parentLinkError !== ''): ?>
            <div class="maintenance-alert error"><?= maintenance_e($parentLinkError) ?></div>
            <?php endif; ?>
            <?php if ($parentLinkSuccess !== ''): ?>
            <div class="maintenance-alert success"><?= maintenance_e($parentLinkSuccess) ?></div>
            <?php endif; ?>

            <?php if (empty($parentAccounts)): ?>
            <div class="maintenance-alert" style="background:#f8fafc;border:1px solid #e5e7eb;color:#475569">
                No parent accounts exist yet. Create one via <strong>Users → Add User</strong> (role: Parent),
                or import students with the <strong>parent_*</strong> columns filled in.
            </div>
            <?php else: ?>
            <form class="merchant-bypass-form" method="POST">
                <input type="hidden" name="parent_link_action" value="link">
                <div class="maintenance-form-grid">
                    <div class="full parent-search-wrap">
                        <label for="parent_search">Parent Account</label>
                        <input type="text" id="parent_search" placeholder="Search parent by name or email…" autocomplete="off">
                        <input type="hidden" name="parent_user_id" id="parent_user_id" value="">
                        <div id="parent_results" class="parent-search-results" style="display:none"></div>
                        <div id="parent_selected" class="parent-search-selected" style="display:none"></div>
                    </div>
                    <div class="full">
                        <label for="student_school_id">Student School ID</label>
                        <input type="text" id="student_school_id" name="student_school_id"
                               placeholder="e.g. GJC2026-2001" maxlength="30" required autocomplete="off">
                    </div>
                </div>
                <div class="maintenance-btn-row">
                    <button class="maintenance-btn primary" type="submit">
                        <i class="fa-solid fa-link" style="margin-right:6px"></i>Link Parent to Student
                    </button>
                </div>
            </form>
            <?php endif; ?>

            <div class="student-preview" style="margin-top:16px">
                <div class="student-preview-head">
                    <span>Existing Links</span>
                    <span><?= count($parentLinks) ?> link<?= count($parentLinks) !== 1 ? 's' : '' ?></span>
                </div>
                <?php if (empty($parentLinks)): ?>
                <div style="padding:14px 12px"><p class="maintenance-help" style="margin:0">No parent–student links yet.</p></div>
                <?php else: ?>
                <div class="table-responsive" style="max-height:360px;overflow:auto">
                    <table class="table table-sm align-middle">
                        <thead>
                            <tr>
                                <th>Parent</th>
                                <th>Parent Email</th>
                                <th>Student</th>
                                <th>Student ID</th>
                                <th>Linked</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($parentLinks as $lk): ?>
                            <tr>
                                <td><?= maintenance_e(trim($lk['p_first'] . ' ' . $lk['p_last']) ?: '—') ?></td>
                                <td><?= maintenance_e($lk['p_email']) ?></td>
                                <td><?= maintenance_e(trim($lk['s_first'] . ' ' . $lk['s_last']) ?: '—') ?></td>
                                <td><?= maintenance_e($lk['studentID'] ?? '—') ?></td>
                                <td style="font-size:12px;white-space:nowrap"><?= maintenance_e($lk['linked_at']) ?></td>
                                <td>
                                    <button type="button" class="maintenance-btn muted js-unlink-btn" style="padding:4px 10px" title="Unlink"
                                            data-link-id="<?= (int) $lk['link_id'] ?>"
                                            data-parent="<?= maintenance_e(trim($lk['p_first'] . ' ' . $lk['p_last']) ?: 'this parent') ?>"
                                            data-student="<?= maintenance_e(trim($lk['s_first'] . ' ' . $lk['s_last']) ?: 'this student') ?>"
                                            data-bs-toggle="modal" data-bs-target="#unlinkModal">
                                        <i class="fa-solid fa-link-slash"></i>
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </section>

        <!-- Unlink Confirmation Modal -->
        <div class="modal fade" id="unlinkModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered" style="max-width:420px">
                <div class="modal-content" style="border-radius:16px;border:none">
                    <div class="modal-header border-0" style="padding:20px 24px 0">
                        <h5 class="modal-title fw-bold" style="font-size:17px">
                            <i class="fa-solid fa-link-slash me-2" style="color:var(--gjc-danger)"></i>Remove Link
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body" style="padding:14px 24px 24px">
                        <p style="font-size:13px;color:var(--gjc-muted);line-height:1.55;margin-bottom:18px">
                            Unlink <strong id="unlinkStudentName">this student</strong> from
                            <strong id="unlinkParentName">this parent</strong>? The parent will no longer see this
                            student's wallet, ledger, or spending controls.
                        </p>
                        <form method="POST" style="margin:0">
                            <input type="hidden" name="parent_link_action" value="unlink">
                            <input type="hidden" name="link_id" id="unlinkLinkId" value="">
                            <div class="maintenance-btn-row" style="justify-content:flex-end">
                                <button type="button" class="maintenance-btn muted" data-bs-dismiss="modal">Cancel</button>
                                <button type="submit" class="maintenance-btn warning">
                                    <i class="fa-solid fa-link-slash" style="margin-right:6px"></i>Unlink
                                </button>
                            </div>
                        </form>
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

<?php if ($previewRows && $previewReport): ?>
document.addEventListener('DOMContentLoaded', function () {
    var pm = document.getElementById('previewModal');
    if (pm) new bootstrap.Modal(pm).show();
});
<?php endif; ?>

// ── Prohibited Products ─────────────────────────────────────────────────────
const RP_API = '<?= ADMIN_URL ?>/api/restricted_products.php';

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

// ── Parent → Student linking ────────────────────────────────────────────────
// Populate the shared unlink modal with the row that triggered it.
(function () {
    const modal = document.getElementById('unlinkModal');
    if (!modal) return;
    modal.addEventListener('show.bs.modal', function (event) {
        const btn = event.relatedTarget;
        if (!btn) return;
        document.getElementById('unlinkLinkId').value        = btn.getAttribute('data-link-id') || '';
        document.getElementById('unlinkStudentName').textContent = btn.getAttribute('data-student') || 'this student';
        document.getElementById('unlinkParentName').textContent  = btn.getAttribute('data-parent') || 'this parent';
    });
})();

// Type-to-search parent picker (client-side over the loaded parent list).
(function () {
    const input = document.getElementById('parent_search');
    if (!input) return;
    const hidden   = document.getElementById('parent_user_id');
    const results  = document.getElementById('parent_results');
    const selected = document.getElementById('parent_selected');
    const PARENTS  = <?= json_encode(array_map(static fn($pa) => [
        'id'    => (int) $pa['userID'],
        'name'  => trim($pa['first_name'] . ' ' . $pa['last_name']) ?: 'Parent',
        'email' => (string) $pa['email'],
    ], $parentAccounts), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) ?>;

    const esc = (s) => { const d = document.createElement('div'); d.textContent = s; return d.innerHTML; };

    function clearSelection() {
        hidden.value = '';
        selected.style.display = 'none';
        selected.textContent = '';
    }

    function closeResults() { results.style.display = 'none'; results.innerHTML = ''; }

    input.addEventListener('input', function () {
        clearSelection();
        const q = input.value.trim().toLowerCase();
        if (q === '') { closeResults(); return; }
        const matches = PARENTS.filter(p =>
            p.name.toLowerCase().includes(q) || p.email.toLowerCase().includes(q)
        ).slice(0, 8);
        if (!matches.length) {
            results.innerHTML = '<div class="parent-search-empty">No parents match “' + esc(input.value.trim()) + '”.</div>';
        } else {
            results.innerHTML = matches.map(p =>
                '<div class="parent-search-item" data-id="' + p.id + '">' +
                    '<strong>' + esc(p.name) + '</strong><span>' + esc(p.email) + '</span>' +
                '</div>'
            ).join('');
        }
        results.style.display = 'block';
    });

    results.addEventListener('click', function (e) {
        const item = e.target.closest('.parent-search-item');
        if (!item) return;
        const p = PARENTS.find(x => String(x.id) === item.getAttribute('data-id'));
        if (!p) return;
        hidden.value = String(p.id);
        input.value  = p.name + ' — ' + p.email;
        closeResults();
        selected.style.color = '';
        selected.textContent = '✓ Selected ' + p.name;
        selected.style.display = 'block';
    });

    // Close the dropdown when clicking outside the picker.
    document.addEventListener('click', function (e) {
        if (!e.target.closest('.parent-search-wrap')) closeResults();
    });

    // Require a chosen parent before the form can submit.
    const form = input.closest('form');
    if (form) {
        form.addEventListener('submit', function (e) {
            if (!hidden.value) {
                e.preventDefault();
                input.focus();
                selected.style.color = 'var(--gjc-danger)';
                selected.textContent = 'Please pick a parent from the search results.';
                selected.style.display = 'block';
            }
        });
    }
})();
</script>
</body>
</html>
