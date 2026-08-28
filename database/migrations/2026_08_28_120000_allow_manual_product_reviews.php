<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A review posted on a marketplace has no account and no order here, only
     * a name; the customer and order become optional and the name gets its
     * own column, filled only for those imported reviews. `source` remembers
     * which marketplace it came from — shown in the admin, never on the shop.
     */
    public function up(): void
    {
        Schema::table('product_reviews', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->change();
            $table->foreignId('order_id')->nullable()->change();
            $table->string('author_name', 100)->nullable()->after('order_id');
            $table->string('source', 50)->nullable()->after('author_name');
        });
    }

    public function down(): void
    {
        Schema::table('product_reviews', function (Blueprint $table) {
            $table->dropColumn(['author_name', 'source']);
            $table->foreignId('user_id')->nullable(false)->change();
            $table->foreignId('order_id')->nullable(false)->change();
        });
    }
};
