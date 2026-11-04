# PropertyMe — Design System & Layout

Sumber kode: `resources/css/app.css` (token & komponen), `resources/views/components/**` (Blade components).

## 1. Warna

| Token | Hex | Pemakaian | Kontras di putih |
|---|---|---|---|
| `--pm-ink-900` | `#0F1B2D` | Judul, angka utama | 17.4 : 1 |
| `--pm-ink-700` | `#334155` | Teks isi | 10.4 : 1 |
| `--pm-ink-500` | `#5B6779` | Label, teks sekunder | 5.6 : 1 (AA) |
| `--pm-surface` | `#F5F7FB` | Latar aplikasi | — |
| `--pm-line` | `#E3E8EF` | Garis/border | — |
| `--pm-primary` | `#2F5FD0` | Aksen biru, tombol utama | 5.9 : 1 (teks putih) |
| `--pm-success` | `#157F3D` / soft `#E7F6EC` | Valid, laba positif | ≥ 4.5 : 1 |
| `--pm-danger` | `#B42318` / soft `#FDECEA` | Dikembalikan, laba minus | ≥ 4.5 : 1 |
| `--pm-warning` | `#8A4B06` / soft `#FFF4D6` | Menunggu pemeriksaan | ≥ 4.5 : 1 |
| `--pm-info` | `#1E40AF` / soft `#E0E9FF` | Final | ≥ 4.5 : 1 |

Platform (kalender & legend): Airbnb `#E0484E` · Booking.com `#1A4FA0` · Direct `#0E8F63` · Lainnya `#7C6F9B`.
Grafik memakai maksimal 6 warna: primary, success, danger, warning-dot, info, neutral.

## 2. Tipografi — Inter (self-hosted, tanpa CDN)

| Peran | Ukuran | Berat |
|---|---|---|
| Angka hero (Net Profit) | 32px | 700 |
| Judul halaman / angka KPI | 24px | 650–700 |
| Judul kartu | 18px | 650 |
| Isi | 15px | 400 |
| Input | 16px (cegah auto-zoom iOS) | 400 |
| Label | 13px | 500–600, abu-abu |
| Caps label / header tabel | 12px uppercase | 600 |

Angka memakai `tabular-nums` agar kolom nominal rata.

## 3. Spacing, radius, target sentuh

- Spacing kelipatan 8px: 8 · 16 · 24 · 32 · 40 · 48
- Radius: 8 (input, tombol) · 12 (dropdown, choice) · 16 (kartu, modal)
- Tinggi minimum tombol & input: **44px**; tombol besar 52px; FAB 56px

## 4. Komponen

| Komponen | Blade | Catatan |
|---|---|---|
| Layout app | `<x-layouts.app>` | Sidebar desktop, top bar, bottom nav HP, offcanvas menu, FAB Staff |
| Page header | `<x-ui.page-header>` | Judul + subjudul + tombol aksi + link kembali |
| Kartu | `<x-ui.card>` | header/actions/flush |
| Kartu angka | `<x-ui.stat>` | `hero` untuk Net Profit, `tone=positive/negative` |
| Badge status | `<x-ui.badge :status="$enum">` | Warna dari enum → konsisten di semua halaman |
| Empty state | `<x-ui.empty-state>` | Ilustrasi + teks + tombol aksi |
| Skeleton | `<x-ui.skeleton>` | Loading grafik/tabel async |
| Toast | otomatis dari `session('success'/'error'/'warning')` atau `window.toast()` |
| Konfirmasi | `<form data-confirm="..." data-confirm-danger>` | Bottom-sheet di HP |
| Form | `<x-form.input/select/textarea/switch/money/actions>` | Error per field, aria-describedby |
| Tabel responsif | `.pm-table.pm-table--stack` + `data-label` | Berubah jadi kartu di < 768px |

Perilaku global (resources/js/app.js): tombol submit terkunci + spinner setelah diklik,
validasi langsung saat field ditinggalkan (`form[data-validate]`), input Rupiah berformat saat diketik.

## 5. Sketsa layout

### Manager — desktop
```
┌──────────────┬───────────────────────────────────────────────────────┐
│ PM PropertyMe│                                    🔔  (MN) Manager ▾ │
│──────────────│───────────────────────────────────────────────────────│
│ ▣ Dashboard  │ Halo, Manager                [Tambah User][+ Villa]   │
│ OPERASIONAL  │ ┌───────────┐┌──────────┐┌──────────┐┌──────────────┐ │
│ ⌂ Villa      │ │NET PROFIT ││ Gross    ││ Mgmt Fee ││ Pengeluaran  │ │
│ ▦ Reservasi  │ │Rp 53,4 jt ││ Rp 80 jt ││Rp 11,5 jt││ Rp 12 jt     │ │
│ ⟳ Sinkron    │ └───────────┘└──────────┘└──────────┘└──────────────┘ │
│ KEUANGAN     │ ┌──────────────────────────────┐┌───────────────────┐ │
│ ↓ Pemasukan  │ │ Grafik / Daftar villa        ││ Nota menunggu (3) │ │
│ ▤ Pengeluaran│ │                              ││ Check-in hari ini │ │
│ ✓ Periksa    │ └──────────────────────────────┘└───────────────────┘ │
│ ...          │                                                       │
│ (MN) Keluar  │                                                       │
└──────────────┴───────────────────────────────────────────────────────┘
```

### Owner — HP
```
┌─────────────────────────┐
│ PM  Dashboard     (BW)▾ │
├─────────────────────────┤
│ Halo, Wayan             │
│ ┌─────────────────────┐ │
│ │ KEUNTUNGAN BERSIH   │ │  ← kartu navy, paling menonjol
│ │ Rp 53.450.000       │ │
│ │ Margin 66,8%        │ │
│ └─────────────────────┘ │
│ ┌──────────┐┌─────────┐ │
│ │Pendapatan││Pengelua.│ │
│ └──────────┘└─────────┘ │
│ [ Villa Kayu Manis   ]  │
├─────────────────────────┤
│ ▣    ▦     ▤    ▢    ☰  │  bottom nav
└─────────────────────────┘
```

### Staff — HP (alur upload nota ≤ 3 langkah)
```
┌─────────────────────────┐
│ PM  Kalender      (MS)▾ │
├─────────────────────────┤
│ HARI INI                │
│ ▸ Check-in  Villa A 14:00│
│ ▸ Check-out Villa B     │
│ BESOK                   │
│ ▸ Check-in  Villa A     │
│ ┌─────────────────────┐ │
│ │  Kalender (terisi/  │ │
│ │  kosong beda warna) │ │
│ └─────────────────────┘ │
│            ┌──────────┐ │
│            │📷 Upload │ │  ← FAB: 1) ketuk → kamera
│            └──────────┘ │     2) foto → form terisi otomatis
├─────────────────────────┤     3) nominal + kategori → Simpan
│ ▦Kalender 📷Nota ⟲Riwayat ☺│
└─────────────────────────┘
```
