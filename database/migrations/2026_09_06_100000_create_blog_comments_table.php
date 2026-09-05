<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Comments under a blog article, published as they arrive. A guest
     * signs with a pseudonym, an account signs itself, and the shop can
     * answer one level deep - a reply carries its parent and the admin
     * flag that dresses it as the house.
     */
    public function up(): void
    {
        Schema::create('blog_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('blog_post_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('blog_comments')->cascadeOnDelete();
            $table->string('author_name', 40)->nullable();
            $table->boolean('is_admin')->default(false);
            $table->text('body');
            $table->timestamps();

            $table->index(['blog_post_id', 'parent_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('blog_comments');
    }
};
