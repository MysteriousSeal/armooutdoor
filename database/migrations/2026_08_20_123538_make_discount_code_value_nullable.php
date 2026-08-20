<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A free-relay-shipping code has no amount. Storing 0 would read as "0% off"
 * and render as "-0 %" anywhere that forgets to branch on the type, so the
 * column becomes nullable instead.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('discount_codes', function (Blueprint $table) {
            $table->unsignedInteger('value')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('discount_codes', function (Blueprint $table) {
            $table->unsignedInteger('value')->nullable(false)->change();
        });
    }
};
