<?php
/**
 * Stock Assign (Employee Asset Allocation)
 */

$pageTitle = 'Stock Assign (Asset Allocation)';
require_once __DIR__ . '/includes/header.php';

$db = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $productId  = (int)($_POST['product_id'] ?? 0);
    $employeeId = (int)($_POST['employee_id'] ?? 0);
    $qty        = (int)($_POST['quantity'] ?? 0);
    $date       = $_POST['assignment_date'] ?? date('Y-m-d');
    $purpose    = sanitize($_POST['purpose'] ?? '');
    $remarks    = sanitize($_POST['remarks'] ?? '');
    $ref        = sanitize($_POST['reference_number'] ?? generate_reference('ASN'));

    if ($productId <= 0 || $employeeId <= 0 || $qty <= 0) {
        set_flash(ALERT_DANGER, 'Product, Employee, and Quantity (>0) are required.');
    } else {
        $res = stock_assign_transaction($productId, $employeeId, $qty, $ref, $date, $purpose, $remarks, $currentUser['id']);
        if ($res['success']) {
            set_flash(ALERT_SUCCESS, "Asset successfully assigned to employee [Ref: {$ref}]. Remaining warehouse balance: {$res['new_balance']} units.");
            header('Location: ' . BASE_URL . '/stock_assign.php');
            exit;
        } else {
            set_flash(ALERT_DANGER, 'Assignment Failed: ' . $res['message']);
        }
    }
}

// Fetch active products and employees
$products  = $db->query("SELECT p.*, c.category_name FROM products p JOIN categories c ON p.category_id = c.id WHERE p.status = 'active' ORDER BY p.product_name ASC")->fetchAll();
$employees = $db->query("SELECT * FROM employees WHERE status = 'active' ORDER BY name ASC")->fetchAll();

// Fetch Stock Assign History
$history = $db->query("
    SELECT sa.*, p.product_name, p.product_code, e.name as employee_name, e.employee_code, u.name as user_name
    FROM stock_assign sa
    JOIN products p ON sa.product_id = p.id
    JOIN employees e ON sa.employee_id = e.id
    JOIN users u ON sa.created_by = u.id
    ORDER BY sa.id DESC
")->fetchAll();
?>

<div class="top-bar">
    <div class="page-header">
        <h1>Stock Assignment (Asset Custody)</h1>
        <p>Issue company hardware, tools, and assets to employees with tracking</p>
    </div>
    <div class="quick-actions">
        <button class="btn btn-primary" onclick="openModal('modalAssign')"><i class="fa-solid fa-plus"></i> Issue New Asset</button>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h3 class="card-title"><i class="fa-solid fa-handshake-angle"></i> Asset Assignment Records</h3>
    </div>
    <div class="table-responsive">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Ref #</th>
                    <th>Asset / Product</th>
                    <th>Employee Assigned</th>
                    <th>Qty Assigned</th>
                    <th>Returned</th>
                    <th>Status</th>
                    <th>Date</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($history)): ?>
                    <tr><td colspan="8" style="text-align:center; color:var(--text-muted);">No asset assignments recorded yet.</td></tr>
                <?php else: ?>
                    <?php foreach ($history as $row): ?>
                        <tr>
                            <td><code style="color:var(--accent-cyan); font-weight:700;"><?= e($row['reference_number']) ?></code></td>
                            <td>
                                <strong><?= e($row['product_name']) ?></strong>
                                <div style="font-size:11px; color:var(--text-muted);"><?= e($row['product_code']) ?></div>
                            </td>
                            <td>
                                <strong><?= e($row['employee_name']) ?></strong>
                                <div style="font-size:11px; color:var(--text-muted);"><?= e($row['employee_code']) ?></div>
                            </td>
                            <td style="font-weight:700;"><?= number_format($row['quantity']) ?></td>
                            <td><?= number_format($row['returned_quantity']) ?></td>
                            <td>
                                <?php
                                $statusBadge = 'badge-info';
                                if ($row['status'] === 'returned') $statusBadge = 'badge-success';
                                elseif ($row['status'] === 'partially_returned') $statusBadge = 'badge-warning';
                                ?>
                                <span class="badge <?= $statusBadge ?>"><?= str_replace('_', ' ', ucfirst($row['status'])) ?></span>
                            </td>
                            <td style="font-size:12px; color:var(--text-muted);"><?= format_date_only($row['assignment_date']) ?></td>
                            <td>
                                <?php if ($row['status'] !== 'returned'): ?>
                                    <a href="<?= BASE_URL ?>/stock_return.php?assignment_id=<?= $row['id'] ?>" class="btn btn-sm btn-success"><i class="fa-solid fa-rotate-left"></i> Return</a>
                                <?php else: ?>
                                    <span style="font-size:12px; color:var(--accent-teal);"><i class="fa-solid fa-check"></i> Closed</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal: Assign Form -->
<div class="modal-overlay" id="modalAssign">
    <div class="modal-container">
        <div class="card-header">
            <h3 class="card-title"><i class="fa-solid fa-handshake-angle"></i> Issue Asset to Employee</h3>
            <button onclick="closeModal('modalAssign')" style="background:none; border:none; color:var(--text-muted); cursor:pointer;"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <form method="POST" action="">
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Reference Number</label>
                    <input type="text" name="reference_number" class="form-control" value="<?= generate_reference('ASN') ?>" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Assignment Date *</label>
                    <input type="date" name="assignment_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">Select Employee *</label>
                <select name="employee_id" class="form-control" required>
                    <option value="">-- Choose Employee --</option>
                    <?php foreach ($employees as $e): ?>
                        <option value="<?= $e['id'] ?>"><?= e($e['name']) ?> (<?= e($e['employee_code']) ?>) — <?= e($e['department']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label class="form-label">Select Product / Asset *</label>
                <select name="product_id" class="form-control" required>
                    <option value="">-- Choose Product --</option>
                    <?php foreach ($products as $p): ?>
                        <option value="<?= $p['id'] ?>" <?= $p['quantity'] <= 0 ? 'disabled' : '' ?>>
                            <?= e($p['product_name']) ?> [<?= e($p['product_code']) ?>] — Stock: <?= $p['quantity'] ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Quantity to Issue *</label>
                    <input type="number" name="quantity" class="form-control" min="1" value="1" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Purpose / Allocation</label>
                    <input type="text" name="purpose" class="form-control" placeholder="e.g. Workstation setup, Field project">
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">Remarks</label>
                <textarea name="remarks" class="form-control" rows="2" placeholder="Serial numbers, condition notes..."></textarea>
            </div>

            <div style="display:flex; justify-content:flex-end; gap:12px; margin-top:16px;">
                <button type="button" class="btn btn-secondary" onclick="closeModal('modalAssign')">Cancel</button>
                <button type="submit" class="btn btn-primary"><i class="fa-solid fa-check"></i> Issue Asset</button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
