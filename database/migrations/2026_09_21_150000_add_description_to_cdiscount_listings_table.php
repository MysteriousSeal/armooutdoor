<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The description a product is sent to Cdiscount with, apart from the shop's.
 *
 * Octopia files a product by reading its description, and the shop's, written
 * for a product page and listing every use of the article, moved products to
 * other categories than the one chosen. This one is written for the category
 * chosen and says what the article is, plainly. Empty, the meta description
 * and then the long description stand in, as before.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cdiscount_listings', function (Blueprint $table) {
            $table->text('description')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('cdiscount_listings', function (Blueprint $table) {
            $table->dropColumn('description');
        });
    }
};
