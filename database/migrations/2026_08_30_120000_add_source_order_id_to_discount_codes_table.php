<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * D'où vient un code offert : la commande qui l'a valu. À ne pas
     * confondre avec orders.discount_code_id, qui dit où un code a été
     * dépensé. Si la commande d'origine disparaît, le code survit — il a
     * peut-être déjà été distribué.
     */
    public function up(): void
    {
        Schema::table('discount_codes', function (Blueprint $table) {
            $table->foreignId('source_order_id')->nullable()->after('user_id')->constrained('orders')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('discount_codes', function (Blueprint $table) {
            $table->dropConstrainedForeignId('source_order_id');
        });
    }
};
