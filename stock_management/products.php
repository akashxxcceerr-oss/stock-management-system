<?php
/**
 * Products Management & Inventory Catalog
 */

$pageTitle = 'Products Catalog';
require_once __DIR__ . '/includes/header.php';

$db = getDB();

// Handle Actions: Create, Edit, Toggle Status
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $code  = strtoupper(sanitize($_POST['product_code'] ?? ''));
        $name  = sanitize($_POST['product_name'] ?? '');
        $catId = (int)($_POST['category_id'] ?? 0);
        $supId = !empty($_POST['supplier_id']) ? (int)$_POST['supplier_id'] : null;
        $unit  = sanitize($_POST['unit'] ?? 'Units');
        $minStock = (int)($_POST['minimum_stock_level'] ?? 5);
        $desc  = sanitize($_POST['description'] ?? '');

        if (empty($code) || empty($name) || $catId <= 0) {
            set_flash(ALERT_DANGER, 'Product Code, Name, and Category are required.');
        } else {
            try {
                $stmt = $db->prepare("
                    INSERT INTO products (product_code, product_name, category_id, supplier_id, unit, quantity, minimum_stock_level, description)
                    VALUES (:code, :name, :cat, :sup, :unit, 0, :min, :desc)
                ");
                $stmt->execute([
                    ':code' => $code, ':name' => $name, ':cat' => $catId,
                    ':sup' => $supId, ':unit' => $unit, ':min' => $minStock, ':desc' => $desc
                ]);
                log_activity($currentUser['id'], $currentUser['name'], 'CREATE', 'Product', (string)$db->lastInsertId(), "Created product: {$name} ({$code})");
                set_flash(ALERT_SUCCESS, "Product '{$name}' created successfully!");
            } catch (Exception $e) {
                set_flash(ALERT_DANGER, 'Error creating product: ' . $e->getMessage());
            }
        }
        header('Location: ' . BASE_URL . '/products.php');
        exit;
    }

    if ($action === 'edit') {
        $id    = (int)($_POST['id'] ?? 0);
        $code  = strtoupper(sanitize($_POST['product_code'] ?? ''));
        $name  = sanitize($_POST['product_name'] ?? '');
        $catId = (int)($_POST['category_id'] ?? 0);
        $supId = !empty($_POST['supplier_id']) ? (int)$_POST['supplier_id'] : null;
        $unit  = sanitize($_POST['unit'] ?? 'Units');
        $minStock = (int)($_POST['minimum_stock_level'] ?? 5);
        $desc  = sanitize($_POST['description'] ?? '');
        $status = $_POST['status'] ?? 'active';

        if ($id <= 0 || empty($code) || empty($name) || $catId <= 0) {
            set_flash(ALERT_DANGER, 'Invalid product parameters.');
        } else {
            try {
                $stmt = $db->prepare("
                    UPDATE products
                    SET product_code = :code, product_name = :name, category_id = :cat, supplier_id = :sup,
                        unit = :unit, minimum_stock_level = :min, description = :desc, status = :st, updated_at = NOW()
                    WHERE id = :id
                ");
                $stmt->execute([
                    ':code' => $code, ':name' => $name, ':cat' => $catId, ':sup' => $supId,
                    ':unit' => $unit, ':min' => $minStock, ':desc' => $desc, ':st' => $status, ':id' => $id
                ]);
                log_activity($currentUser['id'], $currentUser['name'], 'UPDATE', 'Product', (string)$id, "Updated product: {$name}");
                set_flash(ALERT_SUCCESS, "Product '{$name}' updated successfully!");
            } catch (Exception $e) {
                set_flash(ALERT_DANGER, 'Error updating product: ' . $e->getMessage());
            }
        }
        header('Location: ' . BASE_URL . '/products.php');
        exit;
    }
}

// Fetch categories and suppliers for dropdowns
$categories = $db->query("SELECT * FROM categories WHERE status = 'active' ORDER BY category_name ASC")->fetchAll();
$suppliers  = $db->query("SELECT * FROM suppliers WHERE status = 'active' ORDER BY company_name ASC")->fetchAll();

// Search & Filter Query
$search = sanitize($_GET['search'] ?? '');
$catFilter = (int)($_GET['category'] ?? 0);

$query = "
    SELECT p.*, c.category_name, s.company_name as supplier_name
    FROM products p
    JOIN categories c ON p.category_id = c.id
    LEFT JOIN suppliers s ON p.supplier_id = s.id
    WHERE 1=1
";
$params = [];

if ($search !== '') {
    $query .= " AND (p.product_name LIKE :s OR p.product_code LIKE :s OR p.description LIKE :s)";
    $params[':s'] = "%{$search}%";
}

if ($catFilter > 0) {
    $query .= " AND p.category_id = :c";
    $params[':c'] = $catFilter;
}

$query .= " ORDER BY p.id DESC";
$stmt = $db->prepare($query);
$stmt->execute($params);
$products = $stmt->fetchAll();
?>

<div class="top-bar">
    <div class="page-header">
        <h1>Products Catalog</h1>
        <p>Manage product inventory items, stock thresholds, and suppliers</p>
    </div>
    <div class="quick-actions">
        <button class="btn btn-primary" onclick="openModal('modalAddProduct')"><i class="fa-solid fa-plus"></i> Add New Product</button>
    </div>
</div>

<!-- Search & Filter Controls -->
<div class="card" style="padding:16px; margin-bottom:24px;">
    <form method="GET" action="" style="display:flex; gap:16px; align-items:center; flex-wrap:wrap;">
        <div style="flex:1; min-width:240px;">
            <input type="text" name="search" class="form-control" placeholder="Search by name, code, or keyword..." value="<?= e($search) ?>">
        </div>
        <div style="width:200px;">
            <select name="category" class="form-control" onchange="this.form.submit()">
                <option value="0">All Categories</option>
                <?php foreach ($categories as $cat): ?>
                    <option value="<?= $cat['id'] ?>" <?= $catFilter === (int)$cat['id'] ? 'selected' : '' ?>><?= e($cat['category_name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <button type="submit" class="btn btn-secondary"><i class="fa-solid fa-magnifying-glass"></i> Filter</button>
        <?php if ($search !== '' || $catFilter > 0): ?>
            <a href="<?= BASE_URL ?>/products.php" class="btn btn-sm btn-secondary">Clear</a>
        <?php endif; ?>
    </form>
</div>

<!-- Products Table -->
<div class="card">
    <div class="table-responsive">
        <table class="data-table">
            <thead>
                <tr>
                    <th>SKU / Code</th>
                    <th>Product Details</th>
                    <th>Category</th>
                    <th>Supplier</th>
                    <th>Stock Qty</th>
                    <th>Min Level</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($products)): ?>
                    <tr><td colspan="8" style="text-align:center; color:var(--text-muted); padding:30px;">No products found matching criteria.</td></tr>
                <?php else: ?>
                    <?php foreach ($products as $p): ?>
                        <tr>
                            <td><code style="color:var(--accent-cyan); font-weight:700;"><?= e($p['product_code']) ?></code></td>
                            <td>
                                <strong><?= e($p['product_name']) ?></strong>
                                <?php if ($p['description']): ?>
                                    <div style="font-size:12px; color:var(--text-muted);"><?= e($p['description']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td><span class="badge badge-info"><?= e($p['category_name']) ?></span></td>
                            <td><?= e($p['supplier_name'] ?? 'Unassigned') ?></td>
                            <td>
                                <?php
                                $stockBadge = 'badge-success';
                                if ($p['quantity'] == 0) $stockBadge = 'badge-danger';
                                elseif ($p['quantity'] <= $p['minimum_stock_level']) $stockBadge = 'badge-warning';
                                ?>
                                <span class="badge <?= $stockBadge ?>" style="font-size:13px;">
                                    <?= number_format($p['quantity']) ?> <?= e($p['unit']) ?>
                                </span>
                            </td>
                            <td style="color:var(--text-muted);"><?= number_format($p['minimum_stock_level']) ?> <?= e($p['unit']) ?></td>
                            <td>
                                <span class="badge <?= $p['status'] === 'active' ? 'badge-success' : 'badge-danger' ?>">
                                    <?= ucfirst($p['status']) ?>
                                </span>
                            </td>
                            <td>
                                <button class="btn btn-sm btn-secondary" onclick='editProduct(<?= json_encode($p) ?>)'><i class="fa-solid fa-pen-to-square"></i> Edit</button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal: Add Product -->
<div class="modal-overlay" id="modalAddProduct">
    <div class="modal-container">
        <div class="card-header" style="margin-bottom:16px;">
            <h3 class="card-title"><i class="fa-solid fa-box-open"></i> Add New Product</h3>
            <button onclick="closeModal('modalAddProduct')" style="background:none; border:none; color:var(--text-muted); cursor:pointer;"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <form method="POST" action="">
            <input type="hidden" name="action" value="create">
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Product Code / SKU *</label>
                    <input type="text" name="product_code" class="form-control" placeholder="e.g. PRD-2001" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Unit of Measure</label>
                    <input type="text" name="unit" class="form-control" value="Units" required>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">Product Name *</label>
                <input type="text" name="product_name" class="form-control" placeholder="Full product description title" required>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Category *</label>
                    <select name="category_id" class="form-control" required>
                        <option value="">Select Category</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= $cat['id'] ?>"><?= e($cat['category_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Supplier (Optional)</label>
                    <select name="supplier_id" class="form-control">
                        <option value="">Select Primary Supplier</option>
                        <?php foreach ($suppliers as $sup): ?>
                            <option value="<?= $sup['id'] ?>"><?= e($sup['company_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">Minimum Stock Alert Level</label>
                <input type="number" name="minimum_stock_level" class="form-control" value="5" min="0" required>
            </div>

            <div class="form-group">
                <label class="form-label">Specification / Remarks</label>
                <textarea name="description" class="form-control" rows="3" placeholder="Technical specifications, model numbers..."></textarea>
            </div>

            <div style="display:flex; justify-content:flex-end; gap:12px; margin-top:20px;">
                <button type="button" class="btn btn-secondary" onclick="closeModal('modalAddProduct')">Cancel</button>
                <button type="submit" class="btn btn-primary"><i class="fa-solid fa-check"></i> Save Product</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: Edit Product -->
<div class="modal-overlay" id="modalEditProduct">
    <div class="modal-container">
        <div class="card-header" style="margin-bottom:16px;">
            <h3 class="card-title"><i class="fa-solid fa-pen-to-square"></i> Edit Product</h3>
            <button onclick="closeModal('modalEditProduct')" style="background:none; border:none; color:var(--text-muted); cursor:pointer;"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <form method="POST" action="">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="id" id="edit_id">

            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Product Code / SKU *</label>
                    <input type="text" name="product_code" id="edit_code" class="form-control" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Unit of Measure</label>
                    <input type="text" name="unit" id="edit_unit" class="form-control" required>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">Product Name *</label>
                <input type="text" name="product_name" id="edit_name" class="form-control" required>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Category *</label>
                    <select name="category_id" id="edit_cat" class="form-control" required>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= $cat['id'] ?>"><?= e($cat['category_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Supplier</label>
                    <select name="supplier_id" id="edit_sup" class="form-control">
                        <option value="">Select Primary Supplier</option>
                        <?php foreach ($suppliers as $sup): ?>
                            <option value="<?= $sup['id'] ?>"><?= e($sup['company_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Minimum Stock Alert Level</label>
                    <input type="number" name="minimum_stock_level" id="edit_min" class="form-control" min="0" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Status</label>
                    <select name="status" id="edit_status" class="form-control">
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                    </select>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">Specification / Remarks</label>
                <textarea name="description" id="edit_desc" class="form-control" rows="3"></textarea>
            </div>

            <div style="display:flex; justify-content:flex-end; gap:12px; margin-top:20px;">
                <button type="button" class="btn btn-secondary" onclick="closeModal('modalEditProduct')">Cancel</button>
                <button type="submit" class="btn btn-primary"><i class="fa-solid fa-check"></i> Update Changes</button>
            </div>
        </form>
    </div>
</div>

<script>
function editProduct(p) {
    document.getElementById('edit_id').value = p.id;
    document.getElementById('edit_code').value = p.product_code;
    document.getElementById('edit_name').value = p.product_name;
    document.getElementById('edit_unit').value = p.unit;
    document.getElementById('edit_cat').value = p.category_id;
    document.getElementById('edit_sup').value = p.supplier_id || '';
    document.getElementById('edit_min').value = p.minimum_stock_level;
    document.getElementById('edit_status').value = p.status;
    document.getElementById('edit_desc').value = p.description || '';
    openModal('modalEditProduct');
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
