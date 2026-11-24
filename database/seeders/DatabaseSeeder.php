<?php

namespace Database\Seeders;

use App\Enums\OrderStatus;
use App\Models\Product;
use App\Models\User;
use App\Services\OrderService;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed demo data: 1 admin, 2 customers, 15 products, 6 paid orders.
     */
    public function run(OrderService $orderService): void
    {
        User::factory()->admin()->create([
            'name'     => 'Admin',
            'email'    => 'admin@example.com',
            'password' => 'password123',
        ]);

        $customers = collect([
            User::factory()->create([
                'name'     => 'Test Customer',
                'email'    => 'test@example.com',
                'password' => 'password123',
            ]),
            User::factory()->create([
                'name'     => 'Second Customer',
                'email'    => 'customer2@example.com',
                'password' => 'password123',
            ]),
        ]);

        $products = Product::factory()->count(12)->create();
        Product::factory()->count(3)->inactive()->create();

        // Real orders through the service (stock is reduced, prices snapshotted),
        // then marked paid so the top-products report has data.
        foreach ($customers as $customer) {
            foreach (range(1, 3) as $i) {
                $items = $products->random(2)->map(fn (Product $product) => [
                    'product_id' => $product->id,
                    'quantity'   => random_int(1, 5),
                ])->all();

                $orderService->place($customer, $items)
                    ->update(['status' => OrderStatus::Paid]);
            }
        }
    }
}
