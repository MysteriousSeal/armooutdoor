<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What a printed label says about a product.
 *
 * Kept apart from the catalogue's own name and description: a label is read on
 * a package, in a few words, and rarely says what a product page says. All
 * four are optional — a label prints what it has been given and nothing more.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->string('label_title')->nullable()->after('gtin');
            $table->string('label_subtitle')->nullable()->after('label_title');
            $table->string('label_composition', 500)->nullable()->after('label_subtitle');
            $table->string('label_mention', 500)->nullable()->after('label_composition');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->dropColumn(['label_title', 'label_subtitle', 'label_composition', 'label_mention']);
        });
    }
};
