<?php
/**
 * Suppliers Directory
 */

$pageTitle = 'Suppliers Directory';
require_once __DIR__ . '/includes/header.php';

$db = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $company = sanitize($_POST['company_name'] ?? '');
        $contact = sanitize($_POST['contact_person'] ?? '');
        $phone   = sanitize($_POST['phone'] ?? '');
        $email   = sanitize($_POST['email'] ?? '');
        $taxId   = sanitize($_POST['tax_id'] ?? '');
        $address = sanitize($_POST['address'] ?? '');

        if (empty($company)) {
            set_flash(ALERT_DANGER, 'Company name is required.');
        } else {
            try {
                $stmt = $db->prepare("INSERT INTO suppliers (company_name, contact_person, phone, email, tax_id, address) VALUES (:c, :p, :ph, :e, :t, :a)");
                $stmt->execute([':c' => $company, ':p' => $contact, ':ph' => $phone, ':e' => $email, ':t' => $taxId, ':a' => $address]);
                log_activity($currentUser['id'], $currentUser['name'], 'CREATE', 'Supplier', (string)$db->lastInsertId(), "Added supplier: {$company}");
                set_flash(ALERT_SUCCESS, "Supplier '{$company}' added!");
            } catch (Exception $e) {
                set_flash(ALERT_DANGER, 'Error: ' . $e->getMessage());
            }
        }
        header('Location: ' . BASE_URL . '/suppliers.php');
        exit;
    }

    if ($action === 'edit') {
        $id      = (int)($_POST['id'] ?? 0);
        $company = sanitize($_POST['company_name'] ?? '');
        $contact = sanitize($_POST['contact_person'] ?? '');
        $phone   = sanitize($_POST['phone'] ?? '');
        $email   = sanitize($_POST['email'] ?? '');
        $taxId   = sanitize($_POST['tax_id'] ?? '');
        $address = sanitize($_POST['address'] ?? '');
        $status  = $_POST['status'] ?? 'active';

        if ($id <= 0 || empty($company)) {
            set_flash(ALERT_DANGER, 'Invalid supplier parameters.');
        } else {
            try {
                $stmt = $db->prepare("UPDATE suppliers SET company_name = :c, contact_person = :p, phone = :ph, email = :e, tax_id = :t, address = :a, status = :s, updated_at = NOW() WHERE id = :id");
                $stmt->execute([':c' => $company, ':p' => $contact, ':ph' => $phone, ':e' => $email, ':t' => $taxId, ':a' => $address, ':s' => $status, ':id' => $id]);
                log_activity($currentUser['id'], $currentUser['name'], 'UPDATE', 'Supplier', (string)$id, "Updated supplier: {$company}");
                set_flash(ALERT_SUCCESS, "Supplier updated!");
            } catch (Exception $e) {
                set_flash(ALERT_DANGER, 'Error: ' . $e->getMessage());
            }
        }
        header('Location: ' . BASE_URL . '/suppliers.php');
        exit;
    }
}

$suppliers = $db->query("SELECT * FROM suppliers ORDER BY company_name ASC")->fetchAll();
?>

<div class="top-bar">
    <div class="page-header">
        <h1>Suppliers Directory</h1>
        <p>Manage vendors, procurement partners, and contact info</p>
    </div>
    <div class="quick-actions">
        <button class="btn btn-primary" onclick="openModal('modalAddSupplier')"><i class="fa-solid fa-plus"></i> Add Supplier</button>
    </div>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Company Name</th>
                    <th>Contact Person</th>
                    <th>Phone / Email</th>
                    <th>Tax ID / GSTIN</th>
                    <th>Address</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($suppliers)): ?>
                    <tr><td colspan="7" style="text-align:center; color:var(--text-muted);">No suppliers listed.</td></tr>
                <?php else: ?>
                    <?php foreach ($suppliers as $sup): ?>
                        <tr>
                            <td><strong><?= e($sup['company_name']) ?></strong></td>
                            <td><?= e($sup['contact_person'] ?: '-') ?></td>
                            <td>
                                <div><i class="fa-solid fa-phone" style="font-size:11px; color:var(--accent-teal);"></i> <?= e($sup['phone'] ?: '-') ?></div>
                                <div style="font-size:12px; color:var(--text-muted);"><i class="fa-solid fa-envelope" style="font-size:11px;"></i> <?= e($sup['email'] ?: '-') ?></div>
                            </td>
                            <td><code style="color:var(--accent-cyan);"><?= e($sup['tax_id'] ?: '-') ?></code></td>
                            <td style="font-size:12px; color:var(--text-muted);"><?= e($sup['address'] ?: '-') ?></td>
                            <td><span class="badge <?= $sup['status'] === 'active' ? 'badge-success' : 'badge-danger' ?>"><?= ucfirst($sup['status']) ?></span></td>
                            <td>
                                <button class="btn btn-sm btn-secondary" onclick='editSupplier(<?= json_encode($sup) ?>)'><i class="fa-solid fa-pen"></i> Edit</button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal: Add Supplier -->
<div class="modal-overlay" id="modalAddSupplier">
    <div class="modal-container">
        <div class="card-header">
            <h3 class="card-title">Add Supplier</h3>
            <button onclick="closeModal('modalAddSupplier')" style="background:none; border:none; color:var(--text-muted); cursor:pointer;"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <form method="POST" action="">
            <input type="hidden" name="action" value="create">
            <div class="form-group">
                <label class="form-label">Company Name *</label>
                <input type="text" name="company_name" class="form-control" required>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Contact Person</label>
                    <input type="text" name="contact_person" class="form-control">
                </div>
                <div class="form-group">
                    <label class="form-label">Tax ID / GSTIN</label>
                    <input type="text" name="tax_id" class="form-control">
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
            <div class="form-group">
                <label class="form-label">Office Address</label>
                <textarea name="address" class="form-control" rows="2"></textarea>
            </div>
            <div style="display:flex; justify-content:flex-end; gap:12px;">
                <button type="button" class="btn btn-secondary" onclick="closeModal('modalAddSupplier')">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Supplier</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: Edit Supplier -->
<div class="modal-overlay" id="modalEditSupplier">
    <div class="modal-container">
        <div class="card-header">
            <h3 class="card-title">Edit Supplier</h3>
            <button onclick="closeModal('modalEditSupplier')" style="background:none; border:none; color:var(--text-muted); cursor:pointer;"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <form method="POST" action="">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="id" id="edit_sup_id">
            <div class="form-group">
                <label class="form-label">Company Name *</label>
                <input type="text" name="company_name" id="edit_sup_comp" class="form-control" required>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Contact Person</label>
                    <input type="text" name="contact_person" id="edit_sup_contact" class="form-control">
                </div>
                <div class="form-group">
                    <label class="form-label">Status</label>
                    <select name="status" id="edit_sup_status" class="form-control">
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                    </select>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Phone</label>
                    <input type="text" name="phone" id="edit_sup_phone" class="form-control">
                </div>
                <div class="form-group">
                    <label class="form-label">Email</label>
                    <input type="email" name="email" id="edit_sup_email" class="form-control">
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">Tax ID / GSTIN</label>
                <input type="text" name="tax_id" id="edit_sup_tax" class="form-control">
            </div>
            <div class="form-group">
                <label class="form-label">Address</label>
                <textarea name="address" id="edit_sup_addr" class="form-control" rows="2"></textarea>
            </div>
            <div style="display:flex; justify-content:flex-end; gap:12px;">
                <button type="button" class="btn btn-secondary" onclick="closeModal('modalEditSupplier')">Cancel</button>
                <button type="submit" class="btn btn-primary">Update Supplier</button>
            </div>
        </form>
    </div>
</div>

<script>
function editSupplier(s) {
    document.getElementById('edit_sup_id').value = s.id;
    document.getElementById('edit_sup_comp').value = s.company_name;
    document.getElementById('edit_sup_contact').value = s.contact_person || '';
    document.getElementById('edit_sup_phone').value = s.phone || '';
    document.getElementById('edit_sup_email').value = s.email || '';
    document.getElementById('edit_sup_tax').value = s.tax_id || '';
    document.getElementById('edit_sup_addr').value = s.address || '';
    document.getElementById('edit_sup_status').value = s.status;
    openModal('modalEditSupplier');
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
