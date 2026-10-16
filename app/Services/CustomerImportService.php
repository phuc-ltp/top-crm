<?php

namespace App\Services;

use App\Imports\RowsImport;
use App\Models\Customer;
use App\Models\LeadSource;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Nhập khách hàng từ CSV hoặc Excel (.xlsx). CSV chấp nhận dấu phân cách "," hoặc ";".
 * Tiêu đề cột tiếng Việt hoặc tiếng Anh. Dòng lỗi hoặc trùng (email/điện thoại) được bỏ qua và báo cáo lại.
 * Cả tệp được nhập trong MỘT giao dịch: lỗi hệ thống giữa chừng → không lưu bản ghi nào.
 */
class CustomerImportService
{
    public const TEMPLATE_HEADINGS = ['name', 'phone', 'email', 'address', 'country', 'company', 'type', 'source', 'status',
        'segment', 'lifecycle_stage', 'preferred_channel', 'marketing_consent', 'marketing_consent_source'];

    private const MAX_ROWS = 5000;

    private const ALIASES = [
        'name' => ['name', 'ten', 'tên', 'tên khách hàng', 'ten khach hang', 'customer name', 'họ tên'],
        'phone' => ['phone', 'điện thoại', 'dien thoai', 'số điện thoại', 'sdt', 'sđt'],
        'email' => ['email', 'e-mail', 'thư điện tử'],
        'address' => ['address', 'địa chỉ', 'dia chi'],
        'country' => ['country', 'quốc gia', 'quoc gia'],
        'company' => ['company', 'công ty', 'cong ty'],
        'type' => ['type', 'loại', 'loai', 'customer type'],
        'source' => ['source', 'nguồn', 'nguon'],
        'status' => ['status', 'trạng thái', 'trang thai'],
        'segment' => ['segment', 'phân khúc', 'phan khuc'],
        'lifecycle_stage' => ['lifecycle_stage', 'lifecycle', 'vòng đời', 'giai đoạn vòng đời'],
        'preferred_channel' => ['preferred_channel', 'channel', 'kênh liên lạc', 'kênh liên lạc ưa thích'],
        'marketing_consent' => ['marketing_consent', 'consent', 'đồng ý marketing'],
        'marketing_consent_source' => ['marketing_consent_source', 'consent_source', 'nguồn đồng ý'],
    ];

    public function __construct(private CustomerService $customers) {}

    /** @return list<list<string>> mọi dòng (dòng đầu là tiêu đề) */
    private function readRows(UploadedFile $file): array
    {
        $extension = strtolower($file->getClientOriginalExtension());
        if (in_array($extension, ['xlsx', 'xls'], true)) {
            $sheet = Excel::toArray(new RowsImport, $file)[0] ?? [];

            return array_map(fn ($row) => array_map(fn ($v) => $this->cell($v), $row), $sheet);
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

    /** Ô bảng tính → chuỗi (số điện thoại kiểu số mất số 0 đầu sẽ được bù khi chuẩn hóa). */
    private function cell($value): string
    {
        if (is_float($value) && floor($value) === $value) {
            return number_format($value, 0, '', '');
        }

        return trim((string) $value);
    }

    public function import(UploadedFile $file, User $actor): array
    {
        $rows = $this->readRows($file);
        $header = array_shift($rows);
        if (! $header) {
            return ['created' => 0, 'skipped' => 0, 'errors' => [__('Tệp trống hoặc không đọc được.')]];
        }
        $map = $this->mapHeader($header);
        // Cột trường tùy biến: khớp theo mã trường (key) hoặc tên trường (label)
        $customFields = \App\Models\CustomFieldDefinition::for('customer')->active()->get();
        $customMap = [];
        foreach ($header as $index => $title) {
            $title = mb_strtolower(trim(preg_replace('/^\xEF\xBB\xBF/', '', (string) $title)));
            $field = $customFields->first(fn ($f) => $title === mb_strtolower($f->key) || $title === mb_strtolower($f->label));
            if ($field && ! isset($customMap[$field->key])) {
                $customMap[$field->key] = $index;
            }
        }
        if (! isset($map['name'])) {
            return ['created' => 0, 'skipped' => 0, 'errors' => [__('Không tìm thấy cột "name" (Tên khách hàng). Hãy dùng tệp mẫu.')]];
        }

        $sources = LeadSource::pluck('id', 'name')->mapWithKeys(fn ($id, $name) => [mb_strtolower($name) => $id]);
        $countries = array_change_key_case(array_flip(array_map(fn ($n) => mb_strtolower($n[0]), config('crm.countries', []))), CASE_LOWER)
            + array_flip(array_map(fn ($n) => mb_strtolower($n[1]), config('crm.countries', [])));
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

                $get = fn ($key) => isset($map[$key]) ? trim((string) ($row[$map[$key]] ?? '')) : '';
                $country = strtoupper($get('country'));
                $country = array_key_exists($country, config('crm.countries', [])) ? $country : ($countries[mb_strtolower($get('country'))] ?? null);
                $consent = in_array(mb_strtolower($get('marketing_consent')), ['1', 'yes', 'y', 'true', 'có', 'co', 'x', 'đồng ý'], true);
                $data = [
                    'name' => $get('name'),
                    'phone' => $this->phone($get('phone')),
                    'email' => $get('email') ?: null,
                    'address' => $get('address') ?: null,
                    'country' => $country,
                    'company' => $get('company') ?: null,
                    'type' => in_array($get('type'), array_keys(Customer::TYPES), true) ? $get('type')
                        : (in_array(mb_strtolower($get('type')), ['doanh nghiệp', 'doanh nghiep', 'company'], true) ? 'business' : 'individual'),
                    'status' => in_array($get('status'), array_keys(Customer::STATUSES), true) ? $get('status') : 'active',
                    'segment' => array_key_exists($get('segment'), Customer::SEGMENTS) ? $get('segment') : null,
                    'lifecycle_stage' => array_key_exists($get('lifecycle_stage'), Customer::LIFECYCLE_STAGES) ? $get('lifecycle_stage') : null,
                    'preferred_channel' => array_key_exists($get('preferred_channel'), Customer::CHANNELS) ? $get('preferred_channel') : null,
                    // Đồng ý marketing chỉ khi tệp ghi rõ; nguồn mặc định ghi tên tệp để truy vết
                    'marketing_consent' => $consent,
                    'marketing_consent_source' => $consent ? (mb_substr($get('marketing_consent_source'), 0, 100) ?: mb_substr(__('Nhập từ tệp :file', ['file' => $label]), 0, 100)) : null,
                    'lead_source_id' => $sources[mb_strtolower($get('source'))] ?? null,
                    'assigned_to' => $actor->id,
                ];

                $custom = [];
                foreach ($customMap as $key => $index) {
                    $raw = trim((string) ($row[$index] ?? ''));
                    $field = $customFields->firstWhere('key', $key);
                    $custom[$key] = $field->type === 'checkbox'
                        ? in_array(mb_strtolower($raw), ['1', 'yes', 'y', 'true', 'có', 'co', 'x'], true)
                        : ($raw === '' ? null : $raw);
                }
                $data['custom'] = $custom;

                $validator = Validator::make($data, $customFields->mapWithKeys(fn ($f) => ['custom.'.$f->key => array_key_exists($f->key, $customMap) ? $f->rules() : ['nullable']])->all() + [
                    'name' => 'required|string|max:255',
                    'email' => 'nullable|email|max:255',
                    'phone' => ['nullable', 'max:30', 'regex:/^[0-9+\-\s().]{6,30}$/'],
                    'address' => 'nullable|max:500',
                    'company' => 'nullable|max:255',
                ], [], $customFields->mapWithKeys(fn ($f) => ['custom.'.$f->key => $f->label])->all());
                if ($validator->fails()) {
                    $skipped++;
                    $errors[] = __('Dòng :line: :error', ['line' => $line, 'error' => $validator->errors()->first()]);

                    continue;
                }

                if ($this->customers->findDuplicates($data['email'], $data['phone'])->isNotEmpty()) {
                    $skipped++;
                    $errors[] = __('Dòng :line: trùng email/điện thoại với khách hàng đã có (:name).', ['line' => $line, 'name' => $data['name']]);

                    continue;
                }

                $this->customers->save(new Customer, $data, $actor);
                $created++;
            }
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            report($e);

            return ['created' => 0, 'skipped' => $skipped, 'errors' => [__('Lỗi khi nhập dữ liệu, không có bản ghi nào được lưu: :error', ['error' => $e->getMessage()])]];
        }

        ActivityLogger::log('imported', null, "Nhập $created khách hàng từ tệp $label (bỏ qua $skipped dòng)");

        return ['created' => $created, 'skipped' => $skipped, 'errors' => array_slice($errors, 0, 100)];
    }

    /** Excel thường làm mất số 0 đầu của số điện thoại (VD 901234567) → bù lại. */
    private function phone(string $value): ?string
    {
        if ($value === '') {
            return null;
        }
        if (preg_match('/^[1-9]\d{8}$/', $value)) {
            return '0'.$value;
        }

        return $value;
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
