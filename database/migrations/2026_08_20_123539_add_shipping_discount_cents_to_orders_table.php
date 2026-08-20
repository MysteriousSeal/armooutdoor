<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Shipping waived by a discount code. Kept apart from discount_cents, which
 * means "off the goods" and feeds the orders cost KPIs — folding a shipping
 * waiver in there would misreport the margin on every order that used one.
 *
 * shipping_cents keeps the real carrier price so the invoice can still show
 * what delivery would have cost.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->unsignedInteger('shipping_discount_cents')->default(0)->after('shipping_cents');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('shipping_discount_cents');
        });
    }
};
