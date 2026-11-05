<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A variant goes from three photos to eight: `image` stays the main one,
     * and these follow image_2 and image_3 on the product page.
     */
    public function up(): void
    {
        Schema::table('product_variants', function (Blueprint $table) {
            $table->string('image_4')->nullable()->after('image_3');
            $table->string('image_5')->nullable()->after('image_4');
            $table->string('image_6')->nullable()->after('image_5');
            $table->string('image_7')->nullable()->after('image_6');
            $table->string('image_8')->nullable()->after('image_7');
        });
    }

    public function down(): void
    {
        Schema::table('product_variants', function (Blueprint $table) {
            $table->dropColumn(['image_4', 'image_5', 'image_6', 'image_7', 'image_8']);
        });
    }
};
