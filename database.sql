-- CREATE DATABASE
CREATE DATABASE IF NOT EXISTS Swaad_Unlimited;
USE Swaad_Unlimited;

--------------------------------------------------
-- 1. CUSTOMERS (for user.php)
--------------------------------------------------
CREATE TABLE customers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    firstname VARCHAR(100),
    lastname VARCHAR(100),
    username VARCHAR(100) UNIQUE,
    email VARCHAR(150) UNIQUE,
    password VARCHAR(255),
    role VARCHAR(20) DEFAULT 'user',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

--------------------------------------------------
-- 2. FOODS (for chef-foods.php)
--------------------------------------------------
CREATE TABLE foods (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150),
    category VARCHAR(100),
    price DECIMAL(10,2),
    availability VARCHAR(50),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

--------------------------------------------------
-- 3. ORDERS (for chef-orders.php + chef.php)
--------------------------------------------------
CREATE TABLE orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    customer_name VARCHAR(150),
    items TEXT,
    status VARCHAR(50) DEFAULT 'Pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

--------------------------------------------------
-- 4. STAFF USERS (for staff.php)
--------------------------------------------------
CREATE TABLE userss (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(100) UNIQUE,
    password VARCHAR(255),
    role VARCHAR(20) DEFAULT 'staff'
);

--------------------------------------------------
-- 5. STAFF ORDERS (for staff_panel.php)
--------------------------------------------------
CREATE TABLE orderss (
    id INT AUTO_INCREMENT PRIMARY KEY,
    customer_name VARCHAR(150),
    item_name VARCHAR(150),
    quantity INT,
    total_amount DECIMAL(10,2),
    delivery_status VARCHAR(50) DEFAULT 'On Delivery',
    payment_status VARCHAR(50) DEFAULT 'Pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

--------------------------------------------------
-- SAMPLE DATA (OPTIONAL BUT IMPORTANT)
--------------------------------------------------

-- STAFF LOGIN (password: staff123)
INSERT INTO userss (username, password, role)
VALUES ('staff1', '$2y$10$wH8VZr3xQzQxJr7VYz8z2eY0Fv1R3cWz0rOQZzq8V1uYkP6vF5k2K', 'staff');

-- SAMPLE FOODS
INSERT INTO foods (name, category, price, availability) VALUES
('Chicken Curry', 'Main Course', 250, 'Available'),
('Veg Momo', 'Appetizer', 120, 'Available'),
('Chowmein', 'Noodles', 150, 'Available');

-- SAMPLE ORDERS
INSERT INTO orders (customer_name, items, status) VALUES
('Ram', 'Chicken Curry x2', 'Pending'),
('Shyam', 'Momo x1', 'Preparing');

-- SAMPLE STAFF ORDERS
INSERT INTO orderss (customer_name, item_name, quantity, total_amount) VALUES
('Hari', 'Chowmein', 2, 300),
('Gita', 'Veg Momo', 1, 120);