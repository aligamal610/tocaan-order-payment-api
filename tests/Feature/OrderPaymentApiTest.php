<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderPaymentApiTest extends TestCase
{
    use RefreshDatabase;

    private function token(User $user): array
    {
        return ['Authorization' => 'Bearer '.auth('api')->login($user)];
    }

    public function test_user_can_create_confirm_and_pay_order(): void
    {
        $user = User::factory()->create();
        $headers = $this->token($user);
        $order = $this->postJson('/api/orders', [
            'customer_name' => 'Ada',
            'customer_email' => 'ada@example.com',
            'items' => [
                [
                    'product_name' => 'Widget',
                    'quantity' => 2,
                    'unit_price' => '12.50',
                ],
            ],
        ], $headers)->assertCreated()->json('data');

        $this->assertSame('25.00', $order['total']);

        $this->patchJson('/api/orders/'.$order['id'], ['status' => 'confirmed'], $headers)->assertOk();

        $this->postJson('/api/orders/'.$order['id'].'/payments', [
            'method' => 'credit_card',
            'idempotency_key' => 'one',
        ], $headers)->assertCreated()->assertJsonPath('data.amount', '25.00');
    }

    public function test_users_cannot_read_other_orders(): void
    {
        $order = Order::factory()->create();
        $response = $this->getJson('/api/orders/'.$order->id, $this->token(User::factory()->create()));

        $response->assertNotFound();
    }

    public function test_pending_order_cannot_be_paid(): void
    {
        $order = Order::factory()->create(['status' => 'pending']);
        $response = $this->postJson(
            '/api/orders/'.$order->id.'/payments',
            ['method' => 'paypal'],
            $this->token($order->user)
        );

        $response->assertConflict();
    }

    public function test_order_without_payments_can_be_deleted(): void
    {
        $order = Order::factory()->create();

        $this->deleteJson('/api/orders/'.$order->id, [], $this->token($order->user))
            ->assertNoContent();

        $this->assertDatabaseMissing('orders', ['id' => $order->id]);
    }

    public function test_order_with_payments_cannot_be_deleted(): void
    {
        $order = Order::factory()->create(['status' => 'confirmed']);

        $this->postJson('/api/orders/'.$order->id.'/payments', [
            'method' => 'credit_card',
            'idempotency_key' => 'deletion-check',
        ], $this->token($order->user))->assertCreated();

        $this->deleteJson('/api/orders/'.$order->id, [], $this->token($order->user))
            ->assertConflict()
            ->assertJsonPath('message', 'Orders with payments cannot be deleted.');
    }

    public function test_order_creation_returns_validation_errors_for_missing_customer_fields(): void
    {
        $response = $this->postJson('/api/orders', [
            'items' => [
                [
                    'product_name' => 'Widget',
                    'quantity' => 1,
                    'unit_price' => '10.00',
                ],
            ],
        ], $this->token(User::factory()->create()));

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['customer_name', 'customer_email']);
    }

    public function test_order_and_payment_list_filters_are_validated(): void
    {
        $headers = $this->token(User::factory()->create());

        $this->getJson('/api/orders?status=invalid&per_page=0', $headers)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['status', 'per_page']);

        $this->getJson('/api/payments?method=cash&status=unknown', $headers)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['method', 'status']);
    }

    public function test_api_authentication_errors_are_json_without_an_accept_header(): void
    {
        $this->get('/api/orders')
            ->assertUnauthorized()
            ->assertJsonPath('message', 'Unauthenticated.');
    }

    public function test_registration_and_login_validate_and_authenticate_users(): void
    {
        $this->postJson('/api/register', [
            'name' => 'Ada',
            'email' => 'ada@example.test',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertCreated()
            ->assertJsonPath('data.user.email', 'ada@example.test')
            ->assertJsonStructure(['data' => ['token']]);

        $this->postJson('/api/login', [
            'email' => 'ada@example.test',
            'password' => 'wrong-password',
        ])->assertUnauthorized()
            ->assertJsonPath('message', 'Invalid credentials.');

        $this->postJson('/api/login', [
            'email' => 'ada@example.test',
            'password' => 'password123',
        ])->assertOk()
            ->assertJsonStructure(['data' => ['token', 'token_type', 'expires_in']]);
    }

    public function test_payment_ownership_and_gateway_failures_are_handled_safely(): void
    {
        $order = Order::factory()->create(['status' => 'confirmed']);
        $headers = $this->token($order->user);

        $payment = $this->postJson('/api/orders/'.$order->id.'/payments', [
            'method' => 'paypal',
            'idempotency_key' => 'gateway-failure',
            'simulate' => 'exception',
        ], $headers)->assertCreated()
            ->assertJsonPath('data.status', 'failed')
            ->assertJsonPath('data.gateway_metadata.reason', 'gateway_error')
            ->json('data');

        $this->getJson('/api/payments/'.$payment['id'], $this->token(User::factory()->create()))
            ->assertNotFound();
    }

    public function test_paid_orders_cannot_be_updated(): void
    {
        $order = Order::factory()->create(['status' => 'confirmed']);
        $headers = $this->token($order->user);

        $this->postJson('/api/orders/'.$order->id.'/payments', [
            'method' => 'credit_card',
        ], $headers)->assertCreated();

        $this->patchJson('/api/orders/'.$order->id, [
            'customer_name' => 'Changed Name',
        ], $headers)->assertConflict()
            ->assertJsonPath('message', 'Paid orders cannot be edited.');
    }
}
