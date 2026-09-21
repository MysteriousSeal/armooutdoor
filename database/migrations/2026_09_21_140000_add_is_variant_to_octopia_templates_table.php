<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Whether Octopia treats the category as a variant category, in which every
 * product carries a variant group reference, even one sold without variants.
 * Null until it has been asked: a category read before this column existed is
 * looked up the first time a product of it is sent.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('octopia_templates', function (Blueprint $table) {
            $table->boolean('is_variant')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('octopia_templates', function (Blueprint $table) {
            $table->dropColumn('is_variant');
        });
    }
};
