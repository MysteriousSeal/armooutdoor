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
        Schema::create('company_settings', function (Blueprint $table) {
            $table->id();
            $table->string('company_name')->nullable();
            $table->string('legal_form')->nullable();
            $table->string('share_capital')->nullable();
            $table->string('siret')->nullable();
            $table->string('vat_number')->nullable();
            $table->string('address')->nullable();
            $table->string('publication_director')->nullable();
            $table->string('host_name')->nullable();
            $table->string('host_address')->nullable();
            $table->string('host_phone')->nullable();
            $table->string('contact_email')->nullable();
            $table->string('phone')->nullable();
            $table->string('return_address')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('company_settings');
    }
};
