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
            $table->foreignId('package_type_id')->nullable()->after('tracking_carrier_id')->constrained('package_types')->nullOnDelete();
            $table->string('package_type_name')->nullable()->after('package_type_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('package_type_id');
            $table->dropColumn('package_type_name');
        });
    }
};
