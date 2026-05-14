<?php
// dashboards/procurement/purchase_orders.php
require_once __DIR__ . '/../../includes/header.php';

$message = '';
$error = '';

// Add or edit purchase order
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_po']) || isset($_POST['edit_po'])) {
        $po_no = trim($_POST['po_no'] ?? '');
        $request_id = (int)($_POST['request_id'] ?? 0);
        $vendor = trim($_POST['vendor'] ?? '');
        $total_amount = (float)($_POST['total_amount'] ?? 0);
        $expected_delivery_date = trim($_POST['expected_delivery_date'] ?? '');
        
        if (empty($request_id) || empty($vendor)) {
            $error = 'Request and Vendor are required.';
        } else {
            $pdo = getPDO();
            
            // Get the associated request
            $stmt = $pdo->prepare("SELECT * FROM purchase_requests WHERE id = ?");
            $stmt->execute([$request_id]);
            $request = $stmt->fetch();
            
            if (!$request || $request['status'] != 'approved') {
                $error = 'Selected request must be approved to create a purchase order.';
            } else {
                // Generate PO number if not provided
                if (empty($po_no)) {
                    $stmt = $pdo->query("SELECT MAX(CAST(SUBSTRING(po_no, 4) AS UNSIGNED)) as max_num FROM purchase_orders");
                    $next_num = ($stmt->fetch()['max_num'] ?? 0) + 1;
                    $po_no = 'PO-' . str_pad($next_num, 4, '0', STR_PAD_LEFT);
                }
                
                if (isset($_POST['edit_po']) && !empty($_POST['po_id'])) {
                    // Update existing PO
                    $stmt = $pdo->prepare("UPDATE purchase_orders SET po_no=?, request_id=?, vendor=?, total_amount=?, expected_delivery_date=? WHERE id=?");
                    if ($stmt->execute([$po_no, $request_id, $vendor, $total_amount, $expected_delivery_date, (int)$_POST['po_id']])) {
                        $message = 'Purchase order updated successfully!';
                    } else {
                        $error = 'Error updating purchase order.';
                    }
                } else {
                    // Check if PO number already exists
                    $stmt = $pdo->prepare("SELECT id FROM purchase_orders WHERE po_no = ?");
                    $stmt->execute([$po_no]);
                    if ($stmt->fetch()) {
                        $error = 'A purchase order with this number already exists.';
                    } else {
                        // Insert new PO
                        $stmt = $pdo->prepare("INSERT INTO purchase_orders (po_no, request_id, vendor, total_amount, expected_delivery_date) VALUES (?, ?, ?, ?, ?)");
                        if ($stmt->execute([$po_no, $request_id, $vendor, $total_amount, $expected_delivery_date])) {
                            $message = 'Purchase order added successfully!';
                        } else {
                            $error = 'Error adding purchase order.';
                        }
                    }
                }
            }
        }
    }
    
    // Delete PO
    if (isset($_POST['delete_po']) && !empty($_POST['delete_id'])) {
        $pdo = getPDO();
        $delete_id = (int)$_POST['delete_id'];
        
        // Check if PO has associated GRN
        $stmt = $pdo->prepare("SELECT id FROM grn WHERE po_id = ?");
        $stmt->execute([$delete_id]);
        $grn_record = $stmt->fetch();
        
        if ($grn_record) {
            $error = 'Cannot delete PO. It has an associated Goods Receipt Note.';
        } else {
            $stmt = $pdo->prepare("DELETE FROM purchase_orders WHERE id = ?");
            if ($stmt->execute([$delete_id])) {
                $message = 'Purchase order deleted successfully!';
            } else {
                $error = 'Error deleting purchase order.';
            }
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
    $where_clause .= " AND (po.po_no LIKE ? OR po.vendor LIKE ? OR pr.request_no LIKE ? OR pr.department LIKE ?)";
    $search_param = '%' . $search . '%';
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

// Count total for pagination
$pdo = getPDO();
$count_sql = "SELECT COUNT(*) FROM purchase_orders po LEFT JOIN purchase_requests pr ON po.request_id = pr.id " . $where_clause;
$count_stmt = $pdo->prepare($count_sql);
$count_stmt->execute($params);
$total_records = $count_stmt->fetchColumn();
$total_pages = ceil($total_records / $limit);

// Fetch paginated POs with request info
$sql = "SELECT po.*, pr.request_no, pr.department FROM purchase_orders po LEFT JOIN purchase_requests pr ON po.request_id = pr.id " . $where_clause . " ORDER BY po.created_at DESC LIMIT ? OFFSET ?";
$stmt = $pdo->prepare($sql);
$param_index = 1;
foreach ($params as $p) {
    $stmt->bindValue($param_index++, $p);
}
$stmt->bindValue($param_index++, $limit, PDO::PARAM_INT);
$stmt->bindValue($param_index++, $offset, PDO::PARAM_INT);
$stmt->execute();
$pos = $stmt->fetchAll();

// Fetch all approved requests for the form
$form_where = "WHERE status = 'approved'";
$form_params = [];

if ($user['role'] === 'hod') {
    $form_where .= " AND department IN (SELECT name FROM university_departments WHERE hod_id = ?)";
    $form_params[] = $user['id'];
} elseif ($user['role'] === 'coordinator') {
    $form_where .= " AND department IN (SELECT name FROM university_departments WHERE coordinator_id = ?)";
    $form_params[] = $user['id'];
}

$req_sql = "SELECT id, request_no, department, item_description FROM purchase_requests " . $form_where . " ORDER BY request_no ASC";
$req_stmt = $pdo->prepare($req_sql);
$req_stmt->execute($form_params);
$approved_requests = $req_stmt->fetchAll();

// Editing existing PO
$editing_po = null;
if (isset($_GET['edit']) && !empty($_GET['edit'])) {
    $edit_id = (int)$_GET['edit'];
    $stmt = $pdo->prepare("SELECT * FROM purchase_orders WHERE id = ?");
    $stmt->execute([$edit_id]);
    $editing_po = $stmt->fetch();
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
                <h3 class="card-title">Manage Purchase Orders</h3>
            </div>
            
            <div class="card-body">
                <form method="post">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="po_no" class="form-label">PO Number</label>
                                <input type="text" class="form-control" id="po_no" name="po_no" 
                                       value="<?= $editing_po ? escape($editing_po['po_no']) : '' ?>" 
                                       placeholder="Leave blank to auto-generate">
                                <small class="form-text text-muted">Format: PO-0001 (auto-generated if left empty)</small>
                            </div>
                            
                            <div class="mb-3">
                                <label for="request_id" class="form-label">Associated Request *</label>
                                <select class="form-select" id="request_id" name="request_id" required>
                                    <option value="">Select Approved Request</option>
                                    <?php foreach ($approved_requests as $request): ?>
                                        <option value="<?= $request['id'] ?>" 
                                                <?= $editing_po && $editing_po['request_id'] == $request['id'] ? 'selected' : '' ?>>
                                            <?= escape($request['request_no']) ?> - <?= escape($request['item_description']) ?> (<?= escape($request['department']) ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="vendor" class="form-label">Vendor *</label>
                                <input type="text" class="form-control" id="vendor" name="vendor" 
                                       value="<?= $editing_po ? escape($editing_po['vendor']) : '' ?>" required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="total_amount" class="form-label">Total Amount (Rs.)</label>
                                <input type="number" step="0.01" class="form-control" id="total_amount" name="total_amount" 
                                       value="<?= $editing_po ? escape($editing_po['total_amount']) : '' ?>">
                            </div>

                            <div class="mb-3">
                                <label for="expected_delivery_date" class="form-label">Expected Delivery Date</label>
                                <input type="date" class="form-control" id="expected_delivery_date" name="expected_delivery_date" 
                                       value="<?= $editing_po ? escape($editing_po['expected_delivery_date']) : '' ?>">
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <?php if ($editing_po): ?>
                            <input type="hidden" name="po_id" value="<?= $editing_po['id'] ?>">
                            <button type="submit" name="edit_po" class="btn btn-primary">Update PO</button>
                            <a href="purchase_orders.php" class="btn btn-secondary">Cancel Edit</a>
                        <?php else: ?>
                            <button type="submit" name="add_po" class="btn btn-success">Add New PO</button>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>
        
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Existing Purchase Orders</h3>
                <div class="card-tools">
                    <form method="get" class="input-group input-group-sm" style="width: 300px;">
                        <input type="text" name="search" class="form-control" placeholder="Search PO#, vendor, department..." value="<?= escape($search) ?>">
                        <div class="input-group-append">
                            <button type="submit" class="btn btn-default"><i class="fas fa-search"></i></button>
                            <?php if (!empty($search)): ?>
                                <a href="purchase_orders.php" class="btn btn-default" title="Clear"><i class="fas fa-times"></i></a>
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
                                <th>PO #</th>
                                <th>Request #</th>
                                <th>Department</th>
                                <th>Vendor</th>
                                <th>Amount</th>
                                <th>Delivery</th>
                                <th>Created</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($pos as $po): ?>
                                <tr>
                                    <td><code><?= escape($po['po_no']) ?></code></td>
                                    <td><code><?= escape($po['request_no']) ?></code></td>
                                    <td><?= escape($po['department']) ?></td>
                                    <td><?= escape($po['vendor']) ?></td>
                                    <td>Rs. <?= number_format($po['total_amount'], 2) ?></td>
                                    <td><small class="text-info"><?= $po['expected_delivery_date'] ? escape(date('M j, Y', strtotime($po['expected_delivery_date']))) : '—' ?></small></td>
                                    <td><?= escape(date('M j, Y', strtotime($po['created_at']))) ?></td>
                                    <td>
                                        <a href="?edit=<?= $po['id'] ?>" class="btn btn-sm btn-primary">Edit</a>
                                        <button type="button" class="btn btn-sm btn-danger" 
                                                onclick="confirmDelete(<?= $po['id'] ?>)">Delete</button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                <div class="card-footer clearfix">
                    <span class="float-left text-muted">Showing <?= count($pos) ?> of <?= $total_records ?> records</span>
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
function confirmDelete(poId) {
    if (confirm('Are you sure you want to delete this purchase order? This action cannot be undone if there are no associated GRNs.')) {
        const form = document.createElement('form');
        form.method = 'post';
        form.style.display = 'none';
        form.innerHTML = '<input type="hidden" name="delete_id" value="' + poId + '">' +
                         '<input type="hidden" name="delete_po" value="1">';
        document.body.appendChild(form);
        form.submit();
    }
}
</script>

<?php
include __DIR__ . '/../../includes/footer.php';
?>