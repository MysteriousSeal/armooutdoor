<?php

namespace App\Support;

use Illuminate\Http\Request;

class ThemePreference
{
    public static function resolve(Request $request): string
    {
        return $request->cookie('theme') === 'dark' ? 'dark' : 'light';
    }
}
