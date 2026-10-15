<?php

namespace App\Services;

use App\Exports\ArrayExport;
use Maatwebsite\Excel\Excel as ExcelFormat;
use Maatwebsite\Excel\Facades\Excel;

class ExportService
{
    /**
     * @param  iterable<array>  $rows
     * @param  string  $format  csv|xlsx
     */
    public function download(string $baseName, array $headings, iterable $rows, string $format = 'xlsx')
    {
        $format = $format === 'csv' ? 'csv' : 'xlsx';
        $rows = $rows instanceof \Illuminate\Support\Collection ? $rows->all() : (is_array($rows) ? $rows : iterator_to_array($rows));
        $rows = array_map(fn ($r) => array_map(fn ($v) => $this->sanitize($v), array_values($r)), $rows);

        ActivityLogger::log('exported', null, 'Xuất dữ liệu '.$baseName.' ('.count($rows).' dòng, '.strtoupper($format).')');

        return Excel::download(
            new ArrayExport($headings, $rows),
            $baseName.'_'.now()->format('Ymd_His').'.'.$format,
            $format === 'csv' ? ExcelFormat::CSV : ExcelFormat::XLSX,
        );
    }

    /** Chống CSV/Formula injection: ô bắt đầu bằng = + - @ sẽ được thêm dấu nháy. */
    private function sanitize($value)
    {
        if (is_string($value) && $value !== '' && in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true) && ! is_numeric($value)) {
            return "'".$value;
        }

        return $value;
    }
}
