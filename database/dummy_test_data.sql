USE herald_canteen;

INSERT INTO users (full_name, email, password, role) VALUES
('Test Customer', 'customer@heraldcanteen.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'customer'),
('Main Chef', 'chef@heraldcanteen.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'chef'),
('Delivery Staff', 'staff@heraldcanteen.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'staff');

INSERT INTO categories (name, image_url, description) VALUES
('Pizza', 'pizza.jpg', 'Fresh and delicious pizzas'),
('Burger', 'burger.jpg', 'Juicy burgers and sandwiches'),
('Drinks', 'drinks.jpg', 'Cold and hot beverages');

INSERT INTO menu_items (category_id, name, description, price, image_url, rating) VALUES
(1, 'Margherita Pizza', 'Classic cheese pizza', 450.00, 'margherita.jpg', 4.5),
(1, 'Pepperoni Pizza', 'Pepperoni with mozzarella', 550.00, 'pepperoni.jpg', 4.7),
(2, 'Chicken Burger', 'Crispy chicken burger', 320.00, 'chicken_burger.jpg', 4.4),
(3, 'Coke', 'Chilled soft drink', 80.00, 'coke.jpg', 4.0);