-- Migration: adds the pickup_code column and the new menu items/category
-- to an EXISTING smartbite database, without touching current users,
-- orders, or payments.
--
-- Run this instead of re-importing database.sql if you already have real
-- data you want to keep:
--   mysql -u root -p smartbite < migrations/2026_09_24_pickup_code_and_menu.sql

USE smartbite;

-- 1. Add the pickup_code column (nullable at first, so existing rows don't fail).
ALTER TABLE orders ADD COLUMN pickup_code VARCHAR(8) NULL AFTER order_number;

-- 2. Backfill a code for every existing order that doesn't have one yet.
--    (Uses MD5(RAND()) — fine at school-canteen scale; a collision is
--    astronomically unlikely, but the UNIQUE constraint below would catch one.)
UPDATE orders
SET pickup_code = UPPER(SUBSTRING(MD5(RAND()), 1, 6))
WHERE pickup_code IS NULL;

-- 3. Now that every row has a value, make it required and unique.
ALTER TABLE orders MODIFY COLUMN pickup_code VARCHAR(8) NOT NULL;
ALTER TABLE orders ADD UNIQUE KEY uq_orders_pickup_code (pickup_code);

-- 4. Add the Desserts category if it isn't already there.
INSERT INTO categories (name, description)
SELECT 'Desserts', 'Sweet treats to finish your meal'
WHERE NOT EXISTS (SELECT 1 FROM categories WHERE name = 'Desserts');

-- 5. Add the new menu items (skips any name that already exists, so this
--    is safe to run more than once).
INSERT INTO menu_items (category_id, name, description, price, stock_qty, image_url, is_available)
SELECT c.id, x.name, x.description, x.price, x.stock_qty, x.image_url, 1
FROM (
    SELECT 'Meals' cat, 'Pork Adobo Rice Meal' name, 'Braised pork adobo with garlic rice' description, 80.00 price, 35 stock_qty, 'assets/img/items/pork-adobo.svg' image_url
    UNION ALL SELECT 'Meals','Beef Tapa Rice Meal','Cured beef tapa, egg, and garlic rice',85.00,30,'assets/img/items/beef-tapa.svg'
    UNION ALL SELECT 'Meals','Garden Veggie Rice Meal','Stir-fried vegetables with rice',60.00,25,'assets/img/items/veggie-rice.svg'
    UNION ALL SELECT 'Snacks','Siomai (4 pcs)','Steamed pork siomai with chili garlic oil',40.00,45,'assets/img/items/siomai.svg'
    UNION ALL SELECT 'Snacks','Cheese Sticks (5 pcs)','Crispy fried cheese sticks',35.00,40,'assets/img/items/cheese-sticks.svg'
    UNION ALL SELECT 'Snacks','Loaded Nachos','Corn chips with cheese and salsa',45.00,30,'assets/img/items/nachos.svg'
    UNION ALL SELECT 'Drinks','Buko Juice','Fresh coconut juice with strips',30.00,40,'assets/img/items/buko-juice.svg'
    UNION ALL SELECT 'Drinks','Hot Brewed Coffee','Freshly brewed hot coffee',28.00,50,'assets/img/items/hot-coffee.svg'
    UNION ALL SELECT 'Drinks','Milk Tea','Classic milk tea with pearls',45.00,40,'assets/img/items/milk-tea.svg'
    UNION ALL SELECT 'Desserts','Leche Flan','Creamy caramel custard',35.00,25,'assets/img/items/leche-flan.svg'
    UNION ALL SELECT 'Desserts','Buko Pandan','Coconut and pandan gelatin dessert',35.00,25,'assets/img/items/buko-pandan.svg'
    UNION ALL SELECT 'Desserts','Chocolate Muffin','Moist double chocolate muffin',32.00,30,'assets/img/items/choco-muffin.svg'
) x
JOIN categories c ON c.name = x.cat
WHERE NOT EXISTS (SELECT 1 FROM menu_items m WHERE m.name = x.name);

-- 6. Add the image_url for the 6 original items too, if they don't have one yet.
UPDATE menu_items SET image_url = 'assets/img/items/chicken-rice.svg'   WHERE name = 'Chicken Rice Meal' AND (image_url IS NULL OR image_url = '');
UPDATE menu_items SET image_url = 'assets/img/items/burger.svg'         WHERE name = 'Burger Meal'        AND (image_url IS NULL OR image_url = '');
UPDATE menu_items SET image_url = 'assets/img/items/fries.svg'          WHERE name = 'French Fries'        AND (image_url IS NULL OR image_url = '');
UPDATE menu_items SET image_url = 'assets/img/items/banana-bread.svg'   WHERE name = 'Banana Bread'        AND (image_url IS NULL OR image_url = '');
UPDATE menu_items SET image_url = 'assets/img/items/iced-tea.svg'       WHERE name = 'Iced Tea'            AND (image_url IS NULL OR image_url = '');
UPDATE menu_items SET image_url = 'assets/img/items/bottled-water.svg'  WHERE name = 'Bottled Water'       AND (image_url IS NULL OR image_url = '');

-- 7. Sync the inventory table for any items that don't have a row yet.
INSERT INTO inventory (menu_item_id, stock_qty, low_stock_threshold)
SELECT m.id, m.stock_qty, 10 FROM menu_items m
WHERE NOT EXISTS (SELECT 1 FROM inventory i WHERE i.menu_item_id = m.id);

SELECT 'Migration complete.' AS status;
