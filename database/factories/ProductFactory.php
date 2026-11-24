<?php

namespace Database\Factories;

use App\Enums\ProductStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Product>
 */
class ProductFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name'           => ucfirst(fake()->words(2, true)),
            'sku'            => strtoupper(fake()->unique()->bothify('SKU-####-??')),
            'price'          => (string) fake()->randomFloat(2, 5, 500),
            'stock_quantity' => fake()->numberBetween(20, 200),
            'status'         => ProductStatus::Active->value,
        ];
    }

    /**
     * Indicate that the product is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ProductStatus::Inactive->value,
        ]);
    }
}
