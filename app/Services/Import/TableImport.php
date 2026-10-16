<?php

namespace App\Services\Import;

use App\Imports\RowsImport;
use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Khung nhập dữ liệu từ CSV / Excel (.xlsx) dùng chung cho lead, người liên hệ, sản phẩm
 * (khách hàng dùng CustomerImportService riêng có trường tùy biến).
 *  - Tiêu đề cột tiếng Việt hoặc tiếng Anh (aliases), CSV phân cách "," hoặc ";".
 *  - Mỗi dòng qua đúng quy tắc kiểm tra như khi nhập tay; dòng lỗi / trùng bị bỏ qua và báo lại số dòng.
 *  - Cả tệp trong MỘT giao dịch: lỗi hệ thống giữa chừng → không lưu bản ghi nào.
 */
abstract class TableImport
{
    public const MAX_ROWS = 5000;

    /** Tên loại dữ liệu trong nhật ký (VD "lead"). */
    abstract public function noun(): string;

    /** [cột => [tên cột chấp nhận (chữ thường)...]] — cột đầu tiên là cột bắt buộc. */
    abstract public function aliases(): array;

    /** [cột => mô tả] để hiển thị hướng dẫn. */
    abstract public function columns(): array;

    /** Một dòng mẫu cho tệp mẫu (theo thứ tự aliases()). */
    abstract public function sample(): array;

    /** Dựng dữ liệu từ một dòng ($get('cột') trả chuỗi đã trim). Trả về chuỗi = thông báo lỗi của dòng. */
    abstract protected function build(callable $get, User $actor): array|string;

    abstract protected function rules(): array;

    /** Thông báo khi dòng trùng bản ghi đã có (null = không trùng). */
    abstract protected function duplicate(array $data): ?string;

    abstract protected function persist(array $data, User $actor): void;

    protected function attributes(): array
    {
        return [];
    }

    public function headings(): array
    {
        return array_keys($this->aliases());
    }

    public function import(UploadedFile $file, User $actor): array
    {
        $rows = $this->readRows($file);
        $header = array_shift($rows);
        $required = $this->headings()[0];
        if (! $header) {
            return ['created' => 0, 'skipped' => 0, 'errors' => [__('Tệp trống hoặc không đọc được.')]];
        }
        $map = $this->mapHeader($header);
        if (! isset($map[$required])) {
            return ['created' => 0, 'skipped' => 0, 'errors' => [__('Không tìm thấy cột ":column". Hãy dùng tệp mẫu.', ['column' => $required])]];
        }

        $created = 0;
        $skipped = 0;
        $errors = [];
        $line = 1;
        $label = basename($file->getClientOriginalName());

        DB::beginTransaction();
        try {
            foreach ($rows as $row) {
                $line++;
                if ($line - 1 > self::MAX_ROWS) {
                    $errors[] = __('Chỉ nhập tối đa :max dòng mỗi lần.', ['max' => self::MAX_ROWS]);
                    break;
                }
                if (count(array_filter($row, fn ($v) => trim((string) $v) !== '')) === 0) {
                    continue;
                }
                $get = fn (string $key) => isset($map[$key]) ? trim((string) ($row[$map[$key]] ?? '')) : '';

                $data = $this->build($get, $actor);
                if (is_string($data)) {
                    $skipped++;
                    $errors[] = __('Dòng :line: :error', ['line' => $line, 'error' => $data]);

                    continue;
                }
                $validator = Validator::make($data, $this->rules(), [], $this->attributes());
                if ($validator->fails()) {
                    $skipped++;
                    $errors[] = __('Dòng :line: :error', ['line' => $line, 'error' => $validator->errors()->first()]);

                    continue;
                }
                if ($message = $this->duplicate($data)) {
                    $skipped++;
                    $errors[] = __('Dòng :line: :error', ['line' => $line, 'error' => $message]);

                    continue;
                }

                $this->persist($data, $actor);
                $created++;
            }
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            report($e);

            return ['created' => 0, 'skipped' => $skipped, 'errors' => [__('Lỗi khi nhập dữ liệu, không có bản ghi nào được lưu: :error', ['error' => $e->getMessage()])]];
        }

        ActivityLogger::log('imported', null, "Nhập $created {$this->noun()} từ tệp $label (bỏ qua $skipped dòng)");

        return ['created' => $created, 'skipped' => $skipped, 'errors' => array_slice($errors, 0, 100)];
    }

    /** Tệp mẫu CSV (UTF-8 có BOM để Excel đọc đúng tiếng Việt). */
    public function templateCsv(): string
    {
        $out = fopen('php://temp', 'r+');
        fputcsv($out, $this->headings());
        fputcsv($out, $this->sample());
        rewind($out);

        return "\xEF\xBB\xBF".stream_get_contents($out);
    }

    // ---------------------------------------------------------------- tiện ích dùng chung

    /** @return list<list<string>> mọi dòng (dòng đầu là tiêu đề) */
    protected function readRows(UploadedFile $file): array
    {
        $extension = strtolower($file->getClientOriginalExtension());
        if (in_array($extension, ['xlsx', 'xls'], true)) {
            $sheet = Excel::toArray(new RowsImport, $file)[0] ?? [];

            return array_map(fn ($row) => array_map(fn ($v) => $this->cell($v), $row), array_slice($sheet, 0, self::MAX_ROWS + 2));
        }

        $handle = fopen($file->getRealPath(), 'r');
        $firstLine = fgets($handle);
        $delimiter = substr_count((string) $firstLine, ';') > substr_count((string) $firstLine, ',') ? ';' : ',';
        rewind($handle);
        $rows = [];
        while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
            $rows[] = $row;
            if (count($rows) > self::MAX_ROWS + 1) {
                break;
            }
        }
        fclose($handle);

        return $rows;
    }

    /** Ô bảng tính → chuỗi (số nguyên kiểu float của Excel không kèm ".0"). */
    protected function cell($value): string
    {
        if (is_float($value) && floor($value) === $value) {
            return number_format($value, 0, '', '');
        }

        return trim((string) $value);
    }

    protected function mapHeader(array $header): array
    {
        $map = [];
        foreach ($header as $index => $title) {
            $title = mb_strtolower(trim(preg_replace('/^\xEF\xBB\xBF/', '', (string) $title)));
            foreach ($this->aliases() as $key => $aliases) {
                if (in_array($title, $aliases, true) && ! isset($map[$key])) {
                    $map[$key] = $index;
                }
            }
        }

        return $map;
    }

    /** Excel thường làm mất số 0 đầu của số di động (VD 901234567) → bù lại. */
    protected function phone(string $value): ?string
    {
        if ($value === '') {
            return null;
        }

        return preg_match('/^[1-9]\d{8}$/', $value) ? '0'.$value : $value;
    }

    /**
     * Số tiền: "1500000", "1.500.000", "1,500,000", "1 500 000", "1500000.5", "1.500.000,5" → float.
     * Trả về null nếu ô trống, chuỗi gốc nếu không hiểu được (để quy tắc numeric báo lỗi đúng dòng).
     */
    protected function money(string $value): float|string|null
    {
        $v = str_replace([' ', "\u{00A0}", '₫', 'đ', 'VND', 'vnd'], '', $value);
        if ($v === '') {
            return null;
        }
        if (preg_match('/^\d{1,3}(\.\d{3})+(,\d+)?$/', $v)) {        // kiểu Việt Nam: 1.500.000,5
            $v = str_replace(',', '.', str_replace('.', '', $v));
        } elseif (preg_match('/^\d{1,3}(,\d{3})+(\.\d+)?$/', $v)) {  // kiểu quốc tế: 1,500,000.5
            $v = str_replace(',', '', $v);
        } elseif (preg_match('/^\d+,\d+$/', $v)) {                    // 1500,5
            $v = str_replace(',', '.', $v);
        }

        return is_numeric($v) ? (float) $v : $value;
    }
}
