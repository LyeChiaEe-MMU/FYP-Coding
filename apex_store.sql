-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jun 17, 2026 at 06:46 AM
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
-- Database: `apex_store`
--

-- --------------------------------------------------------

--
-- Table structure for table `admins`
--

CREATE TABLE `admins` (
  `admin_id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `remember_token` varchar(255) DEFAULT NULL,
  `token_expiry` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `admins`
--

INSERT INTO `admins` (`admin_id`, `username`, `password`, `created_at`, `remember_token`, `token_expiry`) VALUES
(1, 'admin', '$2y$10$iMb4ED02vNT6tJueiFRhuu3PxnlCJcxGPASAag7rVQzg4Ai44axoS', '2026-05-20 14:04:00', NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `cart_items`
--

CREATE TABLE `cart_items` (
  `cart_id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `product_id` int(11) DEFAULT NULL,
  `size` varchar(10) DEFAULT NULL,
  `color` varchar(80) NOT NULL DEFAULT 'Default',
  `quantity` int(11) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `categories`
--

CREATE TABLE `categories` (
  `category_id` int(11) NOT NULL,
  `category_name` varchar(50) NOT NULL,
  `banner_image` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `categories`
--

INSERT INTO `categories` (`category_id`, `category_name`, `banner_image`) VALUES
(1, 'Running', NULL),
(2, 'Basketball', NULL),
(3, 'Lifestyle', NULL),
(4, 'Training', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `contact_messages`
--

CREATE TABLE `contact_messages` (
  `message_id` int(11) NOT NULL,
  `name` varchar(120) NOT NULL,
  `email` varchar(180) NOT NULL,
  `subject` varchar(120) NOT NULL,
  `message` text NOT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `design_requests`
--

CREATE TABLE `design_requests` (
  `request_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `shoe_name` varchar(150) NOT NULL,
  `category` varchar(50) NOT NULL,
  `color_pref` varchar(150) NOT NULL,
  `description` text NOT NULL,
  `specifications` text DEFAULT NULL,
  `ref_image` varchar(300) DEFAULT NULL,
  `status` enum('Pending','In Review','Approved','Rejected') NOT NULL DEFAULT 'Pending',
  `admin_note` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `design_requests`
--

INSERT INTO `design_requests` (`request_id`, `user_id`, `shoe_name`, `category`, `color_pref`, `description`, `specifications`, `ref_image`, `status`, `admin_note`, `created_at`, `updated_at`) VALUES
(1, 1, 'AP Strider', 'Running', 'Electric Cobalt / Neon Lime', 'The shoe features a sleek, aerodynamic silhouette with a dominant electric cobalt blue mesh upper, providing a modern and energetic aesthetic. It includes neon lime accent detailing on the heel tab and midsole, which provides a high-contrast pop of color. The design is finished with a crisp white transition layer in the midsole, balancing the bold color choices with a clean, professional look.', 'Upper: Breathable engineered mesh for lightweight comfort and airflow.\r\n\r\nMidsole: Dual-density foam construction for impact absorption and responsive energy return.\r\n\r\nOutsole: Durable rubber tread optimized for multi-surface traction and stability.\r\n\r\nHeel: Integrated pull tab for ease of wear and reinforced heel counter for added support.', 'uploads/designs/design_1_1781634445.png', 'In Review', 'So far looking great, will go to review and might add changes', '2026-06-17 02:27:25', '2026-06-17 03:46:19');

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `notif_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `type` varchar(50) NOT NULL DEFAULT 'info',
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `notifications`
--

INSERT INTO `notifications` (`notif_id`, `user_id`, `title`, `message`, `type`, `is_read`, `created_at`) VALUES
(1, 1, 'Order Placed — #000001', 'Your order #000001 has been placed successfully via Online Banking — RHB Bank. Total paid: RM 449.00. We will process it shortly.', 'order', 1, '2026-06-16 18:12:57'),
(2, 1, 'Order #000001 Has Been Delivered!', 'Great news! Your order #000001 has been delivered to your address. Please click \"Mark as Received\" on your order once you have the package in hand.', 'delivery', 1, '2026-06-16 18:21:28'),
(3, 1, 'Order #000001 Received — Thank You!', 'You have confirmed receipt of order #000001. We hope you love your new shoes! You can now write a review for any item in this order.', 'success', 1, '2026-06-16 18:21:52'),
(4, 1, 'Review Submitted — Thank You!', 'Your ★★★★★ review for \"AP Blossom\" has been published. Your feedback helps other shoppers make better choices!', 'review', 1, '2026-06-16 18:22:14'),
(5, 1, 'Design Request Update — AP Strider', 'Your design request status has been updated.\n\nMessage from Apex: So far looking great, will go to review and might add changes', 'info', 1, '2026-06-16 19:45:33'),
(6, 1, 'Design Request Update — AP Strider', 'Your design request status has been updated.\n\nMessage from Apex: So far looking great, will go to review and might add changes', 'info', 1, '2026-06-16 19:45:37'),
(7, 1, 'Design Request Update — AP Strider', 'Your design request is now being reviewed by our team.\n\nMessage from Apex: So far looking great, will go to review and might add changes', 'info', 1, '2026-06-16 19:45:41'),
(8, 1, 'Design Request Update — AP Strider', 'Great news! Your design request has been approved.\n\nMessage from Apex: So far looking great, will go to review and might add changes', 'info', 1, '2026-06-16 19:45:45'),
(9, 1, 'Design Request Update — AP Strider', 'Great news! Your design request has been approved.\n\nMessage from Apex: So far looking great, will go to review and might add changes', 'info', 1, '2026-06-16 19:45:49'),
(10, 1, 'Design Request Update — AP Strider', 'Great news! Your design request has been approved.\n\nMessage from Apex: So far looking great, will go to review and might add changes', 'info', 1, '2026-06-16 19:45:52'),
(11, 1, 'Design Request Update — AP Strider', 'Great news! Your design request has been approved.\n\nMessage from Apex: So far looking great, will go to review and might add changes', 'info', 1, '2026-06-16 19:45:57'),
(12, 1, 'Design Request Update — AP Strider', 'Great news! Your design request has been approved.\n\nMessage from Apex: So far looking great, will go to review and might add changes', 'info', 1, '2026-06-16 19:46:01'),
(13, 1, 'Design Request Update — AP Strider', 'Great news! Your design request has been approved.\n\nMessage from Apex: So far looking great, will go to review and might add changes', 'info', 1, '2026-06-16 19:46:04'),
(14, 1, 'Design Request Update — AP Strider', 'Great news! Your design request has been approved.\n\nMessage from Apex: So far looking great, will go to review and might add changes', 'info', 1, '2026-06-16 19:46:07'),
(15, 1, 'Design Request Update — AP Strider', 'Your design request is now being reviewed by our team.\n\nMessage from Apex: So far looking great, will go to review and might add changes', 'info', 1, '2026-06-16 19:46:19');

-- --------------------------------------------------------

--
-- Table structure for table `orders`
--

CREATE TABLE `orders` (
  `order_id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `total_amount` decimal(10,2) NOT NULL,
  `discount_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `voucher_code` varchar(20) DEFAULT NULL,
  `promo_id` int(11) DEFAULT NULL,
  `status` varchar(30) DEFAULT 'Processing',
  `shipping_address` text DEFAULT NULL,
  `payment_method` varchar(50) DEFAULT 'Online Banking',
  `payment_detail` varchar(100) DEFAULT NULL,
  `order_date` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `orders`
--

INSERT INTO `orders` (`order_id`, `user_id`, `total_amount`, `discount_amount`, `voucher_code`, `promo_id`, `status`, `shipping_address`, `payment_method`, `payment_detail`, `order_date`) VALUES
(1, 1, 449.00, 0.00, '', NULL, 'Completed', '57,Jalan Raja Endut,Kampung Merdeka, Batu Pahat, Johor, 83000', 'Online Banking', 'RHB Bank', '2026-06-16 18:12:57');

-- --------------------------------------------------------

--
-- Table structure for table `order_items`
--

CREATE TABLE `order_items` (
  `order_item_id` int(11) NOT NULL,
  `order_id` int(11) DEFAULT NULL,
  `product_id` int(11) DEFAULT NULL,
  `size` varchar(10) DEFAULT NULL,
  `color` varchar(80) NOT NULL DEFAULT 'Default',
  `quantity` int(11) NOT NULL,
  `price` decimal(10,2) NOT NULL,
  `original_price` decimal(10,2) NOT NULL DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `order_items`
--

INSERT INTO `order_items` (`order_item_id`, `order_id`, `product_id`, `size`, `color`, `quantity`, `price`, `original_price`) VALUES
(1, 1, 14, '7.5', 'Soft Petal Pink', 1, 449.00, 449.00);

-- --------------------------------------------------------

--
-- Table structure for table `order_status_history`
--

CREATE TABLE `order_status_history` (
  `history_id` int(11) NOT NULL,
  `order_id` int(11) DEFAULT NULL,
  `status` varchar(30) NOT NULL,
  `changed_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `order_status_history`
--

INSERT INTO `order_status_history` (`history_id`, `order_id`, `status`, `changed_at`) VALUES
(1, 1, 'Processing', '2026-06-16 18:12:57'),
(2, 1, 'Delivered', '2026-06-16 18:21:28'),
(3, 1, 'Completed', '2026-06-16 18:21:52');

-- --------------------------------------------------------

--
-- Table structure for table `products`
--

CREATE TABLE `products` (
  `product_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text NOT NULL,
  `category_id` int(11) DEFAULT NULL,
  `gender` varchar(20) NOT NULL DEFAULT 'Unisex',
  `price` decimal(10,2) NOT NULL,
  `stock` int(11) NOT NULL DEFAULT 0,
  `is_on_sale` tinyint(1) NOT NULL DEFAULT 0,
  `sale_percent` tinyint(3) UNSIGNED NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `image_url` varchar(255) NOT NULL DEFAULT '',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `products`
--

INSERT INTO `products` (`product_id`, `name`, `description`, `category_id`, `gender`, `price`, `stock`, `is_on_sale`, `sale_percent`, `is_active`, `image_url`, `created_at`) VALUES
(4, 'Apex Court', 'Run', 1, 'Women', 459.00, 260, 0, 0, 0, 'uploads/product_1781532838_Gemini_Generated_Image_gvdhuhgvdhuhgvdh.png', '2026-05-21 01:10:34'),
(5, 'AP Velocity', 'The AP Velocity is designed for athletes who demand precision and speed, blending professional performance with a refined aesthetic. Featuring our signature Midnight Carbon / Cloud White colorway, the shoe utilizes a high-traction outsole pattern that ensures superior grip on varied surfaces. The upper is constructed from an advanced breathable mesh, specifically engineered to provide a lightweight, secure fit that conforms to the foot during intense training sessions.\r\n\r\nAt the core of the shoe’s performance is a high-response cushioned midsole, meticulously balanced to maximize energy return while minimizing impact stress on the joints. The seamless integration of the AP branding across the quarter panel and heel provides a modern, professional look that adheres to a clean and minimalist design language. By removing unnecessary embellishments, the silhouette remains streamlined, focusing entirely on structural integrity and aerodynamic efficiency.\r\n\r\nPerfect for both marathon pacing and daily training, the AP Velocity represents the intersection of technical capability and modern athletic style. The structural layout—from the reinforced heel counter to the responsive forefoot—is built to sustain performance over long distances. This combination of comfort, durability, and a clean visual profile makes it an essential component for any serious runner’s gear collection.', 1, 'Men', 599.00, 616, 0, 0, 0, 'uploads/product_1781532445_product_1780763578_dreamina-2026-06-07-6600-make_me_a_running_shoe_with_AP_Logo__can....png', '2026-06-15 14:07:25'),
(6, 'AP Pulse', 'The AP Pulse is built for high-visibility performance and bold style. Featuring a muted charcoal mesh upper that contrasts sharply with a vibrant, high-energy fuchsia midsole, this shoe is engineered for runners who want to stand out during evening training sessions. The construction balances a secure lockdown fit with a plush, responsive foam base designed to absorb impact while providing a springy feel.', 1, 'Men', 459.00, 322, 0, 0, 1, 'uploads/product_1781533420_product_1780931220_Gemini_Generated_Image_8qt6pl8qt6pl8qt6.png', '2026-06-15 14:23:40'),
(7, 'AP Endurance', 'The AP Endurance is engineered for high-mileage training, prioritizing comfort and structural stability for the dedicated runner. The upper features a high-density, multi-layered mesh that provides exceptional breathability and foot-locking support, while the vibrant crimson accents highlight the shoe\'s aggressive, performance-focused silhouette. Designed with a focus on long-distance durability, the exterior maintains a crisp, professional appearance that stands out on both the track and the road.\r\n\r\nBuilt upon a high-performance, segmented outsole, this model delivers superior shock absorption and consistent traction across various terrains. The midsole utilizes advanced cushioning technology to ensure a smooth transition from heel-strike to toe-off, effectively reducing fatigue during extended runs. By combining a clean, technical aesthetic with rugged construction, the AP Endurance offers a reliable and stylish solution for athletes who refuse to compromise on performance.', 1, 'Men', 449.00, 156, 0, 0, 0, 'uploads/product_1781534363_product_1780939229_Gemini_Generated_Image_r2ieszr2ieszr2ie.png', '2026-06-15 14:39:23'),
(8, 'AP Ignite', 'The AP Ignite is engineered to deliver high-energy performance with a striking visual profile. The shoe features an innovative gradient mesh upper that transitions smoothly from deep onyx at the collar to a fiery orange and radiant yellow silhouette toward the forefoot. This seamless construction provides exceptional breathability and lightweight containment, ensuring the foot stays cool and secure through intensive speed workouts or fast-paced road races.\r\n\r\nThe performance-driven sole unit is built with a dual-density cushioned midsole that optimizes shock absorption and maximizes forward propulsion. Its bold neon accents along the midsole and structured heel counter accentuate the shoe\'s sleek, aerodynamic design. Completed with a durable rubber outsole pattern optimized for multi-surface grip, the AP Ignite brings a clean yet fiercely energetic aesthetic to the track, offering serious runners the perfect balance of responsiveness, stability, and bold modern style.', 1, 'Men', 349.00, 299, 0, 0, 1, 'uploads/product_1781535137_product_1780980089_Gemini_Generated_Image_uv65xguv65xguv65.png', '2026-06-15 14:52:17'),
(9, 'AP Terra', 'The AP Terra is designed for the modern explorer, blending rugged trail functionality with a versatile lifestyle aesthetic. Crafted with a premium suede upper in a rich desert ochre, the shoe features reinforced deep navy overlays that provide structural integrity and a distinctive color-blocked look. Its trail-ready architecture includes a high-traction, lugged outsole designed to handle varied terrain, making it the perfect companion for both off-road adventures and casual urban settings.\r\n\r\nBeyond its durable exterior, the AP Terra prioritizes all-day comfort with an ergonomic fit and responsive cushioning that absorbs impact on uneven surfaces. The vibrant orange laces and matching branding add a touch of high-energy flair to the earthy tones, ensuring a stylish presence whether on the trails or the street. By marrying technical outdoor performance with a clean, contemporary design, the AP Terra delivers a robust and reliable option for those who demand both versatility and resilience in their everyday footwear.', 3, 'Men', 669.00, 150, 0, 0, 1, 'uploads/product_1781535496_product_1780981586_Gemini_Generated_Image_nuth5bnuth5bnuth.png', '2026-06-15 14:58:16'),
(10, 'AP Terra W', 'The AP Terra W is a refined, adventure-ready lifestyle shoe tailored specifically for women, offering the perfect blend of outdoor durability and everyday comfort. Featuring a sophisticated tonal palette of clay dust suede and sandstone accents, this model maintains the signature trail-capable rugged outsole while presenting a softer, more versatile aesthetic suitable for both hiking trails and city commutes.\r\n\r\nDesigned with ergonomics in mind, the shoe provides a secure, lightweight fit that supports natural movement on uneven terrain. The monochromatic color approach, paired with premium materials, creates a polished look that transitions easily from active outings to casual wear. By balancing technical grip and structural resilience with an elegant, earthy design, the AP Terra W offers a versatile and stylish choice for the active, modern woman.', 3, 'Women', 669.00, 110, 0, 0, 1, 'uploads/product_1781535569_product_1780981692_Gemini_Generated_Image_6d4fir6d4fir6d4f.png', '2026-06-15 14:59:29'),
(11, 'AP Court', 'The AP Court is a high-performance basketball sneaker designed to provide elite-level support and explosive responsiveness on the hardwood. Featuring a mid-top silhouette, it offers superior ankle stabilization without sacrificing the agility required for quick cuts and fast breaks. The lightweight, breathable mesh upper is reinforced with durable synthetic overlays, ensuring a secure lockdown fit that withstands the high-intensity demands of competitive play.\r\n\r\nEngineered for optimal court feel and energy return, the midsole utilizes a high-rebound cushioning system that effectively absorbs impact during landings and transitions. The specialized rubber outsole features a multidirectional herringbone tread pattern, delivering exceptional grip for precise pivoting and explosive starts. By combining a modern, sharp aesthetic with technical functionality, the AP Court empowers players to maintain peak performance and style throughout the game.', 2, 'Men', 559.00, 315, 1, 20, 1, 'uploads/product_1781536033_product_1780982762_Gemini_Generated_Image_o7q3uwo7q3uwo7q3.png', '2026-06-15 15:07:13'),
(12, 'AP Apex', 'The AP Apex is a premium training shoe engineered specifically to meet the rigorous demands of high-intensity gym sessions, functional fitness, and weight training. The upper is constructed from a high-tensile, abrasion-resistant woven mesh that offers maximum durability while maintaining exceptional breathability. A low-profile, flat-sole architecture ensures close-to-the-ground contact, providing a rock-solid foundation for lifting and explosive lateral movements.\r\n\r\nFeaturing a dual-density midsole, this model provides firm stability in the heel for heavy lifts alongside a flexible, responsive forefoot that adapts to short sprints and box jumps. The sleek dark charcoal aesthetic is paired with dynamic neon green support bands wrap around the midfoot, ensuring superior lockdown and lateral stability during fast cuts. Completed with a full-coverage, high-traction rubber outsole, the AP Apex delivers the perfect combination of unyielding support, agility, and aggressive modern styling.', 4, 'Men', 229.00, 157, 0, 0, 1, 'uploads/product_1781536282_product_1781113107_Gemini_Generated_Image_xo69cbxo69cbxo69.png', '2026-06-15 15:11:22'),
(13, 'AP Velocity Carbon', 'The AP Velocity Carbon is engineered for runners who prioritize speed and a high-energy response. Featuring a vibrant Infrared Flare mesh upper, the shoe provides superior airflow and a lightweight fit. The bold, minimalist design is accented by a striking carbon fiber-textured midsole, which serves as the foundation for both stability and forward propulsion.\r\n\r\nAt its core, the shoe utilizes a specialized outsole tread pattern designed for optimal traction on hard surfaces, making it an ideal choice for road training or competitive track sessions. The integrated AP branding and sleek aesthetic ensure a professional, modern look that matches its high-performance capabilities. This combination of structural durability, responsive cushioning, and eye-catching color makes the AP Velocity Carbon a standout choice for athletes looking to improve their pace.', 1, 'Men', 568.97, 150, 0, 0, 1, 'uploads/product_1781633028_product_1781117666_Gemini_Generated_Image_ayagztayagztayag.png', '2026-06-16 18:03:48'),
(14, 'AP Blossom', 'The AP Blossom is a lightweight running shoe designed for comfort and effortless style, perfect for daily training and casual movement. The upper is crafted from a breathable, open-knit mesh that ensures excellent ventilation and a soft, flexible fit that adapts to the foot\'s natural motion.\r\n\r\nBuilt for everyday versatility, the shoe features a supportive, cushioned midsole that provides smooth impact absorption and a comfortable stride on various surfaces. The durable outsole is engineered with a specialized grip pattern to ensure reliable traction, while the minimalist, monochromatic Soft Petal Pink aesthetic offers a clean and elegant look for any athletic or lifestyle outfit.', 1, 'Men', 449.00, 159, 0, 0, 1, 'uploads/product_1781633369_product_1781118292_Gemini_Generated_Image_imfr84imfr84imfr.png', '2026-06-16 18:09:29');

-- --------------------------------------------------------

--
-- Table structure for table `product_images`
--

CREATE TABLE `product_images` (
  `image_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `image_url` varchar(300) NOT NULL,
  `color_name` varchar(80) DEFAULT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `product_images`
--

INSERT INTO `product_images` (`image_id`, `product_id`, `image_url`, `color_name`, `sort_order`) VALUES
(3, 5, 'uploads/variant_5_1781532568.png', 'Carbon Volt', 1),
(4, 5, 'uploads/variant_5_1781532582.png', 'Arctic Ghost', 2),
(5, 5, 'uploads/variant_5_1781532595.png', 'Navy Velocity', 3),
(6, 6, 'uploads/variant_6_1781533784.png', 'Sky Gold', 1),
(7, 8, 'uploads/variant_8_1781535248.png', 'Carbon Cobalt', 1),
(8, 11, 'uploads/variant_11_1781536099.png', 'Deep Navy', 1);

-- --------------------------------------------------------

--
-- Table structure for table `product_size`
--

CREATE TABLE `product_size` (
  `size_id` int(11) NOT NULL,
  `product_id` int(11) DEFAULT NULL,
  `size` varchar(10) NOT NULL,
  `stock_for_size` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `product_stock`
--

CREATE TABLE `product_stock` (
  `stock_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `color_name` varchar(80) NOT NULL DEFAULT 'Default',
  `size` varchar(10) NOT NULL,
  `stock` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `product_stock`
--

INSERT INTO `product_stock` (`stock_id`, `product_id`, `color_name`, `size`, `stock`) VALUES
(1, 4, 'Default', '6', 10),
(2, 4, 'Default', '6.5', 10),
(3, 4, 'Default', '7', 10),
(4, 4, 'Default', '7.5', 10),
(5, 4, 'Default', '8', 10),
(6, 4, 'Default', '8.5', 10),
(7, 4, 'Default', '9', 10),
(8, 4, 'Default', '9.5', 10),
(9, 4, 'Default', '10', 10),
(10, 4, 'Default', '10.5', 15),
(11, 4, 'Default', '11', 5),
(12, 4, 'Default', '11.5', 10),
(13, 4, 'Default', '12', 10),
(14, 4, 'Red/Black', '6', 10),
(15, 4, 'Red/Black', '6.5', 10),
(16, 4, 'Red/Black', '7', 10),
(17, 4, 'Red/Black', '7.5', 10),
(18, 4, 'Red/Black', '8', 10),
(19, 4, 'Red/Black', '8.5', 10),
(20, 4, 'Red/Black', '9', 10),
(21, 4, 'Red/Black', '9.5', 10),
(22, 4, 'Red/Black', '10', 10),
(23, 4, 'Red/Black', '10.5', 10),
(24, 4, 'Red/Black', '11', 10),
(25, 4, 'Red/Black', '11.5', 10),
(26, 4, 'Red/Black', '12', 10),
(27, 5, 'Midnight Carbon', '6', 7),
(28, 5, 'Midnight Carbon', '6.5', 8),
(29, 5, 'Midnight Carbon', '7', 9),
(30, 5, 'Midnight Carbon', '7.5', 10),
(31, 5, 'Midnight Carbon', '8', 11),
(32, 5, 'Midnight Carbon', '8.5', 12),
(33, 5, 'Midnight Carbon', '9', 13),
(34, 5, 'Midnight Carbon', '9.5', 14),
(35, 5, 'Midnight Carbon', '10', 15),
(36, 5, 'Midnight Carbon', '10.5', 12),
(37, 5, 'Midnight Carbon', '11', 11),
(38, 5, 'Midnight Carbon', '11.5', 10),
(39, 5, 'Midnight Carbon', '12', 9),
(40, 5, 'Midnight Carbon', '13', 8),
(41, 5, 'Carbon Volt', '6', 7),
(42, 5, 'Carbon Volt', '6.5', 8),
(43, 5, 'Carbon Volt', '7', 9),
(44, 5, 'Carbon Volt', '7.5', 10),
(45, 5, 'Carbon Volt', '8', 11),
(46, 5, 'Carbon Volt', '8.5', 12),
(47, 5, 'Carbon Volt', '9', 13),
(48, 5, 'Carbon Volt', '9.5', 14),
(49, 5, 'Carbon Volt', '10', 15),
(50, 5, 'Carbon Volt', '10.5', 16),
(51, 5, 'Carbon Volt', '11', 11),
(52, 5, 'Carbon Volt', '11.5', 12),
(53, 5, 'Carbon Volt', '12', 13),
(54, 5, 'Carbon Volt', '13', 9),
(55, 5, 'Arctic Ghost', '6', 7),
(56, 5, 'Arctic Ghost', '6.5', 9),
(57, 5, 'Arctic Ghost', '7', 7),
(58, 5, 'Arctic Ghost', '7.5', 12),
(59, 5, 'Arctic Ghost', '8', 10),
(60, 5, 'Arctic Ghost', '8.5', 10),
(61, 5, 'Arctic Ghost', '9', 13),
(62, 5, 'Arctic Ghost', '9.5', 14),
(63, 5, 'Arctic Ghost', '10', 15),
(64, 5, 'Arctic Ghost', '10.5', 11),
(65, 5, 'Arctic Ghost', '11', 12),
(66, 5, 'Arctic Ghost', '11.5', 13),
(67, 5, 'Arctic Ghost', '12', 11),
(68, 5, 'Arctic Ghost', '13', 10),
(69, 5, 'Navy Velocity', '6', 10),
(70, 5, 'Navy Velocity', '6.5', 14),
(71, 5, 'Navy Velocity', '7', 12),
(72, 5, 'Navy Velocity', '7.5', 15),
(73, 5, 'Navy Velocity', '8', 10),
(74, 5, 'Navy Velocity', '8.5', 12),
(75, 5, 'Navy Velocity', '9', 11),
(76, 5, 'Navy Velocity', '9.5', 8),
(77, 5, 'Navy Velocity', '10', 9),
(78, 5, 'Navy Velocity', '10.5', 10),
(79, 5, 'Navy Velocity', '11', 9),
(80, 5, 'Navy Velocity', '11.5', 10),
(81, 5, 'Navy Velocity', '12', 10),
(82, 5, 'Navy Velocity', '13', 13),
(83, 6, 'Charcoal Neon', '6', 7),
(84, 6, 'Charcoal Neon', '6.5', 8),
(85, 6, 'Charcoal Neon', '7', 9),
(86, 6, 'Charcoal Neon', '7.5', 10),
(87, 6, 'Charcoal Neon', '8', 11),
(88, 6, 'Charcoal Neon', '8.5', 13),
(89, 6, 'Charcoal Neon', '9', 14),
(90, 6, 'Charcoal Neon', '9.5', 15),
(91, 6, 'Charcoal Neon', '10', 11),
(92, 6, 'Charcoal Neon', '10.5', 12),
(93, 6, 'Charcoal Neon', '11', 21),
(94, 6, 'Charcoal Neon', '11.5', 12),
(95, 6, 'Charcoal Neon', '12', 14),
(96, 6, 'Charcoal Neon', '13', 11),
(97, 6, 'Sky Gold', '6', 7),
(98, 6, 'Sky Gold', '6.5', 8),
(99, 6, 'Sky Gold', '7', 9),
(100, 6, 'Sky Gold', '7.5', 11),
(101, 6, 'Sky Gold', '8', 13),
(102, 6, 'Sky Gold', '8.5', 10),
(103, 6, 'Sky Gold', '9', 14),
(104, 6, 'Sky Gold', '9.5', 15),
(105, 6, 'Sky Gold', '10', 13),
(106, 6, 'Sky Gold', '10.5', 12),
(107, 6, 'Sky Gold', '11', 13),
(108, 6, 'Sky Gold', '11.5', 12),
(109, 6, 'Sky Gold', '12', 8),
(110, 6, 'Sky Gold', '13', 9),
(111, 7, 'Alpine White', '6', 7),
(112, 7, 'Alpine White', '6.5', 8),
(113, 7, 'Alpine White', '7', 9),
(114, 7, 'Alpine White', '7.5', 11),
(115, 7, 'Alpine White', '8', 12),
(116, 7, 'Alpine White', '8.5', 13),
(117, 7, 'Alpine White', '9', 14),
(118, 7, 'Alpine White', '9.5', 15),
(119, 7, 'Alpine White', '10', 11),
(120, 7, 'Alpine White', '10.5', 12),
(121, 7, 'Alpine White', '11', 14),
(122, 7, 'Alpine White', '11.5', 10),
(123, 7, 'Alpine White', '12', 11),
(124, 7, 'Alpine White', '13', 9),
(125, 8, 'Sunset Blaze', '6', 6),
(126, 8, 'Sunset Blaze', '6.5', 7),
(127, 8, 'Sunset Blaze', '7', 8),
(128, 8, 'Sunset Blaze', '7.5', 9),
(129, 8, 'Sunset Blaze', '8', 10),
(130, 8, 'Sunset Blaze', '8.5', 11),
(131, 8, 'Sunset Blaze', '9', 13),
(132, 8, 'Sunset Blaze', '9.5', 14),
(133, 8, 'Sunset Blaze', '10', 12),
(134, 8, 'Sunset Blaze', '10.5', 14),
(135, 8, 'Sunset Blaze', '11', 15),
(136, 8, 'Sunset Blaze', '11.5', 12),
(137, 8, 'Sunset Blaze', '12', 11),
(138, 8, 'Sunset Blaze', '13', 8),
(139, 8, 'Carbon Cobalt', '6', 6),
(140, 8, 'Carbon Cobalt', '6.5', 7),
(141, 8, 'Carbon Cobalt', '7', 8),
(142, 8, 'Carbon Cobalt', '7.5', 9),
(143, 8, 'Carbon Cobalt', '8', 10),
(144, 8, 'Carbon Cobalt', '8.5', 11),
(145, 8, 'Carbon Cobalt', '9', 14),
(146, 8, 'Carbon Cobalt', '9.5', 15),
(147, 8, 'Carbon Cobalt', '10', 11),
(148, 8, 'Carbon Cobalt', '10.5', 12),
(149, 8, 'Carbon Cobalt', '11', 13),
(150, 8, 'Carbon Cobalt', '11.5', 14),
(151, 8, 'Carbon Cobalt', '12', 11),
(152, 8, 'Carbon Cobalt', '13', 8),
(153, 9, 'Desert Ochre', '6', 6),
(154, 9, 'Desert Ochre', '6.5', 7),
(155, 9, 'Desert Ochre', '7', 8),
(156, 9, 'Desert Ochre', '7.5', 9),
(157, 9, 'Desert Ochre', '8', 11),
(158, 9, 'Desert Ochre', '8.5', 13),
(159, 9, 'Desert Ochre', '9', 14),
(160, 9, 'Desert Ochre', '9.5', 12),
(161, 9, 'Desert Ochre', '10', 11),
(162, 9, 'Desert Ochre', '10.5', 14),
(163, 9, 'Desert Ochre', '11', 11),
(164, 9, 'Desert Ochre', '11.5', 15),
(165, 9, 'Desert Ochre', '12', 11),
(166, 9, 'Desert Ochre', '13', 8),
(167, 10, 'Clay Dust', '3', 6),
(168, 10, 'Clay Dust', '3.5', 7),
(169, 10, 'Clay Dust', '4', 8),
(170, 10, 'Clay Dust', '4.5', 9),
(171, 10, 'Clay Dust', '5', 10),
(172, 10, 'Clay Dust', '5.5', 11),
(173, 10, 'Clay Dust', '6', 12),
(174, 10, 'Clay Dust', '6.5', 13),
(175, 10, 'Clay Dust', '7', 14),
(176, 10, 'Clay Dust', '7.5', 11),
(177, 10, 'Clay Dust', '8', 9),
(178, 11, 'Charcoal Graphite', '6', 8),
(179, 11, 'Charcoal Graphite', '6.5', 9),
(180, 11, 'Charcoal Graphite', '7', 10),
(181, 11, 'Charcoal Graphite', '7.5', 11),
(182, 11, 'Charcoal Graphite', '8', 12),
(183, 11, 'Charcoal Graphite', '8.5', 13),
(184, 11, 'Charcoal Graphite', '9', 14),
(185, 11, 'Charcoal Graphite', '9.5', 11),
(186, 11, 'Charcoal Graphite', '10', 12),
(187, 11, 'Charcoal Graphite', '10.5', 14),
(188, 11, 'Charcoal Graphite', '11', 15),
(189, 11, 'Charcoal Graphite', '11.5', 11),
(190, 11, 'Charcoal Graphite', '12', 10),
(191, 11, 'Charcoal Graphite', '13', 8),
(192, 11, 'Deep Navy', '6', 8),
(193, 11, 'Deep Navy', '6.5', 9),
(194, 11, 'Deep Navy', '7', 10),
(195, 11, 'Deep Navy', '7.5', 11),
(196, 11, 'Deep Navy', '8', 12),
(197, 11, 'Deep Navy', '8.5', 13),
(198, 11, 'Deep Navy', '9', 14),
(199, 11, 'Deep Navy', '9.5', 11),
(200, 11, 'Deep Navy', '10', 12),
(201, 11, 'Deep Navy', '10.5', 14),
(202, 11, 'Deep Navy', '11', 11),
(203, 11, 'Deep Navy', '11.5', 15),
(204, 11, 'Deep Navy', '12', 10),
(205, 11, 'Deep Navy', '13', 7),
(206, 12, 'Candy Peach', '6', 6),
(207, 12, 'Candy Peach', '6.5', 7),
(208, 12, 'Candy Peach', '7', 8),
(209, 12, 'Candy Peach', '7.5', 9),
(210, 12, 'Candy Peach', '8', 11),
(211, 12, 'Candy Peach', '8.5', 13),
(212, 12, 'Candy Peach', '9', 14),
(213, 12, 'Candy Peach', '9.5', 15),
(214, 12, 'Candy Peach', '10', 11),
(215, 12, 'Candy Peach', '10.5', 12),
(216, 12, 'Candy Peach', '11', 13),
(217, 12, 'Candy Peach', '11.5', 18),
(218, 12, 'Candy Peach', '12', 11),
(219, 12, 'Candy Peach', '13', 9),
(220, 13, 'Infrared Flare', '6', 7),
(221, 13, 'Infrared Flare', '6.5', 8),
(222, 13, 'Infrared Flare', '7', 9),
(223, 13, 'Infrared Flare', '7.5', 11),
(224, 13, 'Infrared Flare', '8', 12),
(225, 13, 'Infrared Flare', '8.5', 13),
(226, 13, 'Infrared Flare', '9', 14),
(227, 13, 'Infrared Flare', '9.5', 11),
(228, 13, 'Infrared Flare', '10', 12),
(229, 13, 'Infrared Flare', '10.5', 13),
(230, 13, 'Infrared Flare', '11', 14),
(231, 13, 'Infrared Flare', '11.5', 11),
(232, 13, 'Infrared Flare', '12', 10),
(233, 13, 'Infrared Flare', '13', 5),
(234, 14, 'Soft Petal Pink', '6', 7),
(235, 14, 'Soft Petal Pink', '6.5', 8),
(236, 14, 'Soft Petal Pink', '7', 9),
(237, 14, 'Soft Petal Pink', '7.5', 10),
(238, 14, 'Soft Petal Pink', '8', 12),
(239, 14, 'Soft Petal Pink', '8.5', 13),
(240, 14, 'Soft Petal Pink', '9', 11),
(241, 14, 'Soft Petal Pink', '9.5', 12),
(242, 14, 'Soft Petal Pink', '10', 14),
(243, 14, 'Soft Petal Pink', '10.5', 15),
(244, 14, 'Soft Petal Pink', '11', 11),
(245, 14, 'Soft Petal Pink', '11.5', 12),
(246, 14, 'Soft Petal Pink', '12', 13),
(247, 14, 'Soft Petal Pink', '13', 12);

-- --------------------------------------------------------

--
-- Table structure for table `promo_codes`
--

CREATE TABLE `promo_codes` (
  `promo_id` int(11) NOT NULL,
  `code` varchar(20) NOT NULL,
  `discount_percentage` decimal(5,2) NOT NULL,
  `expiry_date` date NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `promo_codes`
--

INSERT INTO `promo_codes` (`promo_id`, `code`, `discount_percentage`, `expiry_date`) VALUES
(1, 'APEX10', 10.00, '2026-12-31'),
(2, 'WELCOME20', 20.00, '2026-12-31'),
(3, 'FYP2026', 15.00, '2026-12-31');

-- --------------------------------------------------------

--
-- Table structure for table `reviews`
--

CREATE TABLE `reviews` (
  `review_id` int(11) NOT NULL,
  `product_id` int(11) DEFAULT NULL,
  `order_id` int(11) NOT NULL DEFAULT 0,
  `user_id` int(11) DEFAULT NULL,
  `rating` int(11) DEFAULT NULL CHECK (`rating` >= 1 and `rating` <= 5),
  `comment` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `reviews`
--

INSERT INTO `reviews` (`review_id`, `product_id`, `order_id`, `user_id`, `rating`, `comment`, `created_at`) VALUES
(1, 14, 1, 1, 5, 'Good Design, and very soft material', '2026-06-16 18:22:14');

-- --------------------------------------------------------

--
-- Table structure for table `site_settings`
--

CREATE TABLE `site_settings` (
  `setting_key` varchar(80) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `site_settings`
--

INSERT INTO `site_settings` (`setting_key`, `setting_value`, `updated_at`) VALUES
('hero_image', 'uploads/banners/hero_1781535921.png', '2026-06-15 15:05:21');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `user_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `phone` varchar(20) NOT NULL,
  `shopping_preference` enum('men','women','kids') DEFAULT NULL,
  `date_of_birth` date DEFAULT NULL,
  `address` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `is_banned` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`user_id`, `name`, `email`, `password`, `phone`, `shopping_preference`, `date_of_birth`, `address`, `created_at`, `is_banned`) VALUES
(1, 'Lye Chia Ee', 'darren060621@gmail.com', '$2y$10$y.HyQm5lEIFj0J48WEGfA.NCOyBwNmR2lAZcSki3d0m.Dml3n3ZRW', '01131908939', 'men', '2006-06-21', '57,Jalan Raja Endut,Kampung Merdeka, Batu Pahat, Johor, 83000', '2026-06-16 17:34:29', 0);

-- --------------------------------------------------------

--
-- Table structure for table `vouchers`
--

CREATE TABLE `vouchers` (
  `voucher_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `code` varchar(20) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `is_used` tinyint(1) NOT NULL DEFAULT 0,
  `reason` varchar(255) NOT NULL DEFAULT '',
  `expires_at` date DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `wishlists`
--

CREATE TABLE `wishlists` (
  `wishlist_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `added_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `wishlist_notifications`
--

CREATE TABLE `wishlist_notifications` (
  `notif_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `message` varchar(300) NOT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `admins`
--
ALTER TABLE `admins`
  ADD PRIMARY KEY (`admin_id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- Indexes for table `cart_items`
--
ALTER TABLE `cart_items`
  ADD PRIMARY KEY (`cart_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `product_id` (`product_id`);

--
-- Indexes for table `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`category_id`),
  ADD UNIQUE KEY `category_name` (`category_name`);

--
-- Indexes for table `contact_messages`
--
ALTER TABLE `contact_messages`
  ADD PRIMARY KEY (`message_id`),
  ADD KEY `idx_cm_read` (`is_read`);

--
-- Indexes for table `design_requests`
--
ALTER TABLE `design_requests`
  ADD PRIMARY KEY (`request_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`notif_id`),
  ADD KEY `idx_notif_uid_read` (`user_id`,`is_read`);

--
-- Indexes for table `orders`
--
ALTER TABLE `orders`
  ADD PRIMARY KEY (`order_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `promo_id` (`promo_id`);

--
-- Indexes for table `order_items`
--
ALTER TABLE `order_items`
  ADD PRIMARY KEY (`order_item_id`),
  ADD KEY `order_id` (`order_id`),
  ADD KEY `product_id` (`product_id`);

--
-- Indexes for table `order_status_history`
--
ALTER TABLE `order_status_history`
  ADD PRIMARY KEY (`history_id`),
  ADD KEY `order_id` (`order_id`);

--
-- Indexes for table `products`
--
ALTER TABLE `products`
  ADD PRIMARY KEY (`product_id`),
  ADD KEY `category_id` (`category_id`);

--
-- Indexes for table `product_images`
--
ALTER TABLE `product_images`
  ADD PRIMARY KEY (`image_id`),
  ADD KEY `product_id` (`product_id`);

--
-- Indexes for table `product_size`
--
ALTER TABLE `product_size`
  ADD PRIMARY KEY (`size_id`),
  ADD KEY `product_id` (`product_id`);

--
-- Indexes for table `product_stock`
--
ALTER TABLE `product_stock`
  ADD PRIMARY KEY (`stock_id`),
  ADD UNIQUE KEY `uq_pcs` (`product_id`,`color_name`,`size`);

--
-- Indexes for table `promo_codes`
--
ALTER TABLE `promo_codes`
  ADD PRIMARY KEY (`promo_id`),
  ADD UNIQUE KEY `code` (`code`);

--
-- Indexes for table `reviews`
--
ALTER TABLE `reviews`
  ADD PRIMARY KEY (`review_id`),
  ADD UNIQUE KEY `uq_review` (`user_id`,`product_id`,`order_id`),
  ADD KEY `product_id` (`product_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `site_settings`
--
ALTER TABLE `site_settings`
  ADD PRIMARY KEY (`setting_key`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `vouchers`
--
ALTER TABLE `vouchers`
  ADD PRIMARY KEY (`voucher_id`),
  ADD UNIQUE KEY `code` (`code`),
  ADD KEY `idx_voucher_uid` (`user_id`);

--
-- Indexes for table `wishlists`
--
ALTER TABLE `wishlists`
  ADD PRIMARY KEY (`wishlist_id`),
  ADD UNIQUE KEY `uq_up` (`user_id`,`product_id`);

--
-- Indexes for table `wishlist_notifications`
--
ALTER TABLE `wishlist_notifications`
  ADD PRIMARY KEY (`notif_id`),
  ADD KEY `user_id` (`user_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `admins`
--
ALTER TABLE `admins`
  MODIFY `admin_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `cart_items`
--
ALTER TABLE `cart_items`
  MODIFY `cart_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `categories`
--
ALTER TABLE `categories`
  MODIFY `category_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `contact_messages`
--
ALTER TABLE `contact_messages`
  MODIFY `message_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `design_requests`
--
ALTER TABLE `design_requests`
  MODIFY `request_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `notif_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `orders`
--
ALTER TABLE `orders`
  MODIFY `order_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `order_items`
--
ALTER TABLE `order_items`
  MODIFY `order_item_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `order_status_history`
--
ALTER TABLE `order_status_history`
  MODIFY `history_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `products`
--
ALTER TABLE `products`
  MODIFY `product_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `product_images`
--
ALTER TABLE `product_images`
  MODIFY `image_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `product_size`
--
ALTER TABLE `product_size`
  MODIFY `size_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `product_stock`
--
ALTER TABLE `product_stock`
  MODIFY `stock_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=248;

--
-- AUTO_INCREMENT for table `promo_codes`
--
ALTER TABLE `promo_codes`
  MODIFY `promo_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `reviews`
--
ALTER TABLE `reviews`
  MODIFY `review_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `vouchers`
--
ALTER TABLE `vouchers`
  MODIFY `voucher_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `wishlists`
--
ALTER TABLE `wishlists`
  MODIFY `wishlist_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `wishlist_notifications`
--
ALTER TABLE `wishlist_notifications`
  MODIFY `notif_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `cart_items`
--
ALTER TABLE `cart_items`
  ADD CONSTRAINT `cart_items_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `cart_items_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`product_id`) ON DELETE CASCADE;

--
-- Constraints for table `design_requests`
--
ALTER TABLE `design_requests`
  ADD CONSTRAINT `design_requests_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `orders`
--
ALTER TABLE `orders`
  ADD CONSTRAINT `orders_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`),
  ADD CONSTRAINT `orders_ibfk_2` FOREIGN KEY (`promo_id`) REFERENCES `promo_codes` (`promo_id`);

--
-- Constraints for table `order_items`
--
ALTER TABLE `order_items`
  ADD CONSTRAINT `order_items_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`order_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `order_items_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`product_id`);

--
-- Constraints for table `order_status_history`
--
ALTER TABLE `order_status_history`
  ADD CONSTRAINT `order_status_history_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`order_id`) ON DELETE CASCADE;

--
-- Constraints for table `products`
--
ALTER TABLE `products`
  ADD CONSTRAINT `products_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `categories` (`category_id`);

--
-- Constraints for table `product_images`
--
ALTER TABLE `product_images`
  ADD CONSTRAINT `product_images_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`product_id`) ON DELETE CASCADE;

--
-- Constraints for table `product_size`
--
ALTER TABLE `product_size`
  ADD CONSTRAINT `product_size_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`product_id`) ON DELETE CASCADE;

--
-- Constraints for table `product_stock`
--
ALTER TABLE `product_stock`
  ADD CONSTRAINT `product_stock_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`product_id`) ON DELETE CASCADE;

--
-- Constraints for table `reviews`
--
ALTER TABLE `reviews`
  ADD CONSTRAINT `reviews_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`product_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `reviews_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
