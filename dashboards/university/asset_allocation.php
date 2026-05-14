<?php
// dashboards/university/asset_allocation.php - Refined University Asset Allocation Tracker
require_once __DIR__ . '/../../includes/header.php';

$message = '';
$error   = '';

$pdo = getPDO();

// Pagination and Search
$page   = max(1, (int)($_GET['page'] ?? 1));
$search = trim($_GET['search'] ?? '');
$limit  = 10;
$offset = ($page - 1) * $limit;

$where  = "WHERE 1=1";
$params = [];

if (!empty($search)) {
    $where .= " AND (a.asset_tag LIKE ? OR a.name LIKE ? OR a.serial_number LIKE ? OR a.department LIKE ? OR d.name LIKE ? OR u.name LIKE ?)";
    $params = ["%$search%", "%$search%", "%$search%", "%$search%", "%$search%", "%$search%"];
}

// Strict Departmental Isolation
$user = currentUser();
$where .= " AND a.status != 'in_repair'"; // Supervisor: remove from dept inventory when in maintenance
if ($user['role'] === 'hod') {
    $where .= " AND a.department IN (SELECT name FROM university_departments WHERE hod_id = ?)";
    $params[] = $user['id'];
} elseif ($user['role'] === 'coordinator') {
    $where .= " AND a.department IN (SELECT name FROM university_departments WHERE coordinator_id = ?)";
    $params[] = $user['id'];
}

// Count total records
$count_stmt = $pdo->prepare("
    SELECT COUNT(*) 
    FROM assets a
    LEFT JOIN university_departments d ON a.department = d.name
    LEFT JOIN users u ON a.assigned_to_user_id = u.id
    $where");
$count_stmt->execute($params);
$total_records = $count_stmt->fetchColumn();
$total_pages   = max(1, ceil($total_records / $limit));

// Fetch paginated records
$sql = "
    SELECT a.*, ac.name as category_name, u.name as assigned_user_name, d.name as dept_name, d.hod_id, hod_user.name as hod_name
    FROM assets a
    LEFT JOIN asset_categories ac ON a.category_id = ac.id
    LEFT JOIN users u ON a.assigned_to_user_id = u.id
    LEFT JOIN university_departments d ON a.department = d.name
    LEFT JOIN users hod_user ON d.hod_id = hod_user.id
    $where
    ORDER BY a.created_at DESC
    LIMIT ? OFFSET ?";
$stmt = $pdo->prepare($sql);

$param_index = 1;
foreach ($params as $val) {
    $stmt->bindValue($param_index++, $val);
}

$stmt->bindValue($param_index++, $limit, PDO::PARAM_INT);
$stmt->bindValue($param_index++, $offset, PDO::PARAM_INT);

$stmt->execute();
$allocations = $stmt->fetchAll();
?>

<div class="row">
    <div class="col-12">
        <!-- HEADER STATS -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="info-box shadow-sm">
                    <span class="info-box-icon bg-primary"><i class="fas fa-layer-group"></i></span>
                    <div class="info-box-content">
                        <span class="info-box-text text-muted text-xs text-uppercase font-weight-bold">Total Assets</span>
                        <span class="info-box-number h4 mb-0"><?= number_format($total_records) ?></span>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="info-box shadow-sm">
                    <span class="info-box-icon bg-success"><i class="fas fa-user-check"></i></span>
                    <div class="info-box-content">
                        <span class="info-box-text text-muted text-xs text-uppercase font-weight-bold">Allocated</span>
                        <span class="info-box-number h4 mb-0">
                            <?php 
                            $alloc_count = $pdo->query("SELECT COUNT(*) FROM assets WHERE status = 'allocated'")->fetchColumn();
                            echo number_format($alloc_count);
                            ?>
                        </span>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="info-box shadow-sm">
                    <span class="info-box-icon bg-info"><i class="fas fa-university"></i></span>
                    <div class="info-box-content">
                        <span class="info-box-text text-muted text-xs text-uppercase font-weight-bold">Active Depts</span>
                        <span class="info-box-number h4 mb-0"><?= $pdo->query("SELECT COUNT(*) FROM university_departments")->fetchColumn() ?></span>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="info-box shadow-sm">
                    <span class="info-box-icon bg-warning"><i class="fas fa-tools"></i></span>
                    <div class="info-box-content">
                        <span class="info-box-text text-muted text-xs text-uppercase font-weight-bold">In Repair</span>
                        <span class="info-box-number h4 mb-0">
                            <?= $pdo->query("SELECT COUNT(*) FROM assets WHERE status = 'in_repair'")->fetchColumn() ?>
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <div class="card card-outline card-primary shadow-sm">
            <div class="card-header bg-white border-bottom">
                <h3 class="card-title font-weight-bold">
                    <i class="fas fa-exchange-alt mr-1 text-primary"></i> Asset Allocation Details
                </h3>
                <div class="card-tools">
                    <form method="GET" class="input-group input-group-sm" style="width: 280px;">
                        <input type="text" name="search" class="form-control float-right" placeholder="Search Tag, Serial, Dept, User..." value="<?= escape($search) ?>">
                        <div class="input-group-append">
                            <button type="submit" class="btn btn-default"><i class="fas fa-search"></i></button>
                            <?php if(!empty($search)): ?>
                                <a href="asset_allocation.php" class="btn btn-default"><i class="fas fa-times"></i></a>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
            </div>
            
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover table-striped mb-0">
                        <thead class="bg-light">
                            <tr>
                                <th>Asset Tag / SN</th>
                                <th>Name / Model</th>
                                <th>Brand</th>
                                <th>Dept / HOD</th>
                                <th>Assigned To</th>
                                <th>Location</th>
                                <th>Condition</th>
                                <th>Status</th>
                                <th class="text-right">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($allocations)): ?>
                                <tr><td colspan="8" class="text-center py-5 text-muted">No allocations found.</td></tr>
                            <?php else: ?>
                                <?php foreach ($allocations as $item): 
                                    $sb = ['in_stock' => 'success', 'allocated' => 'info', 'in_repair' => 'warning', 'dead' => 'danger'][$item['status']] ?? 'secondary';
                                    $cb = ['good' => 'success', 'fair' => 'warning', 'poor' => 'danger'][$item['condition_status']] ?? 'light';
                                ?>
                                    <tr>
                                        <td>
                                            <code><?= escape($item['asset_tag']) ?></code>
                                            <?php if($item['serial_number']): ?>
                                                <div class="small text-muted">SN: <?= escape($item['serial_number']) ?></div>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="font-weight-bold"><?= escape($item['name']) ?></div>
                                            <small class="text-muted"><?= escape($item['model'] ?: 'N/A') ?></small>
                                        </td>
                                        <td><?= escape($item['brand'] ?: '—') ?></td>
                                        <td>
                                            <strong><?= escape($item['dept_name'] ?? $item['department']) ?></strong>
                                            <div class="small text-primary">HOD: <?= escape($item['hod_name'] ?: 'N/A') ?></div>
                                        </td>
                                        <td>
                                            <?php if ($item['assigned_user_name']): ?>
                                                <span class="badge badge-info shadow-sm"><i class="fas fa-user mr-1"></i> <?= escape($item['assigned_user_name']) ?></span>
                                            <?php else: ?>
                                                <span class="text-muted small"><em>Unassigned</em></span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= escape($item['class_location'] ?: '—') ?></td>
                                        <td><span class="badge badge-<?= $cb ?> text-uppercase shadow-sm" style="font-size: 0.7rem;"><?= escape($item['condition_status']) ?></span></td>
                                        <td><span class="badge badge-<?= $sb ?> shadow-sm"><?= ucfirst(str_replace('_', ' ', $item['status'])) ?></span></td>
                                        <td class="text-right">
                                            <a href="<?= BASE_URL ?>/dashboards/inventory/assets.php?edit=<?= $item['id'] ?>" class="btn btn-xs btn-outline-primary shadow-sm">
                                                <i class="fas fa-external-link-alt mr-1"></i> View Detail
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <?php if ($total_pages > 1): ?>
                <div class="card-footer bg-white clearfix">
                    <ul class="pagination pagination-sm m-0 float-right">
                        <?php if ($page > 1): ?>
                            <li class="page-item"><a class="page-link" href="?page=<?= $page - 1 ?>&search=<?= urlencode($search) ?>">«</a></li>
                        <?php endif; ?>
                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                            <li class="page-item <?= $i == $page ? 'active' : '' ?>"><a class="page-link" href="?page=<?= $i ?>&search=<?= urlencode($search) ?>"><?= $i ?></a></li>
                        <?php endfor; ?>
                        <?php if ($page < $total_pages): ?>
                            <li class="page-item"><a class="page-link" href="?page=<?= $page + 1 ?>&search=<?= urlencode($search) ?>">»</a></li>
                        <?php endif; ?>
                    </ul>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>