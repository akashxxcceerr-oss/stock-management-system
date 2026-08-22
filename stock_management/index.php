<?php
/**
 * Main Dashboard — Enterprise Control Center
 */

$pageTitle = 'Control Center Dashboard';
require_once __DIR__ . '/includes/header.php';

$db = getDB();

// 1. Total Products
$totalProducts = (int)$db->query("SELECT COUNT(*) FROM products WHERE status = 'active'")->fetchColumn();

// 2. Total Stock Value Calculation
$totalValue = (float)$db->query("
    SELECT SUM(p.quantity * COALESCE((SELECT si.unit_price FROM stock_in si WHERE si.product_id = p.id ORDER BY si.id DESC LIMIT 1), 0))
    FROM products p
    WHERE p.status = 'active'
")->fetchColumn();

// 3. Low Stock Items Count
$lowStockCount = (int)$db->query("SELECT COUNT(*) FROM products WHERE status = 'active' AND quantity <= minimum_stock_level")->fetchColumn();

// 4. Currently Assigned Assets Out Count
$assignedAssetsCount = (int)$db->query("SELECT COALESCE(SUM(quantity - returned_quantity), 0) FROM stock_assign WHERE status != 'returned'")->fetchColumn();

// 5. Fetch Recent Transactions
$recentTxStmt = $db->query("
    SELECT t.*, p.product_name, p.product_code, u.name as user_name
    FROM transactions t
    JOIN products p ON t.product_id = p.id
    JOIN users u ON t.created_by = u.id
    ORDER BY t.id DESC
    LIMIT 8
");
$recentTx = $recentTxStmt->fetchAll();

// 6. Fetch Low Stock Products
$lowStockStmt = $db->query("
    SELECT p.*, c.category_name
    FROM products p
    JOIN categories c ON p.category_id = c.id
    WHERE p.status = 'active' AND p.quantity <= p.minimum_stock_level
    ORDER BY p.quantity ASC
");
$lowStockItems = $lowStockStmt->fetchAll();
?>

<div class="top-bar">
    <div class="page-header">
        <h1>Dashboard Overview</h1>
        <p>Real-time enterprise inventory intelligence & double-entry ledger analytics</p>
    </div>
    <div class="quick-actions">
        <a href="<?= BASE_URL ?>/stock_in.php" class="btn btn-primary"><i class="fa-solid fa-arrow-down-to-bracket"></i> Stock In</a>
        <a href="<?= BASE_URL ?>/stock_out.php" class="btn btn-secondary"><i class="fa-solid fa-arrow-up-from-bracket"></i> Stock Out</a>
        <a href="<?= BASE_URL ?>/stock_assign.php" class="btn btn-success"><i class="fa-solid fa-handshake-angle"></i> Assign Asset</a>
    </div>
</div>

<!-- Stats Grid -->
<div class="grid-stats">
    <div class="stat-card">
        <div class="stat-header">
            <span class="stat-title">Total Active Products</span>
            <span class="stat-icon" style="color:var(--primary);"><i class="fa-solid fa-boxes-stacked"></i></span>
        </div>
        <div class="stat-value"><?= number_format($totalProducts) ?></div>
        <div class="stat-meta">Active catalog SKUs</div>
    </div>

    <div class="stat-card accent-teal">
        <div class="stat-header">
            <span class="stat-title">Total Inventory Value</span>
            <span class="stat-icon" style="color:var(--accent-teal);"><i class="fa-solid fa-wallet"></i></span>
        </div>
        <div class="stat-value"><?= format_currency($totalValue) ?></div>
        <div class="stat-meta">Asset valuation at cost</div>
    </div>

    <div class="stat-card accent-warning">
        <div class="stat-header">
            <span class="stat-title">Low Stock Alerts</span>
            <span class="stat-icon" style="color:var(--warning);"><i class="fa-solid fa-triangle-exclamation"></i></span>
        </div>
        <div class="stat-value"><?= number_format($lowStockCount) ?></div>
        <div class="stat-meta">At or below reorder threshold</div>
    </div>

    <div class="stat-card accent-danger">
        <div class="stat-header">
            <span class="stat-title">Assets Under Custody</span>
            <span class="stat-icon" style="color:var(--danger);"><i class="fa-solid fa-user-shield"></i></span>
        </div>
        <div class="stat-value"><?= number_format($assignedAssetsCount) ?></div>
        <div class="stat-meta">Assigned to employees</div>
    </div>
</div>

<div style="display: grid; grid-template-columns: 2fr 1fr; gap: 24px;">
    <!-- Recent Transactions Table -->
    <div class="card">
        <div class="card-header">
            <div class="card-title"><i class="fa-solid fa-clock-rotate-left"></i> Recent Inventory Ledger</div>
            <a href="<?= BASE_URL ?>/transactions.php" class="btn btn-sm btn-secondary">View Full Ledger</a>
        </div>
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Reference</th>
                        <th>Type</th>
                        <th>Product</th>
                        <th>Qty</th>
                        <th>Balance</th>
                        <th>Date & Time</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($recentTx)): ?>
                        <tr><td colspan="6" style="text-align:center; color:var(--text-muted);">No transaction activity recorded yet.</td></tr>
                    <?php else: ?>
                        <?php foreach ($recentTx as $tx): ?>
                            <tr>
                                <td><code style="color:var(--accent-cyan);"><?= e($tx['reference_number']) ?></code></td>
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
                                    <?= ($tx['quantity'] > 0 ? '+' : '') . $tx['quantity'] ?>
                                </td>
                                <td style="font-weight:600;"><?= number_format($tx['balance_after']) ?></td>
                                <td style="font-size:12px; color:var(--text-muted);"><?= format_date($tx['transaction_date']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Low Stock Sidebar Alert -->
    <div class="card">
        <div class="card-header">
            <div class="card-title" style="color:var(--warning);"><i class="fa-solid fa-bell"></i> Reorder Alerts</div>
        </div>
        <?php if (empty($lowStockItems)): ?>
            <div style="text-align:center; padding:20px; color:var(--accent-teal);">
                <i class="fa-solid fa-circle-check" style="font-size:32px; margin-bottom:8px; display:block;"></i>
                All stock levels are healthy!
            </div>
        <?php else: ?>
            <div style="display:flex; flex-direction:column; gap:12px;">
                <?php foreach ($lowStockItems as $item): ?>
                    <div style="padding:12px; background:rgba(245, 158, 11, 0.1); border:1px solid rgba(245, 158, 11, 0.2); border-radius:var(--radius-sm); display:flex; justify-content:space-between; align-items:center;">
                        <div>
                            <strong style="font-size:13px; color:var(--text-main); display:block;"><?= e($item['product_name']) ?></strong>
                            <span style="font-size:11px; color:var(--text-muted);"><?= e($item['category_name']) ?></span>
                        </div>
                        <div style="text-align:right;">
                            <span class="badge badge-warning"><?= $item['quantity'] ?> Left</span>
                            <div style="font-size:10px; color:var(--text-muted); margin-top:2px;">Min: <?= $item['minimum_stock_level'] ?></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
