<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * How many photos a listing shows on NaturaBuy, read from its public page
 * since their API does not say, and when it was last read.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('naturabuy_listings', function (Blueprint $table) {
            $table->unsignedSmallInteger('photo_count')->nullable();
            $table->timestamp('photos_checked_at')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::table('naturabuy_listings', function (Blueprint $table) {
            $table->dropIndex(['photos_checked_at']);
            $table->dropColumn(['photo_count', 'photos_checked_at']);
        });
    }
};
