<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A variant holds up to three photos: `image` stays the main one, read
     * everywhere a single picture stands for the variant, and these two
     * follow it on the product page.
     */
    public function up(): void
    {
        Schema::table('product_variants', function (Blueprint $table) {
            $table->string('image_2')->nullable()->after('image');
            $table->string('image_3')->nullable()->after('image_2');
        });
    }

    public function down(): void
    {
        Schema::table('product_variants', function (Blueprint $table) {
            $table->dropColumn(['image_2', 'image_3']);
        });
    }
};
