<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // A timestamp rather than a flag, like archived_at: it records when
            // the call was made, which a boolean throws away. Indexed because
            // every figure in the admin now filters on it.
            $table->timestamp('test_marked_at')->nullable()->after('archived_at')->index();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['test_marked_at']);
            $table->dropColumn('test_marked_at');
        });
    }
};
