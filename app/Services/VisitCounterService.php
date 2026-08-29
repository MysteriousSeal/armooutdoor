<?php

namespace App\Services;

use App\Models\SiteVisit;
use Illuminate\Http\Request;

class VisitCounterService
{
    public function __construct(private GeoIpService $geoIp) {}

    public function record(Request $request): void
    {
        $geo = $this->geoIp->resolve($request);

        SiteVisit::create([
            'path' => mb_substr('/'.ltrim($request->path(), '/'), 0, 2048),
            'user_id' => $request->user()?->id,
            'ip_address' => $request->ip(),
            'user_agent' => mb_substr((string) $request->userAgent(), 0, 512) ?: null,
            'referrer' => mb_substr((string) $request->header('referer'), 0, 2048) ?: null,
            'country' => $geo['country'],
            'city' => $geo['city'],
        ]);
    }
}
