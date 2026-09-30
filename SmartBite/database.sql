CREATE DATABASE IF NOT EXISTS smartbite CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE smartbite;

-- EXACTLY 10 APPLICATION TABLES

CREATE TABLE users (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 name VARCHAR(120) NOT NULL,
 email VARCHAR(190) NOT NULL UNIQUE,
 student_id VARCHAR(50) UNIQUE,
 password_hash VARCHAR(255) NOT NULL,
 role ENUM('student','staff','admin') NOT NULL DEFAULT 'student',
 assigned_category_id INT UNSIGNED NULL,
 stall_name VARCHAR(50) NULL,
 status ENUM('active','inactive') NOT NULL DEFAULT 'active',
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE categories (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 name VARCHAR(80) NOT NULL UNIQUE,
 description VARCHAR(255)
) ENGINE=InnoDB;

ALTER TABLE users ADD CONSTRAINT fk_user_category
  FOREIGN KEY (assigned_category_id) REFERENCES categories(id)
  ON DELETE SET NULL ON UPDATE CASCADE;

CREATE TABLE menu_items (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 category_id INT UNSIGNED NOT NULL,
 name VARCHAR(120) NOT NULL,
 description TEXT,
 price DECIMAL(10,2) NOT NULL,
 stock_qty INT NOT NULL DEFAULT 0,
 image_url VARCHAR(500),
 is_available TINYINT(1) NOT NULL DEFAULT 1,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 CONSTRAINT fk_menu_category FOREIGN KEY(category_id) REFERENCES categories(id) ON UPDATE CASCADE
) ENGINE=InnoDB;

CREATE TABLE inventory (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 menu_item_id INT UNSIGNED NOT NULL UNIQUE,
 stock_qty INT NOT NULL DEFAULT 0,
 low_stock_threshold INT NOT NULL DEFAULT 10,
 updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 CONSTRAINT fk_inventory_item FOREIGN KEY(menu_item_id) REFERENCES menu_items(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE pickup_slots (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 pickup_date DATE NOT NULL,
 pickup_time TIME NOT NULL,
 label VARCHAR(80) NOT NULL,
 max_orders INT NOT NULL DEFAULT 30,
 is_active TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB;

CREATE TABLE orders (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 order_number VARCHAR(40) NOT NULL UNIQUE,
 pickup_code VARCHAR(8) NOT NULL UNIQUE,
 user_id INT UNSIGNED NOT NULL,
 pickup_slot_id INT UNSIGNED NOT NULL,
 status ENUM('pending','preparing','ready','completed','cancelled') NOT NULL DEFAULT 'pending',
 total_amount DECIMAL(10,2) NOT NULL,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 CONSTRAINT fk_order_user FOREIGN KEY(user_id) REFERENCES users(id),
 CONSTRAINT fk_order_slot FOREIGN KEY(pickup_slot_id) REFERENCES pickup_slots(id)
) ENGINE=InnoDB;

CREATE TABLE order_items (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 order_id INT UNSIGNED NOT NULL,
 menu_item_id INT UNSIGNED NOT NULL,
 quantity INT NOT NULL,
 unit_price DECIMAL(10,2) NOT NULL,
 subtotal DECIMAL(10,2) NOT NULL,
 CONSTRAINT fk_oi_order FOREIGN KEY(order_id) REFERENCES orders(id) ON DELETE CASCADE,
 CONSTRAINT fk_oi_item FOREIGN KEY(menu_item_id) REFERENCES menu_items(id)
) ENGINE=InnoDB;

CREATE TABLE payments (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 order_id INT UNSIGNED NOT NULL UNIQUE,
 provider VARCHAR(40) NOT NULL DEFAULT 'paymongo',
 provider_reference VARCHAR(120),
 method VARCHAR(40) NOT NULL DEFAULT 'paymongo',
 status ENUM('pending','paid','failed','refunded') NOT NULL DEFAULT 'pending',
 amount DECIMAL(10,2) NOT NULL,
 paid_at DATETIME NULL,
 raw_response LONGTEXT NULL,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 CONSTRAINT fk_payment_order FOREIGN KEY(order_id) REFERENCES orders(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE notifications (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 user_id INT UNSIGNED NOT NULL,
 title VARCHAR(150) NOT NULL,
 message TEXT NOT NULL,
 is_read TINYINT(1) NOT NULL DEFAULT 0,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 CONSTRAINT fk_notification_user FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE activity_logs (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 user_id INT UNSIGNED NULL,
 action VARCHAR(100) NOT NULL,
 details TEXT,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 CONSTRAINT fk_activity_user FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

INSERT INTO categories(name,description) VALUES
('Meals','Rice meals and main dishes'),
('Snacks','Quick snacks and merienda'),
('Drinks','Cold and hot drinks'),
('Desserts','Sweet treats to finish your meal');

INSERT INTO menu_items(category_id,name,description,price,stock_qty,image_url,is_available) VALUES
(1,'Chicken Rice Meal','Chicken with rice and sauce',75.00,50,'assets/img/items/chicken-rice.svg',1),
(1,'Burger Meal','Burger with fries',65.00,40,'assets/img/items/burger.svg',1),
(1,'Pork Adobo Rice Meal','Braised pork adobo with garlic rice',80.00,35,'assets/img/items/pork-adobo.svg',1),
(1,'Beef Tapa Rice Meal','Cured beef tapa, egg, and garlic rice',85.00,30,'assets/img/items/beef-tapa.svg',1),
(1,'Garden Veggie Rice Meal','Stir-fried vegetables with rice',60.00,25,'assets/img/items/veggie-rice.svg',1),
(2,'French Fries','Crispy fries',35.00,60,'assets/img/items/fries.svg',1),
(2,'Banana Bread','Freshly baked slice',30.00,30,'assets/img/items/banana-bread.svg',1),
(2,'Siomai (4 pcs)','Steamed pork siomai with chili garlic oil',40.00,45,'assets/img/items/siomai.svg',1),
(2,'Cheese Sticks (5 pcs)','Crispy fried cheese sticks',35.00,40,'assets/img/items/cheese-sticks.svg',1),
(2,'Loaded Nachos','Corn chips with cheese and salsa',45.00,30,'assets/img/items/nachos.svg',1),
(3,'Iced Tea','16 oz iced tea',25.00,80,'assets/img/items/iced-tea.svg',1),
(3,'Bottled Water','500ml bottled water',20.00,100,'assets/img/items/bottled-water.svg',1),
(3,'Buko Juice','Fresh coconut juice with strips',30.00,40,'assets/img/items/buko-juice.svg',1),
(3,'Hot Brewed Coffee','Freshly brewed hot coffee',28.00,50,'assets/img/items/hot-coffee.svg',1),
(3,'Milk Tea','Classic milk tea with pearls',45.00,40,'assets/img/items/milk-tea.svg',1),
(4,'Leche Flan','Creamy caramel custard',35.00,25,'assets/img/items/leche-flan.svg',1),
(4,'Buko Pandan','Coconut and pandan gelatin dessert',35.00,25,'assets/img/items/buko-pandan.svg',1),
(4,'Chocolate Muffin','Moist double chocolate muffin',32.00,30,'assets/img/items/choco-muffin.svg',1);

INSERT INTO inventory(menu_item_id,stock_qty,low_stock_threshold)
SELECT id,stock_qty,10 FROM menu_items;

INSERT INTO pickup_slots(pickup_date,pickup_time,label,max_orders) VALUES
(CURDATE(),'09:30:00','Recess',30),
(CURDATE(),'12:00:00','Lunch',50),
(DATE_ADD(CURDATE(),INTERVAL 1 DAY),'09:30:00','Recess',30),
(DATE_ADD(CURDATE(),INTERVAL 1 DAY),'12:00:00','Lunch',50);

-- Demo password for the student and admin accounts: password
INSERT INTO users(name,email,student_id,password_hash,role) VALUES
('Demo Student','student@smartbite.local','STU-001',
'$2y$10$W104BMxPuq/EDsG5PyzyyukRc.8VtjcuT70KZkn8wLS1La57wHlK.','student'),
('Demo Admin','admin@smartbite.local','ADMIN-001',
'$2y$10$W104BMxPuq/EDsG5PyzyyukRc.8VtjcuT70KZkn8wLS1La57wHlK.','admin');

-- 5 staff accounts, each with a distinct email/password, each responsible
-- for one product category (assigned_category_id) and running one stall
-- (stall_name). "General Staff" has no assigned category (NULL), so they
-- can manage every category and are the one who mans the pickup counter
-- and completes any order. Every staff account can see Sales and run the
-- Orders Board regardless of category - only *product management*
-- (add/edit/delete menu items) is category-scoped.
INSERT INTO users(name,email,student_id,password_hash,role,assigned_category_id,stall_name) VALUES
('Anthony','staff.meals@smartbite.local','STAFF-101',
'$2y$10$mCXKuUW9vAWVD40t0tbwquwa4ld.PkPZ8l7C0z1UIZPHOSGAb8f7u','staff',
(SELECT id FROM categories WHERE name='Meals'),'1st Stall'),
('Jim','staff.snacks@smartbite.local','STAFF-102',
'$2y$10$q6RGAJXGq25FUXwaGVWy9en/C2fYDn3A2iUrj2lwisIw187rvqQ.a','staff',
(SELECT id FROM categories WHERE name='Snacks'),'2nd Stall'),
('Maria','staff.drinks@smartbite.local','STAFF-103',
'$2y$10$H1pT5Ev9XmPl/Xp3GYwV5uwvia7l.mCH.ryWrf49pgu.FYtzSjU1q','staff',
(SELECT id FROM categories WHERE name='Drinks'),'3rd Stall'),
('Carlo','staff.desserts@smartbite.local','STAFF-104',
'$2y$10$i.WEe5/E3VJX5LopEo4XgePIX0DiDe9o0igiwNWFI.1QWtQ6VSH9G','staff',
(SELECT id FROM categories WHERE name='Desserts'),'4th Stall'),
('Ella','staff.general@smartbite.local','STAFF-105',
'$2y$10$UygnO12eOOvilK2qp03Dge3VZ0u8H49dp9kHs61hRH/qZWtFjKXre','staff',
NULL,'5th Stall');

-- Demo passwords, in case you'd rather remember one pattern than five:
--   staff.meals@smartbite.local    -> MealsStaff123!
--   staff.snacks@smartbite.local   -> SnackStaff123!
--   staff.drinks@smartbite.local   -> DrinksStaff123!
--   staff.desserts@smartbite.local -> DessertStaff123!
--   staff.general@smartbite.local  -> GeneralStaff123!
--   student@smartbite.local / admin@smartbite.local -> password
