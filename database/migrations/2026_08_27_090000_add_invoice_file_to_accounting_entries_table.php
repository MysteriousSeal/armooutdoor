<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The supplier's invoice, attached to the line it paid for.
 *
 * Only the path is kept. The file itself lives on the private disk, out of
 * the public directory: an accounting document is nobody's business but the
 * owner's, and a file under public/ is readable by anyone who guesses its
 * name.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('accounting_entries', function (Blueprint $table): void {
            $table->string('invoice_path')->nullable()->after('invoice_number');
        });
    }

    public function down(): void
    {
        Schema::table('accounting_entries', function (Blueprint $table): void {
            $table->dropColumn('invoice_path');
        });
    }
};
