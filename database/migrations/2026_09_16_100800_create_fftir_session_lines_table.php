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
        Schema::create('fftir_session_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fftir_session_id')->constrained('fftir_sessions')->cascadeOnDelete();
            $table->foreignId('fftir_weapon_id')->constrained('fftir_weapons');
            $table->foreignId('fftir_ammunition_id')->constrained('fftir_ammunitions');
            $table->string('distance');
            $table->unsignedInteger('quantity');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fftir_session_lines');
    }
};
