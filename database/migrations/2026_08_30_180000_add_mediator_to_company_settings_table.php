<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Le médiateur de la consommation (L612-1) : nommé dans les CGV dès
     * que la boutique a adhéré à un dispositif. Vide tant que ce n'est pas
     * fait — l'article des CGV s'adapte.
     */
    public function up(): void
    {
        Schema::table('company_settings', function (Blueprint $table) {
            $table->string('mediator_name')->nullable();
            $table->string('mediator_url')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('company_settings', function (Blueprint $table) {
            $table->dropColumn(['mediator_name', 'mediator_url']);
        });
    }
};
