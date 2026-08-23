<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('product_variant_id')->nullable()->constrained()->nullOnDelete();
            // Snapshots: a deleted product leaves a readable historical line.
            $table->string('name');
            $table->string('sku')->nullable();
            $table->string('supplier_reference')->nullable();
            $table->unsignedInteger('quantity_ordered');
            // Cumulative across every receipt.
            $table->unsignedInteger('quantity_received')->default(0);
            // HT (excl. VAT), like every supplier price in the app.
            $table->unsignedInteger('unit_cost_cents');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_order_items');
    }
};
