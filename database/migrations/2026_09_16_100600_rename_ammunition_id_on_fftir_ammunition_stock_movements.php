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
        Schema::table('fftir_ammunition_stock_movements', function (Blueprint $table) {
            $table->renameColumn('ammunition_id', 'fftir_ammunition_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('fftir_ammunition_stock_movements', function (Blueprint $table) {
            $table->renameColumn('fftir_ammunition_id', 'ammunition_id');
        });
    }
};
