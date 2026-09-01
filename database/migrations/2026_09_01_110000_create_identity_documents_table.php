<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The proof of age the CGV reserve the right to ask for.
     *
     * The row outlives the file on purpose. Once a member of staff has looked
     * at a document and said yes or no, the document itself is deleted and
     * only the verdict and its date remain: the shop keeps proof that it
     * checked without keeping a copy of somebody's passport, which is what
     * the CNIL asks of anyone collecting these.
     */
    public function up(): void
    {
        Schema::create('identity_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 20);
            $table->string('original_name');
            $table->string('mime', 100);
            $table->unsignedInteger('size_bytes');
            // Null once the file is gone; the row stays to say it was checked.
            $table->string('path')->nullable();
            $table->string('status', 20)->default('pending');
            $table->timestamp('reviewed_at')->nullable();
            $table->foreignId('reviewed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('review_note')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('identity_documents');
    }
};
