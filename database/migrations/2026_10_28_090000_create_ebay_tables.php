<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One row: the seller account the shop signed in to eBay with.
        Schema::create('ebay_connections', function (Blueprint $table): void {
            $table->id();
            // Encrypted by the model: either one lets anyone act as the seller.
            $table->text('refresh_token');
            $table->timestamp('refresh_token_expires_at')->nullable();
            $table->text('access_token')->nullable();
            $table->timestamp('access_token_expires_at')->nullable();
            $table->timestamps();
        });

        Schema::create('ebay_listings', function (Blueprint $table): void {
            $table->id();
            // eBay's item number: it decides whether a sync creates a row or updates one.
            $table->string('item_id')->unique();
            $table->string('title');
            $table->string('url')->nullable();
            // The seller's custom label, which carries our SKU.
            $table->string('sku')->nullable()->index();
            $table->integer('price_cents')->default(0);
            $table->string('currency', 3)->default('EUR');
            // What is still available to buy, not what was first listed.
            $table->integer('quantity')->default(0);
            $table->integer('quantity_sold')->default(0);
            $table->json('variations')->nullable();
            // When this listing was last seen on eBay.
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ebay_listings');
        Schema::dropIfExists('ebay_connections');
    }
};
