<?php
// dashboards/super_admin/manage_pages.php
require_once __DIR__ . '/../../includes/header.php';

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_page']) || isset($_POST['edit_page'])) {
        // Validate CSRF
        if (!check_csrf($_POST['csrf_token'] ?? '')) {
            $error = 'CSRF token validation failed.';
        } else {
            $page_name = trim($_POST['page_name'] ?? '');
            $page_url = trim($_POST['page_url'] ?? '');
            $icon_class = trim($_POST['icon_class'] ?? '');
            $parent_id = !empty($_POST['parent_id']) ? (int)$_POST['parent_id'] : null;
            $sort_order = (int)($_POST['sort_order'] ?? 0);
            
            if (empty($page_name) || empty($page_url)) {
                $error = 'Page name and URL are required.';
            } else {
                try {
                    if (isset($_POST['edit_page']) && !empty($_POST['page_id'])) {
                        // Update existing page
                        $stmt = $pdo->prepare("UPDATE sys_pages SET page_name=?, page_url=?, icon_class=?, parent_id=?, sort_order=? WHERE id=?");
                        $result = $stmt->execute([$page_name, $page_url, $icon_class, $parent_id, $sort_order, (int)$_POST['page_id']]);
                        
                        if ($result) {
                            // Handle role assignments
                            $stmt = $pdo->prepare("DELETE FROM role_access WHERE page_id = ?");
                            $stmt->execute([(int)$_POST['page_id']]);
                            
                            if (!empty($_POST['roles'])) {
                                foreach ($_POST['roles'] as $role) {
                                    $stmt = $pdo->prepare("INSERT INTO role_access (role_key, page_id) VALUES (?, ?)");
                                    $stmt->execute([$role, (int)$_POST['page_id']]);
                                }
                            }
                            
                            $message = 'Page updated successfully!';
                        }
                    } else {
                        // Insert new page
                        $stmt = $pdo->prepare("INSERT INTO sys_pages (page_name, page_url, icon_class, parent_id, sort_order) VALUES (?, ?, ?, ?, ?)");
                        $result = $stmt->execute([$page_name, $page_url, $icon_class, $parent_id, $sort_order]);
                        
                        if ($result) {
                            $new_page_id = $pdo->lastInsertId();
                            
                            // Assign roles to the new page
                            if (!empty($_POST['roles'])) {
                                foreach ($_POST['roles'] as $role) {
                                    $stmt = $pdo->prepare("INSERT INTO role_access (role_key, page_id) VALUES (?, ?)");
                                    $stmt->execute([$role, $new_page_id]);
                                }
                            }
                            
                            $message = 'Page added successfully!';
                        }
                    }
                } catch (Exception $e) {
                    $error = 'Error saving page: ' . $e->getMessage();
                }
            }
        }
    } elseif (isset($_POST['delete_page']) && !empty($_POST['page_id'])) {
        // Validate CSRF
        if (!check_csrf($_POST['csrf_token'] ?? '')) {
            $error = 'CSRF token validation failed.';
        } else {
            try {
                $page_id = (int)$_POST['page_id'];
                
                // Check if page has children
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM sys_pages WHERE parent_id = ?");
                $stmt->execute([$page_id]);
                $child_count = $stmt->fetchColumn();
                
                if ($child_count > 0) {
                    $error = 'Cannot delete page with child pages. Please remove child pages first.';
                } else {
                    // Delete role access entries
                    $stmt = $pdo->prepare("DELETE FROM role_access WHERE page_id = ?");
                    $stmt->execute([$page_id]);
                    
                    // Delete the page
                    $stmt = $pdo->prepare("DELETE FROM sys_pages WHERE id = ?");
                    $stmt->execute([$page_id]);
                    
                    $message = 'Page deleted successfully!';
                }
            } catch (Exception $e) {
                $error = 'Error deleting page: ' . $e->getMessage();
            }
        }
    }
}

// Fetch all pages for display
$stmt = $pdo->query("
    SELECT sp.*, 
           GROUP_CONCAT(ra.role_key SEPARATOR ', ') as assigned_roles
    FROM sys_pages sp
    LEFT JOIN role_access ra ON sp.id = ra.page_id
    GROUP BY sp.id
    ORDER BY sp.parent_id, sp.sort_order, sp.page_name
");
$pages = $stmt->fetchAll();

// Fetch all roles for the form
$stmt = $pdo->query("SELECT role_key, role_name FROM sys_roles ORDER BY role_name");
$roles = $stmt->fetchAll();

// Fetch parent pages for the dropdown
$stmt = $pdo->query("SELECT id, page_name, parent_id FROM sys_pages WHERE parent_id IS NULL ORDER BY page_name");
$parent_pages = $stmt->fetchAll();

// Check if editing a specific page
$editing_page = null;
if (isset($_GET['edit']) && !empty($_GET['edit'])) {
    $stmt = $pdo->prepare("
        SELECT sp.*, 
               GROUP_CONCAT(ra.role_key SEPARATOR ',') as assigned_roles
        FROM sys_pages sp
        LEFT JOIN role_access ra ON sp.id = ra.page_id
        WHERE sp.id = ?
        GROUP BY sp.id
    ");
    $stmt->execute([(int)$_GET['edit']]);
    $editing_page = $stmt->fetch();
    
    if ($editing_page) {
        $editing_page['assigned_roles_array'] = explode(',', $editing_page['assigned_roles'] ?? '');
    }
}
?>

<div class="row">
    <div class="col-md-12">

    </div>
</div>

<div class="row">
    <div class="col-md-12">
        <div class="card shadow-sm">
            <div class="card-header border-bottom">
                <div class="d-flex justify-content-between align-items-center">
                    <h3 class="card-title font-weight-bold m-0">
                        <i class="fas fa-file-alt text-primary mr-1"></i> System Pages
                    </h3>
                    <div class="card-tools m-0">
                        <button type="button" class="btn btn-sm btn-success shadow-sm" data-bs-toggle="modal" data-bs-target="#pageModal">
                            <i class="fas fa-plus-circle mr-1"></i> Add New Page
                        </button>
                    </div>
                </div>
            </div>
            <div class="card-body">
                <table class="table table-bordered table-striped">
                    <thead>
                        <tr>
                            <th>Page Name</th>
                            <th>URL</th>
                            <th>Parent</th>
                            <th>Assigned Roles</th>
                            <th>Sort Order</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pages as $page): ?>
                            <tr>
                                <td>
                                    <?php if ($page['parent_id']): ?>
                                        &nbsp;&nbsp;&nbsp;&nbsp;├─ 
                                    <?php endif; ?>
                                    <?= escape($page['page_name']) ?>
                                </td>
                                <td><code><?= escape($page['page_url']) ?></code></td>
                                <td>
                                    <?php if ($page['parent_id']): ?>
                                        <?php
                                        $parent_stmt = $pdo->prepare("SELECT page_name FROM sys_pages WHERE id = ?");
                                        $parent_stmt->execute([$page['parent_id']]);
                                        $parent = $parent_stmt->fetch();
                                        echo escape($parent['page_name'] ?? 'Unknown');
                                        ?>
                                    <?php else: ?>
                                        Top Level
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($page['assigned_roles']): ?>
                                        <span class="badge badge-secondary"><?= escape($page['assigned_roles']) ?></span>
                                    <?php else: ?>
                                        <span class="badge badge-warning">No Roles Assigned</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= $page['sort_order'] ?></td>
                                <td>
                                    <a href="?edit=<?= $page['id'] ?>" class="btn btn-sm btn-primary">Edit</a>
                                    <form method="post" style="display:inline;" 
                                          onsubmit="return confirm('Are you sure you want to delete this page?');">
                                        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                                        <input type="hidden" name="page_id" value="<?= $page['id'] ?>">
                                        <input type="hidden" name="delete_page" value="1">
                                        <button type="submit" class="btn btn-sm btn-danger">Delete</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

    </div>
</div>

<!-- PAGE REGISTRATION MODAL -->
<div class="modal fade" id="pageModal" tabindex="-1" aria-labelledby="pageModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="pageModalLabel">
                    <i class="fas fa-<?= $editing_page ? 'edit' : 'plus-circle' ?> mr-2"></i>
                    <?= $editing_page ? 'Update System Page' : 'Add New System Page' ?>
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="post">
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                    <?php if ($editing_page): ?>
                        <input type="hidden" name="page_id" value="<?= $editing_page['id'] ?>">
                        <input type="hidden" name="edit_page" value="1">
                    <?php endif; ?>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="page_name" class="form-label">Page Name *</label>
                            <input type="text" class="form-control" id="page_name" name="page_name" 
                                   value="<?= $editing_page ? escape($editing_page['page_name']) : '' ?>" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="page_url" class="form-label">Page URL *</label>
                            <input type="text" class="form-control" id="page_url" name="page_url" 
                                   value="<?= $editing_page ? escape($editing_page['page_url']) : '' ?>" required
                                   placeholder="Example: dashboards/inventory/assets.php">
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="parent_id" class="form-label">Parent Page</label>
                            <select class="form-select" id="parent_id" name="parent_id">
                                <option value="">None (Top Level)</option>
                                <?php foreach ($parent_pages as $parent): ?>
                                    <option value="<?= $parent['id'] ?>" 
                                        <?= ($editing_page && $editing_page['parent_id'] == $parent['id']) ? 'selected' : '' ?>>
                                        <?= escape($parent['page_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="icon_class" class="form-label">Icon Class</label>
                            <input type="text" class="form-control" id="icon_class" name="icon_class" 
                                   value="<?= $editing_page ? escape($editing_page['icon_class']) : 'fas fa-circle' ?>">
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label font-weight-bold">Assign Role Access *</label>
                        <div class="p-3 border rounded shadow-sm">
                            <?php foreach ($roles as $role): ?>
                                <div class="form-check form-check-inline mb-2 me-3">
                                    <input class="form-check-input" type="checkbox" id="role_<?= $role['role_key'] ?>" 
                                           name="roles[]" value="<?= $role['role_key'] ?>"
                                           <?= ($editing_page && in_array($role['role_key'], $editing_page['assigned_roles_array'] ?? [])) ? 'checked' : '' ?>>
                                    <label class="form-check-label small" for="role_<?= $role['role_key'] ?>">
                                        <?= escape($role['role_name']) ?>
                                    </label>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="sort_order" class="form-label">Sort Order</label>
                        <input type="number" class="form-control" id="sort_order" name="sort_order" 
                               value="<?= $editing_page ? escape($editing_page['sort_order']) : '0' ?>" min="0">
                    </div>
                </div>
                <div class="modal-footer justify-content-between">
                    <button type="button" class="btn btn-default" data-bs-dismiss="modal">Close</button>
                    <div>
                        <?php if ($editing_page): ?>
                            <a href="manage_pages.php" class="btn btn-secondary mr-2">Cancel</a>
                        <?php endif; ?>
                        <button type="submit" name="<?= $editing_page ? 'edit_page' : 'add_page' ?>" class="btn btn-primary px-4 shadow-sm">
                            <i class="fas fa-save mr-1"></i> <?= $editing_page ? 'Update Page' : 'Save Page' ?>
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
    <?php if ($editing_page || $error): ?>
        var pageModal = new bootstrap.Modal(document.getElementById('pageModal'));
        pageModal.show();
    <?php endif; ?>
});
</script>

<?php
include __DIR__ . '/../../includes/footer.php';
?>