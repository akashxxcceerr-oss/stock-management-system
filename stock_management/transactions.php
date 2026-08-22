<?php
/**
 * Central Inventory Ledger (Double-Entry Audit Log)
 */

$pageTitle = 'Central Inventory Ledger';
require_once __DIR__ . '/includes/header.php';

$db = getDB();

// Filters
$typeFilter = sanitize($_GET['type'] ?? '');
$prodFilter = (int)($_GET['product_id'] ?? 0);
$search = sanitize($_GET['search'] ?? '');

$query = "
    SELECT t.*, p.product_name, p.product_code, p.unit,
           s.company_name as supplier_name, e.name as employee_name, u.name as user_name
    FROM transactions t
    JOIN products p ON t.product_id = p.id
    LEFT JOIN suppliers s ON t.supplier_id = s.id
    LEFT JOIN employees e ON t.employee_id = e.id
    JOIN users u ON t.created_by = u.id
    WHERE 1=1
";
$params = [];

if ($typeFilter !== '') {
    $query .= " AND t.transaction_type = :t";
    $params[':t'] = $typeFilter;
}

if ($prodFilter > 0) {
    $query .= " AND t.product_id = :p";
    $params[':p'] = $prodFilter;
}

if ($search !== '') {
    $query .= " AND (t.reference_number LIKE :s OR t.remarks LIKE :s OR p.product_name LIKE :s)";
    $params[':s'] = "%{$search}%";
}

$query .= " ORDER BY t.id DESC";
$stmt = $db->prepare($query);
$stmt->execute($params);
$transactions = $stmt->fetchAll();

$allProducts = $db->query("SELECT id, product_name, product_code FROM products ORDER BY product_name ASC")->fetchAll();
?>

<div class="top-bar">
    <div class="page-header">
        <h1>Central Inventory Ledger</h1>
        <p>Immutable double-entry stock movement ledger and transaction history</p>
    </div>
    <div class="quick-actions">
        <button onclick="window.print()" class="btn btn-secondary"><i class="fa-solid fa-print"></i> Print / Export</button>
    </div>
</div>

<!-- Filters -->
<div class="card" style="padding:16px; margin-bottom:24px;">
    <form method="GET" action="" style="display:flex; gap:16px; align-items:center; flex-wrap:wrap;">
        <div style="flex:1; min-width:200px;">
            <input type="text" name="search" class="form-control" placeholder="Search reference or remarks..." value="<?= e($search) ?>">
        </div>
        <div style="width:180px;">
            <select name="type" class="form-control" onchange="this.form.submit()">
                <option value="">All Types</option>
                <option value="STOCK_IN" <?= $typeFilter === 'STOCK_IN' ? 'selected' : '' ?>>Stock In</option>
                <option value="STOCK_OUT" <?= $typeFilter === 'STOCK_OUT' ? 'selected' : '' ?>>Stock Out</option>
                <option value="ASSIGNMENT" <?= $typeFilter === 'ASSIGNMENT' ? 'selected' : '' ?>>Assignment</option>
                <option value="RETURN" <?= $typeFilter === 'RETURN' ? 'selected' : '' ?>>Return</option>
                <option value="DAMAGE" <?= $typeFilter === 'DAMAGE' ? 'selected' : '' ?>>Damage Write-off</option>
            </select>
        </div>
        <div style="width:220px;">
            <select name="product_id" class="form-control" onchange="this.form.submit()">
                <option value="0">All Products</option>
                <?php foreach ($allProducts as $ap): ?>
                    <option value="<?= $ap['id'] ?>" <?= $prodFilter === (int)$ap['id'] ? 'selected' : '' ?>><?= e($ap['product_name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <button type="submit" class="btn btn-secondary"><i class="fa-solid fa-filter"></i> Filter</button>
        <?php if ($search !== '' || $typeFilter !== '' || $prodFilter > 0): ?>
            <a href="<?= BASE_URL ?>/transactions.php" class="btn btn-sm btn-secondary">Clear</a>
        <?php endif; ?>
    </form>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Ref #</th>
                    <th>Type</th>
                    <th>Product</th>
                    <th>Qty Delta</th>
                    <th>Balance After</th>
                    <th>Party / Recipient</th>
                    <th>Remarks</th>
                    <th>Timestamp</th>
                    <th>Recorded By</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($transactions)): ?>
                    <tr><td colspan="9" style="text-align:center; color:var(--text-muted); padding:30px;">No transactions match criteria.</td></tr>
                <?php else: ?>
                    <?php foreach ($transactions as $tx): ?>
                        <tr>
                            <td><code style="color:var(--accent-cyan); font-weight:700;"><?= e($tx['reference_number']) ?></code></td>
                            <td>
                                <?php
                                $badgeClass = 'badge-info';
                                if ($tx['transaction_type'] === 'STOCK_IN') $badgeClass = 'badge-success';
                                elseif ($tx['transaction_type'] === 'STOCK_OUT') $badgeClass = 'badge-warning';
                                elseif ($tx['transaction_type'] === 'ASSIGNMENT') $badgeClass = 'badge-info';
                                elseif ($tx['transaction_type'] === 'RETURN') $badgeClass = 'badge-success';
                                elseif ($tx['transaction_type'] === 'DAMAGE') $badgeClass = 'badge-danger';
                                ?>
                                <span class="badge <?= $badgeClass ?>"><?= e($tx['transaction_type']) ?></span>
                            </td>
                            <td>
                                <strong><?= e($tx['product_name']) ?></strong>
                                <div style="font-size:11px; color:var(--text-muted);"><?= e($tx['product_code']) ?></div>
                            </td>
                            <td style="font-weight:700; color: <?= $tx['quantity'] > 0 ? '#34d399' : '#f87171' ?>;">
                                <?= ($tx['quantity'] > 0 ? '+' : '') . $tx['quantity'] ?> <?= e($tx['unit']) ?>
                            </td>
                            <td style="font-weight:700; color:var(--text-main);"><?= number_format($tx['balance_after']) ?></td>
                            <td>
                                <?php
                                if ($tx['supplier_name']) echo '<i class="fa-solid fa-truck-field" style="color:var(--accent-teal);"></i> ' . e($tx['supplier_name']);
                                elseif ($tx['employee_name']) echo '<i class="fa-solid fa-user-shield" style="color:var(--accent-cyan);"></i> ' . e($tx['employee_name']);
                                else echo '-';
                                ?>
                            </td>
                            <td style="font-size:12px; color:var(--text-muted); max-width:200px;"><?= e($tx['remarks'] ?: '-') ?></td>
                            <td style="font-size:12px; color:var(--text-muted);"><?= format_date($tx['transaction_date']) ?></td>
                            <td style="font-size:12px; color:var(--text-muted);"><?= e($tx['user_name']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
