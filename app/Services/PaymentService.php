<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\Payment;
use Brick\Math\BigDecimal;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PaymentService
{
    /**
     * Record a payment and mark the order as paid — exactly once per transaction_id.
     *
     * @return bool  true = processed now, false = this transaction was already processed
     */
    public function process(array $data): bool
    {
        return DB::transaction(function () use ($data) {
            // 1. Lock the order first, so duplicate webhooks for it are handled one at a time.
            $order = Order::whereKey($data['order_id'])->lockForUpdate()->firstOrFail();

            // 2. Idempotency: same transaction_id again → do nothing.
            if (Payment::where('transaction_id', $data['transaction_id'])->exists()) {
                return false;
            }

            // 3. Only a pending order can be paid.
            if ($order->status !== OrderStatus::Pending) {
                throw ValidationException::withMessages([
                    'order_id' => ["Order is {$order->status->value} and cannot be paid."],
                ]);
            }

            // 4. The paid amount must match the order total exactly.
            if (BigDecimal::of((string) $data['amount'])->compareTo($order->total_amount) !== 0) {
                throw ValidationException::withMessages([
                    'amount' => ['Amount does not match the order total.'],
                ]);
            }

            // 5. Record the payment and mark the order as paid.
            Payment::create([
                'order_id'       => $order->id,
                'transaction_id' => $data['transaction_id'],
                'amount'         => (string) $data['amount'],
                'payload'        => $data,
            ]);

            $order->update(['status' => OrderStatus::Paid]);

            return true;
        });
    }
}
