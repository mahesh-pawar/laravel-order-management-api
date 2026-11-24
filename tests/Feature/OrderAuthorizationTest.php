<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class OrderAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    /** Test Case 3: a customer cannot view another customer's order. */
    public function test_customer_cannot_view_another_customers_order(): void
    {
        $owner = User::factory()->create();
        $otherCustomer = User::factory()->create();
        $order = Order::factory()->for($owner)->create();

        Sanctum::actingAs($otherCustomer);

        $this->getJson("/api/orders/{$order->id}")->assertForbidden();
    }

    public function test_customer_can_view_their_own_order(): void
    {
        $owner = User::factory()->create();
        $order = Order::factory()->for($owner)->create();

        Sanctum::actingAs($owner);

        $this->getJson("/api/orders/{$order->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $order->id);
    }
}
