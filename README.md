# Tocaan Order & Payment API

Laravel 10 REST API for authenticated orders and simulated payments. Uses JWT, MySQL by default, SQLite-compatible migrations, transactions, integer-cent price calculation, and a strategy-based gateway layer.

## Setup

Run `composer install`, copy `.env.example` to `.env`, configure `DB_*` and `JWT_SECRET` (or run `php artisan jwt:secret`), then run `php artisan key:generate`, `php artisan migrate`, and `php artisan serve`. Run tests with `php artisan test`.

Environment: `JWT_TTL`, `ORDER_DEFAULT_CURRENCY` (USD), `API_PER_PAGE` (15). Never commit `.env`.

## Endpoints

Public: `POST /api/register`, `POST /api/login`. Bearer-token protected: `POST /api/logout`, `POST /api/refresh`, `GET /api/me`; `POST|GET /api/orders`; `GET|PUT|PATCH|DELETE /api/orders/{order}`; `POST|GET /api/orders/{order}/payments`; `GET /api/payments`; and `GET /api/payments/{payment}`.

Order creation requires customer details and items. Server calculation ignores client totals. PUT/PATCH both support partial updates; supplying `items` replaces item snapshots. Only confirmed orders are payable. Collections support `status`, `per_page` (max 100), and payments support `method`. Responses use `{data: ...}`; pagination is Laravel standard; validation is 422 and conflicts 409.

Payments accept `method` (`credit_card` or `paypal`), optional `idempotency_key`, and test-only `simulate` (`fail`/`exception`). Amount always derives from order total. An idempotency key returns the prior payment for the same order.

## Extending gateways

`PaymentService` delegates to `PaymentGatewayManager`, which resolves `config/payments.php`; controllers have no gateway switching. To add a gateway, implement `App\\Payments\\PaymentGatewayInterface`, return `GatewayResult`, and register its class in `config/payments.php`; update the request method allowlist. The project only simulates payments and never stores raw card data or CVVs.

Import `docs/Tocaan_Order_Payment_API.postman_collection.json` into Postman, set `base_url`, and log in to populate `token`.
