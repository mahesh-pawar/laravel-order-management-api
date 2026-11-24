<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CancelStaleOrdersTest extends TestCase
{
    use RefreshDatabase;

    public function test_stale_pending_orders_are_cancelled_and_stock_is_restored(): void
    {
        $product = Product::factory()->create(['price' => '10.00', 'stock_quantity' => 6]);

        $stale  = $this->orderWithItem($product, 4, OrderStatus::Pending, now()->subMinutes(31));
        $recent = $this->orderWithItem($product, 1, OrderStatus::Pending, now()->subMinutes(5));
        $paid   = $this->orderWithItem($product, 2, OrderStatus::Paid, now()->subHour());

        $this->artisan('orders:cancel-stale')
            ->expectsOutput('Cancelled 1 stale pending order(s).')
            ->assertSuccessful();

        $this->assertSame(OrderStatus::Cancelled, $stale->fresh()->status);
        $this->assertSame(OrderStatus::Pending, $recent->fresh()->status);   // too new
        $this->assertSame(OrderStatus::Paid, $paid->fresh()->status);        // already paid
        $this->assertSame(10, $product->fresh()->stock_quantity);            // 6 + 4 restored
    }

    private function orderWithItem(Product $product, int $quantity, OrderStatus $status, $createdAt): Order
    {
        $order = Order::factory()->create(['status' => $status, 'total_amount' => '0.00']);
        $order->items()->create([
            'product_id' => $product->id,
            'quantity'   => $quantity,
            'unit_price' => $product->price,
            'line_total' => '0.00',
        ]);
        $order->forceFill(['created_at' => $createdAt])->save();

        return $order;
    }
}
