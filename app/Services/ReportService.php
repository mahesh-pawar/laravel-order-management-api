<?php

namespace App\Services;

use App\Enums\OrderStatus;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ReportService
{
    /**
     * Top 5 products by quantity sold (paid or shipped orders) in a date range.
     * One SQL query: join + filter + group + sum + sort + limit, all done by MySQL.
     */
    public function topProducts(string $from, string $to): Collection
    {
        return DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->join('products', 'products.id', '=', 'order_items.product_id')
            ->whereIn('orders.status', [OrderStatus::Paid->value, OrderStatus::Shipped->value])
            ->whereBetween('orders.created_at', [
                Carbon::parse($from)->startOfDay(),
                Carbon::parse($to)->endOfDay(),
            ])
            ->groupBy('order_items.product_id', 'products.name', 'products.sku')
            ->orderByDesc('total_quantity')
            ->orderBy('order_items.product_id')
            ->limit(5)
            ->get([
                'order_items.product_id',
                'products.name',
                'products.sku',
                DB::raw('SUM(order_items.quantity) AS total_quantity'),
                DB::raw('SUM(order_items.line_total) AS total_revenue'),
            ]);
    }
}
