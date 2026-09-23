<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What an off-catalogue line cost the shop, per unit, incl. VAT. A catalogue
 * line draws its cost from purchase orders; a line with no product has
 * nothing to draw from, so it carries its own, or none.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->unsignedInteger('unit_cost_incl_vat_cents')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropColumn('unit_cost_incl_vat_cents');
        });
    }
};
