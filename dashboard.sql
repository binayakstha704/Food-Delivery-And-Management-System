-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Apr 11, 2026 at 09:12 AM
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
-- Database: `dashboard`
--

-- --------------------------------------------------------

--
-- Table structure for table `cart`
--

CREATE TABLE `cart` (
  `cart_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `item_id` int(11) NOT NULL,
  `quantity` int(11) DEFAULT 1,
  `added_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `cart`
--

INSERT INTO `cart` (`cart_id`, `user_id`, `item_id`, `quantity`, `added_at`) VALUES
(5, 2, 2, 1, '2026-04-11 06:57:41'),
(6, 2, 1, 1, '2026-04-11 06:57:42'),
(7, 2, 6, 1, '2026-04-11 06:58:24'),
(8, 2, 8, 1, '2026-04-11 06:58:26'),
(9, 2, 79, 1, '2026-04-11 07:01:08'),
(10, 2, 82, 1, '2026-04-11 07:01:10'),
(11, 2, 15, 1, '2026-04-11 07:05:28'),
(12, 2, 18, 1, '2026-04-11 07:05:32');

-- --------------------------------------------------------

--
-- Table structure for table `categories`
--

CREATE TABLE `categories` (
  `category_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `image_url` varchar(255) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `is_available` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `categories`
--

INSERT INTO `categories` (`category_id`, `name`, `image_url`, `description`, `is_available`) VALUES
(1, 'Burgers', '../assets/images/burger.jpg', 'Freshly made burgers with premium ingredients', 1),
(2, 'Pizza', '../assets/images/pizza.jpg', 'Stone baked pizzas with authentic flavors', 1),
(3, 'Momo', '../assets/images/momo.jpg', 'Traditional Nepali dumplings steamed to perfection', 1),
(4, 'Pasta', '../assets/images/pasta.jpg', 'Creamy and rich Italian style pastas', 1),
(5, 'Drinks', '../assets/images/drinks.jpg', 'Refreshing cold and hot beverages', 1),
(6, 'Desserts', '../assets/images/deserts.jpg', 'Sweet treats to end your meal right', 1),
(7, 'Sandwiches', '../assets/images/sandwich.jpg', 'Freshly made sandwiches with premium fillings', 1),
(8, 'Rolls', '../assets/images/rolls.jpg', 'Crispy and soft rolls packed with spiced fillings', 1),
(9, 'Rice Bowls', '../assets/images/rice.jpg', 'Hearty rice bowls with rich curries and toppings', 1),
(10, 'Noodles', '../assets/images/noodles.jpg', 'Stir fried and soupy noodles with bold flavors', 1),
(11, 'Snacks', '../assets/images/snacks.jpg', 'Light bites and crispy snacks to munch on', 1),
(12, 'Soup', '../assets/images/soup.jpg', 'Warm and comforting soups for any time of day', 1),
(13, 'Sushi', '../assets/images/sushi.jpg', 'Fresh Japanese sushi and rolls made with premium ingredients', 1),
(14, 'Tacos', '../assets/images/tacos.jpg', 'Mexican style tacos loaded with bold and spicy flavors', 1),
(15, 'Steak', '../assets/images/steak.jpg', 'Grilled to perfection steaks with rich sauces and sides', 1),
(16, 'Salads', '../assets/images/salad.jpg', 'Fresh and healthy salads with vibrant dressings', 1),
(17, 'Waffles', '../assets/images/waffles.jpg', 'Crispy Belgian waffles with sweet and savory toppings', 1),
(18, 'Kebabs', '../assets/images/kebabs.jpg', 'Smoky grilled kebabs marinated with aromatic spices', 1),
(19, 'Seafood', '../assets/images/seafood.jpg', 'Fresh catch of the day cooked to perfection', 1),
(20, 'Shawarma', '../assets/images/shawarma.jpg', 'Slow roasted meat wrapped in flatbread with garlic sauce', 1),
(21, 'Sizzler', '../assets/images/sizzler.jpg', 'Hot sizzling platters served on cast iron with rich sauces', 1);

-- --------------------------------------------------------

--
-- Table structure for table `menu_items`
--

CREATE TABLE `menu_items` (
  `item_id` int(11) NOT NULL,
  `category_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `price` decimal(10,2) NOT NULL,
  `is_available` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `menu_items`
--

INSERT INTO `menu_items` (`item_id`, `category_id`, `name`, `description`, `price`, `is_available`, `created_at`) VALUES
(1, 1, 'Classic Beef Burger', 'Grilled beef patty with lettuce, tomato, cheddar cheese and our signature sauce.', 350.00, 1, '2026-04-08 10:40:10'),
(2, 1, 'Chicken Crispy Burger', 'Crispy fried chicken fillet with coleslaw, pickles and mayo in a toasted bun.', 300.00, 1, '2026-04-08 10:40:10'),
(3, 1, 'Double Smash Burger', 'Two smashed beef patties with double cheese and caramelized onions.', 450.00, 1, '2026-04-08 10:40:10'),
(4, 1, 'BBQ Bacon Burger', 'Smoky beef patty with crispy bacon, BBQ sauce and cheddar.', 420.00, 1, '2026-04-08 10:40:10'),
(5, 1, 'Veggie Burger', 'Grilled vegetable patty with avocado, fresh greens and garlic aioli.', 280.00, 1, '2026-04-08 10:40:10'),
(6, 2, 'Margherita Pizza', 'Classic tomato base with fresh mozzarella and basil.', 400.00, 1, '2026-04-08 10:40:10'),
(7, 2, 'Pepperoni Pizza', 'Loaded with premium pepperoni on a rich tomato and mozzarella base.', 480.00, 1, '2026-04-08 10:40:10'),
(8, 2, 'BBQ Chicken Pizza', 'Grilled chicken, red onions and BBQ sauce on a crispy thin crust.', 520.00, 1, '2026-04-08 10:40:10'),
(9, 2, 'Mushroom Truffle Pizza', 'Sauteed mushrooms, truffle oil and parmesan on a white cream base.', 550.00, 1, '2026-04-08 10:40:10'),
(10, 3, 'Chicken Steam Momo', 'Classic steamed momo filled with minced chicken and Nepali spices.', 160.00, 1, '2026-04-08 10:40:10'),
(11, 3, 'Buff Momo', 'Traditional buffalo momo with cumin, coriander and timur achar.', 150.00, 1, '2026-04-08 10:40:10'),
(12, 3, 'Paneer Momo', 'Soft paneer and vegetable filling with sesame achar.', 170.00, 1, '2026-04-08 10:40:10'),
(13, 3, 'Fried Momo', 'Crispy deep fried momo with spicy tomato achar.', 180.00, 1, '2026-04-08 10:40:10'),
(14, 3, 'C Momo', 'Steamed momo tossed in spicy and tangy C sauce.', 190.00, 1, '2026-04-08 10:40:10'),
(15, 4, 'Chicken Alfredo', 'Grilled chicken in a rich creamy parmesan sauce over fettuccine.', 320.00, 1, '2026-04-08 10:40:10'),
(16, 4, 'Spaghetti Bolognese', 'Classic minced beef ragu slow cooked with tomatoes over spaghetti.', 310.00, 1, '2026-04-08 10:40:10'),
(17, 4, 'Penne Arrabbiata', 'Penne in a fiery tomato and garlic sauce with chili flakes.', 270.00, 1, '2026-04-08 10:40:10'),
(18, 4, 'Mushroom Carbonara', 'Creamy carbonara with sauteed mushrooms and crispy pancetta.', 340.00, 1, '2026-04-08 10:40:10'),
(19, 5, 'Cold Coffee', 'Chilled espresso blended with milk, ice cream and vanilla.', 150.00, 1, '2026-04-08 10:40:10'),
(20, 5, 'Fresh Lime Soda', 'Freshly squeezed lime with sparkling soda and mint.', 100.00, 1, '2026-04-08 10:40:10'),
(21, 5, 'Mango Lassi', 'Thick yogurt blended with fresh Alphonso mango pulp.', 130.00, 1, '2026-04-08 10:40:10'),
(22, 5, 'Masala Chai', 'Spiced tea brewed with ginger, cardamom and fresh milk.', 80.00, 1, '2026-04-08 10:40:10'),
(23, 6, 'Chocolate Lava Cake', 'Warm dark chocolate cake with a gooey molten center and vanilla ice cream.', 220.00, 1, '2026-04-08 10:40:10'),
(24, 6, 'Gulab Jamun', 'Soft milk dumplings soaked in rose flavored sugar syrup.', 120.00, 1, '2026-04-08 10:40:10'),
(25, 6, 'Mango Cheesecake', 'Baked cheesecake with a buttery biscuit base and fresh mango topping.', 250.00, 1, '2026-04-08 10:40:10'),
(26, 1, 'Spicy Jalape?o Burger', 'Juicy beef patty loaded with fresh jalape?os, pepper jack cheese and chipotle mayo.', 370.00, 1, '2026-04-08 11:42:44'),
(27, 1, 'Mushroom Swiss Burger', 'Grilled beef patty topped with saut?ed mushrooms, swiss cheese and garlic butter.', 360.00, 1, '2026-04-08 11:42:44'),
(28, 2, 'Chicken Tikka Pizza', 'Tandoori spiced chicken, onions, capsicum and mozzarella on a tomato base.', 510.00, 1, '2026-04-08 11:42:44'),
(29, 2, 'Four Cheese Pizza', 'Mozzarella, cheddar, parmesan and gorgonzola melted together on a thin crust.', 540.00, 1, '2026-04-08 11:42:44'),
(30, 3, 'Jhol Momo', 'Steamed chicken momo dunked in a spicy and tangy tomato sesame broth.', 200.00, 1, '2026-04-08 11:42:44'),
(31, 3, 'Cheese Momo', 'Creamy cheese filled momo served with roasted tomato achar.', 210.00, 1, '2026-04-08 11:42:44'),
(32, 4, 'Pesto Pasta', 'Fusilli tossed in fresh basil pesto with cherry tomatoes and pine nuts.', 290.00, 1, '2026-04-08 11:42:44'),
(33, 4, 'Seafood Pasta', 'Linguine with prawns, mussels and squid in a light garlic white wine sauce.', 420.00, 1, '2026-04-08 11:42:44'),
(34, 5, 'Strawberry Milkshake', 'Thick and creamy milkshake blended with fresh strawberries and vanilla ice cream.', 160.00, 1, '2026-04-08 11:42:44'),
(35, 5, 'Virgin Mojito', 'Fresh mint, lime juice, sugar syrup and sparkling soda over crushed ice.', 120.00, 1, '2026-04-08 11:42:44'),
(36, 6, 'Tiramisu', 'Classic Italian dessert with espresso soaked ladyfingers and mascarpone cream.', 260.00, 1, '2026-04-08 11:42:44'),
(37, 6, 'Kulfi', 'Traditional frozen Indian dessert made with condensed milk, cardamom and pistachios.', 130.00, 1, '2026-04-08 11:42:44'),
(38, 7, 'Chicken Club Sandwich', 'Grilled chicken, bacon, lettuce, tomato and mayo in toasted triple decker bread.', 280.00, 1, '2026-04-08 13:18:22'),
(39, 7, 'Egg Mayo Sandwich', 'Creamy egg mayo with cucumber and lettuce on soft white bread.', 180.00, 1, '2026-04-08 13:18:22'),
(40, 7, 'BLT Sandwich', 'Crispy bacon, fresh lettuce and juicy tomato with mustard on sourdough.', 250.00, 1, '2026-04-08 13:18:22'),
(41, 8, 'Chicken Kathi Roll', 'Spiced grilled chicken wrapped in a flaky paratha with onions and mint chutney.', 220.00, 1, '2026-04-08 13:18:22'),
(42, 8, 'Paneer Tikka Roll', 'Tandoori paneer with capsicum and onions wrapped in a soft roomali roti.', 200.00, 1, '2026-04-08 13:18:22'),
(43, 8, 'Egg Roll', 'Fluffy omelette rolled inside a crispy paratha with spicy green chutney.', 160.00, 1, '2026-04-08 13:18:22'),
(44, 9, 'Chicken Biryani Bowl', 'Fragrant basmati rice cooked with spiced chicken, saffron and fried onions.', 320.00, 1, '2026-04-08 13:18:22'),
(45, 9, 'Veg Fried Rice Bowl', 'Wok tossed rice with fresh vegetables, soy sauce and sesame oil.', 220.00, 1, '2026-04-08 13:18:22'),
(46, 9, 'Butter Chicken Bowl', 'Tender chicken in a rich buttery tomato gravy served over steamed basmati.', 340.00, 1, '2026-04-08 13:18:22'),
(47, 10, 'Chicken Chowmein', 'Stir fried egg noodles with chicken, vegetables and soy garlic sauce.', 220.00, 1, '2026-04-08 13:18:22'),
(48, 10, 'Thukpa', 'Tibetan style hearty noodle soup with vegetables and tender chicken pieces.', 250.00, 1, '2026-04-08 13:18:22'),
(49, 10, 'Wai Wai Sadeko', 'Spiced raw noodles tossed with onion, tomato, coriander and lime juice.', 150.00, 1, '2026-04-08 13:18:22'),
(50, 11, 'French Fries', 'Crispy golden fries seasoned with sea salt and served with dipping sauce.', 150.00, 1, '2026-04-08 13:18:22'),
(51, 11, 'Chicken Popcorn', 'Bite sized crispy fried chicken pieces seasoned with herbs and spices.', 200.00, 1, '2026-04-08 13:18:22'),
(52, 11, 'Onion Rings', 'Golden battered onion rings served hot with smoky chipotle dip.', 160.00, 1, '2026-04-08 13:18:22'),
(53, 12, 'Tomato Basil Soup', 'Creamy blended tomato soup with fresh basil and a swirl of cream.', 160.00, 1, '2026-04-08 13:18:22'),
(54, 12, 'Sweet Corn Soup', 'Light and comforting sweet corn soup with vegetables and egg drops.', 140.00, 1, '2026-04-08 13:18:22'),
(55, 12, 'Hot and Sour Soup', 'Classic Chinese style tangy and spicy soup with tofu, mushrooms and bamboo.', 170.00, 1, '2026-04-08 13:18:22'),
(56, 7, 'Tuna Melt Sandwich', 'Creamy tuna salad with melted cheddar on toasted sourdough bread.', 260.00, 1, '2026-04-08 13:31:42'),
(57, 7, 'Grilled Cheese Sandwich', 'Triple cheese blend melted between two slices of golden buttered bread.', 200.00, 1, '2026-04-08 13:31:42'),
(58, 7, 'Turkey Avocado Sandwich', 'Sliced turkey breast with fresh avocado, lettuce and honey mustard.', 290.00, 1, '2026-04-08 13:31:42'),
(59, 8, 'Seekh Kabab Roll', 'Spiced minced chicken seekh kabab wrapped in paratha with onion and chutney.', 240.00, 1, '2026-04-08 13:31:42'),
(60, 8, 'Fish Roll', 'Crispy battered fish fillet with coleslaw and tartar sauce in a soft wrap.', 250.00, 1, '2026-04-08 13:31:42'),
(61, 8, 'Aloo Tikki Roll', 'Spiced potato tikki with tamarind chutney and fresh onions in a roomali roti.', 170.00, 1, '2026-04-08 13:31:42'),
(62, 9, 'Mutton Biryani Bowl', 'Slow cooked mutton with aromatic spices and saffron basmati rice.', 380.00, 1, '2026-04-08 13:31:42'),
(63, 9, 'Egg Fried Rice Bowl', 'Classic egg fried rice with spring onions, soy sauce and sesame oil.', 200.00, 1, '2026-04-08 13:31:42'),
(64, 9, 'Dal Tadka Bowl', 'Yellow lentils tempered with cumin, garlic and chili served over steamed rice.', 240.00, 1, '2026-04-08 13:31:42'),
(65, 10, 'Veg Chowmein', 'Stir fried noodles with fresh vegetables, oyster sauce and chili flakes.', 190.00, 1, '2026-04-08 13:31:42'),
(66, 10, 'Butter Garlic Noodles', 'Soft noodles tossed in rich butter garlic sauce with spring onions.', 210.00, 1, '2026-04-08 13:31:42'),
(67, 10, 'Ramen', 'Japanese style noodle soup with soft boiled egg, nori, corn and rich broth.', 300.00, 1, '2026-04-08 13:31:42'),
(68, 11, 'Mozzarella Sticks', 'Golden fried mozzarella sticks with a gooey center served with marinara dip.', 220.00, 1, '2026-04-08 13:31:42'),
(69, 11, 'Spring Rolls', 'Crispy vegetable spring rolls served with sweet chili dipping sauce.', 170.00, 1, '2026-04-08 13:31:42'),
(70, 11, 'Nachos with Cheese', 'Crunchy tortilla chips loaded with melted cheese, jalape?os and sour cream.', 250.00, 1, '2026-04-08 13:31:42'),
(71, 12, 'Mushroom Soup', 'Creamy blended mushroom soup with thyme, garlic and a drizzle of truffle oil.', 180.00, 1, '2026-04-08 13:31:42'),
(72, 12, 'Lemon Coriander Soup', 'Light and tangy clear soup with lemon, fresh coriander and vegetables.', 150.00, 1, '2026-04-08 13:31:42'),
(73, 12, 'Minestrone Soup', 'Hearty Italian vegetable soup with pasta, beans and fresh herbs.', 170.00, 1, '2026-04-08 13:31:42'),
(74, 13, 'Salmon Nigiri', 'Fresh Atlantic salmon over hand pressed vinegared rice.', 350.00, 1, '2026-04-09 15:48:06'),
(75, 13, 'Spicy Tuna Roll', 'Tuna, cucumber and spicy mayo wrapped in nori and seasoned rice.', 380.00, 1, '2026-04-09 15:48:06'),
(76, 13, 'Dragon Roll', 'Prawn tempura topped with avocado, eel sauce and sesame seeds.', 420.00, 1, '2026-04-09 15:48:06'),
(77, 13, 'Vegetable Roll', 'Cucumber, avocado, carrot and cream cheese wrapped in seasoned rice.', 300.00, 1, '2026-04-09 15:48:06'),
(78, 13, 'Rainbow Roll', 'California roll topped with assorted fresh sashimi and avocado.', 450.00, 1, '2026-04-09 15:48:06'),
(79, 14, 'Chicken Taco', 'Grilled spiced chicken with salsa, guacamole and sour cream in a corn tortilla.', 220.00, 1, '2026-04-09 15:48:06'),
(80, 14, 'Beef Taco', 'Seasoned ground beef with cheddar, lettuce, tomato and chipotle sauce.', 250.00, 1, '2026-04-09 15:48:06'),
(81, 14, 'Fish Taco', 'Crispy battered fish with coleslaw, lime crema and pickled jalape?os.', 240.00, 1, '2026-04-09 15:48:06'),
(82, 14, 'Paneer Taco', 'Tandoori spiced paneer with mango salsa and mint yogurt in a flour tortilla.', 210.00, 1, '2026-04-09 15:48:06'),
(83, 15, 'Ribeye Steak', 'Prime ribeye grilled to your preference with garlic butter and rosemary.', 950.00, 1, '2026-04-09 15:48:06'),
(84, 15, 'Chicken Steak', 'Herb marinated chicken breast grilled and served with mushroom cream sauce.', 480.00, 1, '2026-04-09 15:48:06'),
(85, 15, 'Lamb Chops', 'Tender herb crusted lamb chops with mint jelly and roasted vegetables.', 850.00, 1, '2026-04-09 15:48:06'),
(86, 15, 'Sirloin Steak', 'Classic sirloin with peppercorn sauce, grilled asparagus and mashed potato.', 780.00, 1, '2026-04-09 15:48:06'),
(87, 16, 'Caesar Salad', 'Romaine lettuce, parmesan, croutons and classic Caesar dressing.', 250.00, 1, '2026-04-09 15:48:06'),
(88, 16, 'Greek Salad', 'Cucumber, olives, feta cheese, tomatoes and red onion with olive oil dressing.', 230.00, 1, '2026-04-09 15:48:06'),
(89, 16, 'Grilled Chicken Salad', 'Mixed greens with grilled chicken, cherry tomatoes and balsamic vinaigrette.', 270.00, 1, '2026-04-09 15:48:06'),
(90, 16, 'Quinoa Salad', 'Quinoa with roasted vegetables, chickpeas, feta and lemon herb dressing.', 260.00, 1, '2026-04-09 15:48:06'),
(91, 17, 'Classic Butter Waffle', 'Crispy Belgian waffle with whipped butter, maple syrup and powdered sugar.', 220.00, 1, '2026-04-09 15:48:06'),
(92, 17, 'Nutella Banana Waffle', 'Golden waffle topped with Nutella, fresh banana slices and crushed hazelnuts.', 260.00, 1, '2026-04-09 15:48:06'),
(93, 17, 'Fried Chicken Waffle', 'Crispy fried chicken on a fluffy waffle drizzled with honey and hot sauce.', 320.00, 1, '2026-04-09 15:48:06'),
(94, 17, 'Berry Cream Waffle', 'Waffle topped with mixed berries, whipped cream and berry compote.', 250.00, 1, '2026-04-09 15:48:06'),
(95, 17, 'Lotus Biscoff Waffle', 'Waffle smothered in Biscoff spread with crushed cookies and vanilla ice cream.', 280.00, 1, '2026-04-09 15:48:06'),
(96, 18, 'Chicken Seekh Kebab', 'Minced chicken with herbs and spices skewered and grilled over charcoal.', 280.00, 1, '2026-04-11 03:10:42'),
(97, 18, 'Mutton Boti Kebab', 'Tender mutton pieces marinated in yogurt and spices grilled to smoky perfection.', 350.00, 1, '2026-04-11 03:10:42'),
(98, 18, 'Paneer Tikka Kebab', 'Cubes of paneer in tandoori spices grilled with peppers and onions.', 260.00, 1, '2026-04-11 03:10:42'),
(99, 18, 'Shami Kebab', 'Soft minced lamb patties with lentils and fresh herbs shallow fried.', 240.00, 1, '2026-04-11 03:10:42'),
(100, 18, 'Prawn Kebab', 'Juicy prawns marinated in garlic, lemon and spices grilled on skewers.', 320.00, 1, '2026-04-11 03:10:42'),
(101, 19, 'Grilled Salmon', 'Atlantic salmon fillet grilled with lemon butter sauce and steamed vegetables.', 550.00, 1, '2026-04-11 03:10:42'),
(102, 19, 'Fish and Chips', 'Crispy beer battered fish fillet with thick cut fries and tartar sauce.', 380.00, 1, '2026-04-11 03:10:42'),
(103, 19, 'Prawn Stir Fry', 'Juicy prawns stir fried with garlic, chili, bell peppers and oyster sauce.', 420.00, 1, '2026-04-11 03:10:42'),
(104, 19, 'Calamari', 'Crispy fried squid rings seasoned with sea salt served with aioli dip.', 350.00, 1, '2026-04-11 03:10:42'),
(105, 19, 'Butter Garlic Crab', 'Fresh crab cooked in a rich butter garlic and herb sauce.', 680.00, 1, '2026-04-11 03:10:42'),
(106, 20, 'Chicken Shawarma', 'Slow roasted chicken with garlic sauce, pickles and veggies in a flatbread.', 250.00, 1, '2026-04-11 03:10:42'),
(107, 20, 'Beef Shawarma', 'Tender spiced beef with tahini, tomatoes and onions wrapped in soft bread.', 280.00, 1, '2026-04-11 03:10:42'),
(108, 20, 'Mixed Shawarma', 'Combination of chicken and beef with garlic mayo and fresh salad in a wrap.', 300.00, 1, '2026-04-11 03:10:42'),
(109, 20, 'Falafel Shawarma', 'Crispy falafel with hummus, pickled vegetables and tahini in a pita bread.', 220.00, 1, '2026-04-11 03:10:42'),
(110, 20, 'Prawn Shawarma', 'Spiced grilled prawns with garlic sauce, lettuce and tomato in a flatbread.', 320.00, 1, '2026-04-11 03:10:42'),
(111, 21, 'Chicken Sizzler', 'Grilled chicken with sauteed vegetables, mashed potato and pepper sauce.', 420.00, 1, '2026-04-11 03:10:42'),
(112, 21, 'Beef Sizzler', 'Grilled beef steak with onion rings, fries and mushroom sauce on a hot platter.', 520.00, 1, '2026-04-11 03:10:42'),
(113, 21, 'Prawn Sizzler', 'Grilled prawns with stir fried vegetables, rice and garlic butter sauce.', 550.00, 1, '2026-04-11 03:10:42'),
(114, 21, 'Paneer Sizzler', 'Tandoori paneer with mixed vegetables, noodles and spicy tomato sauce.', 380.00, 1, '2026-04-11 03:10:42'),
(115, 21, 'Mixed Grill Sizzler', 'Combination of chicken, beef and prawn with sides on a sizzling cast iron plate.', 620.00, 1, '2026-04-11 03:10:42');

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `notification_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `title` varchar(100) NOT NULL,
  `message` text DEFAULT NULL,
  `type` enum('order','status','promotion') DEFAULT 'order',
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `notifications`
--

INSERT INTO `notifications` (`notification_id`, `user_id`, `title`, `message`, `type`, `is_read`, `created_at`) VALUES
(1, 2, 'Order Placed — Cash on Delivery', 'Your order #1 has been placed. Please pay Rs. 920.00 on delivery.', 'order', 0, '2026-04-11 06:54:30');

-- --------------------------------------------------------

--
-- Table structure for table `orders`
--

CREATE TABLE `orders` (
  `order_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `total_amount` decimal(10,2) NOT NULL,
  `status` enum('pending','preparing','ready','delivered','cancelled') DEFAULT 'pending',
  `payment_method` enum('cod','esewa','khalti','card') NOT NULL DEFAULT 'cod',
  `order_date` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `orders`
--

INSERT INTO `orders` (`order_id`, `user_id`, `total_amount`, `status`, `payment_method`, `order_date`) VALUES
(1, 2, 920.00, 'pending', 'cod', '2026-04-11 06:54:30');

-- --------------------------------------------------------

--
-- Table structure for table `order_items`
--

CREATE TABLE `order_items` (
  `order_item_id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `item_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL,
  `price` decimal(10,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `order_items`
--

INSERT INTO `order_items` (`order_item_id`, `order_id`, `item_id`, `quantity`, `price`) VALUES
(1, 1, 1, 2, 350.00),
(2, 1, 68, 1, 220.00);

-- --------------------------------------------------------

--
-- Table structure for table `payments`
--

CREATE TABLE `payments` (
  `payment_id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `transaction_uuid` varchar(100) NOT NULL,
  `payment_method` enum('cod','esewa','khalti','card') NOT NULL,
  `payment_status` enum('pending','successful','failed') NOT NULL DEFAULT 'pending',
  `amount` decimal(10,2) NOT NULL,
  `gateway_ref` varchar(255) DEFAULT NULL,
  `paid_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `user_id` int(11) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `email` varchar(150) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `role` enum('user','chef','staff') DEFAULT 'user',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`user_id`, `full_name`, `email`, `password_hash`, `role`, `created_at`) VALUES
(2, 'Test User', 'user@test.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'user', '2026-04-08 10:55:30');

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
  ADD KEY `order_id` (`order_id`);

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
  MODIFY `cart_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `categories`
--
ALTER TABLE `categories`
  MODIFY `category_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- AUTO_INCREMENT for table `menu_items`
--
ALTER TABLE `menu_items`
  MODIFY `item_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=116;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `notification_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `orders`
--
ALTER TABLE `orders`
  MODIFY `order_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `order_items`
--
ALTER TABLE `order_items`
  MODIFY `order_item_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `payments`
--
ALTER TABLE `payments`
  MODIFY `payment_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

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
