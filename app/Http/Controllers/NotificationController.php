<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class NotificationController extends Controller
{
    public function index(Request $request): View
    {
        return view('notifications.index', [
            'notifications' => $request->user()->notifications()->paginate(20),
        ]);
    }

    /** Buka notifikasi: tandai dibaca lalu arahkan ke halaman terkait. */
    public function open(Request $request, string $id): RedirectResponse
    {
        $n = $request->user()->notifications()->findOrFail($id);
        $n->markAsRead();

        $url = $n->data['url'] ?? route('notifications.index');
        // Hanya izinkan redirect ke aplikasi sendiri
        if (! str_starts_with($url, url('/'))) {
            $url = route('notifications.index');
        }

        return redirect($url);
    }

    public function readAll(Request $request): RedirectResponse
    {
        $request->user()->unreadNotifications->markAsRead();

        return back()->with('success', __('Semua notifikasi ditandai sudah dibaca.'));
    }
}
