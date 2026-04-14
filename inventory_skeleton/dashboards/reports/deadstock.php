<?php
// dashboards/reports/deadstock.php - Fixed version
require_once __DIR__ . '/../../includes/header.php';

// Fetch dead stock data
$pdo = getPDO();
$user = currentUser();
$where = " WHERE a.status IN ('dead', 'disposed')";
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
    $params[] = (int) $filter_cat;
}

// Fetch lists for filters
$all_depts = $pdo->query("SELECT name FROM university_departments ORDER BY name ASC")->fetchAll(PDO::FETCH_COLUMN);
$all_cats = $pdo->query("SELECT id, name FROM asset_categories ORDER BY name ASC")->fetchAll();

// Get all dead assets
$dead_assets_stmt = $pdo->prepare("
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
    ORDER BY a.updated_at DESC
");
$dead_assets_stmt->execute($params);
$dead_assets = $dead_assets_stmt->fetchAll();

// Get dead stock by category - FIXED VERSION
$category_dead_stock_stmt = $pdo->prepare("
    SELECT ac.name as category_name,
           COUNT(a.id) as dead_count,
           SUM(a.purchase_cost) as total_original_value,
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
$category_dead_stock_stmt->execute($params);
$category_dead_stock = $category_dead_stock_stmt->fetchAll();

// Calculate totals
$total_dead_count = count($dead_assets);
$total_original_value = 0;
$total_accumulated_depreciation = 0;

foreach ($dead_assets as $asset) {
    $total_original_value += $asset['purchase_cost'];
    $total_accumulated_depreciation += $asset['accumulated_depreciation'];
}
?>

<div class="row">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Dead Stock Report</h3>
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
                                <option value="<?= escape($dept) ?>" <?= $filter_dept === $dept ? 'selected' : '' ?>>
                                    <?= escape($dept) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label for="filter_cat" class="form-label small">Category</label>
                        <select name="filter_cat" id="filter_cat" class="form-select form-select-sm">
                            <option value="">All Categories</option>
                            <?php foreach ($all_cats as $cat): ?>
                                <option value="<?= $cat['id'] ?>" <?= $filter_cat == $cat['id'] ? 'selected' : '' ?>>
                                    <?= escape($cat['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <button type="submit" class="btn btn-sm btn-primary px-3 shadow-sm">Filter Report</button>
                        <?php if ($filter_dept || $filter_cat): ?>
                            <a href="deadstock.php" class="btn btn-sm btn-outline-secondary ml-1 px-3">Clear</a>
                        <?php endif; ?>
                    </div>
                </form>
                <div class="row mb-4">
                    <div class="col-md-4">
                        <div class="info-box">
                            <span class="info-box-icon bg-danger elevation-1"><i class="fas fa-times-circle"></i></span>
                            <div class="info-box-content">
                                <span class="info-box-text">Total Dead Assets</span>
                                <span class="info-box-number"><?= $total_dead_count ?></span>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="info-box">
                            <span class="info-box-icon bg-info elevation-1"><i class="fas fa-dollar-sign"></i></span>
                            <div class="info-box-content">
                                <span class="info-box-text">Original Value Lost</span>
                                <span class="info-box-number">Rs. <?= number_format($total_original_value, 2) ?></span>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="info-box">
                            <span class="info-box-icon bg-warning elevation-1"><i class="fas fa-calculator"></i></span>
                            <div class="info-box-content">
                                <span class="info-box-text">Accumulated Depreciation</span>
                                <span class="info-box-number">Rs.
                                    <?= number_format($total_accumulated_depreciation, 2) ?></span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-12">
                        <div class="card">
                            <div class="card-header">
                                <h3 class="card-title">Dead Assets List</h3>
                            </div>
                            <div class="card-body p-0">
                                <div class="table-responsive">
                                    <table class="table table-striped">
                                        <thead>
                                            <tr>
                                                <th>Asset Tag</th>
                                                <th>Name</th>
                                                <th>Category</th>
                                                <th>Department</th>
                                                <th>Purchase Date</th>
                                                <th>Original Cost</th>
                                                <th>Years in Service</th>
                                                <th>Accum. Depr.</th>
                                                <th>Status Date</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($dead_assets as $asset):
                                                $years_in_service = $asset['years_since_purchase'];
                                                $accumulated_depreciation = $asset['accumulated_depreciation'];
                                                ?>
                                                <tr>
                                                    <td><code><?= escape($asset['asset_tag']) ?></code></td>
                                                    <td><?= escape($asset['name']) ?></td>
                                                    <td><?= escape($asset['category_name']) ?></td>
                                                    <td><?= escape($asset['department']) ?></td>
                                                    <td><?= escape($asset['purchase_date']) ?></td>
                                                    <td>Rs. <?= number_format($asset['purchase_cost'], 2) ?></td>
                                                    <td><?= number_format($years_in_service, 2) ?> yrs</td>
                                                    <td>Rs. <?= number_format($accumulated_depreciation, 2) ?></td>
                                                    <td><?= escape(date('M j, Y', strtotime($asset['updated_at']))) ?></td>
                                                </tr>
                                            <?php endforeach; ?>

                                            <?php if (empty($dead_assets)): ?>
                                                <tr>
                                                    <td colspan="9" class="text-center text-muted">No dead assets found</td>
                                                </tr>
                                            <?php endif; ?>
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
                                <h3 class="card-title">Dead Stock by Category</h3>
                            </div>
                            <div class="card-body p-0">
                                <table class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th>Category</th>
                                            <th>Dead Count</th>
                                            <th>Original Value</th>
                                            <th>Accumulated Depreciation</th>
                                            <th>Avg. Years in Service</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($category_dead_stock as $cat):
                                            // Calculate average years in service for this category - FIXED VERSION
                                            $avg_years_stmt = $pdo->prepare("
                                                SELECT AVG(DATEDIFF(NOW(), purchase_date) / 365.25) as avg_years
                                                FROM assets a
                                                LEFT JOIN asset_categories ac ON a.category_id = ac.id
                                                $where AND ac.name = ?
                                            ");
                                            $avg_years_stmt->execute(array_merge($params, [$cat['category_name']]));
                                            $avg_years_result = $avg_years_stmt->fetch();
                                            $avg_years = $avg_years_result['avg_years'] ?? 0;
                                            ?>
                                            <tr>
                                                <td><?= escape($cat['category_name']) ?></td>
                                                <td><?= $cat['dead_count'] ?></td>
                                                <td>Rs. <?= number_format($cat['total_original_value'], 2) ?></td>
                                                <td>Rs. <?= number_format($cat['total_accumulated_depreciation'], 2) ?></td>
                                                <td><?= number_format($avg_years, 2) ?> yrs</td>
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
                        <div class="callout callout-warning">
                            <h5><i class="fas fa-exclamation-triangle"></i> Dead Stock Analysis</h5>
                            <p>Dead stock represents assets that are no longer functional or usable. Consider reviewing
                                procurement patterns to minimize future losses.</p>
                            <p><strong>Recommendation:</strong> Review the average years in service for each category to
                                identify potential issues with asset quality or maintenance procedures.</p>
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