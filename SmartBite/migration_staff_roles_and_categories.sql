-- SmartBite migration: adds staff category assignment, 5 category-scoped
-- staff accounts, and the admin user-management feature to an EXISTING
-- database. Safe to run once. Do NOT run this on a fresh database - just
-- import database.sql instead in that case.

USE smartbite;

-- 1. Add the assigned_category_id column, if it isn't already there.
--    (If you get "Duplicate column name", it's already been added - skip
--    straight to step 2.)
ALTER TABLE users ADD COLUMN assigned_category_id INT UNSIGNED NULL AFTER role;
ALTER TABLE users ADD CONSTRAINT fk_user_category
  FOREIGN KEY (assigned_category_id) REFERENCES categories(id)
  ON DELETE SET NULL ON UPDATE CASCADE;

-- 2. Add the 5 category-scoped staff accounts (only if an account with that
--    email doesn't already exist, so this is safe to re-run).
--    Demo passwords:
--      staff.meals@smartbite.local    -> MealsStaff123!
--      staff.snacks@smartbite.local   -> SnackStaff123!
--      staff.drinks@smartbite.local   -> DrinksStaff123!
--      staff.desserts@smartbite.local -> DessertStaff123!
--      staff.general@smartbite.local  -> GeneralStaff123!
INSERT INTO users (name, email, student_id, password_hash, role, assigned_category_id, status)
SELECT x.name, x.email, x.student_id, x.hash, 'staff', c.id, 'active'
FROM (
  SELECT 'Meals Staff' name,'staff.meals@smartbite.local' email,'STAFF-101' student_id,'$2y$10$mCXKuUW9vAWVD40t0tbwquwa4ld.PkPZ8l7C0z1UIZPHOSGAb8f7u' hash,'Meals' cat
  UNION ALL SELECT 'Snacks Staff','staff.snacks@smartbite.local','STAFF-102','$2y$10$q6RGAJXGq25FUXwaGVWy9en/C2fYDn3A2iUrj2lwisIw187rvqQ.a','Snacks'
  UNION ALL SELECT 'Drinks Staff','staff.drinks@smartbite.local','STAFF-103','$2y$10$H1pT5Ev9XmPl/Xp3GYwV5uwvia7l.mCH.ryWrf49pgu.FYtzSjU1q','Drinks'
  UNION ALL SELECT 'Desserts Staff','staff.desserts@smartbite.local','STAFF-104','$2y$10$i.WEe5/E3VJX5LopEo4XgePIX0DiDe9o0igiwNWFI.1QWtQ6VSH9G','Desserts'
) x
JOIN categories c ON c.name = x.cat
WHERE NOT EXISTS (SELECT 1 FROM users u WHERE u.email = x.email);

-- The "general" staff has no assigned category (NULL), so it's inserted
-- separately rather than joined to a category row.
INSERT INTO users (name, email, student_id, password_hash, role, assigned_category_id, status)
SELECT 'General Staff','staff.general@smartbite.local','STAFF-105',
       '$2y$10$UygnO12eOOvilK2qp03Dge3VZ0u8H49dp9kHs61hRH/qZWtFjKXre','staff',NULL,'active'
WHERE NOT EXISTS (SELECT 1 FROM users WHERE email = 'staff.general@smartbite.local');

SELECT 'Migration complete.' AS status;
