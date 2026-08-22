<?php
/**
 * Analytics & Inventory Reports
 */

$pageTitle = 'Analytics & Reports';
require_once __DIR__ . '/includes/header.php';

$db = getDB();

// 1. Stock Valuation by Category
$catValuation = $db->query("
    SELECT c.category_name, COUNT(p.id) as total_skus, SUM(p.quantity) as total_qty,
           SUM(p.quantity * COALESCE((SELECT si.unit_price FROM stock_in si WHERE si.product_id = p.id ORDER BY si.id DESC LIMIT 1), 0)) as total_val
    FROM categories c
    LEFT JOIN products p ON c.id = p.category_id AND p.status = 'active'
    GROUP BY c.id
    ORDER BY total_val DESC
")->fetchAll();

// 2. Employee Asset Custody Report
$employeeCustody = $db->query("
    SELECT e.name as employee_name, e.employee_code, e.department,
           p.product_name, p.product_code, (sa.quantity - sa.returned_quantity) as active_qty,
           sa.assignment_date, sa.reference_number
    FROM stock_assign sa
    JOIN employees e ON sa.employee_id = e.id
    JOIN products p ON sa.product_id = p.id
    WHERE sa.status != 'returned'
    ORDER BY e.name ASC
")->fetchAll();

// 3. Movement Summary (Last 30 Days)
$txSummary = $db->query("
    SELECT transaction_type, COUNT(*) as tx_count, ABS(SUM(quantity)) as total_units
    FROM transactions
    GROUP BY transaction_type
")->fetchAll();
?>

<div class="top-bar">
    <div class="page-header">
        <h1>Analytics & Inventory Reports</h1>
        <p>Valuation breakdown, asset custody audit, and operational insights</p>
    </div>
    <div class="quick-actions">
        <button onclick="window.print()" class="btn btn-secondary"><i class="fa-solid fa-print"></i> Print Report</button>
    </div>
</div>

<div class="grid-stats">
    <?php foreach ($txSummary as $ts): ?>
        <div class="stat-card">
            <div class="stat-header">
                <span class="stat-title"><?= e($ts['transaction_type']) ?> Volume</span>
                <span class="stat-icon"><i class="fa-solid fa-chart-bar"></i></span>
            </div>
            <div class="stat-value"><?= number_format($ts['tx_count']) ?> Transactions</div>
            <div class="stat-meta"><?= number_format($ts['total_units']) ?> Units Total</div>
        </div>
    <?php endforeach; ?>
</div>

<!-- Category Valuation Report -->
<div class="card">
    <div class="card-header">
        <h3 class="card-title"><i class="fa-solid fa-chart-pie"></i> Inventory Valuation by Category</h3>
    </div>
    <div class="table-responsive">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Category</th>
                    <th>Total Product SKUs</th>
                    <th>Total In-Stock Units</th>
                    <th>Estimated Valuation (₹)</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $grandTotalVal = 0;
                $grandTotalQty = 0;
                foreach ($catValuation as $cv):
                    $grandTotalVal += (float)$cv['total_val'];
                    $grandTotalQty += (int)$cv['total_qty'];
                ?>
                    <tr>
                        <td><strong><?= e($cv['category_name']) ?></strong></td>
                        <td><span class="badge badge-info"><?= $cv['total_skus'] ?> SKUs</span></td>
                        <td><strong><?= number_format($cv['total_qty'] ?: 0) ?> Units</strong></td>
                        <td style="font-weight:700; color:var(--accent-teal);"><?= format_currency((float)$cv['total_val']) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr style="background:rgba(31, 41, 61, 0.8); font-weight:800;">
                    <td colspan="2">GRAND TOTAL VALUATION</td>
                    <td><?= number_format($grandTotalQty) ?> Units</td>
                    <td style="color:#34d399; font-size:16px;"><?= format_currency($grandTotalVal) ?></td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>

<!-- Employee Custody Audit -->
<div class="card">
    <div class="card-header">
        <h3 class="card-title"><i class="fa-solid fa-user-shield"></i> Active Employee Asset Custody Audit</h3>
    </div>
    <div class="table-responsive">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Ref #</th>
                    <th>Employee Name</th>
                    <th>Department</th>
                    <th>Asset Name & SKU</th>
                    <th>Qty Issued</th>
                    <th>Issued Date</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($employeeCustody)): ?>
                    <tr><td colspan="6" style="text-align:center; color:var(--text-muted);">No active assets issued to employees.</td></tr>
                <?php else: ?>
                    <?php foreach ($employeeCustody as $ec): ?>
                        <tr>
                            <td><code style="color:var(--accent-cyan); font-weight:700;"><?= e($ec['reference_number']) ?></code></td>
                            <td><strong><?= e($ec['employee_name']) ?></strong> (<?= e($ec['employee_code']) ?>)</td>
                            <td><?= e($ec['department']) ?></td>
                            <td>
                                <strong><?= e($ec['product_name']) ?></strong>
                                <div style="font-size:11px; color:var(--text-muted);"><?= e($ec['product_code']) ?></div>
                            </td>
                            <td><span class="badge badge-warning"><?= $ec['active_qty'] ?> Units</span></td>
                            <td style="font-size:12px; color:var(--text-muted);"><?= format_date_only($ec['assignment_date']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
