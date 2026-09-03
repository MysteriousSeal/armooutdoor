<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The category's buying guide: editorial HTML rendered under the product
     * grid. The category pages are the ones commercial searches land on, and
     * a grid with one line of description gives Google nothing to rank.
     */
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->json('guide')->nullable()->after('description');
        });
    }

    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->dropColumn('guide');
        });
    }
};
