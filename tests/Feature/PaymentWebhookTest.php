<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class PaymentWebhookTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'test-webhook-secret';

    protected function setUp(): void
    {
        parent::setUp();

        // Use a known secret in tests, independent of anyone's .env
        config(['services.payment.webhook_secret' => self::SECRET]);
    }

    /**
     * Send a webhook with the exact raw body, signed like a real provider would.
     */
    private function sendWebhook(array $payload, ?string $signature = null): TestResponse
    {
        $body = json_encode($payload);
        $signature ??= hash_hmac('sha256', $body, self::SECRET);

        return $this->call('POST', '/api/webhooks/payment', [], [], [], [
            'CONTENT_TYPE'     => 'application/json',
            'HTTP_ACCEPT'      => 'application/json',
            'HTTP_X_SIGNATURE' => $signature,
        ], $body);
    }

    /** Test Case 4: invalid signature is rejected. */
    public function test_webhook_with_invalid_signature_is_rejected(): void
    {
        $order = Order::factory()->create(['total_amount' => '50.00']);

        $this->sendWebhook(
            ['transaction_id' => 'txn_bad', 'order_id' => $order->id, 'amount' => '50.00'],
            'invalid-signature'
        )->assertUnauthorized();

        $this->assertSame(OrderStatus::Pending, $order->fresh()->status);
        $this->assertDatabaseCount('payments', 0);
    }

    public function test_valid_webhook_marks_order_as_paid(): void
    {
        $order = Order::factory()->create(['total_amount' => '50.00']);

        $this->sendWebhook(['transaction_id' => 'txn_1', 'order_id' => $order->id, 'amount' => '50.00'])
            ->assertOk()
            ->assertJsonPath('message', 'Payment processed.');

        $this->assertSame(OrderStatus::Paid, $order->fresh()->status);
        $this->assertDatabaseHas('payments', ['transaction_id' => 'txn_1', 'order_id' => $order->id]);
    }

    /** Test Case 5: the same transaction_id is processed only once. */
    public function test_duplicate_webhook_is_processed_only_once(): void
    {
        $order = Order::factory()->create(['total_amount' => '50.00']);
        $payload = ['transaction_id' => 'txn_dup', 'order_id' => $order->id, 'amount' => '50.00'];

        $this->sendWebhook($payload)->assertOk()->assertJsonPath('message', 'Payment processed.');
        $this->sendWebhook($payload)->assertOk()->assertJsonPath('message', 'Payment already processed.');

        $this->assertDatabaseCount('payments', 1);
        $this->assertSame(OrderStatus::Paid, $order->fresh()->status);
    }
}
