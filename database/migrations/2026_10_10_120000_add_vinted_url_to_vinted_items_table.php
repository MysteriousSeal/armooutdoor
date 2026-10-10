<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Where the item is posted on Vinted. Optional: an item is often recorded
 * before its listing exists.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vinted_items', function (Blueprint $table): void {
            $table->string('vinted_url', 500)->nullable()->after('image');
        });
    }

    public function down(): void
    {
        Schema::table('vinted_items', function (Blueprint $table): void {
            $table->dropColumn('vinted_url');
        });
    }
};
