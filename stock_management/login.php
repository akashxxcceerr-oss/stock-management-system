<?php
/**
 * System Login Page
 */

require_once __DIR__ . '/config/constants.php';
require_once __DIR__ . '/includes/functions.php';

start_system_session();

if (is_logged_in()) {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = sanitize($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        $error = 'Please enter both username and password.';
    } else {
        $db = getDB();
        $stmt = $db->prepare("SELECT * FROM users WHERE username = :u AND status = 'active' LIMIT 1");
        $stmt->execute([':u' => $username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id']    = $user['id'];
            $_SESSION['user_name']  = $user['name'];
            $_SESSION['username']   = $user['username'];
            $_SESSION['user_role']  = $user['role'];
            $_SESSION['user_email'] = $user['email'];

            // Update last login timestamp
            $upd = $db->prepare("UPDATE users SET last_login = NOW() WHERE id = :id");
            $upd->execute([':id' => $user['id']]);

            log_activity($user['id'], $user['name'], 'LOGIN', 'Auth', (string)$user['id'], 'User logged in successfully');
            set_flash(ALERT_SUCCESS, "Welcome back, {$user['name']}!");
            header('Location: ' . BASE_URL . '/index.php');
            exit;
        } else {
            $error = 'Invalid username or password.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login — <?= APP_NAME ?></title>
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>
<body class="login-body">

<div class="login-card">
    <div class="login-brand">
        <div class="brand-icon">
            <i class="fa-solid fa-boxes-stacked"></i>
        </div>
        <h2>StockMaster Pro</h2>
        <p style="font-size:13px; color:var(--text-muted); margin-top:4px;">Enterprise Inventory & Asset Management</p>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger">
            <i class="fa-solid fa-triangle-exclamation"></i> <?= e($error) ?>
        </div>
    <?php endif; ?>

    <form method="POST" action="">
        <div class="form-group">
            <label class="form-label"><i class="fa-solid fa-user"></i> Username</label>
            <input type="text" name="username" class="form-control" placeholder="Enter your username" value="admin" required autofocus>
        </div>

        <div class="form-group">
            <label class="form-label"><i class="fa-solid fa-key"></i> Password</label>
            <input type="password" name="password" class="form-control" placeholder="Enter your password" value="admin123" required>
        </div>

        <button type="submit" class="btn btn-primary" style="width:100%; justify-content:center; padding:14px; font-size:15px;">
            Sign In to Control Center <i class="fa-solid fa-right-to-bracket"></i>
        </button>
    </form>

    <div style="margin-top: 28px; padding: 16px; background: rgba(31, 41, 61, 0.6); border-radius: var(--radius-sm); border: 1px solid var(--border-color); font-size: 12px;">
        <div style="font-weight:700; color:var(--accent-teal); margin-bottom:8px;"><i class="fa-solid fa-shield-halved"></i> Demo Login Credentials:</div>
        <div style="display:flex; justify-content:space-between; margin-bottom:4px;">
            <span><strong style="color:var(--text-main);">Administrator:</strong> admin</span>
            <span style="color:var(--text-muted);">Pass: admin123</span>
        </div>
        <div style="display:flex; justify-content:space-between; margin-bottom:4px;">
            <span><strong style="color:var(--text-main);">Manager:</strong> manager</span>
            <span style="color:var(--text-muted);">Pass: manager123</span>
        </div>
        <div style="display:flex; justify-content:space-between;">
            <span><strong style="color:var(--text-main);">Staff:</strong> staff</span>
            <span style="color:var(--text-muted);">Pass: staff123</span>
        </div>
    </div>
</div>

</body>
</html>
