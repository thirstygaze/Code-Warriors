# Smartbite frontend

Next.js client for the Holy Name University school canteen. Student ordering and staff/admin workspaces use the Laravel API; role selection in the sign-in form is checked against the authenticated account on the server.

## Run locally

1. Start the MySQL-backed API and configure PayMongo credentials/webhook by following [`../backend/README.md`](../backend/README.md).
2. Copy `.env.example` to `.env.local` if the API is not at `http://localhost:8000/api`, then set `NEXT_PUBLIC_SMARTBITE_API_URL`.
3. From this folder, run `npm install` and `npm run dev`.
4. Open `http://localhost:3000`.

Unauthenticated visitors see a welcome landing page and must sign in or register before accessing the canteen menu or any workspace. Students can create accounts from the sign-in screen using their student number, school email, and a password of at least 12 characters; staff and administrator accounts are provisioned by an administrator. Each staff account is assigned one stall, and staff can view and manage only their own stall's menu and inventory. Administrators can manage all stalls and create, deactivate, reactivate, or permanently delete student, staff, and administrator accounts from the Team workspace. Deactivation prevents sign-in without removing order history. Permanent deletion removes the account and profile while retaining order/payment history without the account identity. Checkout redirects students to PayMongo hosted checkout; orders move to the kitchen only after PayMongo's signed paid webhook is verified. Students can view their order/payment status and resume checkout for unpaid orders. Student order history/notifications and staff/admin workspaces require a valid API token. The first administrator is provisioned through the backend environment.

## Build

Run `npm run lint` and `npm run build` from this folder.
