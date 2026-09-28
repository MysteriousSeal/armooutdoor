<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Carrier;
use Illuminate\Http\JsonResponse;

/**
 * The carriers and their ids, since ids differ between databases and a
 * draft order needs the right one. Every carrier is listed, inactive and
 * manual-only ones included, with flags saying which can be used where.
 */
class CarrierController extends Controller
{
    public function index(): JsonResponse
    {
        $carriers = Carrier::query()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(fn (Carrier $carrier): array => [
                'id' => $carrier->id,
                'slug' => $carrier->slug,
                'name' => $carrier->localizedName(),
                'method' => $carrier->method->value,
                'price_cents' => $carrier->price_cents,
                'active' => $carrier->active,
                // Never offered at checkout; usable by manual orders only.
                'manual_only' => $carrier->manual_only,
            ]);

        return response()->json(['data' => $carriers]);
    }
}
