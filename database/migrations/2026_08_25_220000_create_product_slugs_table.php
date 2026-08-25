<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * L'histoire des adresses d'un produit.
 *
 * Changer un slug cassait toutes les adresses déjà en circulation : liens
 * partagés, pages indexées, annonces de place de marché. Chaque slug qu'un
 * produit a porté reste ici, celui d'aujourd'hui compris, marqué actif. Les
 * anciens servent à rediriger vers lui.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_slugs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            // Unique dans toute la table : un slug ne désigne qu'un produit,
            // hier comme aujourd'hui, sans quoi une vieille adresse pourrait
            // mener à un autre article.
            $table->string('slug')->unique();
            $table->boolean('is_active')->default(false);
            $table->timestamps();

            $table->index(['product_id', 'is_active']);
        });

        // Le catalogue existant entre avec son adresse du jour.
        DB::table('products')->orderBy('id')->chunk(200, function ($products): void {
            $now = now();

            DB::table('product_slugs')->insert($products->map(fn ($product): array => [
                'product_id' => $product->id,
                'slug' => $product->slug,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ])->all());
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_slugs');
    }
};
