<?php

namespace App\Http\Controllers;

use App\Http\Requests\PasswordUpdateRequest;
use App\Http\Requests\ProfileUpdateRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Profil pribadi — semua role. Role & penugasan villa hanya bisa diubah Manager. */
class ProfileController extends Controller
{
    public function edit(Request $request): View
    {
        $user = $request->user()->load(['assignedVillas:id,name', 'ownedVillas:id,name,owner_id']);

        return view('profile.edit', compact('user'));
    }

    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $request->user()->update($request->validated());

        return back()->with('success', __('Profil tersimpan.'));
    }

    public function password(PasswordUpdateRequest $request): RedirectResponse
    {
        $request->user()->update(['password' => $request->validated('password')]);
        $request->session()->regenerate();

        return back()->with('success', __('Kata sandi berhasil diganti.'));
    }
}
