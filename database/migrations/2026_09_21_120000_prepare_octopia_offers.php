<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Offers, the second half of selling on Cdiscount: a product sheet says what
 * an article is, an offer says at what price, in what quantity, delivered how.
 *
 * What a seller decides about them (the delivery costs, the preparation time,
 * a markup) is kept beside the product's category answers. And a submission
 * is now of one of two kinds, since offers go to Octopia in packages of their
 * own and come back with a report of their own.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cdiscount_listings', function (Blueprint $table) {
            $table->json('offer')->nullable();
        });

        Schema::table('octopia_submissions', function (Blueprint $table) {
            $table->string('kind')->default('products');
        });
    }

    public function down(): void
    {
        Schema::table('octopia_submissions', function (Blueprint $table) {
            $table->dropColumn('kind');
        });

        Schema::table('cdiscount_listings', function (Blueprint $table) {
            $table->dropColumn('offer');
        });
    }
};
