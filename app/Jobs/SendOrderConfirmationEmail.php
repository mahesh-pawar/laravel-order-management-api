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
     * Total attempts: the first try + 3 retries on failure.
     * ($tries counts every attempt, so "retry 3 times" means 4.)
     */
    public int $tries = 4;

    /**
     * Seconds to wait before retry 1, retry 2 and retry 3.
     */
    public array $backoff = [10, 30, 60];

    public bool $deleteWhenMissingModels = true;

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
