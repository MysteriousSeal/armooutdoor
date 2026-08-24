<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('blog_posts', function (Blueprint $table): void {
            $table->id();
            // Un article appartient à une rubrique et une seule ; supprimer une
            // rubrique encore utilisée doit échouer plutôt que d'orpheliner.
            $table->foreignId('blog_category_id')->constrained()->restrictOnDelete();
            $table->string('slug')->unique();
            $table->text('title');
            $table->text('excerpt')->nullable();
            $table->text('body');
            $table->string('image')->nullable();
            $table->string('status')->default('draft');
            // Sert deux fois : l'ordre d'affichage et la visibilité. Une date à
            // venir garde l'article privé jusqu'à l'heure dite.
            $table->timestamp('published_at')->nullable();
            $table->string('meta_title')->nullable();
            $table->string('meta_description')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index('published_at');
            $table->index('blog_category_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('blog_posts');
    }
};
