<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A customer can leave one review per qualifying (shipped) order rather
     * than one ever per product, so a repeat buyer can review each purchase.
     */
    public function up(): void
    {
        Schema::table('product_reviews', function (Blueprint $table) {
            $table->dropUnique(['product_id', 'user_id']);
            $table->unique(['product_id', 'order_id']);
        });
    }

    public function down(): void
    {
        Schema::table('product_reviews', function (Blueprint $table) {
            $table->dropUnique(['product_id', 'order_id']);
            $table->unique(['product_id', 'user_id']);
        });
    }
};
