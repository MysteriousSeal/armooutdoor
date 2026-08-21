<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            // Le prix d'achat hors taxe chez le fournisseur, en centimes comme
            // tous les montants de la boutique. Nullable : beaucoup de produits
            // n'ont pas de fournisseur renseigné.
            $table->unsignedInteger('supplier_price_cents')->nullable()->after('supplier_reference');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('supplier_price_cents');
        });
    }
};
