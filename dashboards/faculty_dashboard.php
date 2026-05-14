<?php
// dashboards/faculty_dashboard.php - Faculty/Staff Dashboard
require_once __DIR__ . '/../includes/header.php';

$pdo = getPDO();
$user = currentUser();

// Fetch faculty-specific data
$stats = [];

// Assets assigned to user
$stmt = $pdo->prepare("SELECT COUNT(*) as count FROM assets WHERE assigned_to_user_id = ?");
$stmt->execute([$user['id']]);
$stats['assigned_assets'] = $stmt->fetch()['count'];

// Pending reservations for user
$stmt = $pdo->prepare("SELECT COUNT(*) as count FROM asset_reservations WHERE requester_user_id = ? AND status IN ('pending', 'approved')");
$stmt->execute([$user['id']]);
$stats['pending_reservations'] = $stmt->fetch()['count'];

// Approved reservations
$stmt = $pdo->prepare("SELECT COUNT(*) as count FROM asset_reservations WHERE requester_user_id = ? AND status = 'approved'");
$stmt->execute([$user['id']]);
$stats['approved_reservations'] = $stmt->fetch()['count'];

// Recent reservations
$stmt = $pdo->prepare("
    SELECT ar.*, a.asset_tag, a.name as asset_name
    FROM asset_reservations ar
    LEFT JOIN assets a ON ar.asset_id = a.id
    WHERE ar.requester_user_id = ?
    ORDER BY ar.created_at DESC
    LIMIT 5
");
$stmt->execute([$user['id']]);
$user_reservations = $stmt->fetchAll();
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-lg-3 col-6">
            <div class="info-box shadow-sm">
                <span class="info-box-icon bg-info elevation-1"><i class="fas fa-box"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text text-muted text-uppercase text-xs font-weight-bold">Your Assets</span>
                    <span class="info-box-number h4 font-weight-bold mb-0"><?= number_format($stats['assigned_assets']) ?></span>
                </div>
            </div>
        </div>
        
        <div class="col-lg-3 col-6">
            <div class="info-box shadow-sm">
                <span class="info-box-icon bg-warning elevation-1"><i class="fas fa-calendar-check"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text text-muted text-uppercase text-xs font-weight-bold">Pending Reservations</span>
                    <span class="info-box-number h4 font-weight-bold mb-0"><?= $stats['pending_reservations'] ?></span>
                </div>
            </div>
        </div>
        
        <div class="col-lg-3 col-6">
            <div class="info-box shadow-sm">
                <span class="info-box-icon bg-success elevation-1"><i class="fas fa-check-circle"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text text-muted text-uppercase text-xs font-weight-bold">Approved Reservations</span>
                    <span class="info-box-number h4 font-weight-bold mb-0"><?= $stats['approved_reservations'] ?></span>
                </div>
            </div>
        </div>
        
        <div class="col-lg-3 col-6">
            <div class="info-box shadow-sm">
                <span class="info-box-icon bg-primary elevation-1"><i class="fas fa-calendar"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text text-muted text-uppercase text-xs font-weight-bold">Current Month</span>
                    <span class="info-box-number h4 font-weight-bold mb-0"><?= date('M') ?></span>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Your Recent Reservations</h3>
                </div>
                <div class="card-body p-0">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Asset</th>
                                <th>Time Slot</th>
                                <th>Status</th>
                                <th>Purpose</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($user_reservations as $res): 
                                $start_time = new DateTime($res['reservation_start_datetime']);
                                $end_time = new DateTime($res['reservation_end_datetime']);
                            ?>
                                <tr>
                                    <td>
                                        <code><?= escape($res['asset_tag']) ?></code><br>
                                        <small class="text-muted"><?= escape($res['asset_name']) ?></small>
                                    </td>
                                    <td>
                                        <?= $start_time->format('M j, g:i A') ?> - <?= $end_time->format('g:i A') ?>
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
                                        ][$res['status']] ?? 'secondary';
                                        ?>
                                        <span class="badge badge-<?= $status_badge ?>"><?= ucfirst($res['status']) ?></span>
                                    </td>
                                    <td><?= escape(substr($res['purpose'], 0, 30)) ?>...</td>
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
                        <a href="<?= BASE_URL ?>/dashboards/reservation/asset_reservation.php" class="btn btn-primary">
                            <i class="fas fa-calendar-plus"></i> Reserve Asset
                        </a>
                        <a href="<?= BASE_URL ?>/dashboards/university/asset_allocation.php?user=<?= $user['id'] ?>" class="btn btn-info">
                            <i class="fas fa-user-tag"></i> My Assets
                        </a>
                        <a href="<?= BASE_URL ?>/dashboards/inventory/assets.php?assigned_to=<?= $user['id'] ?>" class="btn btn-success">
                            <i class="fas fa-box"></i> View Assets
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