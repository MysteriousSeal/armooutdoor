<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The Vinted shop's address, beside the NaturaBuy one it belongs with.
 *
 * This table already holds what the shop says about the marketplaces it also
 * sells on. Putting the second address anywhere else would have made two
 * places to look for one kind of fact, and two to keep in step.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('marketplace_settings', function (Blueprint $table): void {
            $table->string('vinted_url')->nullable()->after('naturabuy_on_home');
        });
    }

    public function down(): void
    {
        Schema::table('marketplace_settings', function (Blueprint $table): void {
            $table->dropColumn('vinted_url');
        });
    }
};
