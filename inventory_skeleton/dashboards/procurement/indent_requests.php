<?php
// dashboards/procurement/indent_requests.php - Enhanced with deadline
require_once __DIR__ . '/../../includes/header.php';

$message = '';
$error = '';

// Add or edit purchase request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_request']) || isset($_POST['edit_request'])) {
        $request_no = trim($_POST['request_no'] ?? '');
        $department = trim($_POST['department'] ?? '');
        $requested_by = (int)($_POST['requested_by'] ?? currentUser()['id']);
        $deadline_date = trim($_POST['deadline_date'] ?? '');
        $priority = trim($_POST['priority'] ?? 'medium');
        $item_description = trim($_POST['item_description'] ?? '');
        $justification = trim($_POST['justification'] ?? '');
        $estimated_cost = (float)($_POST['estimated_cost'] ?? 0);
        
        if (empty($department) || empty($deadline_date) || empty($item_description)) {
            $error = 'Department, deadline, and item description are required.';
        } else {
            $pdo = getPDO();
            
            // Validate deadline is in the future
            if (strtotime($deadline_date) < strtotime(date('Y-m-d'))) {
                $error = 'Deadline date must be in the future.';
            } else {
                // Validate department budget
                $current_year = date('Y');
                $budget_stmt = $pdo->prepare("SELECT remaining_amount FROM department_budgets WHERE department_name = ? AND budget_year = ? AND status = 'active'");
                $budget_stmt->execute([$department, $current_year]);
                $active_budget = $budget_stmt->fetchColumn();
                
                if ($active_budget === false) {
                    $error = "No active budget found for $department in $current_year. Cannot create indent request.";
                } else {
                    // Calculate total pending and approved requests costs (that aren't this exact request)
                    $pending_stmt = $pdo->prepare("SELECT COALESCE(SUM(estimated_cost), 0) FROM purchase_requests WHERE department = ? AND status IN ('pending', 'approved') AND id != ?");
                    $pending_stmt->execute([$department, (int)($_POST['request_id'] ?? 0)]);
                    $pending_costs = (float)$pending_stmt->fetchColumn();
                    
                    $available_budget = $active_budget - $pending_costs;
                    
                    if ($estimated_cost > $available_budget) {
                        $error = 'Insufficient budget. You are requesting Rs. ' . number_format($estimated_cost, 2) . ', but the available budget limit is only Rs. ' . number_format($available_budget, 2) . ' (Remaining Budget: Rs. ' . number_format($active_budget, 2) . ', Pending/Approved Indents: Rs. ' . number_format($pending_costs, 2) . ').';
                    } else {
                        // Generate request number if not provided
                        if (empty($request_no)) {
                            $stmt = $pdo->query("SELECT MAX(CAST(SUBSTRING(request_no, 5) AS UNSIGNED)) as max_num FROM purchase_requests");
                            $next_num = ($stmt->fetch()['max_num'] ?? 0) + 1;
                            $request_no = 'REQ-' . str_pad($next_num, 4, '0', STR_PAD_LEFT);
                        }
                        
                        if (isset($_POST['edit_request']) && !empty($_POST['request_id'])) {
                    // Update existing request
                    $stmt = $pdo->prepare("UPDATE purchase_requests SET request_no=?, department=?, requested_by=?, estimated_cost=?, item_description=?, deadline_date=?, priority=?, justification=? WHERE id=?");
                    if ($stmt->execute([$request_no, $department, $requested_by, $estimated_cost, $item_description, $deadline_date, $priority, $justification, (int)$_POST['request_id']])) {
                        $message = 'Request updated successfully!';
                    } else {
                        $error = 'Error updating request.';
                    }
                } else {
                    // Check if request number already exists
                    $stmt = $pdo->prepare("SELECT id FROM purchase_requests WHERE request_no = ?");
                    $stmt->execute([$request_no]);
                    if ($stmt->fetch()) {
                        $error = 'A request with this number already exists.';
                    } else {
                        // Insert new request
                        $stmt = $pdo->prepare("INSERT INTO purchase_requests (request_no, department, requested_by, estimated_cost, item_description, deadline_date, priority, justification, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending')");
                        if ($stmt->execute([$request_no, $department, $requested_by, $estimated_cost, $item_description, $deadline_date, $priority, $justification])) {
                            $message = 'Request added successfully!';
                        } else {
                            $error = 'Error adding request.';
                        }
                    }
                }
            }
        }
    }
}
}
    
    // Approve request
    if (isset($_POST['approve_request']) && !empty($_POST['request_id'])) {
        $pdo = getPDO();
        $request_id = (int)$_POST['request_id'];
        $user = currentUser();
        $approver_id = $user['id'];
        
        // Security check: Only Super Admin can approve
        if ($user['role'] !== 'super_admin') {
            $error = 'Access Denied: Only Super Admin is authorized to approve requests.';
        } else {
            $stmt = $pdo->prepare("UPDATE purchase_requests SET status='approved', approved_by=?, approved_at=NOW() WHERE id=?");
            if ($stmt->execute([$approver_id, $request_id])) {
                $message = 'Request approved successfully!';
            } else {
                $error = 'Error approving request.';
            }
        }
    }
    
    // Reject request
    if (isset($_POST['reject_request']) && !empty($_POST['request_id'])) {
        $pdo = getPDO();
        $request_id = (int)$_POST['request_id'];
        $user = currentUser();

        // Security check: Only Super Admin can reject
        if ($user['role'] !== 'super_admin') {
            $error = 'Access Denied: Only Super Admin is authorized to reject requests.';
        } else {
            $stmt = $pdo->prepare("UPDATE purchase_requests SET status='rejected' WHERE id=?");
            if ($stmt->execute([$request_id])) {
                $message = 'Request rejected successfully!';
            } else {
                $error = 'Error rejecting request.';
            }
        }
    }
    
    // Delete request
    if (isset($_POST['delete_request']) && !empty($_POST['delete_id'])) {
        $pdo = getPDO();
        $delete_id = (int)$_POST['delete_id'];
        
        // Check if request has associated PO
        $stmt = $pdo->prepare("SELECT id FROM purchase_orders WHERE request_id = ?");
        $stmt->execute([$delete_id]);
        $po_record = $stmt->fetch();
        
        if ($po_record) {
            $error = 'Cannot delete request. It has an associated purchase order.';
        } else {
            $stmt = $pdo->prepare("DELETE FROM purchase_requests WHERE id = ?");
            if ($stmt->execute([$delete_id])) {
                $message = 'Request deleted successfully!';
            } else {
                $error = 'Error deleting request.';
            }
        }
    }
}

// Pagination and Search for requests
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$search = trim($_GET['search'] ?? '');
$limit = 10;
$offset = ($page - 1) * $limit;

$where_clause = "WHERE 1=1";
$params = [];

if (!empty($search)) {
    $where_clause .= " AND (pr.department LIKE ? OR pr.request_no LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

// Strict Departmental Isolation
$user = currentUser();
if ($user['role'] === 'hod') {
    $where_clause .= " AND pr.department IN (SELECT name FROM university_departments WHERE hod_id = ?)";
    $params[] = $user['id'];
} elseif ($user['role'] === 'coordinator') {
    $where_clause .= " AND pr.department IN (SELECT name FROM university_departments WHERE coordinator_id = ?)";
    $params[] = $user['id'];
} elseif ($user['role'] === 'faculty') {
    $where_clause .= " AND pr.requested_by = ?";
    $params[] = $user['id'];
}

// Count total records
$count_sql = "SELECT COUNT(*) FROM purchase_requests pr " . $where_clause;
$count_stmt = $pdo->prepare($count_sql);
$count_stmt->execute($params);
$total_records = $count_stmt->fetchColumn();
$total_pages = ceil($total_records / $limit);

// Fetch paginated records
$sql = "SELECT pr.*, u.name as requested_by_name, ua.name as approved_by_name FROM purchase_requests pr LEFT JOIN users u ON pr.requested_by = u.id LEFT JOIN users ua ON pr.approved_by = ua.id " . $where_clause . " ORDER BY pr.created_at DESC LIMIT ? OFFSET ?";
$stmt = $pdo->prepare($sql);

$param_index = 1;
if (!empty($params)) {
    foreach ($params as $val) {
        $stmt->bindValue($param_index++, $val);
    }
}

$stmt->bindValue($param_index++, $limit, PDO::PARAM_INT);
$stmt->bindValue($param_index++, $offset, PDO::PARAM_INT);

$stmt->execute();
$requests = $stmt->fetchAll();

// Fetch all users for the form
$stmt = $pdo->query("SELECT id, name, department FROM users WHERE is_active = 1 ORDER BY name ASC");
$users = $stmt->fetchAll();

// Editing existing request
$editing_request = null;
if (isset($_GET['edit']) && !empty($_GET['edit'])) {
    $edit_id = (int)$_GET['edit'];
    $stmt = $pdo->prepare("SELECT * FROM purchase_requests WHERE id = ?");
    $stmt->execute([$edit_id]);
    $editing_request = $stmt->fetch();
}

// Fetch departments for the modal dropdown
$dept_stmt = $pdo->query("SELECT name FROM university_departments ORDER BY name ASC");
$departments_list = $dept_stmt->fetchAll(PDO::FETCH_COLUMN);
?>

<div class="row">
    <div class="col-md-12">
        <?php if ($message): ?>
            <div class="alert alert-success alert-dismissible"><button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button><?= escape($message) ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible"><button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button><?= escape($error) ?></div>
        <?php endif; ?>
        

        
        <!-- Search Form -->
        <div class="card shadow-sm">
            <div class="card-header border-bottom">
                <div class="d-flex justify-content-between align-items-center">
                    <h3 class="card-title font-weight-bold m-0">
                        <i class="fas fa-file-invoice-dollar text-primary mr-1"></i> Purchase Indents
                    </h3>
                    <div class="card-tools m-0 d-flex">
                        <form method="GET" class="me-2">
                            <div class="input-group input-group-sm">
                                <input type="text" class="form-control" name="search" placeholder="Search ID/Dept..." value="<?= escape($search) ?>">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-search"></i>
                                </button>
                            </div>
                        </form>
                        <button type="button" class="btn btn-sm btn-success shadow-sm" data-bs-toggle="modal" data-bs-target="#requestModal">
                            <i class="fas fa-plus-circle mr-1"></i> New Indent Request
                        </button>
                    </div>
                </div>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Request #</th>
                                <th>Item Description</th>
                                <th>Department</th>
                                <th>Requested By</th>
                                <th>Est. Cost</th>
                                <th>Deadline</th>
                                <th>Priority</th>
                                <th>Status</th>
                                <th>Created</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($requests as $request): 
                                $days_until_deadline = (strtotime($request['deadline_date']) - strtotime(date('Y-m-d'))) / (60 * 60 * 24);
                                $is_overdue = $days_until_deadline < 0 && $request['status'] != 'completed';
                            ?>
                                <tr class="<?= $is_overdue ? 'text-danger font-weight-bold' : '' ?>">
                                    <td><code><?= escape($request['request_no']) ?></code></td>
                                    <td><strong><?= escape($request['item_description']) ?></strong></td>
                                    <td><?= escape($request['department']) ?></td>
                                    <td><?= escape($request['requested_by_name']) ?></td>
                                    <td>Rs. <?= number_format($request['estimated_cost'], 2) ?></td>
                                    <td>
                                        <?= escape($request['deadline_date']) ?>
                                        <?php if ($is_overdue): ?>
                                            <span class="badge badge-danger">OVERDUE!</span>
                                        <?php elseif ($days_until_deadline >= 0 && $days_until_deadline <= 3 && $request['status'] == 'pending'): ?>
                                            <span class="badge badge-warning">Due Soon!</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php 
                                        $priority_badge = [
                                            'low' => 'success',
                                            'medium' => 'info',
                                            'high' => 'warning',
                                            'urgent' => 'danger'
                                        ][$request['priority']] ?? 'secondary';
                                        ?>
                                        <span class="badge badge-<?= $priority_badge ?>"><?= ucfirst($request['priority']) ?></span>
                                    </td>
                                    <td>
                                        <?php 
                                        $status_badge = [
                                            'pending' => 'warning',
                                            'approved' => 'success',
                                            'rejected' => 'danger',
                                            'completed' => 'primary'
                                        ][$request['status']] ?? 'secondary';
                                        ?>
                                        <span class="badge badge-<?= $status_badge ?>"><?= ucfirst($request['status']) ?></span>
                                    </td>
                                    <td><?= escape(date('M j', strtotime($request['created_at']))) ?></td>
                                    <td>
                                        <a href="?edit=<?= $request['id'] ?>" class="btn btn-sm btn-primary">Edit</a>
                                        
                                        <?php if ($request['status'] == 'pending' && currentUser()['role'] === 'super_admin'): ?>
                                            <form method="post" style="display: inline;">
                                                <input type="hidden" name="request_id" value="<?= $request['id'] ?>">
                                                <button type="submit" name="approve_request" class="btn btn-sm btn-success">Approve</button>
                                            </form>
                                            <form method="post" style="display: inline;">
                                                <input type="hidden" name="request_id" value="<?= $request['id'] ?>">
                                                <button type="submit" name="reject_request" class="btn btn-sm btn-danger">Reject</button>
                                            </form>
                                        <?php endif; ?>
                                        
                                        <button type="button" class="btn btn-sm btn-danger" 
                                                onclick="confirmDelete(<?= $request['id'] ?>)">Delete</button>
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
    </div>
</div>

<!-- INDENT REQUEST MODAL -->
<div class="modal fade" id="requestModal" tabindex="-1" aria-labelledby="requestModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="requestModalLabel">
                    <i class="fas fa-<?= $editing_request ? 'edit' : 'plus-circle' ?> mr-2"></i>
                    <?= $editing_request ? 'Update Indent Request' : 'Create New Purchase Indent' ?>
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="post" onsubmit="let btn=this.querySelector('button[type=submit]'); setTimeout(() => { btn.disabled=true; btn.innerHTML='<i class=\'fas fa-spinner fa-spin mr-1\'></i> Processing...'; }, 10); return true;">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="request_no" class="form-label">Indent Number (Internal Reference)</label>
                                <input type="text" class="form-control" id="request_no" name="request_no" 
                                       value="<?= $editing_request ? escape($editing_request['request_no']) : '' ?>" 
                                       placeholder="Leave blank for auto-generation">
                            </div>
                            
                            <div class="mb-3">
                                <label for="department" class="form-label">Charging Department *</label>
                                <select class="form-select" id="department" name="department" required>
                                    <option value="">— Select Department —</option>
                                    <?php 
                                    foreach ($departments_list as $dept): 
                                    ?>
                                        <option value="<?= escape($dept) ?>" 
                                                <?= ($editing_request && $editing_request['department'] == $dept) || (!isset($editing_request) && $dept == ($user['department'] ?? '')) ? 'selected' : '' ?>>
                                            <?= escape($dept) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="mb-3">
                                <label for="deadline_date" class="form-label">Fulfillment Deadline *</label>
                                <input type="date" class="form-control" id="deadline_date" name="deadline_date" 
                                       value="<?= $editing_request ? escape($editing_request['deadline_date']) : date('Y-m-d', strtotime('+7 days')) ?>" required>
                            </div>

                            <div class="mb-3">
                                <label for="estimated_cost" class="form-label">Estimated Budget Impact (Rs.) *</label>
                                <input type="number" step="0.01" class="form-control" id="estimated_cost" name="estimated_cost" 
                                       value="<?= $editing_request ? escape($editing_request['estimated_cost']) : '' ?>" required placeholder="0.00">
                                <small class="form-text text-muted">Must be within your remaining departmental budget.</small>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                             <div class="mb-3">
                                <label for="item_description" class="form-label">Primary Item / Service Description *</label>
                                <input type="text" class="form-control" id="item_description" name="item_description" 
                                       value="<?= $editing_request ? escape($editing_request['item_description']) : '' ?>" required placeholder="e.g., High-performance Server, Lab Furniture">
                            </div>

                            <div class="mb-3">
                                <label for="requested_by" class="form-label">Initiated By (Personnel)</label>
                                <select class="form-select shadow-sm" id="requested_by" name="requested_by">
                                    <option value="">— Select Requester —</option>
                                    <?php foreach ($users as $u): ?>
                                        <option value="<?= $u['id'] ?>" 
                                                data-dept="<?= escape($u['department'] ?? '') ?>"
                                                <?= ($editing_request && $editing_request['requested_by'] == $u['id']) || (!isset($editing_request) && $u['id'] == currentUser()['id']) ? 'selected' : '' ?>>
                                            <?= escape($u['name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="mb-3">
                                <label for="priority" class="form-label">Indent Priority</label>
                                <select class="form-select" id="priority" name="priority">
                                    <option value="low" <?= $editing_request && $editing_request['priority'] == 'low' ? 'selected' : '' ?>>Low (Routine)</option>
                                    <option value="medium" <?= $editing_request && $editing_request['priority'] == 'medium' ? 'selected' : '' ?>>Medium (Normal)</option>
                                    <option value="high" <?= $editing_request && $editing_request['priority'] == 'high' ? 'selected' : '' ?>>High (Important)</option>
                                    <option value="urgent" <?= $editing_request && $editing_request['priority'] == 'urgent' ? 'selected' : '' ?>>Urgent (Critical)</option>
                                </select>
                            </div>
                            
                             <div class="mb-3">
                                <label for="justification" class="form-label">Procurement Justification *</label>
                                <textarea class="form-control" id="justification" name="justification" rows="3" required placeholder="Academic or research necessity..."><?= $editing_request ? escape($editing_request['justification']) : '' ?></textarea>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer justify-content-between">
                    <button type="button" class="btn btn-default shadow-sm px-4" data-bs-dismiss="modal">Close</button>
                    <div>
                        <?php if ($editing_request): ?>
                            <input type="hidden" name="request_id" value="<?= $editing_request['id'] ?>">
                            <a href="indent_requests.php" class="btn btn-secondary mr-2">Cancel</a>
                        <?php endif; ?>
                        <button type="submit" name="<?= $editing_request ? 'edit_request' : 'add_request' ?>" class="btn btn-primary shadow-sm px-5">
                            <i class="fas fa-check-circle mr-1"></i> <?= $editing_request ? 'Update Indent' : 'Submit Indent' ?>
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function confirmDelete(requestId) {
    if (confirm('Are you sure you want to delete this request? This action cannot be undone if there are no associated purchase orders.')) {
        const form = document.createElement('form');
        form.method = 'post';
        form.style.display = 'none';
        form.innerHTML = '<input type="hidden" name="delete_id" value="' + requestId + '">' +
                         '<input type="hidden" name="delete_request" value="1">';
        document.body.appendChild(form);
        form.submit();
    }
}

// Dynamic Department Filtering for Requested By
document.addEventListener('DOMContentLoaded', function() {
    const deptSelect = document.getElementById('department');
    const userSelect = document.getElementById('requested_by');
    const allOptions = Array.from(userSelect.options);

    function filterUsers() {
        const selectedDept = deptSelect.value;
        const currentVal = userSelect.value;
        
        userSelect.innerHTML = '';
        allOptions.forEach(opt => {
            const optDept = opt.getAttribute('data-dept');
            // Strict match: only show if department matches and is not empty
            if (opt.value === "" || (selectedDept !== "" && optDept === selectedDept)) {
                userSelect.appendChild(opt);
            }
        });
        
        // Restore selection if still valid
        if ([...userSelect.options].some(o => o.value === currentVal)) {
            userSelect.value = currentVal;
        }
    }

    if (deptSelect && userSelect) {
        deptSelect.addEventListener('change', filterUsers);
        // Run on load to ensure correct initial state
        filterUsers();
    }
    
    // Auto-open modal for editing or errors
    <?php if ($editing_request || $error): ?>
        var requestModal = new bootstrap.Modal(document.getElementById('requestModal'));
        requestModal.show();
    <?php endif; ?>
});
</script>

<?php
include __DIR__ . '/../../includes/footer.php';
?>