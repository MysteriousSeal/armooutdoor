<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The soft curtain between delete and keep: a hidden comment leaves
     * the public thread but stays in the back office, timestamped, ready
     * to come back if hiding was the wrong call.
     */
    public function up(): void
    {
        Schema::table('blog_comments', function (Blueprint $table) {
            $table->timestamp('hidden_at')->nullable()->after('body');
        });
    }

    public function down(): void
    {
        Schema::table('blog_comments', function (Blueprint $table) {
            $table->dropColumn('hidden_at');
        });
    }
};
