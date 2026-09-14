<?php
/**
 * Main Dashboard — Enterprise Control Center v2.0
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
    LIMIT 6
");
$lowStockItems = $lowStockStmt->fetchAll();

// Time-based greeting
$hour = (int)date('G');
if ($hour < 12) {
    $greeting = "Good morning";
} elseif ($hour < 18) {
    $greeting = "Good afternoon";
} else {
    $greeting = "Good evening";
}
?>

<!-- Welcome & Overview Banner -->
<div class="welcome-banner">
    <div>
        <h2><?= $greeting ?>, <?= e($currentUser['name']) ?> 👋</h2>
        <p>Enterprise inventory tracking, audit trail & valuation ledger are fully synchronized.</p>
    </div>
    <div class="quick-actions">
        <a href="<?= BASE_URL ?>/stock_in.php" class="btn btn-primary"><i class="fa-solid fa-arrow-down-to-bracket"></i> Stock In</a>
        <a href="<?= BASE_URL ?>/stock_out.php" class="btn btn-secondary"><i class="fa-solid fa-arrow-up-from-bracket"></i> Stock Out</a>
        <a href="<?= BASE_URL ?>/stock_assign.php" class="btn btn-secondary"><i class="fa-solid fa-handshake-angle"></i> Assign Asset</a>
    </div>
</div>

<!-- Stats Grid -->
<div class="grid-stats">
    <div class="stat-card">
        <div class="stat-header">
            <span class="stat-title">Catalog SKUs</span>
            <div class="stat-icon" style="color:var(--primary);"><i class="fa-solid fa-boxes-stacked"></i></div>
        </div>
        <div class="stat-value"><?= number_format($totalProducts) ?></div>
        <div class="stat-meta"><i class="fa-solid fa-circle-check" style="color:var(--accent-teal); font-size:10px;"></i> Active products in registry</div>
    </div>

    <div class="stat-card accent-teal">
        <div class="stat-header">
            <span class="stat-title">Total Inventory Value</span>
            <div class="stat-icon" style="color:var(--accent-teal);"><i class="fa-solid fa-wallet"></i></div>
        </div>
        <div class="stat-value"><?= format_currency($totalValue) ?></div>
        <div class="stat-meta"><i class="fa-solid fa-chart-line" style="color:var(--accent-teal); font-size:10px;"></i> Valuation based on latest cost</div>
    </div>

    <div class="stat-card accent-warning">
        <div class="stat-header">
            <span class="stat-title">Low Stock Reorders</span>
            <div class="stat-icon" style="color:var(--warning);"><i class="fa-solid fa-triangle-exclamation"></i></div>
        </div>
        <div class="stat-value"><?= number_format($lowStockCount) ?></div>
        <div class="stat-meta"><i class="fa-solid fa-bell" style="color:var(--warning); font-size:10px;"></i> At or below minimum threshold</div>
    </div>

    <div class="stat-card accent-cyan">
        <div class="stat-header">
            <span class="stat-title">Assets Under Custody</span>
            <div class="stat-icon" style="color:var(--accent-cyan);"><i class="fa-solid fa-user-shield"></i></div>
        </div>
        <div class="stat-value"><?= number_format($assignedAssetsCount) ?></div>
        <div class="stat-meta"><i class="fa-solid fa-arrow-right-arrow-left" style="color:var(--accent-cyan); font-size:10px;"></i> Deployed with staff custody</div>
    </div>
</div>

<!-- Two-Column Operational Layout -->
<div style="display: grid; grid-template-columns: 2fr 1fr; gap: 24px; align-items: start;">
    <!-- Recent Transactions Table -->
    <div class="card">
        <div class="card-header">
            <div>
                <div class="card-title"><i class="fa-solid fa-clock-rotate-left" style="color:var(--primary);"></i> Recent Activity Ledger</div>
                <div class="card-subtitle">Latest movements across warehouses and employees</div>
            </div>
            <a href="<?= BASE_URL ?>/transactions.php" class="btn btn-sm btn-secondary">Full Ledger <i class="fa-solid fa-arrow-right" style="font-size:11px;"></i></a>
        </div>
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Reference</th>
                        <th>Type</th>
                        <th>Product</th>
                        <th>Delta</th>
                        <th>Balance</th>
                        <th>Timestamp</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($recentTx)): ?>
                        <tr>
                            <td colspan="6">
                                <div class="empty-state">
                                    <i class="fa-solid fa-receipt"></i>
                                    <h3>No transactions yet</h3>
                                    <p>Incoming and outgoing stock movements will appear here automatically.</p>
                                </div>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($recentTx as $tx): ?>
                            <tr>
                                <td>
                                    <span class="badge badge-neutral" style="font-family:monospace; letter-spacing:0.5px;"><?= e($tx['reference_number']) ?></span>
                                </td>
                                <td>
                                    <?php
                                    $badgeClass = 'badge-info';
                                    $iconClass = 'fa-arrow-right-arrow-left';
                                    if ($tx['transaction_type'] === 'STOCK_IN') {
                                        $badgeClass = 'badge-success';
                                        $iconClass = 'fa-arrow-down-to-bracket';
                                    } elseif ($tx['transaction_type'] === 'STOCK_OUT') {
                                        $badgeClass = 'badge-warning';
                                        $iconClass = 'fa-arrow-up-from-bracket';
                                    } elseif ($tx['transaction_type'] === 'ASSIGNMENT') {
                                        $badgeClass = 'badge-info';
                                        $iconClass = 'fa-handshake-angle';
                                    } elseif ($tx['transaction_type'] === 'RETURN') {
                                        $badgeClass = 'badge-success';
                                        $iconClass = 'fa-rotate-left';
                                    } elseif ($tx['transaction_type'] === 'DAMAGE') {
                                        $badgeClass = 'badge-danger';
                                        $iconClass = 'fa-triangle-exclamation';
                                    }
                                    ?>
                                    <span class="badge <?= $badgeClass ?>"><i class="fa-solid <?= $iconClass ?>" style="margin-right:4px;"></i><?= e($tx['transaction_type']) ?></span>
                                </td>
                                <td>
                                    <strong style="color:var(--text-main);"><?= e($tx['product_name']) ?></strong>
                                    <div style="font-size:11px; color:var(--text-muted); font-family:monospace;"><?= e($tx['product_code']) ?></div>
                                </td>
                                <td>
                                    <span style="font-weight:700; font-variant-numeric:tabular-nums; color: <?= $tx['quantity'] > 0 ? 'var(--accent-teal)' : 'var(--danger)' ?>;">
                                        <?= ($tx['quantity'] > 0 ? '+' : '') . $tx['quantity'] ?>
                                    </span>
                                </td>
                                <td>
                                    <span style="font-weight:600; font-variant-numeric:tabular-nums;"><?= number_format($tx['balance_after']) ?></span>
                                </td>
                                <td>
                                    <span style="font-size:12px; color:var(--text-muted);"><?= format_date($tx['transaction_date']) ?></span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Low Stock Sidebar Alert Card -->
    <div class="card">
        <div class="card-header">
            <div>
                <div class="card-title" style="color:var(--warning);"><i class="fa-solid fa-bell"></i> Reorder Alerts</div>
                <div class="card-subtitle">Items at critical levels</div>
            </div>
            <?php if (!empty($lowStockItems)): ?>
                <a href="<?= BASE_URL ?>/stock_in.php" class="btn btn-xs btn-primary"><i class="fa-solid fa-plus"></i> Restock</a>
            <?php endif; ?>
        </div>

        <?php if (empty($lowStockItems)): ?>
            <div class="empty-state" style="padding:24px 12px;">
                <i class="fa-solid fa-circle-check" style="color:var(--accent-teal); font-size:36px;"></i>
                <h3 style="margin-top:12px;">All Stock Healthy</h3>
                <p>Every SKU is above its defined minimum replenishment threshold.</p>
            </div>
        <?php else: ?>
            <div style="display:flex; flex-direction:column; gap:10px;">
                <?php foreach ($lowStockItems as $item): ?>
                    <div style="padding:12px 14px; background:rgba(245, 158, 11, 0.07); border:1px solid rgba(245, 158, 11, 0.2); border-radius:var(--radius-md); display:flex; justify-content:space-between; align-items:center; transition:var(--transition);" onmouseover="this.style.background='rgba(245, 158, 11, 0.12)'" onmouseout="this.style.background='rgba(245, 158, 11, 0.07)'">
                        <div>
                            <strong style="font-size:13px; color:var(--text-main); display:block;"><?= e($item['product_name']) ?></strong>
                            <span style="font-size:11px; color:var(--text-muted);"><?= e($item['category_name']) ?></span>
                        </div>
                        <div style="text-align:right;">
                            <span class="badge badge-warning" style="font-weight:700;"><?= $item['quantity'] ?> Left</span>
                            <div style="font-size:10px; color:var(--text-muted); margin-top:3px;">Min: <?= $item['minimum_stock_level'] ?></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
