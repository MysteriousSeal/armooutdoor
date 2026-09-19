<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Cdiscount, through Octopia's product templates.
     *
     * Octopia hands out one Excel template per category, whose columns are
     * that category's own. The file is kept as it came, macros and all, and
     * the export writes the shop's rows into a copy of it.
     *
     * What the shop answers for a product lives beside the product rather
     * than in it, as the Vinted listing does: it is what one marketplace
     * asks, not what the catalogue knows, and a product sold nowhere else
     * carries none of it.
     */
    public function up(): void
    {
        Schema::create('octopia_templates', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->string('original_filename');
            $table->string('path');
            $table->string('sheet_path');
            $table->unsignedSmallInteger('first_data_row');
            $table->json('fields');
            $table->timestamps();
        });

        Schema::create('cdiscount_listings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('octopia_template_id')->constrained()->cascadeOnDelete();
            // The category's own attributes, and the codes each variant
            // answers for itself rather than once for the product.
            $table->json('values');
            $table->json('per_variant');
            $table->timestamps();
        });

        Schema::create('cdiscount_listing_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cdiscount_listing_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_variant_id')->constrained()->cascadeOnDelete();
            $table->json('values');
            $table->timestamps();
            $table->unique(['cdiscount_listing_id', 'product_variant_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cdiscount_listing_variants');
        Schema::dropIfExists('cdiscount_listings');
        Schema::dropIfExists('octopia_templates');
    }
};
