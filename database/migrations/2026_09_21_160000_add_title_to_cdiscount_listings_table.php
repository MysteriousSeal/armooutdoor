<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The title a product is sent to Cdiscount with, apart from the shop's name.
 * Empty, the product's name is used, as before.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cdiscount_listings', function (Blueprint $table) {
            $table->string('title', 132)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('cdiscount_listings', function (Blueprint $table) {
            $table->dropColumn('title');
        });
    }
};
