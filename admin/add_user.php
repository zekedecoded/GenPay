<?php
require_once __DIR__ . '/../connection/config.php';
require_once __DIR__ . '/../connection/pdo.php';
require_once __DIR__ . '/../connection/app.php';

gjc_require_role(['admin']);

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    header('Location: ' . ADMIN_URL . '/users.php');
    exit;
}

$firstName = trim((string) ($_POST['first_name'] ?? ''));
$lastName = trim((string) ($_POST['last_name'] ?? ''));
$email = trim((string) ($_POST['email'] ?? ''));
$password = (string) ($_POST['password'] ?? '');
$role = strtolower((string) ($_POST['role'] ?? 'student'));
$roleId = ['student' => 1, 'merchant' => 2, 'admin' => 3, 'parent' => 1, 'visitor' => 1][$role] ?? 1;

if ($firstName === '' || $lastName === '' || $email === '' || $password === '') {
    header('Location: ' . ADMIN_URL . '/users.php?error=missing_fields');
    exit;
}

$columns = gjc_table_columns($db, 'users');
$insert = [];
$values = [];

foreach ([
    'first_name' => $firstName,
    'last_name' => $lastName,
    'name' => trim($firstName . ' ' . $lastName),
    'email' => $email,
    'password' => password_hash($password, PASSWORD_DEFAULT),
    'roleID' => $roleId,
    'phone' => trim((string) ($_POST['phone'] ?? '')),
    'school_id' => trim((string) ($_POST['school_id'] ?? '')),
] as $column => $value) {
    if (in_array($column, $columns, true)) {
        $insert[] = $column;
        $values[] = $value;
    }
}

if (!$insert) {
    header('Location: ' . ADMIN_URL . '/users.php?error=users_schema');
    exit;
}

$placeholders = implode(',', array_fill(0, count($insert), '?'));
$sql = 'INSERT INTO users (' . implode(',', $insert) . ') VALUES (' . $placeholders . ')';
$stmt = $db->prepare($sql);
$stmt->execute($values);

$userId = (int) $db->lastInsertId();
if ($userId > 0) {
    if ($roleId === 1) {
        gjc_student_wallet($db, $userId);
    } elseif ($roleId === 2) {
        gjc_merchant_wallet($db, $userId);
    }
}

header('Location: ' . ADMIN_URL . '/users.php?created=1');
exit;
