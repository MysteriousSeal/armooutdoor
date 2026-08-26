<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * VAT on a hand-written entry.
 *
 * A purchase is recorded as it reads on the supplier's invoice — the total
 * paid and the rate — and the journal works the rest out. Sales leave it
 * null: their VAT is settled elsewhere.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('accounting_entries', function (Blueprint $table): void {
            // Basis points, like every other rate in the shop: 20% is 2000.
            $table->integer('vat_rate_basis_points')->nullable()->after('total_cents');
        });
    }

    public function down(): void
    {
        Schema::table('accounting_entries', function (Blueprint $table): void {
            $table->dropColumn('vat_rate_basis_points');
        });
    }
};
