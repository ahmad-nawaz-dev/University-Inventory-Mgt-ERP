<?php
// dashboards/maintenance/asset_transfer.php - Asset Transfer & Movement Tracking Module
require_once __DIR__ . '/../../includes/header.php';

$message = '';
$error = '';

// Handle asset transfers
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_role = currentUser()['role'];
    if (isset($_POST['request_transfer']) || isset($_POST['edit_transfer'])) {
        $asset_id = (int)($_POST['asset_id'] ?? 0);
        $from_department = trim($_POST['from_department'] ?? '');
        $to_department = trim($_POST['to_department'] ?? '');
        $from_user_id = !empty($_POST['from_user_id']) ? (int)$_POST['from_user_id'] : null;
        $to_user_id = !empty($_POST['to_user_id']) ? (int)$_POST['to_user_id'] : null;
        $transfer_reason = trim($_POST['transfer_reason'] ?? '');
        if (empty($asset_id) || empty($to_department)) {
            $error = 'Asset and destination department are required.';
        } else {
            $pdo = getPDO();
            
            if (isset($_POST['edit_transfer']) && !empty($_POST['transfer_id'])) {
                // Update existing transfer
                $stmt = $pdo->prepare("UPDATE asset_transfers SET asset_id=?, from_department=?, to_department=?, from_user_id=?, to_user_id=?, transfer_reason=? WHERE id=?");
                if ($stmt->execute([$asset_id, $from_department, $to_department, $from_user_id, $to_user_id, $transfer_reason, (int)$_POST['transfer_id']])) {
                    $message = 'Transfer request updated successfully!';
                } else {
                    $error = 'Error updating transfer request.';
                }
            } else {
                // Insert new transfer request
                $stmt = $pdo->prepare("INSERT INTO asset_transfers (asset_id, from_department, to_department, from_user_id, to_user_id, transfer_reason, requested_by) VALUES (?, ?, ?, ?, ?, ?, ?)");
                if ($stmt->execute([$asset_id, $from_department, $to_department, $from_user_id, $to_user_id, $transfer_reason, currentUser()['id']])) {
                    $transfer_id = $pdo->lastInsertId();
                    $message = 'Transfer request submitted successfully!';
                    
                    // Add to movement log
                    $log_stmt = $pdo->prepare("INSERT INTO asset_movement_log (asset_id, transfer_id, from_department, to_department, from_user_id, to_user_id, movement_type, notes, created_by) VALUES (?, ?, ?, ?, ?, ?, 'transfer', 'Transfer request created', ?)");
                    $log_stmt->execute([$asset_id, $transfer_id, $from_department, $to_department, $from_user_id, $to_user_id, currentUser()['id']]);
                } else {
                    $error = 'Error creating transfer request.';
                }
            }
        }
    }
    
    // Approve transfer at level 1 (Coordinator)
    if (isset($_POST['approve_level1']) && !empty($_POST['transfer_id'])) {
        $pdo = getPDO();
        $transfer_id = (int)$_POST['transfer_id'];
        $current_user_id = currentUser()['id'];
        
        // Strict Departmental Authorization
        $stmt = $pdo->prepare("SELECT at.from_department, d.coordinator_id, d.hod_id 
                               FROM asset_transfers at 
                               LEFT JOIN university_departments d ON at.from_department = d.name 
                               WHERE at.id = ?");
        $stmt->execute([$transfer_id]);
        $transfer_auth = $stmt->fetch();

        if ($user_role === 'super_admin' || 
            ($transfer_auth && (
                ($user_role === 'coordinator' && ($transfer_auth['coordinator_id'] ?? 0) == $current_user_id) || 
                ($user_role === 'hod' && ($transfer_auth['hod_id'] ?? 0) == $current_user_id)
            ))
        ) {
            $stmt = $pdo->prepare("UPDATE asset_transfers SET status='level1_approved', approved_by_level1=?, approved_date_level1=NOW() WHERE id=?");
            if ($stmt->execute([$current_user_id, $transfer_id])) {
                $message = 'Transfer approved at Level 1 (Authorized Coordinator/HOD)!';
            } else {
                $error = 'Error approving transfer.';
            }
        } else {
            $error = 'Access Denied: You are not the authorized Coordinator/HOD for this department.';
        }
    }
    
    // Approve transfer at level 2 (HOD)
    if (isset($_POST['approve_level2']) && !empty($_POST['transfer_id'])) {
        $pdo = getPDO();
        $transfer_id = (int)$_POST['transfer_id'];
        $current_user_id = currentUser()['id'];
        
        // Check if user is the specific HOD of the department
        $stmt = $pdo->prepare("SELECT at.from_department, d.hod_id 
                               FROM asset_transfers at 
                               LEFT JOIN university_departments d ON at.from_department = d.name 
                               WHERE at.id = ?");
        $stmt->execute([$transfer_id]);
        $transfer_auth = $stmt->fetch();

        if ($user_role === 'super_admin' || 
            ($transfer_auth && $transfer_auth['hod_id'] == $current_user_id)
        ) {
            $stmt = $pdo->prepare("UPDATE asset_transfers SET status='level2_approved', approved_by_level2=?, approved_date_level2=NOW() WHERE id=?");
            if ($stmt->execute([$current_user_id, $transfer_id])) {
                $message = 'Transfer approved at Level 2 (Authorized HOD)!';
            } else {
                $error = 'Error approving transfer.';
            }
        } else {
            $error = 'Access Denied: You are not the authorized HOD for this department.';
        }
    }
    
    // Approve transfer at level 3 (Dean)
    if (isset($_POST['approve_level3']) && !empty($_POST['transfer_id'])) {
        $pdo = getPDO();
        $transfer_id = (int)$_POST['transfer_id'];
        $current_user_id = currentUser()['id'];
        
        // Check if user has Dean role or higher
        $user_role = currentUser()['role'];
        if (strpos(strtolower($user_role), 'dean') !== false || 
            $user_role === 'super_admin') {
            
            $stmt = $pdo->prepare("UPDATE asset_transfers SET status='level3_approved', approved_by_level3=?, approved_date_level3=NOW() WHERE id=?");
            if ($stmt->execute([$current_user_id, $transfer_id])) {
                $message = 'Transfer approved at Level 3 (Dean)!';
            } else {
                $error = 'Error approving transfer.';
            }
        } else {
            $error = 'Insufficient permissions to approve at this level.';
        }
    }
    
    // Complete transfer
    if (isset($_POST['complete_transfer']) && !empty($_POST['transfer_id'])) {
        $pdo = getPDO();
        $transfer_id = (int)$_POST['transfer_id'];
        $current_user_id = currentUser()['id'];
        
        // Update transfer status
        $stmt = $pdo->prepare("UPDATE asset_transfers SET status='completed', completed_date=NOW() WHERE id=?");
        if ($stmt->execute([$transfer_id])) {
            // Update asset in main assets table (Preserve existing location)
            $transfer_details = $pdo->prepare("SELECT asset_id, to_department, to_user_id FROM asset_transfers WHERE id=?");
            $transfer_details->execute([$transfer_id]);
            $transfer = $transfer_details->fetch();
            
            if ($transfer) {
                $asset_update = $pdo->prepare("UPDATE assets SET department=?, assigned_to_user_id=? WHERE id=?");
                $asset_update->execute([$transfer['to_department'], $transfer['to_user_id'], $transfer['asset_id']]);
                
                $message = 'Transfer completed successfully! Asset location updated.';
            }
        } else {
            $error = 'Error completing transfer.';
        }
    }
    
    // Reject transfer
    if (isset($_POST['reject_transfer']) && !empty($_POST['transfer_id'])) {
        $pdo = getPDO();
        $transfer_id = (int)$_POST['transfer_id'];
        $rejection_reason = trim($_POST['rejection_reason'] ?? '');
        
        $stmt = $pdo->prepare("UPDATE asset_transfers SET status='rejected', rejection_reason=? WHERE id=?");
        if ($stmt->execute([$rejection_reason, $transfer_id])) {
            $message = 'Transfer request rejected.';
        } else {
            $error = 'Error rejecting transfer.';
        }
    }
}

// Pagination and Search
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$search = trim($_GET['search'] ?? '');
$filter_status = trim($_GET['filter_status'] ?? '');
$limit = 10;
$offset = ($page - 1) * $limit;

$pdo = getPDO();
$user = currentUser();
$where_clause = "WHERE 1=1";
$params = [];

// Strict Departmental Isolation for HOD/Coordinator
if ($user['role'] === 'hod') {
    $where_clause .= " AND (at.from_department IN (SELECT name FROM university_departments WHERE hod_id = ?) 
                        OR at.to_department IN (SELECT name FROM university_departments WHERE hod_id = ?))";
    $params[] = $user['id'];
    $params[] = $user['id'];
} elseif ($user['role'] === 'coordinator') {
    $where_clause .= " AND (at.from_department IN (SELECT name FROM university_departments WHERE coordinator_id = ?) 
                        OR at.to_department IN (SELECT name FROM university_departments WHERE coordinator_id = ?))";
    $params[] = $user['id'];
    $params[] = $user['id'];
}

if (!empty($search)) {
    $where_clause .= " AND (a.asset_tag LIKE ? OR a.name LIKE ? OR at.transfer_reason LIKE ? OR at.to_department LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if (!empty($filter_status)) {
    $where_clause .= " AND at.status = ?";
    $params[] = $filter_status;
}

// Count total records
$count_sql = "
    SELECT COUNT(*) 
    FROM asset_transfers at
    LEFT JOIN assets a ON at.asset_id = a.id
    " . $where_clause;
$count_stmt = $pdo->prepare($count_sql);
$count_stmt->execute($params);
$total_records = $count_stmt->fetchColumn();
$total_pages = max(1, ceil($total_records / $limit));

// Fetch paginated records
$sql = "
    SELECT at.*, a.asset_tag, a.name as asset_name, a.department as current_dept,
           u1.name as requested_by_name, u2.name as approved_by_level1_name, 
           u3.name as approved_by_level2_name, u4.name as approved_by_level3_name
    FROM asset_transfers at
    LEFT JOIN assets a ON at.asset_id = a.id
    LEFT JOIN users u1 ON at.requested_by = u1.id
    LEFT JOIN users u2 ON at.approved_by_level1 = u2.id
    LEFT JOIN users u3 ON at.approved_by_level2 = u3.id
    LEFT JOIN users u4 ON at.approved_by_level3 = u4.id
    " . $where_clause . "
    ORDER BY at.requested_date DESC
    LIMIT ? OFFSET ?";
$params[] = $limit;
$params[] = $offset;

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$transfers = $stmt->fetchAll();

// Filter assets dropdown by department
$assets_where = " WHERE 1=1";
$assets_params = [];
if ($user['role'] === 'hod') {
    $assets_where .= " AND department IN (SELECT name FROM university_departments WHERE hod_id = ?)";
    $assets_params[] = $user['id'];
} elseif ($user['role'] === 'coordinator') {
    $assets_where .= " AND department IN (SELECT name FROM university_departments WHERE coordinator_id = ?)";
    $assets_params[] = $user['id'];
}

$assets_stmt = $pdo->prepare("SELECT id, asset_tag, name, department, class_location FROM assets $assets_where ORDER BY asset_tag ASC");
$assets_stmt->execute($assets_params);
$assets = $assets_stmt->fetchAll();

// Fetch all users for assignment
$users_stmt = $pdo->query("SELECT id, name, role, department FROM users WHERE is_active = 1 ORDER BY name ASC");
$users = $users_stmt->fetchAll();

// Fetch all departments
$depts_stmt = $pdo->query("SELECT name FROM university_departments ORDER BY name ASC");
$departments = $depts_stmt->fetchAll(PDO::FETCH_COLUMN);
// Add University Inventory as a special destination
if (!in_array('University Inventory', $departments)) {
    array_unshift($departments, 'University Inventory');
}

// Editing existing transfer
$editing_transfer = null;
if (isset($_GET['edit']) && !empty($_GET['edit'])) {
    $edit_id = (int)$_GET['edit'];
    $stmt = $pdo->prepare("
        SELECT at.*, a.asset_tag, a.name as asset_name 
        FROM asset_transfers at
        LEFT JOIN assets a ON at.asset_id = a.id
        WHERE at.id = ?
    ");
    $stmt->execute([$edit_id]);
    $editing_transfer = $stmt->fetch();
}
?>

<div class="row">
    <div class="col-md-12">
        <?php if ($message): ?>
            <div class="alert alert-success"><?= escape($message) ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-danger"><?= escape($error) ?></div>
        <?php endif; ?>
        
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Asset Transfer Management</h3>
            </div>
            
            <div class="card-body">
                <form method="post">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="asset_id" class="form-label">Asset *</label>
                                <select class="form-select" id="asset_id" name="asset_id" required>
                                    <option value="" data-dept="" data-loc="">Select Asset</option>
                                    <?php foreach ($assets as $asset): ?>
                                        <option value="<?= $asset['id'] ?>" 
                                                data-dept="<?= escape($asset['department']) ?>"
                                                data-loc="<?= escape($asset['class_location'] ?: '') ?>"
                                                <?= $editing_transfer && $editing_transfer['asset_id'] == $asset['id'] ? 'selected' : '' ?>>
                                            <?= escape($asset['asset_tag']) ?> - <?= escape($asset['name']) ?> (<?= escape($asset['department']) ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="mb-3">
                                <label for="from_department" class="form-label">From Department</label>
                                <select class="form-select" id="from_department" name="from_department">
                                    <option value="">Current Department</option>
                                    <?php foreach ($departments as $dept): ?>
                                        <option value="<?= escape($dept) ?>" 
                                                <?= $editing_transfer && $editing_transfer['from_department'] == $dept ? 'selected' : '' ?>>
                                            <?= escape($dept) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="mb-3">
                                <label for="to_department" class="form-label">To (Destination Dept) *</label>
                                <select class="form-select" id="to_department" name="to_department" required>
                                    <option value="">Select Destination Department</option>
                                    <?php foreach ($departments as $dept): ?>
                                        <option value="<?= escape($dept) ?>" 
                                                <?= $editing_transfer && $editing_transfer['to_department'] == $dept ? 'selected' : '' ?>>
                                            <?= escape($dept) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="from_user_id" class="form-label">From User</label>
                                <select class="form-select" id="from_user_id" name="from_user_id">
                                    <option value="" data-dept="">Select Current User</option>
                                    <!-- Users populated via JavaScript based on department -->
                                </select>
                            </div>
                            
                            <div class="mb-3">
                                <label for="to_user_id" class="form-label">To User</label>
                                <select class="form-select" id="to_user_id" name="to_user_id">
                                    <option value="" data-dept="">Select Receiving User</option>
                                    <!-- Users populated via JavaScript based on department -->
                                </select>
                            </div>
                            
                            <div class="mb-3">
                                <label for="transfer_reason" class="form-label">Reason for Transfer *</label>
                                <textarea class="form-control" id="transfer_reason" name="transfer_reason" rows="3" required><?= $editing_transfer ? escape($editing_transfer['transfer_reason']) : '' ?></textarea>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <?php if ($editing_transfer): ?>
                            <input type="hidden" name="transfer_id" value="<?= $editing_transfer['id'] ?>">
                            <button type="submit" name="edit_transfer" class="btn btn-primary">Update Transfer</button>
                            <a href="asset_transfer.php" class="btn btn-secondary">Cancel Edit</a>
                        <?php else: ?>
                            <button type="submit" name="request_transfer" class="btn btn-success">Request Transfer</button>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Search and Filter Form -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Search & Filter Transfer Requests</h3>
            </div>
            <div class="card-body">
                <form method="GET" class="row g-2 align-items-center mb-4 nc-animate-in">
                    <div class="col-md-5">
                        <input type="text" class="form-control" name="search" placeholder="Search by asset, department, reason..." 
                               value="<?= escape($search) ?>">
                    </div>
                    <div class="col-md-3">
                        <select class="form-select" name="filter_status">
                            <option value="">All Statuses</option>
                            <option value="pending" <?= $filter_status == 'pending' ? 'selected' : '' ?>>Pending</option>
                            <option value="level1_approved" <?= $filter_status == 'level1_approved' ? 'selected' : '' ?>>Level 1 Approved</option>
                            <option value="level2_approved" <?= $filter_status == 'level2_approved' ? 'selected' : '' ?>>Level 2 Approved</option>
                            <option value="level3_approved" <?= $filter_status == 'level3_approved' ? 'selected' : '' ?>>Level 3 Approved</option>
                            <option value="completed" <?= $filter_status == 'completed' ? 'selected' : '' ?>>Completed</option>
                            <option value="rejected" <?= $filter_status == 'rejected' ? 'selected' : '' ?>>Rejected</option>
                        </select>
                    </div>
                    <div class="col-md-auto">
                        <button type="submit" class="btn btn-primary shadow-sm px-4">Filter Results</button>
                        <a href="asset_transfer.php" class="btn btn-outline-secondary ml-1 px-4">Clear</a>
                    </div>
                </form>
            </div>
        </div>
        
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Transfer Requests</h3>
            </div>
            
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Asset</th>
                                <th>From → To</th>
                                <th>Requested By</th>
                                <th>Approvals</th>
                                <th>Status</th>
                                <th>Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($transfers as $transfer): ?>
                                <tr>
                                    <td>
                                        <code><?= escape($transfer['asset_tag']) ?></code><br>
                                        <small class="text-muted"><?= escape($transfer['asset_name']) ?></small>
                                    </td>
                                    <td>
                                        <div class="small">
                                            <?= escape($transfer['from_department'] ?? 'Current Dept') ?> → <?= escape($transfer['to_department']) ?>
                                        </div>
                                    </td>
                                    <td>
                                        <?= escape($transfer['requested_by_name']) ?>
                                    </td>
                                    <td>
                                        <div class="small">
                                            <span class="<?= $transfer['approved_by_level1_name'] ? 'text-success' : 'text-muted' ?>">✓ L1: <?= $transfer['approved_by_level1_name'] ?? 'Pending' ?></span><br>
                                            <span class="<?= $transfer['approved_by_level2_name'] ? 'text-success' : 'text-muted' ?>">✓ L2: <?= $transfer['approved_by_level2_name'] ?? 'Pending' ?></span><br>
                                            <span class="<?= $transfer['approved_by_level3_name'] ? 'text-success' : 'text-muted' ?>">✓ L3: <?= $transfer['approved_by_level3_name'] ?? 'Pending' ?></span>
                                        </div>
                                    </td>
                                    <td>
                                        <?php 
                                        $status_badge = [
                                            'pending' => 'warning',
                                            'level1_approved' => 'info',
                                            'level2_approved' => 'info',
                                            'level3_approved' => 'info',
                                            'completed' => 'success',
                                            'rejected' => 'danger'
                                        ][$transfer['status']] ?? 'secondary';
                                        ?>
                                        <span class="badge badge-<?= $status_badge ?>"><?= ucfirst(str_replace('_', ' ', $transfer['status'])) ?></span>
                                    </td>
                                    <td>
                                        <small><?= escape(date('M j', strtotime($transfer['requested_date']))) ?></small>
                                    </td>
                                    <td>
                                        <a href="?edit=<?= $transfer['id'] ?>" class="btn btn-sm btn-primary">Edit</a>
                                        
                                        <?php if ($transfer['status'] == 'pending'): ?>
                                            <form method="post" style="display: inline;">
                                                <input type="hidden" name="transfer_id" value="<?= $transfer['id'] ?>">
                                                <button type="submit" name="approve_level1" class="btn btn-sm btn-info">Approve L1</button>
                                            </form>
                                        <?php elseif ($transfer['status'] == 'level1_approved'): ?>
                                            <form method="post" style="display: inline;">
                                                <input type="hidden" name="transfer_id" value="<?= $transfer['id'] ?>">
                                                <button type="submit" name="approve_level2" class="btn btn-sm btn-info">Approve L2</button>
                                            </form>
                                        <?php elseif ($transfer['status'] == 'level2_approved'): ?>
                                            <form method="post" style="display: inline;">
                                                <input type="hidden" name="transfer_id" value="<?= $transfer['id'] ?>">
                                                <button type="submit" name="approve_level3" class="btn btn-sm btn-info">Approve L3</button>
                                            </form>
                                        <?php elseif ($transfer['status'] == 'level3_approved'): ?>
                                            <form method="post" style="display: inline;">
                                                <input type="hidden" name="transfer_id" value="<?= $transfer['id'] ?>">
                                                <button type="submit" name="complete_transfer" class="btn btn-sm btn-success">Complete</button>
                                            </form>
                                        <?php endif; ?>
                                        
                                        <?php if ($transfer['status'] != 'completed' && $transfer['status'] != 'rejected'): ?>
                                            <button type="button" class="btn btn-sm btn-danger" 
                                                    onclick="showRejectModal(<?= $transfer['id'] ?>)">Reject</button>
                                        <?php endif; ?>
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
                                <a class="page-link" href="?page=<?= $page - 1 ?>&search=<?= urlencode($search) ?>&filter_status=<?= urlencode($filter_status) ?>">«</a>
                            </li>
                        <?php endif; ?>
                        
                        <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                            <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                                <a class="page-link" href="?page=<?= $i ?>&search=<?= urlencode($search) ?>&filter_status=<?= urlencode($filter_status) ?>"><?= $i ?></a>
                            </li>
                        <?php endfor; ?>
                        
                        <?php if ($page < $total_pages): ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=<?= $page + 1 ?>&search=<?= urlencode($search) ?>&filter_status=<?= urlencode($filter_status) ?>">»</a>
                            </li>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Reject Modal -->
<div class="modal fade" id="rejectModal" tabindex="-1" aria-labelledby="rejectModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title font-weight-bold" id="rejectModalLabel">Reject Transfer Request</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="post">
                <div class="modal-body p-4">
                    <input type="hidden" id="reject_transfer_id" name="transfer_id">
                    <div class="mb-3">
                        <label for="rejection_reason" class="form-label">Rejection Reason *</label>
                        <textarea class="form-control" id="rejection_reason" name="rejection_reason" rows="4" required placeholder="Please provide a valid reason for rejection..."></textarea>
                    </div>
                </div>
                <div class="modal-footer bg-light border-0">
                    <button type="button" class="btn btn-outline-secondary px-4" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="reject_transfer" class="btn btn-danger px-4 shadow-sm">Reject Request</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function showRejectModal(transferId) {
    document.getElementById('reject_transfer_id').value = transferId;
    $('#rejectModal').modal('show');
}

document.addEventListener('DOMContentLoaded', function() {
    const assetSelect = document.getElementById('asset_id');
    const fromDeptSelect = document.getElementById('from_department');
    const toDeptSelect = document.getElementById('to_department');
    const fromUserSelect = document.getElementById('from_user_id');
    const toUserSelect = document.getElementById('to_user_id');
    const allUsers = <?= json_encode($users) ?>;
    
    function updatePersonnelDropdown(selectId, dept, placeholderText) {
        const select = document.getElementById(selectId);
        if (!select) return;
        
        const currentVal = select.value;
        select.innerHTML = `<option value="">${placeholderText}</option>`;
        
        const targetDept = (dept || "").trim();
        if (targetDept === "") {
            // Trigger Select2 update if applicable
            if (window.jQuery && typeof window.jQuery(select).select2 === 'function') {
                window.jQuery(select).trigger('change');
            }
            return;
        }

        allUsers.forEach(u => {
            const userDept = (u.department || "").trim();
            if (userDept === targetDept) {
                const opt = document.createElement('option');
                opt.value = u.id;
                opt.textContent = `${u.name} (${u.role}) - ${u.department}`;
                opt.setAttribute('data-dept', u.department);
                if (u.id == currentVal) opt.selected = true;
                select.appendChild(opt);
            }
        });

        // Trigger Select2 update if applicable
        if (window.jQuery && typeof window.jQuery(select).select2 === 'function') {
            window.jQuery(select).trigger('change');
        }
    }

    if (assetSelect) {
        assetSelect.addEventListener('change', function() {
            const selected = assetSelect.options[assetSelect.selectedIndex];
            const dept = selected.getAttribute('data-dept');
            if (dept) {
                fromDeptSelect.value = dept;
                updatePersonnelDropdown('from_user_id', dept, 'Select Current User');
            }
        });
    }

    if (fromDeptSelect) {
        fromDeptSelect.addEventListener('change', () => {
            updatePersonnelDropdown('from_user_id', fromDeptSelect.value, 'Select Current User');
        });
    }

    if (toDeptSelect) {
        toDeptSelect.addEventListener('change', () => {
            updatePersonnelDropdown('to_user_id', toDeptSelect.value, 'Select Receiving User');
        });
    }

    // Initial filter on load
    updatePersonnelDropdown('from_user_id', fromDeptSelect.value, 'Select Current User');
    updatePersonnelDropdown('to_user_id', toDeptSelect.value, 'Select Receiving User');
});
</script>

<?php
include __DIR__ . '/../../includes/footer.php';
?>