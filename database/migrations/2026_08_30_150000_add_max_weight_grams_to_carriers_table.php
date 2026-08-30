<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Au-delà de ce poids, le transporteur ne prend pas le colis : il reste
     * affiché au checkout mais ne se choisit plus. Null : pas de limite.
     */
    public function up(): void
    {
        Schema::table('carriers', function (Blueprint $table) {
            $table->unsignedInteger('max_weight_grams')->nullable()->after('price_cents');
        });
    }

    public function down(): void
    {
        Schema::table('carriers', function (Blueprint $table) {
            $table->dropColumn('max_weight_grams');
        });
    }
};
