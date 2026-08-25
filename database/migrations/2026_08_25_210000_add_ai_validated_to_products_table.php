<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Marque un produit dont la fiche a été relue et validée.
 *
 * Faux par défaut : un produit existant n'a rien été relu, et un nouveau
 * commence par ne pas l'être.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->boolean('ai_validated')->default(false)->after('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->dropColumn('ai_validated');
        });
    }
};
