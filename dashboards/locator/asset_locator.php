<?php
// dashboards/locator/asset_locator.php - Premium University Asset Locator & QR System
require_once __DIR__ . '/../../includes/header.php';

$message = '';
$error   = '';
$pdo     = getPDO();

// Handle tracking updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['track_location'])) {
    $asset_id = (int)$_POST['asset_id'];
    $location = trim($_POST['current_location'] ?? '');
    $notes    = trim($_POST['notes']);
    $type     = trim($_POST['scan_type']);

    if ($asset_id && !empty($location)) {
        // Log movement
        $stmt = $pdo->prepare("INSERT INTO asset_location_tracking (asset_id, current_location, scanned_by, scan_type, notes) VALUES (?, ?, ?, ?, ?)");
        if ($stmt->execute([$asset_id, $location, currentUser()['id'], $type, $notes])) {
            // Update main table
            $pdo->prepare("UPDATE assets SET class_location = ? WHERE id = ?")->execute([$location, $asset_id]);
            $message = "Asset location updated to <strong>$location</strong> successfully!";
        }
    } else {
        $error = "Asset and Location are required.";
    }
}

// Data fetching
$user = currentUser();
$where_assets = "WHERE 1=1";
$where_tracks = "WHERE 1=1";
$params_assets = [];
$params_tracks = [];

if ($user['role'] === 'hod') {
    $dept_filter = " AND department IN (SELECT name FROM university_departments WHERE hod_id = ?)";
    $where_assets .= $dept_filter;
    $where_tracks .= " AND a.department IN (SELECT name FROM university_departments WHERE hod_id = ?)";
    $params_assets[] = $user['id'];
    $params_tracks[] = $user['id'];
} elseif ($user['role'] === 'coordinator') {
    $dept_filter = " AND department IN (SELECT name FROM university_departments WHERE coordinator_id = ?)";
    $where_assets .= $dept_filter;
    $where_tracks .= " AND a.department IN (SELECT name FROM university_departments WHERE coordinator_id = ?)";
    $params_assets[] = $user['id'];
    $params_tracks[] = $user['id'];
}

// Fetch Master Locations
$locations_list = $pdo->query("SELECT id, location_name, building, room_number FROM locations ORDER BY location_name ASC")->fetchAll();

// Search and Pagination
$search = trim($_GET['search'] ?? '');
$limit = 10;
$page = max(1, (int)($_GET['page'] ?? 1));
$offset = ($page - 1) * $limit;

if (!empty($search)) {
    $where_tracks .= " AND (a.asset_tag LIKE ? OR a.name LIKE ? OR alt.current_location LIKE ?)";
    $search_param = '%' . $search . '%';
    $params_tracks[] = $search_param;
    $params_tracks[] = $search_param;
    $params_tracks[] = $search_param;
}

// Count total records for pagination
$count_stmt = $pdo->prepare("SELECT COUNT(*) FROM asset_location_tracking alt LEFT JOIN assets a ON alt.asset_id = a.id $where_tracks");
$count_stmt->execute($params_tracks);
$total_records = $count_stmt->fetchColumn();
$total_pages = ceil($total_records / $limit);

$assets = $pdo->prepare("SELECT id, asset_tag, name, serial_number, department, class_location FROM assets $where_assets ORDER BY asset_tag ASC");
$assets->execute($params_assets);
$assets = $assets->fetchAll();

$tracks = $pdo->prepare("
    SELECT alt.*, a.asset_tag, a.name as asset_name, u.name as user_name
    FROM asset_location_tracking alt
    LEFT JOIN assets a ON alt.asset_id = a.id
    LEFT JOIN users u ON alt.scanned_by = u.id
    $where_tracks
    ORDER BY alt.scan_date DESC LIMIT ? OFFSET ?
");
$param_index = 1;
foreach ($params_tracks as $p) {
    $tracks->bindValue($param_index++, $p);
}
$tracks->bindValue($param_index++, $limit, PDO::PARAM_INT);
$tracks->bindValue($param_index++, $offset, PDO::PARAM_INT);
$tracks->execute();
$tracking_list = $tracks->fetchAll();
?>

<!-- Include HTML5-QRCode Library -->
<script src="https://unpkg.com/html5-qrcode"></script>

<!-- Select2 CSS/JS -->
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" />
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>

<style>
    #reader {
        width: 100%;
        background: #000;
        border-radius: 8px;
        overflow: hidden;
        margin-bottom: 1rem;
    }
    #reader__dashboard_section_csr button {
        background-color: #007bff !important;
        border: none !important;
        color: white !important;
        padding: 5px 15px !important;
        border-radius: 4px !important;
    }
    .scanner-active {
        border: 3px solid #007bff;
        box-shadow: 0 0 15px rgba(0,123,255,0.5);
    }
</style>

<div class="row">
    <!-- LEFT: SCAN & TRACK -->
    <div class="col-md-5">
        <div class="card card-outline card-primary shadow-sm mb-4">
            <div class="card-header border-bottom">
                <h3 class="card-title font-weight-bold"><i class="fas fa-qrcode text-primary mr-1"></i> QR Scanner & Location</h3>
            </div>
            <div class="card-body">
                <?php if($message): ?><div class="alert alert-success alert-dismissible"><button type="button" class="close" data-dismiss="alert">&times;</button><?= $message ?></div><?php endif; ?>
                <?php if($error): ?><div class="alert alert-danger alert-dismissible"><button type="button" class="close" data-dismiss="alert">&times;</button><?= $error ?></div><?php endif; ?>

                <div id="reader" class="mb-4"></div>
                <div class="text-center mb-4">
                    <button class="btn btn-outline-primary btn-sm px-4" id="btnStartScan"><i class="fas fa-camera mr-2"></i> Start QR Scanner</button>
                    <button class="btn btn-outline-danger btn-sm px-4" id="btnStopScan" style="display:none;"><i class="fas fa-stop mr-2"></i> Stop Scanner</button>
                    <div id="scanFeedback" class="mt-2 text-success font-weight-bold" style="display:none;"><i class="fas fa-check-circle"></i> Asset Tag Scanned!</div>
                </div>

                <form method="post">
                    <div class="mb-3">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <label class="form-label mb-0">Target Asset <span class="text-danger">*</span></label>
                            <button type="button" class="btn btn-link btn-xs p-0 text-primary font-weight-bold" id="btnTestQR" style="display:none; text-decoration: none;">
                                <i class="fas fa-qrcode mr-1"></i> Show Test QR
                            </button>
                        </div>
                        <select name="asset_id" id="asset_id" class="form-select select2" required>
                            <option value="">— Select or Scan Asset —</option>
                            <?php foreach($assets as $a): ?>
                                <option value="<?= $a['id'] ?>" data-tag="<?= escape($a['asset_tag']) ?>">
                                    <?= escape($a['asset_tag']) ?>: <?= escape($a['name']) ?> <?= $a['department'] ? '('.escape($a['department']).')' : '(Unallocated)' ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Test QR Container (Hidden initially) -->
                    <div id="testQRContainer" class="text-center mt-3 p-3 bg-light border rounded shadow-sm" style="display:none;">
                        <p class="small text-muted mb-2">Scan this with your phone to test the locator</p>
                        <div id="testQRDisplay" class="d-inline-block bg-white p-2"></div>
                        <div class="mt-2"><button type="button" class="btn btn-xs btn-secondary" onclick="$('#testQRContainer').hide()">Close</button></div>
                    </div>

                    <div class="row g-3">
                        <div class="col-6">
                            <div class="mb-3">
                                <label class="form-label">Move Type</label>
                                <select name="scan_type" class="form-select">
                                    <option value="verification">Verification</option>
                                    <option value="relocate">Relocation</option>
                                    <option value="check_in">Check-In</option>
                                    <option value="check_out">Check-Out</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="mb-3">
                                <label class="form-label">New Location <span class="text-danger">*</span></label>
                                <select name="current_location" class="form-select select2" required>
                                    <option value="">— Select Location —</option>
                                    <?php foreach($locations_list as $loc): ?>
                                        <option value="<?= escape($loc['location_name']) ?>">
                                            <?= escape($loc['location_name']) ?> (<?= escape($loc['building']) ?>, Room <?= escape($loc['room_number']) ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Security/Asset Notes</label>
                        <textarea name="notes" rows="2" class="form-control" placeholder="Add condition or reason for move"></textarea>
                    </div>

                    <button type="submit" name="track_location" class="btn btn-primary btn-block shadow-sm">
                        <i class="fas fa-map-marker-alt mr-1"></i> Update Location Record
                    </button>
                </form>
            </div>
        </div>

        <div class="card card-outline card-info shadow-sm">
            <div class="card-header border-bottom"><h3 class="card-title font-weight-bold">Inventory Sessions</h3></div>
            <div class="card-body">
                <div class="d-flex align-items-center mb-3">
                    <div class="input-group">
                        <input type="text" class="form-control" placeholder="Annual Audit 2024">
                        <div class="input-group-append"><button class="btn btn-info shadow-sm">Start Session</button></div>
                    </div>
                </div>
                <p class="text-muted small">Sessions allow bulk scanning and reporting for department audits.</p>
            </div>
        </div>
    </div>

    <!-- RIGHT: RECENT HISTORY -->
    <div class="col-md-7">
        <div class="card card-outline card-info shadow-sm">
            <div class="card-header bg-white border-bottom">
                <h3 class="card-title font-weight-bold"><i class="fas fa-history text-info mr-1"></i> Recent Movement History</h3>
                <div class="card-tools">
                    <form method="get" class="input-group input-group-sm" style="width: 250px;">
                        <input type="text" name="search" class="form-control" placeholder="Search Tag/Room..." value="<?= escape($search) ?>">
                        <div class="input-group-append">
                            <button type="submit" class="btn btn-default"><i class="fas fa-search"></i></button>
                            <?php if (!empty($search)): ?>
                                <a href="asset_locator.php" class="btn btn-default" title="Clear"><i class="fas fa-times"></i></a>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover table-striped mb-0">
                        <thead>
                            <tr class="bg-light">
                                <th>Asset Tracker</th>
                                <th>Location</th>
                                <th>Log Details</th>
                                <th>By</th>
                                <th>Timestamp</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($tracking_list as $row): 
                                $badge = ['check_in' => 'success', 'check_out' => 'danger', 'relocate' => 'info', 'verification' => 'primary'][$row['scan_type']] ?? 'secondary';
                            ?>
                            <tr>
                                <td>
                                    <strong><?= escape($row['asset_tag']) ?></strong><br>
                                    <small class="text-muted"><?= escape($row['asset_name']) ?></small>
                                </td>
                                <td><span class="badge badge-light border"><i class="fas fa-map-pin mr-1"></i> <?= escape($row['current_location']) ?></span></td>
                                <td>
                                    <span class="badge badge-<?= $badge ?> text-xs text-uppercase mb-1"><?= escape($row['scan_type']) ?></span><br>
                                    <small class="text-muted d-block"><?= escape($row['notes']) ?></small>
                                </td>
                                <td><small class="text-primary font-weight-bold"><?= escape($row['user_name']) ?></small></td>
                                <td><small><?= date('M j, g:i A', strtotime($row['scan_date'])) ?></small></td>
                                <td>
                                    <button class="btn btn-xs btn-outline-info shadow-sm" title="Verify this asset again" 
                                            onclick="quickVerify(<?= $row['asset_id'] ?>, '<?= escape($row['current_location']) ?>')">
                                        <i class="fas fa-check"></i> Verify
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="card-footer border-top">
                <div class="d-flex justify-content-between align-items-center">
                    <span class="text-sm text-muted">Showing <?= count($tracking_list) ?> of <?= $total_records ?> logs</span>
                    <?php if ($total_pages > 1): ?>
                    <ul class="pagination pagination-sm m-0">
                        <?php if ($page > 1): ?>
                            <li class="page-item"><a class="page-link" href="?page=<?= $page - 1 ?>&search=<?= urlencode($search) ?>">«</a></li>
                        <?php endif; ?>
                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                            <li class="page-item <?= $i == $page ? 'active' : '' ?>"><a class="page-link" href="?page=<?= $i ?>&search=<?= urlencode($search) ?>"><?= $i ?></a></li>
                        <?php endfor; ?>
                        <?php if ($page < $total_pages): ?>
                            <li class="page-item"><a class="page-link" href="?page=<?= $page + 1 ?>&search=<?= urlencode($search) ?>">»</a></li>
                        <?php endif; ?>
                    </ul>
                    <?php endif; ?>
                </div>
                <div class="text-center mt-2">
                    <a href="#" class="text-sm font-weight-bold">View Full Activity Log <i class="fas fa-arrow-right ml-1"></i></a>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
let html5QrCode;

function onScanSuccess(decodedText, decodedResult) {
    console.log(`Code matched = ${decodedText}`, decodedResult);
    
    // Stop scanning
    stopScanner();

    // Feedback
    document.getElementById('scanFeedback').style.display = 'block';
    
    // Logic: Find the asset in the select2 dropdown by its tag
    const assetSelect = $('#asset_id');
    let found = false;
    
    assetSelect.find('option').each(function() {
        if ($(this).data('tag') === decodedText || $(this).val() === decodedText) {
            assetSelect.val($(this).val()).trigger('change');
            found = true;
        }
    });

    if (!found) {
        alert("Asset with tag " + decodedText + " not found or you don't have access to it.");
    }
}

function startScanner() {
    document.getElementById('reader').classList.add('scanner-active');
    document.getElementById('btnStartScan').style.display = 'none';
    document.getElementById('btnStopScan').style.display = 'inline-block';
    document.getElementById('scanFeedback').style.display = 'none';

    html5QrCode = new Html5Qrcode("reader");
    html5QrCode.start(
        { facingMode: "environment" }, 
        {
            fps: 10,
            qrbox: { width: 250, height: 250 }
        },
        onScanSuccess
    ).catch(err => {
        console.error("Unable to start scanning.", err);
        alert("Camera error: " + err);
    });
}

function stopScanner() {
    if (html5QrCode) {
        html5QrCode.stop().then(() => {
            document.getElementById('reader').classList.remove('scanner-active');
            document.getElementById('btnStartScan').style.display = 'inline-block';
            document.getElementById('btnStopScan').style.display = 'none';
        }).catch(err => console.error("Error stopping scanner", err));
    }
}

document.getElementById('btnStartScan').onclick = startScanner;
document.getElementById('btnStopScan').onclick = stopScanner;

$(document).ready(function() {
    $('.select2').select2({
        theme: 'bootstrap-5',
        width: '100%'
    });
});

function quickVerify(id, location) {
    // Select asset
    $('#asset_id').val(id).trigger('change');
    
    // Select location
    $('select[name="current_location"]').val(location).trigger('change');
    
    // Set type to verification
    $('select[name="scan_type"]').val('verification');
    
    // Scroll to top slowly
    $("html, body").animate({ scrollTop: 0 }, "slow");
    
    // Pulse the form
    $('.card-primary').fadeOut(100).fadeIn(100);
}

// TEST QR LOGIC
let testQRHelper;
$('#asset_id').on('change', function() {
    const val = $(this).val();
    if (val) {
        $('#btnTestQR').show();
    } else {
        $('#btnTestQR').hide();
        $('#testQRContainer').hide();
    }
});

document.getElementById('btnTestQR').onclick = function() {
    const assetSelect = $('#asset_id');
    const selectedOption = assetSelect.find('option:selected');
    const tag = selectedOption.data('tag');
    
    if (!tag) return;
    
    $('#testQRContainer').show();
    document.getElementById('testQRDisplay').innerHTML = '';
    
    testQRHelper = new QRCode(document.getElementById("testQRDisplay"), {
        text: tag,
        width: 150,
        height: 150
    });
};
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>