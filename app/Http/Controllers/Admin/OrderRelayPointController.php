<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateOrderRelayPointRequest;
use App\Models\AdminActivityLog;
use App\Models\Order;
use Illuminate\Http\RedirectResponse;

/**
 * Sets a Vinted Go order's relay point by hand. Vinted picks the locker and
 * the shop learns it late, often from the label, so it is filled in or
 * corrected after the order is placed.
 */
class OrderRelayPointController extends Controller
{
    public function update(UpdateOrderRelayPointRequest $request, Order $order): RedirectResponse
    {
        abort_unless($order->relayPointIsEditable(), 404);

        $order->update([
            'relay_snapshot' => [
                // Kept from the old snapshot if there was one: the rest of the
                // shop reads these keys even when they are empty.
                'slug' => $order->relay_snapshot['slug'] ?? null,
                'name' => $request->validated('relay_name'),
                'line1' => $request->validated('relay_line1'),
                'postal_code' => $request->validated('relay_postal_code'),
                'city' => $request->validated('relay_city'),
                'country' => $order->relay_snapshot['country'] ?? ($order->address_snapshot['country'] ?? 'FR'),
                'hours' => $order->relay_snapshot['hours'] ?? null,
            ],
        ]);

        AdminActivityLog::record('order.relay_point_updated', $order, 'Updated the relay point for order '.$order->number);

        return back()->with('status', 'Relay point saved.');
    }
}
