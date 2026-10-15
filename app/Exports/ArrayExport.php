<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithCustomCsvSettings;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/** Xuất bảng dữ liệu bất kỳ ra CSV (UTF-8 có BOM để Excel đọc đúng tiếng Việt) hoặc XLSX. */
class ArrayExport implements FromArray, ShouldAutoSize, WithCustomCsvSettings, WithHeadings, WithStyles
{
    public function __construct(private array $headings, private array $rows) {}

    public function array(): array
    {
        return $this->rows;
    }

    public function headings(): array
    {
        return $this->headings;
    }

    public function getCsvSettings(): array
    {
        return ['use_bom' => true, 'delimiter' => ','];
    }

    public function styles(Worksheet $sheet): array
    {
        return [1 => ['font' => ['bold' => true]]];
    }
}
