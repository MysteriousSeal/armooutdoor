<?php

namespace App\Http\Controllers;

use App\Services\AddressSearchClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Suggestions for the address fields of the checkout and of « Mes adresses ». */
class AddressSearchController extends Controller
{
    public function __invoke(Request $request, AddressSearchClient $client): JsonResponse
    {
        $query = mb_substr((string) $request->query('q', ''), 0, 120);

        return response()->json(['results' => $client->search($query)]);
    }
}
