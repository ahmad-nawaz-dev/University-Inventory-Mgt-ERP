<?php
// dashboards/reservation/asset_reservation.php - Asset Reservation & Booking System
require_once __DIR__ . '/../../includes/header.php';

$message = '';
$error = '';

// Handle asset reservations
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['request_reservation']) || isset($_POST['edit_reservation'])) {
        $asset_id = (int)($_POST['asset_id'] ?? 0);
        $purpose = trim($_POST['purpose'] ?? '');
        $reservation_start = trim($_POST['reservation_start'] ?? '');
        $reservation_end = trim($_POST['reservation_end'] ?? '');
        $department = trim($_POST['department'] ?? '');
        $priority = trim($_POST['priority'] ?? 'medium');
        
        if (empty($asset_id) || empty($purpose) || empty($reservation_start) || empty($reservation_end)) {
            $error = 'Asset, purpose, and reservation dates are required.';
        } elseif (strtotime($reservation_start) >= strtotime($reservation_end)) {
            $error = 'End time must be after start time.';
        } elseif (strtotime($reservation_start) < time()) {
            $error = 'Reservation start time must be in the future.';
        } else {
            $pdo = getPDO();
            
            // Check for conflicts
            $conflict_check = $pdo->prepare("
                SELECT ar.id, ar.reservation_start_datetime, ar.reservation_end_datetime, u.name as requester_name
                FROM asset_reservations ar
                LEFT JOIN users u ON ar.requester_user_id = u.id
                WHERE ar.asset_id = ? 
                AND ar.status IN ('pending', 'approved', 'in_use')
                AND (
                    (ar.reservation_start_datetime < ? AND ar.reservation_end_datetime > ?) OR
                    (ar.reservation_start_datetime < ? AND ar.reservation_end_datetime > ?) OR
                    (? BETWEEN ar.reservation_start_datetime AND ar.reservation_end_datetime) OR
                    (? BETWEEN ar.reservation_start_datetime AND ar.reservation_end_datetime)
                )
            ");
            $conflict_check->execute([
                $asset_id, 
                $reservation_end, $reservation_start, 
                $reservation_end, $reservation_start,
                $reservation_start, $reservation_end
            ]);
            $conflicts = $conflict_check->fetchAll();
            
            if (!empty($conflicts)) {
                $conflict_list = implode(', ', array_map(function($c) {
                    return $c['requester_name'] . ' (' . $c['reservation_start_datetime'] . ' - ' . $c['reservation_end_datetime'] . ')';
                }, $conflicts));
                $error = 'Conflict detected with existing reservation(s): ' . $conflict_list;
            } else {
                if (isset($_POST['edit_reservation']) && !empty($_POST['reservation_id'])) {
                    // Update existing reservation
                    $stmt = $pdo->prepare("UPDATE asset_reservations SET asset_id=?, purpose=?, reservation_start_datetime=?, reservation_end_datetime=?, department=?, priority=?, updated_at=NOW() WHERE id=?");
                    if ($stmt->execute([$asset_id, $purpose, $reservation_start, $reservation_end, $department, $priority, (int)$_POST['reservation_id']])) {
                        $message = 'Reservation updated successfully!';
                    } else {
                        $error = 'Error updating reservation.';
                    }
                } else {
                    // Insert new reservation
                    $stmt = $pdo->prepare("INSERT INTO asset_reservations (asset_id, requester_user_id, department, purpose, reservation_start_datetime, reservation_end_datetime, priority, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                    if ($stmt->execute([$asset_id, currentUser()['id'], $department, $purpose, $reservation_start, $reservation_end, $priority, currentUser()['id']])) {
                        $message = 'Reservation request submitted successfully!';
                    } else {
                        $error = 'Error creating reservation.';
                    }
                }
            }
        }
    }
    
    // Approve reservation
    if (isset($_POST['approve_reservation']) && !empty($_POST['reservation_id'])) {
        $pdo = getPDO();
        $reservation_id = (int)$_POST['reservation_id'];
        $current_user_id = currentUser()['id'];
        
        $stmt = $pdo->prepare("UPDATE asset_reservations SET status='approved', approved_by=?, approved_at=NOW() WHERE id=?");
        if ($stmt->execute([$current_user_id, $reservation_id])) {
            $pdo->prepare("UPDATE assets SET status='reserved' WHERE id = (SELECT asset_id FROM asset_reservations WHERE id=?)")->execute([$reservation_id]);
            $message = 'Reservation approved successfully!';
        } else {
            $error = 'Error approving reservation.';
        }
    }
    
    // Reject reservation
    if (isset($_POST['reject_reservation']) && !empty($_POST['reservation_id'])) {
        $pdo = getPDO();
        $reservation_id = (int)$_POST['reservation_id'];
        $rejection_reason = trim($_POST['rejection_reason'] ?? '');
        
        $stmt = $pdo->prepare("UPDATE asset_reservations SET status='rejected', rejection_reason=? WHERE id=?");
        if ($stmt->execute([$rejection_reason, $reservation_id])) {
            $message = 'Reservation rejected.';
        } else {
            $error = 'Error rejecting reservation.';
        }
    }
    
    // Mark reservation as in use
    if (isset($_POST['start_reservation']) && !empty($_POST['reservation_id'])) {
        $pdo = getPDO();
        $reservation_id = (int)$_POST['reservation_id'];
        
        $stmt = $pdo->prepare("UPDATE asset_reservations SET status='in_use' WHERE id=?");
        if ($stmt->execute([$reservation_id])) {
            $pdo->prepare("UPDATE assets SET status='in_use' WHERE id = (SELECT asset_id FROM asset_reservations WHERE id=?)")->execute([$reservation_id]);
            $message = 'Reservation started!';
        } else {
            $error = 'Error starting reservation.';
        }
    }
    
    // Complete reservation
    if (isset($_POST['complete_reservation']) && !empty($_POST['reservation_id'])) {
        $pdo = getPDO();
        $reservation_id = (int)$_POST['reservation_id'];
        
        $stmt = $pdo->prepare("UPDATE asset_reservations SET status='completed' WHERE id=?");
        if ($stmt->execute([$reservation_id])) {
            $pdo->prepare("UPDATE assets SET status='in_stock' WHERE id = (SELECT asset_id FROM asset_reservations WHERE id=?)")->execute([$reservation_id]);
            $message = 'Reservation completed!';
        } else {
            $error = 'Error completing reservation.';
        }
    }
    
    // Cancel reservation
    if (isset($_POST['cancel_reservation']) && !empty($_POST['reservation_id'])) {
        $pdo = getPDO();
        $reservation_id = (int)$_POST['reservation_id'];
        
        $stmt = $pdo->prepare("UPDATE asset_reservations SET status='cancelled' WHERE id=?");
        if ($stmt->execute([$reservation_id])) {
            $pdo->prepare("UPDATE assets SET status='in_stock' WHERE id = (SELECT asset_id FROM asset_reservations WHERE id=?)")->execute([$reservation_id]);
            $message = 'Reservation cancelled!';
        } else {
            $error = 'Error cancelling reservation.';
        }
    }
}

// Pagination and Search
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$search = trim($_GET['search'] ?? '');
$filter_status = trim($_GET['filter_status'] ?? '');
$filter_department = trim($_GET['filter_department'] ?? '');
$limit = 10;
$offset = ($page - 1) * $limit;

$pdo = getPDO();
$user = currentUser();
$where_clause = "WHERE 1=1";
$params = [];

if ($user['role'] === 'hod') {
    $where_clause .= " AND (ar.department IN (SELECT name FROM university_departments WHERE hod_id = ?) 
                        OR a.department IN (SELECT name FROM university_departments WHERE hod_id = ?))";
    $params[] = $user['id'];
    $params[] = $user['id'];
} elseif ($user['role'] === 'coordinator') {
    $where_clause .= " AND (ar.department IN (SELECT name FROM university_departments WHERE coordinator_id = ?) 
                        OR a.department IN (SELECT name FROM university_departments WHERE coordinator_id = ?))";
    $params[] = $user['id'];
    $params[] = $user['id'];
}

if (!empty($search)) {
    $where_clause .= " AND (a.asset_tag LIKE ? OR a.name LIKE ? OR ar.purpose LIKE ? OR u.name LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if (!empty($filter_status)) {
    $where_clause .= " AND ar.status = ?";
    $params[] = $filter_status;
}

if (!empty($filter_department)) {
    $where_clause .= " AND ar.department = ?";
    $params[] = $filter_department;
}

// Count total records
$count_sql = "
    SELECT COUNT(*) 
    FROM asset_reservations ar
    LEFT JOIN assets a ON ar.asset_id = a.id
    LEFT JOIN users u ON ar.requester_user_id = u.id
    " . $where_clause;
$count_stmt = $pdo->prepare($count_sql);
$count_stmt->execute($params);
$total_records = $count_stmt->fetchColumn();
$total_pages = ceil($total_records / $limit);

// Fetch paginated records
$sql = "
    SELECT ar.*, a.asset_tag, a.name as asset_name, u.name as requester_name, ub.name as approved_by_name
    FROM asset_reservations ar
    LEFT JOIN assets a ON ar.asset_id = a.id
    LEFT JOIN users u ON ar.requester_user_id = u.id
    LEFT JOIN users ub ON ar.approved_by = ub.id
    " . $where_clause . "
    ORDER BY ar.reservation_start_datetime ASC
    LIMIT ? OFFSET ?";
$params[] = $limit;
$params[] = $offset;

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$reservations = $stmt->fetchAll();

// Fetch all assets for dropdown - Filtered for relevance
$assets_where = " WHERE a.status = 'in_stock'";
$assets_params = [];
if ($user['role'] === 'hod') {
    $assets_where .= " AND a.department IN (SELECT name FROM university_departments WHERE hod_id = ?)";
    $assets_params[] = $user['id'];
} elseif ($user['role'] === 'coordinator') {
    $assets_where .= " AND a.department IN (SELECT name FROM university_departments WHERE coordinator_id = ?)";
    $assets_params[] = $user['id'];
}

$assets_stmt = $pdo->prepare("
    SELECT a.id, a.asset_tag, a.name, a.department, ac.name as category_name
    FROM assets a
    LEFT JOIN asset_categories ac ON a.category_id = ac.id
    $assets_where
    ORDER BY a.asset_tag ASC
");
$assets_stmt->execute($assets_params);
$assets = $assets_stmt->fetchAll();

// Fetch all users for requester selection
$users_stmt = $pdo->query("SELECT id, name, role FROM users WHERE is_active = 1 ORDER BY name ASC");
$users = $users_stmt->fetchAll();

// Fetch all departments
$depts_stmt = $pdo->query("SELECT name FROM university_departments ORDER BY name ASC");
$departments = $depts_stmt->fetchAll(PDO::FETCH_COLUMN);

// Editing existing reservation
$editing_reservation = null;
if (isset($_GET['edit']) && !empty($_GET['edit'])) {
    $edit_id = (int)$_GET['edit'];
    $stmt = $pdo->prepare("
        SELECT ar.*, a.asset_tag, a.name as asset_name 
        FROM asset_reservations ar
        LEFT JOIN assets a ON ar.asset_id = a.id
        WHERE ar.id = ?
    ");
    $stmt->execute([$edit_id]);
    $editing_reservation = $stmt->fetch();
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
        
        <div class="row">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Asset Reservation System</h3>
                    </div>
                    
                    <div class="card-body">
                        <form method="post" class="nc-animate-in">
                            <div class="row g-4">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="asset_id" class="form-label">Selected Asset *</label>
                                        <select class="form-select" id="asset_id" name="asset_id" required>
                                            <option value="">— Select Available Asset —</option>
                                            <?php foreach ($assets as $asset): ?>
                                                <option value="<?= $asset['id'] ?>" 
                                                        <?= $editing_reservation && $editing_reservation['asset_id'] == $asset['id'] ? 'selected' : '' ?>>
                                                    <?= escape($asset['asset_tag']) ?> - <?= escape($asset['name']) ?> (<?= escape($asset['category_name']) ?>)
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="reservation_start" class="form-label">Start Date & Time *</label>
                                        <input type="datetime-local" class="form-control" id="reservation_start" name="reservation_start" 
                                               value="<?= $editing_reservation ? escape($editing_reservation['reservation_start_datetime']) : date('Y-m-d\TH:i', strtotime('+1 hour')) ?>" required>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="reservation_end" class="form-label">End Date & Time *</label>
                                        <input type="datetime-local" class="form-control" id="reservation_end" name="reservation_end" 
                                               value="<?= $editing_reservation ? escape($editing_reservation['reservation_end_datetime']) : date('Y-m-d\TH:i', strtotime('+2 hours')) ?>" required>
                                    </div>
                                </div>
                                
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="department" class="form-label">Department / Lab</label>
                                        <select class="form-select" id="department" name="department">
                                            <option value="">— Select Department —</option>
                                            <?php foreach ($departments as $dept): ?>
                                                <option value="<?= escape($dept) ?>" 
                                                        <?= $editing_reservation && $editing_reservation['department'] == $dept ? 'selected' : '' ?>>
                                                    <?= escape($dept) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="priority" class="form-label">Booking Priority</label>
                                        <select class="form-select" id="priority" name="priority">
                                            <option value="low" <?= $editing_reservation && $editing_reservation['priority'] == 'low' ? 'selected' : '' ?>>Low (Routine)</option>
                                            <option value="medium" <?= $editing_reservation && $editing_reservation['priority'] == 'medium' ? 'selected' : '' ?>>Medium (Normal)</option>
                                            <option value="high" <?= $editing_reservation && $editing_reservation['priority'] == 'high' ? 'selected' : '' ?>>High (Important)</option>
                                            <option value="urgent" <?= $editing_reservation && $editing_reservation['priority'] == 'urgent' ? 'selected' : '' ?>>Urgent (Critical)</option>
                                        </select>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="purpose" class="form-label">Booking Purpose *</label>
                                        <textarea class="form-control" id="purpose" name="purpose" rows="3" required placeholder="State the objective of this asset reservation..."><?= $editing_reservation ? escape($editing_reservation['purpose']) : '' ?></textarea>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mt-4">
                                <?php if ($editing_reservation): ?>
                                    <input type="hidden" name="reservation_id" value="<?= $editing_reservation['id'] ?>">
                                    <button type="submit" name="edit_reservation" class="btn btn-primary px-4 shadow-sm">
                                        <i class="fas fa-save me-2"></i> Update Reservation
                                    </button>
                                    <a href="asset_reservation.php" class="btn btn-outline-secondary px-4 ms-2">Cancel Edit</a>
                                <?php else: ?>
                                    <button type="submit" name="request_reservation" class="btn btn-success px-5 shadow-sm">
                                        <i class="fas fa-calendar-check me-2"></i> Submit Request
                                    </button>
                                <?php endif; ?>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Reservation Guidelines</h3>
                    </div>
                    
                    <div class="card-body">
                        <ul class="list-unstyled">
                            <li><i class="fas fa-check text-success"></i> Reservations must be made at least 1 hour in advance</li>
                            <li><i class="fas fa-check text-success"></i> Maximum reservation duration: 8 hours</li>
                            <li><i class="fas fa-check text-success"></i> Conflicting reservations will be blocked</li>
                            <li><i class="fas fa-check text-success"></i> Reservations require approval before use</li>
                            <li><i class="fas fa-check text-success"></i> Late cancellation may affect future bookings</li>
                        </ul>
                        
                        <div class="alert alert-info">
                            <h5><i class="fas fa-info-circle"></i> Current Time</h5>
                            <p><?= date('l, F j, Y g:i A') ?></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Search and Filter Form -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Search & Filter Reservations</h3>
            </div>
            <div class="card-body">
                <form method="GET" class="row g-2 align-items-end nc-animate-in">
                    <div class="col-md-4">
                        <label class="form-label small">Search Requests</label>
                        <input type="text" class="form-control form-control-sm" name="search" placeholder="Asset tag, user, or purpose..." 
                               value="<?= escape($search) ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small">Filter Status</label>
                        <select class="form-select form-select-sm" name="filter_status">
                            <option value="">All Statuses</option>
                            <option value="pending" <?= $filter_status == 'pending' ? 'selected' : '' ?>>Pending Review</option>
                            <option value="approved" <?= $filter_status == 'approved' ? 'selected' : '' ?>>Approved</option>
                            <option value="in_use" <?= $filter_status == 'in_use' ? 'selected' : '' ?>>In Use</option>
                            <option value="completed" <?= $filter_status == 'completed' ? 'selected' : '' ?>>Completed</option>
                            <option value="rejected" <?= $filter_status == 'rejected' ? 'selected' : '' ?>>Rejected</option>
                            <option value="cancelled" <?= $filter_status == 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small">Department Scope</label>
                        <select class="form-select form-select-sm" name="filter_department">
                            <option value="">All Departments</option>
                            <?php foreach ($departments as $dept): ?>
                                <option value="<?= escape($dept) ?>" <?= $filter_department == $dept ? 'selected' : '' ?>>
                                    <?= escape($dept) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2 d-flex">
                        <button type="submit" class="btn btn-sm btn-primary flex-grow-1 me-2 shadow-sm">
                             <i class="fas fa-filter"></i> Apply
                        </button>
                        <a href="asset_reservation.php" class="btn btn-sm btn-outline-secondary shadow-sm">
                            <i class="fas fa-sync-alt"></i>
                        </a>
                    </div>
                </form>
            </div>
        </div>
        
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Reservation Schedule</h3>
            </div>
            
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Asset</th>
                                <th>Requester</th>
                                <th>Time Slot</th>
                                <th>Purpose</th>
                                <th>Department</th>
                                <th>Status</th>
                                <th>Priority</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($reservations as $reservation): 
                                $start_time = new DateTime($reservation['reservation_start_datetime']);
                                $end_time = new DateTime($reservation['reservation_end_datetime']);
                                $duration = $start_time->diff($end_time);
                                
                                $is_past = $end_time < new DateTime();
                                $is_current = $start_time <= new DateTime() && $end_time >= new DateTime();
                                $is_future = $start_time > new DateTime();
                            ?>
                                <tr class="<?= $is_current ? 'text-info font-weight-bold' : ($is_past ? 'text-muted' : '') ?>">
                                    <td>
                                        <code><?= escape($reservation['asset_tag']) ?></code><br>
                                        <small class="text-muted"><?= escape($reservation['asset_name']) ?></small>
                                    </td>
                                    <td>
                                        <?= escape($reservation['requester_name']) ?><br>
                                        <small class="text-muted"><?= escape($reservation['requester_user_id']) ?></small>
                                    </td>
                                    <td>
                                        <div class="small">
                                            <strong><?= $start_time->format('M j, g:i A') ?></strong><br>
                                            to <strong><?= $end_time->format('M j, g:i A') ?></strong><br>
                                            <span class="badge badge-light"><?= $duration->h ?>h <?= $duration->i ?>m</span>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="small">
                                            <?= escape($reservation['purpose']) ?>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge badge-primary"><?= escape($reservation['department']) ?></span>
                                    </td>
                                    <td>
                                        <?php 
                                        $status_badge = [
                                            'pending' => 'warning',
                                            'approved' => 'success',
                                            'in_use' => 'info',
                                            'completed' => 'secondary',
                                            'rejected' => 'danger',
                                            'cancelled' => 'dark'
                                        ][$reservation['status']] ?? 'secondary';
                                        ?>
                                        <span class="badge badge-<?= $status_badge ?>"><?= ucfirst($reservation['status']) ?></span>
                                        <?php if ($is_current): ?>
                                            <span class="badge badge-info">CURRENT</span>
                                        <?php elseif ($is_past): ?>
                                            <span class="badge badge-secondary">PAST</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php 
                                        $priority_badge = [
                                            'low' => 'success',
                                            'medium' => 'info',
                                            'high' => 'warning',
                                            'urgent' => 'danger'
                                        ][$reservation['priority']] ?? 'secondary';
                                        ?>
                                        <span class="badge badge-<?= $priority_badge ?>"><?= ucfirst($reservation['priority']) ?></span>
                                    </td>
                                    <td>
                                        <?php if ($reservation['status'] == 'pending'): ?>
                                            <form method="post" style="display: inline;">
                                                <input type="hidden" name="reservation_id" value="<?= $reservation['id'] ?>">
                                                <button type="submit" name="approve_reservation" class="btn btn-sm btn-success">Approve</button>
                                            </form>
                                            <button type="button" class="btn btn-sm btn-danger" 
                                                    onclick="showRejectModal(<?= $reservation['id'] ?>)">Reject</button>
                                        <?php elseif ($reservation['status'] == 'approved' && !$is_past): ?>
                                            <form method="post" style="display: inline;">
                                                <input type="hidden" name="reservation_id" value="<?= $reservation['id'] ?>">
                                                <button type="submit" name="start_reservation" class="btn btn-sm btn-info">Start</button>
                                            </form>
                                            <form method="post" style="display: inline;">
                                                <input type="hidden" name="reservation_id" value="<?= $reservation['id'] ?>">
                                                <button type="submit" name="cancel_reservation" class="btn btn-sm btn-warning">Cancel</button>
                                            </form>
                                        <?php elseif ($reservation['status'] == 'in_use'): ?>
                                            <form method="post" style="display: inline;">
                                                <input type="hidden" name="reservation_id" value="<?= $reservation['id'] ?>">
                                                <button type="submit" name="complete_reservation" class="btn btn-sm btn-success">Complete</button>
                                            </form>
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
                                <a class="page-link" href="?page=<?= $page - 1 ?>&search=<?= urlencode($search) ?>&filter_status=<?= urlencode($filter_status) ?>&filter_department=<?= urlencode($filter_department) ?>">«</a>
                            </li>
                        <?php endif; ?>
                        
                        <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                            <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                                <a class="page-link" href="?page=<?= $i ?>&search=<?= urlencode($search) ?>&filter_status=<?= urlencode($filter_status) ?>&filter_department=<?= urlencode($filter_department) ?>"><?= $i ?></a>
                            </li>
                        <?php endfor; ?>
                        
                        <?php if ($page < $total_pages): ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=<?= $page + 1 ?>&search=<?= urlencode($search) ?>&filter_status=<?= urlencode($filter_status) ?>&filter_department=<?= urlencode($filter_department) ?>">»</a>
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
                <h5 class="modal-title font-weight-bold" id="rejectModalLabel">Reject Reservation Booking</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="post">
                <div class="modal-body p-4">
                    <input type="hidden" id="reject_reservation_id" name="reservation_id">
                    <div class="mb-3">
                        <label for="rejection_reason" class="form-label">Reason for Rejection *</label>
                        <textarea class="form-control" id="rejection_reason" name="rejection_reason" rows="4" required placeholder="Provide detail on why this booking is being declined..."></textarea>
                    </div>
                </div>
                <div class="modal-footer bg-light border-0">
                    <button type="button" class="btn btn-outline-secondary px-4" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="reject_reservation" class="btn btn-danger px-4 shadow-sm">Reject Request</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function showRejectModal(reservationId) {
    document.getElementById('reject_reservation_id').value = reservationId;
    var rejectModal = new bootstrap.Modal(document.getElementById('rejectModal'));
    rejectModal.show();
}
</script>

<?php
include __DIR__ . '/../../includes/footer.php';
?>