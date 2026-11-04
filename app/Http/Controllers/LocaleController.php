<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class LocaleController extends Controller
{
    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'locale' => ['required', Rule::in(array_keys(config('propertyme.locales')))],
        ]);

        $request->session()->put('locale', $data['locale']);
        $request->user()?->forceFill(['locale' => $data['locale']])->saveQuietly();

        return back();
    }
}
