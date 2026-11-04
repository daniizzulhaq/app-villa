<?php

namespace App\Http\Controllers\Manager;

use App\Http\Controllers\Controller;
use App\Models\ExpenseCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Pengaturan: kategori pengeluaran (16 bawaan + custom). */
class SettingsController extends Controller
{
    public function index(): View
    {
        abort_unless(auth()->user()->isMaster(), 403);

        return view('settings.index', [
            'categories' => ExpenseCategory::withCount('expenses')->orderBy('sort_order')->orderBy('name')->get(),
            'icons' => ['bi-tag', 'bi-cart', 'bi-tools', 'bi-lightning-charge', 'bi-droplet', 'bi-wifi', 'bi-truck', 'bi-bag', 'bi-cup-hot', 'bi-shield-check', 'bi-bug', 'bi-flower1', 'bi-house-gear', 'bi-receipt'],
        ]);
    }

    public function storeCategory(Request $request): RedirectResponse
    {
        abort_unless($request->user()->isMaster(), 403);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('expense_categories', 'name')],
            'icon' => ['nullable', 'string', 'max:50', 'regex:/^bi-[a-z0-9-]+$/'],
        ], ['name.unique' => __('Kategori dengan nama ini sudah ada.')], ['name' => __('nama kategori')]);

        ExpenseCategory::create([
            'name' => $data['name'],
            'icon' => $data['icon'] ?? 'bi-tag',
            'is_default' => false,
            'is_active' => true,
            'sort_order' => (int) ExpenseCategory::max('sort_order') + 1,
        ]);

        return back()->with('success', "Kategori \"{$data['name']}\" ditambahkan.");
    }

    public function updateCategory(Request $request, ExpenseCategory $category): RedirectResponse
    {
        abort_unless($request->user()->isMaster(), 403);

        if ($request->has('toggle')) {
            $category->update(['is_active' => ! $category->is_active]);

            return back()->with('success', $category->is_active ? __('Kategori diaktifkan.') : __('Kategori disembunyikan dari form. Data lama tetap ada.'));
        }

        abort_if($category->is_default, 403, __('Kategori bawaan tidak dapat diganti namanya.'));
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('expense_categories', 'name')->ignore($category->id)],
        ], [], ['name' => __('nama kategori')]);
        $category->update($data);

        return back()->with('success', __('Kategori diperbarui.'));
    }

    public function destroyCategory(ExpenseCategory $category): RedirectResponse
    {
        abort_unless(auth()->user()->isMaster(), 403);

        if ($category->is_default || $category->expenses()->withTrashed()->exists()) {
            return back()->with('error', __('Kategori bawaan atau yang sudah dipakai tidak bisa dihapus — nonaktifkan saja.'));
        }
        $category->delete();

        return back()->with('success', __('Kategori dihapus.'));
    }
}
