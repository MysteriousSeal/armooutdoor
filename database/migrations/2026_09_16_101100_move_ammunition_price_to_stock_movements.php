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
        Schema::table('fftir_ammunition_stock_movements', function (Blueprint $table) {
            $table->unsignedInteger('total_price_cents')->nullable()->after('quantity_after');
        });

        // Fold the price that used to live on the ammunition itself into its
        // earliest stock-in movement, so the weighted average this replaces
        // it with starts from the same number instead of resetting to N/A.
        foreach (DB::table('fftir_ammunitions')->get() as $ammunition) {
            if ($ammunition->unit_price_cents === null) {
                continue;
            }

            $firstStockIn = DB::table('fftir_ammunition_stock_movements')
                ->where('fftir_ammunition_id', $ammunition->id)
                ->where('delta', '>', 0)
                ->orderBy('created_at')
                ->first();

            if ($firstStockIn === null) {
                continue;
            }

            DB::table('fftir_ammunition_stock_movements')
                ->where('id', $firstStockIn->id)
                ->update(['total_price_cents' => $ammunition->unit_price_cents * $firstStockIn->delta]);
        }

        Schema::table('fftir_ammunitions', function (Blueprint $table) {
            $table->dropColumn('unit_price_cents');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('fftir_ammunitions', function (Blueprint $table) {
            $table->unsignedInteger('unit_price_cents')->nullable()->after('denomination');
        });

        foreach (DB::table('fftir_ammunitions')->get() as $ammunition) {
            $priced = DB::table('fftir_ammunition_stock_movements')
                ->where('fftir_ammunition_id', $ammunition->id)
                ->where('delta', '>', 0)
                ->whereNotNull('total_price_cents')
                ->get();

            $totalRounds = $priced->sum('delta');

            if ($totalRounds === 0) {
                continue;
            }

            DB::table('fftir_ammunitions')
                ->where('id', $ammunition->id)
                ->update(['unit_price_cents' => (int) round($priced->sum('total_price_cents') / $totalRounds)]);
        }

        Schema::table('fftir_ammunition_stock_movements', function (Blueprint $table) {
            $table->dropColumn('total_price_cents');
        });
    }
};
