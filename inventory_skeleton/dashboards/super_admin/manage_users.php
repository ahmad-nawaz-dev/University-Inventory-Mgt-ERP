<?php
// dashboards/super_admin/manage_users.php
require_once __DIR__ . '/../../includes/header.php';

$message = '';
$error = '';

// Add or edit user
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_user']) || isset($_POST['edit_user'])) {
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $role = trim($_POST['role'] ?? '');
        $department = !empty($_POST['department']) ? trim($_POST['department']) : null;
        $identity_no = trim($_POST['identity_no'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';

        if (empty($name) || empty($email) || empty($role)) {
            $error = 'Name, email, and role are required.';
        } elseif ($role !== 'super_admin' && empty($department)) {
            $error = 'Department is required for this role.';
        } elseif ($password && $password !== $confirm_password) {
            $error = 'Passwords do not match.';
        } else {
            $pdo = getPDO();

            if (isset($_POST['edit_user']) && !empty($_POST['user_id'])) {
                // Update existing user
                $user_id = (int)$_POST['user_id'];

                if ($password) {
                    // Update with new password
                    $hashed_password = password_hash($password, PASSWORD_BCRYPT);
                    $stmt = $pdo->prepare("UPDATE users SET name=?, email=?, role=?, department=?, identity_no=?, password=? WHERE id=?");
                    $params = [$name, $email, $role, $department, $identity_no, $hashed_password, $user_id];
                } else {
                    // Update without changing password
                    $stmt = $pdo->prepare("UPDATE users SET name=?, email=?, role=?, department=?, identity_no=? WHERE id=?");
                    $params = [$name, $email, $role, $department, $identity_no, $user_id];
                }

                if ($stmt->execute($params)) {
                    $message = 'User updated successfully!';
                } else {
                    $error = 'Error updating user.';
                }
            } else {
                // Check if email already exists
                $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
                $stmt->execute([$email]);
                if ($stmt->fetch()) {
                    $error = 'A user with this email already exists.';
                } else {
                    // Insert new user
                    $hashed_password = password_hash($password, PASSWORD_BCRYPT);
                    $stmt = $pdo->prepare("INSERT INTO users (name, email, role, department, identity_no, password) VALUES (?, ?, ?, ?, ?, ?)");
                    if ($stmt->execute([$name, $email, $role, $department, $identity_no, $hashed_password])) {
                        $message = 'User added successfully!';
                    } else {
                        $error = 'Error adding user.';
                    }
                }
            }
        }
    }

    // Toggle user active status
    if (isset($_POST['toggle_status']) && !empty($_POST['user_id'])) {
        $pdo = getPDO();
        $user_id = (int)$_POST['user_id'];

        $stmt = $pdo->prepare("SELECT is_active FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch();

        $new_status = $user['is_active'] ? 0 : 1;
        $stmt = $pdo->prepare("UPDATE users SET is_active = ? WHERE id = ?");
        if ($stmt->execute([$new_status, $user_id])) {
            $message = 'User status updated successfully!';
        } else {
            $error = 'Error updating user status.';
        }
    }

    // Delete user
    if (isset($_POST['delete_user']) && !empty($_POST['delete_id'])) {
        $pdo = getPDO();
        $delete_id = (int)$_POST['delete_id'];

        // Don't actually delete, just deactivate
        $stmt = $pdo->prepare("UPDATE users SET is_active = 0 WHERE id = ?");
        if ($stmt->execute([$delete_id])) {
            $message = 'User deactivated successfully!';
        } else {
            $error = 'Error deactivating user.';
        }
    }
}

// Fetch all users with optional department filter and search
$pdo = getPDO();
$filter_dept = trim($_GET['filter_dept'] ?? '');
$search = trim($_GET['search'] ?? '');
$limit = 10;
$page = max(1, (int)($_GET['page'] ?? 1));
$offset = ($page - 1) * $limit;

$where = "WHERE 1=1";
$params = [];

if (!empty($filter_dept)) {
    $where .= " AND u.department = ?";
    $params[] = $filter_dept;
}

if (!empty($search)) {
    $where .= " AND (u.name LIKE ? OR u.email LIKE ? OR u.identity_no LIKE ?)";
    $search_param = '%' . $search . '%';
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

// Count total
$count_stmt = $pdo->prepare("SELECT COUNT(*) FROM users u $where");
$count_stmt->execute($params);
$total_records = $count_stmt->fetchColumn();
$total_pages = ceil($total_records / $limit);

$stmt = $pdo->prepare("SELECT u.*, r.role_name FROM users u LEFT JOIN sys_roles r ON u.role = r.role_key $where ORDER BY u.name ASC LIMIT ? OFFSET ?");
$param_index = 1;
foreach ($params as $p) {
    $stmt->bindValue($param_index++, $p);
}
$stmt->bindValue($param_index++, $limit, PDO::PARAM_INT);
$stmt->bindValue($param_index++, $offset, PDO::PARAM_INT);
$stmt->execute();
$users = $stmt->fetchAll();

// Fetch all roles for the form
$stmt = $pdo->query("SELECT role_key, role_name FROM sys_roles WHERE is_system_role = 0 ORDER BY role_name ASC");
$roles = $stmt->fetchAll();

// Fetch all departments for the form
$stmt = $pdo->query("SELECT name FROM university_departments ORDER BY name ASC");
$departments = $stmt->fetchAll(PDO::FETCH_COLUMN);

// Editing existing user
$editing_user = null;
if (isset($_GET['edit']) && !empty($_GET['edit'])) {
    $edit_id = (int)$_GET['edit'];
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$edit_id]);
    $editing_user = $stmt->fetch();
}
?>

<div class="row">
    <div class="col-md-12">
        <?php if ($message): ?>
            <div class="alert alert-success alert-dismissible"><button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button><?= escape($message) ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible"><button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button><?= escape($error) ?></div>
        <?php endif; ?>



        <div class="card shadow-sm">
            <div class="card-header bg-white border-bottom">
                <div class="d-flex justify-content-between align-items-center">
                    <h3 class="card-title font-weight-bold m-0 text-dark">
                        <i class="fas fa-users text-primary mr-1"></i> User Management
                    </h3>
                    <div class="card-tools m-0">
                        <button type="button" class="btn btn-sm btn-success shadow-sm" data-bs-toggle="modal" data-bs-target="#userModal">
                            <i class="fas fa-user-plus mr-1"></i> Add New User
                        </button>
                    </div>
                </div>
            </div>
            <div class="card-body">
                <form method="GET" class="row g-2 align-items-end mb-4 nc-animate-in">
                    <div class="col-md-4">
                        <label for="filter_dept" class="form-label small">Filter by Department</label>
                        <select name="filter_dept" id="filter_dept" class="form-select form-select-sm">
                            <option value="">All Departments</option>
                            <?php foreach ($departments as $dept): ?>
                                <option value="<?= escape($dept) ?>" <?= $filter_dept === $dept ? 'selected' : '' ?>>
                                    <?= escape($dept) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label for="search" class="form-label small">Search User</label>
                        <input type="text" id="search" name="search" class="form-control form-control-sm" placeholder="Search name, email..." value="<?= escape($search) ?>">
                    </div>
                    <div class="col-md-4">
                        <button type="submit" class="btn btn-sm btn-primary px-3 shadow-sm">Filter Results</button>
                        <?php if ($filter_dept || $search): ?>
                            <a href="manage_users.php" class="btn btn-sm btn-outline-secondary ml-1 px-3">Clear</a>
                        <?php endif; ?>
                    </div>
                </form>

                <div class="table-responsive">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th>Department</th>
                            <th>Identity No</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $user): ?>
                            <tr>
                                <td><?= escape($user['name']) ?></td>
                                <td><?= escape($user['email']) ?></td>
                                <td>
                                    <span class="badge badge-<?= roleBadgeColor($user['role']) ?>">
                                        <?= escape($user['role_name'] ?? $user['role']) ?>
                                    </span>
                                </td>
                                <td><?= escape($user['department'] ?? '-') ?></td>
                                <td><?= escape($user['identity_no'] ?? '-') ?></td>
                                <td>
                                    <?php if ($user['is_active']): ?>
                                        <span class="badge badge-success">Active</span>
                                    <?php else: ?>
                                        <span class="badge badge-danger">Inactive</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a href="?edit=<?= $user['id'] ?>" class="btn btn-sm btn-primary">Edit</a>

                                    <form method="post" style="display: inline;">
                                        <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                        <button type="submit" name="toggle_status" class="btn btn-sm btn-<?= $user['is_active'] ? 'warning' : 'success' ?>">
                                            <?= $user['is_active'] ? 'Deactivate' : 'Activate' ?>
                                        </button>
                                    </form>

                                    <?php if ($user['id'] != currentUser()['id']): ?>
                                        <button type="button" class="btn btn-sm btn-danger"
                                                onclick="confirmDelete(<?= $user['id'] ?>)">Delete</button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
            <div class="card-footer clearfix">
                <span class="float-left text-muted">Showing <?= count($users) ?> of <?= $total_records ?> users</span>
                <ul class="pagination pagination-sm m-0 float-right">
                    <?php if ($page > 1): ?>
                        <li class="page-item">
                            <a class="page-link" href="?page=<?= $page - 1 ?>&filter_dept=<?= urlencode($filter_dept) ?>&search=<?= urlencode($search) ?>">«</a>
                        </li>
                    <?php endif; ?>
                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                            <a class="page-link" href="?page=<?= $i ?>&filter_dept=<?= urlencode($filter_dept) ?>&search=<?= urlencode($search) ?>"><?= $i ?></a>
                        </li>
                    <?php endfor; ?>
                    <?php if ($page < $total_pages): ?>
                        <li class="page-item">
                            <a class="page-link" href="?page=<?= $page + 1 ?>&filter_dept=<?= urlencode($filter_dept) ?>&search=<?= urlencode($search) ?>">»</a>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- USER REGISTRATION MODAL -->
<div class="modal fade" id="userModal" tabindex="-1" aria-labelledby="userModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="userModalLabel">
                    <i class="fas fa-<?= $editing_user ? 'edit' : 'user-plus' ?> mr-2"></i>
                    <?= $editing_user ? 'Update User Details' : 'Register New System User' ?>
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="post">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="name" class="form-label">Full Name *</label>
                                <input type="text" class="form-control" id="name" name="name"
                                       value="<?= $editing_user ? escape($editing_user['name']) : '' ?>" required>
                            </div>

                            <div class="mb-3">
                                <label for="email" class="form-label">Email *</label>
                                <input type="email" class="form-control" id="email" name="email"
                                       value="<?= $editing_user ? escape($editing_user['email']) : '' ?>" required>
                            </div>

                            <div class="mb-3">
                                <label for="identity_no" class="form-label">Identity No (Employee/Student ID)</label>
                                <input type="text" class="form-control" id="identity_no" name="identity_no"
                                       value="<?= $editing_user ? escape($editing_user['identity_no']) : '' ?>">
                            </div>

                            <div class="mb-3">
                                <label for="role" class="form-label">System Role *</label>
                                <select class="form-select" id="role" name="role" required>
                                    <option value="">— Select Role —</option>
                                    <?php foreach ($roles as $role): ?>
                                        <option value="<?= $role['role_key'] ?>"
                                                <?= $editing_user && $editing_user['role'] == $role['role_key'] ? 'selected' : '' ?>>
                                            <?= escape($role['role_name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="department" class="form-label">Department <span id="dept_req_star" style="display:none;">*</span></label>
                                <select class="form-select" id="department" name="department">
                                    <option value="">None / Unassigned</option>
                                    <?php foreach ($departments as $dept): ?>
                                        <option value="<?= escape($dept) ?>"
                                                <?= $editing_user && ($editing_user['department'] ?? null) == $dept ? 'selected' : '' ?>>
                                            <?= escape($dept) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <small class="form-text text-muted" id="dept_hint">Required for most staff roles.</small>
                            </div>

                            <div class="mb-3">
                                <label for="password" class="form-label">
                                    <?= $editing_user ? 'New Password' : 'Password *' ?>
                                </label>
                                <input type="password" class="form-control" id="password" name="password"
                                       <?= $editing_user ? '' : 'required' ?> placeholder="<?= $editing_user ? 'Leave blank to keep current' : '' ?>">
                            </div>

                            <div class="mb-3">
                                <label for="confirm_password" class="form-label">Confirm Password *</label>
                                <input type="password" class="form-control" id="confirm_password" name="confirm_password"
                                       <?= $editing_user ? '' : 'required' ?>>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer justify-content-between">
                    <button type="button" class="btn btn-default" data-bs-dismiss="modal">Close</button>
                    <div>
                        <?php if ($editing_user): ?>
                            <input type="hidden" name="user_id" value="<?= $editing_user['id'] ?>">
                            <a href="manage_users.php" class="btn btn-secondary mr-2">Cancel</a>
                        <?php endif; ?>
                        <button type="submit" name="<?= $editing_user ? 'edit_user' : 'add_user' ?>" class="btn btn-<?= $editing_user ? 'primary' : 'success' ?> shadow-sm px-4">
                            <i class="fas fa-check-circle mr-1"></i> <?= $editing_user ? 'Update User' : 'Register User' ?>
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
<script>
document.getElementById('role').addEventListener('change', function() {
    const role = this.value;
    const deptStar = document.getElementById('dept_req_star');
    const deptSelect = document.getElementById('department');
    if (role === 'super_admin' || role === '') {
        deptStar.style.display = 'none';
        deptSelect.required = false;
    } else {
        deptStar.style.display = 'inline';
        deptStar.style.color = 'red';
        deptSelect.required = true;
    }
});
// Trigger on load
document.addEventListener("DOMContentLoaded", function() {
    document.getElementById('role').dispatchEvent(new Event('change'));
    
    <?php if ($editing_user || $error): ?>
        var userModal = new bootstrap.Modal(document.getElementById('userModal'));
        userModal.show();
    <?php endif; ?>
});

function confirmDelete(userId) {
    if (confirm('Are you sure you want to delete this user? This will deactivate the user account.')) {
        const form = document.createElement('form');
        form.method = 'post';
        form.style.display = 'none';
        form.innerHTML = '<input type="hidden" name="delete_id" value="' + userId + '">' +
                         '<input type="hidden" name="delete_user" value="1">';
        document.body.appendChild(form);
        form.submit();
    }
}
</script>

<?php
include __DIR__ . '/../../includes/footer.php';
?>