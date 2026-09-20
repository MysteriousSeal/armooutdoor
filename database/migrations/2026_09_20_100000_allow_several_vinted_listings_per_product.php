<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A product may carry several Vinted drafts: the same article posted again
 * with other wording or other photos. The unique key on `product_id` said one
 * row per product; a plain index takes its place, added first because the
 * foreign key needs an index under it while the unique one is dropped.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vinted_listings', function (Blueprint $table): void {
            $table->index('product_id');
        });

        Schema::table('vinted_listings', function (Blueprint $table): void {
            $table->dropUnique(['product_id']);
        });
    }

    public function down(): void
    {
        // Fails while a product still has several drafts: keep one of them
        // before rolling back.
        Schema::table('vinted_listings', function (Blueprint $table): void {
            $table->unique('product_id');
        });

        Schema::table('vinted_listings', function (Blueprint $table): void {
            $table->dropIndex(['product_id']);
        });
    }
};
