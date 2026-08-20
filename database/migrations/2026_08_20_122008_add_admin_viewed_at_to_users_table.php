<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * When an admin last opened this customer's profile. Shop-wide rather than
 * per-admin, matching how conversations track admin reads.
 *
 * Deliberately not backfilled: existing customers start unviewed, so the
 * badge shows every customer nobody has looked at yet.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('admin_viewed_at')->nullable()->after('notes');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('admin_viewed_at');
        });
    }
};
