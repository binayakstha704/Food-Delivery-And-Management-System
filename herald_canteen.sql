-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Apr 17, 2026 at 07:06 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `herald_canteen`
--

-- --------------------------------------------------------

--
-- Table structure for table `cart`
--

CREATE TABLE `cart` (
  `cart_id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `item_id` int(10) UNSIGNED NOT NULL,
  `quantity` int(11) NOT NULL DEFAULT 1,
  `total_price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `added_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `categories`
--

CREATE TABLE `categories` (
  `category_id` int(10) UNSIGNED NOT NULL,
  `name` varchar(100) NOT NULL,
  `image_url` varchar(255) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `is_available` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `categories`
--

INSERT INTO `categories` (`category_id`, `name`, `image_url`, `description`, `is_available`) VALUES
(1, 'Burgers', 'assets/images/burger.jpg', 'Freshly made burgers with premium ingredients', 1),
(2, 'Pizza', 'assets/images/pizza.jpg', 'Stone baked pizzas with authentic flavors', 1),
(3, 'Momo', 'assets/images/momo.jpg', 'Traditional Nepali dumplings steamed to perfection', 1),
(4, 'Pasta', 'assets/images/pasta.jpg', 'Creamy and rich Italian style pastas', 1),
(5, 'Drinks', 'assets/images/drinks.jpg', 'Refreshing cold and hot beverages', 1),
(6, 'Desserts', 'assets/images/deserts.jpg', 'Sweet treats to end your meal right', 1),
(7, 'Sandwiches', 'assets/images/sandwich.jpg', 'Freshly made sandwiches with premium fillings', 1),
(8, 'Rolls', 'assets/images/rolls.jpg', 'Crispy and soft rolls packed with spiced fillings', 1),
(9, 'Rice Bowls', 'assets/images/rice.jpg', 'Hearty rice bowls with rich curries and toppings', 1),
(10, 'Noodles', 'assets/images/noodles.jpg', 'Stir fried and soupy noodles with bold flavors', 1),
(11, 'Snacks', 'assets/images/snacks.jpg', 'Light bites and crispy snacks to munch on', 1),
(12, 'Soup', 'assets/images/soup.jpg', 'Warm and comforting soups for any time of day', 1),
(13, 'Sushi', 'assets/images/sushi.jpg', 'Fresh Japanese sushi and rolls', 1),
(14, 'Tacos', 'assets/images/tacos.jpg', 'Mexican style tacos with bold and spicy flavors', 1),
(15, 'Steak', 'assets/images/steak.jpg', 'Grilled to perfection steaks with rich sauces', 1),
(16, 'Salads', 'assets/images/salad.jpg', 'Fresh and healthy salads with vibrant dressings', 1),
(17, 'Waffles', 'assets/images/waffles.jpg', 'Crispy Belgian waffles with sweet and savory toppings', 1),
(18, 'Kebabs', 'assets/images/kebabs.jpg', 'Smoky grilled kebabs with aromatic spices', 1),
(19, 'Seafood', 'assets/images/seafood.jpg', 'Fresh catch of the day cooked to perfection', 1),
(20, 'Shawarma', 'assets/images/shawarma.jpg', 'Slow roasted meat wrapped in flatbread', 1),
(21, 'Sizzler', 'assets/images/sizzler.jpg', 'Hot sizzling platters on cast iron with rich sauces', 1);

-- --------------------------------------------------------

--
-- Table structure for table `login_attempts`
--

CREATE TABLE `login_attempts` (
  `attempt_id` int(10) UNSIGNED NOT NULL,
  `ip_address` varchar(45) NOT NULL,
  `attempted_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `menu_items`
--

CREATE TABLE `menu_items` (
  `item_id` int(10) UNSIGNED NOT NULL,
  `category_id` int(10) UNSIGNED NOT NULL,
  `name` varchar(150) NOT NULL,
  `description` text DEFAULT NULL,
  `price` decimal(10,2) NOT NULL,
  `rating` decimal(2,1) NOT NULL DEFAULT 0.0,
  `is_available` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `menu_items`
--

INSERT INTO `menu_items` (`item_id`, `category_id`, `name`, `description`, `price`, `rating`, `is_available`, `created_at`) VALUES
(1, 1, 'Classic Beef Burger', 'Grilled beef patty with lettuce, tomato, cheddar and signature sauce.', 350.00, 4.7, 1, '2026-04-17 10:48:24'),
(2, 1, 'Chicken Crispy Burger', 'Crispy fried chicken fillet with coleslaw, pickles and mayo.', 300.00, 4.5, 1, '2026-04-17 10:48:24'),
(3, 1, 'Double Smash Burger', 'Two smashed beef patties with double cheese and caramelized onions.', 450.00, 4.8, 1, '2026-04-17 10:48:24'),
(4, 1, 'BBQ Bacon Burger', 'Smoky beef patty with crispy bacon, BBQ sauce and cheddar.', 420.00, 4.6, 1, '2026-04-17 10:48:24'),
(5, 1, 'Veggie Burger', 'Grilled vegetable patty with avocado, fresh greens and garlic aioli.', 280.00, 4.3, 1, '2026-04-17 10:48:24'),
(6, 2, 'Margherita Pizza', 'Classic tomato base with fresh mozzarella and basil.', 400.00, 4.6, 1, '2026-04-17 10:48:24'),
(7, 2, 'Pepperoni Pizza', 'Premium pepperoni on a rich tomato and mozzarella base.', 480.00, 4.7, 1, '2026-04-17 10:48:24'),
(8, 2, 'BBQ Chicken Pizza', 'Grilled chicken, red onions and BBQ sauce on a crispy thin crust.', 520.00, 4.5, 1, '2026-04-17 10:48:24'),
(9, 2, 'Mushroom Truffle Pizza', 'Sauteed mushrooms, truffle oil and parmesan on a white cream base.', 550.00, 4.8, 1, '2026-04-17 10:48:24'),
(10, 3, 'Chicken Steam Momo', 'Classic steamed momo filled with minced chicken and Nepali spices.', 160.00, 4.9, 1, '2026-04-17 10:48:24'),
(11, 3, 'Buff Momo', 'Traditional buffalo momo with cumin, coriander and timur achar.', 150.00, 4.8, 1, '2026-04-17 10:48:24'),
(12, 3, 'Paneer Momo', 'Soft paneer and vegetable filling with sesame achar.', 170.00, 4.6, 1, '2026-04-17 10:48:24'),
(13, 3, 'Fried Momo', 'Crispy deep fried momo with spicy tomato achar.', 180.00, 4.7, 1, '2026-04-17 10:48:24'),
(14, 3, 'C Momo', 'Steamed momo tossed in spicy and tangy C sauce.', 190.00, 4.5, 1, '2026-04-17 10:48:24'),
(15, 4, 'Chicken Alfredo', 'Grilled chicken in a rich creamy parmesan sauce over fettuccine.', 320.00, 4.6, 1, '2026-04-17 10:48:24'),
(16, 4, 'Spaghetti Bolognese', 'Classic minced beef ragu slow cooked with tomatoes over spaghetti.', 310.00, 4.5, 1, '2026-04-17 10:48:24'),
(17, 4, 'Penne Arrabbiata', 'Penne in a fiery tomato and garlic sauce with chili flakes.', 270.00, 4.3, 1, '2026-04-17 10:48:24'),
(18, 4, 'Mushroom Carbonara', 'Creamy carbonara with sauteed mushrooms and crispy pancetta.', 340.00, 4.7, 1, '2026-04-17 10:48:24'),
(19, 5, 'Cold Coffee', 'Chilled espresso blended with milk, ice cream and vanilla.', 150.00, 4.6, 1, '2026-04-17 10:48:24'),
(20, 5, 'Fresh Lime Soda', 'Freshly squeezed lime with sparkling soda and mint.', 100.00, 4.4, 1, '2026-04-17 10:48:24'),
(21, 5, 'Mango Lassi', 'Thick yogurt blended with fresh Alphonso mango pulp.', 130.00, 4.5, 1, '2026-04-17 10:48:24'),
(22, 5, 'Masala Chai', 'Spiced tea brewed with ginger, cardamom and fresh milk.', 80.00, 4.6, 1, '2026-04-17 10:48:24'),
(23, 6, 'Chocolate Lava Cake', 'Warm dark chocolate cake with a gooey molten center.', 220.00, 4.8, 1, '2026-04-17 10:48:24'),
(24, 6, 'Gulab Jamun', 'Soft milk dumplings soaked in rose flavored sugar syrup.', 120.00, 4.5, 1, '2026-04-17 10:48:24'),
(25, 6, 'Mango Cheesecake', 'Baked cheesecake with buttery biscuit base and fresh mango topping.', 250.00, 4.7, 1, '2026-04-17 10:48:24');

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `notification_id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `title` varchar(100) NOT NULL,
  `message` text DEFAULT NULL,
  `type` enum('order','status','promotion') NOT NULL DEFAULT 'order',
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `orders`
--

CREATE TABLE `orders` (
  `order_id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `total_amount` decimal(10,2) NOT NULL,
  `status` enum('pending','preparing','ready','delivered','cancelled') NOT NULL DEFAULT 'pending',
  `payment_method` enum('cod','esewa','khalti','card','online') NOT NULL DEFAULT 'cod',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `order_items`
--

CREATE TABLE `order_items` (
  `order_item_id` int(10) UNSIGNED NOT NULL,
  `order_id` int(10) UNSIGNED NOT NULL,
  `item_id` int(10) UNSIGNED NOT NULL,
  `quantity` int(11) NOT NULL DEFAULT 1,
  `price` decimal(10,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `payments`
--

CREATE TABLE `payments` (
  `payment_id` int(10) UNSIGNED NOT NULL,
  `order_id` int(10) UNSIGNED NOT NULL,
  `transaction_uuid` varchar(100) NOT NULL,
  `payment_method` enum('cod','esewa','khalti','card','online') NOT NULL DEFAULT 'cod',
  `payment_status` enum('pending','successful','failed') NOT NULL DEFAULT 'pending',
  `amount` decimal(10,2) NOT NULL,
  `gateway_ref` varchar(255) DEFAULT NULL,
  `paid_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `user_id` int(10) UNSIGNED NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `email` varchar(150) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('user','staff','chef') NOT NULL DEFAULT 'user',
  `phone` varchar(20) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`user_id`, `full_name`, `email`, `password`, `role`, `phone`, `created_at`) VALUES
(1, 'Head Chef', 'chef@heraldcanteen.com', '$2y$12$eImiTXuWVxfM37uY4JANjOe5XtMnMF5mIiQD5FhiGNJYgimR6M.Je', 'chef', NULL, '2026-04-17 10:48:24'),
(2, 'Test User', 'user@heraldcanteen.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'user', NULL, '2026-04-17 10:48:24');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `cart`
--
ALTER TABLE `cart`
  ADD PRIMARY KEY (`cart_id`),
  ADD UNIQUE KEY `unique_cart` (`user_id`,`item_id`),
  ADD KEY `item_id` (`item_id`);

--
-- Indexes for table `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`category_id`);

--
-- Indexes for table `login_attempts`
--
ALTER TABLE `login_attempts`
  ADD PRIMARY KEY (`attempt_id`),
  ADD KEY `idx_ip_time` (`ip_address`,`attempted_at`);

--
-- Indexes for table `menu_items`
--
ALTER TABLE `menu_items`
  ADD PRIMARY KEY (`item_id`),
  ADD KEY `category_id` (`category_id`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`notification_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `orders`
--
ALTER TABLE `orders`
  ADD PRIMARY KEY (`order_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `order_items`
--
ALTER TABLE `order_items`
  ADD PRIMARY KEY (`order_item_id`),
  ADD KEY `order_id` (`order_id`),
  ADD KEY `item_id` (`item_id`);

--
-- Indexes for table `payments`
--
ALTER TABLE `payments`
  ADD PRIMARY KEY (`payment_id`),
  ADD UNIQUE KEY `order_id` (`order_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `cart`
--
ALTER TABLE `cart`
  MODIFY `cart_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `categories`
--
ALTER TABLE `categories`
  MODIFY `category_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- AUTO_INCREMENT for table `login_attempts`
--
ALTER TABLE `login_attempts`
  MODIFY `attempt_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `menu_items`
--
ALTER TABLE `menu_items`
  MODIFY `item_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=26;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `notification_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `orders`
--
ALTER TABLE `orders`
  MODIFY `order_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `order_items`
--
ALTER TABLE `order_items`
  MODIFY `order_item_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `payments`
--
ALTER TABLE `payments`
  MODIFY `payment_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `cart`
--
ALTER TABLE `cart`
  ADD CONSTRAINT `cart_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `cart_ibfk_2` FOREIGN KEY (`item_id`) REFERENCES `menu_items` (`item_id`) ON DELETE CASCADE;

--
-- Constraints for table `menu_items`
--
ALTER TABLE `menu_items`
  ADD CONSTRAINT `menu_items_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `categories` (`category_id`) ON DELETE CASCADE;

--
-- Constraints for table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `orders`
--
ALTER TABLE `orders`
  ADD CONSTRAINT `orders_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `order_items`
--
ALTER TABLE `order_items`
  ADD CONSTRAINT `order_items_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`order_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `order_items_ibfk_2` FOREIGN KEY (`item_id`) REFERENCES `menu_items` (`item_id`);

--
-- Constraints for table `payments`
--
ALTER TABLE `payments`
  ADD CONSTRAINT `payments_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`order_id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
