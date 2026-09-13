<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * What a marketplace paid on top of the order itself.
     *
     * Recorded, not yet counted: nothing reads this figure, so it changes no
     * payout and no cost until somebody decides where it belongs.
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->unsignedInteger('marketplace_bonus_cents')->nullable()->after('marketplace_commission_cents');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('marketplace_bonus_cents');
        });
    }
};
