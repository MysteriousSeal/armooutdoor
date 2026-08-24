<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table): void {
            // Même traitement que shipping_cents : saisis comme le
            // fournisseur les montre, ramenés au HT via le taux de TVA du
            // bon. La remise est stockée en grandeur positive et retranchée
            // aux totaux, plutôt qu'en négatif.
            $table->unsignedInteger('discount_cents')->default(0)->after('shipping_cents');
            $table->unsignedInteger('additional_costs_cents')->default(0)->after('discount_cents');
        });
    }

    public function down(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table): void {
            $table->dropColumn(['discount_cents', 'additional_costs_cents']);
        });
    }
};
