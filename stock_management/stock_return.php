<?php
/**
 * Stock Return (Employee Asset Return)
 */

$pageTitle = 'Stock Return (Asset Return)';
require_once __DIR__ . '/includes/header.php';

$db = getDB();

$selectedAssignmentId = (int)($_GET['assignment_id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $assignmentId = (int)($_POST['assignment_id'] ?? 0);
    $qty          = (int)($_POST['quantity'] ?? 0);
    $condition    = $_POST['condition_status'] ?? 'Usable';
    $date         = $_POST['return_date'] ?? date('Y-m-d');
    $remarks      = sanitize($_POST['remarks'] ?? '');
    $ref          = sanitize($_POST['reference_number'] ?? generate_reference('RET'));

    if ($assignmentId <= 0 || $qty <= 0) {
        set_flash(ALERT_DANGER, 'Assignment record and return quantity (>0) are required.');
    } else {
        $res = stock_return_transaction($assignmentId, $qty, $condition, $ref, $date, $remarks, $currentUser['id']);
        if ($res['success']) {
            set_flash(ALERT_SUCCESS, "Asset return recorded successfully [Ref: {$ref}]. Updated stock balance: {$res['new_balance']} units.");
            header('Location: ' . BASE_URL . '/stock_return.php');
            exit;
        } else {
            set_flash(ALERT_DANGER, 'Return Failed: ' . $res['message']);
        }
    }
}

// Fetch active assignments eligible for return
$activeAssignments = $db->query("
    SELECT sa.*, p.product_name, p.product_code, e.name as employee_name, (sa.quantity - sa.returned_quantity) as pending_qty
    FROM stock_assign sa
    JOIN products p ON sa.product_id = p.id
    JOIN employees e ON sa.employee_id = e.id
    WHERE sa.status != 'returned'
    ORDER BY sa.id DESC
")->fetchAll();

// Fetch Stock Return History
$history = $db->query("
    SELECT sr.*, p.product_name, p.product_code, e.name as employee_name, u.name as user_name
    FROM stock_return sr
    JOIN products p ON sr.product_id = p.id
    JOIN employees e ON sr.employee_id = e.id
    JOIN users u ON sr.created_by = u.id
    ORDER BY sr.id DESC
")->fetchAll();
?>

<div class="top-bar">
    <div class="page-header">
        <h1>Stock Return (Asset Recovery)</h1>
        <p>Process returned assets, verify working condition, and update inventory</p>
    </div>
    <div class="quick-actions">
        <button class="btn btn-primary" onclick="openModal('modalReturn')"><i class="fa-solid fa-plus"></i> Process Return</button>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h3 class="card-title"><i class="fa-solid fa-rotate-left"></i> Asset Return History</h3>
    </div>
    <div class="table-responsive">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Ref #</th>
                    <th>Product</th>
                    <th>Returned By</th>
                    <th>Qty Returned</th>
                    <th>Condition</th>
                    <th>Return Date</th>
                    <th>Processed By</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($history)): ?>
                    <tr><td colspan="7" style="text-align:center; color:var(--text-muted);">No asset returns recorded yet.</td></tr>
                <?php else: ?>
                    <?php foreach ($history as $row): ?>
                        <tr>
                            <td><code style="color:var(--accent-cyan); font-weight:700;"><?= e($row['reference_number']) ?></code></td>
                            <td>
                                <strong><?= e($row['product_name']) ?></strong>
                                <div style="font-size:11px; color:var(--text-muted);"><?= e($row['product_code']) ?></div>
                            </td>
                            <td><strong><?= e($row['employee_name']) ?></strong></td>
                            <td><span class="badge badge-success">+<?= number_format($row['quantity']) ?></span></td>
                            <td>
                                <?php
                                $cBadge = 'badge-success';
                                if ($row['condition_status'] === 'Damaged') $cBadge = 'badge-danger';
                                elseif ($row['condition_status'] === 'Under Repair') $cBadge = 'badge-warning';
                                ?>
                                <span class="badge <?= $cBadge ?>"><?= e($row['condition_status']) ?></span>
                            </td>
                            <td style="font-size:12px; color:var(--text-muted);"><?= format_date_only($row['return_date']) ?></td>
                            <td style="font-size:12px; color:var(--text-muted);"><?= e($row['user_name']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal: Return Form -->
<div class="modal-overlay <?= $selectedAssignmentId > 0 ? 'active' : '' ?>" id="modalReturn">
    <div class="modal-container">
        <div class="card-header">
            <h3 class="card-title"><i class="fa-solid fa-rotate-left"></i> Process Asset Return</h3>
            <button onclick="closeModal('modalReturn')" style="background:none; border:none; color:var(--text-muted); cursor:pointer;"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <form method="POST" action="">
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Reference Number</label>
                    <input type="text" name="reference_number" class="form-control" value="<?= generate_reference('RET') ?>" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Return Date *</label>
                    <input type="date" name="return_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">Select Active Assignment Record *</label>
                <select name="assignment_id" class="form-control" required>
                    <option value="">-- Select Active Issued Asset --</option>
                    <?php foreach ($activeAssignments as $asn): ?>
                        <option value="<?= $asn['id'] ?>" <?= $selectedAssignmentId === (int)$asn['id'] ? 'selected' : '' ?>>
                            <?= e($asn['employee_name']) ?> — <?= e($asn['product_name']) ?> [Pending Return: <?= $asn['pending_qty'] ?>]
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Quantity Returned *</label>
                    <input type="number" name="quantity" class="form-control" min="1" value="1" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Condition Status *</label>
                    <select name="condition_status" class="form-control" required>
                        <option value="Usable">Usable (Restock to Warehouse)</option>
                        <option value="Damaged">Damaged (Requires Repair / Scrap)</option>
                        <option value="Under Repair">Under Repair</option>
                    </select>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">Inspection Remarks</label>
                <textarea name="remarks" class="form-control" rows="2" placeholder="Physical condition check notes..."></textarea>
            </div>

            <div style="display:flex; justify-content:flex-end; gap:12px; margin-top:16px;">
                <button type="button" class="btn btn-secondary" onclick="closeModal('modalReturn')">Cancel</button>
                <button type="submit" class="btn btn-primary"><i class="fa-solid fa-check"></i> Process Return</button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
