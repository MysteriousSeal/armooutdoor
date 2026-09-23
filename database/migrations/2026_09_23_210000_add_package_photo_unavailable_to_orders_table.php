<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * When, and by whom, an order was marked as having no package photo to
 * give: it then leaves the Missing package picture tab.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->timestamp('package_photo_unavailable_at')->nullable();
            $table->foreignId('package_photo_unavailable_by_user_id')->nullable()->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('package_photo_unavailable_by_user_id');
            $table->dropColumn('package_photo_unavailable_at');
        });
    }
};
