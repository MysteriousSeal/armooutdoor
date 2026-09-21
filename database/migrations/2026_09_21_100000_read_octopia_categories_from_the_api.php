<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A category is read from Octopia's API: there is no Excel file, sheet or first
 * row to keep any more. What is kept is the category and what it asks, stamped
 * with when Octopia was last asked.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('octopia_templates', function (Blueprint $table) {
            $table->timestamp('synced_at')->nullable();
            $table->dropColumn(['original_filename', 'path', 'sheet_path', 'first_data_row']);
        });
    }

    public function down(): void
    {
        Schema::table('octopia_templates', function (Blueprint $table) {
            $table->dropColumn('synced_at');
            $table->string('original_filename')->nullable();
            $table->string('path')->nullable();
            $table->string('sheet_path')->nullable();
            $table->unsignedSmallInteger('first_data_row')->nullable();
        });
    }
};
