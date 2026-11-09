import * as bootstrap from 'bootstrap';
import Alpine from 'alpinejs';
import focus from '@alpinejs/focus';

window.bootstrap = bootstrap;

/* ==========================================================================
   Terjemahan untuk teks yang dibuat JavaScript.
   Kamus dikirim server lewat <script id="pm-i18n"> di layout (lihat partials/i18n).
   t('Teks :x', { x: 1 }) → teks terjemahan; bila belum ada → teks asli (Indonesia).
   ========================================================================== */
const I18N = (() => {
    try { return JSON.parse(document.getElementById('pm-i18n')?.textContent || '{}'); } catch { return {}; }
})();
const LOCALE = I18N.locale || document.documentElement.lang || 'id';
const t = (key, repl = {}) => {
    let s = (I18N.messages && I18N.messages[key]) || key;
    for (const [k, v] of Object.entries(repl)) s = s.split(':' + k).join(v);
    return s;
};
window.t = t;

/* ==========================================================================
   Toast — sukses / gagal / peringatan
   Pakai dari mana saja: window.toast('Tersimpan', 'success')
   ========================================================================== */
Alpine.store('toasts', {
    items: [],
    push(message, type = 'success', timeout = 4500) {
        const id = Date.now() + Math.random();
        this.items.push({ id, message, type });
        if (timeout) setTimeout(() => this.remove(id), timeout);
    },
    remove(id) {
        this.items = this.items.filter((t) => t.id !== id);
    },
});
window.toast = (message, type = 'success') => Alpine.store('toasts').push(message, type);

/* ==========================================================================
   Konfirmasi sebelum aksi berbahaya
   <form data-confirm="Hapus villa ini?" data-confirm-text="..." data-confirm-button="Ya, hapus" data-confirm-danger>
   ========================================================================== */
Alpine.store('confirm', {
    open: false,
    title: '',
    text: '',
    button: t('Ya, lanjutkan'),
    danger: false,
    _resolve: null,
    ask({ title, text = '', button = t('Ya, lanjutkan'), danger = false }) {
        Object.assign(this, { title, text, button, danger, open: true });
        return new Promise((resolve) => (this._resolve = resolve));
    },
    answer(value) {
        this.open = false;
        this._resolve?.(value);
        this._resolve = null;
    },
});

document.addEventListener('submit', async (e) => {
    const form = e.target;
    if (!(form instanceof HTMLFormElement)) return;

    if (form.dataset.confirm && !form.dataset.confirmed) {
        e.preventDefault();
        const ok = await Alpine.store('confirm').ask({
            title: form.dataset.confirm,
            text: form.dataset.confirmText || '',
            button: form.dataset.confirmButton || 'Ya, lanjutkan',
            danger: form.hasAttribute('data-confirm-danger'),
        });
        if (ok) {
            form.dataset.confirmed = '1';
            form.requestSubmit(e.submitter || undefined);
        }
        return;
    }

    if (form.dataset.submitting && !form.hasAttribute('data-no-lock')) {
        e.preventDefault(); // klik kedua diabaikan
        return;
    }
    lockSubmit(form, e.submitter);
}, true);

/* ==========================================================================
   Tombol simpan tidak bisa diklik dua kali + indikator loading
   ========================================================================== */
function lockSubmit(form, submitter) {
    if (form.hasAttribute('data-no-lock')) return;
    form.dataset.submitting = '1';
    form.querySelectorAll('button[type="submit"], button:not([type])').forEach((btn) => {
        btn.disabled = true;
        btn.classList.add('is-loading');
    });
    const target = submitter || form.querySelector('button[type="submit"]');
    if (target && !target.querySelector('.spinner-border')) {
        target.dataset.originalHtml = target.innerHTML;
        const label = target.dataset.loadingText || t('Menyimpan…');
        target.innerHTML = `<span class="spinner-border" aria-hidden="true"></span><span>${label}</span>`;
    }
}

// Kembali dengan tombol back browser (bfcache) → aktifkan lagi tombol
window.addEventListener('pageshow', () => {
    document.querySelectorAll('form[data-submitting]').forEach((form) => {
        delete form.dataset.submitting;
        delete form.dataset.confirmed;
        form.querySelectorAll('button.is-loading').forEach((btn) => {
            btn.disabled = false;
            btn.classList.remove('is-loading');
            if (btn.dataset.originalHtml) btn.innerHTML = btn.dataset.originalHtml;
        });
    });
});

/* ==========================================================================
   Validasi langsung di field (sebelum dikirim ke server), pesan bahasa sederhana
   ========================================================================== */
function friendlyMessage(el) {
    const v = el.validity;
    const label = el.dataset.label || t('Kolom ini');
    if (v.valueMissing) return t(':label wajib diisi.', { label });
    if (v.typeMismatch && el.type === 'email') return t('Format email belum benar, contoh: nama@email.com');
    if (v.typeMismatch && el.type === 'url') return t('Masukkan alamat lengkap, diawali https://');
    if (v.rangeUnderflow) return t(':label minimal :min.', { label, min: el.min });
    if (v.rangeOverflow) return t(':label maksimal :max.', { label, max: el.max });
    if (v.tooShort) return t(':label minimal :min karakter.', { label, min: el.minLength });
    if (v.tooLong) return t(':label maksimal :max karakter.', { label, max: el.maxLength });
    if (v.stepMismatch) return t(':label maksimal 2 angka di belakang koma.', { label });
    if (v.patternMismatch) return el.title || t(':label belum sesuai format.', { label });
    if (v.badInput) return t(':label harus berupa angka.', { label });
    return el.validationMessage;
}

function showFieldState(el) {
    if (!el.willValidate || el.type === 'hidden') return;
    const wrapper = el.closest('[data-field]') || el.parentElement;
    let fb = wrapper.querySelector('.invalid-feedback[data-live]');
    if (el.validity.valid) { // jangan checkValidity(): memicu event "invalid" lagi → rekursi
        if (fb) {
            el.classList.remove('is-invalid');
            fb.remove();
        }
        return;
    }
    el.classList.add('is-invalid');
    wrapper.querySelectorAll('.invalid-feedback:not([data-live])').forEach((n) => n.remove());
    if (!fb) {
        fb = document.createElement('div');
        fb.className = 'invalid-feedback d-block';
        fb.dataset.live = '1';
        wrapper.appendChild(fb);
    }
    fb.textContent = friendlyMessage(el);
    el.setAttribute('aria-invalid', 'true');
}

document.addEventListener('focusout', (e) => {
    const el = e.target;
    if (el.matches?.('form[data-validate] :is(input, select, textarea)')) showFieldState(el);
});
document.addEventListener('input', (e) => {
    const el = e.target;
    if (el.classList?.contains('is-invalid')) {
        // hapus error server begitu pengguna mulai memperbaiki
        if (el.validity.valid) { // jangan checkValidity(): memicu event "invalid" lagi → rekursi
            el.classList.remove('is-invalid');
            el.removeAttribute('aria-invalid');
            (el.closest('[data-field]') || el.parentElement).querySelectorAll('.invalid-feedback').forEach((n) => n.remove());
        }
    }
});
document.addEventListener('invalid', (e) => {
    const el = e.target;
    if (el.form?.hasAttribute('data-validate')) {
        e.preventDefault(); // pakai pesan kita, bukan bubble bawaan browser
        showFieldState(el);
        const first = el.form.querySelector(':invalid');
        if (first === el) el.focus({ preventScroll: false });
    }
}, true);

/* ==========================================================================
   Komponen Alpine
   ========================================================================== */

// Input nominal Rupiah: tampil "1.250.000", dikirim "1250000"
Alpine.data('moneyInput', (initial = '') => ({
    raw: initial ? String(Math.round(Number(initial))) : '',
    get display() {
        return this.raw === '' ? '' : Number(this.raw).toLocaleString('id-ID');
    },
    onInput(e) {
        const digits = e.target.value.replace(/[^\d]/g, '').replace(/^0+(?=\d)/, '').slice(0, 13);
        this.raw = digits;
        const pos = e.target.value.length - e.target.selectionStart;
        e.target.value = this.display;
        const p = Math.max(0, e.target.value.length - pos);
        e.target.setSelectionRange(p, p);
    },
}));

// Pratinjau foto sebelum diunggah
Alpine.data('photoPicker', (existing = null) => ({
    preview: existing,
    removed: false,
    pick(e) {
        const file = e.target.files?.[0];
        if (!file) return;
        if (file.size > 10 * 1024 * 1024) {
            window.toast(t('Ukuran foto maksimal 10 MB.'), 'error');
            e.target.value = '';
            return;
        }
        this.preview = URL.createObjectURL(file);
        this.removed = false;
    },
    clear() {
        this.preview = null;
        this.removed = true;
        this.$refs.file.value = '';
    },
}));

// Foto nota: kamera HP langsung terbuka, bisa memotret beberapa kali (file digabung, tidak saling menimpa)
Alpine.data('receiptPicker', ({ max = 6, existing = 0 } = {}) => ({
    files: [],
    previews: [],
    existing,
    get count() { return this.files.length + this.existing; },
    add(e) {
        const picked = Array.from(e.target.files || []);
        for (const f of picked) {
            if (this.count >= max) {
                window.toast(t('Maksimal :max foto per nota.', { max }), 'warning');
                break;
            }
            if (f.size > 10 * 1024 * 1024) {
                window.toast(t(':name lebih dari 10 MB, dilewati.', { name: f.name }), 'error');
                continue;
            }
            this.files.push(f);
            this.previews.push(f.type.startsWith('image/') ? URL.createObjectURL(f) : null);
        }
        this.sync();
    },
    remove(i) {
        this.files.splice(i, 1);
        this.previews.splice(i, 1);
        this.sync();
    },
    sync() {
        const dt = new DataTransfer();
        this.files.forEach((f) => dt.items.add(f));
        this.$refs.input.files = dt.files;
    },
}));

// Salin teks (link iCal, dsb.)
Alpine.data('copyText', () => ({
    copied: false,
    async copy(text) {
        try {
            await navigator.clipboard.writeText(text);
            this.copied = true;
            window.toast(t('Link disalin.'), 'success');
            setTimeout(() => (this.copied = false), 2000);
        } catch {
            window.toast(t('Gagal menyalin. Salin manual.'), 'error');
        }
    },
}));

/* ==========================================================================
   Kalender reservasi (FullCalendar) — dimuat hanya di halaman kalender
   ========================================================================== */
Alpine.data('bookingCalendar', ({ eventsUrl, villa = '', initialDate = null, cancelled = false }) => ({
    villa: villa ? String(villa) : '',
    loading: true,
    selected: null,
    calendar: null,
    async init() {
        const [{ Calendar }, dayGrid, list, interaction, allLocales] = await Promise.all([
            import('@fullcalendar/core'),
            import('@fullcalendar/daygrid'),
            import('@fullcalendar/list'),
            import('@fullcalendar/interaction'),
            import('@fullcalendar/core/locales-all'),
        ]);
        const mobile = window.matchMedia('(max-width: 767.98px)').matches;
        this.calendar = new Calendar(this.$refs.cal, {
            plugins: [dayGrid.default, list.default, interaction.default],
            initialView: mobile ? 'listMonth' : 'dayGridMonth',
            initialDate: initialDate || undefined,
            locales: allLocales.default,
            locale: LOCALE.toLowerCase().replace('_', '-'),
            firstDay: 1,
            height: 'auto',
            dayMaxEvents: 3,
            headerToolbar: { left: 'prev,next today', center: 'title', right: 'dayGridMonth,listMonth' },
            noEventsContent: t('Tidak ada reservasi di periode ini — villa kosong.'),
            moreLinkContent: (a) => t('+:num lagi', { num: a.num }),
            events: (info, success, failure) => {
                this.loading = true;
                const params = new URLSearchParams({ start: info.startStr, end: info.endStr });
                if (this.villa) params.set('villa', this.villa);
                if (cancelled) params.set('cancelled', 1);
                fetch(`${eventsUrl}?${params}`, { headers: { Accept: 'application/json' } })
                    .then((r) => (r.ok ? r.json() : Promise.reject(r)))
                    .then(success)
                    .catch((e) => {
                        failure(e);
                        window.toast(t('Gagal memuat kalender. Periksa koneksi lalu coba lagi.'), 'error');
                    })
                    .finally(() => (this.loading = false));
            },
            eventClick: (info) => {
                if (info.event.display === 'background') return;
                info.jsEvent.preventDefault();
                this.selected = { title: info.event.title, ...info.event.extendedProps };
            },
        });
        this.calendar.render();
        this.$watch('villa', () => this.calendar.refetchEvents());
    },
    gotoDate(d) {
        if (d) this.calendar?.gotoDate(d);
    },
}));

/* ==========================================================================
   Grafik (ApexCharts) — format Rupiah di sumbu & tooltip
   ========================================================================== */
const rupiahShort = (v) => {
    const a = Math.abs(v), s = v < 0 ? '−' : '';
    if (a >= 1e9) return `${s}Rp ${(a / 1e9).toLocaleString('id-ID', { maximumFractionDigits: 1 })} ${t('M')}`;
    if (a >= 1e6) return `${s}Rp ${(a / 1e6).toLocaleString('id-ID', { maximumFractionDigits: 1 })} ${t('jt')}`;
    if (a >= 1e3) return `${s}Rp ${(a / 1e3).toLocaleString('id-ID', { maximumFractionDigits: 0 })} ${t('rb')}`;
    return `${s}Rp ${a.toLocaleString('id-ID')}`;
};
const rupiahFull = (v) => (v < 0 ? '−' : '') + 'Rp ' + Math.round(Math.abs(v)).toLocaleString('id-ID');
window.rupiahFull = rupiahFull;

Alpine.data('chart', (config) => ({
    loading: true,
    empty: false,
    async init() {
        const { default: ApexCharts } = await import('apexcharts');
        const isDonut = ['donut', 'pie'].includes(config.type);
        const series = config.series || [];
        this.empty = isDonut ? !series.some((v) => Number(v) !== 0) : !series.some((s) => (s.data || []).some((v) => Number(v) !== 0));
        this.loading = false;
        if (this.empty) return;
        const base = {
            chart: { type: config.type, height: config.height || 300, fontFamily: 'Inter Variable, Inter, sans-serif', toolbar: { show: false }, animations: { speed: 300 } },
            series,
            colors: config.colors || ['#a77c25', '#d5b363', '#e8cf94', '#eadfc9', '#8c8271', '#b8a991'],
            dataLabels: { enabled: false },
            legend: { position: 'bottom', fontSize: '13px', markers: { size: 6 } },
            grid: { borderColor: '#ece9e3', strokeDashArray: 4 },
            tooltip: { y: { formatter: (v) => (config.percent ? `${v}%` : rupiahFull(v)) } },
            noData: { text: t('Belum ada data') },
        };
        if (isDonut) {
            Object.assign(base, {
                labels: config.labels,
                plotOptions: { pie: { donut: { size: '68%', labels: { show: true, total: { show: true, label: t('Total'), formatter: (w) => rupiahShort(w.globals.seriesTotals.reduce((a, b) => a + b, 0)) }, value: { formatter: (v) => rupiahShort(Number(v)) } } } } },
                stroke: { width: 2, colors: ['#fff'] },
            });
        } else {
            Object.assign(base, {
                xaxis: { categories: config.labels, labels: { style: { colors: '#5B6779', fontSize: '12px' } }, axisBorder: { show: false } },
                yaxis: { labels: { formatter: (v) => (config.percent ? `${v}%` : rupiahShort(v)), style: { colors: '#5B6779' } } },
                plotOptions: { bar: { borderRadius: 3, columnWidth: '48%', horizontal: !!config.horizontal } },
                stroke: config.type === 'line' ? { width: 3, curve: 'smooth' } : { width: 0 },
                markers: config.type === 'line' ? { size: 4 } : {},
            });
            if (config.horizontal) {
                // Batang horizontal: nilai di sumbu X, nama (villa) di sumbu Y
                base.xaxis.labels.formatter = (v) => rupiahShort(Number(v));
                base.yaxis = { labels: { style: { colors: '#334155', fontSize: '12px' }, maxWidth: 160 } };
            }
        }
        new ApexCharts(this.$refs.chart, Object.assign(base, config.options || {})).render();
    },
}));

Alpine.plugin(focus);
window.Alpine = Alpine;
Alpine.start();

// Tooltip Bootstrap untuk ikon tanpa teks
document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach((el) => new bootstrap.Tooltip(el));
