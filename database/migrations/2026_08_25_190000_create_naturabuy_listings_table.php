<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('naturabuy_listings', function (Blueprint $table): void {
            $table->id();
            // L'identifiant de l'annonce chez eux : c'est lui qui décide si une
            // synchronisation crée une ligne ou en met une à jour.
            $table->unsignedBigInteger('naturabuy_id')->unique();
            $table->string('title');
            $table->string('url')->nullable();
            $table->unsignedInteger('category')->nullable();
            // Leur « internalcode » porte notre SKU. Indexé parce que c'est par
            // là qu'un rapprochement avec le catalogue passera un jour.
            $table->string('internalcode')->nullable()->index();
            $table->integer('price_cents')->default(0);
            $table->integer('oldprice_cents')->nullable();
            $table->integer('quantity')->default(0);
            $table->integer('physical_quantity')->default(0);
            $table->boolean('out_of_stock')->default(false);
            $table->boolean('out_of_stock_available')->default(false);
            $table->boolean('closed')->default(false);
            $table->json('variants')->nullable();
            // Quand cette ligne a été vue pour la dernière fois chez eux.
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();

            $table->index('closed');
            $table->index('out_of_stock');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('naturabuy_listings');
    }
};
