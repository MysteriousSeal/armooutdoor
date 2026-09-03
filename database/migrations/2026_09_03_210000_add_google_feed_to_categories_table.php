<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Whether the category's products belong in the Google Merchant feed.
     * On by default: the feed opts categories out, not in — Google's policy
     * refusals (replicas, ammunition) are the reason this switch exists.
     */
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->boolean('google_feed')->default(true)->after('sort_order');
        });
    }

    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->dropColumn('google_feed');
        });
    }
};
