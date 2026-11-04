{{-- Toast global. Flash session: ->with('success'|'error'|'warning', 'pesan') --}}
@php
    $flash = collect(['success', 'error', 'warning'])
        ->filter(fn ($k) => session()->has($k))
        ->map(fn ($k) => ['type' => $k, 'message' => session($k)])
        ->values();
@endphp
<div class="pm-toasts" x-data
     x-init="@js($flash).forEach(t => $store.toasts.push(t.message, t.type, t.type === 'error' ? 8000 : 4500))"
     role="status" aria-live="polite">
    <template x-for="t in $store.toasts.items" :key="t.id">
        <div class="pm-toast" :class="'pm-toast--' + t.type"
             x-transition.opacity>
            <i class="bi" :class="{ 'bi-check-circle-fill': t.type === 'success', 'bi-x-circle-fill': t.type === 'error', 'bi-exclamation-triangle-fill': t.type === 'warning' }" aria-hidden="true"></i>
            <span x-text="t.message"></span>
            <button type="button" class="pm-toast__close" @click="$store.toasts.remove(t.id)" aria-label="{{ __('Tutup notifikasi') }}"><i class="bi bi-x-lg" aria-hidden="true"></i></button>
        </div>
    </template>
</div>
