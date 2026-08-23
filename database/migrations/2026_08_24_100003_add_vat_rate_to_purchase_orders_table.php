<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The rate the supplier's prices included, kept so the order can show what
 * was actually paid. Costs stay stored excl. VAT: this only records how the
 * figures were entered, so the incl. VAT amounts can be shown back.
 *
 * Basis points, like markup_basis_points on products — 20% is 2000.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->unsignedInteger('vat_rate_basis_points')->default(0)->after('shipping_cents');
        });
    }

    public function down(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->dropColumn('vat_rate_basis_points');
        });
    }
};
