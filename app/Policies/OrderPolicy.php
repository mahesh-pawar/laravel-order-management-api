<?php

namespace App\Policies;

use App\Models\Order;
use App\Models\User;

class OrderPolicy
{
    /**
     * Admins can view any order; customers only their own.
     */
    public function view(User $user, Order $order): bool
    {
        return $user->isAdmin() || $order->user_id === $user->id;
    }

    /**
     * Only customers place orders.
     */
    public function create(User $user): bool
    {
        return !$user->isAdmin();
    }
}
