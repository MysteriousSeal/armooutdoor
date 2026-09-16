<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::rename('ammunition_stock_movements', 'fftir_ammunition_stock_movements');
        Schema::rename('ammunitions', 'fftir_ammunitions');
        Schema::rename('weapons', 'fftir_weapons');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::rename('fftir_weapons', 'weapons');
        Schema::rename('fftir_ammunitions', 'ammunitions');
        Schema::rename('fftir_ammunition_stock_movements', 'ammunition_stock_movements');
    }
};
