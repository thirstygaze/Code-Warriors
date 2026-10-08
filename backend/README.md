# Smartbite API

Laravel backend for the Holy Name University school canteen ordering and management system.

## Local setup: MySQL and PayMongo

These steps assume Windows with XAMPP. Keep the backend terminal and the webhook tunnel terminal running while testing payments.

### 1. Start MySQL and create the database

Open the XAMPP Control Panel and start **MySQL**. In phpMyAdmin's SQL tab (usually `http://localhost/phpmyadmin`), create the database:

```sql
CREATE DATABASE smartbite CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

For a local-only XAMPP install, the default MySQL account is often `root` with no password. For better isolation, create a dedicated account and grant it access to this database:

```sql
CREATE USER 'smartbite'@'localhost' IDENTIFIED BY 'replace-with-a-strong-password';
GRANT ALL PRIVILEGES ON smartbite.* TO 'smartbite'@'localhost';
```

### 2. Configure and initialize the Laravel backend

From the `backend` folder, install PHP dependencies and create your local environment file:

```powershell
composer install
Copy-Item .env.example .env
php artisan key:generate
```

Edit `backend/.env` and set the database values to match the account you chose:

```dotenv
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=smartbite
DB_USERNAME=smartbite
DB_PASSWORD=replace-with-a-strong-password
```

If you use the default XAMPP `root` account instead, set `DB_USERNAME=root` and leave `DB_PASSWORD` empty. Also set `SMARTBITE_ADMIN_NAME`, `SMARTBITE_ADMIN_EMAIL`, and a unique `SMARTBITE_ADMIN_PASSWORD` of at least 12 characters before seeding.

Run the migrations and seed the initial menu and administrator:

```powershell
php artisan config:clear
php artisan migrate --seed
```

### 3. Get PayMongo test credentials

1. Sign in to your PayMongo account and switch to **Test Mode**.
2. From the PayMongo dashboard's API keys page, copy the **test secret key**. Do not use a publishable key here.
3. In `backend/.env`, set `PAYMONGO_SECRET_KEY` to that test secret key. Leave `PAYMONGO_CURRENCY=PHP`. The default `PAYMONGO_PAYMENT_METHODS=card,paymaya,gcash,grab_pay,qrph` enables cards, Maya, GCash, GrabPay, and QR Ph in hosted checkout. Only keep methods enabled for your PayMongo account; enable them in the PayMongo dashboard first. To offer a different set, edit this comma-separated backend setting.
4. Keep all PayMongo secret keys and webhook secrets in the backend `.env`; never add them to the frontend environment file or commit them.

### 4. Start the API and expose its webhook endpoint

In the backend folder, run:

```powershell
php artisan serve --host=0.0.0.0 --port=8000
```

PayMongo cannot send webhooks to `localhost`. In another terminal, start an HTTPS tunnel to port 8000 using a tunnel provider such as ngrok:

```powershell
ngrok http 8000
```

Copy the HTTPS forwarding URL shown by the tunnel (for example, `https://example.ngrok-free.app`). In the PayMongo dashboard, add a webhook whose URL is that forwarding URL plus `/api/webhooks/paymongo`, and subscribe to the `checkout_session.payment.paid` event. Copy the signing secret shown for that webhook into `PAYMONGO_WEBHOOK_SECRET` in `backend/.env`, then reload Laravel's configuration:

```powershell
php artisan config:clear
```

Keep the tunnel running; its URL may change when restarted. If it changes, update the webhook URL in PayMongo. The endpoint verifies PayMongo's signature, so a missing or incorrect signing secret prevents payments from being marked as paid. Only the verified webhook—not the browser's return redirect—confirms an order payment.

### 5. Run the frontend and test checkout

In a separate terminal, follow the frontend setup in [`../frontend/README.md`](../frontend/README.md). Its default API URL is `http://localhost:8000/api`; ensure `FRONTEND_URL=http://localhost:3000` in the backend `.env`.

Open `http://localhost:3000`, create an order, and complete checkout using PayMongo's test-mode payment flow. Confirm that the webhook is received and the order changes to paid in the app. Test payment options depend on your PayMongo account and current PayMongo test-mode guidance.

Before accepting real payments, deploy the API at a stable HTTPS URL, configure a production webhook for that URL, and replace the test secret key and webhook signing secret with the production credentials. Never use test credentials in production or production credentials for local testing.

The API uses Laravel Sanctum bearer tokens. Students can create an account at `POST /api/auth/register` with their name, email, student number, and a password of at least 12 characters; successful registration signs them in. Staff and administrator accounts are created by an administrator. Sign in at `POST /api/auth/login` with `email`, `password`, and the account's actual `role`; tokens expire after 12 hours. The submitted role is checked against the stored account role. Configure `FRONTEND_URL` to the frontend origin for CORS, PayMongo return redirects, and password reset links. Password reset emails use the `log` mailer by default in local development; configure SMTP or another mail transport in `.env` to deliver them.

Student checkout reserves inventory and supports either PayMongo online payment or cash at pickup. Online orders are released to canteen staff after the signed PayMongo paid webhook is verified. Cash orders appear for staff cash confirmation and cannot be prepared until staff marks the cash payment as received. Configure webhook secrets only on the backend; never put PayMongo secret keys in frontend environment variables. Refund paid online orders in the PayMongo dashboard before staff cancel them.

Each staff account is assigned one stall by an administrator. Staff menu and inventory endpoints are scoped to the signed-in staff member's items; administrators can manage all menu items. Students see available items from all stalls, with each item's stall name included in the public menu response. Staff can add as many items as needed to their assigned stall.

## Role permissions

| Role | API permissions |
| --- | --- |
| Student | Browse the public menu, create orders, view only their own orders and notifications |
| Staff | View and process incoming orders, manage only their stall's menu items and inventory |
| Administrator | Staff permissions plus all-stall menu access, sales reports, user/staff management, and category management |

All protected routes use server-side authentication and role middleware; hiding dashboard navigation in the frontend is not treated as authorization.

## Main API routes

- `GET /api/menu`
- `POST /api/auth/register`, `POST /api/auth/login`, `GET /api/auth/me`, `POST /api/auth/logout`
- `POST /api/auth/forgot-password`, `POST /api/auth/reset-password`
- `POST /api/webhooks/paymongo` (signed PayMongo webhook; no bearer token)
- `GET|POST /api/student/orders` (orders accept `payment_method: paymongo|cash`), `GET /api/student/orders/{order}/payment`, `GET /api/student/notifications`
- `GET /api/staff/orders`, `PATCH /api/staff/orders/{order}/status`, `POST /api/staff/orders/{order}/cash-payment`
- `GET|POST /api/staff/menu-items`, `PATCH /api/staff/menu-items/{menuItem}`
- `GET /api/staff/inventory`, `PUT /api/staff/inventory/{menuItem}`
- `GET /api/admin/reports`, `GET|POST|PATCH /api/admin/users`
- `GET|POST|PATCH|DELETE /api/admin/categories`

Run the backend suite with `php artisan test`.
