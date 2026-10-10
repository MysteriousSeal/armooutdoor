<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Each piece of a Vinted-only item sold, with what it went for.
 *
 * One row per piece: the item's stock already says how many are gone, this
 * says when and at what price, which is what a margin is worked out from.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vinted_item_sales', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('vinted_item_id')->constrained()->cascadeOnDelete();
            // Empty for a sale reported before prices were asked for: an
            // unknown price is not a price of zero.
            $table->unsignedInteger('price_cents')->nullable();
            $table->timestamps();
        });

        // The pieces already counted as sold each get their line, without a
        // price, so the history and the stock agree from the first day.
        foreach (DB::table('vinted_items')->whereColumn('quantity', '<', 'lot_quantity')->orderBy('id')->get() as $item) {
            for ($piece = $item->quantity; $piece < $item->lot_quantity; $piece++) {
                DB::table('vinted_item_sales')->insert([
                    'vinted_item_id' => $item->id,
                    'price_cents' => null,
                    'created_at' => $item->updated_at,
                    'updated_at' => $item->updated_at,
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('vinted_item_sales');
    }
};
