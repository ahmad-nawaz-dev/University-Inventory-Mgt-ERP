<?php
// dashboards/reports/depreciation_report.php
require_once __DIR__ . '/../../includes/header.php';

// Fetch depreciation data
$pdo = getPDO();
$user = currentUser();
$where = " WHERE a.purchase_date IS NOT NULL";
$params = [];

if ($user['role'] === 'hod') {
    $where .= " AND a.department IN (SELECT name FROM university_departments WHERE hod_id = ?)";
    $params[] = $user['id'];
} elseif ($user['role'] === 'coordinator') {
    $where .= " AND a.department IN (SELECT name FROM university_departments WHERE coordinator_id = ?)";
    $params[] = $user['id'];
}

// Additional search/filters
$filter_dept = trim($_GET['filter_dept'] ?? '');
$filter_cat = trim($_GET['filter_cat'] ?? '');

if (!empty($filter_dept)) {
    $where .= " AND a.department = ?";
    $params[] = $filter_dept;
}
if (!empty($filter_cat)) {
    $where .= " AND a.category_id = ?";
    $params[] = (int)$filter_cat;
}

// Fetch lists for filters
$all_depts = $pdo->query("SELECT name FROM university_departments ORDER BY name ASC")->fetchAll(PDO::FETCH_COLUMN);
$all_cats = $pdo->query("SELECT id, name FROM asset_categories ORDER BY name ASC")->fetchAll();

// Get all assets with depreciation calculations
$assets_stmt = $pdo->prepare("
    SELECT a.*, ac.name as category_name,
           DATEDIFF(NOW(), a.purchase_date) / 365.25 as years_since_purchase,
           CASE 
               WHEN a.useful_life_years > 0 THEN (a.purchase_cost - a.salvage_value) / a.useful_life_years
               ELSE 0
           END as annual_depreciation,
           CASE 
               WHEN a.useful_life_years > 0 THEN 
                   LEAST(
                       (a.purchase_cost - a.salvage_value) / a.useful_life_years * GREATEST(DATEDIFF(NOW(), a.purchase_date) / 365.25, 0),
                       a.purchase_cost - a.salvage_value
                   )
               ELSE 0
           END as accumulated_depreciation
    FROM assets a
    LEFT JOIN asset_categories ac ON a.category_id = ac.id
    $where
    ORDER BY a.purchase_date ASC
");
$assets_stmt->execute($params);
$assets = $assets_stmt->fetchAll();

// Calculate overall totals
$total_purchase_value = 0;
$total_accumulated_depreciation = 0;
$total_net_book_value = 0;

foreach ($assets as $asset) {
    $total_purchase_value += $asset['purchase_cost'];
    $total_accumulated_depreciation += $asset['accumulated_depreciation'];
    $net_book_value = $asset['purchase_cost'] - $asset['accumulated_depreciation'];
    $total_net_book_value += $net_book_value;
}

// Get depreciation by category
$category_depreciation_stmt = $pdo->prepare("
    SELECT ac.name as category_name,
           COUNT(a.id) as asset_count,
           SUM(a.purchase_cost) as total_purchase_value,
           SUM(
               CASE 
                   WHEN a.useful_life_years > 0 THEN 
                       LEAST(
                           (a.purchase_cost - a.salvage_value) / a.useful_life_years * GREATEST(DATEDIFF(NOW(), a.purchase_date) / 365.25, 0),
                           a.purchase_cost - a.salvage_value
                       )
                   ELSE 0
               END
           ) as total_accumulated_depreciation
    FROM assets a
    LEFT JOIN asset_categories ac ON a.category_id = ac.id
    $where
    GROUP BY ac.id, ac.name
    ORDER BY ac.name
");
$category_depreciation_stmt->execute($params);
$category_depreciation = $category_depreciation_stmt->fetchAll();
?>

<div class="row">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Depreciation Report</h3>
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
                <form method="GET" class="row g-2 align-items-end mb-4 no-print nc-animate-in">
                    <div class="col-md-4">
                        <label for="filter_dept" class="form-label small">Department</label>
                        <select name="filter_dept" id="filter_dept" class="form-select form-select-sm">
                            <option value="">All Departments</option>
                            <?php foreach ($all_depts as $dept): ?>
                                <option value="<?= escape($dept) ?>" <?= $filter_dept === $dept ? 'selected' : '' ?>><?= escape($dept) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label for="filter_cat" class="form-label small">Category</label>
                        <select name="filter_cat" id="filter_cat" class="form-select form-select-sm">
                            <option value="">All Categories</option>
                            <?php foreach ($all_cats as $cat): ?>
                                <option value="<?= $cat['id'] ?>" <?= $filter_cat == $cat['id'] ? 'selected' : '' ?>><?= escape($cat['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <button type="submit" class="btn btn-sm btn-primary px-3 shadow-sm">Filter Report</button>
                        <?php if ($filter_dept || $filter_cat): ?>
                            <a href="depreciation_report.php" class="btn btn-sm btn-outline-secondary ml-1 px-3">Clear</a>
                        <?php endif; ?>
                    </div>
                </form>
                <div class="row mb-4">
                    <div class="col-md-4">
                        <div class="info-box">
                            <span class="info-box-icon bg-info elevation-1"><i class="fas fa-dollar-sign"></i></span>
                            <div class="info-box-content">
                                <span class="info-box-text">Total Purchase Value</span>
                                <span class="info-box-number">Rs. <?= number_format($total_purchase_value, 2) ?></span>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="info-box">
                            <span class="info-box-icon bg-warning elevation-1"><i class="fas fa-calculator"></i></span>
                            <div class="info-box-content">
                                <span class="info-box-text">Accumulated Depreciation</span>
                                <span class="info-box-number">Rs. <?= number_format($total_accumulated_depreciation, 2) ?></span>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="info-box">
                            <span class="info-box-icon bg-success elevation-1"><i class="fas fa-chart-line"></i></span>
                            <div class="info-box-content">
                                <span class="info-box-text">Net Book Value</span>
                                <span class="info-box-number">Rs. <?= number_format($total_net_book_value, 2) ?></span>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-12">
                        <div class="card">
                            <div class="card-header">
                                <h3 class="card-title">Asset Depreciation Details</h3>
                            </div>
                            <div class="card-body p-0">
                                <div class="table-responsive">
                                    <table class="table table-striped">
                                        <thead>
                                            <tr>
                                                <th>Asset Tag</th>
                                                <th>Name</th>
                                                <th>Category</th>
                                                <th>Purchase Date</th>
                                                <th>Cost</th>
                                                <th>Years</th>
                                                <th>Annual Depr.</th>
                                                <th>Accum. Depr.</th>
                                                <th>Net Book Value</th>
                                                <th>Life Left</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($assets as $asset): 
                                                $years_since_purchase = $asset['years_since_purchase'];
                                                $annual_depreciation = $asset['annual_depreciation'];
                                                $accumulated_depreciation = $asset['accumulated_depreciation'];
                                                $net_book_value = $asset['purchase_cost'] - $accumulated_depreciation;
                                                $years_left = max(0, $asset['useful_life_years'] - $years_since_purchase);
                                            ?>
                                                <tr>
                                                    <td><code><?= escape($asset['asset_tag']) ?></code></td>
                                                    <td><?= escape($asset['name']) ?></td>
                                                    <td><?= escape($asset['category_name']) ?></td>
                                                    <td><?= escape($asset['purchase_date']) ?></td>
                                                    <td>Rs. <?= number_format($asset['purchase_cost'], 2) ?></td>
                                                    <td><?= number_format($years_since_purchase, 2) ?> yrs</td>
                                                    <td>Rs. <?= number_format($annual_depreciation, 2) ?></td>
                                                    <td>Rs. <?= number_format($accumulated_depreciation, 2) ?></td>
                                                    <td>Rs. <?= number_format($net_book_value, 2) ?></td>
                                                    <td>
                                                        <?php if ($years_left <= 0): ?>
                                                            <span class="badge badge-danger">Expired</span>
                                                        <?php else: ?>
                                                            <span class="badge badge-success"><?= number_format($years_left, 1) ?> yrs</span>
                                                        <?php endif; ?>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="row mt-4">
                    <div class="col-md-12">
                        <div class="card">
                            <div class="card-header">
                                <h3 class="card-title">Depreciation by Category</h3>
                            </div>
                            <div class="card-body p-0">
                                <table class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th>Category</th>
                                            <th>Asset Count</th>
                                            <th>Total Purchase Value</th>
                                            <th>Total Accumulated Depreciation</th>
                                            <th>Net Book Value</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($category_depreciation as $cat): 
                                            $net_book_value = $cat['total_purchase_value'] - $cat['total_accumulated_depreciation'];
                                        ?>
                                            <tr>
                                                <td><?= escape($cat['category_name']) ?></td>
                                                <td><?= $cat['asset_count'] ?></td>
                                                <td>Rs. <?= number_format($cat['total_purchase_value'], 2) ?></td>
                                                <td>Rs. <?= number_format($cat['total_accumulated_depreciation'], 2) ?></td>
                                                <td>Rs. <?= number_format($net_book_value, 2) ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
include __DIR__ . '/../../includes/footer.php';
?>