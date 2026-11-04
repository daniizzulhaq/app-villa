<?php

namespace App\Http\Controllers;

use App\Models\ExpenseReceipt;
use App\Models\IncomeAttachment;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * File nota & bukti bayar disimpan di storage/app/private (tidak bisa diakses langsung dari URL publik).
 * Setiap permintaan dicek Policy pengeluaran / pemasukan terkait.
 */
class PrivateFileController extends Controller
{
    public function receipt(ExpenseReceipt $receipt): StreamedResponse
    {
        $receipt->load('expense');
        abort_unless($receipt->expense, 404);
        $this->authorize('viewReceipt', $receipt->expense);

        return $this->stream($receipt->path, $receipt->original_name);
    }

    public function incomeAttachment(IncomeAttachment $attachment): StreamedResponse
    {
        $attachment->load('income');
        abort_unless($attachment->income, 404);
        $this->authorize('view', $attachment->income);

        return $this->stream($attachment->path, $attachment->original_name);
    }

    private function stream(string $path, string $name): StreamedResponse
    {
        abort_unless(Storage::disk('local')->exists($path), 404);

        $disposition = request()->boolean('download') ? 'attachment' : 'inline';

        return Storage::disk('local')->response($path, $name, [
            'Cache-Control' => 'private, max-age=86400',
            'X-Content-Type-Options' => 'nosniff',
        ], $disposition);
    }
}
