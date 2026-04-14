<?php
// dashboards/university/departments.php - Corrected version
require_once __DIR__ . '/../../includes/header.php';

$message = '';
$error = '';

// Add or edit department
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_department']) || isset($_POST['edit_department'])) {
        $name = trim($_POST['name'] ?? '');
        $faculty = trim($_POST['faculty'] ?? '');
        $hod_id = (int)($_POST['hod_id'] ?? 0);
        $coordinator_id = (int)($_POST['coordinator_id'] ?? 0);
        $clerk_id = (int)($_POST['clerk_id'] ?? 0);
        $store_officer_id = (int)($_POST['store_officer_id'] ?? 0);
        $contact_email = trim($_POST['contact_email'] ?? '');
        
        if (empty($name)) {
            $error = 'Department name is required.';
        } else {
            $pdo = getPDO();
            
            // Helper function to create/update staff accounts
            $processStaff = function($name, $email, $pass, $role, $deptName) use ($pdo) {
                if (empty($email)) return null;
                $stmt = $pdo->prepare("SELECT id, role FROM users WHERE email = ?");
                $stmt->execute([$email]);
                $user = $stmt->fetch();
                if ($user) {
                    if ($user['role'] === 'super_admin') return $user['id']; // Don't demote super admins
                    $user_id = $user['id'];
                    $sql = "UPDATE users SET name = ?, role = ?, department = ?, is_active = 1 WHERE id = ?";
                    $params = [$name, $role, $deptName, $user_id];
                    if (!empty($pass)) {
                        $sql = "UPDATE users SET name = ?, role = ?, department = ?, password = ?, is_active = 1 WHERE id = ?";
                        $params = [$name, $role, $deptName, password_hash($pass, PASSWORD_BCRYPT), $user_id];
                    }
                    $pdo->prepare($sql)->execute($params);
                    return $user_id;
                } else {
                    $pass = !empty($pass) ? $pass : '123456'; // Default password
                    $stmt = $pdo->prepare("INSERT INTO users (name, email, password, role, department, is_active) VALUES (?, ?, ?, ?, ?, 1)");
                    $stmt->execute([$name, $email, password_hash($pass, PASSWORD_BCRYPT), $role, $deptName]);
                    return $pdo->lastInsertId();
                }
            };

            if (isset($_POST['edit_department']) && !empty($_POST['department_id'])) {
                $dept_id = (int)$_POST['department_id'];
                
                // Get old name for syncing
                $old_name_stmt = $pdo->prepare("SELECT name FROM university_departments WHERE id = ?");
                $old_name_stmt->execute([$dept_id]);
                $old_name = $old_name_stmt->fetchColumn();

                // Process staff
                $hod_id = $processStaff($_POST['hod_name'], $_POST['hod_email'], $_POST['hod_pass'], 'hod', $name);
                $coordinator_id = $processStaff($_POST['coord_name'], $_POST['coord_email'], $_POST['coord_pass'], 'coordinator', $name);
                $clerk_id = $processStaff($_POST['clerk_name'], $_POST['clerk_email'], $_POST['clerk_pass'], 'clerk', $name);
                $store_officer_id = $processStaff($_POST['store_name'], $_POST['store_email'], $_POST['store_pass'], 'store_officer', $name);

                // Update existing department
                $stmt = $pdo->prepare("UPDATE university_departments SET name=?, faculty=?, hod_id=?, coordinator_id=?, clerk_id=?, store_officer_id=?, contact_email=? WHERE id=?");
                if ($stmt->execute([$name, $faculty, $hod_id, $coordinator_id, $clerk_id, $store_officer_id, $contact_email, $dept_id])) {
                    // Sync name changes to users and assets if renamed
                    if ($old_name && $old_name !== $name) {
                        $pdo->prepare("UPDATE users SET department = ? WHERE department = ?")->execute([$name, $old_name]);
                        $pdo->prepare("UPDATE assets SET department = ? WHERE department = ?")->execute([$name, $old_name]);
                    }
                    
                    // Handle Faculty Members
                    if (isset($_POST['faculty_names']) && is_array($_POST['faculty_names'])) {
                        foreach ($_POST['faculty_names'] as $idx => $fName) {
                            $fEmail = $_POST['faculty_emails'][$idx] ?? '';
                            $fPass = $_POST['faculty_passes'][$idx] ?? '';
                            if (!empty($fName) && !empty($fEmail)) {
                                $processStaff($fName, $fEmail, $fPass, 'faculty', $name);
                            }
                        }
                    }

                    $message = 'Department and staff updated successfully!';
                } else {
                    $error = 'Error updating department.';
                }
            } else {
                // Check if department already exists
                $stmt = $pdo->prepare("SELECT id FROM university_departments WHERE name = ?");
                $stmt->execute([$name]);
                if ($stmt->fetch()) {
                    $error = 'A department with this name already exists.';
                } else {
                    // Process staff for new department
                    $hod_id = $processStaff($_POST['hod_name'], $_POST['hod_email'], $_POST['hod_pass'], 'hod', $name);
                    $coordinator_id = $processStaff($_POST['coord_name'], $_POST['coord_email'], $_POST['coord_pass'], 'coordinator', $name);
                    $clerk_id = $processStaff($_POST['clerk_name'], $_POST['clerk_email'], $_POST['clerk_pass'], 'clerk', $name);
                    $store_officer_id = $processStaff($_POST['store_name'], $_POST['store_email'], $_POST['store_pass'], 'store_officer', $name);

                    // Insert new department
                    $stmt = $pdo->prepare("INSERT INTO university_departments (name, faculty, hod_id, coordinator_id, clerk_id, store_officer_id, contact_email) VALUES (?, ?, ?, ?, ?, ?, ?)");
                    if ($stmt->execute([$name, $faculty, $hod_id, $coordinator_id, $clerk_id, $store_officer_id, $contact_email])) {
                        
                        // Handle Faculty Members
                        if (isset($_POST['faculty_names']) && is_array($_POST['faculty_names'])) {
                            foreach ($_POST['faculty_names'] as $idx => $fName) {
                                $fEmail = $_POST['faculty_emails'][$idx] ?? '';
                                $fPass = $_POST['faculty_passes'][$idx] ?? '';
                                if (!empty($fName) && !empty($fEmail)) {
                                    $processStaff($fName, $fEmail, $fPass, 'faculty', $name);
                                }
                            }
                        }

                        $message = 'Department and staff accounts created successfully!';
                    } else {
                        $error = 'Error adding department.';
                    }
                }
            }
        }
    }
    
    // Delete department
    if (isset($_POST['delete_department']) && !empty($_POST['delete_id'])) {
        $pdo = getPDO();
        $delete_id = (int)$_POST['delete_id'];
        
        // Check if department has associated assets
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM assets WHERE department = (SELECT name FROM university_departments WHERE id = ?)");
        $stmt->execute([$delete_id]);
        $asset_count = $stmt->fetchColumn();
        
        if ($asset_count > 0) {
            $error = 'Cannot delete department. It has ' . $asset_count . ' associated assets.';
        } else {
            $stmt = $pdo->prepare("DELETE FROM university_departments WHERE id = ?");
            if ($stmt->execute([$delete_id])) {
                $message = 'Department deleted successfully!';
            } else {
                $error = 'Error deleting department.';
            }
        }
    }
}

// Fetch all departments
$pdo = getPDO();
$stmt = $pdo->query("
    SELECT ud.*, u.name as hod_name, uc.name as coordinator_name, 
           ucl.name as clerk_name, uso.name as store_officer_name 
    FROM university_departments ud 
    LEFT JOIN users u ON ud.hod_id = u.id 
    LEFT JOIN users uc ON ud.coordinator_id = uc.id 
    LEFT JOIN users ucl ON ud.clerk_id = ucl.id 
    LEFT JOIN users uso ON ud.store_officer_id = uso.id 
    ORDER BY ud.name ASC");
$departments = $stmt->fetchAll();

// Fetch all users for reference (if needed, but mostly we use direct creation now)
$users = $pdo->query("SELECT id, name, role FROM users WHERE is_active = 1 ORDER BY name ASC")->fetchAll();

// Editing existing department
$editing_department = null;
$staff = ['hod' => null, 'coord' => null, 'clerk' => null, 'store' => null];
$editing_faculty = [];

if (isset($_GET['edit']) && !empty($_GET['edit'])) {
    $edit_id = (int)$_GET['edit'];
    $stmt = $pdo->prepare("SELECT * FROM university_departments WHERE id = ?");
    $stmt->execute([$edit_id]);
    $editing_department = $stmt->fetch();
    
    if ($editing_department) {
        // Fetch specific staff details
        $roles_to_fetch = [
            'hod' => $editing_department['hod_id'],
            'coord' => $editing_department['coordinator_id'],
            'clerk' => $editing_department['clerk_id'],
            'store' => $editing_department['store_officer_id']
        ];
        foreach ($roles_to_fetch as $key => $uid) {
            if ($uid) {
                $u_stmt = $pdo->prepare("SELECT name, email FROM users WHERE id = ?");
                $u_stmt->execute([$uid]);
                $staff[$key] = $u_stmt->fetch();
            }
        }
        
        // Fetch Faculty members
        $f_stmt = $pdo->prepare("SELECT name, email FROM users WHERE department = ? AND role = 'faculty' AND is_active = 1");
        $f_stmt->execute([$editing_department['name']]);
        $editing_faculty = $f_stmt->fetchAll();
    }
}
?>

<div class="row">
    <div class="col-md-12">
        <?php if ($message): ?>
            <div class="alert alert-success"><?= escape($message) ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible"><button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button><?= escape($error) ?></div>
        <?php endif; ?>
        

        
        <div class="card shadow-sm">
            <div class="card-header bg-white">
                <div class="d-flex justify-content-between align-items-center">
                    <h3 class="card-title font-weight-bold m-0 text-dark">
                        <i class="fas fa-university text-primary mr-1"></i> Existing Departments
                    </h3>
                    <div class="card-tools m-0">
                        <button type="button" class="btn btn-sm btn-success shadow-sm" data-bs-toggle="modal" data-bs-target="#deptModal">
                            <i class="fas fa-plus-circle mr-1"></i> Add New Department
                        </button>
                    </div>
                </div>
            </div>
            
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Department</th>
                                <th>Faculty</th>
                                <th>Key Personnel</th>
                                <th>Contact Email</th>
                                <th>Asset Count</th>
                                <th class="text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($departments as $dept): 
                                // Count assets for this department
                                $asset_count_stmt = $pdo->prepare("SELECT COUNT(*) FROM assets WHERE department = ?");
                                $asset_count_stmt->execute([$dept['name']]);
                                $asset_count = $asset_count_stmt->fetchColumn();
                            ?>
                                <tr>
                                    <td><strong><?= escape($dept['name']) ?></strong></td>
                                    <td><?= escape($dept['faculty']) ?></td>
                                    <td>
                                        <div class="small"><strong>HOD:</strong> <?= escape($dept['hod_name'] ?? 'N/A') ?></div>
                                        <div class="small text-muted"><strong>Coord:</strong> <?= escape($dept['coordinator_name'] ?? 'N/A') ?></div>
                                        <div class="small text-muted"><strong>Clerk:</strong> <?= escape($dept['clerk_name'] ?? 'N/A') ?></div>
                                        <div class="small text-muted"><strong>Store:</strong> <?= escape($dept['store_officer_name'] ?? 'N/A') ?></div>
                                        <?php 
                                            $f_count_stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE department = ? AND role = 'faculty' AND is_active = 1");
                                            $f_count_stmt->execute([$dept['name']]);
                                            $f_count = $f_count_stmt->fetchColumn();
                                        ?>
                                        <div class="small text-primary"><strong>Faculty:</strong> <?= $f_count ?> members</div>
                                    </td>
                                    <td><?= escape($dept['contact_email']) ?></td>
                                    <td><span class="badge badge-info"><?= $asset_count ?></span></td>
                                    <td class="text-right">
                                        <a href="?edit=<?= $dept['id'] ?>" class="btn btn-sm btn-outline-primary"><i class="fas fa-edit"></i></a>
                                        <button type="button" class="btn btn-sm btn-outline-danger" 
                                                onclick="confirmDelete(<?= $dept['id'] ?>)"><i class="fas fa-trash-alt"></i></button>
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

<!-- DEPARTMENT REGISTRATION MODAL -->
<div class="modal fade" id="deptModal" tabindex="-1" aria-labelledby="deptModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="post">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="deptModalLabel">
                        <i class="fas fa-<?= $editing_department ? 'edit' : 'plus-circle' ?> mr-2"></i>
                        <?= $editing_department ? 'Update Department Details' : 'Create New University Department' ?>
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <!-- Manual scroll added below -->
                <div class="modal-body bg-light" style="max-height: 480px; overflow-y: auto;">
                    <div class="row">
                        <!-- DEPARTMENT CORE INFO -->
                        <div class="col-md-12 mb-3">
                            <div class="card card-outline card-info shadow-sm">
                                <div class="card-header"><h3 class="card-title font-weight-bold">Department Information</h3></div>
                                <div class="card-body">
                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="name" class="form-label">Department Name *</label>
                                                <input type="text" class="form-control" id="name" name="name" 
                                                       value="<?= $editing_department ? escape($editing_department['name']) : '' ?>" required placeholder="e.g. Computer Science">
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="faculty" class="form-label">Faculty</label>
                                                <input type="text" class="form-control" id="faculty" name="faculty" 
                                                       value="<?= $editing_department ? escape($editing_department['faculty']) : '' ?>" placeholder="e.g. Science & Technology">
                                            </div>
                                        </div>
                                        <div class="col-md-12">
                                            <div class="mb-3">
                                                <label for="contact_email" class="form-label">Departmental Contact Email</label>
                                                <input type="email" class="form-control" id="contact_email" name="contact_email" 
                                                       value="<?= $editing_department ? escape($editing_department['contact_email']) : '' ?>" placeholder="dept@university.edu">
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <?php 
                        $staff_roles = [
                            'hod' => ['label' => 'Head of Department (HOD)', 'prefix' => 'hod', 'color' => 'primary'],
                            'coord' => ['label' => 'Department Coordinator', 'prefix' => 'coord', 'color' => 'info'],
                            'clerk' => ['label' => 'Department Clerk', 'prefix' => 'clerk', 'color' => 'secondary'],
                            'store' => ['label' => 'Store Officer', 'prefix' => 'store', 'color' => 'warning']
                        ];
                        foreach ($staff_roles as $key => $info):
                            $cur = $staff[$key] ?? ['name' => '', 'email' => ''];
                        ?>
                        <div class="col-md-12 mb-3">
                            <div class="card card-outline card-<?= $info['color'] ?> shadow-sm">
                                <div class="card-header py-2"><h3 class="card-title font-weight-bold" style="font-size: 1rem;"><?= $info['label'] ?></h3></div>
                                <div class="card-body py-2">
                                    <div class="row g-2">
                                        <div class="col-md-4">
                                            <div class="mb-3">
                                                <label class="form-label small">Full Name</label>
                                                <input type="text" class="form-control form-control-sm" name="<?= $info['prefix'] ?>_name" value="<?= escape($cur['name']) ?>" placeholder="Full Name">
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="mb-3">
                                                <label class="form-label small">Email Address</label>
                                                <input type="email" class="form-control form-control-sm" name="<?= $info['prefix'] ?>_email" value="<?= escape($cur['email']) ?>" placeholder="Email">
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="mb-3">
                                                <label class="form-label small">Password <?= $editing_department ? '(optional)' : '' ?></label>
                                                <input type="password" class="form-control form-control-sm" name="<?= $info['prefix'] ?>_pass" placeholder="<?= $editing_department ? 'Leave blank' : 'Default: 123456' ?>">
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>

                        <!-- FACULTY SECTION -->
                        <div class="col-md-12 mt-2">
                            <div class="card card-outline card-success shadow-sm" id="faculty_section">
                                <div class="card-header">
                                    <h3 class="card-title font-weight-bold">Faculty Members</h3>
                                    <div class="card-tools">
                                        <button type="button" class="btn btn-sm btn-success shadow-sm" onclick="addFacultyRow()"><i class="fas fa-plus"></i> Add Faculty</button>
                                    </div>
                                </div>
                                <div class="card-body">
                                    <div id="faculty_rows">
                                        <?php if (empty($editing_faculty)): ?>
                                            <div class="row faculty-row mb-2">
                                                <div class="col-md-4"><div class="mb-3"><input type="text" name="faculty_names[]" class="form-control" placeholder="Faculty Name"></div></div>
                                                <div class="col-md-4"><div class="mb-3"><input type="email" name="faculty_emails[]" class="form-control" placeholder="Faculty Email"></div></div>
                                                <div class="col-md-3"><div class="mb-3"><input type="password" name="faculty_passes[]" class="form-control" placeholder="Password"></div></div>
                                                <div class="col-md-1"></div>
                                            </div>
                                        <?php else: ?>
                                            <?php foreach ($editing_faculty as $fac): ?>
                                                <div class="row faculty-row mb-2">
                                                    <div class="col-md-4"><div class="mb-3"><input type="text" name="faculty_names[]" class="form-control" value="<?= escape($fac['name']) ?>"></div></div>
                                                    <div class="col-md-4"><div class="mb-3"><input type="email" name="faculty_emails[]" class="form-control" value="<?= escape($fac['email']) ?>" readonly></div></div>
                                                    <div class="col-md-3"><div class="mb-3"><input type="password" name="faculty_passes[]" class="form-control" placeholder="Update Pass"></div></div>
                                                    <div class="col-md-1"><button type="button" class="btn btn-danger btn-sm" onclick="this.parentElement.parentElement.remove()"><i class="fas fa-times"></i></button></div>
                                                </div>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php if ($editing_department): ?>
                        <input type="hidden" name="department_id" value="<?= $editing_department['id'] ?>">
                    <?php endif; ?>
                </div>
                <div class="modal-footer justify-content-between bg-white border-top">
                    <button type="button" class="btn btn-default" data-bs-dismiss="modal">Close</button>
                    <div>
                        <?php if ($editing_department): ?>
                            <a href="departments.php" class="btn btn-secondary mr-2">Cancel Edit</a>
                        <?php endif; ?>
                        <button type="submit" name="<?= $editing_department ? 'edit_department' : 'add_department' ?>" class="btn btn-<?= $editing_department ? 'primary' : 'success' ?> px-4 shadow">
                            <i class="fas fa-check-circle mr-1"></i> <?= $editing_department ? 'Update Department' : 'Create Department' ?>
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function addFacultyRow() {
    const row = `
        <div class="row faculty-row mb-2">
            <div class="col-md-4"><input type="text" name="faculty_names[]" class="form-control" placeholder="Faculty Name"></div>
            <div class="col-md-4"><input type="email" name="faculty_emails[]" class="form-control" placeholder="Faculty Email"></div>
            <div class="col-md-3"><input type="password" name="faculty_passes[]" class="form-control" placeholder="Password"></div>
            <div class="col-md-1"><button type="button" class="btn btn-danger btn-sm" onclick="this.parentElement.parentElement.remove()"><i class="fas fa-times"></i></button></div>
        </div>
    `;
    document.getElementById('faculty_rows').insertAdjacentHTML('beforeend', row);
}

// Auto-open department modal for editing or errors
document.addEventListener("DOMContentLoaded", function() {
    <?php if ($editing_department || $error): ?>
        var deptModal = new bootstrap.Modal(document.getElementById('deptModal'));
        deptModal.show();
    <?php endif; ?>
});

function confirmDelete(deptId) {
    if (confirm('Are you sure you want to delete this department? This action cannot be undone if there are no associated assets.')) {
        const form = document.createElement('form');
        form.method = 'post';
        form.style.display = 'none';
        form.innerHTML = '<input type="hidden" name="delete_id" value="' + deptId + '">' +
                         '<input type="hidden" name="delete_department" value="1">';
        document.body.appendChild(form);
        form.submit();
    }
}
</script>

<?php
include __DIR__ . '/../../includes/footer.php';
?>