<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A record of every accounting journal taken out of the admin.
 *
 * One row per download rather than a single date on the month: an accounting
 * book that leaves the building is worth knowing about, and a second copy
 * printed a fortnight later is a different event from the first.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accounting_journal_downloads', function (Blueprint $table): void {
            $table->id();
            // Sales today, purchases when that journal exists.
            $table->string('section')->default('sales');
            // The month the journal covers, as "2026-04".
            $table->string('month', 7);
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            // Every read asks for the latest row of one month of one section.
            $table->index(['section', 'month', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accounting_journal_downloads');
    }
};
