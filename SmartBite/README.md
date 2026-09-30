# SmartBite — School Canteen Ordering & Management System

PHP + MySQL + PayMongo Checkout.

## What changed in this pass

The uploaded project was missing `config/`, `includes/`, `assets/`, `staff/`,
and `student/` (they came through as empty folders), and had a few real bugs.
This pass rebuilt the missing pieces, added a logo, product images, more
menu items, a pickup verification code, and fixed the following:

1. **No PayMongo webhook existed.** `payment_success.php` only ever read
   `payments.status` from the database, but nothing ever set that column to
   `paid` — so every order stayed stuck on "payment is being verified"
   forever. Added `webhook.php`, which verifies the `Paymongo-Signature`
   header (HMAC-SHA256, timing-safe compare) and is now the only thing
   allowed to mark a payment `paid` or `failed`.
2. **Stock was never decremented.** `menu_items.stock_qty` was checked when
   adding to cart but never reduced when an order was placed, so the same
   stock could be sold indefinitely. `checkout.php` now locks each item's row
   (`SELECT ... FOR UPDATE`) inside the order transaction, re-validates
   stock, and deducts it — so two students checking out at the same moment
   can't oversell the last item.
3. **No pickup-slot capacity enforcement.** `checkout.php` now counts
   non-cancelled orders against `pickup_slots.max_orders` and rejects
   checkout once a slot is full.
4. **Demo password hash was wrong.** The seeded bcrypt hash in
   `database.sql` did not actually match `password`, so none of the demo
   accounts could log in. Replaced with a verified hash.
5. Built the missing `config/`, `includes/` (db, auth, helpers, header,
   footer), `assets/css/style.css`, `register.php`, `student/orders.php`,
   `student/order.php` (order detail + status tracker), `staff/index.php`
   (orders board), `staff/menu.php` (menu & stock management), and
   `staff/sales.php` (admin sales report) — all referenced by links in the
   original files but not included in the upload.
6. Redesigned the UI (see below).

Everything above was tested end-to-end against a local MySQL + PHP server:
register → login → browse → cart → checkout → stock deduction → staff
board (advance/cancel with stock restore) → admin sales → webhook
(valid and invalid signatures) → CSRF rejection → role gating → logout.

## New in this pass: logo, product images, more items, pickup codes

- **Logo**: a custom SVG bento-box mark (`assets/img/logo.svg`), used in the
  nav bar and as the favicon. No external image dependency.
- **Product images**: every menu item now has a small flat-style SVG icon
  under `assets/img/items/`, shown on the menu grid, in the cart, and in
  staff's menu manager. These are original vector illustrations (not stock
  photos), so there's nothing to license or attribute. Staff can also set a
  custom image path when adding a new item.
- **More products**: the menu grew from 6 items to 18, across a new
  `Desserts` category plus more Meals, Snacks, and Drinks (see
  `database.sql`).
- **Pickup verification code**: every order now gets a random 6-character
  code (`orders.pickup_code`) shown to the student right after checkout, on
  `student/orders.php`, on `student/order.php`, and on the PayMongo
  success page once payment clears. Staff can only move an order from
  **Ready** to **Completed** on the orders board by typing in the matching
  code — this stops an order being marked picked-up by mistake (or by
  someone other than the student). Tested: wrong code is rejected and the
  order stays "Ready"; correct code completes it.

## Design

A school-canteen "menu board" look: a serif display face (Fraunces) for
headings, clean sans (Inter) for UI text, a chalkboard-green + mango-yellow
palette, ticket-stub menu cards with a dashed price line, a receipt-style
cart/checkout table, and a kanban board for staff order management (since
Pending → Preparing → Ready → Completed is a real sequence).

## 10 database tables

1. `users` — students, staff, admins
2. `categories` — meals/snacks/drinks
3. `menu_items` — digital canteen menu
4. `inventory` — stock and low-stock thresholds
5. `pickup_slots` — recess/lunch pickup schedules
6. `orders` — order header and status
7. `order_items` — items inside each order
8. `payments` — PayMongo/payment records
9. `notifications` — order/payment notifications
10. `activity_logs` — audit trail

## Requirements

- XAMPP/Laragon (Apache + PHP 8.1+ + MySQL 8+, PHP cURL enabled)
- A PayMongo test account and API keys

## Installation

1. Copy the `SmartBite` folder to `htdocs`.
2. Start Apache and MySQL.
3. Open phpMyAdmin and import `database.sql`.
4. Edit `config/config.php`:
   - database credentials
   - `APP_URL`
   - `PAYMONGO_SECRET_KEY`
   - `PAYMONGO_WEBHOOK_SECRET` (see below)
5. Open `http://localhost/SmartBite/`.
6. Log in with:
   - student@smartbite.local / password
   - admin@smartbite.local / password
   - 5 staff accounts, one per product category — see "The 5 staff
     accounts" table further down for each email/password.

## PayMongo

`paymongo_checkout.php` creates a PayMongo Checkout Session and redirects
the customer to the returned checkout URL. The secret key stays server-side.

**You must register a webhook** for payments to ever be marked as paid:

1. In the PayMongo Dashboard, go to Developers > Webhooks.
2. Add endpoint: `https://YOUR-DOMAIN/webhook.php`
3. Subscribe it to `payment.paid` and `payment.failed` (and
   `checkout_session.payment.paid` if offered).
4. Copy the webhook's signing secret into `PAYMONGO_WEBHOOK_SECRET` in
   `config/config.php`.

Note PayMongo webhooks need a publicly reachable HTTPS URL — for local
development, tunnel `localhost` with something like ngrok and register that
tunnel URL as the webhook endpoint instead.

`payment_success.php` (the browser redirect target) never marks anything
paid itself — it only reflects what the webhook already recorded, and
polls itself for ~60 seconds in case the webhook arrives a couple of
seconds after the redirect.

## Roles

- **student** — browse menu, cart, checkout, track orders, pay via
  PayMongo or cash on pickup. Can delete their own account from
  `student/account.php` (deactivated instead of hard-deleted if they have
  order history, so past orders stay consistent).
- **staff** — 5 seeded accounts, each responsible for one product
  category (see below). Every staff account, regardless of category, can
  access `staff/index.php` (orders board: advance status,
  cancel-with-stock-restore, complete-with-pickup-code) and
  `staff/sales.php` (revenue, order counts, top-selling items). Only
  `staff/menu.php` (add/edit/delete menu items) is category-scoped: a
  category-restricted staff member can only add, edit, or delete items in
  their own category — enforced server-side, not just hidden in the UI.
- **admin** — everything staff can do, plus `staff/users.php`: create new
  staff or student accounts, reassign which category a staff member
  manages, deactivate/reactivate any account, and delete accounts
  (falls back to deactivating if the account has order history, same as
  the student self-delete).

### The 5 staff accounts

| Name | Stall | Email | Password | Manages |
|---|---|---|---|---|
| Anthony | 1st Stall | staff.meals@smartbite.local | MealsStaff123! | Meals only |
| Jim | 2nd Stall | staff.snacks@smartbite.local | SnackStaff123! | Snacks only |
| Maria | 3rd Stall | staff.drinks@smartbite.local | DrinksStaff123! | Drinks only |
| Carlo | 4th Stall | staff.desserts@smartbite.local | DessertStaff123! | Desserts only |
| Ella | 5th Stall | staff.general@smartbite.local | GeneralStaff123! | All categories |

Each staff member's name and stall show up next to their login in the nav
bar, on the Orders Board, and on Manage Menu. An admin can rename anyone,
reassign their category, or relabel their stall from `staff/users.php` at
any time - "General Staff" has no assigned category, so `staff/menu.php`
shows them every item, same as admin.

## Product photos

Menu items currently ship with original flat-illustration SVG icons
(`assets/img/items/`) — not stock photography, so there's nothing to
license or attribute.

**To use real photos of your actual food**, staff (or admin) can upload
one directly from `staff/menu.php`:
- when adding a new item ("Photo" field), or
- next to any existing item in the table, then "Save all changes".

Uploads are validated (real image files only, JPG/PNG/WEBP, 3MB max),
saved with a random filename under `assets/img/items/uploads/`, and the
item's `image_url` is updated automatically.

I didn't bulk-fill the menu with photos pulled from the web: I couldn't
find a source of specific-dish food photography (adobo, siomai, buko
pandan, etc.) with clearly verified, redistributable licensing for every
item, and even "made for this" placeholder APIs (e.g. Foodish) explicitly
disclaim owning the rights to their own images. Real photos of your own
food, uploaded through the feature above, are the only genuinely correct
source anyway.

## Still worth adding before a real production deployment

- A scheduled job to auto-cancel (and restore stock for) PayMongo orders
  left unpaid for too long.
- Password reset and email verification.
- Rate limiting on login/register.
- Structured error logging instead of `error_log()`.
