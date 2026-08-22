<?php
/**
 * Database Seed Script — Enterprise Stock Management System
 */

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';

echo "Initializing Database Seeding...\n";

try {
    $db = getDB();

    // Disable foreign key checks for clean wipe & seed
    $db->exec("SET FOREIGN_KEY_CHECKS = 0;");
    $db->exec("TRUNCATE TABLE activity_log;");
    $db->exec("TRUNCATE TABLE transactions;");
    $db->exec("TRUNCATE TABLE stock_damage;");
    $db->exec("TRUNCATE TABLE stock_return;");
    $db->exec("TRUNCATE TABLE stock_assign;");
    $db->exec("TRUNCATE TABLE stock_out;");
    $db->exec("TRUNCATE TABLE stock_in;");
    $db->exec("TRUNCATE TABLE products;");
    $db->exec("TRUNCATE TABLE employees;");
    $db->exec("TRUNCATE TABLE suppliers;");
    $db->exec("TRUNCATE TABLE categories;");
    $db->exec("TRUNCATE TABLE users;");
    $db->exec("SET FOREIGN_KEY_CHECKS = 1;");

    // 1. Seed Users
    echo "[1/6] Seeding Users...\n";
    $passHash = password_hash('admin123', PASSWORD_DEFAULT);
    $mgrHash  = password_hash('manager123', PASSWORD_DEFAULT);
    $staffHash = password_hash('staff123', PASSWORD_DEFAULT);

    $uStmt = $db->prepare("
        INSERT INTO users (name, username, email, password, role, status) VALUES
        ('System Administrator', 'admin', 'admin@enterprise.local', :pass1, 'admin', 'active'),
        ('Inventory Manager', 'manager', 'manager@enterprise.local', :pass2, 'manager', 'active'),
        ('Warehouse Staff', 'staff', 'staff@enterprise.local', :pass3, 'staff', 'active')
    ");
    $uStmt->execute([':pass1' => $passHash, ':pass2' => $mgrHash, ':pass3' => $staffHash]);
    $adminId = 1;

    // 2. Seed Categories
    echo "[2/6] Seeding Categories...\n";
    $catStmt = $db->prepare("
        INSERT INTO categories (category_name, description, status) VALUES
        ('Electronics & Hardware', 'Laptops, Desktop PCs, Displays, Accessories', 'active'),
        ('Network & IT Infrastructure', 'Routers, Switches, Access Points, Rack Servers', 'active'),
        ('Office Supplies & Stationery', 'Paper, Toner Cartridges, Desk Tools', 'active'),
        ('Furniture & Ergonomic Fixtures', 'Executive Chairs, Desks, Storage Cabinets', 'active'),
        ('Safety & Industrial Gear', 'Helmets, High-Vis Vests, First Aid Kits', 'active')
    ");
    $catStmt->execute();

    // 3. Seed Suppliers
    echo "[3/6] Seeding Suppliers...\n";
    $supStmt = $db->prepare("
        INSERT INTO suppliers (company_name, contact_person, phone, email, address, tax_id, status) VALUES
        ('TechCorp Global Solutions', 'Rajesh Sharma', '+91 98765 43210', 'sales@techcorp.in', 'Tech Park, Sector 62, Noida, UP', 'GSTIN27AAACT1234F', 'active'),
        ('Apex Office Products Ltd', 'Sunita Rao', '+91 98123 45678', 'orders@apexoffice.com', 'MIDC Industrial Zone, Mumbai, MH', 'GSTIN27BBBPA5678G', 'active'),
        ('NetCom Systems India', 'Vikram Malhotra', '+91 97111 22334', 'info@netcom.co.in', 'Electronic City, Bengaluru, KA', 'GSTIN29CCCPN9988H', 'active')
    ");
    $supStmt->execute();

    // 4. Seed Employees
    echo "[4/6] Seeding Employees...\n";
    $empStmt = $db->prepare("
        INSERT INTO employees (employee_code, name, department, designation, phone, email, status) VALUES
        ('EMP-1001', 'Rahul Sharma', 'Information Technology', 'Senior Systems Engineer', '+91 98200 11223', 'rahul.s@enterprise.local', 'active'),
        ('EMP-1002', 'Priya Patel', 'Software Engineering', 'Lead Developer', '+91 98333 44556', 'priya.p@enterprise.local', 'active'),
        ('EMP-1003', 'Amit Verma', 'Human Resources', 'HR Operations Manager', '+91 98444 55667', 'amit.v@enterprise.local', 'active'),
        ('EMP-1004', 'Sneha Kulkarni', 'Finance & Accounting', 'Financial Controller', '+91 98555 66778', 'sneha.k@enterprise.local', 'active')
    ");
    $empStmt->execute();

    // 5. Seed Products
    echo "[5/6] Seeding Products...\n";
    $prodStmt = $db->prepare("
        INSERT INTO products (product_code, product_name, category_id, supplier_id, unit, quantity, minimum_stock_level, description, status) VALUES
        ('PRD-1001', 'Dell Latitude 5440 Core i7 Laptop', 1, 1, 'Units', 0, 5, '14-inch Full HD, 16GB RAM, 512GB NVMe SSD', 'active'),
        ('PRD-1002', 'Logitech MX Master 3S Wireless Mouse', 1, 1, 'Units', 0, 10, 'Ergonomic Wireless Mouse with 8K DPI Sensor', 'active'),
        ('PRD-1003', 'Cisco Catalyst Wi-Fi 6 Enterprise Access Point', 2, 3, 'Units', 0, 3, 'Dual Band AX3000 Wi-Fi 6 Access Point with PoE+', 'active'),
        ('PRD-1004', 'Ergonomic Mesh High-Back Office Chair', 4, 2, 'Units', 0, 5, 'Adjustable Lumbar Support & 3D Armrests', 'active'),
        ('PRD-1005', 'A4 Copier Paper 80GSM (Box of 5 Reams)', 3, 2, 'Boxes', 0, 15, 'Premium 80GSM Bright White Copier Paper', 'active'),
        ('PRD-1006', 'Samsung 27\" 4K IPS Ergonomic Monitor', 1, 1, 'Units', 0, 4, 'UHD 3840x2160 IPS Display with USB-C Hub', 'active')
    ");
    $prodStmt->execute();

    // 6. Execute Initial Stock Transactions
    echo "[6/6] Executing Initial Stock Transactions...\n";

    // Stock In 20 Laptops @ ₹75,000
    stock_in_transaction(1, 1, 20, 75000.00, generate_reference('IN'), date('Y-m-d'), 'Initial Purchase Order PO-9001', $adminId);

    // Stock In 50 Mice @ ₹5,500
    stock_in_transaction(2, 1, 50, 5500.00, generate_reference('IN'), date('Y-m-d'), 'Initial Purchase Order PO-9002', $adminId);

    // Stock In 10 Cisco Access Points @ ₹24,000
    stock_in_transaction(3, 3, 10, 24000.00, generate_reference('IN'), date('Y-m-d'), 'Initial Purchase Order PO-9003', $adminId);

    // Stock In 15 Office Chairs @ ₹12,500
    stock_in_transaction(4, 2, 15, 12500.00, generate_reference('IN'), date('Y-m-d'), 'Initial Purchase Order PO-9004', $adminId);

    // Stock In 40 Boxes Paper @ ₹1,200
    stock_in_transaction(5, 2, 40, 1200.00, generate_reference('IN'), date('Y-m-d'), 'Initial Purchase Order PO-9005', $adminId);

    // Stock In 8 Samsung Monitors @ ₹32,000
    stock_in_transaction(6, 1, 8, 32000.00, generate_reference('IN'), date('Y-m-d'), 'Initial Purchase Order PO-9006', $adminId);

    // Stock Out 5 Paper Boxes to Admin Dept
    stock_out_transaction(5, 'Administrative Office Floor 3', 5, generate_reference('OUT'), date('Y-m-d'), 'Quarterly Stationery Supply', 'Approved by Manager', $adminId);

    // Assign Laptop & Mouse to Employee 1 (Rahul Sharma)
    stock_assign_transaction(1, 1, 1, generate_reference('ASN'), date('Y-m-d'), 'Workstation Allocation', 'Issued for Project Alpha', $adminId);
    stock_assign_transaction(2, 1, 1, generate_reference('ASN'), date('Y-m-d'), 'Workstation Allocation', 'Issued for Project Alpha', $adminId);

    // Assign Laptop & Monitor to Employee 2 (Priya Patel)
    $resPriya = stock_assign_transaction(1, 2, 1, generate_reference('ASN'), date('Y-m-d'), 'Engineering Workstation', 'Lead Developer Laptop', $adminId);

    // Record 1 Damaged Chair
    stock_damage_transaction(4, 1, 'Hydraulic lift failure during transit', generate_reference('DMG'), date('Y-m-d'), 'Written off after inspection', $adminId);

    echo "\nDatabase Seeding Completed Successfully!\n";
    echo "=============================================\n";
    echo "Default Admin Login Credentials:\n";
    echo "  Username: admin\n";
    echo "  Password: admin123\n";
    echo "=============================================\n";

} catch (Exception $e) {
    die("Seeding Failed: " . $e->getMessage() . "\n");
}
