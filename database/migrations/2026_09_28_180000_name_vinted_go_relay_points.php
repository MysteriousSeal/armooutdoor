<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Every Vinted Go order's relay point is named "Locker Vinted Go". Orders
 * saved before that rule got whatever was typed, the customer's own name
 * among them; their name is set here, the rest of the address kept.
 */
return new class extends Migration
{
    public function up(): void
    {
        $carrierId = DB::table('carriers')->where('slug', 'vinted-go')->value('id');

        if ($carrierId === null) {
            return;
        }

        DB::table('orders')
            ->where('carrier_id', $carrierId)
            ->whereNotNull('relay_snapshot')
            ->orderBy('id')
            ->each(function (object $order): void {
                $relay = json_decode((string) $order->relay_snapshot, true);

                if (! is_array($relay) || ($relay['name'] ?? null) === 'Locker Vinted Go') {
                    return;
                }

                $relay['name'] = 'Locker Vinted Go';

                DB::table('orders')->where('id', $order->id)->update(['relay_snapshot' => json_encode($relay)]);
            });
    }

    public function down(): void
    {
        // The names typed before are not kept: nothing to put back.
    }
};
