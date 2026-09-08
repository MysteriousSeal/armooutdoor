<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * L'annonce Vinted d'un produit : son titre, son texte, son prix, ses photos.
 *
 * Une table à part plutôt que des colonnes sur `products` : ce que la
 * boutique vend et ce qu'elle raconte sur une place de marché sont deux
 * choses, et la seconde recommencera à zéro pour la place suivante. Une
 * ligne par produit — l'unicité le dit, pour qu'aucun écran n'ait à choisir
 * entre deux brouillons.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vinted_listings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('title')->nullable();
            $table->text('description')->nullable();
            // En centimes comme partout ailleurs, et facultatif : une annonce
            // en cours d'écriture n'a pas encore de prix.
            $table->unsignedInteger('price_cents')->nullable();
            $table->timestamps();
        });

        /*
         * Les photos de l'annonce sont les siennes. Sur Vinted on photographie
         * l'article porté, posé, dans la lumière du salon ; ce ne sont pas les
         * images du catalogue, et les lier aurait forcé les deux à changer
         * ensemble.
         */
        Schema::create('vinted_listing_images', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('vinted_listing_id')->constrained()->cascadeOnDelete();
            $table->string('image');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['vinted_listing_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vinted_listing_images');
        Schema::dropIfExists('vinted_listings');
    }
};
