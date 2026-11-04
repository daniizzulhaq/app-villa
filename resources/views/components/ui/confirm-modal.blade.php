{{-- Modal konfirmasi global. Dipicu oleh <form data-confirm="..."> (lihat resources/js/app.js). --}}
<div x-data x-cloak x-show="$store.confirm.open" class="pm-modal-backdrop"
     @keydown.escape.window="$store.confirm.open && $store.confirm.answer(false)"
     @click.self="$store.confirm.answer(false)">
    <div class="pm-modal" role="alertdialog" aria-modal="true" aria-labelledby="pmConfirmTitle" aria-describedby="pmConfirmText"
         x-show="$store.confirm.open" x-trap.noscroll="$store.confirm.open"
         x-transition>
        <div class="pm-modal__icon" :class="$store.confirm.danger && 'pm-modal__icon--danger'" aria-hidden="true">
            <i class="bi" :class="$store.confirm.danger ? 'bi-exclamation-triangle' : 'bi-question-circle'"></i>
        </div>
        <h2 class="pm-modal__title" id="pmConfirmTitle" x-text="$store.confirm.title"></h2>
        <p class="pm-modal__text" id="pmConfirmText" x-text="$store.confirm.text" x-show="$store.confirm.text"></p>
        <div class="pm-modal__actions">
            <button type="button" class="btn btn-light" @click="$store.confirm.answer(false)">{{ __('Batal') }}</button>
            <button type="button" class="btn" :class="$store.confirm.danger ? 'btn-danger' : 'btn-primary'"
                    @click="$store.confirm.answer(true)" x-text="$store.confirm.button"></button>
        </div>
    </div>
</div>
