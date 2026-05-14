<?php
// dashboards/disposal/asset_disposal.php - Refined University Asset Disposal & Surplus
require_once __DIR__ . '/../../includes/header.php';

$message = '';
$error   = '';
$pdo     = getPDO();

// Handle disposal updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['request_disposal'])) {
    $asset_id = (int)$_POST['asset_id'];
    $method   = trim($_POST['disposal_method']);
    $notes    = trim($_POST['disposal_notes']);

    if ($asset_id && !empty($method)) {
        $stmt = $pdo->prepare("INSERT INTO asset_disposals (asset_id, disposal_method, disposal_notes, requested_by, status) VALUES (?, ?, ?, ?, 'pending')");
        if ($stmt->execute([$asset_id, $method, $notes, currentUser()['id']])) {
            $message = "Disposal request submitted for <strong>Approval</strong> successfully!";
        }
    } else {
        $error = "Asset and Method are required.";
    }
}
// Handle disposal approvals
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['approve_disposal'])) {
    if (currentUser()['role'] === 'super_admin') {
        $disposal_id = (int)$_POST['disposal_id'];
        $stmt = $pdo->prepare("UPDATE asset_disposals SET status = 'completed', completed_date = NOW(), approved_by_level1 = ? WHERE id = ?");
        if ($stmt->execute([currentUser()['id'], $disposal_id])) {
            $pdo->prepare("UPDATE assets SET status = 'disposed' WHERE id = (SELECT asset_id FROM asset_disposals WHERE id = ?)")->execute([$disposal_id]);
            $message = "Disposal request approved successfully! Asset marked as disposed.";
        } else {
            $error = "Error approving disposal.";
        }
    } else {
        $error = "Access Denied: Only Admin can approve disposals.";
    }
}

// Handle disposal rejections
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reject_disposal'])) {
    if (currentUser()['role'] === 'super_admin') {
        $disposal_id = (int)$_POST['disposal_id'];
        $stmt = $pdo->prepare("UPDATE asset_disposals SET status = 'rejected' WHERE id = ?");
        if ($stmt->execute([$disposal_id])) {
            $message = "Disposal request rejected.";
        }
    } else {
        $error = "Access Denied: Only Admin can reject disposals.";
    }
}

// Fetch lists
$user = currentUser();
$where_assets = "WHERE status IN ('in_repair', 'dead')";
$where_disposals = "WHERE 1=1";
$params_assets = [];
$params_disposals = [];

if ($user['role'] === 'hod') {
    $where_assets .= " AND department IN (SELECT name FROM university_departments WHERE hod_id = ?)";
    $where_disposals .= " AND a.department IN (SELECT name FROM university_departments WHERE hod_id = ?)";
    $params_assets[] = $user['id'];
    $params_disposals[] = $user['id'];
} elseif ($user['role'] === 'coordinator') {
    $where_assets .= " AND department IN (SELECT name FROM university_departments WHERE coordinator_id = ?)";
    $where_disposals .= " AND a.department IN (SELECT name FROM university_departments WHERE coordinator_id = ?)";
    $params_assets[] = $user['id'];
    $params_disposals[] = $user['id'];
}

$assets_stmt = $pdo->prepare("SELECT id, asset_tag, name, serial_number, department, status FROM assets $where_assets ORDER BY asset_tag ASC");
$assets_stmt->execute($params_assets);
$assets = $assets_stmt->fetchAll();

// Search and Pagination for disposals
$search = trim($_GET['search'] ?? '');
$limit = 10;
$page = max(1, (int)($_GET['page'] ?? 1));
$offset = ($page - 1) * $limit;

$disposal_where = $where_disposals;
if (!empty($search)) {
    $disposal_where .= " AND (a.asset_tag LIKE ? OR a.name LIKE ? OR u.name LIKE ?)";
    $search_param = '%' . $search . '%';
    $params_disposals[] = $search_param;
    $params_disposals[] = $search_param;
    $params_disposals[] = $search_param;
}

// Count total disposals
$count_sql = "SELECT COUNT(*) FROM asset_disposals ad LEFT JOIN assets a ON ad.asset_id = a.id LEFT JOIN users u ON ad.requested_by = u.id $disposal_where";
$count_stmt = $pdo->prepare($count_sql);
$count_stmt->execute($params_disposals);
$total_disposals = $count_stmt->fetchColumn();
$total_pages = ceil($total_disposals / $limit);

// Re-build params for the main query (count consumed them)
$params_disp_main = $params_disposals;

$disposals_stmt = $pdo->prepare("
    SELECT ad.*, a.asset_tag, a.name as asset_name, u.name as requestor_name
    FROM asset_disposals ad
    LEFT JOIN assets a ON ad.asset_id = a.id
    LEFT JOIN users u ON ad.requested_by = u.id
    $disposal_where
    ORDER BY ad.requested_date DESC LIMIT ? OFFSET ?
");
$param_index = 1;
foreach ($params_disp_main as $p) {
    $disposals_stmt->bindValue($param_index++, $p);
}
$disposals_stmt->bindValue($param_index++, $limit, PDO::PARAM_INT);
$disposals_stmt->bindValue($param_index++, $offset, PDO::PARAM_INT);
$disposals_stmt->execute();
$disposals = $disposals_stmt->fetchAll();
?>

<div class="row">
    <!-- LEFT: DISPOSAL FORM -->
    <div class="col-md-5">
        <div class="card card-outline card-danger shadow-sm mb-4">
            <div class="card-header bg-white">
                <h3 class="card-title font-weight-bold text-danger"><i class="fas fa-trash-alt mr-1"></i> Disposal & Surplus Request</h3>
            </div>
            <div class="card-body">
                <?php if($message): ?><div class="alert alert-success alert-dismissible"><button type="button" class="close" data-dismiss="alert">&times;</button><?= $message ?></div><?php endif; ?>
                <?php if($error): ?><div class="alert alert-danger alert-dismissible"><button type="button" class="close" data-dismiss="alert">&times;</button><?= $error ?></div><?php endif; ?>

                <div class="alert alert-light border small shadow-sm mb-4">
                    <i class="fas fa-university mr-1 text-primary"></i> <strong>Note:</strong> Disposing of University property requires multi-level approvals. Ensure the equipment is marked as <em>Dead</em> or <em>In-Repair</em> first.
                </div>

                <form method="post">
                    <div class="mb-3">
                        <label class="form-label">Asset for Disposal <span class="text-danger">*</span></label>
                        <select name="asset_id" id="asset_id" class="form-select select2" required>
                            <option value="">— Select Failed Asset —</option>
                            <?php foreach($assets as $a): ?>
                                <option value="<?= $a['id'] ?>">
                                    <?= escape($a['asset_tag']) ?>: <?= escape($a['name']) ?> (Status: <?= escape($a['status']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Disposal Method</label>
                        <select name="disposal_method" class="form-select">
                            <option value="scrapped">Scrapped / Salvage</option>
                            <option value="recycling">e-Waste Recycling</option>
                            <option value="donation">Internal Donation</option>
                            <option value="sale">Auction / Sale</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Reason & Authorization Notes</label>
                        <textarea name="disposal_notes" rows="3" class="form-control" placeholder="Describe mechanical failure or obsolescence details"></textarea>
                    </div>

                    <button type="submit" name="request_disposal" class="btn btn-danger btn-block shadow-sm">
                        <i class="fas fa-paper-plane mr-1"></i> Submit for Approval
                    </button>
                </form>
            </div>
        </div>

        <div class="card card-outline card-secondary shadow-sm">
            <div class="card-header bg-white"><h3 class="card-title font-weight-bold">Environmental Compliance</h3></div>
            <div class="card-body">
                <p class="text-muted small">All IT equipment must be disposed of via authorized e-waste channels to comply with University Policy 402.</p>
                <div class="d-flex justify-content-between align-items-center">
                    <span class="text-xs text-muted">Last Audit: Mar 2024</span>
                    <button class="btn btn-xs btn-outline-secondary">Download Policy</button>
                </div>
            </div>
        </div>
    </div>

    <!-- RIGHT: PENDING APPROVALS -->
    <div class="col-md-7">
        <div class="card card-outline card-warning shadow-sm">
            <div class="card-header bg-white border-bottom">
                <h3 class="card-title font-weight-bold text-dark"><i class="fas fa-file-signature text-warning mr-1"></i> Recent Records & Approvals</h3>
                <div class="card-tools">
                    <form method="get" class="input-group input-group-sm" style="width: 250px;">
                        <input type="text" name="search" class="form-control" placeholder="Search asset, requestor..." value="<?= escape($search) ?>">
                        <div class="input-group-append">
                            <button type="submit" class="btn btn-default"><i class="fas fa-search"></i></button>
                            <?php if (!empty($search)): ?>
                                <a href="asset_disposal.php" class="btn btn-default" title="Clear"><i class="fas fa-times"></i></a>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
            </div>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover table-striped mb-0">
                        <thead>
                            <tr class="bg-light">
                                <th>Asset Details</th>
                                <th>Method / Reason</th>
                                <th>Requestor</th>
                                <th>Status</th>
                                <th class="text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($disposals as $row): 
                                $badge = ['pending' => 'warning', 'rejected' => 'danger', 'completed' => 'success'][$row['status']] ?? 'info';
                            ?>
                            <tr>
                                <td>
                                    <strong><?= escape($row['asset_tag']) ?></strong><br>
                                    <small class="text-muted"><?= escape($row['asset_name']) ?></small>
                                </td>
                                <td>
                                    <span class="badge badge-light border mb-1"><?= ucfirst(escape($row['disposal_method'])) ?></span>
                                    <div class="text-xs text-muted"><?= escape(substr($row['disposal_notes'], 0, 40)) ?>...</div>
                                </td>
                                <td><small class="text-primary"><?= escape($row['requestor_name']) ?></small></td>
                                <td><span class="badge badge-<?= $badge ?> font-weight-bold"><?= strtoupper(escape($row['status'])) ?></span></td>
                                <td class="text-right d-flex justify-content-end align-items-center">
                                    <button class="btn btn-xs btn-outline-dark shadow-sm mr-1"><i class="fas fa-search"></i> Details</button>
                                    <?php if ($row['status'] === 'pending' && currentUser()['role'] === 'super_admin'): ?>
                                    <form method="post" style="display: inline; margin: 0;">
                                        <input type="hidden" name="disposal_id" value="<?= $row['id'] ?>">
                                        <button type="submit" name="approve_disposal" class="btn btn-xs btn-success shadow-sm mr-1" onclick="return confirm('Approve disposal? The asset will be permanently marked as Disposed.');">Approve</button>
                                        <button type="submit" name="reject_disposal" class="btn btn-xs btn-danger shadow-sm" onclick="return confirm('Reject this disposal request?');">Reject</button>
                                    </form>
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
                    <span class="float-left text-muted">Showing <?= count($disposals) ?> of <?= $total_disposals ?> records</span>
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
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>