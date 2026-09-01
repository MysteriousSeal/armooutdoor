<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_settings', function (Blueprint $table) {
            $table->id();
            // At or below this quantity a product reads « Derniers stocks
            // disponibles ». Default 2 is the value the code hardcoded.
            $table->unsignedSmallInteger('low_stock_threshold')->default(2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_settings');
    }
};
