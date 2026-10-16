<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Trường tùy biến do tổ chức tự định nghĩa (hiện cho khách hàng). Giá trị lưu trong customers.custom_fields (JSON)
 * theo khóa `key`. Ẩn trường (is_active = false) không xóa dữ liệu đã nhập.
 */
class CustomFieldDefinition extends Model
{
    use BelongsToOrganization, LogsActivity;

    public const TYPES = [
        'text' => 'Chữ (một dòng)',
        'textarea' => 'Đoạn văn',
        'number' => 'Số',
        'date' => 'Ngày',
        'select' => 'Danh sách chọn',
        'checkbox' => 'Có / Không',
    ];

    public const ENTITIES = ['customer'];

    /** Tối đa số trường mỗi đối tượng mỗi tổ chức (giữ biểu mẫu gọn, truy vấn nhanh). */
    public const MAX_PER_ENTITY = 30;

    protected $fillable = ['entity', 'key', 'label', 'type', 'options', 'is_required', 'is_active', 'position'];

    protected $casts = ['options' => 'array', 'is_required' => 'boolean', 'is_active' => 'boolean'];

    public function scopeFor(Builder $query, string $entity): Builder
    {
        return $query->where('entity', $entity)->orderBy('position')->orderBy('id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /** Khóa ổn định sinh từ nhãn (chữ thường, không dấu, gạch dưới), duy nhất trong tổ chức. */
    public static function makeKey(string $label, string $entity = 'customer'): string
    {
        $base = Str::limit(Str::slug(Str::ascii($label), '_'), 40, '') ?: 'truong';
        if (! preg_match('/^[a-z]/', $base)) {
            $base = 'f_'.$base;
        }
        $key = $base;
        for ($i = 2; static::where('entity', $entity)->where('key', $key)->exists(); $i++) {
            $key = $base.'_'.$i;
        }

        return $key;
    }

    /** Quy tắc kiểm tra giá trị nhập cho trường này. */
    public function rules(): array
    {
        $base = $this->is_required && $this->type !== 'checkbox' ? ['required'] : ['nullable'];

        return array_merge($base, match ($this->type) {
            'number' => ['numeric', 'between:-999999999999,999999999999'],
            'date' => ['date'],
            'select' => [Rule::in($this->options ?? [])],
            'checkbox' => ['boolean'],
            'textarea' => ['string', 'max:2000'],
            default => ['string', 'max:255'],
        });
    }

    /** Chuẩn hóa giá trị trước khi lưu. */
    public function normalize($value)
    {
        if ($value === null || $value === '') {
            return $this->type === 'checkbox' ? false : null;
        }

        return match ($this->type) {
            'number' => $value + 0,
            'checkbox' => (bool) $value,
            'date' => \Illuminate\Support\Carbon::parse($value)->toDateString(),
            default => (string) $value,
        };
    }

    /** Giá trị hiển thị cho người dùng. */
    public function display($value): string
    {
        if ($value === null || $value === '') {
            return '—';
        }

        return match ($this->type) {
            'checkbox' => $value ? __('Có') : __('Không'),
            'date' => vn_date($value),
            'number' => rtrim(rtrim(number_format((float) $value, 2, ',', '.'), '0'), ','),
            default => (string) $value,
        };
    }
}
