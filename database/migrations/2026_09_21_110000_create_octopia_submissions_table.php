<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What was sent to Octopia, and what Octopia said about it.
 *
 * Octopia takes a batch of products and answers with a package id; whether
 * each product was refused or integrated is only known by asking for the
 * package's report afterwards. The batch is kept with its report, so the page
 * can say what became of each line instead of leaving it to be found out in
 * Octopia's own back office.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('octopia_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('octopia_template_id')->constrained()->cascadeOnDelete();
            $table->string('package_id')->index();
            // The lines as they left: gtin, reference and title, to name them
            // in the report without reading them from the catalogue again.
            $table->json('lines');
            // Octopia's answer per product, as of the last time it was asked.
            $table->json('report')->nullable();
            $table->timestamp('checked_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('octopia_submissions');
    }
};
