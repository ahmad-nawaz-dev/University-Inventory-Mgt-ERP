<?php
// dashboards/clerk_dashboard.php - Clerk Dashboard
require_once __DIR__ . '/../includes/header.php';

$pdo = getPDO();
$user = currentUser();

// Strict Departmental Hierarchy: Clerk is linked via clerk_id in university_departments
$dept_subquery = "SELECT name FROM university_departments WHERE clerk_id = ?";

// Fetch clerk-specific data
$stats = [];

// Department Assets (excluding in_repair)
$stmt = $pdo->prepare("SELECT COUNT(*) as count FROM assets WHERE department IN ($dept_subquery) AND status != 'in_repair'");
$stmt->execute([$user['id']]);
$stats['dept_assets'] = $stmt->fetch()['count'];

// Pending Indent Requests from their department
$stmt = $pdo->prepare("SELECT COUNT(*) as count FROM purchase_requests WHERE department IN ($dept_subquery) AND status = 'pending'");
$stmt->execute([$user['id']]);
$stats['pending_requests'] = $stmt->fetch()['count'];

// Assets in Repair in their department
$stmt = $pdo->prepare("SELECT COUNT(*) as count FROM assets WHERE department IN ($dept_subquery) AND status = 'in_repair'");
$stmt->execute([$user['id']]);
$stats['in_repair'] = $stmt->fetch()['count'];

// Recent Acquisitions (30 days) in their department
$stmt = $pdo->prepare("SELECT COUNT(*) as count FROM assets WHERE department IN ($dept_subquery) AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)");
$stmt->execute([$user['id']]);
$stats['recent_acquisitions'] = $stmt->fetch()['count'];

// Recent Department Requests (last 5)
$stmt = $pdo->prepare("
    SELECT pr.*, u.name as requested_by_name 
    FROM purchase_requests pr
    LEFT JOIN users u ON pr.requested_by = u.id
    WHERE pr.department IN ($dept_subquery)
    ORDER BY pr.created_at DESC
    LIMIT 5
");
$stmt->execute([$user['id']]);
$dept_requests = $stmt->fetchAll();

// Assets by condition in department
$stmt = $pdo->prepare("SELECT condition_status, COUNT(*) as count FROM assets WHERE department IN ($dept_subquery) GROUP BY condition_status");
$stmt->execute([$user['id']]);
$assets_by_condition = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
?>

<div class="container-fluid animation-fade-in">
    <!-- ═══ 3D PARALLAX STAT PANELS ═══ -->
    <div class="row mb-5">
        <div class="col-lg-3 col-md-6 col-sm-6 mb-4">
            <div class="nc-stat-card nc-gradient-indigo nc-animate-in">
                <i class="fas fa-cubes-stacked nc-stat-icon"></i>
                <div class="nc-stat-label">Department Assets</div>
                <div class="nc-stat-value">
                    <span class="nc-counter" data-target="<?= $stats['dept_assets'] ?>"><?= number_format($stats['dept_assets']) ?></span>
                </div>
                <div class="nc-stat-sub">Active inventory footprint</div>
            </div>
        </div>
        
        <div class="col-lg-3 col-md-6 col-sm-6 mb-4">
            <div class="nc-stat-card nc-gradient-amber nc-animate-in" style="animation-delay: 0.1s;">
                <i class="fas fa-file-invoice nc-stat-icon"></i>
                <div class="nc-stat-label">Pending Indents</div>
                <div class="nc-stat-value">
                    <span class="nc-counter" data-target="<?= $stats['pending_requests'] ?>"><?= $stats['pending_requests'] ?></span>
                </div>
                <div class="nc-stat-sub">Awaiting procurement action</div>
            </div>
        </div>
        
        <div class="col-lg-3 col-md-6 col-sm-6 mb-4">
            <div class="nc-stat-card nc-gradient-cyan nc-animate-in" style="animation-delay: 0.2s;">
                <i class="fas fa-microchip nc-stat-icon"></i>
                <div class="nc-stat-label">Repair Pipeline</div>
                <div class="nc-stat-value">
                    <span class="nc-counter" data-target="<?= $stats['in_repair'] ?>"><?= $stats['in_repair'] ?></span>
                </div>
                <div class="nc-stat-sub">Items in maintenance cycle</div>
            </div>
        </div>
        
        <div class="col-lg-3 col-md-6 col-sm-6 mb-4">
            <div class="nc-stat-card nc-gradient-emerald nc-animate-in" style="animation-delay: 0.3s;">
                <i class="fas fa-bolt-lightning nc-stat-icon"></i>
                <div class="nc-stat-label">Recent Inflow</div>
                <div class="nc-stat-value">
                    <span class="nc-counter" data-target="<?= $stats['recent_acquisitions'] ?>"><?= $stats['recent_acquisitions'] ?></span>
                </div>
                <div class="nc-stat-sub">New acquisitions (30d)</div>
            </div>
        </div>
    </div>

    <!-- ═══ RECENT REQUESTS + ASSET HEALTH ═══ -->
    <div class="row">
        <div class="col-xl-8 col-lg-7 mb-4">
            <div class="card card-outline card-primary nc-animate-in" style="animation-delay: 0.4s;">
                <div class="card-header border-0 pb-0">
                    <h3 class="card-title">
                        <i class="fas fa-stream text-primary me-2"></i>
                        Recent Department Activity
                    </h3>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>Ref ID</th>
                                    <th>Requested By</th>
                                    <th>Status</th>
                                    <th class="text-end">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($dept_requests)): ?>
                                    <tr>
                                        <td colspan="4" class="text-center py-5">
                                            <div class="text-muted opacity-40 mb-3">
                                                <i class="fas fa-layer-group fa-3x"></i>
                                            </div>
                                            <p class="text-muted">No recent requests found in your department.</p>
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($dept_requests as $req): ?>
                                        <tr>
                                            <td><code><?= escape($req['request_no']) ?></code></td>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <div class="rounded-circle bg-light border p-1 me-2" style="width: 24px; height: 24px; font-size: 0.6rem; display: flex; align-items: center; justify-content: center;">
                                                        <?= substr($req['requested_by_name'], 0, 1) ?>
                                                    </div>
                                                    <span><?= escape($req['requested_by_name']) ?></span>
                                                </div>
                                            </td>
                                            <td>
                                                <?php 
                                                $status_cfg = [
                                                    'pending' => ['b' => 'warning', 'i' => 'fa-clock'],
                                                    'approved' => ['b' => 'success', 'i' => 'fa-check-circle'],
                                                    'rejected' => ['b' => 'danger', 'i' => 'fa-times-circle']
                                                ][$req['status']] ?? ['b' => 'secondary', 'i' => 'fa-question-circle'];
                                                ?>
                                                <span class="badge badge-<?= $status_cfg['b'] ?>">
                                                    <i class="fas <?= $status_cfg['i'] ?> me-1"></i>
                                                    <?= ucfirst($req['status']) ?>
                                                </span>
                                            </td>
                                            <td class="text-end">
                                                <a href="<?= BASE_URL ?>/dashboards/procurement/indent_requests.php?edit=<?= $req['id'] ?>" class="btn btn-sm btn-info px-3">
                                                    Manage
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-xl-4 col-lg-5 mb-4">
            <!-- Asset Condition Summary -->
            <div class="card card-outline card-info mb-4 nc-animate-in" style="animation-delay: 0.5s;">
                <div class="card-header border-0 pb-0">
                    <h3 class="card-title">
                        <i class="fas fa-heart-pulse text-info me-2"></i>
                        Inventory Condition
                    </h3>
                </div>
                <div class="card-body">
                    <?php 
                    $total_dept = array_sum($assets_by_condition) ?: 1;
                    $condition_map = [
                        'good' => ['c' => 'success', 'l' => 'Operating at 100%'],
                        'fair' => ['c' => 'warning', 'l' => 'Minor attention needed'],
                        'poor' => ['c' => 'danger', 'l' => 'Critical maintenance']
                    ];
                    foreach ($condition_map as $c => $map): 
                        $count = $assets_by_condition[$c] ?? 0;
                        $percent = round(($count / $total_dept) * 100);
                    ?>
                    <div class="mb-4">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <div>
                                <h6 class="mb-0 text-capitalize font-weight-700"><?= $c ?></h6>
                                <small class="text-muted text-xs"><?= $map['l'] ?></small>
                            </div>
                            <div class="text-end">
                                <span class="badge badge-light border"><?= $count ?> units</span>
                            </div>
                        </div>
                        <div class="progress" style="height: 10px;">
                            <div class="progress-bar bg-<?= $map['c'] ?>" role="progressbar" 
                                 style="width: <?= $percent ?>%;"></div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Global Action Center -->
            <div class="card card-outline card-secondary nc-animate-in" style="animation-delay: 0.6s;">
                <div class="card-header border-0 pb-0">
                    <h3 class="card-title">
                        <i class="fas fa-crosshairs text-secondary me-2"></i>
                        Operations Center
                    </h3>
                </div>
                <div class="card-body pt-3">
                    <div class="d-grid gap-3">
                        <a href="<?= BASE_URL ?>/dashboards/procurement/indent_requests.php" class="btn btn-primary justify-content-center">
                            <i class="fas fa-plus-circle"></i> Create New Indent
                        </a>
                        <div class="row g-2">
                            <div class="col-6">
                                <a href="<?= BASE_URL ?>/dashboards/inventory/assets.php" class="btn btn-outline-info w-100 justify-content-center">
                                    <i class="fas fa-list"></i> Assets
                                </a>
                            </div>
                            <div class="col-6">
                                <a href="<?= BASE_URL ?>/dashboards/reports/stock_summary.php" class="btn btn-outline-warning w-100 justify-content-center">
                                    <i class="fas fa-chart-pie"></i> Stats
                                </a>
                            </div>
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
