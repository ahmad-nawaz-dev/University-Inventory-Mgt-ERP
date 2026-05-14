<?php
// dashboards/super_admin/manage_roles.php
require_once __DIR__ . '/../../includes/header.php';

$message = '';
$error = '';

// Add or edit role
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF for all POST actions
    if (!check_csrf($_POST['csrf_token'] ?? '')) {
        $error = 'CSRF token validation failed.';
    } else {
        if (isset($_POST['add_role']) || isset($_POST['edit_role'])) {
            $role_name = trim($_POST['role_name'] ?? '');
            
            if (empty($role_name)) {
                $error = 'Role name is required.';
            } else {
                $role_key = slugify($role_name);
                $pdo = getPDO();
                
                if (isset($_POST['edit_role']) && !empty($_POST['role_id'])) {
                    // Update existing role
                    $role_id = (int)$_POST['role_id'];
                    
                    // Check if it's a system role
                    $stmt = $pdo->prepare("SELECT is_system_role FROM sys_roles WHERE id = ?");
                    $stmt->execute([$role_id]);
                    $role_info = $stmt->fetch();
                    
                    if ($role_info['is_system_role']) {
                        $error = 'Cannot modify system roles.';
                    } else {
                        $stmt = $pdo->prepare("UPDATE sys_roles SET role_name=?, role_key=? WHERE id=?");
                        if ($stmt->execute([$role_name, $role_key, $role_id])) {
                            $message = 'Role updated successfully!';
                        } else {
                            $error = 'Error updating role.';
                        }
                    }
                } else {
                    // Insert new role
                    $stmt = $pdo->prepare("INSERT INTO sys_roles (role_name, role_key) VALUES (?, ?)");
                    if ($stmt->execute([$role_name, $role_key])) {
                        $message = 'Role added successfully!';
                    } else {
                        $error = 'Error adding role.';
                    }
                }
            }
        }
        
        // Delete role
        if (isset($_POST['delete_role']) && !empty($_POST['delete_id'])) {
            $pdo = getPDO();
            $delete_id = (int)$_POST['delete_id'];
            
            // Check if it's a system role
            $stmt = $pdo->prepare("SELECT is_system_role FROM sys_roles WHERE id = ?");
            $stmt->execute([$delete_id]);
            $role_info = $stmt->fetch();
            
            if ($role_info['is_system_role']) {
                $error = 'Cannot delete system roles.';
            } else {
                // Migrate users with this role to 'suspended' or deactivate them
                $stmt = $pdo->prepare("UPDATE users SET is_active=0 WHERE role = (SELECT role_key FROM sys_roles WHERE id = ?)");
                $stmt->execute([$delete_id]);
                
                // Remove role assignments
                $stmt = $pdo->prepare("DELETE FROM role_access WHERE role_key = (SELECT role_key FROM sys_roles WHERE id = ?)");
                $stmt->execute([$delete_id]);
                
                // Delete the role
                $stmt = $pdo->prepare("DELETE FROM sys_roles WHERE id = ?");
                if ($stmt->execute([$delete_id])) {
                    $message = 'Role deleted successfully!';
                } else {
                    $error = 'Error deleting role.';
                }
            }
        }
    }
}

// Fetch all roles
$pdo = getPDO();
$stmt = $pdo->query("SELECT * FROM sys_roles ORDER BY role_name ASC");
$roles = $stmt->fetchAll();

// Editing existing role
$editing_role = null;
if (isset($_GET['edit']) && !empty($_GET['edit'])) {
    $edit_id = (int)$_GET['edit'];
    $stmt = $pdo->prepare("SELECT * FROM sys_roles WHERE id = ?");
    $stmt->execute([$edit_id]);
    $editing_role = $stmt->fetch();
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
                        <i class="fas fa-user-tag text-primary mr-1"></i> System Roles
                    </h3>
                    <div class="card-tools m-0">
                        <a href="manage_access.php" class="btn btn-sm btn-info mr-2">
                            <i class="fas fa-user-shield me-1"></i> Permission Matrix
                        </a>
                        <button type="button" class="btn btn-sm btn-success shadow-sm" data-bs-toggle="modal" data-bs-target="#roleModal">
                            <i class="fas fa-plus-circle mr-1"></i> Add New Role
                        </button>
                    </div>
                </div>
            </div>
            
            <div class="card-body p-0">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>Role Name</th>
                            <th>Role Key</th>
                            <th>Type</th>
                            <th>Users Count</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($roles as $role): ?>
                            <tr>
                                <td><?= escape($role['role_name']) ?></td>
                                <td><code><?= escape($role['role_key']) ?></code></td>
                                <td>
                                    <?php if ($role['is_system_role']): ?>
                                        <span class="badge badge-danger">System</span>
                                    <?php else: ?>
                                        <span class="badge badge-info">Custom</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php 
                                    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE role = ?");
                                    $stmt->execute([$role['role_key']]);
                                    echo $stmt->fetchColumn();
                                    ?>
                                </td>
                                <td>
                                    <?php if (!$role['is_system_role']): ?>
                                        <a href="?edit=<?= $role['id'] ?>" class="btn btn-sm btn-primary">Edit</a>
                                        <button type="button" class="btn btn-sm btn-danger" 
                                                onclick="confirmDelete(<?= $role['id'] ?>)">Delete</button>
                                    <?php else: ?>
                                        <span class="text-muted">System Role</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- ROLE REGISTRATION MODAL -->
<div class="modal fade" id="roleModal" tabindex="-1" aria-labelledby="roleModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="roleModalLabel">
                    <i class="fas fa-<?= $editing_role ? 'edit' : 'plus-circle' ?> mr-2"></i>
                    <?= $editing_role ? 'Update System Role' : 'Add New Custom Role' ?>
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="post">
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                    <div class="mb-3">
                        <label for="role_name" class="form-label">Role Name *</label>
                        <input type="text" class="form-control" id="role_name" name="role_name" 
                               value="<?= $editing_role ? escape($editing_role['role_name']) : '' ?>" required
                               placeholder="e.g. Finance Officer, Junior Assistant">
                        <small class="form-text text-muted">A unique slug (key) will be generated automatically.</small>
                    </div>
                </div>
                <div class="modal-footer justify-content-between">
                    <button type="button" class="btn btn-default" data-bs-dismiss="modal">Close</button>
                    <div>
                        <?php if ($editing_role): ?>
                            <input type="hidden" name="role_id" value="<?= $editing_role['id'] ?>">
                            <a href="manage_roles.php" class="btn btn-secondary mr-2">Cancel</a>
                        <?php endif; ?>
                        <button type="submit" name="<?= $editing_role ? 'edit_role' : 'add_role' ?>" class="btn btn-primary px-4 shadow-sm">
                            <i class="fas fa-save mr-1"></i> <?= $editing_role ? 'Update Role' : 'Save Role' ?>
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Auto-open modal for editing or errors
document.addEventListener("DOMContentLoaded", function() {
    <?php if ($editing_role || $error): ?>
        var roleModal = new bootstrap.Modal(document.getElementById('roleModal'));
        roleModal.show();
    <?php endif; ?>
});
function confirmDelete(roleId) {
    if (confirm('Are you sure you want to delete this role? This will deactivate all users with this role and remove all associated permissions.')) {
        const form = document.createElement('form');
        form.method = 'post';
        form.style.display = 'none';
        form.innerHTML = '<input type="hidden" name="delete_id" value="' + roleId + '">' +
                         '<input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">' +
                         '<input type="hidden" name="delete_role" value="1">';
        document.body.appendChild(form);
        form.submit();
    }
}
</script>

<?php
include __DIR__ . '/../../includes/footer.php';
?>