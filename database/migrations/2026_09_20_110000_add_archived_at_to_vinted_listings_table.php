<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A draft that has run its course is archived, not deleted: it leaves the
 * working list, keeps its wording and photos, and can come back.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vinted_listings', function (Blueprint $table): void {
            $table->timestamp('archived_at')->nullable()->after('price_cents');
        });
    }

    public function down(): void
    {
        Schema::table('vinted_listings', function (Blueprint $table): void {
            $table->dropColumn('archived_at');
        });
    }
};
