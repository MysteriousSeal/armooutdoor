<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            // Le tableau de bord filtre en permanence sur ces trois colonnes —
            // statut, archivage, date — et le sélecteur de période ajoute une
            // borne temporelle à chaque requête. Aucune n'était indexée.
            $table->index('status');
            $table->index('archived_at');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropIndex(['status']);
            $table->dropIndex(['archived_at']);
            $table->dropIndex(['created_at']);
        });
    }
};
