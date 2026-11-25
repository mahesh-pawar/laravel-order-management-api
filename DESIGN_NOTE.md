# Design and Implementation Note

## Key design decisions

- **Thin controllers, logic in services.** Every request follows Form Request (validation) →
  Service (business logic) → API Resource (JSON). Controllers only connect these, and authorization
  runs on the routes (`->can(...)`) so a forbidden user gets `403` before validation runs.
- **Authorization.** Policies for models (`ProductPolicy`, `OrderPolicy`: customers see only their
  own orders) and a Gate (`view-reports`) for the report, which is not tied to a model. Public
  registration always creates a customer; `role` is not mass-assignable.
- **No overselling.** Placing an order runs in one transaction: product rows are locked with
  `SELECT … FOR UPDATE` in ascending id order (prevents deadlocks), every item is checked before
  anything changes, then stock is decremented. Prices and totals are copied into the order, and
  money is calculated with `BigDecimal` and stored as `DECIMAL`, never floats.
- **Webhook safety.** The signature is an HMAC-SHA256 of the raw body compared with `hash_equals`
  (constant time). Idempotency has two layers: the order row is locked first and then the
  `transaction_id` is checked, and a unique index on `transaction_id` guarantees no duplicate
  payment at database level. Duplicates return `200` so the provider stops retrying.
- **Stale-order cancellation** locks each order and re-checks it is still pending, so a payment
  arriving at the same moment can never leave an order both paid and restocked.
- **Report** is a single aggregate query (join, group, sum, limit 5) that uses the
  `orders(status, created_at)` index. Only paid and shipped orders count as "sold".
- **Simple infrastructure.** Database queue driver and log mailer, so the project needs only PHP
  and MySQL. Tests run on MySQL (not SQLite) because SQLite ignores row locks.
- **Compatibility.** Dependencies are resolved for PHP 8.2 (`config.platform.php`) so the lock file
  installs on 8.2–8.4. Laravel 11 is past security support, so only `laravel/framework` advisories
  are allowed in `composer.json`; I would move to Laravel 12 in a real project.

## What I would improve with more time

- Map the rare duplicate-key race on registration (two identical sign-ups at the same instant) to
  `409 Conflict` instead of a `500`.
- Endpoints for admins to update order status (ship) and for customers to cancel their own order.
- A FULLTEXT index (or a search engine) for product search; `LIKE '%term%'` cannot use an index.
- Rate limiting on login and the webhook, and token expiry for Sanctum tokens.
- A CI pipeline (GitHub Actions) running the tests on MySQL 8 and PHP 8.2 on every push, plus
  automated concurrency tests for the locking behaviour.
- OpenAPI/Swagger documentation generated from the code.

## AI-Assisted Development Disclosure

I used **Claude Code** (Anthropic) as a tutor and code reviewer while building this project.

- It helped me break down the assignment into modules and understand concepts such as Sanctum,
  row locking, HMAC, queues, and scheduling before implementing them. I wrote the application code
  myself, module by module.
- It reviewed my code after each step and identified issues, such as routes placed outside the
  `auth:sanctum` middleware group and a missing default value for the `role` attribute in the
  model.
- I used it to assist with manual API testing and concurrency scenarios, including two buyers
  attempting to purchase the last item, duplicate webhooks, and payment processing versus
  cancellation, to verify the locking and consistency mechanisms.
- It helped generate the Postman collection, README, and the initial draft of this disclosure. It
  also identified that `composer.lock` required PHP 8.4 and, at my request, applied the PHP 8.2
  platform compatibility fix in `composer.json`.

I reviewed the implementation and understand the code and design decisions. I can explain the
approach, trade-offs, and limitations of the implementation during the technical discussion.
