<?php

namespace App\Http\Controllers\Manager;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Expense;
use App\Models\Income;
use App\Models\MonthlyReport;
use App\Models\Region;
use App\Models\User;
use App\Models\Villa;
use App\Models\VillaFeeHistory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use OwenIt\Auditing\Models\Audit;

/** Audit log: siapa mengubah apa, nilai lama → nilai baru. */
class AuditController extends Controller
{
    public const TYPES = [
        Income::class => 'Pemasukan',
        Expense::class => 'Pengeluaran',
        Villa::class => 'Villa (termasuk % fee)',
        VillaFeeHistory::class => 'Riwayat fee',
        MonthlyReport::class => 'Laporan / kunci bulan',
        Booking::class => 'Reservasi',
        User::class => 'Pengguna',
        Region::class => 'Wilayah',
    ];

    public const EVENTS = ['created' => 'Dibuat', 'updated' => 'Diubah', 'deleted' => 'Dihapus', 'restored' => 'Dipulihkan'];

    /** Label kolom yang ramah dibaca. */
    public const FIELDS = [
        'gross_amount' => 'Gross', 'platform_fee' => 'Komisi', 'other_deduction' => 'Potongan lain', 'net_revenue' => 'Net revenue',
        'fee_percentage' => '% fee', 'fee_basis' => 'Basis fee', 'fee_base_amount' => 'Basis fee (Rp)', 'fee_amount' => 'Management fee',
        'payment_date' => 'Tanggal bayar', 'expense_date' => 'Tanggal', 'amount' => 'Nominal', 'status' => 'Status',
        'return_reason' => 'Alasan dikembalikan', 'description' => 'Keterangan', 'vendor' => 'Vendor', 'category_id' => 'Kategori',
        'villa_id' => 'Villa', 'owner_id' => 'Owner', 'booking_ref' => 'Kode booking', 'guest_name' => 'Nama tamu',
        'check_in' => 'Check-in', 'check_out' => 'Check-out', 'is_active' => 'Aktif', 'name' => 'Nama', 'unlock_reason' => 'Alasan buka kunci',
        'is_net_of_platform' => 'Sudah net', 'notes' => 'Catatan', 'effective_from' => 'Berlaku mulai', 'deleted_at' => 'Dihapus pada',
    ];

    public function __invoke(Request $request): View
    {
        abort_unless($request->user()->isManager(), 403);

        $f = $request->validate([
            'type' => ['nullable', 'in:'.implode(',', array_keys(self::TYPES))],
            'event' => ['nullable', 'in:'.implode(',', array_keys(self::EVENTS))],
            'user' => ['nullable', 'integer'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);

        $audits = Audit::query()
            ->with('user')
            ->tap(fn ($q) => $this->scopeToVillas($q, $request->user()))
            ->when($f['type'] ?? null, fn ($q, $t) => $q->where('auditable_type', $t))
            ->when($f['event'] ?? null, fn ($q, $e) => $q->where('event', $e))
            ->when($f['user'] ?? null, fn ($q, $u) => $q->where('user_id', $u)->where('user_type', User::class))
            ->when($f['from'] ?? null, fn ($q, $d) => $q->whereDate('created_at', '>=', $d))
            ->when($f['to'] ?? null, fn ($q, $d) => $q->whereDate('created_at', '<=', $d))
            ->latest()->paginate(30)->withQueryString();

        return view('audits.index', [
            'audits' => $audits,
            'filters' => $f,
            'users' => User::withTrashed()->orderBy('name')->pluck('name', 'id'),
        ]);
    }

    /** Manager wilayah: hanya perubahan pada villa di wilayahnya & data turunannya. Master: semua. */
    private function scopeToVillas($query, User $user): void
    {
        $ids = $user->accessibleVillaIds();
        if ($ids === null) {
            return;
        }
        $ids = $ids ?: [0];
        $query->where(function ($w) use ($ids) {
            $w->where(fn ($q) => $q->where('auditable_type', Villa::class)->whereIn('auditable_id', $ids));
            foreach ([Income::class => 'incomes', Expense::class => 'expenses', Booking::class => 'bookings',
                MonthlyReport::class => 'monthly_reports', VillaFeeHistory::class => 'villa_fee_histories'] as $class => $table) {
                $w->orWhere(fn ($q) => $q->where('auditable_type', $class)
                    ->whereIn('auditable_id', DB::table($table)->select('id')->whereIn('villa_id', $ids)));
            }
        });
    }

    public static function display(mixed $value): string
    {
        return match (true) {
            $value === null, $value === '' => '—',
            is_bool($value) => $value ? 'Ya' : __('Tidak'),
            is_array($value) => json_encode($value, JSON_UNESCAPED_UNICODE),
            is_numeric($value) && str_contains((string) $value, '.') && strlen((string) $value) > 4 => number_format((float) $value, 2, ',', '.'),
            default => mb_strimwidth((string) $value, 0, 120, '…'),
        };
    }
}
