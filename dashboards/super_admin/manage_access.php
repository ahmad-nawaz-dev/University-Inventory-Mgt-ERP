<?php
// dashboards/super_admin/manage_access.php
require_once __DIR__ . '/../../includes/header.php';

// Only Super Admin can access (already handled by header.php redirect logic, 
// but good to have an extra check if needed)
if ($user['role'] !== 'super_admin') {
    header("Location: " . BASE_URL . "/dashboards/index.php");
    exit();
}

$message = '';
$error = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_permissions'])) {
    if (!check_csrf($_POST['csrf_token'] ?? '')) {
        $error = 'CSRF token validation failed.';
    } else {
        try {
            $pdo->beginTransaction();
            
            // Current permissions in a flat array for easy comparison/sync
            // But actually it's easier to just wipe and re-insert if we are doing a bulk update from a matrix
            // However, to be safer and more efficient, we could only delete/insert changes.
            // For a small matrix, wipe and re-insert is often cleaner.
            
            // Delete all current access (or we could be more specific, but this is a central management page)
            $pdo->exec("DELETE FROM role_access");
            
            if (isset($_POST['permissions']) && is_array($_POST['permissions'])) {
                $stmt = $pdo->prepare("INSERT INTO role_access (role_key, page_id) VALUES (?, ?)");
                foreach ($_POST['permissions'] as $role_key => $pages) {
                    foreach ($pages as $page_id => $val) {
                        $stmt->execute([$role_key, (int)$page_id]);
                    }
                }
            }
            
            $pdo->commit();
            $message = 'Permissions updated successfully!';
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = 'Error updating permissions: ' . $e->getMessage();
        }
    }
}

// Fetch all roles
$stmt = $pdo->query("SELECT * FROM sys_roles ORDER BY role_name ASC");
$roles = $stmt->fetchAll();

// Fetch all pages, ordered by parent and sort order
$stmt = $pdo->query("SELECT * FROM sys_pages ORDER BY parent_id, sort_order, page_name");
$all_pages = $stmt->fetchAll();

// Organize pages into hierarchy
$pages_by_parent = [];
foreach ($all_pages as $p) {
    $parent_id = $p['parent_id'] ?? 0;
    $pages_by_parent[$parent_id][] = $p;
}

// Fetch current permissions
$stmt = $pdo->query("SELECT role_key, page_id FROM role_access");
$current_permissions = [];
while ($row = $stmt->fetch()) {
    $current_permissions[$row['role_key']][$row['page_id']] = true;
}

/**
 * Recursive function to render page rows
 */
function renderPageRow($pages_by_parent, $parent_id, $roles, $current_permissions, $level = 0) {
    if (!isset($pages_by_parent[$parent_id])) return;
    
    foreach ($pages_by_parent[$parent_id] as $page) {
        echo '<tr>';
        echo '<td>';
        echo str_repeat('&nbsp;&nbsp;&nbsp;&nbsp;', $level);
        if ($level > 0) echo '└─ ';
        echo escape($page['page_name']);
        echo '<br><small class="text-muted">' . str_repeat('&nbsp;&nbsp;&nbsp;&nbsp;', $level) . ($level > 0 ? '&nbsp;&nbsp;&nbsp;' : '') . escape($page['page_url']) . '</small>';
        echo '</td>';
        
        foreach ($roles as $role) {
            $checked = isset($current_permissions[$role['role_key']][$page['id']]) ? 'checked' : '';
            echo '<td class="text-center">';
            echo '<div class="form-check">';
            echo '<input class="form-check-input position-static" type="checkbox" ';
            echo 'name="permissions[' . escape($role['role_key']) . '][' . $page['id'] . ']" value="1" ' . $checked . '>';
            echo '</div>';
            echo '</td>';
        }
        echo '</tr>';
        
        // Render children
        renderPageRow($pages_by_parent, $page['id'], $roles, $current_permissions, $level + 1);
    }
}
?>

<div class="row">
    <div class="col-md-12">
        <div class="card card-outline card-primary shadow-sm">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-user-shield me-2"></i> Role-Page Permission Matrix</h3>
                <div class="card-tools">
                    <button type="submit" form="permissionForm" name="save_permissions" class="btn btn-primary btn-sm">
                        <i class="fas fa-save me-1"></i> Save Changes
                    </button>
                </div>
            </div>
            <div class="card-body p-0">
                <?php if ($message): ?>
                    <div class="alert alert-success m-3"><?= escape($message) ?></div>
                <?php endif; ?>
                <?php if ($error): ?>
                    <div class="alert alert-danger m-3"><?= escape($error) ?></div>
                <?php endif; ?>
                
                <form id="permissionForm" method="post">
                    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                    <div class="table-responsive">
                        <table class="table table-hover table-bordered mb-0">
                            <thead class="bg-light">
                                <tr>
                                    <th style="min-width: 250px;">Page / Navigation Item</th>
                                    <?php foreach ($roles as $role): ?>
                                        <th class="text-center">
                                            <?= escape($role['role_name']) ?><br>
                                            <small class="badge badge-secondary"><?= escape($role['role_key']) ?></small>
                                        </th>
                                    <?php endforeach; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php renderPageRow($pages_by_parent, 0, $roles, $current_permissions); ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <div class="card-footer border-top text-right p-3">
                        <button type="submit" name="save_permissions" class="btn btn-primary">
                            <i class="fas fa-save me-1"></i> Save Access Permissions
                        </button>
                    </div>
                </form>
            </div>
        </div>
        
        <div class="alert alert-info mt-3 shadow-sm border-0">
            <h5><i class="icon fas fa-info-circle"></i> Management Tips</h5>
            <ul class="mb-0">
                <li>Checkboxes indicate which roles have access to specific pages or sidebar modules.</li>
                <li>Changes take effect immediately for users on their next page load or sidebar refresh.</li>
                <li>Ensure at least one role has access to crucial administrative pages.</li>
            </ul>
        </div>
    </div>
</div>

<style>
.table th, .table td { vertical-align: middle !important; }
.form-check-input { width: 1.25rem; height: 1.25rem; cursor: pointer; }
.table-hover tbody tr:hover { background-color: rgba(0,123,255,0.05); }
.bg-light { background-color: #f8f9fa !important; }
</style>

<?php
include __DIR__ . '/../../includes/footer.php';
?>
