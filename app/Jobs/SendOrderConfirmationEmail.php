<?php

namespace App\Jobs;

use App\Mail\OrderConfirmationMail;
use App\Models\Order;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SendOrderConfirmationEmail implements ShouldQueue
{
    use Queueable;

    /**
     * Try the job up to 3 times before marking it as failed.
     */
    public int $tries = 3;

    /**
     * Seconds to wait before retry 1 and retry 2.
     */
    public array $backoff = [10, 30];

    public function __construct(public Order $order)
    {
    }

    public function handle(): void
    {
        $this->order->load('user', 'items.product');

        Mail::to($this->order->user->email)
            ->send(new OrderConfirmationMail($this->order));
    }

    /**
     * Called once after all 3 attempts have failed.
     */
    public function failed(Throwable $exception): void
    {
        Log::error('Order confirmation email failed', [
            'order_id' => $this->order->id,
            'error'    => $exception->getMessage(),
        ]);
    }
}
