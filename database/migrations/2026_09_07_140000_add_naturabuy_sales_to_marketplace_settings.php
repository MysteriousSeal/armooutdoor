<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('marketplace_settings', function (Blueprint $table) {
            // Optional: the block shows without it, and the line simply
            // does not appear.
            $table->unsignedInteger('naturabuy_sales')->nullable()->after('naturabuy_reviews');
        });
    }

    public function down(): void
    {
        Schema::table('marketplace_settings', function (Blueprint $table) {
            $table->dropColumn('naturabuy_sales');
        });
    }
};
