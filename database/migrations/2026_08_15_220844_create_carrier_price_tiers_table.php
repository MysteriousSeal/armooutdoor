<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('carrier_price_tiers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('carrier_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('min_weight_grams');
            $table->unsignedInteger('price_cents');
            $table->timestamps();

            $table->unique(['carrier_id', 'min_weight_grams']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('carrier_price_tiers');
    }
};
