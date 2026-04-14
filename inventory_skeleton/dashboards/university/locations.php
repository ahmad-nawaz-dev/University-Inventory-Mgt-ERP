<?php
// dashboards/university/locations.php - University Location Management
require_once __DIR__ . '/../../includes/header.php';

$message = '';
$error = '';
$pdo = getPDO();

// Add or edit location
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_location']) || isset($_POST['edit_location'])) {
        $name = trim($_POST['location_name'] ?? '');
        $building = trim($_POST['building'] ?? '');
        $floor = trim($_POST['floor'] ?? '');
        $room_number = trim($_POST['room_number'] ?? '');
        $type = trim($_POST['location_type'] ?? 'classroom');
        $capacity = (int)($_POST['capacity'] ?? 0);
        $contact_id = (int)($_POST['contact_person_id'] ?? 0);
        $actual_contact_id = ($contact_id > 0) ? $contact_id : null;

        if (empty($name)) {
            $error = 'Location name is required.';
        } else {
            if (isset($_POST['edit_location']) && !empty($_POST['location_id'])) {
                $loc_id = (int)$_POST['location_id'];
                $stmt = $pdo->prepare("UPDATE locations SET location_name=?, building=?, floor=?, room_number=?, location_type=?, capacity=?, contact_person_id=? WHERE id=?");
                if ($stmt->execute([$name, $building, $floor, $room_number, $type, $capacity, $actual_contact_id, $loc_id])) {
                    $message = 'Location updated successfully!';
                } else {
                    $error = 'Error updating location.';
                }
            } else {
                // Check if location already exists
                $stmt = $pdo->prepare("SELECT id FROM locations WHERE location_name = ?");
                $stmt->execute([$name]);
                if ($stmt->fetch()) {
                    $error = 'A location with this name already exists.';
                } else {
                    $stmt = $pdo->prepare("INSERT INTO locations (location_name, building, floor, room_number, location_type, capacity, contact_person_id) VALUES (?, ?, ?, ?, ?, ?, ?)");
                    if ($stmt->execute([$name, $building, $floor, $room_number, $type, $capacity, $actual_contact_id])) {
                        $message = 'Location added successfully!';
                    } else {
                        $error = 'Error adding location.';
                    }
                }
            }
        }
    }
    
    // Delete location
    if (isset($_POST['delete_location']) && !empty($_POST['delete_id'])) {
        $delete_id = (int)$_POST['delete_id'];
        
        // Check if location has associated assets
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM assets WHERE class_location = (SELECT location_name FROM locations WHERE id = ?)");
        $stmt->execute([$delete_id]);
        $asset_count = $stmt->fetchColumn();
        
        if ($asset_count > 0) {
            $error = 'Cannot delete location. It has ' . $asset_count . ' associated assets.';
        } else {
            $stmt = $pdo->prepare("DELETE FROM locations WHERE id = ?");
            if ($stmt->execute([$delete_id])) {
                $message = 'Location deleted successfully!';
            } else {
                $error = 'Error deleting location.';
            }
        }
    }
}

// Fetch all locations
$stmt = $pdo->query("SELECT l.*, u.name as contact_name FROM locations l LEFT JOIN users u ON l.contact_person_id = u.id ORDER BY l.building, l.location_name ASC");
$locations = $stmt->fetchAll();

$users = $pdo->query("SELECT id, name FROM users WHERE is_active = 1 ORDER BY name ASC")->fetchAll();

// Editing existing location
$editing_loc = null;
if (isset($_GET['edit']) && !empty($_GET['edit'])) {
    $edit_id = (int)$_GET['edit'];
    $stmt = $pdo->prepare("SELECT * FROM locations WHERE id = ?");
    $stmt->execute([$edit_id]);
    $editing_loc = $stmt->fetch();
}
?>

<div class="row">
    <div class="col-md-12">
        <?php if ($message): ?>
            <div class="alert alert-success alert-dismissible"><button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button><?= escape($message) ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible"><button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button><?= escape($error) ?></div>
        <?php endif; ?>
        

        
        <div class="card card-outline card-info shadow-sm">
            <div class="card-header bg-white border-bottom">
                <div class="d-flex justify-content-between align-items-center">
                    <h3 class="card-title font-weight-bold m-0 text-dark">
                        <i class="fas fa-list text-info mr-1"></i> Existing University Locations
                    </h3>
                    <div class="card-tools m-0">
                        <button type="button" class="btn btn-sm btn-success shadow-sm" data-bs-toggle="modal" data-bs-target="#locationModal">
                            <i class="fas fa-plus-circle mr-1"></i> Add New Location
                        </button>
                    </div>
                </div>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover table-striped mb-0">
                        <thead class="bg-light">
                            <tr>
                                <th>Location Name</th>
                                <th>Building / Floor</th>
                                <th>Room #</th>
                                <th>Type</th>
                                <th>In-Charge</th>
                                <th class="text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($locations)): ?>
                                <tr><td colspan="6" class="text-center py-5 text-muted">No locations defined yet. Add your first room or lab above!</td></tr>
                            <?php else: ?>
                                <?php foreach ($locations as $loc): ?>
                                    <tr>
                                        <td><strong><?= escape($loc['location_name']) ?></strong></td>
                                        <td><?= escape($loc['building']) ?: '—' ?> <small class="text-muted"><?= $loc['floor'] ? '('.escape($loc['floor']).')' : '' ?></small></td>
                                        <td><span class="badge badge-light border"><?= escape($loc['room_number']) ?: '—' ?></span></td>
                                        <td><span class="badge badge-info text-uppercase font-weight-normal"><?= str_replace('_', ' ', $loc['location_type']) ?></span></td>
                                        <td><small class="font-weight-bold text-primary"><?= escape($loc['contact_name']) ?: '—' ?></small></td>
                                        <td class="text-right">
                                            <a href="?edit=<?= $loc['id'] ?>" class="btn btn-xs btn-primary shadow-sm"><i class="fas fa-edit"></i></a>
                                            <button onclick="confirmDelete(<?= $loc['id'] ?>)" class="btn btn-xs btn-danger shadow-sm"><i class="fas fa-trash"></i></button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- LOCATION REGISTRATION MODAL -->
<div class="modal fade" id="locationModal" tabindex="-1" aria-labelledby="locationModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="locationModalLabel">
                    <i class="fas fa-<?= $editing_loc ? 'edit' : 'plus-circle' ?> mr-2"></i>
                    <?= $editing_loc ? 'Update Location' : 'Add New University Room/Lab' ?>
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="post">
                <div class="modal-body">
                    <?php if ($editing_loc): ?>
                        <input type="hidden" name="location_id" value="<?= $editing_loc['id'] ?>">
                    <?php endif; ?>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Location Name * (e.g. Lab 101)</label>
                                <input type="text" class="form-control" name="location_name" value="<?= $editing_loc ? escape($editing_loc['location_name']) : '' ?>" required placeholder="e.g. Computer Science Lab 1">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Building</label>
                                <input type="text" class="form-control" name="building" value="<?= $editing_loc ? escape($editing_loc['building']) : '' ?>" placeholder="e.g. Faculty Block A">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Location Type</label>
                                <select class="form-select" name="location_type">
                                    <?php 
                                    $types = ['classroom', 'lab', 'office', 'central_store', 'workshop', 'library'];
                                    foreach($types as $t): ?>
                                        <option value="<?= $t ?>" <?= ($editing_loc && $editing_loc['location_type'] == $t) ? 'selected' : '' ?>><?= ucfirst(str_replace('_', ' ', $t)) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Floor</label>
                                <input type="text" class="form-control" name="floor" value="<?= $editing_loc ? escape($editing_loc['floor']) : '' ?>" placeholder="e.g. 1st Floor">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">Room Number</label>
                                <input type="text" class="form-control" name="room_number" value="<?= $editing_loc ? escape($editing_loc['room_number']) : '' ?>" placeholder="e.g. R-101">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">Capacity (Persons)</label>
                                <input type="number" class="form-control" name="capacity" value="<?= $editing_loc ? escape($editing_loc['capacity']) : '' ?>" placeholder="0">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">In-Charge / Contact</label>
                                <select class="form-select select2-modal" name="contact_person_id" style="width: 100%;">
                                    <option value="0">— Select Personnel —</option>
                                    <?php foreach($users as $u): ?>
                                        <option value="<?= $u['id'] ?>" <?= ($editing_loc && $editing_loc['contact_person_id'] == $u['id']) ? 'selected' : '' ?>><?= escape($u['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer justify-content-between">
                    <button type="button" class="btn btn-default" data-bs-dismiss="modal">Close</button>
                    <div>
                        <?php if ($editing_loc): ?>
                            <a href="locations.php" class="btn btn-secondary mr-2">Cancel Edit</a>
                        <?php endif; ?>
                        <button type="submit" name="<?= $editing_loc ? 'edit_location' : 'add_location' ?>" class="btn btn-primary px-4 shadow-sm">
                            <i class="fas fa-save mr-1"></i> <?= $editing_loc ? 'Update Location' : 'Save Location' ?>
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function confirmDelete(id) {
    if (confirm('Are you sure you want to delete this location? Assets currently assigned to this room will lose their location link.')) {
        const f = document.createElement('form');
        f.method = 'post';
        f.innerHTML = `<input type="hidden" name="delete_id" value="${id}"><input type="hidden" name="delete_location" value="1">`;
        document.body.appendChild(f);
        f.submit();
    }
}

// Auto-open location modal for editing or errors
document.addEventListener("DOMContentLoaded", function() {
    <?php if ($editing_loc || $error): ?>
        var locationModal = new bootstrap.Modal(document.getElementById('locationModal'));
        locationModal.show();
    <?php endif; ?>
});

$(document).ready(function() {
    $('.select2-modal').select2({ 
        dropdownParent: $('#locationModal'),
        theme: 'bootstrap-4', 
        width: '100%' 
    });
});
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
