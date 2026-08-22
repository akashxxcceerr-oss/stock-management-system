<?php
/**
 * Employees & Asset Custody Directory
 */

$pageTitle = 'Employees & Custody Directory';
require_once __DIR__ . '/includes/header.php';

$db = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $code  = strtoupper(sanitize($_POST['employee_code'] ?? ''));
        $name  = sanitize($_POST['name'] ?? '');
        $dept  = sanitize($_POST['department'] ?? '');
        $desig = sanitize($_POST['designation'] ?? '');
        $phone = sanitize($_POST['phone'] ?? '');
        $email = sanitize($_POST['email'] ?? '');

        if (empty($code) || empty($name) || empty($dept)) {
            set_flash(ALERT_DANGER, 'Employee Code, Name, and Department are required.');
        } else {
            try {
                $stmt = $db->prepare("INSERT INTO employees (employee_code, name, department, designation, phone, email) VALUES (:c, :n, :d, :ds, :p, :e)");
                $stmt->execute([':c' => $code, ':n' => $name, ':d' => $dept, ':ds' => $desig, ':p' => $phone, ':e' => $email]);
                log_activity($currentUser['id'], $currentUser['name'], 'CREATE', 'Employee', (string)$db->lastInsertId(), "Added employee: {$name} ({$code})");
                set_flash(ALERT_SUCCESS, "Employee '{$name}' added!");
            } catch (Exception $e) {
                set_flash(ALERT_DANGER, 'Error: ' . $e->getMessage());
            }
        }
        header('Location: ' . BASE_URL . '/employees.php');
        exit;
    }

    if ($action === 'edit') {
        $id    = (int)($_POST['id'] ?? 0);
        $code  = strtoupper(sanitize($_POST['employee_code'] ?? ''));
        $name  = sanitize($_POST['name'] ?? '');
        $dept  = sanitize($_POST['department'] ?? '');
        $desig = sanitize($_POST['designation'] ?? '');
        $phone = sanitize($_POST['phone'] ?? '');
        $email = sanitize($_POST['email'] ?? '');
        $status = $_POST['status'] ?? 'active';

        if ($id <= 0 || empty($code) || empty($name) || empty($dept)) {
            set_flash(ALERT_DANGER, 'Invalid employee parameters.');
        } else {
            try {
                $stmt = $db->prepare("UPDATE employees SET employee_code = :c, name = :n, department = :d, designation = :ds, phone = :p, email = :e, status = :st, updated_at = NOW() WHERE id = :id");
                $stmt->execute([':c' => $code, ':n' => $name, ':d' => $dept, ':ds' => $desig, ':p' => $phone, ':e' => $email, ':st' => $status, ':id' => $id]);
                log_activity($currentUser['id'], $currentUser['name'], 'UPDATE', 'Employee', (string)$id, "Updated employee: {$name}");
                set_flash(ALERT_SUCCESS, "Employee record updated!");
            } catch (Exception $e) {
                set_flash(ALERT_DANGER, 'Error: ' . $e->getMessage());
            }
        }
        header('Location: ' . BASE_URL . '/employees.php');
        exit;
    }
}

// Fetch Employees with Active Custody Counts
$employees = $db->query("
    SELECT e.*,
        COALESCE((SELECT SUM(sa.quantity - sa.returned_quantity) FROM stock_assign sa WHERE sa.employee_id = e.id AND sa.status != 'returned'), 0) as active_assets_count
    FROM employees e
    ORDER BY e.name ASC
")->fetchAll();
?>

<div class="top-bar">
    <div class="page-header">
        <h1>Employees & Asset Custody</h1>
        <p>Personnel registry and active corporate asset allocations</p>
    </div>
    <div class="quick-actions">
        <button class="btn btn-primary" onclick="openModal('modalAddEmployee')"><i class="fa-solid fa-plus"></i> Add Employee</button>
    </div>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Emp ID</th>
                    <th>Employee Name</th>
                    <th>Department & Role</th>
                    <th>Contact Info</th>
                    <th>Active Asset Custody</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($employees)): ?>
                    <tr><td colspan="7" style="text-align:center; color:var(--text-muted);">No employees registered.</td></tr>
                <?php else: ?>
                    <?php foreach ($employees as $emp): ?>
                        <tr>
                            <td><code style="color:var(--accent-cyan); font-weight:700;"><?= e($emp['employee_code']) ?></code></td>
                            <td><strong><?= e($emp['name']) ?></strong></td>
                            <td>
                                <div><?= e($emp['department']) ?></div>
                                <div style="font-size:12px; color:var(--text-muted);"><?= e($emp['designation'] ?: 'Staff') ?></div>
                            </td>
                            <td>
                                <div><i class="fa-solid fa-phone" style="font-size:11px; color:var(--accent-teal);"></i> <?= e($emp['phone'] ?: '-') ?></div>
                                <div style="font-size:12px; color:var(--text-muted);"><i class="fa-solid fa-envelope" style="font-size:11px;"></i> <?= e($emp['email'] ?: '-') ?></div>
                            </td>
                            <td>
                                <?php if ($emp['active_assets_count'] > 0): ?>
                                    <span class="badge badge-warning"><i class="fa-solid fa-laptop"></i> <?= $emp['active_assets_count'] ?> Assets Issued</span>
                                <?php else: ?>
                                    <span class="badge badge-success">No Assets Assigned</span>
                                <?php endif; ?>
                            </td>
                            <td><span class="badge <?= $emp['status'] === 'active' ? 'badge-success' : 'badge-danger' ?>"><?= ucfirst($emp['status']) ?></span></td>
                            <td>
                                <button class="btn btn-sm btn-secondary" onclick='editEmployee(<?= json_encode($emp) ?>)'><i class="fa-solid fa-pen"></i> Edit</button>
                                <a href="<?= BASE_URL ?>/reports.php?employee_id=<?= $emp['id'] ?>" class="btn btn-sm btn-secondary" title="View Custody Report"><i class="fa-solid fa-eye"></i> Custody</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal: Add Employee -->
<div class="modal-overlay" id="modalAddEmployee">
    <div class="modal-container">
        <div class="card-header">
            <h3 class="card-title">Add Employee</h3>
            <button onclick="closeModal('modalAddEmployee')" style="background:none; border:none; color:var(--text-muted); cursor:pointer;"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <form method="POST" action="">
            <input type="hidden" name="action" value="create">
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Employee Code *</label>
                    <input type="text" name="employee_code" class="form-control" placeholder="e.g. EMP-1005" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Full Name *</label>
                    <input type="text" name="name" class="form-control" placeholder="Employee Full Name" required>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Department *</label>
                    <input type="text" name="department" class="form-control" placeholder="e.g. IT, HR, Sales" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Designation / Title</label>
                    <input type="text" name="designation" class="form-control" placeholder="Job Title">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Phone Number</label>
                    <input type="text" name="phone" class="form-control">
                </div>
                <div class="form-group">
                    <label class="form-label">Email Address</label>
                    <input type="email" name="email" class="form-control">
                </div>
            </div>
            <div style="display:flex; justify-content:flex-end; gap:12px; margin-top:16px;">
                <button type="button" class="btn btn-secondary" onclick="closeModal('modalAddEmployee')">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Employee</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: Edit Employee -->
<div class="modal-overlay" id="modalEditEmployee">
    <div class="modal-container">
        <div class="card-header">
            <h3 class="card-title">Edit Employee</h3>
            <button onclick="closeModal('modalEditEmployee')" style="background:none; border:none; color:var(--text-muted); cursor:pointer;"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <form method="POST" action="">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="id" id="edit_emp_id">
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Employee Code *</label>
                    <input type="text" name="employee_code" id="edit_emp_code" class="form-control" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Full Name *</label>
                    <input type="text" name="name" id="edit_emp_name" class="form-control" required>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Department *</label>
                    <input type="text" name="department" id="edit_emp_dept" class="form-control" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Designation</label>
                    <input type="text" name="designation" id="edit_emp_desig" class="form-control">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Phone</label>
                    <input type="text" name="phone" id="edit_emp_phone" class="form-control">
                </div>
                <div class="form-group">
                    <label class="form-label">Email</label>
                    <input type="email" name="email" id="edit_emp_email" class="form-control">
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">Status</label>
                <select name="status" id="edit_emp_status" class="form-control">
                    <option value="active">Active</option>
                    <option value="inactive">Inactive</option>
                </select>
            </div>
            <div style="display:flex; justify-content:flex-end; gap:12px; margin-top:16px;">
                <button type="button" class="btn btn-secondary" onclick="closeModal('modalEditEmployee')">Cancel</button>
                <button type="submit" class="btn btn-primary">Update Record</button>
            </div>
        </form>
    </div>
</div>

<script>
function editEmployee(e) {
    document.getElementById('edit_emp_id').value = e.id;
    document.getElementById('edit_emp_code').value = e.employee_code;
    document.getElementById('edit_emp_name').value = e.name;
    document.getElementById('edit_emp_dept').value = e.department;
    document.getElementById('edit_emp_desig').value = e.designation || '';
    document.getElementById('edit_emp_phone').value = e.phone || '';
    document.getElementById('edit_emp_email').value = e.email || '';
    document.getElementById('edit_emp_status').value = e.status;
    openModal('modalEditEmployee');
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
