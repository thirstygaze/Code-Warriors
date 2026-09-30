-- SmartBite migration: adds pickup codes, product images, and new menu items
-- to an EXISTING database that already has real data in it.
-- Safe to run once. Do NOT run this on a fresh database - just import
-- database.sql instead in that case.

USE smartbite;

-- 1. Add the pickup_code column (nullable first, since existing rows
--    don't have one yet).
ALTER TABLE orders ADD COLUMN pickup_code VARCHAR(8) NULL AFTER order_number;

-- 2. Backfill a code for every existing order from its order_number,
--    so old orders get a usable code too. This uses the last 6 characters
--    of the order number, which are already random hex - good enough for
--    existing/historical orders. New orders going forward get a proper
--    randomly generated code from generate_pickup_code().
UPDATE orders
SET pickup_code = UPPER(RIGHT(order_number, 6))
WHERE pickup_code IS NULL;

-- 3. Now that every row has a value, enforce NOT NULL + UNIQUE.
--    If this fails with a duplicate-key error, two orders collided -
--    that's extremely unlikely but if it happens, message me and I'll
--    give you a per-row fix.
ALTER TABLE orders MODIFY COLUMN pickup_code VARCHAR(8) NOT NULL;
ALTER TABLE orders ADD UNIQUE KEY uq_orders_pickup_code (pickup_code);

-- 4. Add the new Desserts category (safe to run even if it already exists).
INSERT IGNORE INTO categories (name, description)
VALUES ('Desserts', 'Sweet treats to finish your meal');

-- 5. Add image_url to your original 6 items (only fills it in if empty,
--    so it won't overwrite an image you've already set yourself).
UPDATE menu_items SET image_url = 'assets/img/items/chicken-rice.svg'  WHERE name = 'Chicken Rice Meal' AND (image_url IS NULL OR image_url = '');
UPDATE menu_items SET image_url = 'assets/img/items/burger.svg'        WHERE name = 'Burger Meal'        AND (image_url IS NULL OR image_url = '');
UPDATE menu_items SET image_url = 'assets/img/items/fries.svg'         WHERE name = 'French Fries'       AND (image_url IS NULL OR image_url = '');
UPDATE menu_items SET image_url = 'assets/img/items/banana-bread.svg' WHERE name = 'Banana Bread'        AND (image_url IS NULL OR image_url = '');
UPDATE menu_items SET image_url = 'assets/img/items/iced-tea.svg'      WHERE name = 'Iced Tea'            AND (image_url IS NULL OR image_url = '');
UPDATE menu_items SET image_url = 'assets/img/items/bottled-water.svg' WHERE name = 'Bottled Water'       AND (image_url IS NULL OR image_url = '');

-- 6. Add the 12 new menu items (only if a same-named item doesn't already
--    exist, so running this twice won't create duplicates).
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

-- 7. Keep the inventory table in sync for any items that don't have a row yet.
INSERT INTO inventory (menu_item_id, stock_qty, low_stock_threshold)
SELECT m.id, m.stock_qty, 10 FROM menu_items m
WHERE NOT EXISTS (SELECT 1 FROM inventory i WHERE i.menu_item_id = m.id);

SELECT 'Migration complete.' AS status;
