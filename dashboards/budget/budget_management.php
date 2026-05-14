<?php
// dashboards/budget/budget_management.php - Budget & Financial Planning Module
require_once __DIR__ . '/../../includes/header.php';

$message = '';
$error = '';

// Handle budget operations
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_budget']) || isset($_POST['edit_budget'])) {
        $department_name = trim($_POST['department_name'] ?? '');
        $budget_year = (int)($_POST['budget_year'] ?? date('Y'));
        $allocated_amount = (float)($_POST['allocated_amount'] ?? 0);
        $status = trim($_POST['status'] ?? 'active');
        
        if (empty($department_name) || $allocated_amount <= 0) {
            $error = 'Department name and allocated amount are required.';
        } else {
            $pdo = getPDO();
            
            // Calculate remaining amount based on existing expenses and refunds
            $spent_stmt = $pdo->prepare("
                SELECT 
                    COALESCE(SUM(CASE WHEN bt.transaction_type = 'expense' THEN bt.amount ELSE 0 END), 0) -
                    COALESCE(SUM(CASE WHEN bt.transaction_type = 'refund' THEN bt.amount ELSE 0 END), 0) as spent
                FROM budget_transactions bt
                JOIN department_budgets db ON bt.budget_id = db.id
                WHERE db.department_name = ? AND db.budget_year = ? AND bt.status = 'approved'
            ");
            $spent_stmt->execute([$department_name, $budget_year]);
            $spent_amount = $spent_stmt->fetchColumn();
            
            $remaining_amount = $allocated_amount - $spent_amount;
            
            if (isset($_POST['edit_budget']) && !empty($_POST['budget_id'])) {
                // Check if editing causes a duplicate
                $check_stmt = $pdo->prepare("SELECT id FROM department_budgets WHERE department_name = ? AND budget_year = ? AND id != ?");
                $check_stmt->execute([$department_name, $budget_year, (int)$_POST['budget_id']]);
                
                if ($check_stmt->fetch()) {
                    $error = 'A budget already exists for this department and year.';
                } else {
                    // Update existing budget
                    $stmt = $pdo->prepare("UPDATE department_budgets SET department_name=?, budget_year=?, allocated_amount=?, remaining_amount=?, status=? WHERE id=?");
                    if ($stmt->execute([$department_name, $budget_year, $allocated_amount, $remaining_amount, $status, (int)$_POST['budget_id']])) {
                        $message = 'Budget updated successfully!';
                    } else {
                        $error = 'Error updating budget.';
                    }
                }
            } else {
                // Check if budget already exists for this department and year
                $check_stmt = $pdo->prepare("SELECT id FROM department_budgets WHERE department_name = ? AND budget_year = ?");
                $check_stmt->execute([$department_name, $budget_year]);
                if ($check_stmt->fetch()) {
                    $error = 'A budget already exists for this department and year.';
                } else {
                    // Insert new budget
                    $stmt = $pdo->prepare("INSERT INTO department_budgets (department_name, budget_year, allocated_amount, spent_amount, remaining_amount, status, created_by) VALUES (?, ?, ?, ?, ?, ?, ?)");
                    if ($stmt->execute([$department_name, $budget_year, $allocated_amount, $spent_amount, $remaining_amount, $status, currentUser()['id']])) {
                        $message = 'Budget created successfully!';
                    } else {
                        $error = 'Error creating budget.';
                    }
                }
            }
        }
    }
    
    // Handle budget transaction
    if (isset($_POST['add_transaction'])) {
        $budget_id = (int)($_POST['budget_id'] ?? 0);
        $transaction_type = trim($_POST['transaction_type'] ?? 'expense');
        $amount = (float)($_POST['amount'] ?? 0);
        $description = trim($_POST['description'] ?? '');
        $reference_no = trim($_POST['reference_no'] ?? '');
        $transaction_date = trim($_POST['transaction_date'] ?? date('Y-m-d'));
        $asset_id = !empty($_POST['asset_id']) ? (int)$_POST['asset_id'] : null;
        
        if ($budget_id <= 0 || $amount <= 0 || empty($description)) {
            $error = 'Budget, amount, and description are required.';
        } else {
            $pdo = getPDO();
            
            // Check if budget is active
            $budget_check = $pdo->prepare("SELECT allocated_amount, remaining_amount, status FROM department_budgets WHERE id = ?");
            $budget_check->execute([$budget_id]);
            $budget_info = $budget_check->fetch();
            
            if (!$budget_info || $budget_info['status'] !== 'active') {
                $error = 'Selected budget is not active.';
            } elseif ($transaction_type === 'expense' && $budget_info['remaining_amount'] < $amount) {
                $error = 'Insufficient budget remaining for this expense.';
            } else {
                // Insert transaction
                $stmt = $pdo->prepare("INSERT INTO budget_transactions (budget_id, transaction_type, amount, description, reference_no, transaction_date, asset_id, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                if ($stmt->execute([$budget_id, $transaction_type, $amount, $description, $reference_no, $transaction_date, $asset_id, currentUser()['id']])) {
                    $message = 'Transaction recorded successfully! It is now pending approval.';
                } else {
                    $error = 'Error recording transaction.';
                }
            }
        }
    }
    
    // Approve transaction
    if (isset($_POST['approve_transaction']) && !empty($_POST['transaction_id'])) {
        $pdo = getPDO();
        $transaction_id = (int)$_POST['transaction_id'];
        $current_user_id = currentUser()['id'];
        
        // Get transaction details
        $trans_stmt = $pdo->prepare("SELECT bt.*, db.remaining_amount, db.allocated_amount FROM budget_transactions bt JOIN department_budgets db ON bt.budget_id = db.id WHERE bt.id = ?");
        $trans_stmt->execute([$transaction_id]);
        $transaction = $trans_stmt->fetch();
        
        if ($transaction && $transaction['status'] === 'pending') {
            // Check if user is the HOD for this budget's department
            $auth_stmt = $pdo->prepare("SELECT 1 FROM department_budgets db JOIN university_departments ud ON db.department_name = ud.name WHERE db.id = ? AND ud.hod_id = ?");
            $auth_stmt->execute([$transaction['budget_id'], $current_user_id]);
            $is_authorized = ($user['role'] === 'super_admin' || $auth_stmt->fetch());

            if (!$is_authorized) {
                $error = 'Access Denied: You are not the authorized HOD for this department.';
            } elseif ($transaction['transaction_type'] === 'expense' && $transaction['amount'] > $transaction['remaining_amount']) {
                $error = 'Insufficient budget remaining for this expense.';
            } else {
                // Update transaction status
                $stmt = $pdo->prepare("UPDATE budget_transactions SET status='approved', approved_by=?, approved_date=NOW() WHERE id=?");
                if ($stmt->execute([$current_user_id, $transaction_id])) {
                    // Update budget amounts
                    if ($transaction['transaction_type'] === 'expense') {
                        $new_remaining = $transaction['remaining_amount'] - $transaction['amount'];
                        $new_spent = $transaction['allocated_amount'] - $new_remaining;
                        $update_budget = $pdo->prepare("UPDATE department_budgets SET remaining_amount=?, spent_amount=? WHERE id=?");
                        $update_budget->execute([$new_remaining, $new_spent, $transaction['budget_id']]);
                    } elseif ($transaction['transaction_type'] === 'refund') {
                        $new_remaining = $transaction['remaining_amount'] + $transaction['amount'];
                        $new_spent = $transaction['allocated_amount'] - $new_remaining;
                        $update_budget = $pdo->prepare("UPDATE department_budgets SET remaining_amount=?, spent_amount=? WHERE id=?");
                        $update_budget->execute([$new_remaining, $new_spent, $transaction['budget_id']]);
                    }
                    
                    $message = 'Transaction approved successfully!';
                } else {
                    $error = 'Error approving transaction.';
                }
            }
        } else {
            $error = 'Transaction cannot be approved.';
        }
    }
}

// Pagination and Search for budgets
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$search = trim($_GET['search'] ?? '');
$limit = 10;
$offset = ($page - 1) * $limit;

$pdo = getPDO();
$user = currentUser();
$where_clause = "WHERE 1=1";
$search = trim($_GET['search'] ?? '');
$params = [];

if ($user['role'] === 'hod') {
    $where_clause .= " AND department_name IN (SELECT name FROM university_departments WHERE hod_id = ?)";
    $params[] = $user['id'];
} elseif ($user['role'] === 'coordinator') {
    $where_clause .= " AND department_name IN (SELECT name FROM university_departments WHERE coordinator_id = ?)";
    $params[] = $user['id'];
}

if (!empty($search)) {
    $where_clause .= " AND (department_name LIKE ? OR budget_year LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

// Count total records
$count_sql = "SELECT COUNT(*) FROM department_budgets " . $where_clause;
$count_stmt = $pdo->prepare($count_sql);
$count_stmt->execute($params);
$total_records = $count_stmt->fetchColumn();
$total_pages = ceil($total_records / $limit);

// Fetch paginated records
$sql = "SELECT * FROM department_budgets " . $where_clause . " ORDER BY budget_year DESC, department_name ASC LIMIT ? OFFSET ?";
$params[] = $limit;
$params[] = $offset;

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$budgets = $stmt->fetchAll();

// Fetch all departments for dropdown - Filtered by department
$dept_where = " WHERE 1=1";
$dept_params = [];
if ($user['role'] === 'hod') {
    $dept_where .= " AND name IN (SELECT name FROM university_departments WHERE hod_id = ?)";
    $dept_params[] = $user['id'];
} elseif ($user['role'] === 'coordinator') {
    $dept_where .= " AND name IN (SELECT name FROM university_departments WHERE coordinator_id = ?)";
    $dept_params[] = $user['id'];
}

$dept_stmt = $pdo->prepare("SELECT name FROM university_departments $dept_where ORDER BY name ASC");
$dept_stmt->execute($dept_params);
$departments = $dept_stmt->fetchAll(PDO::FETCH_COLUMN);

// Fetch all assets for transaction dropdown
$assets_stmt = $pdo->query("SELECT id, asset_tag, name FROM assets ORDER BY asset_tag ASC");
$assets = $assets_stmt->fetchAll();

// Editing existing budget
$editing_budget = null;
if (isset($_GET['edit']) && !empty($_GET['edit'])) {
    $edit_id = (int)$_GET['edit'];
    $stmt = $pdo->prepare("SELECT * FROM department_budgets WHERE id = ?");
    $stmt->execute([$edit_id]);
    $editing_budget = $stmt->fetch();
}

// Fetch recent transactions - Filtered for relevance
$trans_where = "";
$trans_params = [];
if ($user['role'] === 'hod') {
    $trans_where = " WHERE db.department_name IN (SELECT name FROM university_departments WHERE hod_id = ?)";
    $trans_params[] = $user['id'];
} elseif ($user['role'] === 'coordinator') {
    $trans_where = " WHERE db.department_name IN (SELECT name FROM university_departments WHERE coordinator_id = ?)";
    $trans_params[] = $user['id'];
}

$transactions_stmt = $pdo->prepare("
    SELECT bt.*, db.department_name, db.budget_year, a.asset_tag, u.name as created_by_name
    FROM budget_transactions bt
    JOIN department_budgets db ON bt.budget_id = db.id
    LEFT JOIN assets a ON bt.asset_id = a.id
    LEFT JOIN users u ON bt.created_by = u.id
    $trans_where
    ORDER BY bt.created_at DESC
    LIMIT 10
");
$transactions_stmt->execute($trans_params);
$recent_transactions = $transactions_stmt->fetchAll();
?>

<div class="row">
    <div class="col-md-12">
        <?php if ($message): ?>
            <div class="alert alert-success alert-dismissible"><button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button><?= escape($message) ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible"><button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button><?= escape($error) ?></div>
        <?php endif; ?>
        

        
        <!-- Search & Action Bar -->
        <div class="card shadow-sm mb-4 border-0 nc-animate-in">
            <div class="card-body p-3">
                <div class="row g-3 align-items-center">
                    <div class="col-md-auto me-auto">
                        <h3 class="h5 font-weight-bold m-0 text-dark">
                            <i class="fas fa-wallet text-primary me-2"></i> Departmental Budgets
                        </h3>
                    </div>
                    <div class="col-md-auto">
                        <form method="GET" class="d-flex">
                            <div class="input-group input-group-sm">
                                <span class="input-group-text bg-white border-end-0"><i class="fas fa-search text-muted"></i></span>
                                <input type="text" class="form-control border-start-0 ps-0" name="search" placeholder="Search Dept/Year..." value="<?= escape($search) ?>" style="min-width: 200px;">
                                <button type="submit" class="btn btn-primary px-3">Filter</button>
                            </div>
                        </form>
                    </div>
                    <div class="col-md-auto d-flex gap-2">
                        <button type="button" class="btn btn-sm btn-info shadow-sm text-white px-3" data-bs-toggle="modal" data-bs-target="#transactionModal">
                            <i class="fas fa-exchange-alt me-1"></i> Transaction
                        </button>
                        <button type="button" class="btn btn-sm btn-success shadow-sm px-3" data-bs-toggle="modal" data-bs-target="#budgetModal">
                            <i class="fas fa-plus-circle me-1"></i> New Budget
                        </button>
                    </div>
                </div>
            </div>
        </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Department</th>
                                <th>Year</th>
                                <th>Allocated</th>
                                <th>Spent</th>
                                <th>Remaining</th>
                                <th>Utilization</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($budgets as $budget): 
                                $utilization_percent = $budget['allocated_amount'] > 0 ? ($budget['spent_amount'] / $budget['allocated_amount']) * 100 : 0;
                                $utilization_color = $utilization_percent >= 90 ? 'bg-danger' : ($utilization_percent >= 75 ? 'bg-warning' : 'bg-success');
                            ?>
                                <tr>
                                    <td><strong><?= escape($budget['department_name']) ?></strong></td>
                                    <td><?= $budget['budget_year'] ?></td>
                                    <td>Rs. <?= number_format($budget['allocated_amount'], 2) ?></td>
                                    <td>Rs. <?= number_format($budget['spent_amount'], 2) ?></td>
                                    <td>Rs. <?= number_format($budget['remaining_amount'], 2) ?></td>
                                    <td>
                                        <div class="progress progress-xs">
                                            <div class="progress-bar <?= $utilization_color ?>" style="width: <?= number_format($utilization_percent, 1) ?>%"></div>
                                        </div>
                                        <span class="small"><?= number_format($utilization_percent, 1) ?>%</span>
                                    </td>
                                    <td>
                                        <?php 
                                        $status_badge = [
                                            'active' => 'success',
                                            'frozen' => 'warning',
                                            'closed' => 'danger'
                                        ][$budget['status']] ?? 'secondary';
                                        ?>
                                        <span class="badge badge-<?= $status_badge ?>"><?= ucfirst($budget['status']) ?></span>
                                    </td>
                                    <td>
                                        <a href="?edit=<?= $budget['id'] ?>" class="btn btn-sm btn-primary">Edit</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Pagination -->
                <div class="card-footer clearfix">
                    <ul class="pagination pagination-sm m-0 float-right">
                        <?php if ($page > 1): ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=<?= $page - 1 ?>&search=<?= urlencode($search) ?>">«</a>
                            </li>
                        <?php endif; ?>
                        
                        <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                            <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                                <a class="page-link" href="?page=<?= $i ?>&search=<?= urlencode($search) ?>"><?= $i ?></a>
                            </li>
                        <?php endfor; ?>
                        
                        <?php if ($page < $total_pages): ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=<?= $page + 1 ?>&search=<?= urlencode($search) ?>">»</a>
                            </li>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>
        </div>
        
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Recent Transactions</h3>
            </div>
            
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Budget</th>
                                <th>Type</th>
                                <th>Amount</th>
                                <th>Asset</th>
                                <th>Description</th>
                                <th>Date</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_transactions as $trans): ?>
                                <tr>
                                    <td>
                                        <small><?= escape($trans['department_name']) ?> (<?= $trans['budget_year'] ?>)</small>
                                    </td>
                                    <td>
                                        <span class="badge badge-<?= $trans['transaction_type'] === 'expense' ? 'danger' : ($trans['transaction_type'] === 'refund' ? 'success' : 'info') ?>">
                                            <?= ucfirst($trans['transaction_type']) ?>
                                        </span>
                                    </td>
                                    <td>Rs. <?= number_format($trans['amount'], 2) ?></td>
                                    <td><?= $trans['asset_tag'] ? escape($trans['asset_tag']) : 'N/A' ?></td>
                                    <td><?= escape($trans['description']) ?></td>
                                    <td><?= escape(date('M j, Y', strtotime($trans['created_at']))) ?></td>
                                    <td>
                                        <?php 
                                        $status_badge = [
                                            'pending' => 'warning',
                                            'approved' => 'success',
                                            'rejected' => 'danger'
                                        ][$trans['status']] ?? 'secondary';
                                        ?>
                                        <span class="badge badge-<?= $status_badge ?>"><?= ucfirst($trans['status']) ?></span>
                                    </td>
                                    <td>
                                        <?php if ($trans['status'] === 'pending'): ?>
                                            <form method="post" style="display: inline;">
                                                <input type="hidden" name="transaction_id" value="<?= $trans['id'] ?>">
                                                <button type="submit" name="approve_transaction" class="btn btn-sm btn-success">Approve</button>
                                            </form>
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

<!-- BUDGET MASTER MODAL -->
<div class="modal fade" id="budgetModal" tabindex="-1" aria-labelledby="budgetModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-primary text-white py-3">
                <h5 class="modal-title font-weight-bold" id="budgetModalLabel">
                    <i class="fas fa-<?= $editing_budget ? 'edit' : 'plus-circle' ?> me-2"></i>
                    <?= $editing_budget ? 'Update Budget Allocation' : 'Create New Master Budget' ?>
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="post">
                <div class="modal-body p-4">
                    <div class="row g-4">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="department_name" class="form-label">Department *</label>
                                <select class="form-select" id="department_name" name="department_name" required>
                                    <option value="">— Select Department —</option>
                                    <?php foreach ($departments as $dept): ?>
                                        <option value="<?= escape($dept) ?>" 
                                                <?= $editing_budget && $editing_budget['department_name'] == $dept ? 'selected' : '' ?>>
                                            <?= escape($dept) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="mb-3">
                                <label for="budget_year" class="form-label">Fiscal Year *</label>
                                <select class="form-select" id="budget_year" name="budget_year" required>
                                    <?php 
                                    $current_year = date('Y');
                                    for ($year = $current_year; $year <= $current_year + 1; $year++): ?>
                                        <option value="<?= $year ?>" 
                                                <?= ($editing_budget && $editing_budget['budget_year'] == $year) || (!$editing_budget && $year == $current_year) ? 'selected' : '' ?>>
                                            <?= $year ?>
                                        </option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="allocated_amount" class="form-label">Total Allocated Fund (Rs.) *</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light border-end-0">Rs.</span>
                                    <input type="number" step="0.01" class="form-control" id="allocated_amount" name="allocated_amount" 
                                           value="<?= $editing_budget ? escape($editing_budget['allocated_amount']) : '' ?>" required placeholder="0.00">
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="status" class="form-label">Budget Status</label>
                                <select class="form-select" id="status" name="status">
                                    <option value="active" <?= $editing_budget && $editing_budget['status'] == 'active' ? 'selected' : '' ?>>Active (Enabled)</option>
                                    <option value="frozen" <?= $editing_budget && $editing_budget['status'] == 'frozen' ? 'selected' : '' ?>>Frozen (Read-Only)</option>
                                    <option value="closed" <?= $editing_budget && $editing_budget['status'] == 'closed' ? 'selected' : '' ?>>Closed (Completed)</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light border-0 py-3">
                    <button type="button" class="btn btn-outline-secondary px-4" data-bs-dismiss="modal">Close</button>
                    <?php if ($editing_budget): ?>
                        <input type="hidden" name="budget_id" value="<?= $editing_budget['id'] ?>">
                        <a href="budget_management.php" class="btn btn-dark px-4 ms-2">Cancel</a>
                    <?php endif; ?>
                    <button type="submit" name="<?= $editing_budget ? 'edit_budget' : 'add_budget' ?>" class="btn btn-success px-5 ms-2 shadow-sm">
                        <i class="fas fa-check-circle me-1"></i> <?= $editing_budget ? 'Update Allocation' : 'Initialize Budget' ?>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- TRANSACTION RECORDING MODAL -->
<div class="modal fade" id="transactionModal" tabindex="-1" aria-labelledby="transactionModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-info text-white py-3">
                <h5 class="modal-title font-weight-bold" id="transactionModalLabel">
                    <i class="fas fa-exchange-alt me-2"></i> Record Transaction
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="post">
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label for="budget_id" class="form-label">Source Budget *</label>
                        <select class="form-select" id="budget_id" name="budget_id" required>
                            <option value="">— Select Active Budget —</option>
                            <?php foreach ($budgets as $budget): ?>
                                <?php if ($budget['status'] === 'active'): ?>
                                    <option value="<?= $budget['id'] ?>">
                                        <?= escape($budget['department_name']) ?> (<?= $budget['budget_year'] ?>) - Available: Rs. <?= number_format($budget['remaining_amount'], 2) ?>
                                    </option>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="row g-3">
                        <div class="col-6">
                            <div class="mb-3">
                                <label for="transaction_type" class="form-label">Entry Type *</label>
                                <select class="form-select" id="transaction_type" name="transaction_type" required>
                                    <option value="expense">Expense (-)</option>
                                    <option value="refund">Refund (+)</option>
                                    <option value="adjustment">Internal Adjustment</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="mb-3">
                                <label for="amount" class="form-label">Amount *</label>
                                <input type="number" step="0.01" class="form-control" id="amount" name="amount" required placeholder="0.00">
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="asset_id" class="form-label">Linked Asset (Optional)</label>
                        <select class="form-select" id="asset_id" name="asset_id">
                            <option value="">None / Not Applicable</option>
                            <?php foreach ($assets as $asset): ?>
                                <option value="<?= $asset['id'] ?>">
                                    [<?= escape($asset['asset_tag']) ?>] <?= escape($asset['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="transaction_date" class="form-label">Posting Date *</label>
                        <input type="date" class="form-control" id="transaction_date" name="transaction_date" 
                               value="<?= date('Y-m-d') ?>" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="description" class="form-label">Memo / Description *</label>
                        <textarea class="form-control" id="description" name="description" rows="2" required placeholder="Details of the expenditure or refund..."></textarea>
                    </div>
                </div>
                <div class="modal-footer bg-light border-0 py-3">
                    <button type="button" class="btn btn-outline-secondary px-4" data-bs-dismiss="modal">Close</button>
                    <button type="submit" name="add_transaction" class="btn btn-info px-4 text-white shadow-sm">
                        <i class="fas fa-check-double me-1"></i> Post Transaction
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", function() {
    // Helper to ensure bootstrap is available before calling Modal
    const showModal = (modalId) => {
        const modalEl = document.getElementById(modalId);
        if (modalEl && typeof bootstrap !== 'undefined') {
            const modal = new bootstrap.Modal(modalEl);
            modal.show();
        }
    };

    // Auto-open master budget modal for editing or general errors
    <?php if ($editing_budget || ($error && !isset($_POST['add_transaction']))): ?>
        showModal('budgetModal');
    <?php endif; ?>

    // Auto-open transaction modal if a transaction error occurs
    <?php if ($error && isset($_POST['add_transaction'])): ?>
        showModal('transactionModal');
    <?php endif; ?>
});
</script>

<?php
include __DIR__ . '/../../includes/footer.php';
?>