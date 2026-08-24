<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_movements', function (Blueprint $table): void {
            $table->id();

            // Le journal ne se lit que depuis la fiche du produit : sans elle
            // une ligne serait inatteignable, donc elle part avec.
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_variant_id')->nullable()->constrained()->nullOnDelete();

            // Une déclinaison peut être retirée du formulaire produit ; son
            // nom reste ici pour que l'historique continue de se lire.
            $table->string('variant_label')->nullable();

            $table->string('reason')->index();

            // delta signé pour le mouvement, les deux absolus pour la
            // photographie : une saisie qui fixe une quantité et un retrait
            // relatif s'enregistrent aussi fidèlement l'un que l'autre.
            $table->integer('delta');
            $table->integer('quantity_before');
            $table->integer('quantity_after');

            $table->string('subject_type')->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();

            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('note')->nullable();

            $table->timestamp('created_at')->nullable();

            $table->index(['product_id', 'created_at']);
            $table->index(['subject_type', 'subject_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
    }
};
