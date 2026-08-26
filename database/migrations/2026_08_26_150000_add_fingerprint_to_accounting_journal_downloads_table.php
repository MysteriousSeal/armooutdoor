<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What the journal said when it was taken out.
 *
 * A fingerprint of the printed lines, so a later visit can tell whether the
 * month still reads the way the filed copy does. Comparing dates would not
 * do: a tracking number touches an order without changing a figure, and a
 * deleted entry touches nothing at all.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('accounting_journal_downloads', function (Blueprint $table): void {
            $table->string('fingerprint', 64)->nullable()->after('month');
        });
    }

    public function down(): void
    {
        Schema::table('accounting_journal_downloads', function (Blueprint $table): void {
            $table->dropColumn('fingerprint');
        });
    }
};
