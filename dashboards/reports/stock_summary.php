<?php
// dashboards/reports/stock_summary.php
require_once __DIR__ . '/../../includes/header.php';

// Fetch stock summary data
$pdo = getPDO();
$user = currentUser();
$where = " WHERE 1=1";
$params = [];

if ($user['role'] === 'hod') {
    $where .= " AND department IN (SELECT name FROM university_departments WHERE hod_id = ?)";
    $params[] = $user['id'];
} elseif ($user['role'] === 'coordinator') {
    $where .= " AND department IN (SELECT name FROM university_departments WHERE coordinator_id = ?)";
    $params[] = $user['id'];
}

// Additional search/filters
$filter_dept = trim($_GET['filter_dept'] ?? '');
$filter_cat = trim($_GET['filter_cat'] ?? '');

if (!empty($filter_dept)) {
    $where .= " AND department = ?";
    $params[] = $filter_dept;
}
if (!empty($filter_cat)) {
    $where .= " AND category_id = ?";
    $params[] = (int)$filter_cat;
}

// Fetch lists for filters
$all_depts = $pdo->query("SELECT name FROM university_departments ORDER BY name ASC")->fetchAll(PDO::FETCH_COLUMN);
$all_cats = $pdo->query("SELECT id, name FROM asset_categories ORDER BY name ASC")->fetchAll();

// Total assets by category
$category_stmt = $pdo->prepare("
    SELECT ac.name as category_name, 
           COUNT(a.id) as total_assets, 
           SUM(a.current_value) as total_value
    FROM asset_categories ac
    LEFT JOIN assets a ON ac.id = a.category_id 
    " . str_replace(" AND department ", " AND a.department ", $where) . "
    GROUP BY ac.id, ac.name
    ORDER BY ac.name
");
$category_stmt->execute($params);
$category_summary = $category_stmt->fetchAll();

// Assets by status
$status_stmt = $pdo->prepare("
    SELECT status, COUNT(*) as count
    FROM assets
    $where
    GROUP BY status
    ORDER BY status
");
$status_stmt->execute($params);
$status_summary = $status_stmt->fetchAll();

// Assets by department
$dept_stmt = $pdo->prepare("
    SELECT department, COUNT(*) as count, SUM(current_value) as total_value
    FROM assets
    $where
    GROUP BY department
    ORDER BY department
");
$dept_stmt->execute($params);
$dept_summary = $dept_stmt->fetchAll();

// Overall totals
$total_assets_stmt = $pdo->prepare("SELECT COUNT(*) as total, SUM(current_value) as total_value FROM assets $where");
$total_assets_stmt->execute($params);
$overall_totals = $total_assets_stmt->fetch();
?>

<div class="row">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Stock Summary Report</h3>
                <div class="card-tools">
                    <button type="button" class="btn btn-tool" data-card-widget="collapse">
                        <i class="fas fa-minus"></i>
                    </button>
                    <button type="button" class="btn btn-tool" onclick="window.print()">
                        <i class="fas fa-print"></i>
                    </button>
                </div>
            </div>
            
            <div class="card-body">
                <!-- Filters -->
                <form method="GET" class="row g-3 mb-5 no-print align-items-end nc-animate-in">
                    <div class="col-auto">
                        <label for="filter_dept" class="form-label">Department</label>
                        <select name="filter_dept" id="filter_dept" class="form-select form-select-sm" style="min-width: 200px;">
                            <option value="">All Departments</option>
                            <?php foreach ($all_depts as $dept): ?>
                                <option value="<?= escape($dept) ?>" <?= $filter_dept === $dept ? 'selected' : '' ?>><?= escape($dept) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-auto">
                        <label for="filter_cat" class="form-label">Category</label>
                        <select name="filter_cat" id="filter_cat" class="form-select form-select-sm" style="min-width: 180px;">
                            <option value="">All Categories</option>
                            <?php foreach ($all_cats as $cat): ?>
                                <option value="<?= $cat['id'] ?>" <?= $filter_cat == $cat['id'] ? 'selected' : '' ?>><?= escape($cat['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-auto">
                        <button type="submit" class="btn btn-sm btn-primary px-4">
                            <i class="fas fa-filter me-2"></i> Filter
                        </button>
                        <?php if ($filter_dept || $filter_cat): ?>
                            <a href="stock_summary.php" class="btn btn-sm btn-outline-secondary ms-2">
                                <i class="fas fa-times me-1"></i> Clear
                            </a>
                        <?php endif; ?>
                    </div>
                </form>
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="info-box">
                            <span class="info-box-icon bg-info elevation-1"><i class="fas fa-box"></i></span>
                            <div class="info-box-content">
                                <span class="info-box-text">Total Assets</span>
                                <span class="info-box-number"><?= $overall_totals['total'] ?></span>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="info-box">
                            <span class="info-box-icon bg-success elevation-1"><i class="fas fa-dollar-sign"></i></span>
                            <div class="info-box-content">
                                <span class="info-box-text">Total Value</span>
                                <span class="info-box-number">Rs. <?= number_format($overall_totals['total_value'], 2) ?></span>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="info-box">
                            <span class="info-box-icon bg-warning elevation-1"><i class="fas fa-exclamation-triangle"></i></span>
                            <div class="info-box-content">
                                <span class="info-box-text">In Repair</span>
                                <span class="info-box-number">
                                    <?php 
                                    $repair_count = 0;
                                    foreach ($status_summary as $status) {
                                        if ($status['status'] == 'in_repair') {
                                            $repair_count = $status['count'];
                                            break;
                                        }
                                    }
                                    echo $repair_count;
                                    ?>
                                </span>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="info-box">
                            <span class="info-box-icon bg-danger elevation-1"><i class="fas fa-times"></i></span>
                            <div class="info-box-content">
                                <span class="info-box-text">Dead Assets</span>
                                <span class="info-box-number">
                                    <?php 
                                    $dead_count = 0;
                                    foreach ($status_summary as $status) {
                                        if ($status['status'] == 'dead') {
                                            $dead_count = $status['count'];
                                            break;
                                        }
                                    }
                                    echo $dead_count;
                                    ?>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h3 class="card-title">By Category</h3>
                            </div>
                            <div class="card-body p-0">
                                <table class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th>Category</th>
                                            <th>Count</th>
                                            <th>Total Value</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($category_summary as $cat): ?>
                                            <tr>
                                                <td><?= escape($cat['category_name']) ?></td>
                                                <td><?= $cat['total_assets'] ?? 0 ?></td>
                                                <td>Rs. <?= number_format($cat['total_value'] ?? 0, 2) ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h3 class="card-title">By Department</h3>
                            </div>
                            <div class="card-body p-0">
                                <table class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th>Department</th>
                                            <th>Count</th>
                                            <th>Total Value</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($dept_summary as $dept): ?>
                                            <tr>
                                                <td><?= escape($dept['department']) ?></td>
                                                <td><?= $dept['count'] ?></td>
                                                <td>Rs. <?= number_format($dept['total_value'], 2) ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="row mt-4">
                    <div class="col-md-12">
                        <div class="card">
                            <div class="card-header">
                                <h3 class="card-title">By Status</h3>
                            </div>
                            <div class="card-body">
                                <canvas id="statusChart" width="400" height="200"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Chart.js for the status chart -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Prepare data for the chart
    var statusLabels = [];
    var statusData = [];
    var statusColors = [];
    
    <?php foreach ($status_summary as $status): ?>
        statusLabels.push('<?= ucfirst(str_replace('_', ' ', $status['status'])) ?>');
        statusData.push(<?= $status['count'] ?>);
        
        var colorMap = {
            'In Stock': '#10b981',
            'Allocated': '#3b82f6',
            'In Repair': '#f59e0b',
            'Dead': '#ef4444',
            'Reserved': '#6366f1',
            'In Use': '#06b6d4'
        };
        
        var statusDisplay = '<?= ucfirst(str_replace('_', ' ', $status['status'])) ?>';
        var color = colorMap[statusDisplay] || '#6c757d';
        statusColors.push(color);
    <?php endforeach; ?>
    
    // Create the chart
    var ctx = document.getElementById('statusChart').getContext('2d');
    var statusChart = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: statusLabels,
            datasets: [{
                label: 'Asset Count by Status',
                data: statusData,
                backgroundColor: statusColors,
                borderColor: statusColors.map(c => c.replace('0.2', '1')),
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        precision: 0
                    }
                }
            }
        }
    });
});
</script>

<?php
include __DIR__ . '/../../includes/footer.php';
?>