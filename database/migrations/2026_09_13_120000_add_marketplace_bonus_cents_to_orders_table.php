<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * What a marketplace paid on top of the order itself.
     *
     * The figure lives nowhere the shop can read, so it is taken off the
     * statement and typed in. It counts as money received, never as a cost:
     * it lifts what the order perceived and stays out of the recorded costs.
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
