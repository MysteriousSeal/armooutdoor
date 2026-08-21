<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            // La marge voulue sur ce produit, en points de base : 3000 = 30 %.
            // Entier, comme tous les montants de la boutique, pour que 32,5 %
            // reste exact sans passer par un flottant.
            $table->unsignedInteger('markup_basis_points')->nullable()->after('supplier_price_cents');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('markup_basis_points');
        });
    }
};
