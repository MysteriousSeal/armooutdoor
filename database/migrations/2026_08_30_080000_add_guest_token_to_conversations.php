<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A guest thread gets a private link instead of an account: the token is
     * the whole key to it, so it is long, random and unique. `closed_at`
     * remembers when the door shut — the link outlives a closure by thirty
     * days, and "when" has to be known for that.
     */
    public function up(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            $table->string('guest_token', 64)->nullable()->unique()->after('email');
            $table->timestamp('closed_at')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            $table->dropColumn(['guest_token', 'closed_at']);
        });
    }
};
