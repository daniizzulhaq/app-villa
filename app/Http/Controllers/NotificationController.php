<?php

namespace App\Http\Controllers;

use App\Enums\NotificationCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class NotificationController extends Controller
{
    public function index(Request $request): View
    {
        $category = $this->category($request);
        $unreadCounts = [];
        foreach (NotificationCategory::cases() as $inbox) {
            $unreadCounts[$inbox->value] = $inbox->notifications($request->user())->whereNull('read_at')->count();
        }

        return view('notifications.index', [
            'category' => $category,
            'unreadCounts' => $unreadCounts,
            'notifications' => $category->notifications($request->user())->paginate(20)->withQueryString(),
        ]);
    }

    /** Buka notifikasi: tandai dibaca lalu arahkan ke halaman terkait. */
    public function open(Request $request, string $id): RedirectResponse
    {
        $n = $request->user()->notifications()->findOrFail($id);
        $n->markAsRead();

        $fallback = route('notifications.index', ['category' => NotificationCategory::forType($n->type)->value]);
        $url = $n->data['url'] ?? $fallback;
        // Hanya izinkan redirect ke aplikasi sendiri
        if (! str_starts_with($url, url('/'))) {
            $url = $fallback;
        }

        return redirect($url);
    }

    public function readAll(Request $request): RedirectResponse
    {
        $this->category($request)->notifications($request->user())->whereNull('read_at')->update(['read_at' => now()]);

        return back()->with('success', __('Semua notifikasi ditandai sudah dibaca.'));
    }

    private function category(Request $request): NotificationCategory
    {
        $data = $request->validate(['category' => ['nullable', Rule::enum(NotificationCategory::class)]]);

        return NotificationCategory::from($data['category'] ?? NotificationCategory::General->value);
    }
}
