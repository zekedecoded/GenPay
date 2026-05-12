<?php
require_once __DIR__ . '/../connection/config.php';
require_once __DIR__ . '/../connection/pdo.php';

$query = "
    SELECT 
        u.userID,
        u.first_name,
        u.last_name,
        u.email,
        r.role_name as role,
        COALESCE(w.balance, 0) as balance
    FROM users u
    LEFT JOIN role r ON u.roleID = r.roleID
    LEFT JOIN wallet w ON u.userID = w.userID
    ORDER BY u.userID DESC
";
$stmt = $db->prepare($query);
$stmt->execute();
$dbUsers = $stmt->fetchAll(PDO::FETCH_ASSOC);

$users = [];
foreach ($dbUsers as $u) {
    $roleName = ucfirst($u['role'] ?? 'User');
    $schoolIdPrefix = 'GJC';
    if ($u['role'] === 'merchant') $schoolIdPrefix = 'MER';
    if ($u['role'] === 'admin') $schoolIdPrefix = 'ADM';
    
    $users[] = [
        "name" => trim($u['first_name'] . ' ' . $u['last_name']),
        "role" => $roleName,
        "school_id" => $schoolIdPrefix . '-' . str_pad($u['userID'], 4, '0', STR_PAD_LEFT),
        "email" => $u['email'],
        "balance" => $u['balance'],
        "status" => "Active"
    ];
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Users Management | GJC EduPay</title>

    <link rel="stylesheet" href="<?= CSS_URL ?>/bootstrap.min.css">
    <link rel="stylesheet" href="<?= CSS_URL ?>/admin.css">
    <link rel="stylesheet" href="<?= CSS_URL ?>/users.css">
    <link rel="stylesheet" href="<?= CSS_URL ?>/responsive.css">

    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap"
        rel="stylesheet">
</head>

<body>

    <div class="admin-layout">

        <aside class="admin-sidebar" id="sidebar">

            <div class="brand-box">
                <div class="brand-logo">
                    <img src="<?= ICONS_URL ?>/edupay.png" alt="Logo">
                </div>

                <div class="brand-text">
                    <h4>GJC EduPay</h4>
                    <span>Admin Portal</span>
                </div>
            </div>

            <nav class="sidebar-menu">
                <a href="<?= ADMIN_URL ?>/dashboard.php">
                    <img src="<?= ICONS_URL ?>/dashboard.png" class="nav-icon" alt="">
                    <span class="nav-text">Dashboard</span>
                </a>

                <a href="<?= ADMIN_URL ?>/users.php" class="active">
                    <img src="<?= ICONS_URL ?>/users.png" class="nav-icon" alt="">
                    <span class="nav-text">Users</span>
                </a>

                <a href="<?= ADMIN_URL ?>/topups.php">
                    <img src="<?= ICONS_URL ?>/topups.png" class="nav-icon" alt="">
                    <span class="nav-text">Top-ups</span>
                </a>

                <a href="<?= ADMIN_URL ?>/encashments.php">
                    <img src="<?= ICONS_URL ?>/encashments.png" class="nav-icon" alt="">
                    <span class="nav-text">Encashments</span>
                </a>

                <a href="<?= ADMIN_URL ?>/transactions.php">
                    <img src="<?= ICONS_URL ?>/transactions.png" class="nav-icon" alt="">
                    <span class="nav-text">Transactions</span>
                </a>

                <a href="<?= ADMIN_URL ?>/visitors.php">
                    <img src="<?= ICONS_URL ?>/visitors.png" class="nav-icon" alt="">
                    <span class="nav-text">Visitors</span>
                </a>

                <a href="<?= ADMIN_URL ?>/settings.php">
                    <img src="<?= ICONS_URL ?>/settings.png" class="nav-icon" alt="">
                    <span class="nav-text">Settings</span>
                </a>
            </nav>

            <a href="<?= BASE_URL ?>/logout.php" class="logout-btn">
                <img src="<?= ICONS_URL ?>/logout.png" class="logout-icon" alt="">
                <span>Logout</span>
            </a>

        </aside>

        <main class="admin-main">

            <header class="topbar">
                <button class="menu-btn" onclick="toggleSidebar()">☰</button>

                <div>
                    <h1>Users Management</h1>
                    <p>Manage users, roles, status, wallet access, and account controls.</p>
                </div>

                <div class="admin-user">
                    <span>Admin</span>
                    <div class="avatar">
                        <img src="<?= ICONS_URL ?>/admin.png" alt="Admin">
                    </div>
                </div>
            </header>

            <section class="users-command-panel mb-4">

                <div class="users-panel-header">
                    <div>
                        <h3>Users Directory</h3>
                        <p>Search and filter accounts across the GJC EduPay system.</p>
                    </div>

                    <button type="button" class="add-user-btn" data-bs-toggle="modal" data-bs-target="#addUserModal">
                        <span>+</span> Add User
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
                            <option value="">All Roles</option>
                            <option value="admin">Admin</option>
                            <option value="user">User</option>
                            <option value="merchant">Merchant</option>
                            <option value="parent">Parent</option>
                            <option value="visitor">Visitor</option>
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
                    <table class="table users-table align-middle">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Role</th>
                                <th>School ID</th>
                                <th>Email</th>
                                <th>Balance</th>
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

                                <td class="balance-text">
                                    ₱<?php echo number_format($u['balance'], 2); ?>
                                </td>

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

    <!-- ADD USER MODAL -->
    <div class="modal fade" id="addUserModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-xl">
            <div class="modal-content add-user-modal">

                <div class="modal-header add-user-modal-header">
                    <h5 class="modal-title">
                        <span class="modal-title-icon">+</span>
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
                                    <option value="parent">Parent</option>
                                    <option value="visitor">Visitor</option>
                                </select>
                            </div>

                            <div class="col-md-6">
                                <label class="add-user-label">
                                    School ID <span>(Students)</span>
                                </label>
                                <input type="text" name="school_id" class="add-user-input" placeholder="2024-XXXXX">
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

    <script>
    function toggleSidebar() {
        document.getElementById("sidebar").classList.toggle("collapsed");
    }
    </script>

</body>

</html>