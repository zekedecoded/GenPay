<?php
require_once __DIR__ . '/../connection/config.php';
require_once __DIR__ . '/../connection/pdo.php';
require_once __DIR__ . '/../connection/app.php';

gjc_require_role(['finance']);

gjc_backfill_student_ids($db);

$roleFilter    = trim((string) ($_GET['role'] ?? ''));
$excludeAdmin  = !empty($_GET['exclude_admin']);

$query = "
    SELECT
        u.userID,
        u.first_name,
        u.last_name,
        u.email,
        r.role_name as role,
        si.studentID as student_id
    FROM users u
    LEFT JOIN role r ON u.roleID = r.roleID
    LEFT JOIN wallet w ON u.userID = w.userID
    LEFT JOIN student_info si ON si.userID = u.userID
";
$conditions = [];
$params = [];
if ($roleFilter !== '') {
    $conditions[] = 'LOWER(COALESCE(r.role_name, "")) = ?';
    $params[] = strtolower($roleFilter);
}
if ($excludeAdmin) {
    $conditions[] = 'LOWER(COALESCE(r.role_name, "")) != ?';
    $params[] = 'finance';
}
if ($conditions) {
    $query .= ' WHERE ' . implode(' AND ', $conditions);
}
$query .= ' ORDER BY u.userID DESC';

$stmt = $db->prepare($query);
$stmt->execute($params);
$dbUsers = $stmt->fetchAll(PDO::FETCH_ASSOC);

$users = [];
foreach ($dbUsers as $u) {
    $roleName = ($u['role'] === 'finance') ? 'Finance' : ucfirst($u['role'] ?? 'User');
    $role = strtolower($u['role'] ?? '');

    if ($role === 'student') {
        $displayId = $u['student_id'] ?? ('GJC' . date('Y') . '-????');
    } elseif (in_array($role, ['merchant', 'merchant_admin', 'merchant_staff'], true)) {
        $displayId = 'MER-' . str_pad($u['userID'], 4, '0', STR_PAD_LEFT);
    } elseif ($role === 'finance') {
        $displayId = 'FIN-' . str_pad($u['userID'], 4, '0', STR_PAD_LEFT);
    } else {
        $displayId = 'GJC-' . str_pad($u['userID'], 4, '0', STR_PAD_LEFT);
    }

    $users[] = [
        "name"      => trim($u['first_name'] . ' ' . $u['last_name']),
        "role"      => $roleName,
        "school_id" => $displayId,
        "email"     => $u['email'],
        "status"    => "Active",
    ];
}

$currentPage = 'users';
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <link rel="icon" type="image/png" sizes="32x32" href="<?= ICONS_URL ?>/gp_logo.png">
    <link rel="icon" type="image/png" sizes="192x192" href="<?= ICONS_URL ?>/gp_logo.png">
    <link rel="apple-touch-icon" sizes="180x180" href="<?= ICONS_URL ?>/gp_logo.png">
    <meta charset="UTF-8">
    <title>Users Management | GenPay</title>

    <link rel="stylesheet" href="<?= CSS_URL ?>/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer">
    <link rel="stylesheet" href="<?= CSS_URL ?>/admin.css?v=3">
    <link rel="stylesheet" href="<?= CSS_URL ?>/users.css">
    <link rel="stylesheet" href="<?= CSS_URL ?>/responsive.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.8/css/dataTables.bootstrap5.min.css">

    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap"
        rel="stylesheet">
</head>

<body>

    <div class="admin-layout">

        <?php require __DIR__ . '/../includes/partials/sidebar_admin.php'; ?>

        <main class="admin-main">

            <header class="topbar">
                <button class="menu-btn" onclick="toggleSidebar()"><i class="fa-solid fa-bars"></i></button>

                <div>
                    <h1>Users Management</h1>
                    <p>Manage users, roles, status, wallet access, and account controls.</p>
                </div>

                <div class="admin-user">
                    <span>Admin</span>
                    <div class="avatar">
                        <i class="fa-solid fa-user-tie"></i>
                    </div>
                </div>
            </header>

            <section class="users-command-panel mb-4">

                <div class="users-panel-header">
                    <div>
                        <h3>Users Directory</h3>
                        <p>Search and filter accounts across the GenPay system.</p>
                    </div>

                    <button type="button" class="add-user-btn" data-bs-toggle="modal" data-bs-target="#addUserModal">
                        <i class="fa-solid fa-user-plus"></i> Add User
                    </button>
                </div>

                <form class="users-filter-grid" method="GET" action="<?= ADMIN_URL ?>/users.php">

                    <div class="premium-field search-field">
                        <label>Search User</label>
                        <input type="text" name="search" placeholder="Name, email, school ID, or student">
                    </div>

                    <div class="premium-field">
                        <label>Role</label>
                        <select name="role">
                            <option value="" <?= $roleFilter === '' ? 'selected' : '' ?>>All Roles</option>
                            <option value="student" <?= $roleFilter === 'student' ? 'selected' : '' ?>>Student</option>
                            <option value="merchant" <?= $roleFilter === 'merchant' ? 'selected' : '' ?>>Merchant</option>
                            <option value="finance" <?= $roleFilter === 'finance' ? 'selected' : '' ?>>Finance</option>
                        </select>
                    </div>

                    <div class="premium-field">
                        <label>Status</label>
                        <select name="status">
                            <option value="">All Status</option>
                            <option value="active">Active</option>
                            <option value="suspended">Suspended</option>
                            <option value="blocked">Blocked</option>
                            <option value="pending">Pending</option>
                        </select>
                    </div>

                    <button type="submit" class="filter-btn">
                        Filter
                    </button>

                </form>

            </section>

            <section class="users-table-panel">

                <div class="users-table-header">
                    <div>
                        <h3>All Users</h3>
                        <p>Account list with wallet balance and management actions.</p>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table users-table align-middle js-datatable" id="usersTable" data-page-length="10">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Role</th>
                                <th>School ID</th>
                                <th>Email</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>

                        <tbody>
                            <?php foreach($users as $u): ?>
                            <tr>
                                <td>
                                    <div class="user-cell">
                                        <div class="user-avatar">
                                            <?php echo strtoupper(substr($u['name'], 0, 1)); ?>
                                        </div>
                                        <div>
                                            <strong><?php echo $u['name']; ?></strong>
                                            <small><?php echo $u['role']; ?> Account</small>
                                        </div>
                                    </div>
                                </td>

                                <td>
                                    <span class="role-pill">
                                        <?php echo $u['role']; ?>
                                    </span>
                                </td>

                                <td><?php echo $u['school_id']; ?></td>

                                <td><?php echo $u['email']; ?></td>

                                <td>
                                    <?php
                                    $statusClass = strtolower($u['status']);
                                    ?>
                                    <span class="status-pill <?php echo $statusClass; ?>">
                                        <?php echo $u['status']; ?>
                                    </span>
                                </td>

                                <td>
                                    <div class="action-area">
                                        <div class="dropdown">
                                            <button class="premium-action-btn dropdown-toggle" type="button"
                                                data-bs-toggle="dropdown">
                                                Manage
                                            </button>

                                            <ul class="dropdown-menu premium-dropdown">
                                                <li><a class="dropdown-item" href="#">Suspend</a></li>
                                                <li><a class="dropdown-item" href="#">Block</a></li>
                                                <li><a class="dropdown-item" href="#">Restrict</a></li>
                                                <li><a class="dropdown-item" href="#">Set Spending Limit</a></li>
                                            </ul>
                                        </div>

                                        <button class="freeze-btn">
                                            Toggle Wallet Freeze
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>

                    </table>
                </div>

            </section>

        </main>

    </div>

    
    <div class="modal fade" id="addUserModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-xl">
            <div class="modal-content add-user-modal">

                <div class="modal-header add-user-modal-header">
                    <h5 class="modal-title">
                        <span class="modal-title-icon"><i class="fa-solid fa-user-plus"></i></span>
                        Create New User
                    </h5>

                    <button type="button" class="btn-close add-user-close" data-bs-dismiss="modal"
                        aria-label="Close"></button>
                </div>

                <form action="<?= ADMIN_URL ?>/add_user.php" method="POST">

                    <div class="modal-body add-user-modal-body">

                        <div class="row g-4">

                            <div class="col-md-6">
                                <label class="add-user-label">First Name *</label>
                                <input type="text" name="first_name" class="add-user-input" required>
                            </div>

                            <div class="col-md-6">
                                <label class="add-user-label">Last Name *</label>
                                <input type="text" name="last_name" class="add-user-input" required>
                            </div>

                            <div class="col-md-6">
                                <label class="add-user-label">Email *</label>
                                <input type="email" name="email" class="add-user-input" required>
                            </div>

                            <div class="col-md-6">
                                <label class="add-user-label">Phone</label>
                                <input type="text" name="phone" class="add-user-input" placeholder="09XX-XXX-XXXX">
                            </div>

                            <div class="col-md-6">
                                <label class="add-user-label">Role *</label>
                                <select name="role" class="add-user-input" required>
                                    <option value="student">Student</option>
                                    <option value="merchant">Merchant</option>
                                    <option value="finance">Finance</option>
                                    <option value="parent">Parent</option>
                                    <option value="visitor">Visitor</option>
                                </select>
                            </div>

                            <div class="col-md-6" id="student-id-field" style="display:none">
                                <label class="add-user-label">Student ID</label>
                                <div style="padding:10px 14px;background:#f0fdf4;border:1.5px solid #bbf7d0;border-radius:8px;font-size:13px;color:#15803d;font-weight:600">
                                    <i class="fa-solid fa-wand-magic-sparkles me-1"></i>
                                    Auto-generated as <strong>GJC<?= date('Y') ?>-XXXX</strong>
                                </div>
                            </div>

                            <div class="col-12">
                                <label class="add-user-label">Initial Password *</label>
                                <input type="password" name="password" class="add-user-input" required>
                                <p class="add-user-help">User should change this on first login.</p>
                            </div>

                        </div>

                    </div>

                    <div class="modal-footer add-user-modal-footer">
                        <button type="button" class="modal-cancel-btn" data-bs-dismiss="modal">
                            Cancel
                        </button>

                        <button type="submit" class="modal-create-btn">
                            Create Account
                        </button>
                    </div>

                </form>

            </div>
        </div>
    </div>

    <script src="<?= JS_URL ?>/bootstrap.bundle.min.js"></script>
    <?php require __DIR__ . '/../includes/partials/datatables_assets.php'; ?>

    <script>
    function toggleSidebar() {
        document.getElementById("sidebar").classList.toggle("collapsed");
    }

    document.addEventListener('DOMContentLoaded', function () {
        const roleSelect = document.querySelector('[name="role"]');
        const studentIdField = document.getElementById('student-id-field');
        if (roleSelect && studentIdField) {
            function toggleStudentId() {
                studentIdField.style.display = roleSelect.value === 'student' ? '' : 'none';
            }
            roleSelect.addEventListener('change', toggleStudentId);
            toggleStudentId();
        }
    });
    </script>

</body>

</html>
