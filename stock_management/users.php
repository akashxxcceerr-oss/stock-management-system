<?php
/**
 * System User Management (Admin Only)
 */

$pageTitle = 'User Management';
require_once __DIR__ . '/includes/header.php';

require_role(['admin']);

$db = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $name = sanitize($_POST['name'] ?? '');
        $username = sanitize($_POST['username'] ?? '');
        $email = sanitize($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $role = $_POST['role'] ?? 'staff';

        if (empty($name) || empty($username) || empty($password)) {
            set_flash(ALERT_DANGER, 'Name, Username, and Password are required.');
        } else {
            try {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $db->prepare("INSERT INTO users (name, username, email, password, role) VALUES (:n, :u, :e, :p, :r)");
                $stmt->execute([':n' => $name, ':u' => $username, ':e' => $email, ':p' => $hash, ':r' => $role]);
                log_activity($currentUser['id'], $currentUser['name'], 'CREATE', 'User', (string)$db->lastInsertId(), "Created user: {$username}");
                set_flash(ALERT_SUCCESS, "User account '{$username}' created!");
            } catch (Exception $e) {
                set_flash(ALERT_DANGER, 'Error: ' . $e->getMessage());
            }
        }
        header('Location: ' . BASE_URL . '/users.php');
        exit;
    }

    if ($action === 'edit') {
        $id = (int)($_POST['id'] ?? 0);
        $name = sanitize($_POST['name'] ?? '');
        $email = sanitize($_POST['email'] ?? '');
        $role = $_POST['role'] ?? 'staff';
        $status = $_POST['status'] ?? 'active';
        $newPass = $_POST['new_password'] ?? '';

        if ($id <= 0 || empty($name)) {
            set_flash(ALERT_DANGER, 'Invalid user parameters.');
        } else {
            try {
                if (!empty($newPass)) {
                    $hash = password_hash($newPass, PASSWORD_DEFAULT);
                    $stmt = $db->prepare("UPDATE users SET name = :n, email = :e, role = :r, status = :st, password = :p, updated_at = NOW() WHERE id = :id");
                    $stmt->execute([':n' => $name, ':e' => $email, ':r' => $role, ':st' => $status, ':p' => $hash, ':id' => $id]);
                } else {
                    $stmt = $db->prepare("UPDATE users SET name = :n, email = :e, role = :r, status = :st, updated_at = NOW() WHERE id = :id");
                    $stmt->execute([':n' => $name, ':e' => $email, ':r' => $role, ':st' => $status, ':id' => $id]);
                }
                log_activity($currentUser['id'], $currentUser['name'], 'UPDATE', 'User', (string)$id, "Updated user account ID: {$id}");
                set_flash(ALERT_SUCCESS, "User updated successfully!");
            } catch (Exception $e) {
                set_flash(ALERT_DANGER, 'Error: ' . $e->getMessage());
            }
        }
        header('Location: ' . BASE_URL . '/users.php');
        exit;
    }
}

$users = $db->query("SELECT * FROM users ORDER BY id ASC")->fetchAll();
?>

<div class="top-bar">
    <div class="page-header">
        <h1>User Administration</h1>
        <p>Manage system users, access roles, and security permissions</p>
    </div>
    <div class="quick-actions">
        <button class="btn btn-primary" onclick="openModal('modalAddUser')"><i class="fa-solid fa-user-plus"></i> Add New User</button>
    </div>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="data-table">
            <thead>
                <tr>
                    <th>User ID</th>
                    <th>Full Name</th>
                    <th>Username</th>
                    <th>Email</th>
                    <th>Role</th>
                    <th>Status</th>
                    <th>Last Login</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $u): ?>
                    <tr>
                        <td>#<?= $u['id'] ?></td>
                        <td><strong><?= e($u['name']) ?></strong></td>
                        <td><code style="color:var(--accent-cyan);"><?= e($u['username']) ?></code></td>
                        <td><?= e($u['email'] ?: '-') ?></td>
                        <td>
                            <?php
                            $roleBadge = 'badge-info';
                            if ($u['role'] === 'admin') $roleBadge = 'badge-danger';
                            elseif ($u['role'] === 'manager') $roleBadge = 'badge-warning';
                            ?>
                            <span class="badge <?= $roleBadge ?>"><?= ucfirst($u['role']) ?></span>
                        </td>
                        <td><span class="badge <?= $u['status'] === 'active' ? 'badge-success' : 'badge-danger' ?>"><?= ucfirst($u['status']) ?></span></td>
                        <td style="font-size:12px; color:var(--text-muted);"><?= format_date($u['last_login']) ?></td>
                        <td>
                            <button class="btn btn-sm btn-secondary" onclick='editUser(<?= json_encode($u) ?>)'><i class="fa-solid fa-pen"></i> Edit</button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal: Add User -->
<div class="modal-overlay" id="modalAddUser">
    <div class="modal-container">
        <div class="card-header">
            <h3 class="card-title"><i class="fa-solid fa-user-plus"></i> Create System User</h3>
            <button onclick="closeModal('modalAddUser')" style="background:none; border:none; color:var(--text-muted); cursor:pointer;"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <form method="POST" action="">
            <input type="hidden" name="action" value="create">
            <div class="form-group">
                <label class="form-label">Full Name *</label>
                <input type="text" name="name" class="form-control" placeholder="User Full Name" required>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Username *</label>
                    <input type="text" name="username" class="form-control" placeholder="e.g. john_doe" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Email Address</label>
                    <input type="email" name="email" class="form-control" placeholder="john@enterprise.local">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Password *</label>
                    <input type="password" name="password" class="form-control" placeholder="Enter secure password" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Access Role *</label>
                    <select name="role" class="form-control" required>
                        <option value="staff">Staff (Basic Ops)</option>
                        <option value="manager">Manager (Inventory Ops)</option>
                        <option value="admin">Administrator (Full Access)</option>
                    </select>
                </div>
            </div>
            <div style="display:flex; justify-content:flex-end; gap:12px; margin-top:16px;">
                <button type="button" class="btn btn-secondary" onclick="closeModal('modalAddUser')">Cancel</button>
                <button type="submit" class="btn btn-primary">Create User</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: Edit User -->
<div class="modal-overlay" id="modalEditUser">
    <div class="modal-container">
        <div class="card-header">
            <h3 class="card-title">Edit User Account</h3>
            <button onclick="closeModal('modalEditUser')" style="background:none; border:none; color:var(--text-muted); cursor:pointer;"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <form method="POST" action="">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="id" id="edit_user_id">
            <div class="form-group">
                <label class="form-label">Full Name *</label>
                <input type="text" name="name" id="edit_user_name" class="form-control" required>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Email Address</label>
                    <input type="email" name="email" id="edit_user_email" class="form-control">
                </div>
                <div class="form-group">
                    <label class="form-label">Role</label>
                    <select name="role" id="edit_user_role" class="form-control">
                        <option value="staff">Staff</option>
                        <option value="manager">Manager</option>
                        <option value="admin">Administrator</option>
                    </select>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Status</label>
                    <select name="status" id="edit_user_status" class="form-control">
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Reset Password (Optional)</label>
                    <input type="password" name="new_password" class="form-control" placeholder="Leave blank to keep current">
                </div>
            </div>
            <div style="display:flex; justify-content:flex-end; gap:12px; margin-top:16px;">
                <button type="button" class="btn btn-secondary" onclick="closeModal('modalEditUser')">Cancel</button>
                <button type="submit" class="btn btn-primary">Update User</button>
            </div>
        </form>
    </div>
</div>

<script>
function editUser(u) {
    document.getElementById('edit_user_id').value = u.id;
    document.getElementById('edit_user_name').value = u.name;
    document.getElementById('edit_user_email').value = u.email || '';
    document.getElementById('edit_user_role').value = u.role;
    document.getElementById('edit_user_status').value = u.status;
    openModal('modalEditUser');
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
