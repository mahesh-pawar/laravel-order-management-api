# Design and Implementation Note

## Key design decisions

- **Layers:** Form Request → Service → API Resource. Controllers stay thin; Policies/Gates are
  applied on routes, so a forbidden user gets `403` before validation runs.
- **No overselling:** order placement runs in one transaction and locks product rows
  (`FOR UPDATE`, ascending id to avoid deadlocks), checks every item, then reduces stock. Prices
  and totals are snapshotted; money uses `BigDecimal` and `DECIMAL` columns, never floats.
- **Webhook:** HMAC-SHA256 of the raw body, compared with `hash_equals`. Idempotent by locking
  the order before checking `transaction_id`, backed by a unique index.
- **Stale orders:** each cancellation locks the order and re-checks it is still pending, so a
  concurrent payment can never be undone.
- **Report:** one aggregate query using the `orders(status, created_at)` index; only paid and
  shipped orders count as sold.
- **Simple setup:** database queue and log mailer (PHP + MySQL only); dependencies resolved for
  PHP 8.2; tests run on MySQL because SQLite ignores row locks.

## What I would improve with more time

- Order status endpoints (ship, customer cancel), rate limiting and token expiry.
- FULLTEXT search instead of `LIKE '%term%'`.
- CI on MySQL 8 / PHP 8.2 and automated concurrency tests.
- Upgrade to Laravel 12 (Laravel 11 is past security support).

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
  platform compatibility fix in `composer.json`. At my request it also applied the fixes for three
  edge cases found during API testing (with regression tests) and the retry-count change.

I reviewed the implementation and understand the code and design decisions. I can explain the
approach, trade-offs, and limitations of the implementation during the technical discussion.
