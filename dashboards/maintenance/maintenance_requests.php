<?php
// dashboards/maintenance/maintenance_requests.php - Asset Maintenance Module
require_once __DIR__ . '/../../includes/header.php';

$message = '';
$error = '';

// Handle maintenance requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_maintenance']) || isset($_POST['edit_maintenance'])) {
        $asset_id = (int)($_POST['asset_id'] ?? 0);
        $maintenance_type = trim($_POST['maintenance_type'] ?? 'preventive');
        $service_provider = trim($_POST['service_provider'] ?? '');
        $technician_name = trim($_POST['technician_name'] ?? '');
        $maintenance_date = trim($_POST['maintenance_date'] ?? date('Y-m-d'));
        $next_maintenance_date = trim($_POST['next_maintenance_date'] ?? '');
        $maintenance_cost = (float)($_POST['maintenance_cost'] ?? 0);
        $description = trim($_POST['description'] ?? '');
        $status = trim($_POST['status'] ?? 'pending');
        $priority = trim($_POST['priority'] ?? 'medium');
        $warranty_period_months = (int)($_POST['warranty_period_months'] ?? 0);
        
        if (empty($asset_id) || empty($description)) {
            $error = 'Asset and description are required.';
        } else {
            $pdo = getPDO();
            
            // Calculate warranty expiry if period is provided
            $warranty_expiry_date = null;
            if ($warranty_period_months > 0 && !empty($maintenance_date)) {
                $warranty_expiry_date = date('Y-m-d', strtotime("$maintenance_date +$warranty_period_months months"));
            }
            
            if (isset($_POST['edit_maintenance']) && !empty($_POST['maintenance_id'])) {
                // Update existing maintenance
                $stmt = $pdo->prepare("UPDATE asset_maintenance SET asset_id=?, maintenance_type=?, service_provider=?, technician_name=?, maintenance_date=?, next_maintenance_date=?, maintenance_cost=?, description=?, status=?, priority=?, warranty_period_months=?, warranty_expiry_date=?, updated_at=NOW() WHERE id=?");
                if ($stmt->execute([$asset_id, $maintenance_type, $service_provider, $technician_name, $maintenance_date, $next_maintenance_date, $maintenance_cost, $description, $status, $priority, $warranty_period_months, $warranty_expiry_date, (int)$_POST['maintenance_id']])) {
                    $message = 'Maintenance record updated successfully!';
                    
                    // Update corresponding asset status based on edited maintenance status
                    if ($status === 'completed' || $status === 'cancelled') {
                        $update_asset = $pdo->prepare("UPDATE assets SET status = 'in_stock' WHERE id = ?");
                        $update_asset->execute([$asset_id]);
                    } else if ($status === 'pending' || $status === 'in_progress') {
                        $update_asset = $pdo->prepare("UPDATE assets SET status = 'in_repair' WHERE id = ?");
                        $update_asset->execute([$asset_id]);
                    }

                    // Add to history
                    $history_stmt = $pdo->prepare("INSERT INTO asset_maintenance_history (maintenance_id, status_change, comments, changed_by) VALUES (?, ?, ?, ?)");
                    $history_stmt->execute([(int)$_POST['maintenance_id'], $status, "Status changed to $status", currentUser()['id']]);
                } else {
                    $error = 'Error updating maintenance record.';
                }
            } else {
                // Insert new maintenance
                $stmt = $pdo->prepare("INSERT INTO asset_maintenance (asset_id, maintenance_type, service_provider, technician_name, maintenance_date, next_maintenance_date, maintenance_cost, description, status, priority, warranty_period_months, warranty_expiry_date, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                if ($stmt->execute([$asset_id, $maintenance_type, $service_provider, $technician_name, $maintenance_date, $next_maintenance_date, $maintenance_cost, $description, $status, $priority, $warranty_period_months, $warranty_expiry_date, currentUser()['id']])) {
                    $maintenance_id = $pdo->lastInsertId();
                    $message = 'Maintenance request created successfully!';
                    
                    // Update asset status to 'in_repair' if the request is active
                    if ($status === 'pending' || $status === 'in_progress') {
                        $update_asset = $pdo->prepare("UPDATE assets SET status = 'in_repair' WHERE id = ?");
                        $update_asset->execute([$asset_id]);
                    }
                    
                    // Add to history
                    $history_stmt = $pdo->prepare("INSERT INTO asset_maintenance_history (maintenance_id, status_change, comments, changed_by) VALUES (?, ?, ?, ?)");
                    $history_stmt->execute([$maintenance_id, $status, "Maintenance request created", currentUser()['id']]);
                } else {
                    $error = 'Error creating maintenance request.';
                }
            }
        }
    }
    
    // Update maintenance status
    if ((isset($_POST['update_status'])) && !empty($_POST['maintenance_id'])) {
        $pdo = getPDO();
        $maintenance_id = (int)$_POST['maintenance_id'];
        $new_status = trim($_POST['update_status']);
        $comments = trim($_POST['comments'] ?? '');
        
        $valid_statuses = ['pending', 'in_progress', 'completed', 'cancelled'];
        if (in_array($new_status, $valid_statuses)) {
            $stmt = $pdo->prepare("UPDATE asset_maintenance SET status=?, updated_at=NOW() WHERE id=?");
            if ($stmt->execute([$new_status, $maintenance_id])) {
                // Update corresponding asset status
                $asset_stmt = $pdo->prepare("SELECT asset_id FROM asset_maintenance WHERE id = ?");
                $asset_stmt->execute([$maintenance_id]);
                $asset_id = $asset_stmt->fetchColumn();
                
                if ($new_status === 'completed' || $new_status === 'cancelled') {
                    $update_asset = $pdo->prepare("UPDATE assets SET status = 'in_stock' WHERE id = ?");
                    $update_asset->execute([$asset_id]);
                } else if ($new_status === 'pending' || $new_status === 'in_progress') {
                    $update_asset = $pdo->prepare("UPDATE assets SET status = 'in_repair' WHERE id = ?");
                    $update_asset->execute([$asset_id]);
                }

                // Add to history
                $history_stmt = $pdo->prepare("INSERT INTO asset_maintenance_history (maintenance_id, status_change, comments, changed_by) VALUES (?, ?, ?, ?)");
                $history_stmt->execute([$maintenance_id, $new_status, $comments, currentUser()['id']]);
                
                $message = 'Maintenance status updated successfully!';
            } else {
                $error = 'Error updating maintenance status.';
            }
        } else {
            $error = 'Invalid status selected.';
        }
    }
}

// Pagination and Search
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$search = trim($_GET['search'] ?? '');
$filter_status = trim($_GET['filter_status'] ?? '');
$limit = 10;
$offset = ($page - 1) * $limit;

$pdo = getPDO();
$user = currentUser();
$where_clause = "WHERE 1=1";
$params = [];

if ($user['role'] === 'hod') {
    $where_clause .= " AND a.department IN (SELECT name FROM university_departments WHERE hod_id = ?)";
    $params[] = $user['id'];
} elseif ($user['role'] === 'coordinator') {
    $where_clause .= " AND a.department IN (SELECT name FROM university_departments WHERE coordinator_id = ?)";
    $params[] = $user['id'];
}

if (!empty($search)) {
    $where_clause .= " AND (a.asset_tag LIKE ? OR a.name LIKE ? OR am.description LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if (!empty($filter_status)) {
    $where_clause .= " AND am.status = ?";
    $params[] = $filter_status;
} else {
    // Supervisor requirement: show only assets in repair
    $where_clause .= " AND a.status = 'in_repair'";
}

// Count total records
$count_sql = "
    SELECT COUNT(*) 
    FROM asset_maintenance am
    LEFT JOIN assets a ON am.asset_id = a.id
    " . $where_clause;
$count_stmt = $pdo->prepare($count_sql);
$count_stmt->execute($params);
$total_records = $count_stmt->fetchColumn();
$total_pages = ceil($total_records / $limit);

// Fetch paginated records
$sql = "
    SELECT am.*, a.asset_tag, a.name as asset_name, a.brand, a.model, a.department, u.name as created_by_name
    FROM asset_maintenance am
    LEFT JOIN assets a ON am.asset_id = a.id
    LEFT JOIN users u ON am.created_by = u.id
    " . $where_clause . "
    ORDER BY am.maintenance_date DESC, am.created_at DESC
    LIMIT ? OFFSET ?";

$stmt = $pdo->prepare($sql);
$pagination_params = array_merge($params, [$limit, $offset]);
$stmt->execute($pagination_params);
$maintenance_records = $stmt->fetchAll();

// Fetch all assets for dropdown - Filtered by department & status (NOT in repair)
$assets_where = " WHERE status != 'in_repair'";
$assets_params = [];
if ($user['role'] === 'hod') {
    $assets_where .= " AND department IN (SELECT name FROM university_departments WHERE hod_id = ?)";
    $assets_params[] = $user['id'];
} elseif ($user['role'] === 'coordinator') {
    $assets_where .= " AND department IN (SELECT name FROM university_departments WHERE coordinator_id = ?)";
    $assets_params[] = $user['id'];
}

$assets_stmt = $pdo->prepare("SELECT id, asset_tag, name, department FROM assets $assets_where ORDER BY asset_tag ASC");
$assets_stmt->execute($assets_params);
$assets = $assets_stmt->fetchAll();

// Editing existing record
$editing_maintenance = null;
if (isset($_GET['edit']) && !empty($_GET['edit'])) {
    $edit_id = (int)$_GET['edit'];
    $stmt = $pdo->prepare("
        SELECT am.*, a.asset_tag, a.name as asset_name 
        FROM asset_maintenance am
        LEFT JOIN assets a ON am.asset_id = a.id
        WHERE am.id = ?
    ");
    $stmt->execute([$edit_id]);
    $editing_maintenance = $stmt->fetch();
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
                <h3 class="card-title">Asset Maintenance Management</h3>
            </div>
            
            <div class="card-body">
                <form method="post">
                    <div class="row">
                        <div class="col-md-6">
                            <!-- Asset selection already updated in previous turn (partial success) -->
                            <div class="mb-3">
                                <label for="asset_id" class="form-label">Asset *</label>
                                <select class="form-select" id="asset_id" name="asset_id" required>
                                    <option value="">Select Asset</option>
                                    <?php foreach ($assets as $asset): ?>
                                        <option value="<?= $asset['id'] ?>" 
                                                <?= $editing_maintenance && $editing_maintenance['asset_id'] == $asset['id'] ? 'selected' : '' ?>>
                                            <?= escape($asset['asset_tag']) ?> - <?= escape($asset['name']) ?> (<?= escape($asset['department']) ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="mb-3">
                                <label for="maintenance_type" class="form-label">Maintenance Type</label>
                                <select class="form-select" id="maintenance_type" name="maintenance_type">
                                    <option value="preventive" <?= $editing_maintenance && $editing_maintenance['maintenance_type'] == 'preventive' ? 'selected' : '' ?>>Preventive</option>
                                    <option value="corrective" <?= $editing_maintenance && $editing_maintenance['maintenance_type'] == 'corrective' ? 'selected' : '' ?>>Corrective</option>
                                    <option value="emergency" <?= $editing_maintenance && $editing_maintenance['maintenance_type'] == 'emergency' ? 'selected' : '' ?>>Emergency</option>
                                    <option value="upgrade" <?= $editing_maintenance && $editing_maintenance['maintenance_type'] == 'upgrade' ? 'selected' : '' ?>>Upgrade</option>
                                </select>
                            </div>
                            
                            <div class="mb-3">
                                <label for="maintenance_date" class="form-label">Maintenance Date *</label>
                                <input type="date" class="form-control" id="maintenance_date" name="maintenance_date" 
                                       value="<?= $editing_maintenance ? escape($editing_maintenance['maintenance_date']) : date('Y-m-d') ?>" required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="next_maintenance_date" class="form-label">Next Maintenance Date</label>
                                <input type="date" class="form-control" id="next_maintenance_date" name="next_maintenance_date" 
                                       value="<?= $editing_maintenance ? escape($editing_maintenance['next_maintenance_date']) : '' ?>">
                            </div>
                            
                             <div class="mb-3">
                                <label for="maintenance_cost" class="form-label">Estimated/Actual Cost (Rs.)</label>
                                <input type="number" step="0.01" class="form-control" id="maintenance_cost" name="maintenance_cost" 
                                       value="<?= $editing_maintenance ? escape($editing_maintenance['maintenance_cost']) : '' ?>">
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="service_provider" class="form-label">Service Provider</label>
                                <input type="text" class="form-control" id="service_provider" name="service_provider" 
                                       value="<?= $editing_maintenance ? escape($editing_maintenance['service_provider']) : '' ?>">
                            </div>
                            
                            <div class="mb-3">
                                <label for="technician_name" class="form-label">Technician Name</label>
                                <input type="text" class="form-control" id="technician_name" name="technician_name" 
                                       value="<?= $editing_maintenance ? escape($editing_maintenance['technician_name']) : '' ?>">
                            </div>
                            
                            <div class="mb-3">
                                <label for="status" class="form-label">Status</label>
                                <select class="form-select" id="status" name="status">
                                    <option value="pending" <?= $editing_maintenance && $editing_maintenance['status'] == 'pending' ? 'selected' : '' ?>>Pending</option>
                                    <option value="in_progress" <?= $editing_maintenance && $editing_maintenance['status'] == 'in_progress' ? 'selected' : '' ?>>In Progress</option>
                                    <option value="completed" <?= $editing_maintenance && $editing_maintenance['status'] == 'completed' ? 'selected' : '' ?>>Completed</option>
                                    <option value="cancelled" <?= $editing_maintenance && $editing_maintenance['status'] == 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                                </select>
                            </div>
                            
                            <div class="mb-3">
                                <label for="priority" class="form-label">Priority</label>
                                <select class="form-select" id="priority" name="priority">
                                    <option value="low" <?= $editing_maintenance && $editing_maintenance['priority'] == 'low' ? 'selected' : '' ?>>Low</option>
                                    <option value="medium" <?= $editing_maintenance && $editing_maintenance['priority'] == 'medium' ? 'selected' : '' ?>>Medium</option>
                                    <option value="high" <?= $editing_maintenance && $editing_maintenance['priority'] == 'high' ? 'selected' : '' ?>>High</option>
                                    <option value="critical" <?= $editing_maintenance && $editing_maintenance['priority'] == 'critical' ? 'selected' : '' ?>>Critical</option>
                                </select>
                            </div>
                            
                            <div class="mb-3">
                                <label for="warranty_period_months" class="form-label">Warranty Period (Months)</label>
                                <input type="number" class="form-control" id="warranty_period_months" name="warranty_period_months" 
                                       value="<?= $editing_maintenance ? escape($editing_maintenance['warranty_period_months']) : '0' ?>">
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="description" class="form-label">Description *</label>
                        <textarea class="form-control" id="description" name="description" rows="3" required><?= $editing_maintenance ? escape($editing_maintenance['description']) : '' ?></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <?php if ($editing_maintenance): ?>
                            <input type="hidden" name="maintenance_id" value="<?= $editing_maintenance['id'] ?>">
                            <button type="submit" name="edit_maintenance" class="btn btn-primary shadow-sm px-4">Update Maintenance</button>
                            <a href="maintenance_requests.php" class="btn btn-secondary shadow-sm px-4">Cancel Edit</a>
                        <?php else: ?>
                            <button type="submit" name="add_maintenance" class="btn btn-success shadow-sm px-4">Add Maintenance Request</button>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Search and Filter Form -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Search & Filter Maintenance Records</h3>
            </div>
            <div class="card-body">
                <form method="GET" class="row g-2 align-items-center">
                    <div class="col-md-4">
                        <input type="text" class="form-control" name="search" placeholder="Search by asset, description..." 
                               value="<?= escape($search) ?>">
                    </div>
                    <div class="col-md-3">
                        <select class="form-select" name="filter_status">
                            <option value="">All Statuses</option>
                            <option value="pending" <?= $filter_status == 'pending' ? 'selected' : '' ?>>Pending</option>
                            <option value="in_progress" <?= $filter_status == 'in_progress' ? 'selected' : '' ?>>In Progress</option>
                            <option value="completed" <?= $filter_status == 'completed' ? 'selected' : '' ?>>Completed</option>
                            <option value="cancelled" <?= $filter_status == 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                        </select>
                    </div>
                    <div class="col-md-auto">
                        <button type="submit" class="btn btn-primary shadow-sm px-4">Filter</button>
                        <a href="maintenance_requests.php" class="btn btn-outline-secondary ml-1 px-4">Clear</a>
                    </div>
                </form>
            </div>
        </div>
        
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Maintenance Records</h3>
            </div>
            
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Asset Description</th>
                                <th>Brand/Model</th>
                                <th>Maint. Type</th>
                                <th>Date</th>
                                <th>Service Provider</th>
                                <th>Maint. Cost</th>
                                <th>Status</th>
                                <th>Priority</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($maintenance_records as $record): 
                                $days_until_next = $record['next_maintenance_date'] ? (strtotime($record['next_maintenance_date']) - strtotime(date('Y-m-d'))) / (60 * 60 * 24) : null;
                                $is_due_soon = $days_until_next !== null && $days_until_next <= 7 && $days_until_next >= 0 && $record['status'] !== 'completed';
                                $is_overdue = $days_until_next !== null && $days_until_next < 0 && $record['status'] !== 'completed';
                            ?>
                                <tr class="<?= $is_overdue ? 'table-danger' : ($is_due_soon ? 'table-warning' : '') ?>">
                                    <td>
                                        <code><?= escape($record['asset_tag']) ?></code><br>
                                        <strong><?= escape($record['asset_name']) ?></strong>
                                    </td>
                                    <td>
                                        <div class="small"><?= escape($record['brand'] ?: '—') ?></div>
                                        <div class="small text-muted"><?= escape($record['model'] ?: '—') ?></div>
                                    </td>
                                    <td>
                                        <span class="badge badge-info"><?= ucfirst($record['maintenance_type']) ?></span>
                                    </td>
                                    <td><?= escape($record['maintenance_date']) ?></td>
                                    <td><?= escape($record['service_provider'] ?? 'Internal') ?></td>
                                    <td>Rs. <?= number_format($record['maintenance_cost'], 2) ?></td>
                                    <td>
                                        <?php 
                                        $status_badge = [
                                            'pending' => 'warning',
                                            'in_progress' => 'info',
                                            'completed' => 'success',
                                            'cancelled' => 'danger'
                                        ][$record['status']] ?? 'secondary';
                                        ?>
                                        <span class="badge badge-<?= $status_badge ?>"><?= ucfirst($record['status']) ?></span>
                                        <?php if ($is_due_soon): ?>
                                            <span class="badge badge-warning">Due Soon!</span>
                                        <?php elseif ($is_overdue): ?>
                                            <span class="badge badge-danger">OVERDUE!</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php 
                                        $priority_badge = [
                                            'low' => 'success',
                                            'medium' => 'info',
                                            'high' => 'warning',
                                            'critical' => 'danger'
                                        ][$record['priority']] ?? 'secondary';
                                        ?>
                                        <span class="badge badge-<?= $priority_badge ?>"><?= ucfirst($record['priority']) ?></span>
                                    </td>
                                    <td>
                                        <a href="?edit=<?= $record['id'] ?>" class="btn btn-sm btn-primary">Edit</a>
                                        
                                        <div class="dropdown d-inline">
                                            <button class="btn btn-sm btn-secondary dropdown-toggle" type="button" id="statusDropdown<?= $record['id'] ?>" data-bs-toggle="dropdown">
                                                Update Status
                                            </button>
                                            <div class="dropdown-menu">
                                                <form method="post" class="px-4 py-2" style="min-width: 250px;">
                                                    <input type="hidden" name="maintenance_id" value="<?= $record['id'] ?>">
                                                    <div class="mb-2">
                                                        <label class="form-label small">Status Note</label>
                                                        <textarea class="form-control form-control-sm" name="comments" placeholder="Comments..." rows="2"></textarea>
                                                    </div>
                                                    <div class="d-grid gap-1">
                                                        <button type="submit" name="update_status" value="pending" class="btn btn-xs btn-outline-warning text-left">Pending</button>
                                                        <button type="submit" name="update_status" value="in_progress" class="btn btn-xs btn-outline-info text-left">In Progress</button>
                                                        <button type="submit" name="update_status" value="completed" class="btn btn-xs btn-outline-success text-left">Completed</button>
                                                        <button type="submit" name="update_status" value="cancelled" class="btn btn-xs btn-outline-danger text-left">Cancelled</button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Pagination -->
                <div class="card-footer clearfix">
                    <ul class="pagination pagination-sm m-0 float-right">
                        <?php if ($page > 1): ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=<?= $page - 1 ?>&search=<?= urlencode($search) ?>&filter_status=<?= urlencode($filter_status) ?>">«</a>
                            </li>
                        <?php endif; ?>
                        
                        <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                            <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                                <a class="page-link" href="?page=<?= $i ?>&search=<?= urlencode($search) ?>&filter_status=<?= urlencode($filter_status) ?>"><?= $i ?></a>
                            </li>
                        <?php endfor; ?>
                        
                        <?php if ($page < $total_pages): ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=<?= $page + 1 ?>&search=<?= urlencode($search) ?>&filter_status=<?= urlencode($filter_status) ?>">»</a>
                            </li>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
include __DIR__ . '/../../includes/footer.php';
?>