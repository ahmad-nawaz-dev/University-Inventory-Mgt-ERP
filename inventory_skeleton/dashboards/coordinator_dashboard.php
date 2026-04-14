<?php
// dashboards/coordinator_dashboard.php - Coordinator Dashboard
require_once __DIR__ . '/../includes/header.php';

$pdo = getPDO();
$user = currentUser();

// Strict Departmental Hierarchy: Coordinator can oversee multiple departments
$dept_subquery = "SELECT name FROM university_departments WHERE coordinator_id = ?";
// No need to fetch a single department string anymore, we will use the subquery directly in SQL

// Fetch coordinator-specific data
$stats = [];

// Assets in their department (excluding those in repair)
$stmt = $pdo->prepare("SELECT COUNT(*) as count FROM assets WHERE department IN ($dept_subquery) AND status != 'in_repair'");
$stmt->execute([$user['id']]);
$stats['dept_assets'] = $stmt->fetch()['count'];

// Pending requests from their department
$stmt = $pdo->prepare("SELECT COUNT(*) as count FROM purchase_requests WHERE department IN ($dept_subquery) AND status = 'pending'");
$stmt->execute([$user['id']]);
$stats['pending_requests'] = $stmt->fetch()['count'];

// Assets needing attention
$stmt = $pdo->prepare("SELECT COUNT(*) as count FROM assets WHERE department IN ($dept_subquery) AND status IN ('in_repair', 'dead')");
$stmt->execute([$user['id']]);
$stats['attention_needed'] = $stmt->fetch()['count'];

// Upcoming maintenance in their department
$stmt = $pdo->prepare("
    SELECT COUNT(*) as count 
    FROM asset_maintenance am
    JOIN assets a ON am.asset_id = a.id
    WHERE a.department IN ($dept_subquery)
    AND am.maintenance_date <= DATE_ADD(CURDATE(), INTERVAL 7 DAY) 
    AND am.status != 'completed'
");
$stmt->execute([$user['id']]);
$stats['upcoming_maintenance'] = $stmt->fetch()['count'];
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-lg-3 col-6">
            <div class="info-box shadow-sm">
                <span class="info-box-icon bg-info elevation-1"><i class="fas fa-boxes"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text text-muted text-uppercase text-xs font-weight-bold">Department Assets</span>
                    <span class="info-box-number h4 font-weight-bold mb-0"><?= number_format($stats['dept_assets']) ?></span>
                </div>
            </div>
        </div>
        
        <div class="col-lg-3 col-6">
            <div class="info-box shadow-sm">
                <span class="info-box-icon bg-warning elevation-1"><i class="fas fa-file-alt"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text text-muted text-uppercase text-xs font-weight-bold">Pending Requests</span>
                    <span class="info-box-number h4 font-weight-bold mb-0"><?= $stats['pending_requests'] ?></span>
                </div>
            </div>
        </div>
        
        <div class="col-lg-3 col-6">
            <div class="info-box shadow-sm">
                <span class="info-box-icon bg-danger elevation-1"><i class="fas fa-exclamation-triangle"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text text-muted text-uppercase text-xs font-weight-bold">Needs Attention</span>
                    <span class="info-box-number h4 font-weight-bold mb-0"><?= $stats['attention_needed'] ?></span>
                </div>
            </div>
        </div>
        
        <div class="col-lg-3 col-6">
            <div class="info-box shadow-sm">
                <span class="info-box-icon bg-success elevation-1"><i class="fas fa-tools"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text text-muted text-uppercase text-xs font-weight-bold">Upcoming Maintenance</span>
                    <span class="info-box-number h4 font-weight-bold mb-0"><?= $stats['upcoming_maintenance'] ?></span>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Quick Access</h3>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-3">
                            <a href="<?= BASE_URL ?>/dashboards/procurement/indent_requests.php" class="btn btn-primary btn-block">
                                <i class="fas fa-file-medical"></i> New Request
                            </a>
                        </div>
                        <div class="col-md-3">
                            <a href="<?= BASE_URL ?>/dashboards/inventory/assets.php" class="btn btn-info btn-block">
                                <i class="fas fa-box"></i> Manage Assets
                            </a>
                        </div>
                        <div class="col-md-3">
                            <a href="<?= BASE_URL ?>/dashboards/maintenance/maintenance_requests.php" class="btn btn-warning btn-block">
                                <i class="fas fa-tools"></i> Maintenance
                            </a>
                        </div>
                        <div class="col-md-3">
                            <a href="<?= BASE_URL ?>/dashboards/university/asset_allocation.php" class="btn btn-success btn-block">
                                <i class="fas fa-user-tag"></i> Track Assets
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