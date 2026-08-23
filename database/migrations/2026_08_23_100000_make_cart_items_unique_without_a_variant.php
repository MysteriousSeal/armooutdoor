<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The unique index on (user_id, product_id, product_variant_id) never covered
 * a product sold without variants: in SQL, NULL is not equal to NULL, so two
 * rows holding NULL are two distinct keys. A double-click on "add to cart"
 * could therefore leave the same product in the basket twice, each row with
 * its own quantity.
 *
 * Indexing COALESCE(product_variant_id, 0) gives those rows a comparable key
 * without touching the column, which keeps its foreign key and its
 * null-on-delete behaviour.
 */
return new class extends Migration
{
    public function up(): void
    {
        // A duplicate already in the table would block the index. Keep the
        // largest quantity: it is the one the visitor last asked for.
        $duplicates = DB::table('cart_items')
            ->selectRaw('user_id, product_id, MAX(quantity) as keep_quantity, MIN(id) as keep_id')
            ->whereNull('product_variant_id')
            ->groupBy('user_id', 'product_id')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        foreach ($duplicates as $duplicate) {
            DB::table('cart_items')
                ->where('user_id', $duplicate->user_id)
                ->where('product_id', $duplicate->product_id)
                ->whereNull('product_variant_id')
                ->whereKeyNot($duplicate->keep_id)
                ->delete();

            DB::table('cart_items')
                ->where('id', $duplicate->keep_id)
                ->update(['quantity' => $duplicate->keep_quantity]);
        }

        Schema::table('cart_items', function ($table): void {
            $table->dropUnique(['user_id', 'product_id', 'product_variant_id']);
        });

        DB::statement(
            'CREATE UNIQUE INDEX cart_items_user_product_variant_unique
             ON cart_items (user_id, product_id, COALESCE(product_variant_id, 0))'
        );
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS cart_items_user_product_variant_unique');

        Schema::table('cart_items', function ($table): void {
            $table->unique(['user_id', 'product_id', 'product_variant_id']);
        });
    }
};
