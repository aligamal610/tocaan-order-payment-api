# Postman Endpoint Coverage

Route list audited with `php artisan route:list --path=api`.

| Method | URI | Auth | Postman request | Positive | Negative / special setup |
| --- | --- | --- | --- | --- | --- |
| POST | `api/register` | No | `[SUCCESS] Register` | Yes | Invalid fields; duplicate email can be replayed after a successful registration and returns 422 |
| POST | `api/login` | No | `[SUCCESS] Login` | Yes | Invalid credentials |
| POST | `api/logout` | JWT | `[SUCCESS] Logout (Run Last)` | Yes | Protected-route no-token scenarios cover JWT enforcement |
| GET | `api/me` | JWT | `[SUCCESS] Get Current User` | Yes | Without token |
| POST | `api/refresh` | JWT | `[SUCCESS] Refresh Token` | Yes | Requires a non-revoked token |
| GET | `api/orders` | JWT | `[SUCCESS] List Orders With Pagination` | Yes | Without token; confirmed-status filter |
| POST | `api/orders` | JWT | `[SUCCESS] Create Order` | Yes | Without items |
| GET | `api/orders/{order}` | JWT + owner | `[SUCCESS] Get Order` | Yes | Nonexistent ID; another user's order also resolves as 404 |
| PUT/PATCH | `api/orders/{order}` | JWT + owner | `[SUCCESS] Confirm Order (PATCH)` | Yes | Paid order update returns 409; PUT and PATCH use identical partial-update behavior |
| DELETE | `api/orders/{order}` | JWT + owner | `[BUSINESS RULE] Delete Order With Payments` | Yes, for unpaid orders (manual new order) | Paid order returns 409 |
| POST | `api/orders/{order}/payments` | JWT + owner | `[SUCCESS] Process Credit Card Payment` | Yes | Pending/cancelled order 409, invalid ID 404, unsupported method 422, gateway simulation |
| GET | `api/orders/{order}/payments` | JWT + owner | `[SUCCESS] Get Payments For Order` | Yes | Owner-only access; no-token behavior is covered by protected-route checks |
| GET | `api/payments` | JWT | `[SUCCESS] List Payments With Filters` | Yes | Without token |
| GET | `api/payments/{payment}` | JWT + owner order | `[SUCCESS] Get Payment` | Yes | Other user's payment resolves as 404 |

No API routes were excluded. Laravel's non-API web/framework routes are outside this collection because they are not part of the `/api` contract.
