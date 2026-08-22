<?php
/**
 * Stock Out (Dispatch & Sales)
 */

$pageTitle = 'Stock Out (Dispatch)';
require_once __DIR__ . '/includes/header.php';

$db = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $productId = (int)($_POST['product_id'] ?? 0);
    $recipient = sanitize($_POST['recipient'] ?? '');
    $qty       = (int)($_POST['quantity'] ?? 0);
    $date      = $_POST['dispatch_date'] ?? date('Y-m-d');
    $purpose   = sanitize($_POST['purpose'] ?? '');
    $remarks   = sanitize($_POST['remarks'] ?? '');
    $ref       = sanitize($_POST['reference_number'] ?? generate_reference('OUT'));

    if ($productId <= 0 || empty($recipient) || $qty <= 0) {
        set_flash(ALERT_DANGER, 'Product, Recipient name, and Quantity (>0) are required.');
    } else {
        $res = stock_out_transaction($productId, $recipient, $qty, $ref, $date, $purpose, $remarks, $currentUser['id']);
        if ($res['success']) {
            set_flash(ALERT_SUCCESS, "Stock Out dispatch recorded [Ref: {$ref}]. Remaining product balance: {$res['new_balance']} units.");
            header('Location: ' . BASE_URL . '/stock_out.php');
            exit;
        } else {
            set_flash(ALERT_DANGER, 'Dispatch Failed: ' . $res['message']);
        }
    }
}

// Fetch active products with available stock
$products = $db->query("SELECT p.*, c.category_name FROM products p JOIN categories c ON p.category_id = c.id WHERE p.status = 'active' ORDER BY p.product_name ASC")->fetchAll();

// Fetch Stock Out History
$history = $db->query("
    SELECT so.*, p.product_name, p.product_code, p.unit, u.name as user_name
    FROM stock_out so
    JOIN products p ON so.product_id = p.id
    JOIN users u ON so.created_by = u.id
    ORDER BY so.id DESC
")->fetchAll();
?>

<div class="top-bar">
    <div class="page-header">
        <h1>Stock Out (Dispatch & Consumption)</h1>
        <p>Record stock dispatches, sales, and external transfers</p>
    </div>
    <div class="quick-actions">
        <button class="btn btn-primary" onclick="openModal('modalStockOut')"><i class="fa-solid fa-plus"></i> New Dispatch Entry</button>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h3 class="card-title"><i class="fa-solid fa-list"></i> Dispatch History</h3>
    </div>
    <div class="table-responsive">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Ref #</th>
                    <th>Product</th>
                    <th>Recipient / Department</th>
                    <th>Qty Dispatched</th>
                    <th>Purpose / Usage</th>
                    <th>Date</th>
                    <th>Processed By</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($history)): ?>
                    <tr><td colspan="7" style="text-align:center; color:var(--text-muted);">No dispatches recorded yet.</td></tr>
                <?php else: ?>
                    <?php foreach ($history as $row): ?>
                        <tr>
                            <td><code style="color:var(--accent-cyan); font-weight:700;"><?= e($row['reference_number']) ?></code></td>
                            <td>
                                <strong><?= e($row['product_name']) ?></strong>
                                <div style="font-size:11px; color:var(--text-muted);"><?= e($row['product_code']) ?></div>
                            </td>
                            <td><strong><?= e($row['recipient']) ?></strong></td>
                            <td><span class="badge badge-warning">-<?= number_format($row['quantity']) ?> <?= e($row['unit']) ?></span></td>
                            <td><?= e($row['purpose'] ?: '-') ?></td>
                            <td style="font-size:12px; color:var(--text-muted);"><?= format_date_only($row['dispatch_date']) ?></td>
                            <td style="font-size:12px; color:var(--text-muted);"><?= e($row['user_name']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal: New Stock Out Form -->
<div class="modal-overlay" id="modalStockOut">
    <div class="modal-container">
        <div class="card-header">
            <h3 class="card-title"><i class="fa-solid fa-arrow-up-from-bracket"></i> Record Stock Dispatch</h3>
            <button onclick="closeModal('modalStockOut')" style="background:none; border:none; color:var(--text-muted); cursor:pointer;"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <form method="POST" action="">
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Reference Number</label>
                    <input type="text" name="reference_number" class="form-control" value="<?= generate_reference('OUT') ?>" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Dispatch Date *</label>
                    <input type="date" name="dispatch_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">Select Product *</label>
                <select name="product_id" class="form-control" required>
                    <option value="">-- Choose Product --</option>
                    <?php foreach ($products as $p): ?>
                        <option value="<?= $p['id'] ?>" <?= $p['quantity'] <= 0 ? 'disabled' : '' ?>>
                            <?= e($p['product_name']) ?> [<?= e($p['product_code']) ?>] — Available: <?= $p['quantity'] ?> <?= e($p['unit']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label class="form-label">Recipient / Target Entity *</label>
                <input type="text" name="recipient" class="form-control" placeholder="e.g. Sales Team 2, Customer ABC, Floor 4 Admin" required>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Quantity to Dispatch *</label>
                    <input type="number" name="quantity" class="form-control" min="1" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Purpose / Project</label>
                    <input type="text" name="purpose" class="form-control" placeholder="e.g. Client Delivery, Internal Event">
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">Remarks</label>
                <textarea name="remarks" class="form-control" rows="2" placeholder="Approval reference, delivery notes..."></textarea>
            </div>

            <div style="display:flex; justify-content:flex-end; gap:12px; margin-top:16px;">
                <button type="button" class="btn btn-secondary" onclick="closeModal('modalStockOut')">Cancel</button>
                <button type="submit" class="btn btn-primary"><i class="fa-solid fa-check"></i> Process Dispatch</button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
