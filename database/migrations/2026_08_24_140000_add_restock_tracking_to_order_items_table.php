<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table): void {
            // Même modèle que quantity_ordered/quantity_received sur les
            // bons de commande : une ligne remboursée peut être remise en
            // rayon partiellement (article abîmé, retour incomplet), donc
            // c'est une quantité cumulée plutôt qu'un simple drapeau.
            $table->unsignedInteger('restocked_quantity')->default(0);
            $table->timestamp('restocked_at')->nullable();
            $table->foreignId('restocked_by_user_id')->nullable()->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('restocked_by_user_id');
            $table->dropColumn(['restocked_quantity', 'restocked_at']);
        });
    }
};
