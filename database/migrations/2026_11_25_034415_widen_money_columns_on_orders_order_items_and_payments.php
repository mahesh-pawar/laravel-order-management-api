<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Widen calculated money columns so the largest order validation allows always fits.
     *
     * Max line total: 99,999,999.99 (max price) x 1,000 (max quantity) = 99,999,999,990.00
     * Max order total: that x 50 (max items)                              = 4,999,999,999,500.00
     * decimal(12,2) holds up to 9,999,999,999.99, which is too small; decimal(15,2) is enough.
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->decimal('total_amount', 15, 2)->change();
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->decimal('line_total', 15, 2)->change();
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->decimal('amount', 15, 2)->change();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->decimal('total_amount', 12, 2)->change();
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->decimal('line_total', 12, 2)->change();
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->decimal('amount', 12, 2)->change();
        });
    }
};
