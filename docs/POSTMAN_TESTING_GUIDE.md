# Postman Testing Guide

## Local setup

Install PHP dependencies with `composer install`. Copy `.env.example` to `.env`, configure the MySQL `DB_*` variables, then create application and JWT secrets:

```powershell
php artisan key:generate
php artisan jwt:secret
php artisan migrate
php artisan serve
```

The collection defaults to `http://127.0.0.1:8000/api`. If the server uses another host or port, update the `base_url` collection variable after import. Never add real credentials, card data, or JWT values to the collection file.

## Import and main workflow

Import `Tocaan_Order_Payment_API.postman_collection.json` in Postman. The collection owns its variables: `base_url`, `token`, `test_email`, `test_password`, `order_id`, `pending_order_id`, `payment_id`, and `idempotency_key`.

Run these requests in order:

1. `Authentication / [SUCCESS] Register` generates a unique test email and saves its JWT to `token`.
2. `Orders / [SUCCESS] Create Order` saves the response ID to `order_id` and asserts the server-calculated 1250.00 total.
3. `Orders / [SUCCESS] Confirm Order (PATCH)` changes the order from `pending` to `confirmed`.
4. `Payments / [SUCCESS] Process Credit Card Payment` saves `payment_id`.
5. Run payment retrieval/list/history and idempotency requests.
6. Run `Orders / [BUSINESS RULE] Delete Order With Payments` to verify the 409 protection.
7. Run logout only after protected scenarios, because it invalidates the collection token.

All protected requests use `Authorization: Bearer {{token}}`. Register and Login have request scripts which set `token` from the actual `data.token` response.

## Success and error scenarios

The collection includes executable tests for invalid registration/login, absent JWTs, missing order items, nonexistent orders, unsupported methods, idempotency, gateway exceptions, and deletion after payment.

To test payment rejection before confirmation, create another order with the normal create request, copy its ID into `pending_order_id`, and do not run confirmation on that order. The `Pay Pending Order` request must return 409. To test cancelled order rejection, create another order, patch its status to `cancelled`, then post a payment; it also returns 409 with `Only confirmed orders can be paid.`

The gateway is simulated. `simulate: "fail"` produces a payment with `failed` status. `simulate: "exception"` tests safe exception handling and returns a failed payment whose metadata is `{ "reason": "gateway_error" }`. No actual charge is performed.

## Resetting data safely

Use a dedicated local test database. To reset it, verify the configured database is disposable, then run:

```powershell
php artisan migrate:fresh
```

This deletes all tables in the configured connection. Do not use it against shared, staging, or production data.

## Troubleshooting

- `401 Unauthorized`: run Register/Login again; ensure the request uses `Bearer {{token}}` and logout has not been run.
- `422 Unprocessable Content`: inspect `errors` in Laravel's JSON validation response; money fields accept up to two decimal places.
- `409 Only confirmed orders can be paid`: PATCH the intended order with `{ "status": "confirmed" }` first.
- `409 Paid orders cannot be edited` or deletion conflict: use a new unpaid order for update/delete tests.
- Database connection errors: check `.env`, ensure MySQL is running, then rerun migrations.

## cURL workflow

```powershell
curl -X POST http://127.0.0.1:8000/api/register -H "Content-Type: application/json" -d '{"name":"API Tester","email":"api.tester@example.test","password":"Password123!","password_confirmation":"Password123!"}'
curl -X POST http://127.0.0.1:8000/api/orders -H "Authorization: Bearer TOKEN" -H "Content-Type: application/json" -d '{"customer_name":"API Tester","customer_email":"api.tester@example.test","items":[{"product_name":"Keyboard","quantity":1,"unit_price":"500.00"}]}'
curl -X PATCH http://127.0.0.1:8000/api/orders/ORDER_ID -H "Authorization: Bearer TOKEN" -H "Content-Type: application/json" -d '{"status":"confirmed"}'
curl -X POST http://127.0.0.1:8000/api/orders/ORDER_ID/payments -H "Authorization: Bearer TOKEN" -H "Content-Type: application/json" -d '{"method":"credit_card","idempotency_key":"curl-checkout-001"}'
```
