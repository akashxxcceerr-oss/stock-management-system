<?php
/**
 * Global Configuration & Environmental Constants
 */

if (!defined('APP_NAME')) {
    define('APP_NAME', 'Enterprise Stock & Inventory System');
}
define('APP_VERSION', '1.0.0');

// Base URL Setup (Supports built-in PHP dev server and subdirectory hosting)
$scriptDir = dirname($_SERVER['SCRIPT_NAME'] ?? '');
$baseUrl = ($scriptDir === '/' || $scriptDir === '\\') ? '' : rtrim(str_replace('\\', '/', $scriptDir), '/');
define('BASE_URL', $baseUrl);

// Database Credentials
define('DB_HOST', 'localhost');
define('DB_PORT', '3306');
define('DB_NAME', 'stock_management_db');
define('DB_USER', 'root');
define('DB_PASS', '');

// Session & Security Config
define('SESSION_LIFETIME', 86400); // 24 Hours
define('DEFAULT_PAGE_SIZE', 15);
define('CURRENCY_SYMBOL', '₹');
define('CURRENCY_CODE', 'INR');

// Alert Message Flash Keys
define('ALERT_SUCCESS', 'success');
define('ALERT_DANGER', 'danger');
define('ALERT_WARNING', 'warning');
define('ALERT_INFO', 'info');