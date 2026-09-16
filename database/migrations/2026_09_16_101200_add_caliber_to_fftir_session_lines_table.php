<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('fftir_session_lines', function (Blueprint $table) {
            $table->foreignId('fftir_ammunition_id')->nullable()->change();
            $table->string('caliber')->nullable()->after('fftir_ammunition_id');
        });

        // Every existing line was logged against a specific ammunition, so
        // its caliber is simply that ammunition's, carried over so the
        // column reads the same whether or not one is picked.
        DB::table('fftir_session_lines')->get()->each(function ($line) {
            $ammunition = DB::table('fftir_ammunitions')->find($line->fftir_ammunition_id);

            if ($ammunition !== null) {
                DB::table('fftir_session_lines')
                    ->where('id', $line->id)
                    ->update(['caliber' => $ammunition->caliber]);
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('fftir_session_lines', function (Blueprint $table) {
            $table->dropColumn('caliber');
            $table->foreignId('fftir_ammunition_id')->nullable(false)->change();
        });
    }
};
