<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('marketplace_settings', function (Blueprint $table) {
            $table->id();
            // The shop's own seller page, its standing there, and how many
            // buyers that standing rests on. Typed by hand: nothing is read
            // back from the marketplace.
            $table->string('naturabuy_url')->nullable();
            $table->unsignedSmallInteger('naturabuy_rating_tenths')->nullable();
            $table->unsignedInteger('naturabuy_reviews')->nullable();
            $table->boolean('naturabuy_on_home')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketplace_settings');
    }
};
