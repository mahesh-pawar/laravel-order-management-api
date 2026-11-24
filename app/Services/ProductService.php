<?php

namespace App\Services;

use App\Enums\ProductStatus;
use App\Models\Product;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ProductService
{
    /**
     * Admins see all products; customers see only active ones.
     * Optional search by name or SKU, always paginated.
     */
    public function list(User $user, array $filters): LengthAwarePaginator
    {
        return Product::query()
            ->when(!$user->isAdmin(), fn ($query) => $query->where('status', ProductStatus::Active))
            ->when($filters['search'] ?? null, function ($query, $search) {
                $query->where(function ($query) use ($search) {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('sku', 'like', "%{$search}%");
                });
            })
            ->latest('id')
            ->paginate($filters['per_page'] ?? 15);
    }

    public function create(array $data): Product
    {
        return Product::create($this->normalizePrice($data));
    }

    public function update(Product $product, array $data): Product
    {
        $product->update($this->normalizePrice($data));

        return $product;
    }

    public function delete(Product $product): void
    {
        $product->delete(); // soft delete (sets deleted_at)
    }

    /**
     * Store price as a string so it never passes through float math.
     */
    private function normalizePrice(array $data): array
    {
        if (array_key_exists('price', $data)) {
            $data['price'] = (string) $data['price'];
        }

        return $data;
    }
}
