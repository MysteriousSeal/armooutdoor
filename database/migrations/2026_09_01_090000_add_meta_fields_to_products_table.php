<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * What a search result shows, when what the page says is the wrong length
     * for one.
     *
     * Product names here run past a hundred characters and descriptions past a
     * thousand; a result shows around sixty and a hundred and sixty. Both are
     * derived from the page by default — the name as it stands, the
     * description cut at its last whole sentence — and these are where a
     * better one is written for the products that earn the effort. Null: the
     * derived one is used, as before.
     *
     * Named for what the blog already calls them, so the two read alike.
     */
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('meta_title', 70)->nullable()->after('name');
            $table->string('meta_description', 255)->nullable()->after('meta_title');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['meta_title', 'meta_description']);
        });
    }
};
