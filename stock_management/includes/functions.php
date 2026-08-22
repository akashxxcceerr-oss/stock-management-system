<?php
/**
 * System Core Helpers, Auth Engine & Transaction Engine
 */

require_once __DIR__ . '/../config/db.php';

function start_system_session(): void {
    if (session_status() === PHP_SESSION_NONE) {
        ini_set('session.gc_maxlifetime', SESSION_LIFETIME);
        session_set_cookie_params(SESSION_LIFETIME);
        session_start();
    }
}

function sanitize(string $data): string {
    return trim($data);
}

function e(?string $data): string {
    return htmlspecialchars((string)($data ?? ''), ENT_QUOTES, 'UTF-8');
}

function format_currency(float $amount): string {
    return CURRENCY_SYMBOL . ' ' . number_format($amount, 2);
}

function format_date(?string $dateStr): string {
    if (!$dateStr) return '-';
    $timestamp = strtotime($dateStr);
    return $timestamp ? date('d M Y, h:i A', $timestamp) : $dateStr;
}

function format_date_only(?string $dateStr): string {
    if (!$dateStr) return '-';
    $timestamp = strtotime($dateStr);
    return $timestamp ? date('d M Y', $timestamp) : $dateStr;
}

function generate_reference(string $prefix): string {
    return strtoupper($prefix) . '-' . date('Ymd') . '-' . str_pad((string)random_int(1000, 9999), 4, '0', STR_PAD_LEFT);
}

// Session & Auth Helpers
function is_logged_in(): bool {
    start_system_session();
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

function require_login(): void {
    if (!is_logged_in()) {
        set_flash(ALERT_WARNING, 'Please log in to access the application.');
        header('Location: ' . BASE_URL . '/login.php');
        exit;
    }
}

function require_role(array $allowedRoles): void {
    require_login();
    $userRole = $_SESSION['user_role'] ?? 'staff';
    if (!in_array($userRole, $allowedRoles, true)) {
        set_flash(ALERT_DANGER, 'Unauthorized access attempt. Insufficient permissions.');
        header('Location: ' . BASE_URL . '/index.php');
        exit;
    }
}

function get_logged_user(): array {
    start_system_session();
    return [
        'id'       => $_SESSION['user_id'] ?? 0,
        'name'     => $_SESSION['user_name'] ?? 'Guest',
        'username' => $_SESSION['username'] ?? '',
        'role'     => $_SESSION['user_role'] ?? 'staff',
        'email'    => $_SESSION['user_email'] ?? '',
    ];
}

function set_flash(string $type, string $message): void {
    start_system_session();
    $_SESSION['flash'] = [
        'type' => $type,
        'message' => $message,
    ];
}

function get_flash(): ?array {
    start_system_session();
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

function log_activity(?int $userId, ?string $userName, string $action, ?string $module = null, ?string $recordId = null, ?string $description = null): void {
    try {
        $db = getDB();
        $stmt = $db->prepare("
            INSERT INTO activity_log (user_id, user_name, action, module, record_id, description, ip_address, user_agent)
            VALUES (:uid, :uname, :act, :mod, :rec, :desc, :ip, :ua)
        ");
        $stmt->execute([
            ':uid'   => $userId,
            ':uname' => $userName,
            ':act'   => $action,
            ':mod'   => $module,
            ':rec'   => $recordId,
            ':desc'  => $description,
            ':ip'    => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
            ':ua'    => $_SERVER['HTTP_USER_AGENT'] ?? 'CLI/Script'
        ]);
    } catch (Exception $ex) {
        // Silently catch activity log failures
    }
}

// ----------------------------------------------------
// TRANSACTION ENGINE
// ----------------------------------------------------

/**
 * 1. Stock In (Procurement / Restock)
 */
function stock_in_transaction(int $productId, ?int $supplierId, int $qty, float $unitPrice, string $ref, string $date, string $remarks, int $userId): array {
    if ($qty <= 0) {
        return ['success' => false, 'message' => 'Quantity must be greater than zero.'];
    }
    $db = getDB();
    try {
        $db->beginTransaction();
        $totalCost = $qty * $unitPrice;

        $inStmt = $db->prepare("
            INSERT INTO stock_in (reference_number, product_id, supplier_id, quantity, unit_price, total_cost, purchase_date, remarks, created_by)
            VALUES (:ref, :pid, :sid, :qty, :up, :tc, :dt, :rem, :uid)
        ");
        $inStmt->execute([
            ':ref' => $ref, ':pid' => $productId, ':sid' => $supplierId ?: null,
            ':qty' => $qty, ':up' => $unitPrice, ':tc' => $totalCost,
            ':dt' => $date, ':rem' => $remarks, ':uid' => $userId
        ]);

        $upd = $db->prepare("UPDATE products SET quantity = quantity + :qty, updated_at = NOW() WHERE id = :pid");
        $upd->execute([':qty' => $qty, ':pid' => $productId]);

        $balStmt = $db->prepare("SELECT quantity, product_name FROM products WHERE id = :pid");
        $balStmt->execute([':pid' => $productId]);
        $prod = $balStmt->fetch();
        $newBal = (int)($prod['quantity'] ?? 0);

        $tx = $db->prepare("
            INSERT INTO transactions (transaction_type, reference_number, product_id, quantity, balance_after, supplier_id, remarks, created_by)
            VALUES ('STOCK_IN', :ref, :pid, :qty, :bal, :sid, :rem, :uid)
        ");
        $tx->execute([
            ':ref' => $ref, ':pid' => $productId, ':qty' => $qty, ':bal' => $newBal,
            ':sid' => $supplierId ?: null, ':rem' => $remarks, ':uid' => $userId
        ]);

        $db->commit();
        log_activity($userId, $_SESSION['user_name'] ?? 'System', 'STOCK_IN', 'Stock In', $ref, "Added {$qty} units of {$prod['product_name']}");
        return ['success' => true, 'new_balance' => $newBal];
    } catch (Exception $ex) {
        if ($db->inTransaction()) $db->rollBack();
        return ['success' => false, 'message' => $ex->getMessage()];
    }
}

/**
 * 2. Stock Out (Dispatch / Sales / Consumption)
 */
function stock_out_transaction(int $productId, string $recipient, int $qty, string $ref, string $date, ?string $purpose, string $remarks, int $userId): array {
    if ($qty <= 0) {
        return ['success' => false, 'message' => 'Quantity must be greater than zero.'];
    }
    $db = getDB();
    try {
        $db->beginTransaction();

        $checkStmt = $db->prepare("SELECT quantity, product_name FROM products WHERE id = :pid FOR UPDATE");
        $checkStmt->execute([':pid' => $productId]);
        $prod = $checkStmt->fetch();
        if (!$prod) {
            throw new Exception('Product not found.');
        }

        $currentQty = (int)$prod['quantity'];
        if ($qty > $currentQty) {
            throw new Exception("Insufficient stock! Available: {$currentQty}, Requested: {$qty}");
        }

        $outStmt = $db->prepare("
            INSERT INTO stock_out (reference_number, product_id, quantity, recipient, dispatch_date, purpose, remarks, created_by)
            VALUES (:ref, :pid, :qty, :rec, :dt, :pur, :rem, :uid)
        ");
        $outStmt->execute([
            ':ref' => $ref, ':pid' => $productId, ':qty' => $qty,
            ':rec' => $recipient, ':dt' => $date, ':pur' => $purpose,
            ':rem' => $remarks, ':uid' => $userId
        ]);

        $upd = $db->prepare("UPDATE products SET quantity = quantity - :qty, updated_at = NOW() WHERE id = :pid");
        $upd->execute([':qty' => $qty, ':pid' => $productId]);

        $newBal = $currentQty - $qty;

        $tx = $db->prepare("
            INSERT INTO transactions (transaction_type, reference_number, product_id, quantity, balance_after, remarks, created_by)
            VALUES ('STOCK_OUT', :ref, :pid, :qty, :bal, :rem, :uid)
        ");
        $tx->execute([
            ':ref' => $ref, ':pid' => $productId, ':qty' => -$qty, ':bal' => $newBal,
            ':rem' => "Dispatched to: {$recipient}. {$remarks}", ':uid' => $userId
        ]);

        $db->commit();
        log_activity($userId, $_SESSION['user_name'] ?? 'System', 'STOCK_OUT', 'Stock Out', $ref, "Dispatched {$qty} units of {$prod['product_name']} to {$recipient}");
        return ['success' => true, 'new_balance' => $newBal];
    } catch (Exception $ex) {
        if ($db->inTransaction()) $db->rollBack();
        return ['success' => false, 'message' => $ex->getMessage()];
    }
}

/**
 * 3. Stock Assign (Employee Custody / Asset Allocation)
 */
function stock_assign_transaction(int $productId, int $employeeId, int $qty, string $ref, string $date, ?string $purpose, string $remarks, int $userId): array {
    if ($qty <= 0) {
        return ['success' => false, 'message' => 'Quantity must be greater than zero.'];
    }
    $db = getDB();
    try {
        $db->beginTransaction();

        $checkStmt = $db->prepare("SELECT quantity, product_name FROM products WHERE id = :pid FOR UPDATE");
        $checkStmt->execute([':pid' => $productId]);
        $prod = $checkStmt->fetch();
        if (!$prod) {
            throw new Exception('Product not found.');
        }

        $currentQty = (int)$prod['quantity'];
        if ($qty > $currentQty) {
            throw new Exception("Insufficient stock! Available: {$currentQty}, Requested: {$qty}");
        }

        $empStmt = $db->prepare("SELECT name FROM employees WHERE id = :eid");
        $empStmt->execute([':eid' => $employeeId]);
        $empName = $empStmt->fetchColumn();

        $assignStmt = $db->prepare("
            INSERT INTO stock_assign (reference_number, product_id, employee_id, quantity, returned_quantity, assignment_date, purpose, status, remarks, created_by)
            VALUES (:ref, :pid, :eid, :qty, 0, :dt, :pur, 'assigned', :rem, :uid)
        ");
        $assignStmt->execute([
            ':ref' => $ref, ':pid' => $productId, ':eid' => $employeeId,
            ':qty' => $qty, ':dt' => $date, ':pur' => $purpose,
            ':rem' => $remarks, ':uid' => $userId
        ]);

        $upd = $db->prepare("UPDATE products SET quantity = quantity - :qty, updated_at = NOW() WHERE id = :pid");
        $upd->execute([':qty' => $qty, ':pid' => $productId]);

        $newBal = $currentQty - $qty;

        $tx = $db->prepare("
            INSERT INTO transactions (transaction_type, reference_number, product_id, quantity, balance_after, employee_id, remarks, created_by)
            VALUES ('ASSIGNMENT', :ref, :pid, :qty, :bal, :eid, :rem, :uid)
        ");
        $tx->execute([
            ':ref' => $ref, ':pid' => $productId, ':qty' => -$qty, ':bal' => $newBal,
            ':eid' => $employeeId, ':rem' => "Assigned to Employee: {$empName}. {$remarks}", ':uid' => $userId
        ]);

        $db->commit();
        log_activity($userId, $_SESSION['user_name'] ?? 'System', 'ASSIGNMENT', 'Stock Assign', $ref, "Assigned {$qty} units of {$prod['product_name']} to {$empName}");
        return ['success' => true, 'new_balance' => $newBal];
    } catch (Exception $ex) {
        if ($db->inTransaction()) $db->rollBack();
        return ['success' => false, 'message' => $ex->getMessage()];
    }
}

/**
 * 4. Stock Return (Return assigned asset from employee)
 */
function stock_return_transaction(int $assignmentId, int $qty, string $conditionStatus, string $ref, string $date, string $remarks, int $userId): array {
    if ($qty <= 0) {
        return ['success' => false, 'message' => 'Return quantity must be greater than zero.'];
    }
    $db = getDB();
    try {
        $db->beginTransaction();

        $assignStmt = $db->prepare("
            SELECT sa.*, p.product_name, e.name as employee_name
            FROM stock_assign sa
            JOIN products p ON sa.product_id = p.id
            JOIN employees e ON sa.employee_id = e.id
            WHERE sa.id = :aid FOR UPDATE
        ");
        $assignStmt->execute([':aid' => $assignmentId]);
        $assign = $assignStmt->fetch();

        if (!$assign) {
            throw new Exception('Stock assignment record not found.');
        }

        $pendingQty = (int)$assign['quantity'] - (int)$assign['returned_quantity'];
        if ($qty > $pendingQty) {
            throw new Exception("Return quantity ({$qty}) exceeds assigned balance remaining ({$pendingQty}).");
        }

        $newReturnedQty = (int)$assign['returned_quantity'] + $qty;
        $newStatus = ($newReturnedQty >= (int)$assign['quantity']) ? 'returned' : 'partially_returned';

        $updAssign = $db->prepare("UPDATE stock_assign SET returned_quantity = :rq, status = :st WHERE id = :aid");
        $updAssign->execute([':rq' => $newReturnedQty, ':st' => $newStatus, ':aid' => $assignmentId]);

        $retStmt = $db->prepare("
            INSERT INTO stock_return (reference_number, assignment_id, product_id, employee_id, quantity, return_date, condition_status, remarks, created_by)
            VALUES (:ref, :aid, :pid, :eid, :qty, :dt, :cs, :rem, :uid)
        ");
        $retStmt->execute([
            ':ref' => $ref, ':aid' => $assignmentId, ':pid' => $assign['product_id'],
            ':eid' => $assign['employee_id'], ':qty' => $qty, ':dt' => $date,
            ':cs'  => $conditionStatus, ':rem' => $remarks, ':uid' => $userId
        ]);

        $newBal = 0;
        if ($conditionStatus === 'Usable') {
            $updProd = $db->prepare("UPDATE products SET quantity = quantity + :qty, updated_at = NOW() WHERE id = :pid");
            $updProd->execute([':qty' => $qty, ':pid' => $assign['product_id']]);

            $balStmt = $db->prepare("SELECT quantity FROM products WHERE id = :pid");
            $balStmt->execute([':pid' => $assign['product_id']]);
            $newBal = (int)$balStmt->fetchColumn();
        } else {
            $balStmt = $db->prepare("SELECT quantity FROM products WHERE id = :pid");
            $balStmt->execute([':pid' => $assign['product_id']]);
            $newBal = (int)$balStmt->fetchColumn();
        }

        $tx = $db->prepare("
            INSERT INTO transactions (transaction_type, reference_number, product_id, quantity, balance_after, employee_id, remarks, created_by)
            VALUES ('RETURN', :ref, :pid, :qty, :bal, :eid, :rem, :uid)
        ");
        $tx->execute([
            ':ref' => $ref, ':pid' => $assign['product_id'], ':qty' => $qty, ':bal' => $newBal,
            ':eid' => $assign['employee_id'], ':rem' => "Returned by {$assign['employee_name']} [Condition: {$conditionStatus}]. {$remarks}", ':uid' => $userId
        ]);

        $db->commit();
        log_activity($userId, $_SESSION['user_name'] ?? 'System', 'RETURN', 'Stock Return', $ref, "Returned {$qty} units of {$assign['product_name']} from {$assign['employee_name']}");
        return ['success' => true, 'new_balance' => $newBal];
    } catch (Exception $ex) {
        if ($db->inTransaction()) $db->rollBack();
        return ['success' => false, 'message' => $ex->getMessage()];
    }
}

/**
 * 5. Stock Damage (Write-Off / Damaged inventory)
 */
function stock_damage_transaction(int $productId, int $qty, string $reason, string $ref, string $date, string $remarks, int $userId): array {
    if ($qty <= 0) {
        return ['success' => false, 'message' => 'Quantity must be greater than zero.'];
    }
    $db = getDB();
    try {
        $db->beginTransaction();

        $checkStmt = $db->prepare("SELECT quantity, product_name FROM products WHERE id = :pid FOR UPDATE");
        $checkStmt->execute([':pid' => $productId]);
        $prod = $checkStmt->fetch();
        if (!$prod) {
            throw new Exception('Product not found.');
        }

        $currentQty = (int)$prod['quantity'];
        if ($qty > $currentQty) {
            throw new Exception("Cannot record damage exceeding available stock ({$currentQty}).");
        }

        $dmgStmt = $db->prepare("
            INSERT INTO stock_damage (reference_number, product_id, quantity, damage_reason, damage_date, status, remarks, created_by)
            VALUES (:ref, :pid, :qty, :rs, :dt, 'written_off', :rem, :uid)
        ");
        $dmgStmt->execute([
            ':ref' => $ref, ':pid' => $productId, ':qty' => $qty,
            ':rs' => $reason, ':dt' => $date, ':rem' => $remarks, ':uid' => $userId
        ]);

        $upd = $db->prepare("UPDATE products SET quantity = quantity - :qty, updated_at = NOW() WHERE id = :pid");
        $upd->execute([':qty' => $qty, ':pid' => $productId]);

        $newBal = $currentQty - $qty;

        $tx = $db->prepare("
            INSERT INTO transactions (transaction_type, reference_number, product_id, quantity, balance_after, remarks, created_by)
            VALUES ('DAMAGE', :ref, :pid, :qty, :bal, :rem, :uid)
        ");
        $tx->execute([
            ':ref' => $ref, ':pid' => $productId, ':qty' => -$qty, ':bal' => $newBal,
            ':rem' => "Written Off: {$reason}. {$remarks}", ':uid' => $userId
        ]);

        $db->commit();
        log_activity($userId, $_SESSION['user_name'] ?? 'System', 'DAMAGE', 'Stock Damage', $ref, "Recorded damage of {$qty} units for {$prod['product_name']}");
        return ['success' => true, 'new_balance' => $newBal];
    } catch (Exception $ex) {
        if ($db->inTransaction()) $db->rollBack();
        return ['success' => false, 'message' => $ex->getMessage()];
    }
}