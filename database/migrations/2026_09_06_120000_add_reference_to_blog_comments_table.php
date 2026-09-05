<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A short reference per comment: ten uppercase hex characters an admin
     * can read off the article page and paste into the back-office search.
     * Stored rather than derived, so the search is a plain indexed WHERE.
     */
    public function up(): void
    {
        Schema::table('blog_comments', function (Blueprint $table) {
            $table->string('reference', 10)->nullable()->after('id');
            $table->unique('reference');
        });

        // Backfill what already exists through the same uniqueness guard
        // the model uses: a collision would abort the deploy otherwise.
        foreach (\App\Models\BlogComment::query()->whereNull('reference')->get(['id', 'created_at']) as $comment) {
            \Illuminate\Support\Facades\DB::table('blog_comments')->where('id', $comment->id)->update([
                'reference' => \App\Models\BlogComment::uniqueReference($comment->id.'|'.$comment->created_at),
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('blog_comments', function (Blueprint $table) {
            $table->dropUnique(['reference']);
            $table->dropColumn('reference');
        });
    }
};
