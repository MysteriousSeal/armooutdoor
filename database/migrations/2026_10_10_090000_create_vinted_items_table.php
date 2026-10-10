<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What is sold on Vinted and nowhere else.
 *
 * A table of its own rather than hidden rows in `products`: the storefront,
 * the feeds and the sitemap all read `products`, and an article that must
 * never show there is safest where none of them looks.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vinted_items', function (Blueprint $table): void {
            $table->id();
            $table->string('title');
            $table->string('image')->nullable();
            // What the whole lot cost, not one piece: that is the figure on
            // the receipt. The unit cost is worked out from it.
            $table->unsignedInteger('purchase_total_cents');
            // How many pieces that price bought. Kept apart from what is
            // left, or the unit cost would climb with every sale.
            $table->unsignedInteger('lot_quantity');
            $table->unsignedInteger('quantity');
            $table->timestamps();

            $table->index('quantity');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vinted_items');
    }
};
