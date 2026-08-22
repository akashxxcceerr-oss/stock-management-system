<?php
/**
 * Categories Management
 */

$pageTitle = 'Category Management';
require_once __DIR__ . '/includes/header.php';

$db = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $name = sanitize($_POST['category_name'] ?? '');
        $desc = sanitize($_POST['description'] ?? '');

        if (empty($name)) {
            set_flash(ALERT_DANGER, 'Category name is required.');
        } else {
            try {
                $stmt = $db->prepare("INSERT INTO categories (category_name, description) VALUES (:n, :d)");
                $stmt->execute([':n' => $name, ':d' => $desc]);
                log_activity($currentUser['id'], $currentUser['name'], 'CREATE', 'Category', (string)$db->lastInsertId(), "Created category: {$name}");
                set_flash(ALERT_SUCCESS, "Category '{$name}' created!");
            } catch (Exception $e) {
                set_flash(ALERT_DANGER, 'Error: ' . $e->getMessage());
            }
        }
        header('Location: ' . BASE_URL . '/categories.php');
        exit;
    }

    if ($action === 'edit') {
        $id   = (int)($_POST['id'] ?? 0);
        $name = sanitize($_POST['category_name'] ?? '');
        $desc = sanitize($_POST['description'] ?? '');
        $status = $_POST['status'] ?? 'active';

        if ($id <= 0 || empty($name)) {
            set_flash(ALERT_DANGER, 'Invalid category parameters.');
        } else {
            try {
                $stmt = $db->prepare("UPDATE categories SET category_name = :n, description = :d, status = :s, updated_at = NOW() WHERE id = :id");
                $stmt->execute([':n' => $name, ':d' => $desc, ':s' => $status, ':id' => $id]);
                log_activity($currentUser['id'], $currentUser['name'], 'UPDATE', 'Category', (string)$id, "Updated category: {$name}");
                set_flash(ALERT_SUCCESS, "Category updated!");
            } catch (Exception $e) {
                set_flash(ALERT_DANGER, 'Error: ' . $e->getMessage());
            }
        }
        header('Location: ' . BASE_URL . '/categories.php');
        exit;
    }
}

$categories = $db->query("
    SELECT c.*, COUNT(p.id) as product_count
    FROM categories c
    LEFT JOIN products p ON c.id = p.category_id
    GROUP BY c.id
    ORDER BY c.category_name ASC
")->fetchAll();
?>

<div class="top-bar">
    <div class="page-header">
        <h1>Categories</h1>
        <p>Organize products into hierarchical classification groups</p>
    </div>
    <div class="quick-actions">
        <button class="btn btn-primary" onclick="openModal('modalAddCategory')"><i class="fa-solid fa-plus"></i> Add Category</button>
    </div>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Category Name</th>
                    <th>Description</th>
                    <th>Associated Products</th>
                    <th>Status</th>
                    <th>Created At</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($categories)): ?>
                    <tr><td colspan="6" style="text-align:center; color:var(--text-muted);">No categories added yet.</td></tr>
                <?php else: ?>
                    <?php foreach ($categories as $cat): ?>
                        <tr>
                            <td><strong><?= e($cat['category_name']) ?></strong></td>
                            <td style="color:var(--text-muted);"><?= e($cat['description'] ?: 'No description') ?></td>
                            <td><span class="badge badge-info"><?= $cat['product_count'] ?> Products</span></td>
                            <td><span class="badge <?= $cat['status'] === 'active' ? 'badge-success' : 'badge-danger' ?>"><?= ucfirst($cat['status']) ?></span></td>
                            <td style="font-size:12px; color:var(--text-muted);"><?= format_date($cat['created_at']) ?></td>
                            <td>
                                <button class="btn btn-sm btn-secondary" onclick='editCategory(<?= json_encode($cat) ?>)'><i class="fa-solid fa-pen"></i> Edit</button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal: Add Category -->
<div class="modal-overlay" id="modalAddCategory">
    <div class="modal-container">
        <div class="card-header">
            <h3 class="card-title">Add Category</h3>
            <button onclick="closeModal('modalAddCategory')" style="background:none; border:none; color:var(--text-muted); cursor:pointer;"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <form method="POST" action="">
            <input type="hidden" name="action" value="create">
            <div class="form-group">
                <label class="form-label">Category Name *</label>
                <input type="text" name="category_name" class="form-control" placeholder="e.g. Office Stationery" required>
            </div>
            <div class="form-group">
                <label class="form-label">Description</label>
                <textarea name="description" class="form-control" rows="3" placeholder="Category purpose and scope..."></textarea>
            </div>
            <div style="display:flex; justify-content:flex-end; gap:12px;">
                <button type="button" class="btn btn-secondary" onclick="closeModal('modalAddCategory')">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Category</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: Edit Category -->
<div class="modal-overlay" id="modalEditCategory">
    <div class="modal-container">
        <div class="card-header">
            <h3 class="card-title">Edit Category</h3>
            <button onclick="closeModal('modalEditCategory')" style="background:none; border:none; color:var(--text-muted); cursor:pointer;"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <form method="POST" action="">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="id" id="edit_cat_id">
            <div class="form-group">
                <label class="form-label">Category Name *</label>
                <input type="text" name="category_name" id="edit_cat_name" class="form-control" required>
            </div>
            <div class="form-group">
                <label class="form-label">Status</label>
                <select name="status" id="edit_cat_status" class="form-control">
                    <option value="active">Active</option>
                    <option value="inactive">Inactive</option>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">Description</label>
                <textarea name="description" id="edit_cat_desc" class="form-control" rows="3"></textarea>
            </div>
            <div style="display:flex; justify-content:flex-end; gap:12px;">
                <button type="button" class="btn btn-secondary" onclick="closeModal('modalEditCategory')">Cancel</button>
                <button type="submit" class="btn btn-primary">Update Category</button>
            </div>
        </form>
    </div>
</div>

<script>
function editCategory(c) {
    document.getElementById('edit_cat_id').value = c.id;
    document.getElementById('edit_cat_name').value = c.category_name;
    document.getElementById('edit_cat_desc').value = c.description || '';
    document.getElementById('edit_cat_status').value = c.status;
    openModal('modalEditCategory');
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
