<?php
// dashboards/super_admin/index.php - University Asset Dashboard (Final Polish)
require_once __DIR__ . '/../../includes/header.php';

$pdo = getPDO();

// Total Assets
$stats['total_assets'] = $pdo->query("SELECT COUNT(*) FROM assets")->fetchColumn();

// Total Asset Value
$stats['total_value'] = $pdo->query("SELECT SUM(purchase_cost) FROM assets")->fetchColumn() ?? 0;

// Assets by Status
$stats['assets_by_status'] = $pdo->query("SELECT status, COUNT(*) as count FROM assets GROUP BY status")->fetchAll(PDO::FETCH_KEY_PAIR);

// Assets by Condition (New University Field)
$stats['assets_by_condition'] = $pdo->query("SELECT condition_status, COUNT(*) as count FROM assets GROUP BY condition_status")->fetchAll(PDO::FETCH_KEY_PAIR);

// Assets by Department (Top 5) - Excluding In Repair
$stats['assets_by_dept'] = $pdo->query("SELECT department, COUNT(*) as count FROM assets WHERE department IS NOT NULL AND department != '' AND status != 'in_repair' GROUP BY department ORDER BY count DESC LIMIT 5")->fetchAll(PDO::FETCH_KEY_PAIR);

// Recent Activities
$stats['recent_activities'] = $pdo->query("SELECT action, details, created_at FROM audit_logs ORDER BY created_at DESC LIMIT 5")->fetchAll();

// Pending Requests
$stats['pending_requests'] = $pdo->query("SELECT COUNT(*) FROM purchase_requests WHERE status = 'pending'")->fetchColumn();

// Assets needing repair
$stats['repair_assets'] = $pdo->query("SELECT COUNT(*) FROM assets WHERE status = 'in_repair'")->fetchColumn();

// Recent Acquisitions (30 days)
$stats['recent_acquisitions'] = $pdo->query("SELECT COUNT(*) FROM assets WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)")->fetchColumn();
?>

<div class="container-fluid">
    <!-- STAT CARDS -->
    <div class="row">
        <div class="col-lg-3 col-6">
            <div class="info-box shadow-sm">
                <span class="info-box-icon bg-primary elevation-1"><i class="fas fa-cubes"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text text-muted text-xs text-uppercase font-weight-bold">Total Assets</span>
                    <span class="info-box-number h4 font-weight-bold mb-0"><?= number_format($stats['total_assets']) ?></span>
                </div>
            </div>
        </div>

        <div class="col-lg-3 col-6">
            <div class="info-box shadow-sm">
                <span class="info-box-icon bg-success elevation-1"><i class="fas fa-hand-holding-usd"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text text-muted text-xs text-uppercase font-weight-bold">Inventory Value</span>
                    <span class="info-box-number h4 font-weight-bold mb-0">Rs. <?= number_format($stats['total_value'], 2) ?></span>
                </div>
            </div>
        </div>

        <div class="col-lg-3 col-6">
            <div class="info-box shadow-sm">
                <span class="info-box-icon bg-warning elevation-1"><i class="fas fa-file-invoice"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text text-muted text-xs text-uppercase font-weight-bold">Pending Req.</span>
                    <span class="info-box-number h4 font-weight-bold mb-0"><?= $stats['pending_requests'] ?></span>
                </div>
            </div>
        </div>

        <div class="col-lg-3 col-6">
            <div class="info-box shadow-sm">
                <span class="info-box-icon bg-danger elevation-1"><i class="fas fa-tools"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text text-muted text-xs text-uppercase font-weight-bold">In-Repair</span>
                    <span class="info-box-number h4 font-weight-bold mb-0"><?= $stats['repair_assets'] ?></span>
                </div>
            </div>
        </div>
    </div>

    <!-- MAIN CHARTS -->
    <div class="row">
        <div class="col-md-8">
            <div class="card card-outline card-primary shadow-sm">
                <div class="card-header border-0"><h3 class="card-title font-weight-bold">Dept Distribution</h3></div>
                <div class="card-body">
                    <canvas id="departmentChart" style="min-height: 250px; height: 250px;"></canvas>
                </div>
            </div>
            
            <div class="card card-outline card-info shadow-sm">
                <div class="card-header border-0"><h3 class="card-title font-weight-bold">Asset Condition Status</h3></div>
                <div class="card-body">
                    <div class="row">
                        <?php 
                        foreach(['good' => 'success', 'fair' => 'warning', 'poor' => 'danger'] as $c => $color): 
                            $count = $stats['assets_by_condition'][$c] ?? 0;
                            $percent = $stats['total_assets'] > 0 ? round(($count / $stats['total_assets']) * 100) : 0;
                        ?>
                        <div class="col-4 text-center">
                            <input type="text" class="knob" value="<?= $percent ?>" data-width="90" data-height="90" data-fgColor="<?= $color === 'success' ? '#28a745' : ($color === 'warning' ? '#ffc107' : '#dc3545') ?>" readonly>
                            <div class="text-muted text-xs text-uppercase font-weight-bold mt-2"><?= $c ?></div>
                            <div class="h5 font-weight-bold"><?= $count ?> Items</div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="info-box mb-3 bg-gradient-info text-white shadow-sm">
                <span class="info-box-icon"><i class="fas fa-chart-line"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text">Serviceable Assets</span>
                    <span class="info-box-number"><?= $stats['total_assets'] - $stats['repair_assets'] ?></span>
                </div>
            </div>

            <div class="card card-outline card-secondary shadow-sm">
                <div class="card-header"><h3 class="card-title font-weight-bold">Recent Acquisitions</h3></div>
                <div class="card-body">
                    <div class="text-center py-3">
                        <div class="display-4 text-primary font-weight-bold"><?= $stats['recent_acquisitions'] ?></div>
                        <p class="text-muted text-sm">New assets added this month</p>
                    </div>
                </div>
            </div>

            <div class="card card-outline card-dark shadow-sm">
                <div class="card-header"><h3 class="card-title font-weight-bold">System Management</h3></div>
                <div class="card-body p-0">
                    <div class="list-group list-group-flush">
                        <a href="manage_users.php" class="list-group-item list-group-item-action border-0">
                            <i class="fas fa-users-cog text-primary me-2"></i> User Management
                        </a>
                        <a href="manage_roles.php" class="list-group-item list-group-item-action border-0">
                            <i class="fas fa-user-tag text-success me-2"></i> Role Management
                        </a>
                        <a href="manage_access.php" class="list-group-item list-group-item-action border-0">
                            <i class="fas fa-user-shield text-danger me-2"></i> Permission Matrix
                        </a>
                        <a href="manage_pages.php" class="list-group-item list-group-item-action border-0">
                            <i class="fas fa-file-code text-info me-2"></i> Page Management
                        </a>
                    </div>
                </div>
            </div>

            <div class="card card-outline card-dark shadow-sm">
                <div class="card-header"><h3 class="card-title font-weight-bold">Recent System Logs</h3></div>
                <div class="card-body p-0">
                    <ul class="list-group list-group-flush">
                        <?php foreach ($stats['recent_activities'] as $log): ?>
                            <li class="list-group-item px-3 py-2 border-0">
                                <small class="text-primary font-weight-bold d-block"><?= escape($log['action']) ?></small>
                                <div class="text-xs text-muted"><?= escape($log['details']) ?></div>
                                <span class="text-xs text-muted float-right"><?= date('M j', strtotime($log['created_at'])) ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const ctx = document.getElementById('departmentChart').getContext('2d');
    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: <?= json_encode(array_keys($stats['assets_by_dept'])) ?>,
            datasets: [{
                label: 'Asset Count',
                data: <?= json_encode(array_values($stats['assets_by_dept'])) ?>,
                backgroundColor: 'rgba(54, 162, 235, 0.7)',
                borderColor: 'rgba(54, 162, 235, 1)',
                borderWidth: 1,
                borderRadius: 5
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: { y: { beginAtZero: true } },
            plugins: { legend: { display: false } }
        }
    });
});
</script>
<?php include __DIR__ . '/../../includes/footer.php'; ?>