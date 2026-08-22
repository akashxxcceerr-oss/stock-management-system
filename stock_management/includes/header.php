<?php
/**
 * Header & Sidebar Component
 */

require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/functions.php';

require_login();

$currentUser = get_logged_user();
$flash = get_flash();
$currentPage = basename($_SERVER['SCRIPT_FILENAME']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle ?? 'Dashboard') ?> — <?= APP_NAME ?></title>
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>
<body>
<div class="app-container">
    <!-- Sidebar Navigation -->
    <aside class="sidebar">
        <div class="brand-header">
            <div class="brand-icon">
                <i class="fa-solid fa-boxes-stacked"></i>
            </div>
            <div>
                <div class="brand-title">StockMaster Pro</div>
                <div class="brand-subtitle">Enterprise System</div>
            </div>
        </div>

        <nav class="sidebar-nav">
            <div class="nav-section-label">Main Menu</div>
            <a href="<?= BASE_URL ?>/index.php" class="nav-item <?= $currentPage === 'index.php' ? 'active' : '' ?>">
                <span class="nav-icon"><i class="fa-solid fa-chart-line"></i></span>
                <span>Dashboard</span>
            </a>

            <a href="<?= BASE_URL ?>/products.php" class="nav-item <?= $currentPage === 'products.php' ? 'active' : '' ?>">
                <span class="nav-icon"><i class="fa-solid fa-box-open"></i></span>
                <span>Products Catalog</span>
            </a>

            <a href="<?= BASE_URL ?>/categories.php" class="nav-item <?= $currentPage === 'categories.php' ? 'active' : '' ?>">
                <span class="nav-icon"><i class="fa-solid fa-tags"></i></span>
                <span>Categories</span>
            </a>

            <a href="<?= BASE_URL ?>/suppliers.php" class="nav-item <?= $currentPage === 'suppliers.php' ? 'active' : '' ?>">
                <span class="nav-icon"><i class="fa-solid fa-truck-field"></i></span>
                <span>Suppliers</span>
            </a>

            <a href="<?= BASE_URL ?>/employees.php" class="nav-item <?= $currentPage === 'employees.php' ? 'active' : '' ?>">
                <span class="nav-icon"><i class="fa-solid fa-id-card"></i></span>
                <span>Employees & Custody</span>
            </a>

            <div class="nav-section-label">Inventory Operations</div>
            <a href="<?= BASE_URL ?>/stock_in.php" class="nav-item <?= $currentPage === 'stock_in.php' ? 'active' : '' ?>">
                <span class="nav-icon"><i class="fa-solid fa-arrow-down-to-bracket"></i></span>
                <span>Stock In (Procurement)</span>
            </a>

            <a href="<?= BASE_URL ?>/stock_out.php" class="nav-item <?= $currentPage === 'stock_out.php' ? 'active' : '' ?>">
                <span class="nav-icon"><i class="fa-solid fa-arrow-up-from-bracket"></i></span>
                <span>Stock Out (Dispatch)</span>
            </a>

            <a href="<?= BASE_URL ?>/stock_assign.php" class="nav-item <?= $currentPage === 'stock_assign.php' ? 'active' : '' ?>">
                <span class="nav-icon"><i class="fa-solid fa-handshake-angle"></i></span>
                <span>Assign Asset</span>
            </a>

            <a href="<?= BASE_URL ?>/stock_return.php" class="nav-item <?= $currentPage === 'stock_return.php' ? 'active' : '' ?>">
                <span class="nav-icon"><i class="fa-solid fa-rotate-left"></i></span>
                <span>Return Asset</span>
            </a>

            <a href="<?= BASE_URL ?>/stock_damage.php" class="nav-item <?= $currentPage === 'stock_damage.php' ? 'active' : '' ?>">
                <span class="nav-icon"><i class="fa-solid fa-triangle-exclamation"></i></span>
                <span>Damage & Write-off</span>
            </a>

            <div class="nav-section-label">Ledgers & Reports</div>
            <a href="<?= BASE_URL ?>/transactions.php" class="nav-item <?= $currentPage === 'transactions.php' ? 'active' : '' ?>">
                <span class="nav-icon"><i class="fa-solid fa-receipt"></i></span>
                <span>Central Ledger</span>
            </a>

            <a href="<?= BASE_URL ?>/reports.php" class="nav-item <?= $currentPage === 'reports.php' ? 'active' : '' ?>">
                <span class="nav-icon"><i class="fa-solid fa-chart-pie"></i></span>
                <span>Analytics & Reports</span>
            </a>

            <?php if ($currentUser['role'] === 'admin'): ?>
            <div class="nav-section-label">Administration</div>
            <a href="<?= BASE_URL ?>/users.php" class="nav-item <?= $currentPage === 'users.php' ? 'active' : '' ?>">
                <span class="nav-icon"><i class="fa-solid fa-users-gear"></i></span>
                <span>System Users</span>
            </a>
            <?php endif; ?>
        </nav>

        <div class="user-profile-bar">
            <div class="user-avatar">
                <?= strtoupper(substr($currentUser['name'], 0, 1)) ?>
            </div>
            <div class="user-info">
                <div class="user-name"><?= e($currentUser['name']) ?></div>
                <div class="user-role"><?= ucfirst(e($currentUser['role'])) ?></div>
            </div>
            <a href="<?= BASE_URL ?>/logout.php" class="logout-btn" title="Logout">
                <i class="fa-solid fa-right-from-bracket"></i>
            </a>
        </div>
    </aside>

    <!-- Main Content Body -->
    <main class="main-content">
        <?php if ($flash): ?>
            <div class="alert alert-<?= e($flash['type']) ?>">
                <div><i class="fa-solid fa-circle-info"></i> <?= e($flash['message']) ?></div>
                <button type="button" onclick="this.parentElement.remove()" style="background:none; border:none; color:inherit; cursor:pointer;"><i class="fa-solid fa-xmark"></i></button>
            </div>
        <?php endif; ?>
