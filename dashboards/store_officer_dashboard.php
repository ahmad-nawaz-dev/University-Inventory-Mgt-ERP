<?php
// dashboards/store_officer_dashboard.php - Store Officer Dashboard
require_once __DIR__ . '/../includes/header.php';

$pdo = getPDO();
$user = currentUser();

// Fetch store officer-specific data
$stats = [];

// Total assets in inventory
$where = " WHERE status = 'in_stock'";
$params = [];
if (!empty($user['department'])) {
    $where .= " AND department = ?";
    $params[] = $user['department'];
} else {
    // If store officer has no department assigned, show nothing to enforce strict isolation
    $where .= " AND 1=0";
}
$stmt = $pdo->prepare("SELECT COUNT(*) as count FROM assets" . $where);
$stmt->execute($params);
$stats['in_stock'] = $stmt->fetch()['count'];

// Pending GRNs
$where_grn = " WHERE g.received_at >= CURDATE()";
$params_grn = [];
if (!empty($user['department'])) {
    $where_grn .= " AND pr.department = ?";
    $params_grn[] = $user['department'];
} else {
    $where_grn .= " AND 1=0";
}
$stmt = $pdo->prepare("SELECT COUNT(*) as count FROM grn g LEFT JOIN purchase_orders po ON g.po_id = po.id LEFT JOIN purchase_requests pr ON po.request_id = pr.id" . $where_grn);
$stmt->execute($params_grn);
$stats['today_grn'] = $stmt->fetch()['count'];

// Pending purchase orders
$where_po = " WHERE po.status = 'pending'";
$params_po = [];
if (!empty($user['department'])) {
    $where_po .= " AND pr.department = ?";
    $params_po[] = $user['department'];
} else {
    $where_po .= " AND 1=0";
}
$stmt = $pdo->prepare("SELECT COUNT(*) as count FROM purchase_orders po LEFT JOIN purchase_requests pr ON po.request_id = pr.id" . $where_po);
$stmt->execute($params_po);
$stats['pending_pos'] = $stmt->fetch()['count'];

// Assets in repair
$where_repair = " WHERE status = 'in_repair'";
$params_repair = [];
if (!empty($user['department'])) {
    $where_repair .= " AND department = ?";
    $params_repair[] = $user['department'];
} else {
    $where_repair .= " AND 1=0";
}
$stmt = $pdo->prepare("SELECT COUNT(*) as count FROM assets" . $where_repair);
$stmt->execute($params_repair);
$stats['in_repair'] = $stmt->fetch()['count'];
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-lg-3 col-6">
            <div class="info-box shadow-sm">
                <span class="info-box-icon bg-success elevation-1"><i class="fas fa-box"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text text-muted text-uppercase text-xs font-weight-bold">In Stock</span>
                    <span class="info-box-number h4 font-weight-bold mb-0"><?= number_format($stats['in_stock']) ?></span>
                </div>
            </div>
        </div>
        
        <div class="col-lg-3 col-6">
            <div class="info-box shadow-sm">
                <span class="info-box-icon bg-info elevation-1"><i class="fas fa-clipboard-check"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text text-muted text-uppercase text-xs font-weight-bold">Today's Receipts</span>
                    <span class="info-box-number h4 font-weight-bold mb-0"><?= $stats['today_grn'] ?></span>
                </div>
            </div>
        </div>
        
        <div class="col-lg-3 col-6">
            <div class="info-box shadow-sm">
                <span class="info-box-icon bg-warning elevation-1"><i class="fas fa-shopping-cart"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text text-muted text-uppercase text-xs font-weight-bold">Pending POs</span>
                    <span class="info-box-number h4 font-weight-bold mb-0"><?= $stats['pending_pos'] ?></span>
                </div>
            </div>
        </div>
        
        <div class="col-lg-3 col-6">
            <div class="info-box shadow-sm">
                <span class="info-box-icon bg-danger elevation-1"><i class="fas fa-wrench"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text text-muted text-uppercase text-xs font-weight-bold">In Repair</span>
                    <span class="info-box-number h4 font-weight-bold mb-0"><?= $stats['in_repair'] ?></span>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Store Operations</h3>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-3">
                            <a href="<?= BASE_URL ?>/dashboards/procurement/grn.php" class="btn btn-success btn-block">
                                <i class="fas fa-clipboard-check"></i> Receive Items
                            </a>
                        </div>
                        <div class="col-md-3">
                            <a href="<?= BASE_URL ?>/dashboards/procurement/purchase_orders.php" class="btn btn-primary btn-block">
                                <i class="fas fa-shopping-cart"></i> Purchase Orders
                            </a>
                        </div>
                        <div class="col-md-3">
                            <a href="<?= BASE_URL ?>/dashboards/maintenance/maintenance_requests.php" class="btn btn-warning btn-block">
                                <i class="fas fa-tools"></i> Maintenance
                            </a>
                        </div>
                        <div class="col-md-3">
                            <a href="<?= BASE_URL ?>/dashboards/disposal/asset_disposal.php" class="btn btn-danger btn-block">
                                <i class="fas fa-trash-alt"></i> Disposal
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
include __DIR__ . '/../includes/footer.php';
?>