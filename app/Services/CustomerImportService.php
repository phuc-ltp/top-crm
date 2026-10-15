<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\LeadSource;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

/**
 * Nhập khách hàng từ CSV. Chấp nhận dấu phân cách "," hoặc ";", tiêu đề tiếng Việt hoặc tiếng Anh.
 * Dòng lỗi hoặc trùng (email/điện thoại) được bỏ qua và báo cáo lại.
 */
class CustomerImportService
{
    public const TEMPLATE_HEADINGS = ['name', 'phone', 'email', 'address', 'company', 'type', 'source', 'status'];

    private const MAX_ROWS = 5000;

    private const ALIASES = [
        'name' => ['name', 'ten', 'tên', 'tên khách hàng', 'ten khach hang', 'customer name', 'họ tên'],
        'phone' => ['phone', 'điện thoại', 'dien thoai', 'số điện thoại', 'sdt', 'sđt'],
        'email' => ['email', 'e-mail', 'thư điện tử'],
        'address' => ['address', 'địa chỉ', 'dia chi'],
        'company' => ['company', 'công ty', 'cong ty'],
        'type' => ['type', 'loại', 'loai', 'customer type'],
        'source' => ['source', 'nguồn', 'nguon'],
        'status' => ['status', 'trạng thái', 'trang thai'],
    ];

    public function __construct(private CustomerService $customers) {}

    public function import(UploadedFile $file, User $actor): array
    {
        $handle = fopen($file->getRealPath(), 'r');
        $firstLine = fgets($handle);
        $delimiter = substr_count((string) $firstLine, ';') > substr_count((string) $firstLine, ',') ? ';' : ',';
        rewind($handle);

        $header = fgetcsv($handle, 0, $delimiter);
        if (! $header) {
            return ['created' => 0, 'skipped' => 0, 'errors' => ['Tệp trống hoặc không đọc được.']];
        }
        $map = $this->mapHeader($header);
        if (! isset($map['name'])) {
            fclose($handle);

            return ['created' => 0, 'skipped' => 0, 'errors' => ['Không tìm thấy cột "name" (Tên khách hàng). Hãy dùng tệp mẫu.']];
        }

        $sources = LeadSource::pluck('id', 'name')->mapWithKeys(fn ($id, $name) => [mb_strtolower($name) => $id]);
        $created = 0;
        $skipped = 0;
        $errors = [];
        $line = 1;

        DB::beginTransaction();
        try {
            while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
                $line++;
                if ($line - 1 > self::MAX_ROWS) {
                    $errors[] = 'Chỉ nhập tối đa '.self::MAX_ROWS.' dòng mỗi lần.';
                    break;
                }
                if (count(array_filter($row, fn ($v) => trim((string) $v) !== '')) === 0) {
                    continue;
                }

                $get = fn ($key) => isset($map[$key]) ? trim((string) ($row[$map[$key]] ?? '')) : '';
                $data = [
                    'name' => $get('name'),
                    'phone' => $get('phone') ?: null,
                    'email' => $get('email') ?: null,
                    'address' => $get('address') ?: null,
                    'company' => $get('company') ?: null,
                    'type' => in_array($get('type'), array_keys(Customer::TYPES), true) ? $get('type')
                        : (in_array(mb_strtolower($get('type')), ['doanh nghiệp', 'doanh nghiep', 'company'], true) ? 'business' : 'individual'),
                    'status' => in_array($get('status'), array_keys(Customer::STATUSES), true) ? $get('status') : 'active',
                    'lead_source_id' => $sources[mb_strtolower($get('source'))] ?? null,
                    'assigned_to' => $actor->id,
                ];

                $validator = Validator::make($data, [
                    'name' => 'required|string|max:255',
                    'email' => 'nullable|email|max:255',
                    'phone' => ['nullable', 'max:30', 'regex:/^[0-9+\-\s().]{6,30}$/'],
                    'address' => 'nullable|max:500',
                    'company' => 'nullable|max:255',
                ]);
                if ($validator->fails()) {
                    $skipped++;
                    $errors[] = "Dòng $line: ".$validator->errors()->first();

                    continue;
                }

                if ($this->customers->findDuplicates($data['email'], $data['phone'])->isNotEmpty()) {
                    $skipped++;
                    $errors[] = "Dòng $line: trùng email/điện thoại với khách hàng đã có ({$data['name']}).";

                    continue;
                }

                $this->customers->save(new Customer, $data, $actor);
                $created++;
            }
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            fclose($handle);
            report($e);

            return ['created' => 0, 'skipped' => $skipped, 'errors' => ['Lỗi khi nhập dữ liệu, không có bản ghi nào được lưu: '.$e->getMessage()]];
        }
        fclose($handle);

        ActivityLogger::log('imported', null, "Nhập $created khách hàng từ CSV (bỏ qua $skipped dòng)");

        return ['created' => $created, 'skipped' => $skipped, 'errors' => array_slice($errors, 0, 100)];
    }

    private function mapHeader(array $header): array
    {
        $map = [];
        foreach ($header as $index => $title) {
            $title = mb_strtolower(trim(preg_replace('/^\xEF\xBB\xBF/', '', (string) $title)));
            foreach (self::ALIASES as $key => $aliases) {
                if (in_array($title, $aliases, true) && ! isset($map[$key])) {
                    $map[$key] = $index;
                }
            }
        }

        return $map;
    }
}
