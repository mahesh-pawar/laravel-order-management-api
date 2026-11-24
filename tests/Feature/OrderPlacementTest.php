<?php

namespace Tests\Feature;

use App\Jobs\SendOrderConfirmationEmail;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class OrderPlacementTest extends TestCase
{
    use RefreshDatabase;

    /** Test Case 1: successful order placement reduces stock. */
    public function test_customer_can_place_an_order_and_stock_is_reduced(): void
    {
        Queue::fake();

        $customer = User::factory()->create();
        $pen  = Product::factory()->create(['price' => '10.50', 'stock_quantity' => 10]);
        $book = Product::factory()->create(['price' => '25.00', 'stock_quantity' => 5]);

        Sanctum::actingAs($customer);

        $response = $this->postJson('/api/orders', [
            'items' => [
                ['product_id' => $pen->id,  'quantity' => 3],
                ['product_id' => $book->id, 'quantity' => 2],
            ],
        ]);

        // 3 × 10.50 + 2 × 25.00 = 81.50
        $response->assertCreated()
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.total_amount', '81.50')
            ->assertJsonCount(2, 'data.items');

        $this->assertDatabaseHas('orders', ['user_id' => $customer->id, 'total_amount' => '81.50']);
        $this->assertDatabaseHas('order_items', ['product_id' => $pen->id, 'quantity' => 3, 'unit_price' => '10.50']);

        $this->assertSame(7, $pen->fresh()->stock_quantity);
        $this->assertSame(3, $book->fresh()->stock_quantity);

        Queue::assertPushed(SendOrderConfirmationEmail::class);
    }

    /** Test Case 2: ordering more than the stock fails with 422. */
    public function test_order_fails_with_422_when_stock_is_insufficient(): void
    {
        Queue::fake();

        $customer = User::factory()->create();
        $pen = Product::factory()->create(['stock_quantity' => 2]);

        Sanctum::actingAs($customer);

        $this->postJson('/api/orders', [
            'items' => [['product_id' => $pen->id, 'quantity' => 5]],
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['items.0.quantity']);

        // Nothing changed
        $this->assertSame(2, $pen->fresh()->stock_quantity);
        $this->assertDatabaseCount('orders', 0);
        Queue::assertNothingPushed();
    }
}
