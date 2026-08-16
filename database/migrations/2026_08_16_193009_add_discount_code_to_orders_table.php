<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('discount_code_id')->nullable()->after('shipping_cents')->constrained()->nullOnDelete();
            $table->json('discount_code_snapshot')->nullable()->after('discount_code_id');
            $table->unsignedInteger('discount_cents')->default(0)->after('discount_code_snapshot');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('discount_code_id');
            $table->dropColumn(['discount_code_snapshot', 'discount_cents']);
        });
    }
};
