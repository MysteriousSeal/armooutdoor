<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('addresses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('label')->nullable();
            $table->string('first_name');
            $table->string('last_name');
            $table->string('line1');
            $table->string('line2')->nullable();
            $table->string('postal_code');
            $table->string('city');
            $table->string('country', 2);
            $table->string('phone')->nullable();
            $table->boolean('is_default')->default(false);
            $table->timestamps();
        });

        Schema::create('carriers', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->json('name');
            $table->json('description');
            $table->json('eta');
            $table->string('method');
            $table->unsignedInteger('price_cents');
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('relay_points', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name');
            $table->string('line1');
            $table->string('postal_code');
            $table->string('city');
            $table->string('country', 2)->default('FR');
            $table->string('hours')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('number')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('status');
            $table->foreignId('address_id')->nullable()->constrained()->nullOnDelete();
            $table->json('address_snapshot');
            $table->foreignId('carrier_id')->nullable()->constrained()->nullOnDelete();
            $table->string('carrier_method');
            $table->json('carrier_snapshot');
            $table->foreignId('relay_point_id')->nullable()->constrained()->nullOnDelete();
            $table->json('relay_snapshot')->nullable();
            $table->unsignedInteger('subtotal_cents');
            $table->unsignedInteger('shipping_cents');
            $table->unsignedInteger('total_cents');
            $table->timestamps();
        });

        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->string('product_slug');
            $table->json('name');
            $table->string('image');
            $table->unsignedInteger('unit_price_cents');
            $table->unsignedInteger('quantity');
            $table->unsignedInteger('line_cents');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');
        Schema::dropIfExists('relay_points');
        Schema::dropIfExists('carriers');
        Schema::dropIfExists('addresses');
    }
};
