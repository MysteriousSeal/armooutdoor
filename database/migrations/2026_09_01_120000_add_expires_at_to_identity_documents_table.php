<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The day the proof stops being proof.
     *
     * Read off the document itself when a member of staff verifies it: a
     * passport valid until 2031 proves nothing in 2032. Required on a
     * verification and left null on a rejection, a refused document having no
     * validity to run out.
     */
    public function up(): void
    {
        Schema::table('identity_documents', function (Blueprint $table) {
            $table->date('expires_at')->nullable()->after('reviewed_by_user_id');
        });
    }

    public function down(): void
    {
        Schema::table('identity_documents', function (Blueprint $table) {
            $table->dropColumn('expires_at');
        });
    }
};
