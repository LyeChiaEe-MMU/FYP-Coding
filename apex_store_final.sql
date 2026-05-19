-- ================================================================
--  APEX STORE — Complete Database (Combined)
--  Includes original tables + new design_requests for FYP features
--  Run this FRESH in phpMyAdmin to set up everything at once
-- ================================================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";
SET NAMES utf8mb4;

DROP DATABASE IF EXISTS apex_store;
CREATE DATABASE apex_store CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE apex_store;

-- ────────────────────────────────────────────
-- 1. ADMINS
-- ────────────────────────────────────────────
CREATE TABLE `admins` (
  `admin_id`       int(11)      NOT NULL AUTO_INCREMENT,
  `username`       varchar(50)  NOT NULL,
  `password`       varchar(255) NOT NULL,
  `created_at`     timestamp    NOT NULL DEFAULT current_timestamp(),
  `remember_token` varchar(255) DEFAULT NULL,
  `token_expiry`   int(11)      DEFAULT NULL,
  PRIMARY KEY (`admin_id`),
  UNIQUE KEY `username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Default admin (password: admin123)
INSERT INTO `admins` (`username`, `password`) VALUES
('admin', '$2y$10$iMb4ED02vNT6tJueiFRhuu3PxnlCJcxGPASAag7rVQzg4Ai44axoS');

-- ────────────────────────────────────────────
-- 2. USERS
-- ────────────────────────────────────────────
CREATE TABLE `users` (
  `user_id`    int(11)      NOT NULL AUTO_INCREMENT,
  `name`       varchar(100) NOT NULL,
  `email`      varchar(100) NOT NULL,
  `password`   varchar(255) NOT NULL,
  `phone`      varchar(20)  NOT NULL,
  `address`    text         NOT NULL,
  `created_at` timestamp    NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`user_id`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ────────────────────────────────────────────
-- 3. CATEGORIES
-- ────────────────────────────────────────────
CREATE TABLE `categories` (
  `category_id`   int(11)     NOT NULL AUTO_INCREMENT,
  `category_name` varchar(50) NOT NULL,
  PRIMARY KEY (`category_id`),
  UNIQUE KEY `category_name` (`category_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `categories` (`category_id`, `category_name`) VALUES
(1, 'Running'),
(2, 'Basketball'),
(3, 'Lifestyle'),
(4, 'Training');

-- ────────────────────────────────────────────
-- 4. PRODUCTS
-- ────────────────────────────────────────────
CREATE TABLE `products` (
  `product_id`  int(11)        NOT NULL AUTO_INCREMENT,
  `name`        varchar(100)   NOT NULL,
  `description` text           NOT NULL,
  `category_id` int(11)        DEFAULT NULL,
  `price`       decimal(10,2)  NOT NULL,
  `stock`       int(11)        NOT NULL DEFAULT 0,
  `image_url`   varchar(255)   NOT NULL DEFAULT '',
  `created_at`  timestamp      NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`product_id`),
  KEY `category_id` (`category_id`),
  CONSTRAINT `products_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `categories` (`category_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ────────────────────────────────────────────
-- 5. PRODUCT SIZE (per-size inventory)
-- ────────────────────────────────────────────
CREATE TABLE `product_size` (
  `size_id`       int(11)     NOT NULL AUTO_INCREMENT,
  `product_id`    int(11)     DEFAULT NULL,
  `size`          varchar(10) NOT NULL,
  `stock_for_size` int(11)    NOT NULL DEFAULT 0,
  PRIMARY KEY (`size_id`),
  KEY `product_id` (`product_id`),
  CONSTRAINT `product_size_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`product_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ────────────────────────────────────────────
-- 6. PROMO CODES
-- ────────────────────────────────────────────
CREATE TABLE `promo_codes` (
  `promo_id`            int(11)       NOT NULL AUTO_INCREMENT,
  `code`                varchar(20)   NOT NULL,
  `discount_percentage` decimal(5,2)  NOT NULL,
  `expiry_date`         date          NOT NULL,
  PRIMARY KEY (`promo_id`),
  UNIQUE KEY `code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Sample promo codes
INSERT INTO `promo_codes` (`code`, `discount_percentage`, `expiry_date`) VALUES
('APEX10',   10.00, '2026-12-31'),
('WELCOME20',20.00, '2026-12-31'),
('FYP2026',  15.00, '2026-12-31');

-- ────────────────────────────────────────────
-- 7. ORDERS
-- ────────────────────────────────────────────
CREATE TABLE `orders` (
  `order_id`       int(11)       NOT NULL AUTO_INCREMENT,
  `user_id`        int(11)       DEFAULT NULL,
  `total_amount`   decimal(10,2) NOT NULL,
  `promo_id`       int(11)       DEFAULT NULL,
  `status`         varchar(30)   DEFAULT 'Processing',
  `shipping_address` text        DEFAULT NULL,
  `order_date`     timestamp     NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`order_id`),
  KEY `user_id`  (`user_id`),
  KEY `promo_id` (`promo_id`),
  CONSTRAINT `orders_ibfk_1` FOREIGN KEY (`user_id`)  REFERENCES `users` (`user_id`),
  CONSTRAINT `orders_ibfk_2` FOREIGN KEY (`promo_id`) REFERENCES `promo_codes` (`promo_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ────────────────────────────────────────────
-- 8. ORDER ITEMS
-- ────────────────────────────────────────────
CREATE TABLE `order_items` (
  `order_item_id` int(11)       NOT NULL AUTO_INCREMENT,
  `order_id`      int(11)       DEFAULT NULL,
  `product_id`    int(11)       DEFAULT NULL,
  `size`          varchar(10)   DEFAULT NULL,
  `quantity`      int(11)       NOT NULL,
  `price`         decimal(10,2) NOT NULL,
  PRIMARY KEY (`order_item_id`),
  KEY `order_id`   (`order_id`),
  KEY `product_id` (`product_id`),
  CONSTRAINT `order_items_ibfk_1` FOREIGN KEY (`order_id`)   REFERENCES `orders`   (`order_id`) ON DELETE CASCADE,
  CONSTRAINT `order_items_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`product_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ────────────────────────────────────────────
-- 9. ORDER STATUS HISTORY
-- ────────────────────────────────────────────
CREATE TABLE `order_status_history` (
  `history_id` int(11)     NOT NULL AUTO_INCREMENT,
  `order_id`   int(11)     DEFAULT NULL,
  `status`     varchar(30) NOT NULL,
  `changed_at` timestamp   NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`history_id`),
  KEY `order_id` (`order_id`),
  CONSTRAINT `order_status_history_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`order_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ────────────────────────────────────────────
-- 10. CART ITEMS
-- ────────────────────────────────────────────
CREATE TABLE `cart_items` (
  `cart_id`    int(11)     NOT NULL AUTO_INCREMENT,
  `user_id`    int(11)     DEFAULT NULL,
  `product_id` int(11)     DEFAULT NULL,
  `size`       varchar(10) DEFAULT NULL,
  `quantity`   int(11)     NOT NULL DEFAULT 1,
  PRIMARY KEY (`cart_id`),
  KEY `user_id`    (`user_id`),
  KEY `product_id` (`product_id`),
  CONSTRAINT `cart_items_ibfk_1` FOREIGN KEY (`user_id`)    REFERENCES `users`    (`user_id`) ON DELETE CASCADE,
  CONSTRAINT `cart_items_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`product_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ────────────────────────────────────────────
-- 11. WISHLIST
-- ────────────────────────────────────────────
CREATE TABLE `wishlist` (
  `wishlist_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id`     int(11) DEFAULT NULL,
  `product_id`  int(11) DEFAULT NULL,
  PRIMARY KEY (`wishlist_id`),
  KEY `user_id`    (`user_id`),
  KEY `product_id` (`product_id`),
  CONSTRAINT `wishlist_ibfk_1` FOREIGN KEY (`user_id`)    REFERENCES `users`    (`user_id`) ON DELETE CASCADE,
  CONSTRAINT `wishlist_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`product_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ────────────────────────────────────────────
-- 12. REVIEWS
-- ────────────────────────────────────────────
CREATE TABLE `reviews` (
  `review_id`  int(11)   NOT NULL AUTO_INCREMENT,
  `product_id` int(11)   DEFAULT NULL,
  `user_id`    int(11)   DEFAULT NULL,
  `rating`     int(11)   DEFAULT NULL CHECK (`rating` >= 1 AND `rating` <= 5),
  `comment`    text      DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`review_id`),
  KEY `product_id` (`product_id`),
  KEY `user_id`    (`user_id`),
  CONSTRAINT `reviews_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`product_id`) ON DELETE CASCADE,
  CONSTRAINT `reviews_ibfk_2` FOREIGN KEY (`user_id`)    REFERENCES `users`    (`user_id`)    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ────────────────────────────────────────────
-- 13. DESIGN REQUESTS ← NEW FEATURE
-- ────────────────────────────────────────────
CREATE TABLE `design_requests` (
  `request_id`     int(11)      NOT NULL AUTO_INCREMENT,
  `user_id`        int(11)      NOT NULL,
  `shoe_name`      varchar(150) NOT NULL,
  `category`       varchar(50)  NOT NULL,
  `color_pref`     varchar(150) NOT NULL,
  `description`    text         NOT NULL,
  `specifications` text         DEFAULT NULL,
  `ref_image`      varchar(300) DEFAULT NULL,
  `status`         enum('Pending','In Review','Approved','Rejected') NOT NULL DEFAULT 'Pending',
  `admin_note`     text         DEFAULT NULL,
  `created_at`     datetime     NOT NULL DEFAULT current_timestamp(),
  `updated_at`     datetime     NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`request_id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `design_requests_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

COMMIT;

-- ================================================================
--  QUICK REFERENCE — Table Summary
-- ================================================================
-- admins            → admin login (admin_id, username, password)
-- users             → customers (user_id, name, email, phone, address)
-- categories        → shoe categories (category_id, category_name)
-- products          → shoes (product_id, name, price, stock, image_url)
-- product_size      → per-size stock (size_id, product_id, size, stock_for_size)
-- promo_codes       → discount codes (promo_id, code, discount_percentage, expiry_date)
-- orders            → customer orders (order_id, user_id, total_amount, status, order_date)
-- order_items       → items in each order (order_item_id, order_id, product_id, size, quantity, price)
-- order_status_history → order audit trail (history_id, order_id, status, changed_at)
-- cart_items        → shopping cart (cart_id, user_id, product_id, size, quantity)
-- wishlist          → saved items (wishlist_id, user_id, product_id)
-- reviews           → product reviews (review_id, product_id, user_id, rating, comment)
-- design_requests   → custom shoe ideas (request_id, user_id, shoe_name, category, status) ← NEW
-- ================================================================

-- ────────────────────────────────────────────
-- 14. PRODUCT IMAGES (multi-image slider)
-- Run this if you already imported apex_store_final.sql
-- ────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `product_images` (
  `image_id`   int(11)      NOT NULL AUTO_INCREMENT,
  `product_id` int(11)      NOT NULL,
  `image_url`  varchar(300) NOT NULL,
  `color_name` varchar(80)  DEFAULT NULL,
  `sort_order` int(11)      NOT NULL DEFAULT 0,
  PRIMARY KEY (`image_id`),
  KEY `product_id` (`product_id`),
  CONSTRAINT `product_images_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`product_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
