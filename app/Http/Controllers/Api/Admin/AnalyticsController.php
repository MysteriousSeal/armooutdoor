<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\SiteVisit;
use Illuminate\Http\JsonResponse;

class AnalyticsController extends Controller
{
    private const PER_PAGE = 50;

    /** Paginated listing of every site visit, newest first, 50 per page. */
    public function index(): JsonResponse
    {
        $visits = SiteVisit::query()
            ->with('user')
            ->latest('created_at')
            ->paginate(self::PER_PAGE);

        return response()->json([
            'data' => $visits->getCollection()->map(fn (SiteVisit $visit) => $this->serialize($visit)),
            'meta' => [
                'current_page' => $visits->currentPage(),
                'last_page' => $visits->lastPage(),
                'per_page' => $visits->perPage(),
                'total' => $visits->total(),
            ],
        ]);
    }

    private function serialize(SiteVisit $visit): array
    {
        return [
            'id' => $visit->id,
            'path' => $visit->path,
            'referrer' => $visit->referrer,
            'ip_address' => $visit->ip_address,
            'user_agent' => $visit->user_agent,
            'country' => $visit->country,
            'city' => $visit->city,
            'browser' => $visit->browser,
            'device' => $visit->device,
            'os' => $visit->os,
            'is_bot' => $visit->is_bot,
            'user' => $visit->user ? [
                'id' => $visit->user->id,
                'name' => $visit->user->name,
                'email' => $visit->user->email,
            ] : null,
            'created_at' => $visit->created_at,
        ];
    }
}
