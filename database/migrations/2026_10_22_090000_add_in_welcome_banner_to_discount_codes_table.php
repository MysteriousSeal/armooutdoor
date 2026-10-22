<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Marks the one code the homepage banner advertises, so the banner
     * always shows what the code really takes off.
     */
    public function up(): void
    {
        Schema::table('discount_codes', function (Blueprint $table) {
            $table->boolean('in_welcome_banner')->default(false)->after('max_uses_per_customer');
        });
    }

    public function down(): void
    {
        Schema::table('discount_codes', function (Blueprint $table) {
            $table->dropColumn('in_welcome_banner');
        });
    }
};
