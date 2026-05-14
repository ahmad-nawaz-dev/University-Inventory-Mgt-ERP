<?php
// dashboards/inventory/categories.php - Updated with unique ID display
require_once __DIR__ . '/../../includes/header.php';

$message = '';
$error = '';

// Add or edit category
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_category']) || isset($_POST['edit_category'])) {
        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        
        if (empty($name)) {
            $error = 'Category name is required.';
        } else {
            $pdo = getPDO();
            
            if (isset($_POST['edit_category']) && !empty($_POST['category_id'])) {
                // Update existing category
                $stmt = $pdo->prepare("UPDATE asset_categories SET name=?, description=? WHERE id=?");
                if ($stmt->execute([$name, $description, (int)$_POST['category_id']])) {
                    $message = 'Category updated successfully!';
                } else {
                    $error = 'Error updating category.';
                }
            } else {
                // Check if category already exists
                $stmt = $pdo->prepare("SELECT id FROM asset_categories WHERE name = ?");
                $stmt->execute([$name]);
                if ($stmt->fetch()) {
                    $error = 'A category with this name already exists.';
                } else {
                    // Insert new category
                    $stmt = $pdo->prepare("INSERT INTO asset_categories (name, description) VALUES (?, ?)");
                    if ($stmt->execute([$name, $description])) {
                        $message = 'Category added successfully!';
                    } else {
                        $error = 'Error adding category.';
                    }
                }
            }
        }
    }
    
    // Delete category
    if (isset($_POST['delete_category']) && !empty($_POST['delete_id'])) {
        $pdo = getPDO();
        $delete_id = (int)$_POST['delete_id'];
        
        // Check if category has associated assets
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM assets WHERE category_id = ?");
        $stmt->execute([$delete_id]);
        $asset_count = $stmt->fetchColumn();
        
        if ($asset_count > 0) {
            $error = 'Cannot delete category. It has ' . $asset_count . ' associated assets.';
        } else {
            $stmt = $pdo->prepare("DELETE FROM asset_categories WHERE id = ?");
            if ($stmt->execute([$delete_id])) {
                $message = 'Category deleted successfully!';
            } else {
                $error = 'Error deleting category.';
            }
        }
    }
}

// Pagination and Search
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$search = trim($_GET['search'] ?? '');
$limit = 5;
$offset = ($page - 1) * $limit;

$pdo = getPDO();
$where_clause = "";
$params = [];

if (!empty($search)) {
    $where_clause = "WHERE name LIKE ? OR description LIKE ?";
    $params = ["%$search%", "%$search%"];
}

// Count total records
$count_sql = "SELECT COUNT(*) FROM asset_categories " . $where_clause;
$count_stmt = $pdo->prepare($count_sql);
$count_stmt->execute($params);
$total_records = $count_stmt->fetchColumn();
$total_pages = ceil($total_records / $limit);

// Fetch paginated records
$sql = "SELECT * FROM asset_categories " . $where_clause . " ORDER BY name ASC LIMIT ? OFFSET ?";
$stmt = $pdo->prepare($sql);

$param_index = 1;
if (!empty($search)) {
    $stmt->bindValue($param_index++, "%$search%");
    $stmt->bindValue($param_index++, "%$search%");
}

$stmt->bindValue($param_index++, $limit, PDO::PARAM_INT);
$stmt->bindValue($param_index++, $offset, PDO::PARAM_INT);

$stmt->execute();
$categories = $stmt->fetchAll();

// Editing existing category
$editing_category = null;
if (isset($_GET['edit']) && !empty($_GET['edit'])) {
    $edit_id = (int)$_GET['edit'];
    $stmt = $pdo->prepare("SELECT * FROM asset_categories WHERE id = ?");
    $stmt->execute([$edit_id]);
    $editing_category = $stmt->fetch();
}
?>

<div class="row">
    <div class="col-md-12">
        <?php if ($message): ?>
            <div class="alert alert-success"><?= escape($message) ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-danger"><?= escape($error) ?></div>
        <?php endif; ?>
        

        
        <!-- Search Form -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Search Categories</h3>
            </div>
            <div class="card-body">
                <form method="GET" class="row g-2 align-items-center mb-4 nc-animate-in">
                    <div class="col-md-5">
                        <input type="text" class="form-control" name="search" placeholder="Search categories by name or description..." 
                               value="<?= escape($search) ?>">
                    </div>
                    <div class="col-md-auto">
                        <button type="submit" class="btn btn-primary shadow-sm px-4">Search Categories</button>
                        <a href="categories.php" class="btn btn-outline-secondary ml-1 px-4">Clear</a>
                    </div>
                </form>
            </div>
        </div>
        
        <div class="card">
            <div class="card-header">
                <div class="d-flex justify-content-between align-items-center">
                    <h3 class="card-title m-0">Existing Categories</h3>
                    <div class="card-tools m-0">
                        <span class="badge badge-primary mr-2">Showing <?= min($limit, $total_records - ($page-1)*$limit) ?> of <?= $total_records ?> categories</span>
                        <button type="button" class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#categoryModal">
                            <i class="fas fa-plus"></i> Add New Category
                        </button>
                    </div>
                </div>
            </div>
            
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Category Name</th>
                                <th>Description</th>
                                <th>Asset Count</th>
                                <th>Created</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($categories as $category): ?>
                                <tr>
                                    <td><code>#<?= $category['id'] ?></code></td>
                                    <td>
                                        <strong><?= escape($category['name']) ?></strong>
                                        <?php
                                        // Check if this name appears in other categories (duplicate detection)
                                        $duplicate_check = $pdo->prepare("SELECT COUNT(*) FROM asset_categories WHERE name = ? AND id != ?");
                                        $duplicate_check->execute([$category['name'], $category['id']]);
                                        $duplicate_count = $duplicate_check->fetchColumn();
                                        
                                        if ($duplicate_count > 0) {
                                            echo '<span class="badge badge-warning ml-2">Duplicate Found</span>';
                                        }
                                        ?>
                                    </td>
                                    <td><?= nl2br(escape($category['description'])) ?></td>
                                    <td>
                                        <?php 
                                        $stmt = $pdo->prepare("SELECT COUNT(*) FROM assets WHERE category_id = ?");
                                        $stmt->execute([$category['id']]);
                                        $asset_count = $stmt->fetchColumn();
                                        echo '<span class="badge badge-info">' . $asset_count . '</span>';
                                        ?>
                                    </td>
                                    <td><?= escape(date('M j, Y', strtotime($category['created_at']))) ?></td>
                                    <td>
                                        <a href="?edit=<?= $category['id'] ?>" class="btn btn-sm btn-primary">Edit</a>
                                        <button type="button" class="btn btn-sm btn-danger" 
                                                onclick="confirmDelete(<?= $category['id'] ?>)">Delete</button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            
                            <?php if (empty($categories) && !isset($_GET['search'])): ?>
                                <tr>
                                    <td colspan="6" class="text-center text-muted">
                                        No categories found. Add your first category using the form above.
                                    </td>
                                </tr>
                            <?php elseif (empty($categories) && isset($_GET['search'])): ?>
                                <tr>
                                    <td colspan="6" class="text-center text-muted">
                                        No categories match your search criteria. Try adjusting your search terms.
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Pagination -->
                <div class="card-footer clearfix">
                    <ul class="pagination pagination-sm m-0 float-right">
                        <?php if ($page > 1): ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=<?= $page - 1 ?>&search=<?= urlencode($search) ?>">«</a>
                            </li>
                        <?php endif; ?>
                        
                        <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                            <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                                <a class="page-link" href="?page=<?= $i ?>&search=<?= urlencode($search) ?>"><?= $i ?></a>
                            </li>
                        <?php endfor; ?>
                        
                        <?php if ($page < $total_pages): ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=<?= $page + 1 ?>&search=<?= urlencode($search) ?>">»</a>
                            </li>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>
        </div>
        
        <?php if (!empty($search)): ?>
        <div class="alert alert-info">
            <h5><i class="fas fa-info-circle"></i> Search Results</h5>
            <p>Found <strong><?= $total_records ?></strong> categories matching your search: <code>"<?= escape($search) ?>"</code></p>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Modal for Add/Edit Category -->
<div class="modal fade" id="categoryModal" tabindex="-1" role="dialog" aria-labelledby="categoryModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="categoryModalLabel"><?= $editing_category ? 'Edit Category' : 'Add New Category' ?></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form method="post" action="categories.php">
          <div class="modal-body">
              <div class="row">
                  <div class="col-md-6">
                      <div class="mb-3">
                          <label for="name" class="form-label">Category Name *</label>
                          <input type="text" class="form-control" id="name" name="name" 
                                 value="<?= $editing_category ? escape($editing_category['name']) : '' ?>" required>
                      </div>
                  </div>
                  <div class="col-md-6">
                      <div class="mb-3">
                          <label for="description" class="form-label">Description</label>
                          <textarea class="form-control" id="description" name="description" rows="3" 
                                    placeholder="Enter category description..."><?= $editing_category ? escape($editing_category['description']) : '' ?></textarea>
                      </div>
                  </div>
              </div>
              <?php if ($editing_category): ?>
                  <input type="hidden" name="category_id" value="<?= $editing_category['id'] ?>">
              <?php endif; ?>
          </div>
          <div class="modal-footer">
              <?php if ($editing_category): ?>
                  <a href="categories.php" class="btn btn-secondary">Cancel</a>
                  <button type="submit" name="edit_category" class="btn btn-primary">Update Category</button>
              <?php else: ?>
                  <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                  <button type="submit" name="add_category" class="btn btn-success">Save Category</button>
              <?php endif; ?>
          </div>
      </form>
    </div>
  </div>
</div>

<script>
// Auto-open modal logic for editing or errors
document.addEventListener("DOMContentLoaded", function() {
    <?php if ($editing_category || $error): ?>
        var myModal = new bootstrap.Modal(document.getElementById('categoryModal'));
        myModal.show();
    <?php endif; ?>
});

function confirmDelete(categoryId) {
    if (confirm('Are you sure you want to delete this category? This action cannot be undone if there are no associated assets.')) {
        const form = document.createElement('form');
        form.method = 'post';
        form.style.display = 'none';
        form.innerHTML = '<input type="hidden" name="delete_id" value="' + categoryId + '">' +
                         '<input type="hidden" name="delete_category" value="1">';
        document.body.appendChild(form);
        form.submit();
    }
}
</script>

<?php
include __DIR__ . '/../../includes/footer.php';
?>