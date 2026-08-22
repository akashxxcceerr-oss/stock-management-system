<?php
/**
 * Stock In (Procurement & Inventory Restocking)
 */

$pageTitle = 'Stock In (Procurement)';
require_once __DIR__ . '/includes/header.php';

$db = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $productId = (int)($_POST['product_id'] ?? 0);
    $supplierId = !empty($_POST['supplier_id']) ? (int)$_POST['supplier_id'] : null;
    $qty       = (int)($_POST['quantity'] ?? 0);
    $unitPrice = (float)($_POST['unit_price'] ?? 0.00);
    $date      = $_POST['purchase_date'] ?? date('Y-m-d');
    $remarks   = sanitize($_POST['remarks'] ?? '');
    $ref       = sanitize($_POST['reference_number'] ?? generate_reference('IN'));

    if ($productId <= 0 || $qty <= 0 || $unitPrice < 0) {
        set_flash(ALERT_DANGER, 'Invalid transaction parameters. Product, Quantity (>0), and Unit Price are required.');
    } else {
        $res = stock_in_transaction($productId, $supplierId, $qty, $unitPrice, $ref, $date, $remarks, $currentUser['id']);
        if ($res['success']) {
            set_flash(ALERT_SUCCESS, "Stock In transaction successfully recorded [Ref: {$ref}]. New product balance: {$res['new_balance']} units.");
            header('Location: ' . BASE_URL . '/stock_in.php');
            exit;
        } else {
            set_flash(ALERT_DANGER, 'Transaction Failed: ' . $res['message']);
        }
    }
}

// Fetch active products and suppliers for dropdown
$products  = $db->query("SELECT p.*, c.category_name FROM products p JOIN categories c ON p.category_id = c.id WHERE p.status = 'active' ORDER BY p.product_name ASC")->fetchAll();
$suppliers = $db->query("SELECT * FROM suppliers WHERE status = 'active' ORDER BY company_name ASC")->fetchAll();

// Fetch Stock In History
$history = $db->query("
    SELECT si.*, p.product_name, p.product_code, p.unit, s.company_name as supplier_name, u.name as user_name
    FROM stock_in si
    JOIN products p ON si.product_id = p.id
    LEFT JOIN suppliers s ON si.supplier_id = s.id
    JOIN users u ON si.created_by = u.id
    ORDER BY si.id DESC
")->fetchAll();
?>

<div class="top-bar">
    <div class="page-header">
        <h1>Stock In (Procurement & Restock)</h1>
        <p>Record incoming goods, purchase orders, and update stock balances</p>
    </div>
    <div class="quick-actions">
        <button class="btn btn-primary" onclick="openModal('modalStockIn')"><i class="fa-solid fa-plus"></i> New Stock In Entry</button>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h3 class="card-title"><i class="fa-solid fa-list"></i> Procurement History</h3>
    </div>
    <div class="table-responsive">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Ref #</th>
                    <th>Product</th>
                    <th>Supplier</th>
                    <th>Qty Received</th>
                    <th>Unit Price</th>
                    <th>Total Cost</th>
                    <th>Date</th>
                    <th>Processed By</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($history)): ?>
                    <tr><td colspan="8" style="text-align:center; color:var(--text-muted);">No stock in transactions recorded yet.</td></tr>
                <?php else: ?>
                    <?php foreach ($history as $row): ?>
                        <tr>
                            <td><code style="color:var(--accent-cyan); font-weight:700;"><?= e($row['reference_number']) ?></code></td>
                            <td>
                                <strong><?= e($row['product_name']) ?></strong>
                                <div style="font-size:11px; color:var(--text-muted);"><?= e($row['product_code']) ?></div>
                            </td>
                            <td><?= e($row['supplier_name'] ?: 'N/A') ?></td>
                            <td><span class="badge badge-success">+<?= number_format($row['quantity']) ?> <?= e($row['unit']) ?></span></td>
                            <td><?= format_currency((float)$row['unit_price']) ?></td>
                            <td style="font-weight:700; color:var(--accent-teal);"><?= format_currency((float)$row['total_cost']) ?></td>
                            <td style="font-size:12px; color:var(--text-muted);"><?= format_date_only($row['purchase_date']) ?></td>
                            <td style="font-size:12px; color:var(--text-muted);"><?= e($row['user_name']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal: New Stock In Form -->
<div class="modal-overlay" id="modalStockIn">
    <div class="modal-container">
        <div class="card-header">
            <h3 class="card-title"><i class="fa-solid fa-arrow-down-to-bracket"></i> Record Stock In</h3>
            <button onclick="closeModal('modalStockIn')" style="background:none; border:none; color:var(--text-muted); cursor:pointer;"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <form method="POST" action="">
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Reference Number</label>
                    <input type="text" name="reference_number" class="form-control" value="<?= generate_reference('IN') ?>" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Purchase Date *</label>
                    <input type="date" name="purchase_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">Select Product *</label>
                <select name="product_id" class="form-control" required>
                    <option value="">-- Choose Product --</option>
                    <?php foreach ($products as $p): ?>
                        <option value="<?= $p['id'] ?>"><?= e($p['product_name']) ?> [<?= e($p['product_code']) ?>] — Current Stock: <?= $p['quantity'] ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label class="form-label">Supplier</label>
                <select name="supplier_id" class="form-control">
                    <option value="">-- Select Supplier (Optional) --</option>
                    <?php foreach ($suppliers as $sup): ?>
                        <option value="<?= $sup['id'] ?>"><?= e($sup['company_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Quantity Received *</label>
                    <input type="number" name="quantity" class="form-control" min="1" placeholder="e.g. 50" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Unit Price (<?= CURRENCY_SYMBOL ?>) *</label>
                    <input type="number" step="0.01" name="unit_price" class="form-control" placeholder="e.g. 1500.00" required>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">Remarks / Purchase Order Info</label>
                <textarea name="remarks" class="form-control" rows="2" placeholder="PO Number, Invoice #, Batch details..."></textarea>
            </div>

            <div style="display:flex; justify-content:flex-end; gap:12px; margin-top:16px;">
                <button type="button" class="btn btn-secondary" onclick="closeModal('modalStockIn')">Cancel</button>
                <button type="submit" class="btn btn-primary"><i class="fa-solid fa-check"></i> Process Stock In</button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
