<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Each purchase of a Vinted-only item, as its own line.
 *
 * The item keeps its running totals, which the list reads; this table says
 * what they are made of, lot by lot, so a restock has a date and a wrong
 * entry can be taken back.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vinted_item_lots', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('vinted_item_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('quantity');
            $table->unsignedInteger('purchase_total_cents');
            $table->timestamps();
        });

        // What each item already holds becomes its first lot, dated from the
        // day the item was added.
        foreach (DB::table('vinted_items')->orderBy('id')->get() as $item) {
            DB::table('vinted_item_lots')->insert([
                'vinted_item_id' => $item->id,
                'quantity' => $item->lot_quantity,
                'purchase_total_cents' => $item->purchase_total_cents,
                'created_at' => $item->created_at,
                'updated_at' => $item->created_at,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('vinted_item_lots');
    }
};
