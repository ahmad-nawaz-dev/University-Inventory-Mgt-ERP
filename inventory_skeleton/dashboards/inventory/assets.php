<?php
// dashboards/inventory/assets.php - University Asset Management (Refined & Fixed)
ob_start();
require_once __DIR__ . '/../../includes/header.php';
?>
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
<?php

$message = '';
$error = '';

$pdo = getPDO();

// ──────────────────────────────────────────
// POST HANDLERS
// ──────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') 

    // ADD / EDIT ASSET
    if (isset($_POST['add_asset']) || isset($_POST['edit_asset'])) {
        $asset_tag          = trim($_POST['asset_tag'] ?? '');
        $name               = trim($_POST['name'] ?? '');
        $serial_number      = trim($_POST['serial_number'] ?? '');
        $model              = trim($_POST['model'] ?? '');
        $brand              = trim($_POST['brand'] ?? '');
        $category_id        = (int)($_POST['category_id'] ?? 0);
        $department         = trim($_POST['department'] ?? '');
        $faculty            = trim($_POST['faculty'] ?? '');
        $assigned_to_user_id = (int)($_POST['assigned_to_user_id'] ?? 0);
        $class_location     = trim($_POST['class_location'] ?? '');
        $condition_status   = trim($_POST['condition_status'] ?? 'good');
        $purchase_request_id = !empty($_POST['purchase_request_id']) ? (int)$_POST['purchase_request_id'] : null;
        $purchase_date      = trim($_POST['purchase_date'] ?? '');
        $purchase_cost      = (float)($_POST['purchase_cost'] ?? 0);
        $funding_source     = trim($_POST['funding_source'] ?? '');

        // Validation
        if (empty($name) || empty($category_id) || empty($purchase_date)) {
            $error = 'Asset Name, Category, and Purchase Date are required.';
        } else {
            // Validate department if provided
            if (!empty($department)) {
                $dept_check = $pdo->prepare("SELECT id FROM university_departments WHERE name = ?");
                $dept_check->execute([$department]);
                if (!$dept_check->fetch()) {
                    $error = 'Selected department does not exist.';
                }
            }
            
            if (!$error) {
                // ... rest of the logic ...
                    // Auto-generate asset tag if blank
                    if (empty($asset_tag)) {
                        $cat_stmt = $pdo->prepare("SELECT name FROM asset_categories WHERE id = ?");
                        $cat_stmt->execute([$category_id]);
                        $cat_row = $cat_stmt->fetch();
                        $prefix  = strtoupper(substr($cat_row['name'] ?? 'AST', 0, 3));
                        $max_stmt = $pdo->prepare("SELECT MAX(CAST(SUBSTRING(asset_tag, LENGTH(?) + 2) AS UNSIGNED)) FROM assets WHERE asset_tag LIKE ?");
                        $max_stmt->execute([$prefix, $prefix . '-%']);
                        $next_num = ($max_stmt->fetchColumn() ?? 0) + 1;
                        $asset_tag = $prefix . '-' . str_pad($next_num, 3, '0', STR_PAD_LEFT);
                    }

                    $actual_user_id = ($assigned_to_user_id == 0) ? null : $assigned_to_user_id;

                    // Automated Status Logic
                    $auto_status = !empty($department) ? 'allocated' : 'in_stock';

                    if (isset($_POST['edit_asset']) && !empty($_POST['asset_id'])) {
                        // For Update: Preserve special statuses like in_repair/dead
                        $curr_stat_stmt = $pdo->prepare("SELECT status FROM assets WHERE id = ?");
                        $curr_stat_stmt->execute([(int)$_POST['asset_id']]);
                        $current_status = $curr_stat_stmt->fetchColumn() ?: 'in_stock';
                        
                        if (in_array($current_status, ['in_repair', 'dead'])) {
                            $auto_status = $current_status;
                        }

                        // UPDATE
                        $stmt = $pdo->prepare("UPDATE assets SET asset_tag=?, name=?, serial_number=?, model=?, brand=?, category_id=?, department=?, faculty=?,
                            assigned_to_user_id=?, class_location=?, condition_status=?, purchase_request_id=?,
                            purchase_date=?, purchase_cost=?, funding_source=?, status=? WHERE id=?");
                        $ok = $stmt->execute([
                            $asset_tag, $name, $serial_number, $model, $brand, $category_id, $department, $faculty,
                            $actual_user_id, $class_location, $condition_status, $purchase_request_id,
                            $purchase_date, $purchase_cost, $funding_source, $auto_status, (int)$_POST['asset_id']
                        ]);
                        $message = $ok ? 'Asset updated successfully.' : ($error = 'Error updating asset.');
                    } else {
                        // CHECK duplicate tag
                        $dup = $pdo->prepare("SELECT id FROM assets WHERE asset_tag = ?");
                        $dup->execute([$asset_tag]);
                        if ($dup->fetch()) {
                            $error = 'An asset with this tag already exists.';
                        } else {
                            // INSERT
                            $stmt = $pdo->prepare("INSERT INTO assets (asset_tag, name, serial_number, model, brand, category_id, department, faculty,
                                assigned_to_user_id, class_location, condition_status, purchase_request_id,
                                purchase_date, purchase_cost, funding_source, current_value, status)
                                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                            $ok = $stmt->execute([
                                $asset_tag, $name, $serial_number, $model, $brand, $category_id, $department, $faculty,
                                $actual_user_id, $class_location, $condition_status, $purchase_request_id,
                                $purchase_date, $purchase_cost, $funding_source, $purchase_cost, $auto_status
                            ]);
                            $message = $ok ? 'Asset added successfully.' : ($error = 'Error adding asset.');
                        }
                }
            }
        }
    }

    // DELETE
    if (isset($_POST['delete_asset']) && !empty($_POST['delete_id'])) {
        $delete_id = (int)$_POST['delete_id'];
        try {
            $stmt = $pdo->prepare("DELETE FROM assets WHERE id = ?");
            if ($stmt->execute([$delete_id])) {
                $message = 'Asset deleted successfully.';
            } else {
                $error = 'Error deleting asset.';
            }
        } catch (PDOException $e) {
            if ($e->getCode() == 23000) {
                // Error 23000: Integrity constraint violation
                $error = 'Cannot delete asset — it is associated with existing records (e.g., transfers, maintenance, or reservations).';
            } else {
                $error = 'Database error preventing deletion.';
            }
        }
    }


// ──────────────────────────────────────────
// FETCH DATA
// ──────────────────────────────────────────
$page   = max(1, (int)($_GET['page'] ?? 1));
$search = trim($_GET['search'] ?? '');
$limit  = 7;
$offset = ($page - 1) * $limit;

$where  = "WHERE 1=1";
$params = [];
if (!empty($search)) {
    $where   .= " AND (a.asset_tag LIKE ? OR a.name LIKE ? OR a.serial_number LIKE ? OR a.department LIKE ? OR u.name LIKE ?)";
    $params   = ["%$search%", "%$search%", "%$search%", "%$search%", "%$search%"];
}

// Strict Departmental Isolation
$user = currentUser();
// $where .= " AND a.status != 'in_repair'"; // Supervisor: show in repair assets to maintain list count consistency
if ($user['role'] === 'hod') {
    $where .= " AND a.department IN (SELECT name FROM university_departments WHERE hod_id = ?)";
    $params[] = $user['id'];
} elseif ($user['role'] === 'coordinator') {
    $where .= " AND a.department IN (SELECT name FROM university_departments WHERE coordinator_id = ?)";
    $params[] = $user['id'];
}

$count_stmt = $pdo->prepare("SELECT COUNT(*) FROM assets a LEFT JOIN users u ON a.assigned_to_user_id = u.id $where");
$count_stmt->execute($params);
$total_records = $count_stmt->fetchColumn();
$total_pages   = max(1, ceil($total_records / $limit));

$sql = "SELECT a.*, ac.name AS category_name, u.name AS assigned_user_name, pr.request_no AS purchase_request_no, ar.department AS reservation_department
        FROM assets a
        LEFT JOIN asset_categories  ac ON a.category_id        = ac.id
        LEFT JOIN users             u  ON a.assigned_to_user_id = u.id
        LEFT JOIN purchase_requests pr ON a.purchase_request_id = pr.id
        LEFT JOIN (
            SELECT asset_id, MAX(id) as latest_res_id
            FROM asset_reservations 
            WHERE status IN ('approved', 'in_use') 
            GROUP BY asset_id
        ) latest_res ON a.id = latest_res.asset_id
        LEFT JOIN asset_reservations ar ON latest_res.latest_res_id = ar.id
        $where ORDER BY a.asset_tag ASC LIMIT ? OFFSET ?";
$stmt  = $pdo->prepare($sql);

// Bind search parameters first
$param_index = 1;
if (!empty($params)) {
    foreach ($params as $val) {
        $stmt->bindValue($param_index++, $val);
    }
}

// Bind LIMIT and OFFSET parameters explicitly as integers
$stmt->bindValue($param_index++, $limit, PDO::PARAM_INT);
$stmt->bindValue($param_index++, $offset, PDO::PARAM_INT);

$stmt->execute();
$assets = $stmt->fetchAll();

// Dropdowns
$departments = $pdo->query("SELECT name FROM university_departments ORDER BY name ASC")->fetchAll(PDO::FETCH_COLUMN);
$categories  = $pdo->query("SELECT id, name FROM asset_categories ORDER BY name ASC")->fetchAll();
$users       = $pdo->query("SELECT id, name, role, department FROM users WHERE is_active = 1 ORDER BY name ASC")->fetchAll();
$requests    = $pdo->query("SELECT id, request_no FROM purchase_requests ORDER BY request_no DESC")->fetchAll();

// Edit mode
$editing_asset = null;
if (!empty($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM assets WHERE id = ?");
    $stmt->execute([(int)$_GET['edit']]);
    $editing_asset = $stmt->fetch();
}
?>

<div class="row">
    <div class="col-12">
        <?php if ($message): ?>
            <div class="alert alert-success alert-dismissible"><button type="button" class="close" data-dismiss="alert">&times;</button><?= escape($message) ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible"><button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button><?= escape($error) ?></div>
        <?php endif; ?>



        <!-- LIST CARD -->
        <div class="card card-outline card-info shadow-sm">
            <div class="card-header border-bottom">
                <div class="d-flex justify-content-between align-items-center">
                    <h3 class="card-title font-weight-bold m-0">
                        <i class="fas fa-table text-info mr-1"></i> Asset Inventory
                    </h3>
                    <div class="card-tools d-flex align-items-center">
                        <form method="GET" class="input-group input-group-sm mr-2" style="width: 250px;">
                            <input type="text" name="search" class="form-control float-right" placeholder="Search Assets..." value="<?= escape($search) ?>">
                            <div class="input-group-append">
                                <button type="submit" class="btn btn-default"><i class="fas fa-search"></i></button>
                            </div>
                        </form>
                        <button type="button" class="btn btn-sm btn-success shadow-sm" data-bs-toggle="modal" data-bs-target="#assetModal">
                            <i class="fas fa-plus-circle mr-1"></i> Add New Asset
                        </button>
                    </div>
                </div>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover table-striped mb-0">
                        <thead class="bg-light">
                            <tr>
                                <th>Asset Tag</th>
                                <th>Name / Model</th>
                                <th>Brand</th>
                                <th>Serial S/N</th>
                                <th>Category</th>
                                <th>Department</th>
                                <th>Location</th>
                                <th>Condition</th>
                                <th>Status</th>
                                <th class="text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($assets)): ?>
                                <tr><td colspan="10" class="text-center py-5 text-muted">No university assets found in the inventory.</td></tr>
                            <?php else: ?>
                                <?php foreach ($assets as $asset): 
                                    $sb = ['in_stock' => 'success', 'allocated' => 'info', 'in_repair' => 'warning', 'dead' => 'danger', 'reserved' => 'primary', 'in_use' => 'info'][$asset['status']] ?? 'secondary';
                                    $cb = ['good' => 'success', 'fair' => 'warning', 'poor' => 'danger'][$asset['condition_status']] ?? 'light';
                                ?>
                                <tr>
                                    <td><code><?= escape($asset['asset_tag']) ?></code></td>
                                    <td>
                                        <div class="font-weight-bold"><?= escape($asset['name']) ?></div>
                                        <small class="text-muted"><?= escape($asset['model'] ?: 'N/A') ?></small>
                                    </td>
                                    <td><?= escape($asset['brand'] ?: '—') ?></td>
                                    <td><small class="bg-light px-1 border"><?= escape($asset['serial_number'] ?: 'N/A') ?></small></td>
                                    <td><span class="badge badge-light border"><?= escape($asset['category_name']) ?></span></td>
                                    <?php 
                                        $display_dept = escape($asset['department']);
                                        if (in_array($asset['status'], ['reserved', 'in_use']) && !empty($asset['reservation_department'])) {
                                            $display_dept = escape($asset['reservation_department']) . ' <br><small class="text-primary">(Reserved by)</small>';
                                        }
                                    ?>
                                    <td><?= $display_dept ?></td>
                                    <td><?= escape($asset['class_location'] ?: '—') ?></td>
                                    <td><span class="badge badge-<?= $cb ?> text-uppercase"><?= escape($asset['condition_status']) ?></span></td>
                                    <td><span class="badge badge-<?= $sb ?>"><?= ucfirst(str_replace('_', ' ', $asset['status'])) ?></span></td>
                                    <td class="text-right">
                                        <button onclick="showQRCode('<?= escape($asset['asset_tag']) ?>', '<?= escape($asset['name']) ?>')" class="btn btn-xs btn-outline-dark shadow-sm" title="Generate QR Tag"><i class="fas fa-qrcode"></i></button>
                                        <a href="?edit=<?= $asset['id'] ?>" class="btn btn-xs btn-primary shadow-sm"><i class="fas fa-edit"></i></a>
                                        <button onclick="confirmDelete(<?= $asset['id'] ?>)" class="btn btn-xs btn-danger shadow-sm"><i class="fas fa-trash"></i></button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php if ($total_pages > 1): ?>
                <div class="card-footer border-top">
                    <ul class="pagination pagination-sm m-0 float-right">
                        <?php if ($page > 1): ?><li class="page-item"><a class="page-link" href="?page=<?= $page-1 ?>&search=<?= urlencode($search) ?>">«</a></li><?php endif; ?>
                        <?php for ($i = 1; $i <= $total_pages; $i++): ?><li class="page-item <?= $i == $page ? 'active' : '' ?>"><a class="page-link" href="?page=<?= $i ?>&search=<?= urlencode($search) ?>"><?= $i ?></a></li><?php endfor; ?>
                        <?php if ($page < $total_pages): ?><li class="page-item"><a class="page-link" href="?page=<?= $page+1 ?>&search=<?= urlencode($search) ?>">»</a></li><?php endif; ?>
                    </ul>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- ASSET REGISTRATION MODAL -->
<div class="modal fade" id="assetModal" tabindex="-1" aria-labelledby="assetModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="assetModalLabel">
                    <i class="fas fa-<?= $editing_asset ? 'edit' : 'plus-circle' ?> mr-2"></i>
                    <?= $editing_asset ? 'Update Asset Details' : 'Register New University Asset' ?>
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="post">
                <div class="modal-body">
                    <?php if ($editing_asset): ?>
                        <input type="hidden" name="asset_id" value="<?= $editing_asset['id'] ?>">
                    <?php endif; ?>

                    <div class="row">
                        <!-- SECTION 1: CORE DETAILS -->
                        <div class="col-md-4">
                            <h6 class="text-uppercase text-muted font-weight-bold mb-3 border-bottom pb-1">General Information</h6>
                            <div class="mb-3">
                                <label class="form-label">Asset Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="name" required value="<?= $editing_asset ? escape($editing_asset['name']) : '' ?>" placeholder="e.g. Dell Latitude 5420">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Asset Tag</label>
                                <input type="text" class="form-control" name="asset_tag" value="<?= $editing_asset ? escape($editing_asset['asset_tag']) : '' ?>" placeholder="Leave blank for auto-generation">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Asset Category <span class="text-danger">*</span></label>
                                <select class="form-select" name="category_id" required>
                                    <option value="">— Select Category —</option>
                                    <?php foreach ($categories as $cat): ?><option value="<?= $cat['id'] ?>" <?= ($editing_asset && $editing_asset['category_id'] == $cat['id']) ? 'selected' : '' ?>><?= escape($cat['name']) ?></option><?php endforeach; ?>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Physical Condition</label>
                                <select class="form-select" name="condition_status">
                                    <option value="good" <?= ($editing_asset && $editing_asset['condition_status'] == 'good') ? 'selected' : '' ?>>Good / New</option>
                                    <option value="fair" <?= ($editing_asset && $editing_asset['condition_status'] == 'fair') ? 'selected' : '' ?>>Fair / Used</option>
                                    <option value="poor" <?= ($editing_asset && $editing_asset['condition_status'] == 'poor') ? 'selected' : '' ?>>Poor / Damaged</option>
                                </select>
                            </div>
                        </div>

                        <!-- SECTION 2: IDENTIFICATION & BRANDING -->
                        <div class="col-md-4">
                            <h6 class="text-uppercase text-muted font-weight-bold mb-3 border-bottom pb-1">Identification</h6>
                            <div class="mb-3">
                                <label class="form-label">Brand / Manufacturer</label>
                                <input type="text" class="form-control" name="brand" value="<?= $editing_asset ? escape($editing_asset['brand']) : '' ?>" placeholder="e.g. Dell, HP, Apple">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Model</label>
                                <input type="text" class="form-control" name="model" value="<?= $editing_asset ? escape($editing_asset['model']) : '' ?>" placeholder="e.g. XPS 15 9510">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Serial Number (S/N)</label>
                                <input type="text" class="form-control" name="serial_number" value="<?= $editing_asset ? escape($editing_asset['serial_number']) : '' ?>" placeholder="Unique device serial number">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Funding Source</label>
                                <input type="text" class="form-control" name="funding_source" value="<?= $editing_asset ? escape($editing_asset['funding_source']) : '' ?>" placeholder="e.g. Dept Budget, HEC Grant, Project ABC">
                            </div>
                        </div>

                        <!-- SECTION 3: LOCATION & ASSIGNMENT -->
                        <div class="col-md-4">
                            <h6 class="text-uppercase text-muted font-weight-bold mb-3 border-bottom pb-1">Acquisiton & Deployment</h6>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Dept</label>
                                        <select class="form-select" name="department">
                                            <option value="">— Unallocated / General —</option>
                                            <?php foreach ($departments as $dept): ?><option value="<?= escape($dept) ?>" <?= ($editing_asset && $editing_asset['department'] == $dept) ? 'selected' : '' ?>><?= escape($dept) ?></option><?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Faculty</label>
                                        <input type="text" class="form-control" name="faculty" value="<?= $editing_asset ? escape($editing_asset['faculty']) : '' ?>" placeholder="School/Faculty">
                                    </div>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Assigned To</label>
                                <select class="form-select select2" name="assigned_to_user_id" id="assigned_to_user_id" style="width: 100%;">
                                    <option value="0" data-dept="">— Unassigned —</option>
                                    <?php foreach ($users as $u): ?>
                                        <option value="<?= $u['id'] ?>" 
                                                data-dept="<?= escape($u['department']) ?>"
                                                <?= ($editing_asset && $editing_asset['assigned_to_user_id'] == $u['id']) ? 'selected' : '' ?>>
                                            <?= escape($u['name']) ?> (<?= escape($u['role']) ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Room / Lab Location</label>
                                <input type="text" class="form-control" name="class_location" value="<?= $editing_asset ? escape($editing_asset['class_location']) : '' ?>" placeholder="Room 101, Computer Lab A">
                            </div>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Purchase Date <span class="text-danger">*</span></label>
                                        <input type="date" class="form-control" name="purchase_date" required value="<?= $editing_asset ? escape($editing_asset['purchase_date']) : date('Y-m-d') ?>">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Purchase Cost (Rs.)</label>
                                        <input type="number" step="0.01" class="form-control" name="purchase_cost" value="<?= $editing_asset ? escape($editing_asset['purchase_cost']) : '' ?>" placeholder="0.00">
                                    </div>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Linked Purchase Request</label>
                                <select class="form-select" name="purchase_request_id">
                                    <option value="">— No Request —</option>
                                    <?php foreach ($requests as $req): ?><option value="<?= $req['id'] ?>" <?= ($editing_asset && $editing_asset['purchase_request_id'] == $req['id']) ? 'selected' : '' ?>><?= escape($req['request_no']) ?></option><?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer justify-content-between">
                    <button type="button" class="btn btn-default" data-bs-dismiss="modal">Close</button>
                    <div>
                        <?php if ($editing_asset): ?>
                            <a href="assets.php" class="btn btn-secondary mr-2">Cancel Edit</a>
                        <?php endif; ?>
                        <button type="submit" name="<?= $editing_asset ? 'edit_asset' : 'add_asset' ?>" class="btn btn-<?= $editing_asset ? 'primary' : 'success' ?> shadow-sm">
                            <i class="fas fa-check-circle mr-1"></i> <?= $editing_asset ? 'Save Changes' : 'Register Asset' ?>
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
<!-- QR MODAL -->
<div class="modal fade" id="qrModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-sm modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-dark text-white border-0">
                <h5 class="modal-title"><i class="fas fa-qrcode mr-2"></i> Asset QR Tag</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body text-center p-4">
                <div id="qrcode" class="d-inline-block mb-3 p-3 bg-white border rounded shadow-sm"></div>
                <h4 id="qrAssetTag" class="font-weight-bold text-dark mb-1"></h4>
                <p id="qrAssetName" class="text-muted small mb-0"></p>
            </div>
            <div class="modal-footer border-0">
                <button type="button" class="btn btn-secondary btn-block" onclick="window.print()">
                    <i class="fas fa-print mr-1"></i> Print Label
                </button>
            </div>
        </div>
    </div>
</div>

<style>
@media print {
    body * { visibility: hidden; }
    #qrModal, #qrModal * { visibility: visible; }
    #qrModal { position: absolute; left: 0; top: 0; width: 100%; border: none !important; }
    .modal-footer, .btn-close { display: none !important; }
}
</style>

<script>
function confirmDelete(id) {
    if (confirm('Are you sure you want to delete this university asset? This action will permanently remove it from the system.')) {
        const f = document.createElement('form');
        f.method = 'post';
        f.innerHTML = `<input type="hidden" name="delete_id" value="${id}"><input type="hidden" name="delete_asset" value="1">`;
        document.body.appendChild(f);
        f.submit();
    }
}

// Dynamic Department Filtering for Assigned Personnel
document.addEventListener('DOMContentLoaded', function() {
    const deptSelect = document.querySelector('select[name="department"]');
    const userSelect = document.getElementById('assigned_to_user_id');
    const allOptions = Array.from(userSelect.options);

    function filterUsers() {
        const selectedDept = deptSelect.value;
        
        // Clear current options
        userSelect.innerHTML = '';
        
        // Filter and re-add options
        allOptions.forEach(opt => {
            const optDept = opt.getAttribute('data-dept');
            // Show if it's the unassigned option (value '0') or if department matches
            if (opt.value === "0" || (selectedDept !== "" && optDept === selectedDept)) {
                userSelect.appendChild(opt);
            }
        });
        
        // Trigger Select2 update if applicable
        if (window.jQuery && typeof window.jQuery(userSelect).select2 === 'function') {
            window.jQuery(userSelect).trigger('change');
        }
    }

    if (deptSelect && userSelect) {
        deptSelect.addEventListener('change', filterUsers);
        // Initial filter on page load
        filterUsers();
    }
});

let qrHelper;
function showQRCode(tag, name) {
    document.getElementById('qrAssetTag').innerText = tag;
    document.getElementById('qrAssetName').innerText = name;
    
    // Clear previous
    document.getElementById('qrcode').innerHTML = '';
    
    // Generate
    if(!qrHelper) {
        qrHelper = new QRCode(document.getElementById("qrcode"), {
            text: tag,
            width: 180,
            height: 180,
            colorDark : "#000000",
            colorLight : "#ffffff",
            correctLevel : QRCode.CorrectLevel.H
        });
    } else {
        qrHelper.clear();
        qrHelper.makeCode(tag);
    }
    
    // Show modal
    new bootstrap.Modal(document.getElementById('qrModal')).show();
}

// Auto-open asset modal for editing or errors
document.addEventListener("DOMContentLoaded", function() {
    <?php if ($editing_asset || $error): ?>
        var assetModal = new bootstrap.Modal(document.getElementById('assetModal'));
        assetModal.show();
    <?php endif; ?>
});
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>