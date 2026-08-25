<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('blog_posts', function (Blueprint $table): void {
            // Facultatif : beaucoup de visuels viennent du fabricant ou du
            // fournisseur et demandent une mention, les nôtres non.
            $table->string('image_credit')->nullable()->after('image');
        });
    }

    public function down(): void
    {
        Schema::table('blog_posts', function (Blueprint $table): void {
            $table->dropColumn('image_credit');
        });
    }
};
