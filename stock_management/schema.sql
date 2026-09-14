-- Online Stock Management System
-- Database: stock_management_db


-- Users Table
CREATE TABLE IF NOT EXISTS `users` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(100) NOT NULL,
  `username` VARCHAR(50) NOT NULL UNIQUE,
  `email` VARCHAR(100) NULL,
  `password` VARCHAR(255) NOT NULL,
  `role` ENUM('admin', 'manager', 'staff') NOT NULL DEFAULT 'staff',
  `status` ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
  `last_login` DATETIME NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NULL ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Categories Table
CREATE TABLE IF NOT EXISTS `categories` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `category_name` VARCHAR(100) NOT NULL UNIQUE,
  `description` TEXT NULL,
  `status` ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NULL ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Suppliers Table
CREATE TABLE IF NOT EXISTS `suppliers` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `company_name` VARCHAR(150) NOT NULL,
  `contact_person` VARCHAR(100) NULL,
  `phone` VARCHAR(30) NULL,
  `email` VARCHAR(100) NULL,
  `address` TEXT NULL,
  `tax_id` VARCHAR(50) NULL,
  `status` ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NULL ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Products Table
CREATE TABLE IF NOT EXISTS `products` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `product_code` VARCHAR(50) NOT NULL UNIQUE,
  `product_name` VARCHAR(150) NOT NULL,
  `category_id` INT UNSIGNED NOT NULL,
  `supplier_id` INT UNSIGNED NULL,
  `unit` VARCHAR(30) NOT NULL DEFAULT 'Pcs',
  `quantity` INT NOT NULL DEFAULT 0,
  `minimum_stock_level` INT NOT NULL DEFAULT 5,
  `description` TEXT NULL,
  `status` ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT `fk_products_category` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_products_supplier` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Employees Table
CREATE TABLE IF NOT EXISTS `employees` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `employee_code` VARCHAR(50) NOT NULL UNIQUE,
  `name` VARCHAR(100) NOT NULL,
  `department` VARCHAR(100) NOT NULL,
  `designation` VARCHAR(100) NULL,
  `phone` VARCHAR(30) NULL,
  `email` VARCHAR(100) NULL,
  `status` ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NULL ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Stock In Table
CREATE TABLE IF NOT EXISTS `stock_in` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `reference_number` VARCHAR(60) NOT NULL UNIQUE,
  `product_id` INT UNSIGNED NOT NULL,
  `supplier_id` INT UNSIGNED NULL,
  `quantity` INT NOT NULL,
  `unit_price` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `total_cost` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `purchase_date` DATE NOT NULL,
  `remarks` TEXT NULL,
  `created_by` INT UNSIGNED NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_stock_in_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_stock_in_supplier` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_stock_in_user` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Stock Out Table
CREATE TABLE IF NOT EXISTS `stock_out` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `reference_number` VARCHAR(60) NOT NULL UNIQUE,
  `product_id` INT UNSIGNED NOT NULL,
  `quantity` INT NOT NULL,
  `recipient` VARCHAR(150) NOT NULL,
  `dispatch_date` DATE NOT NULL,
  `purpose` VARCHAR(255) NULL,
  `remarks` TEXT NULL,
  `created_by` INT UNSIGNED NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_stock_out_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_stock_out_user` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Stock Assign Table
CREATE TABLE IF NOT EXISTS `stock_assign` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `reference_number` VARCHAR(60) NOT NULL UNIQUE,
  `product_id` INT UNSIGNED NOT NULL,
  `employee_id` INT UNSIGNED NOT NULL,
  `quantity` INT NOT NULL,
  `returned_quantity` INT NOT NULL DEFAULT 0,
  `assignment_date` DATE NOT NULL,
  `purpose` VARCHAR(255) NULL,
  `status` ENUM('assigned', 'partially_returned', 'returned') NOT NULL DEFAULT 'assigned',
  `remarks` TEXT NULL,
  `created_by` INT UNSIGNED NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_stock_assign_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_stock_assign_employee` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_stock_assign_user` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Stock Return Table
CREATE TABLE IF NOT EXISTS `stock_return` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `reference_number` VARCHAR(60) NOT NULL UNIQUE,
  `assignment_id` INT UNSIGNED NOT NULL,
  `product_id` INT UNSIGNED NOT NULL,
  `employee_id` INT UNSIGNED NOT NULL,
  `quantity` INT NOT NULL,
  `return_date` DATE NOT NULL,
  `condition_status` ENUM('Usable', 'Damaged', 'Under Repair') NOT NULL DEFAULT 'Usable',
  `remarks` TEXT NULL,
  `created_by` INT UNSIGNED NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_stock_return_assignment` FOREIGN KEY (`assignment_id`) REFERENCES `stock_assign` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_stock_return_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_stock_return_employee` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_stock_return_user` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Stock Damage Table
CREATE TABLE IF NOT EXISTS `stock_damage` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `reference_number` VARCHAR(60) NOT NULL UNIQUE,
  `product_id` INT UNSIGNED NOT NULL,
  `quantity` INT NOT NULL,
  `damage_reason` VARCHAR(255) NOT NULL,
  `damage_date` DATE NOT NULL,
  `status` ENUM('written_off', 'pending', 'repaired') NOT NULL DEFAULT 'written_off',
  `remarks` TEXT NULL,
  `created_by` INT UNSIGNED NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_stock_damage_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_stock_damage_user` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Central Transactions Ledger
CREATE TABLE IF NOT EXISTS `transactions` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `transaction_type` ENUM('STOCK_IN', 'STOCK_OUT', 'ASSIGNMENT', 'RETURN', 'DAMAGE') NOT NULL,
  `reference_number` VARCHAR(60) NOT NULL,
  `product_id` INT UNSIGNED NOT NULL,
  `quantity` INT NOT NULL,
  `balance_after` INT NOT NULL,
  `supplier_id` INT UNSIGNED NULL,
  `employee_id` INT UNSIGNED NULL,
  `remarks` TEXT NULL,
  `transaction_date` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_by` INT UNSIGNED NOT NULL,
  CONSTRAINT `fk_trans_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_trans_user` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- System Audit Log
CREATE TABLE IF NOT EXISTS `activity_log` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT UNSIGNED NULL,
  `user_name` VARCHAR(100) NULL,
  `action` VARCHAR(50) NOT NULL,
  `module` VARCHAR(50) NULL,
  `record_id` VARCHAR(50) NULL,
  `description` TEXT NULL,
  `ip_address` VARCHAR(45) NULL,
  `user_agent` TEXT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;