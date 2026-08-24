<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_movements', function (Blueprint $table): void {
            // Une ligne écrite après coup, dont le solde est reconstruit et
            // non observé. C'est aussi ce qui rend le rattrapage rejouable :
            // il ne réécrit que ses propres lignes, jamais un mouvement réel.
            $table->boolean('backfilled')->default(false)->index()->after('note');
        });
    }

    public function down(): void
    {
        Schema::table('stock_movements', function (Blueprint $table): void {
            $table->dropColumn('backfilled');
        });
    }
};
