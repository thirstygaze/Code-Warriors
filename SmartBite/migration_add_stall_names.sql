-- SmartBite migration: adds a stall_name field for staff, and (if the 5
-- staff accounts from the earlier migration exist) gives them real names
-- and stall labels. Safe to run once. Do NOT run this on a fresh database
-- - just import database.sql instead in that case.

USE smartbite;

-- 1. Add the column, if it isn't already there.
--    (If you get "Duplicate column name", it's already been added.)
ALTER TABLE users ADD COLUMN stall_name VARCHAR(50) NULL AFTER assigned_category_id;

-- 2. Give the 5 seeded staff accounts a real name and stall label
--    (only touches rows that still have the old generic names, so this
--    won't stomp on names you've already customized).
UPDATE users SET name='Anthony', stall_name='1st Stall' WHERE email='staff.meals@smartbite.local'    AND name='Meals Staff';
UPDATE users SET name='Jim',     stall_name='2nd Stall' WHERE email='staff.snacks@smartbite.local'   AND name='Snacks Staff';
UPDATE users SET name='Maria',   stall_name='3rd Stall' WHERE email='staff.drinks@smartbite.local'   AND name='Drinks Staff';
UPDATE users SET name='Carlo',   stall_name='4th Stall' WHERE email='staff.desserts@smartbite.local' AND name='Desserts Staff';
UPDATE users SET name='Ella',    stall_name='5th Stall' WHERE email='staff.general@smartbite.local'  AND name='General Staff';

SELECT 'Migration complete.' AS status;
