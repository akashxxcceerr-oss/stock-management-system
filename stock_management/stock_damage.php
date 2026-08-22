<?php
/**
 * Stock Damage & Write-Off Management
 */

$pageTitle = 'Stock Damage & Write-Off';
require_once __DIR__ . '/includes/header.php';

$db = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $productId = (int)($_POST['product_id'] ?? 0);
    $qty       = (int)($_POST['quantity'] ?? 0);
    $reason    = sanitize($_POST['damage_reason'] ?? '');
    $date      = $_POST['damage_date'] ?? date('Y-m-d');
    $remarks   = sanitize($_POST['remarks'] ?? '');
    $ref       = sanitize($_POST['reference_number'] ?? generate_reference('DMG'));

    if ($productId <= 0 || empty($reason) || $qty <= 0) {
        set_flash(ALERT_DANGER, 'Product, Damage Reason, and Quantity (>0) are required.');
    } else {
        $res = stock_damage_transaction($productId, $qty, $reason, $ref, $date, $remarks, $currentUser['id']);
        if ($res['success']) {
            set_flash(ALERT_SUCCESS, "Stock damage recorded [Ref: {$ref}]. Remaining warehouse balance: {$res['new_balance']} units.");
            header('Location: ' . BASE_URL . '/stock_damage.php');
            exit;
        } else {
            set_flash(ALERT_DANGER, 'Damage Record Failed: ' . $res['message']);
        }
    }
}

// Fetch active products with stock
$products = $db->query("SELECT p.*, c.category_name FROM products p JOIN categories c ON p.category_id = c.id WHERE p.status = 'active' ORDER BY p.product_name ASC")->fetchAll();

// Fetch Stock Damage History
$history = $db->query("
    SELECT sd.*, p.product_name, p.product_code, p.unit, u.name as user_name
    FROM stock_damage sd
    JOIN products p ON sd.product_id = p.id
    JOIN users u ON sd.created_by = u.id
    ORDER BY sd.id DESC
")->fetchAll();
?>

<div class="top-bar">
    <div class="page-header">
        <h1>Stock Damage & Write-Off</h1>
        <p>Log inventory damage, expiry, defect write-offs, and scrap audit</p>
    </div>
    <div class="quick-actions">
        <button class="btn btn-danger" onclick="openModal('modalDamage')"><i class="fa-solid fa-plus"></i> Record Damage Write-off</button>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h3 class="card-title" style="color:var(--danger);"><i class="fa-solid fa-triangle-exclamation"></i> Damage & Write-Off Log</h3>
    </div>
    <div class="table-responsive">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Ref #</th>
                    <th>Product</th>
                    <th>Qty Written Off</th>
                    <th>Reason / Root Cause</th>
                    <th>Status</th>
                    <th>Damage Date</th>
                    <th>Reported By</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($history)): ?>
                    <tr><td colspan="7" style="text-align:center; color:var(--text-muted);">No damage logs recorded.</td></tr>
                <?php else: ?>
                    <?php foreach ($history as $row): ?>
                        <tr>
                            <td><code style="color:var(--accent-cyan); font-weight:700;"><?= e($row['reference_number']) ?></code></td>
                            <td>
                                <strong><?= e($row['product_name']) ?></strong>
                                <div style="font-size:11px; color:var(--text-muted);"><?= e($row['product_code']) ?></div>
                            </td>
                            <td><span class="badge badge-danger">-<?= number_format($row['quantity']) ?> <?= e($row['unit']) ?></span></td>
                            <td><strong><?= e($row['damage_reason']) ?></strong></td>
                            <td><span class="badge badge-danger"><?= str_replace('_', ' ', ucfirst($row['status'])) ?></span></td>
                            <td style="font-size:12px; color:var(--text-muted);"><?= format_date_only($row['damage_date']) ?></td>
                            <td style="font-size:12px; color:var(--text-muted);"><?= e($row['user_name']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal: Damage Form -->
<div class="modal-overlay" id="modalDamage">
    <div class="modal-container">
        <div class="card-header">
            <h3 class="card-title" style="color:var(--danger);"><i class="fa-solid fa-triangle-exclamation"></i> Record Stock Damage</h3>
            <button onclick="closeModal('modalDamage')" style="background:none; border:none; color:var(--text-muted); cursor:pointer;"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <form method="POST" action="">
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Reference Number</label>
                    <input type="text" name="reference_number" class="form-control" value="<?= generate_reference('DMG') ?>" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Damage Incident Date *</label>
                    <input type="date" name="damage_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">Select Product *</label>
                <select name="product_id" class="form-control" required>
                    <option value="">-- Choose Damaged Product --</option>
                    <?php foreach ($products as $p): ?>
                        <option value="<?= $p['id'] ?>" <?= $p['quantity'] <= 0 ? 'disabled' : '' ?>>
                            <?= e($p['product_name']) ?> [<?= e($p['product_code']) ?>] — Stock: <?= $p['quantity'] ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label class="form-label">Damage Reason / Root Cause *</label>
                <input type="text" name="damage_reason" class="form-control" placeholder="e.g. Water leak in aisle 3, Expiry, Broken during transport" required>
            </div>

            <div class="form-group">
                <label class="form-label">Quantity Damaged *</label>
                <input type="number" name="quantity" class="form-control" min="1" value="1" required>
            </div>

            <div class="form-group">
                <label class="form-label">Inspection & Disposal Remarks</label>
                <textarea name="remarks" class="form-control" rows="2" placeholder="Insurance claim details, scrap disposition..."></textarea>
            </div>

            <div style="display:flex; justify-content:flex-end; gap:12px; margin-top:16px;">
                <button type="button" class="btn btn-secondary" onclick="closeModal('modalDamage')">Cancel</button>
                <button type="submit" class="btn btn-danger"><i class="fa-solid fa-check"></i> Write Off Stock</button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
