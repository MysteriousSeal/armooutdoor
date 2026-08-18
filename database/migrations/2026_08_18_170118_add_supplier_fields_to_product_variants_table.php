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
        Schema::table('product_variants', function (Blueprint $table) {
            $table->foreignId('supplier_id')->nullable()->after('product_id')->constrained()->nullOnDelete();
            $table->boolean('available_at_supplier')->default(true)->after('supplier_id');
            $table->string('supplier_product_url')->nullable()->after('available_at_supplier');
            $table->string('supplier_reference')->nullable()->after('supplier_product_url');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('product_variants', function (Blueprint $table) {
            $table->dropConstrainedForeignId('supplier_id');
            $table->dropColumn(['available_at_supplier', 'supplier_product_url', 'supplier_reference']);
        });
    }
};
