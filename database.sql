CREATE DATABASE IF NOT EXISTS inventory_system_2 CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE inventory_system_2;

CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(191) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role VARCHAR(30) DEFAULT 'admin',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS products (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(191) NOT NULL,
    category VARCHAR(120) DEFAULT NULL,
    barcode VARCHAR(120) NOT NULL UNIQUE,
    cost_price DECIMAL(10,2) NOT NULL DEFAULT 0,
    selling_price DECIMAL(10,2) NOT NULL DEFAULT 0,
    quantity INT NOT NULL DEFAULT 0,
    description TEXT DEFAULT NULL,
    image VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS exhibitions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(191) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS exhibition_products (
    id INT AUTO_INCREMENT PRIMARY KEY,
    exhibition_id INT NOT NULL,
    product_id INT NOT NULL,
    UNIQUE KEY uq_exhibition_product (exhibition_id, product_id),
    CONSTRAINT fk_exhibition_products_exhibition FOREIGN KEY (exhibition_id) REFERENCES exhibitions(id) ON DELETE CASCADE,
    CONSTRAINT fk_exhibition_products_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS containers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    container_name VARCHAR(191) NOT NULL,
    barcode VARCHAR(120) NOT NULL UNIQUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS container_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    container_id INT NOT NULL,
    product_id INT NOT NULL,
    quantity INT NOT NULL DEFAULT 1,
    CONSTRAINT fk_container_items_container FOREIGN KEY (container_id) REFERENCES containers(id) ON DELETE CASCADE,
    CONSTRAINT fk_container_items_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS inventory_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NULL,
    barcode VARCHAR(120) NULL,
    product_name VARCHAR(191) NOT NULL,
    quantity_change INT NOT NULL,
    action_type VARCHAR(50) NOT NULL,
    note TEXT NULL,
    sale_price DECIMAL(10,2) NULL,
    cost_price DECIMAL(10,2) NULL,
    exhibition_id INT NULL,
    sold_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_inventory_log_product_id (product_id),
    KEY idx_inventory_log_action_type (action_type),
    KEY idx_inventory_log_sold_at (sold_at),
    CONSTRAINT fk_inventory_log_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE SET NULL,
    CONSTRAINT fk_inventory_log_exhibition FOREIGN KEY (exhibition_id) REFERENCES exhibitions(id) ON DELETE SET NULL
) ENGINE=InnoDB;
