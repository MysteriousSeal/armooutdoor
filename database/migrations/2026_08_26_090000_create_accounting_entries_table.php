<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Les écritures saisies à la main.
 *
 * Tout n'arrive pas par une commande de la boutique : une prestation, une
 * vente de la main à la main, une réparation. Elles se rangent dans le même
 * tableau que les commandes, à leur date.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accounting_entries', function (Blueprint $table): void {
            $table->id();
            // Ventes aujourd'hui, achats plus tard : la colonne évite d'avoir
            // à dédoubler la table le jour venu.
            $table->string('section')->default('sales');
            $table->date('entered_on');
            $table->string('invoice_number')->nullable();
            $table->string('client')->nullable();
            $table->string('channel')->nullable();
            $table->string('type')->default('stock_sale');
            $table->integer('total_cents')->default(0);
            $table->integer('fees_cents')->default(0);
            $table->string('payment_method')->default('bank_wire');
            $table->string('remark')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            // Le tableau d'un mois lit toujours ces deux colonnes ensemble.
            $table->index(['section', 'entered_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accounting_entries');
    }
};
