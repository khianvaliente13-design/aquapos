-- ============================================================
-- AquaStation POS — Full Database Setup
-- HOW TO IMPORT:
--   1. Open phpMyAdmin
--   2. Click the SQL tab (no need to select a database first)
--   3. Paste this entire file and click Go
-- ============================================================

-- Create and select database
CREATE DATABASE IF NOT EXISTS aquastation CHARACTER SET utf8 COLLATE utf8_general_ci;
USE aquastation;

-- Drop all tables cleanly
SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS loyalty_logs;
DROP TABLE IF EXISTS deliveries;
DROP TABLE IF EXISTS transaction_items;
DROP TABLE IF EXISTS transactions;
DROP TABLE IF EXISTS inventory_logs;
DROP TABLE IF EXISTS customers;
DROP TABLE IF EXISTS products;
DROP TABLE IF EXISTS categories;
DROP TABLE IF EXISTS users;

SET FOREIGN_KEY_CHECKS = 1;

-- ── USERS ────────────────────────────────────────────────────
CREATE TABLE users (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    username   VARCHAR(50) UNIQUE NOT NULL,
    password   VARCHAR(255) NOT NULL,
    full_name  VARCHAR(100) NOT NULL,
    role       ENUM('admin','cashier','delivery') DEFAULT 'cashier',
    email      VARCHAR(100),
    status     ENUM('active','inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ── CATEGORIES ───────────────────────────────────────────────
CREATE TABLE categories (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    name       VARCHAR(50) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ── PRODUCTS ─────────────────────────────────────────────────
CREATE TABLE products (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    category_id INT,
    name        VARCHAR(100) NOT NULL,
    price       DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    stock       INT NOT NULL DEFAULT 0,
    unit        VARCHAR(20) DEFAULT 'piece',
    status      ENUM('active','inactive') DEFAULT 'active',
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL
);

-- ── CUSTOMERS ────────────────────────────────────────────────
CREATE TABLE customers (
    id               INT AUTO_INCREMENT PRIMARY KEY,
    name             VARCHAR(100) NOT NULL,
    phone            VARCHAR(20) UNIQUE NOT NULL,
    address          TEXT NOT NULL,
    email            VARCHAR(100),
    loyalty_points   INT DEFAULT 0,
    total_purchases  INT DEFAULT 0,
    status           ENUM('active','inactive') DEFAULT 'active',
    created_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- ── TRANSACTIONS ─────────────────────────────────────────────
CREATE TABLE transactions (
    id               INT AUTO_INCREMENT PRIMARY KEY,
    transaction_code VARCHAR(20) UNIQUE NOT NULL,
    customer_id      INT,
    cashier_id       INT NOT NULL,
    type             ENUM('walk-in','delivery') DEFAULT 'walk-in',
    subtotal         DECIMAL(10,2) NOT NULL,
    discount         DECIMAL(10,2) DEFAULT 0.00,
    loyalty_discount DECIMAL(10,2) DEFAULT 0.00,
    total            DECIMAL(10,2) NOT NULL,
    amount_paid      DECIMAL(10,2) NOT NULL,
    change_amount    DECIMAL(10,2) NOT NULL,
    payment_method   ENUM('cash','gcash','maya') DEFAULT 'cash',
    points_earned    INT DEFAULT 0,
    points_used      INT DEFAULT 0,
    status           ENUM('completed','pending','cancelled') DEFAULT 'completed',
    notes            TEXT,
    created_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL,
    FOREIGN KEY (cashier_id)  REFERENCES users(id)
);

-- ── TRANSACTION ITEMS ─────────────────────────────────────────
CREATE TABLE transaction_items (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    transaction_id INT NOT NULL,
    product_id     INT NOT NULL,
    product_name   VARCHAR(100) NOT NULL,
    quantity       INT NOT NULL,
    unit_price     DECIMAL(10,2) NOT NULL,
    subtotal       DECIMAL(10,2) NOT NULL,
    FOREIGN KEY (transaction_id) REFERENCES transactions(id),
    FOREIGN KEY (product_id)     REFERENCES products(id)
);

-- ── INVENTORY LOGS ────────────────────────────────────────────
CREATE TABLE inventory_logs (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    type       ENUM('sale','restock','adjustment') NOT NULL,
    quantity   INT NOT NULL,
    reference  VARCHAR(50),
    notes      TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(id)
);

-- ── LOYALTY LOGS ──────────────────────────────────────────────
CREATE TABLE loyalty_logs (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    customer_id INT NOT NULL,
    type        ENUM('earned','used','adjusted') NOT NULL,
    points      INT NOT NULL,
    reference   VARCHAR(50),
    notes       TEXT,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES customers(id)
);

-- ── DELIVERIES ────────────────────────────────────────────────
CREATE TABLE deliveries (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    transaction_id INT,
    customer_id    INT,
    driver_id      INT,
    address        TEXT NOT NULL,
    scheduled_date DATE,
    scheduled_time TIME,
    status         ENUM('pending','in_transit','delivered','cancelled') DEFAULT 'pending',
    notes          TEXT,
    created_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (transaction_id) REFERENCES transactions(id) ON DELETE SET NULL,
    FOREIGN KEY (customer_id)    REFERENCES customers(id)    ON DELETE SET NULL,
    FOREIGN KEY (driver_id)      REFERENCES users(id)        ON DELETE SET NULL
);

-- ── SEED DATA ─────────────────────────────────────────────────

-- Default users (password: "password")
INSERT INTO users (username, password, full_name, role) VALUES
('admin',    '$2y$10$TKh8H1.PfQx37YgCzwiKb.KjNyWgaHb9cbcoQgdIVFlYg7B77bAHC', 'System Administrator', 'admin'),
('cashier1', '$2y$10$TKh8H1.PfQx37YgCzwiKb.KjNyWgaHb9cbcoQgdIVFlYg7B77bAHC', 'Maria Santos',         'cashier');

-- Categories
INSERT INTO categories (name) VALUES
('Purified Water'),
('Mineral Water'),
('Accessories');

-- Products
INSERT INTO products (category_id, name, price, stock, unit) VALUES
(1, '5-Gallon Purified',  35.00, 100, 'gallon'),
(1, '1-Gallon Purified',  12.00, 100, 'gallon'),
(1, 'Slim Bottle 500ml',   8.00, 200, 'bottle'),
(2, '5-Gallon Mineral',   40.00, 80,  'gallon'),
(2, '1-Gallon Mineral',   15.00, 80,  'gallon'),
(3, 'Gallon Container',  150.00, 30,  'piece'),
(3, 'Water Pump',        120.00, 20,  'piece');

-- Sample customers
INSERT INTO customers (name, phone, address, loyalty_points) VALUES
('Juan dela Cruz', '09171234567', '123 Rizal St., Barangay 1, Cebu City',      50),
('Maria Garcia',   '09281234567', '456 Bonifacio Ave., Barangay 2, Cebu City', 20);
