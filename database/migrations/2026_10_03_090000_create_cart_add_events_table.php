<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Every successful add-to-cart, kept separately from cart_items: a cart item
 * is overwritten as the quantity changes, so it can't tell "added three times"
 * from "added once at quantity three". This is the append-only log of each
 * add, the server-side counterpart to the browser's add_to_cart/cart_item_added
 * events in public/js/analytics.js.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cart_add_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('variant_id')->nullable()->constrained('product_variants')->nullOnDelete();
            $table->unsignedInteger('quantity');
            $table->unsignedInteger('unit_price_cents');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            // The same first-party session cookie used for the cart and
            // login, and the same consent gate as site_visits: carried only
            // once the visitor accepted it, so a guest who declined is
            // recorded without anything that could link this row to their
            // next visit.
            $table->string('session_id', 100)->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index('created_at');
            $table->index(['session_id', 'created_at']);
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cart_add_events');
    }
};
