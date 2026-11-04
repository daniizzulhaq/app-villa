<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\Response;

/**
 * Bahasa tampilan: pilihan akun → pilihan sesi (halaman login) → default aplikasi.
 */
class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $supported = array_keys(config('propertyme.locales'));
        $locale = $request->user()?->locale ?: $request->session()->get('locale');

        if (! in_array($locale, $supported, true)) {
            $locale = config('app.locale');
        }

        app()->setLocale($locale);
        Carbon::setLocale($locale);

        return $next($request);
    }
}
