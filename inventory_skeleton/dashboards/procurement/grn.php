<?php
// dashboards/procurement/grn.php
require_once __DIR__ . '/../../includes/header.php';

$message = '';
$error = '';

// Add or edit GRN
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_grn']) || isset($_POST['edit_grn'])) {
        $grn_no = trim($_POST['grn_no'] ?? '');
        $po_id = (int)($_POST['po_id'] ?? 0);
        $received_by = (int)($_POST['received_by'] ?? currentUser()['id']);
        $inspection_notes = trim($_POST['inspection_notes'] ?? '');
        $item_condition = trim($_POST['item_condition'] ?? 'good');
        $category_id = (int)($_POST['category_id'] ?? 0);
        
        if (empty($po_id)) {
            $error = 'Purchase Order is required.';
        } elseif (empty($category_id) && !isset($_POST['edit_grn'])) {
            $error = 'Asset Category is required for drafting into inventory.';
        } else {
            $pdo = getPDO();
            
            // Get the associated PO
            $stmt = $pdo->prepare("SELECT * FROM purchase_orders WHERE id = ?");
            $stmt->execute([$po_id]);
            $po = $stmt->fetch();
            
            if (!$po) {
                $error = 'Selected purchase order does not exist.';
            } else {
                // Generate GRN number if not provided
                if (empty($grn_no)) {
                    $stmt = $pdo->query("SELECT MAX(CAST(SUBSTRING(grn_no, 5) AS UNSIGNED)) as max_num FROM grn");
                    $next_num = ($stmt->fetch()['max_num'] ?? 0) + 1;
                    $grn_no = 'GRN-' . str_pad($next_num, 4, '0', STR_PAD_LEFT);
                }
                
                if (isset($_POST['edit_grn']) && !empty($_POST['grn_id'])) {
                    // Update existing GRN
                    $stmt = $pdo->prepare("UPDATE grn SET grn_no=?, po_id=?, received_by=?, inspection_notes=?, item_condition=? WHERE id=?");
                    if ($stmt->execute([$grn_no, $po_id, $received_by, $inspection_notes, $item_condition, (int)$_POST['grn_id']])) {
                        $message = 'GRN updated successfully!';
                    } else {
                        $error = 'Error updating GRN.';
                    }
                } else {
                    // Check if GRN number already exists
                    $stmt = $pdo->prepare("SELECT id FROM grn WHERE grn_no = ?");
                    $stmt->execute([$grn_no]);
                    if ($stmt->fetch()) {
                        $error = 'A GRN with this number already exists.';
                    } else {
                        // Insert new GRN
                        $stmt = $pdo->prepare("INSERT INTO grn (grn_no, po_id, received_by, inspection_notes, item_condition) VALUES (?, ?, ?, ?, ?)");
                        if ($stmt->execute([$grn_no, $po_id, $received_by, $inspection_notes, $item_condition])) {
                            // Update stock based on the PO
                            // Get the request associated with this PO
                            $stmt = $pdo->prepare("SELECT request_id FROM purchase_orders WHERE id = ?");
                            $stmt->execute([$po_id]);
                            $request_data = $stmt->fetch();
                            
                            if ($request_data) {
                                // Fetch request details
                                $req_stmt = $pdo->prepare("SELECT * FROM purchase_requests WHERE id = ?");
                                $req_stmt->execute([$request_data['request_id']]);
                                $req = $req_stmt->fetch();
                                
                                if ($req) {
                                    // Get selected category
                                    $cat_stmt = $pdo->prepare("SELECT name FROM asset_categories WHERE id = ?");
                                    $cat_stmt->execute([$category_id]);
                                    $cat_row = $cat_stmt->fetch();
                                    $prefix = $cat_row ? strtoupper(substr($cat_row['name'], 0, 3)) : 'AST';

                                    // Auto-generate asset tag
                                    $max_stmt = $pdo->prepare("SELECT MAX(CAST(SUBSTRING(asset_tag, LENGTH(?) + 2) AS UNSIGNED)) FROM assets WHERE asset_tag LIKE ?");
                                    $max_stmt->execute([$prefix, $prefix . '-%']);
                                    $next_num = ($max_stmt->fetchColumn() ?? 0) + 1;
                                    $asset_tag = $prefix . '-' . str_pad($next_num, 3, '0', STR_PAD_LEFT);

                                    // Insert Asset
                                    $ast_stmt = $pdo->prepare("INSERT INTO assets (asset_tag, name, serial_number, model, brand, category_id, department, faculty, assigned_to_user_id, class_location, condition_status, purchase_request_id, purchase_date, purchase_cost, funding_source, current_value, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                                    
                                    $ast_stmt->execute([
                                        $asset_tag, 
                                        $req['item_description'], 
                                        '', // serial_number
                                        '', // model
                                        '', // brand
                                        $category_id, 
                                        $req['department'], 
                                        '', // faculty
                                        null, // assigned_to_user_id
                                        '', // class_location
                                        $item_condition,
                                        $req['id'],
                                        date('Y-m-d'), // purchase_date
                                        $req['estimated_cost'],
                                        '', // funding_source
                                        $req['estimated_cost'],
                                        !empty($req['department']) ? 'allocated' : 'in_stock'
                                    ]);
                                }
                            }
                            
                            $message = 'GRN added successfully! Asset has been automatically drafted in the inventory.';
                        } else {
                            $error = 'Error adding GRN.';
                        }
                    }
                }
            }
        }
    }
    
    // Delete GRN
    if (isset($_POST['delete_grn']) && !empty($_POST['delete_id'])) {
        $pdo = getPDO();
        $delete_id = (int)$_POST['delete_id'];
        
        $stmt = $pdo->prepare("DELETE FROM grn WHERE id = ?");
        if ($stmt->execute([$delete_id])) {
            $message = 'GRN deleted successfully!';
        } else {
            $error = 'Error deleting GRN.';
        }
    }
}

// Strict Departmental Isolation logic
$where_clause = "WHERE 1=1";
$params = [];
$user = currentUser();

if ($user['role'] === 'hod') {
    $where_clause .= " AND pr.department IN (SELECT name FROM university_departments WHERE hod_id = ?)";
    $params[] = $user['id'];
} elseif ($user['role'] === 'coordinator') {
    $where_clause .= " AND pr.department IN (SELECT name FROM university_departments WHERE coordinator_id = ?)";
    $params[] = $user['id'];
}

// Pagination and Search
$search = trim($_GET['search'] ?? '');
$limit = 10;
$page = max(1, (int)($_GET['page'] ?? 1));
$offset = ($page - 1) * $limit;

if (!empty($search)) {
    $where_clause .= " AND (g.grn_no LIKE ? OR po.po_no LIKE ? OR po.vendor LIKE ? OR pr.item_description LIKE ? OR pr.request_no LIKE ?)";
    $search_param = '%' . $search . '%';
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

// Count total for pagination
$pdo = getPDO();
$count_sql = "SELECT COUNT(*) FROM grn g LEFT JOIN purchase_orders po ON g.po_id = po.id LEFT JOIN purchase_requests pr ON po.request_id = pr.id LEFT JOIN users u ON g.received_by = u.id " . $where_clause;
$count_stmt = $pdo->prepare($count_sql);
$count_stmt->execute($params);
$total_records = $count_stmt->fetchColumn();
$total_pages = ceil($total_records / $limit);

// Fetch paginated GRNs with PO and request info
$grn_sql = "SELECT g.*, po.po_no, po.vendor, pr.request_no, pr.item_description, u.name as received_by_name FROM grn g LEFT JOIN purchase_orders po ON g.po_id = po.id LEFT JOIN purchase_requests pr ON po.request_id = pr.id LEFT JOIN users u ON g.received_by = u.id " . $where_clause . " ORDER BY g.created_at DESC LIMIT ? OFFSET ?";
$grn_stmt = $pdo->prepare($grn_sql);
$param_index = 1;
foreach ($params as $p) {
    $grn_stmt->bindValue($param_index++, $p);
}
$grn_stmt->bindValue($param_index++, $limit, PDO::PARAM_INT);
$grn_stmt->bindValue($param_index++, $offset, PDO::PARAM_INT);
$grn_stmt->execute();
$grns = $grn_stmt->fetchAll();

// Fetch all POs for the form
$po_sql = "SELECT po.id, po.po_no, po.vendor, pr.request_no FROM purchase_orders po LEFT JOIN purchase_requests pr ON po.request_id = pr.id " . $where_clause . " ORDER BY po.po_no ASC";
$po_stmt = $pdo->prepare($po_sql);
$po_stmt->execute($params);
$pos = $po_stmt->fetchAll();

// Fetch all users for the form
$stmt = $pdo->query("SELECT id, name, role FROM users WHERE is_active = 1 ORDER BY name ASC");
$users = $stmt->fetchAll();

// Fetch categories for the form
$cat_stmt = $pdo->query("SELECT id, name FROM asset_categories ORDER BY name ASC");
$categories = $cat_stmt->fetchAll();

// Editing existing GRN
$editing_grn = null;
if (isset($_GET['edit']) && !empty($_GET['edit'])) {
    $edit_id = (int)$_GET['edit'];
    $stmt = $pdo->prepare("SELECT * FROM grn WHERE id = ?");
    $stmt->execute([$edit_id]);
    $editing_grn = $stmt->fetch();
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
        
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Manage Goods Receipt Notes</h3>
            </div>
            
            <div class="card-body">
                <form method="post">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="grn_no" class="form-label">GRN Number</label>
                                <input type="text" class="form-control" id="grn_no" name="grn_no" 
                                       value="<?= $editing_grn ? escape($editing_grn['grn_no']) : '' ?>" 
                                       placeholder="Leave blank to auto-generate">
                                <small class="form-text text-muted">Format: GRN-0001 (auto-generated if left empty)</small>
                            </div>
                            
                            <div class="mb-3">
                                <label for="po_id" class="form-label">Associated PO *</label>
                                <select class="form-select" id="po_id" name="po_id" required>
                                    <option value="">Select Purchase Order</option>
                                    <?php foreach ($pos as $po_item): ?>
                                        <option value="<?= $po_item['id'] ?>" 
                                                <?= $editing_grn && $editing_grn['po_id'] == $po_item['id'] ? 'selected' : '' ?>>
                                            <?= escape($po_item['po_no']) ?> - <?= escape($po_item['vendor']) ?> (Req: <?= escape($po_item['request_no']) ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <?php if (!$editing_grn): ?>
                            <div class="mb-3">
                                <label for="category_id" class="form-label">Asset Category * (for auto-draft)</label>
                                <select class="form-select" id="category_id" name="category_id" required>
                                    <option value="">Select Category</option>
                                    <?php foreach ($categories as $cat): ?>
                                        <option value="<?= $cat['id'] ?>"><?= escape($cat['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="received_by" class="form-label">Received By</label>
                                <select class="form-select" id="received_by" name="received_by">
                                    <?php foreach ($users as $user): ?>
                                        <option value="<?= $user['id'] ?>" 
                                                <?= ($editing_grn && $editing_grn['received_by'] == $user['id']) || (!isset($editing_grn) && $user['id'] == currentUser()['id']) ? 'selected' : '' ?>>
                                            <?= escape($user['name']) ?> (<?= escape(ucfirst($user['role'])) ?> - ID: <?= escape($user['id']) ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="mb-3">
                                <label for="item_condition" class="form-label">Item Condition</label>
                                <select class="form-select" id="item_condition" name="item_condition">
                                    <option value="good" <?= ($editing_grn && $editing_grn['item_condition'] == 'good') ? 'selected' : '' ?>>Good / Sealed</option>
                                    <option value="fair" <?= ($editing_grn && $editing_grn['item_condition'] == 'fair') ? 'selected' : '' ?>>Fair / Open Box</option>
                                    <option value="damaged" <?= ($editing_grn && $editing_grn['item_condition'] == 'damaged') ? 'selected' : '' ?>>Damaged / Rejected</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="inspection_notes" class="form-label">Inspection Notes</label>
                        <textarea class="form-control" id="inspection_notes" name="inspection_notes" rows="3" placeholder="Describe the physical condition of the received assets..."><?= $editing_grn ? escape($editing_grn['inspection_notes']) : '' ?></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <?php if ($editing_grn): ?>
                            <input type="hidden" name="grn_id" value="<?= $editing_grn['id'] ?>">
                            <button type="submit" name="edit_grn" class="btn btn-primary">Update GRN</button>
                            <a href="grn.php" class="btn btn-secondary">Cancel Edit</a>
                        <?php else: ?>
                            <button type="submit" name="add_grn" class="btn btn-success">Add New GRN</button>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>
        
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Existing Goods Receipt Notes</h3>
                <div class="card-tools">
                    <form method="get" class="input-group input-group-sm" style="width: 300px;">
                        <input type="text" name="search" class="form-control" placeholder="Search GRN#, PO#, vendor, item..." value="<?= escape($search) ?>">
                        <div class="input-group-append">
                            <button type="submit" class="btn btn-default"><i class="fas fa-search"></i></button>
                            <?php if (!empty($search)): ?>
                                <a href="grn.php" class="btn btn-default" title="Clear"><i class="fas fa-times"></i></a>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
            </div>
            
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>GRN #</th>
                                <th>PO #</th>
                                <th>Item</th>
                                <th>Vendor</th>
                                <th>Request #</th>
                                <th>Condition</th>
                                <th>Received By</th>
                                <th>Received At</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($grns as $grn): ?>
                                <tr>
                                    <td><code><?= escape($grn['grn_no']) ?></code></td>
                                    <td><code><?= escape($grn['po_no']) ?></code></td>
                                    <td><strong><?= escape($grn['item_description']) ?></strong></td>
                                    <td><?= escape($grn['vendor']) ?></td>
                                    <td><code><?= escape($grn['request_no']) ?></code></td>
                                    <td>
                                        <span class="badge badge-<?= ['good'=>'success', 'fair'=>'warning', 'damaged'=>'danger'][$grn['item_condition']] ?? 'secondary' ?>">
                                            <?= ucfirst($grn['item_condition']) ?>
                                        </span>
                                    </td>
                                    <td><?= escape($grn['received_by_name']) ?></td>
                                    <td><?= escape(date('M j, Y g:i A', strtotime($grn['received_at']))) ?></td>
                                    <td>
                                        <a href="?edit=<?= $grn['id'] ?>" class="btn btn-sm btn-primary">Edit</a>
                                        <button type="button" class="btn btn-sm btn-danger" 
                                                onclick="confirmDelete(<?= $grn['id'] ?>)">Delete</button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                <div class="card-footer clearfix">
                    <span class="float-left text-muted">Showing <?= count($grns) ?> of <?= $total_records ?> records</span>
                    <ul class="pagination pagination-sm m-0 float-right">
                        <?php if ($page > 1): ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=<?= $page - 1 ?>&search=<?= urlencode($search) ?>">«</a>
                            </li>
                        <?php endif; ?>
                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
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
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
function confirmDelete(grnId) {
    if (confirm('Are you sure you want to delete this GRN?')) {
        const form = document.createElement('form');
        form.method = 'post';
        form.style.display = 'none';
        form.innerHTML = '<input type="hidden" name="delete_id" value="' + grnId + '">' +
                         '<input type="hidden" name="delete_grn" value="1">';
        document.body.appendChild(form);
        form.submit();
    }
}
</script>

<?php
include __DIR__ . '/../../includes/footer.php';
?>