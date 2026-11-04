<?php

namespace App\Imports;

use Maatwebsite\Excel\Concerns\Import;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

/** Baca baris pertama sebagai nama kolom (di-slug: "Confirmation Code" → confirmation_code). */
class HeadingImport implements Import, WithHeadingRow
{
}
