<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/** Sheet generik: judul, header tebal, format angka ribuan. */
class Sheet implements FromArray, WithTitle, WithHeadings, WithColumnFormatting, WithStyles, ShouldAutoSize
{
    public function __construct(
        private string $title,
        private array $headings,
        private array $rows,
        private array $formats = [],
        private bool $titleRow = false,
    ) {}

    public function array(): array
    {
        return $this->rows;
    }

    public function title(): string
    {
        return $this->title;
    }

    public function headings(): array
    {
        return $this->headings;
    }

    public function columnFormats(): array
    {
        return $this->formats;
    }

    public function styles(Worksheet $sheet): ?array
    {
        if ($this->titleRow) {
            return [1 => ['font' => ['bold' => true, 'size' => 14]], 15 => ['font' => ['bold' => true]]];
        }

        return [1 => ['font' => ['bold' => true], 'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => 'EEF2F7']]]];
    }
}
