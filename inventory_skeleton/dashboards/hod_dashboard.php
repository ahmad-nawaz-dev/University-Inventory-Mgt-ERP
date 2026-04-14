<?php
// dashboards/hod_dashboard.php - HOD Dashboard
require_once __DIR__ . '/../includes/header.php';

$pdo = getPDO();
$user = currentUser();

// Strict Departmental Hierarchy: HOD can oversee multiple departments
$dept_subquery = "SELECT name FROM university_departments WHERE hod_id = ?";
// No need to fetch a single department string anymore, we will use the subquery directly in SQL

// Fetch HOD-specific data
$stats = [];

// Assets in their department (excluding those in repair as per supervisor requirement)
$stmt = $pdo->prepare("SELECT COUNT(*) as count FROM assets WHERE department IN ($dept_subquery) AND status != 'in_repair'");
$stmt->execute([$user['id']]);
$stats['dept_assets'] = $stmt->fetch()['count'];

// Their assigned assets
$stmt = $pdo->prepare("SELECT COUNT(*) as count FROM assets WHERE assigned_to_user_id = ?");
$stmt->execute([$user['id']]);
$stats['assigned_assets'] = $stmt->fetch()['count'];

// Pending requests from their department
$stmt = $pdo->prepare("SELECT COUNT(*) as count FROM purchase_requests WHERE department IN ($dept_subquery) AND status = 'pending'");
$stmt->execute([$user['id']]);
$stats['pending_requests'] = $stmt->fetch()['count'];

// Assets in repair in their department
$stmt = $pdo->prepare("SELECT COUNT(*) as count FROM assets WHERE department IN ($dept_subquery) AND status = 'in_repair'");
$stmt->execute([$user['id']]);
$stats['dept_repair'] = $stmt->fetch()['count'];

// Budget for their departments (current year)
$current_year = date('Y');
$stmt = $pdo->prepare("SELECT SUM(allocated_amount) as allocated_amount, SUM(spent_amount) as spent_amount, SUM(remaining_amount) as remaining_amount FROM department_budgets WHERE department_name IN ($dept_subquery) AND budget_year = ?");
$stmt->execute([$user['id'], $current_year]);
$budget = $stmt->fetch();

$stats['total_budget'] = $budget['allocated_amount'] ?? 0;
$stats['spent_budget'] = $budget['spent_amount'] ?? 0;
$stats['remaining_budget'] = $budget['remaining_amount'] ?? 0;

// Recent activities in their department
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
                <span class="info-box-icon bg-success elevation-1"><i class="fas fa-user-tag"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text text-muted text-uppercase text-xs font-weight-bold">Assigned to You</span>
                    <span class="info-box-number h4 font-weight-bold mb-0"><?= number_format($stats['assigned_assets']) ?></span>
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
                <span class="info-box-icon bg-danger elevation-1"><i class="fas fa-wrench"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text text-muted text-uppercase text-xs font-weight-bold">Needs Repair</span>
                    <span class="info-box-number h4 font-weight-bold mb-0"><?= $stats['dept_repair'] ?></span>
                </div>
            </div>
        </div>
    </div>
    <div class="row">
        <div class="col-lg-4 col-6">
            <div class="info-box shadow-sm border-left border-primary">
                <span class="info-box-icon bg-primary elevation-1"><i class="fas fa-wallet"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text text-muted text-uppercase text-xs font-weight-bold">Total Budget (<?= date('Y') ?>)</span>
                    <span class="info-box-number h4 font-weight-bold mb-0">Rs. <?= number_format($stats['total_budget'], 2) ?></span>
                </div>
            </div>
        </div>
        <div class="col-lg-4 col-6">
            <div class="info-box shadow-sm border-left border-danger">
                <span class="info-box-icon bg-danger elevation-1"><i class="fas fa-shopping-cart"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text text-muted text-uppercase text-xs font-weight-bold">Utilized Budget</span>
                    <span class="info-box-number h4 font-weight-bold mb-0">Rs. <?= number_format($stats['spent_budget'], 2) ?></span>
                </div>
            </div>
        </div>
        <div class="col-lg-4 col-12">
            <div class="info-box shadow-sm border-left border-success">
                <span class="info-box-icon bg-success elevation-1"><i class="fas fa-hand-holding-usd"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text text-muted text-uppercase text-xs font-weight-bold">Remaining Budget</span>
                    <span class="info-box-number h4 font-weight-bold mb-0">Rs. <?= number_format($stats['remaining_budget'], 2) ?></span>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Recent Department Requests</h3>
                </div>
                <div class="card-body p-0">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Request #</th>
                                <th>Requested By</th>
                                <th>Status</th>
                                <th>Deadline</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($dept_requests as $req): ?>
                                <tr>
                                    <td><code><?= escape($req['request_no']) ?></code></td>
                                    <td><?= escape($req['requested_by_name']) ?></td>
                                    <td>
                                        <?php 
                                        $status_badge = [
                                            'pending' => 'warning',
                                            'approved' => 'success',
                                            'rejected' => 'danger'
                                        ][$req['status']] ?? 'secondary';
                                        ?>
                                        <span class="badge badge-<?= $status_badge ?>"><?= ucfirst($req['status']) ?></span>
                                    </td>
                                    <td><?= escape($req['deadline_date']) ?></td>
                                    <td>
                                        <a href="<?= BASE_URL ?>/dashboards/procurement/indent_requests.php?edit=<?= $req['id'] ?>" class="btn btn-sm btn-primary">Review</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        
        <div class="col-md-4">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Quick Actions</h3>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <a href="<?= BASE_URL ?>/dashboards/procurement/indent_requests.php" class="btn btn-primary">
                            <i class="fas fa-file-medical"></i> New Request
                        </a>
                        <a href="<?= BASE_URL ?>/dashboards/inventory/assets.php" class="btn btn-info">
                            <i class="fas fa-box"></i> View Assets
                        </a>
                        <a href="<?= BASE_URL ?>/dashboards/university/asset_allocation.php" class="btn btn-success">
                            <i class="fas fa-user-tag"></i> Track Assignments
                        </a>
                        <a href="<?= BASE_URL ?>/dashboards/reports/stock_summary.php" class="btn btn-warning">
                            <i class="fas fa-chart-bar"></i> Department Report
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
include __DIR__ . '/../includes/footer.php';
?>