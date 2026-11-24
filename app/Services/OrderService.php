<?php

namespace App\Services;

use App\Enums\ProductStatus;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Brick\Math\BigDecimal;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use App\Jobs\SendOrderConfirmationEmail;

class OrderService
{
    /**
     * Place an order: lock products, check stock, reduce stock,
     * and save prices as they are right now — all in one transaction.
     *
     * @param  array<int, array{product_id: int, quantity: int}>  $items
     */
    public function place(User $user, array $items): Order
    {
        $order = DB::transaction(function () use ($user, $items) {
            // 1. Lock the product rows (ascending id order prevents deadlocks).
            //    Soft-deleted products are excluded automatically.
            $products = Product::whereIn('id', array_column($items, 'product_id'))
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            // 2. Check every item before changing anything.
            $errors = [];
            foreach ($items as $index => $item) {
                $product = $products->get($item['product_id']);

                if (!$product || $product->status !== ProductStatus::Active) {
                    $errors["items.$index.product_id"] = 'This product is not available.';
                } elseif ($product->stock_quantity < $item['quantity']) {
                    $errors["items.$index.quantity"] = "Insufficient stock. Only {$product->stock_quantity} left.";
                }
            }

            if ($errors) {
                throw ValidationException::withMessages($errors); // 422 + rollback
            }

            // 3. Reduce stock and build the order lines with today's prices.
            $total = BigDecimal::zero();
            $lines = [];

            foreach ($items as $item) {
                $product = $products->get($item['product_id']);
                $lineTotal = BigDecimal::of($product->price)->multipliedBy($item['quantity']);
                $total = $total->plus($lineTotal);

                $product->decrement('stock_quantity', $item['quantity']);

                $lines[] = [
                    'product_id' => $product->id,
                    'quantity'   => $item['quantity'],
                    'unit_price' => $product->price,
                    'line_total' => (string) $lineTotal,
                ];
            }

            // 4. Save the order (status defaults to pending) and its items.
            $order = $user->orders()->create(['total_amount' => (string) $total]);
            $order->items()->createMany($lines);

            return $order;
        });

        // Dispatched after the transaction has committed,
        // so an email is never sent for an order that was rolled back.
        SendOrderConfirmationEmail::dispatch($order);

        return $order->load('items.product');
    }

    /**
     * Admins see all orders; customers see only their own.
     */
    public function list(User $user): LengthAwarePaginator
    {
        return Order::query()
            ->when(!$user->isAdmin(), fn ($query) => $query->where('user_id', $user->id))
            ->with('items.product')
            ->latest('id')
            ->paginate(15);
    }
}
