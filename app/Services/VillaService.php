<?php

namespace App\Services;

use App\Enums\FeeBasis;
use App\Models\BookingPlatform;
use App\Models\User;
use App\Models\Villa;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class VillaService
{
    public function __construct(private ImageCompressor $images) {}

    public function create(array $data, User $actor): Villa
    {
        return DB::transaction(function () use ($data, $actor) {
            app(VillaAssignmentLimits::class)->forVilla(null, $data);
            $villa = new Villa(Arr::only($data, (new Villa)->getFillable()));

            if (($data['photo'] ?? null) instanceof UploadedFile) {
                $villa->photo = $this->images->store($data['photo'], 'villas')['path'];
            }
            $villa->save();

            // Riwayat fee pertama berlaku sejak villa mulai dikelola.
            $villa->feeHistories()->create([
                'fee_percentage' => $villa->fee_percentage,
                'fee_basis' => $villa->fee_basis,
                'fee_basis_note' => $villa->fee_basis_note,
                'effective_from' => $data['fee_effective_from'] ?? $villa->started_at ?? today(),
                'changed_by' => $actor->id,
            ]);

            $this->syncChannels($villa, $data['channels'] ?? []);
            $villa->staff()->sync($data['staff_ids'] ?? []);

            return $villa;
        });
    }

    public function update(Villa $villa, array $data, User $actor): Villa
    {
        return DB::transaction(function () use ($villa, $data, $actor) {
            app(VillaAssignmentLimits::class)->forVilla($villa, $data);
            $oldPct = (string) $villa->fee_percentage;
            $oldBasis = $villa->fee_basis;
            $oldNote = $villa->fee_basis_note;

            $villa->fill(Arr::only($data, (new Villa)->getFillable()));

            if (! empty($data['remove_photo']) && $villa->photo) {
                Storage::disk('public')->delete($villa->photo);
                $villa->photo = null;
            }
            if (($data['photo'] ?? null) instanceof UploadedFile) {
                if ($villa->getOriginal('photo')) {
                    Storage::disk('public')->delete($villa->getOriginal('photo'));
                }
                $villa->photo = $this->images->store($data['photo'], 'villas')['path'];
            }

            $feeChanged = bccomp($oldPct, (string) $villa->fee_percentage, 2) !== 0
                || $oldBasis !== $villa->fee_basis
                || ($villa->fee_basis === FeeBasis::Custom && $oldNote !== $villa->fee_basis_note);

            $villa->save();

            if ($feeChanged) {
                $villa->feeHistories()->create([
                    'fee_percentage' => $villa->fee_percentage,
                    'fee_basis' => $villa->fee_basis,
                    'fee_basis_note' => $villa->fee_basis_note,
                    'effective_from' => Carbon::parse($data['fee_effective_from'] ?? today()),
                    'changed_by' => $actor->id,
                ]);
            }

            $this->syncChannels($villa, $data['channels'] ?? []);
            $villa->staff()->sync($data['staff_ids'] ?? []);

            return $villa;
        });
    }

    /**
     * Simpan link iCal per platform. Link dikosongkan → channel dihapus.
     * Status sinkron (last_synced_at, last_error) direset bila URL berubah.
     */
    public function syncChannels(Villa $villa, array $channels): void
    {
        foreach (BookingPlatform::icalPlatforms() as $platform) {
            $url = trim((string) ($channels[$platform->slug] ?? ''));
            $existing = $villa->channels()->where('platform_id', $platform->id)->first();

            if ($url === '') {
                $existing?->delete();

                continue;
            }

            if ($existing) {
                if ($existing->ical_url !== $url) {
                    $existing->update(['ical_url' => $url, 'last_error' => null, 'last_synced_at' => null, 'last_success_at' => null]);
                }
            } else {
                $villa->channels()->create(['platform_id' => $platform->id, 'ical_url' => $url]);
            }
        }
    }
}
