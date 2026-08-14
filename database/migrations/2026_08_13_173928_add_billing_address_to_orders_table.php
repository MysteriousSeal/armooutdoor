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
            $table->foreignId('billing_address_id')->nullable()->after('address_snapshot')->constrained('addresses')->nullOnDelete();
            $table->json('billing_address_snapshot')->nullable()->after('billing_address_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('billing_address_id');
            $table->dropColumn('billing_address_snapshot');
        });
    }
};
