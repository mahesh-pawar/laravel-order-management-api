# Order Management API

A small Order Management REST API built with **Laravel 11**, **PHP 8.2+** and **MySQL**.

- Token authentication with **Laravel Sanctum** and two roles: **admin** and **customer**
- Product management with soft deletes, search and pagination
- Order placement with **database transactions and row locking** (no overselling)
- **Queued** order-confirmation email with 3 retries
- **HMAC-SHA256 signed**, **idempotent** payment webhook
- Admin **top-5 products report** in a single SQL query
- **Scheduled command** that cancels unpaid orders after 30 minutes and restores stock
- Feature tests for every required scenario

- **Postman collection:** [postman/Order-Management-API.postman_collection.json](postman/Order-Management-API.postman_collection.json)
- **Design note:** [DESIGN_NOTE.md](DESIGN_NOTE.md) (design decisions, possible improvements and AI tool usage)

---

## Contents

1. [Prerequisites](#1-prerequisites)
2. [Setup guide](#2-setup-guide)
3. [Running the background processes](#3-running-the-background-processes)
4. [Running the tests](#4-running-the-tests)
5. [Testing with Postman](#5-testing-with-postman)
6. [API endpoints](#6-api-endpoints)
7. [Error format](#7-error-format)
8. [Payment webhook: how to sign a request](#8-payment-webhook-how-to-sign-a-request)
9. [Project structure](#9-project-structure)
10. [Troubleshooting](#10-troubleshooting)

---

## 1. Prerequisites

| Requirement | Version | Notes |
|---|---|---|
| **PHP** | **8.2 or newer** (8.2, 8.3, 8.4) | Extensions: `pdo_mysql`, `mbstring`, `openssl`, `fileinfo`, `curl` |
| **Composer** | 2.x | <https://getcomposer.org> |
| **MySQL** | **8.0+** (MariaDB 10.4+ also works) | The InnoDB engine is required for row locking |
| **Laravel** | 11.x | Installed by Composer, nothing to install separately |
| **Git** | any | To clone the repository |
| **Postman** | optional | To use the included collection |

Check your versions:

```bash
php -v
php -m        # must list pdo_mysql
composer -V
mysql --version
```

> **Windows (XAMPP) tip:** if `php -m` does not list `pdo_mysql`, open your `php.ini`
> (`php --ini` shows where it is), remove the `;` in front of `extension=pdo_mysql` and save.

---

## 2. Setup guide

### Step 1: Clone the repository

```bash
git clone https://github.com/mahesh-pawar/laravel-order-management-api.git
cd laravel-order-management-api
```

### Step 2: Install PHP dependencies

```bash
composer install
```

> `composer.json` pins dependency resolution to PHP 8.2 (`config.platform.php`), so the lock file
> installs on PHP 8.2, 8.3 and 8.4. It also allows the known security advisories of
> `laravel/framework` only, because Laravel 11 is past its security-support window and newer
> Composer versions would otherwise refuse to install it. All other packages are still checked.

### Step 3: Create the environment file and app key

```bash
cp .env.example .env
php artisan key:generate
```

On Windows Command Prompt use `copy .env.example .env`.

### Step 4: Create the databases

Two databases are used: one for the app, one for the automated tests (the tests wipe their
database on every run, so they must not share the app database).

```sql
CREATE DATABASE order_management      CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE DATABASE order_management_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

From the command line:

```bash
mysql -u root -p -e "CREATE DATABASE order_management CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci; CREATE DATABASE order_management_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
```

### Step 5: Configure `.env`

Open `.env` and set your MySQL credentials:

```dotenv
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=order_management
DB_USERNAME=root
DB_PASSWORD=your-password
```

Set the payment webhook secret (any long random string). You can generate one with:

```bash
php -r "echo bin2hex(random_bytes(32)), PHP_EOL;"
```

```dotenv
PAYMENT_WEBHOOK_SECRET=paste-the-generated-value-here
```

These are already set correctly in `.env.example` and need no change:

| Key | Value | Why |
|---|---|---|
| `QUEUE_CONNECTION` | `database` | Jobs are stored in the `jobs` table, so no Redis is needed |
| `MAIL_MAILER` | `log` | Emails are written to `storage/logs/laravel.log` instead of being sent |

### Step 6: Run migrations and seed demo data

```bash
php artisan migrate --seed
```

This creates all tables and the following demo data:

| Account | Email | Password | Role |
|---|---|---|---|
| Admin | `admin@example.com` | `password123` | admin |
| Customer | `test@example.com` | `password123` | customer |
| Customer | `customer2@example.com` | `password123` | customer |

It also creates 15 products (12 active, 3 inactive) and 6 paid orders, so the report endpoint
returns data straight away.

To reset everything later: `php artisan migrate:fresh --seed`

### Step 7: Start the API

```bash
php artisan serve
```

The API is now available at **<http://127.0.0.1:8000/api>**.

Quick check:

```bash
curl -X POST http://127.0.0.1:8000/api/login \
  -H "Accept: application/json" -H "Content-Type: application/json" \
  -d '{"email":"admin@example.com","password":"password123"}'
```

You should get a `200` response with a `token`.

---

## 3. Running the background processes

Two features run outside the HTTP request. Open a separate terminal for each.

### Queue worker (order confirmation emails)

```bash
php artisan queue:work
```

Every placed order dispatches `SendOrderConfirmationEmail`. The worker sends it, and the email
appears in `storage/logs/laravel.log`. The job retries up to **3 times** (10 s, then 30 s
backoff); after the last failure it is logged and stored in the `failed_jobs` table
(`php artisan queue:retry all` re-runs failed jobs).

### Scheduler (cancel unpaid orders)

```bash
php artisan schedule:work
```

Runs `orders:cancel-stale` every minute. It cancels **pending orders older than 30 minutes** and
puts their stock back.

- Run it once by hand: `php artisan orders:cancel-stale`
- Use a different age: `php artisan orders:cancel-stale --minutes=60`
- See the schedule: `php artisan schedule:list`
- In production, add one cron entry instead of `schedule:work`:
  `* * * * * cd /path/to/project && php artisan schedule:run >> /dev/null 2>&1`

---

## 4. Running the tests

```bash
php artisan test
```

Tests run against the separate **`order_management_test`** database (configured in
`phpunit.xml`); your app data is never touched. Each test starts with empty tables and its data
is rolled back afterwards.

| Test | Covers |
|---|---|
| `OrderPlacementTest` | Placing an order creates it, saves prices and **reduces stock**; ordering more than the stock fails with **422** and changes nothing |
| `OrderAuthorizationTest` | A customer gets **403** on another customer's order; the owner can view it |
| `PaymentWebhookTest` | A wrong signature is rejected with **401**; a valid webhook marks the order paid; the **same `transaction_id` twice** is processed only once |
| `CancelStaleOrdersTest` | Only pending orders older than 30 minutes are cancelled, and their stock is restored |

Expected output: `Tests: 8 passed`.

> The tests use the same `DB_HOST`, `DB_USERNAME` and `DB_PASSWORD` from your `.env`. Only the
> database name is overridden.

---

## 5. Testing with Postman

Import [**postman/Order-Management-API.postman_collection.json**](postman/Order-Management-API.postman_collection.json)
(Postman → **Import** → select the file). It contains a request for every endpoint below, grouped
into Auth, Products, Orders, Payment Webhook and Reports.

1. Make sure `php artisan serve` is running (`base_url` is `http://127.0.0.1:8000/api`).
2. Run **Auth → Login as Admin** and **Auth → Login as Customer**. The tokens are saved into
   collection variables automatically.
3. Run **Products → Create Product (Admin)**. It saves `product_id`.
4. Run **Orders → Place Order (Customer)**. It saves `order_id` and `order_total`.
5. For the webhook, open the collection's **Variables** tab and set `webhook_secret` to the
   `PAYMENT_WEBHOOK_SECRET` value from your `.env`. The webhook requests sign themselves with a
   pre-request script.

Requests that should fail (403, 422, 401) are included and named "(expect …)".

---

## 6. API endpoints

Base URL: `http://127.0.0.1:8000/api`

All of these are ready to run in the [Postman collection](postman/Order-Management-API.postman_collection.json)
(see [section 5](#5-testing-with-postman)).

Send `Accept: application/json` on every request. Protected endpoints need
`Authorization: Bearer <token>` (the token comes from register or login).

### Auth

| Method | Endpoint | Access | Body | Success |
|---|---|---|---|---|
| POST | `/register` | Public | `name`, `email`, `password`, `password_confirmation` | `201` user + token (always role `customer`) |
| POST | `/login` | Public | `email`, `password` | `200` user + token (`401` on wrong credentials) |
| POST | `/logout` | Logged in | none | `200` (revokes the current token) |
| GET | `/me` | Logged in | none | `200` current user |

### Products

| Method | Endpoint | Access | Body / Query | Success |
|---|---|---|---|---|
| GET | `/products` | Logged in | `?search=` (name or SKU), `?per_page=` (1–100, default 15), `?page=` | `200` paginated list. Admin sees all statuses; customer sees **active** only |
| POST | `/products` | Admin | `name`, `sku` (unique), `price`, `stock_quantity`, `status` (`active`/`inactive`, optional) | `201` product |
| PUT/PATCH | `/products/{id}` | Admin | any of the fields above | `200` product |
| DELETE | `/products/{id}` | Admin | none | `200` (soft delete) |

### Orders

| Method | Endpoint | Access | Body | Success |
|---|---|---|---|---|
| GET | `/orders` | Logged in | none | `200` paginated. Customer sees **own** orders; admin sees all |
| POST | `/orders` | Customer | `{"items": [{"product_id": 1, "quantity": 2}]}` | `201` order with items, status `pending` |
| GET | `/orders/{id}` | Owner or admin | none | `200` order (`403` for other customers) |

Example order body:

```json
{
  "items": [
    { "product_id": 1, "quantity": 2 },
    { "product_id": 4, "quantity": 1 }
  ]
}
```

Example response:

```json
{
  "data": {
    "id": 7,
    "user_id": 2,
    "status": "pending",
    "total_amount": "46.00",
    "items": [
      { "product_id": 1, "product_name": "Blue pen", "sku": "SKU-4821-KD", "quantity": 2, "unit_price": "10.50", "line_total": "21.00" },
      { "product_id": 4, "product_name": "Notebook a5", "sku": "SKU-1180-QX", "quantity": 1, "unit_price": "25.00", "line_total": "25.00" }
    ],
    "created_at": "2026-11-25T10:00:00+00:00"
  }
}
```

### Payment webhook

| Method | Endpoint | Access | Body | Success |
|---|---|---|---|---|
| POST | `/webhooks/payment` | `X-Signature` header (HMAC-SHA256) | `transaction_id`, `order_id`, `amount` | `200` `Payment processed.` or `Payment already processed.` |

- Wrong or missing signature → `401`
- Order not pending (already paid, cancelled or shipped) → `422`
- Amount not equal to the order total → `422`

### Reports

| Method | Endpoint | Access | Query | Success |
|---|---|---|---|---|
| GET | `/reports/top-products` | Admin | `from`, `to` (`Y-m-d`, required) | `200` top 5 products by quantity sold |

Only **paid** and **shipped** orders count as sold. Example:
`GET /reports/top-products?from=2026-11-01&to=2026-11-30`

```json
{
  "data": [
    { "product_id": 3, "name": "Notebook a5", "sku": "SKU-1180-QX", "total_quantity": 9, "total_revenue": "225.00" }
  ],
  "meta": { "from": "2026-11-01", "to": "2026-11-30" }
}
```

---

## 7. Error format

Every API error uses the same JSON shape:

```json
{
  "success": false,
  "message": "Validation failed.",
  "errors": {
    "items.0.quantity": ["Insufficient stock. Only 2 left."]
  }
}
```

`errors` is present only for validation errors.

| Status | When |
|---|---|
| `401` | Missing or invalid token, wrong login credentials, invalid webhook signature |
| `403` | Logged in but not allowed (e.g. customer creating a product or viewing another customer's order) |
| `404` | Resource or route not found |
| `422` | Validation failed, insufficient stock, product unavailable, order cannot be paid |
| `500` | Unexpected server error. The message is generic unless `APP_DEBUG=true`; details are always logged |

---

## 8. Payment webhook: how to sign a request

The signature is `HMAC-SHA256(raw request body, PAYMENT_WEBHOOK_SECRET)` as a lowercase hex
string, sent in the `X-Signature` header. The body must be sent exactly as it was signed.

**Bash / Git Bash** (reads the secret from `.env`):

```bash
SECRET=$(grep '^PAYMENT_WEBHOOK_SECRET=' .env | cut -d= -f2)
BODY='{"transaction_id":"txn_1001","order_id":1,"amount":"10.50"}'
SIG=$(printf '%s' "$BODY" | openssl dgst -sha256 -hmac "$SECRET" | sed 's/^.* //')

curl -X POST http://127.0.0.1:8000/api/webhooks/payment \
  -H "Content-Type: application/json" -H "Accept: application/json" \
  -H "X-Signature: $SIG" -d "$BODY"
```

Use an existing **pending** order id, and its exact `total_amount` as `amount`. Sending the same
command again returns `Payment already processed.`

---

## 9. Project structure

```
app/
├── Console/Commands/CancelStaleOrders.php     # orders:cancel-stale
├── Enums/                                      # UserRole, ProductStatus, OrderStatus
├── Http/
│   ├── Controllers/                            # thin controllers (validate → service → resource)
│   ├── Middleware/VerifyWebhookSignature.php   # HMAC check for the webhook
│   ├── Requests/                               # Form Request validation
│   └── Resources/                              # API Resources (JSON output)
├── Jobs/SendOrderConfirmationEmail.php         # queued, 3 tries
├── Mail/OrderConfirmationMail.php
├── Models/                                     # User, Product, Order, OrderItem, Payment
├── Policies/                                   # ProductPolicy, OrderPolicy
├── Providers/AppServiceProvider.php            # 'view-reports' Gate
└── Services/                                   # Auth, Product, Order, Payment, Report business logic
bootstrap/app.php                               # JSON error handler
database/
├── factories/  migrations/  seeders/
routes/
├── api.php                                     # all endpoints
└── console.php                                 # scheduler
tests/Feature/                                  # feature tests
postman/                                        # Postman collection
```

---

## 10. Troubleshooting

| Problem | Fix |
|---|---|
| `could not find driver` | Enable `extension=pdo_mysql` in `php.ini` |
| `SQLSTATE[HY000] [2002] ... refused` | MySQL is not running, or `DB_HOST`/`DB_PORT` are wrong |
| `Unknown database 'order_management'` | Create the databases (Setup step 4) |
| Webhook always returns `401` | The signature must be computed over the exact body sent, with the same secret as `PAYMENT_WEBHOOK_SECRET` |
| No confirmation email in the log | Start `php artisan queue:work` |
| Old orders never get cancelled | Start `php artisan schedule:work` (or run `php artisan orders:cancel-stale`) |
| Changes to `.env` are ignored | Run `php artisan config:clear` and restart `php artisan serve` |
