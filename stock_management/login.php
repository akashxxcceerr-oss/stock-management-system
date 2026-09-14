<?php
/**
 * System Login Page — v2.0 Redesign
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
        <p style="font-size:13px; color:var(--text-muted); margin-top:6px;">Enterprise Inventory & Asset Management</p>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger" style="margin-bottom:20px;">
            <i class="fa-solid fa-triangle-exclamation"></i> <?= e($error) ?>
        </div>
    <?php endif; ?>

    <form method="POST" action="" id="loginForm">
        <div class="form-group">
            <label class="form-label"><i class="fa-solid fa-user" style="color:var(--primary);"></i> Username</label>
            <input type="text" id="usernameInput" name="username" class="form-control" placeholder="Enter username" value="admin" required autofocus autocomplete="username">
        </div>

        <div class="form-group" style="position:relative;">
            <label class="form-label"><i class="fa-solid fa-key" style="color:var(--primary);"></i> Password</label>
            <div style="position:relative;">
                <input type="password" id="passwordInput" name="password" class="form-control" placeholder="Enter password" value="admin123" required autocomplete="current-password" style="padding-right:42px;">
                <button type="button" onclick="togglePasswordVisibility()" style="position:absolute; right:12px; top:50%; transform:translateY(-50%); background:none; border:none; color:var(--text-muted); cursor:pointer; font-size:14px; padding:4px;" title="Toggle visibility">
                    <i class="fa-regular fa-eye" id="toggleIcon"></i>
                </button>
            </div>
        </div>

        <button type="submit" class="btn btn-primary" style="width:100%; justify-content:center; padding:13px; font-size:15px; margin-top:10px;">
            Sign In to Dashboard <i class="fa-solid fa-arrow-right" style="margin-left:6px;"></i>
        </button>
    </form>

    <!-- Quick Demo Credential Pills -->
    <div style="margin-top: 24px; padding: 16px; background: rgba(15, 20, 38, 0.85); border-radius: var(--radius-md); border: 1px solid var(--border-color); font-size: 12px;">
        <div style="font-weight:700; color:var(--text-secondary); margin-bottom:10px; display:flex; align-items:center; justify-content:space-between;">
            <span><i class="fa-solid fa-bolt" style="color:var(--warning);"></i> 1-Click Demo Login:</span>
            <span style="font-size:11px; color:var(--text-muted);">Click to fill</span>
        </div>
        <div style="display:grid; grid-template-columns:1fr 1fr 1fr; gap:8px;">
            <button type="button" class="btn btn-secondary btn-sm" onclick="fillCreds('admin', 'admin123')" style="justify-content:center; font-size:11px; padding:6px 4px;">
                <i class="fa-solid fa-shield-halved" style="color:var(--primary);"></i> Admin
            </button>
            <button type="button" class="btn btn-secondary btn-sm" onclick="fillCreds('manager', 'manager123')" style="justify-content:center; font-size:11px; padding:6px 4px;">
                <i class="fa-solid fa-briefcase" style="color:var(--accent-cyan);"></i> Manager
            </button>
            <button type="button" class="btn btn-secondary btn-sm" onclick="fillCreds('staff', 'staff123')" style="justify-content:center; font-size:11px; padding:6px 4px;">
                <i class="fa-solid fa-user-tag" style="color:var(--accent-teal);"></i> Staff
            </button>
        </div>
    </div>
</div>

<script>
function togglePasswordVisibility() {
    const pwd = document.getElementById('passwordInput');
    const icon = document.getElementById('toggleIcon');
    if (pwd.type === 'password') {
        pwd.type = 'text';
        icon.classList.remove('fa-eye');
        icon.classList.add('fa-eye-slash');
    } else {
        pwd.type = 'password';
        icon.classList.remove('fa-eye-slash');
        icon.classList.add('fa-eye');
    }
}

function fillCreds(username, password) {
    document.getElementById('usernameInput').value = username;
    document.getElementById('passwordInput').value = password;
}
</script>

</body>
</html>
