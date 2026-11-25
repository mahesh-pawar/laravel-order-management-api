<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Regression tests for inputs that used to return 500 instead of a clean response.
 */
class ValidationEdgeCasesTest extends TestCase
{
    use RefreshDatabase;

    public function test_stock_above_unsigned_int_max_is_rejected_with_422(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());

        $this->postJson('/api/products', [
            'name'           => 'Too much stock',
            'sku'            => 'BIG-STOCK',
            'price'          => '1.00',
            'stock_quantity' => 99999999999,
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['stock_quantity']);
    }

    public function test_largest_order_allowed_by_validation_fits_in_the_database(): void
    {
        Queue::fake();
        Sanctum::actingAs(User::factory()->create());

        $product = Product::factory()->create(['price' => '99999999.99', 'stock_quantity' => 1000]);

        // 1000 x 99,999,999.99 = 99,999,999,990.00 (did not fit in decimal(12,2))
        $this->postJson('/api/orders', [
            'items' => [['product_id' => $product->id, 'quantity' => 1000]],
        ])
            ->assertCreated()
            ->assertJsonPath('data.total_amount', '99999999990.00')
            ->assertJsonPath('data.items.0.line_total', '99999999990.00');
    }

    public function test_report_with_array_date_is_rejected_with_422(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());

        $this->getJson('/api/reports/top-products?from[]=2026-11-01&to=2026-11-30')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['from']);
    }
}
