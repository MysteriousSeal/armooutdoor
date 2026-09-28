<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A carrier the shop uses but never offers: Vinted Go, for parcels sold on
 * Vinted and shipped with its label. `manual_only` keeps it off checkout
 * and every customer-facing list; only manual orders can pick it.
 *
 * The row is created here and not only in the seeder, since a deploy runs
 * migrations and never seeds.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('carriers', function (Blueprint $table) {
            $table->boolean('manual_only')->default(false);
        });

        if (DB::table('carriers')->where('slug', 'vinted-go')->doesntExist()) {
            DB::table('carriers')->insert([
                'slug' => 'vinted-go',
                'name' => json_encode(['en' => 'Vinted Go', 'fr' => 'Vinted Go']),
                'description' => json_encode([
                    'en' => 'Vinted Go locker or pickup point, with a Vinted label.',
                    'fr' => 'Casier ou point de retrait Vinted Go, avec une étiquette Vinted.',
                ]),
                'eta' => json_encode(['en' => '3–5 days', 'fr' => '3–5 jours']),
                'method' => 'relay',
                'price_cents' => 0,
                'sort_order' => 6,
                'active' => true,
                'manual_only' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('carriers')->where('slug', 'vinted-go')->delete();

        Schema::table('carriers', function (Blueprint $table) {
            $table->dropColumn('manual_only');
        });
    }
};
