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
        Schema::table('fftir_ammunitions', function (Blueprint $table) {
            // Price of a single round, not of the whole box.
            $table->unsignedInteger('unit_price_cents')->nullable()->after('denomination');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('fftir_ammunitions', function (Blueprint $table) {
            $table->dropColumn('unit_price_cents');
        });
    }
};
