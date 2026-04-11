-- ============================================================
--  Herald Canteen — herald_canteen.sql
--    Chef    → chef@heraldcanteen.com   / Chef@1234
-- ============================================================

DROP DATABASE IF EXISTS herald_canteen;
CREATE DATABASE herald_canteen
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE herald_canteen;

-- ============================================================
-- TABLES
-- ============================================================

CREATE TABLE users (
    user_id    INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    name       VARCHAR(100)  NOT NULL,
    email      VARCHAR(150)  NOT NULL UNIQUE,
    password   VARCHAR(255)  NOT NULL,
    role       ENUM('student','staff','chef') NOT NULL DEFAULT 'student',
    phone      VARCHAR(20)   DEFAULT NULL,
    created_at DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id)
) ENGINE=InnoDB;

CREATE TABLE menu (
    item_id      INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    item_name    VARCHAR(150)  NOT NULL,
    cuisine      VARCHAR(100)  DEFAULT NULL,
    price        DECIMAL(8,2)  NOT NULL,
    availability ENUM('available','out_of_stock') NOT NULL DEFAULT 'available',
    rating       DECIMAL(2,1)  DEFAULT 0.0,
    image_url    VARCHAR(300)  DEFAULT NULL,
    created_at   DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (item_id)
) ENGINE=InnoDB;

CREATE TABLE cart (
    cart_id     INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    user_id     INT UNSIGNED  NOT NULL,
    item_id     INT UNSIGNED  NOT NULL,
    quantity    INT           NOT NULL DEFAULT 1,
    total_price DECIMAL(8,2)  NOT NULL DEFAULT 0.00,
    added_at    DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (cart_id),
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (item_id) REFERENCES menu(item_id)  ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE orders (
    order_id     INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    user_id      INT UNSIGNED  NOT NULL,
    total_amount DECIMAL(8,2)  NOT NULL,
    order_status ENUM('in_process','ready_for_delivery','on_delivery','delivered')
                               NOT NULL DEFAULT 'in_process',
    created_at   DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at   DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (order_id),
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE order_details (
    order_detail_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    order_id        INT UNSIGNED NOT NULL,
    item_id         INT UNSIGNED NOT NULL,
    quantity        INT          NOT NULL DEFAULT 1,
    price           DECIMAL(8,2) NOT NULL,
    PRIMARY KEY (order_detail_id),
    FOREIGN KEY (order_id) REFERENCES orders(order_id) ON DELETE CASCADE,
    FOREIGN KEY (item_id)  REFERENCES menu(item_id)    ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE payment (
    payment_id     INT UNSIGNED NOT NULL AUTO_INCREMENT,
    order_id       INT UNSIGNED NOT NULL UNIQUE,
    payment_method ENUM('online','cod')            NOT NULL DEFAULT 'cod',
    payment_status ENUM('pending','successful')    NOT NULL DEFAULT 'pending',
    paid_at        DATETIME DEFAULT NULL,
    PRIMARY KEY (payment_id),
    FOREIGN KEY (order_id) REFERENCES orders(order_id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE login_attempts (
    attempt_id   INT UNSIGNED NOT NULL AUTO_INCREMENT,
    ip_address   VARCHAR(45)  NOT NULL,
    attempted_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (attempt_id),
    INDEX idx_ip_time (ip_address, attempted_at)
) ENGINE=InnoDB;

-- ============================================================
-- SEED DATA
-- ============================================================

INSERT INTO users (name, email, password, role) VALUES
('Head Chef Ram Bahadur',
 'chef@heraldcanteen.com',
 '$2y$12$eImiTXuWVxfM37uY4JANjOe5XtMnMF5mIiQD5FhiGNJYgimR6M.Je',
 'chef');

-- Student@1234
INSERT INTO users (name, email, password, role, phone) VALUES
('Sangam Rijal',
 'sangam@heraldcollege.edu.np',
 '$2y$12$WMDYAqFB4YyFjBp4IzmNjOqVKfOOWjCG2OVhcpRx3.0l6eqOcfpjW',
 'student',
 '9812345678');

-- Staff@1234
INSERT INTO users (name, email, password, role, phone) VALUES
('Prof. Aarti Sharma',
 'aarti@heraldcollege.edu.np',
 '$2y$12$cCXYU14.CdGJbcvYt0LiHOWKKZXjf8x0nylMSMNzJm1F66.U8bJ2i',
 'staff',
 '9800000002');

-- Menu items (Nepali canteen style)
INSERT INTO menu (item_name, cuisine, price, availability, rating) VALUES
('Dal Bhat Tarkari',   'Nepali',        120.00, 'available',    4.8),
('Momo (8 pcs)',       'Nepali',         90.00, 'available',    4.9),
('Chowmein',          'Nepali-Chinese',  80.00, 'available',    4.5),
('Thukpa',            'Nepali',         100.00, 'available',    4.6),
('Aloo Sadeko',       'Nepali Snack',    50.00, 'available',    4.3),
('Samosa (2 pcs)',    'Nepali Snack',    40.00, 'available',    4.2),
('Sel Roti',          'Nepali Sweet',    30.00, 'available',    4.4),
('Chicken Curry Set', 'Nepali',         160.00, 'available',    4.7),
('Veg Fried Rice',    'Nepali-Chinese',  85.00, 'available',    4.3),
('Masala Tea',        'Beverage',        25.00, 'available',    4.6),
('Lassi',             'Beverage',        45.00, 'available',    4.4),
('Buff Choila',       'Newari',         110.00, 'out_of_stock', 4.8),
('Pani Puri',         'Nepali Snack',    35.00, 'available',    4.5),
('Chicken Chilli',    'Nepali-Chinese', 130.00, 'available',    4.6);

-- Cart for  (user_id = 2)
INSERT INTO cart (user_id, item_id, quantity, total_price) VALUES
(2, 2, 2, 180.00),
(2, 4, 1, 100.00),
(2, 10, 1, 25.00);

-- Orders 
INSERT INTO orders (user_id, total_amount, order_status, created_at) VALUES
(2, 210.00, 'delivered',          '2025-04-01 12:30:00'),
(2, 160.00, 'on_delivery',        '2025-04-07 13:00:00'),
(2, 280.00, 'ready_for_delivery', '2025-04-08 11:45:00'),
(2,  90.00, 'in_process',         NOW());

-- Order details
INSERT INTO order_details (order_id, item_id, quantity, price) VALUES
(1, 1, 1, 120.00),
(1, 10, 2,  25.00),
(1, 7,  1,  30.00),
(2, 8,  1, 160.00),
(3, 2,  2,  90.00),
(3, 5,  1,  50.00),
(3, 11, 1,  45.00),
(4, 2,  1,  90.00);

-- Payments
INSERT INTO payment (order_id, payment_method, payment_status, paid_at) VALUES
(1, 'cod',    'successful', '2025-04-01 12:45:00'),
(2, 'online', 'successful', '2025-04-07 13:05:00'),
(3, 'online', 'successful', '2025-04-08 11:46:00'),
(4, 'cod',    'pending',    NULL);
