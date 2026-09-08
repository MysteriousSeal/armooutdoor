<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A product's Vinted listing: its title, its text, its price, its photos.
 *
 * A table of its own rather than columns on `products`: what the shop sells
 * and what it says about it on a marketplace are two things, and the second
 * starts from nothing again for the next marketplace. One row per product —
 * the unique constraint says so, so no screen has to choose between two
 * drafts.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vinted_listings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('title')->nullable();
            $table->text('description')->nullable();
            // In cents like everywhere else, and optional: a listing still
            // being written does not have a price yet.
            $table->unsignedInteger('price_cents')->nullable();
            $table->timestamps();
        });

        /*
         * The listing's photos are its own. On Vinted one photographs the
         * article worn, on a table, in the light of the room; these are not
         * the catalogue's images, and tying the two together would have
         * forced each to change with the other.
         */
        Schema::create('vinted_listing_images', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('vinted_listing_id')->constrained()->cascadeOnDelete();
            $table->string('image');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['vinted_listing_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vinted_listing_images');
        Schema::dropIfExists('vinted_listings');
    }
};
