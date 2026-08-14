<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PreferenceController extends Controller
{
    public function theme(Request $request): JsonResponse
    {
        $theme = $request->validate([
            'theme' => ['required', 'in:light,dark'],
        ])['theme'];

        return response()
            ->json(['theme' => $theme])
            ->cookie('theme', $theme, 60 * 24 * 365);
    }
}
