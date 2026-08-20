<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Drafts can no longer be archived — they are deleted instead. A handful
     * were archived while that was still allowed, and they would otherwise sit
     * in a state the rules no longer produce, counted by nothing and reachable
     * only through the Drafts tab.
     */
    public function up(): void
    {
        DB::table('orders')->where('status', 'draft')->whereNotNull('archived_at')->update(['archived_at' => null]);
    }

    public function down(): void
    {
        // Which drafts were archived is not recorded anywhere, so this cannot
        // be undone. Nothing was deleted, so nothing is lost either.
    }
};
