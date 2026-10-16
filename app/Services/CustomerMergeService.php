<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Gộp khách hàng trùng: chuyển MỌI dữ liệu liên quan của $remove sang $keep, bổ sung thông tin còn trống,
 * rồi lưu trữ $remove (xóa mềm, merged_into_id) — không xóa cứng, có nhật ký đầy đủ.
 */
class CustomerMergeService
{
    /** Bảng tham chiếu trực tiếp customers.id. */
    private const FOREIGN = [
        'contacts' => 'customer_id',
        'opportunities' => 'customer_id',
        'quotations' => 'customer_id',
        'sales_orders' => 'customer_id',
        'invoices' => 'customer_id',
        'support_tickets' => 'customer_id',
        'leads' => 'converted_customer_id',
    ];

    /** Bảng tham chiếu đa hình (type = 'customer'). */
    private const MORPHS = [
        'interactions' => 'interactable',
        'attachments' => 'attachable',
        'tasks' => 'related',
    ];

    /** Trường được bổ sung khi bản giữ lại còn trống. */
    private const FILLABLE_GAPS = ['phone', 'email', 'address', 'country', 'company', 'segment', 'lifecycle_stage', 'preferred_channel', 'lead_source_id'];

    /** Số bản ghi sẽ được chuyển (dùng để xem trước). */
    public function preview(Customer $remove): array
    {
        $counts = [];
        foreach (self::FOREIGN as $table => $column) {
            $counts[$table] = DB::table($table)->where($column, $remove->id)->count();
        }
        foreach (self::MORPHS as $table => $morph) {
            $counts[$table] = DB::table($table)->where($morph.'_type', 'customer')->where($morph.'_id', $remove->id)->count();
        }
        $counts['tags'] = $remove->tags()->count();

        return $counts;
    }

    public function merge(Customer $keep, Customer $remove, User $actor): array
    {
        if ($keep->is($remove)) {
            throw new \InvalidArgumentException('Không thể gộp khách hàng với chính nó.');
        }

        return DB::transaction(function () use ($keep, $remove, $actor) {
            // Khóa cả hai bản ghi để không ai sửa giữa chừng
            $keep = Customer::whereKey($keep->id)->lockForUpdate()->firstOrFail();
            $remove = Customer::whereKey($remove->id)->lockForUpdate()->firstOrFail();
            $orgId = (int) $keep->organization_id;
            abort_unless((int) $remove->organization_id === $orgId, 404);

            $moved = [];
            foreach (self::FOREIGN as $table => $column) {
                $moved[$table] = DB::table($table)->where('organization_id', $orgId)->where($column, $remove->id)->update([$column => $keep->id]);
            }
            foreach (self::MORPHS as $table => $morph) {
                $moved[$table] = DB::table($table)->where('organization_id', $orgId)
                    ->where($morph.'_type', 'customer')->where($morph.'_id', $remove->id)->update([$morph.'_id' => $keep->id]);
            }
            $tagIds = $remove->tags()->pluck('tags.id')->all();
            $keep->tags()->syncWithoutDetaching($tagIds);
            $remove->tags()->detach();
            $moved['tags'] = count($tagIds);

            // Bổ sung thông tin còn trống
            $filled = [];
            foreach (self::FILLABLE_GAPS as $field) {
                if (blank($keep->{$field}) && filled($remove->{$field})) {
                    $keep->{$field} = $remove->{$field};
                    $filled[] = $field;
                }
            }
            $keep->custom_fields = array_merge(array_filter((array) $remove->custom_fields, fn ($v) => $v !== null && $v !== ''),
                array_filter((array) $keep->custom_fields, fn ($v) => $v !== null && $v !== ''));
            if (! $keep->marketing_consent && $remove->marketing_consent) {
                $keep->forceFill(['marketing_consent' => true, 'marketing_consent_source' => $remove->marketing_consent_source]);
            }
            if (filled($remove->notes)) {
                $keep->notes = trim(($keep->notes ? $keep->notes."\n\n" : '').__('[Gộp từ :code] ', ['code' => $remove->code]).$remove->notes);
            }
            $keep->updated_by = $actor->id;
            $keep->save();
            if (! $keep->marketing_consent_at && $remove->marketing_consent_at && $keep->marketing_consent) {
                $keep->forceFill(['marketing_consent_at' => $remove->marketing_consent_at])->saveQuietly();   // giữ thời điểm đồng ý gốc
            }

            // Lưu trữ bản bị gộp
            $remove->forceFill(['merged_into_id' => $keep->id, 'status' => 'inactive', 'updated_by' => $actor->id])->saveQuietly();
            $remove->delete();

            $summary = collect($moved)->filter()->map(fn ($n, $k) => "$k: $n")->implode(', ') ?: 'không có dữ liệu liên quan';
            ActivityLogger::log('merged', $keep, 'Gộp khách hàng '.$remove->code.' ('.$remove->name.') vào '.$keep->code.' — chuyển '.$summary
                .($filled ? '; bổ sung: '.implode(', ', $filled) : ''), ['merged_customer_id' => $remove->id, 'moved' => $moved, 'filled' => $filled]);
            ActivityLogger::log('merged', $remove, 'Đã gộp vào khách hàng '.$keep->code.' ('.$keep->name.')', ['merged_into_id' => $keep->id]);

            return ['moved' => $moved, 'filled' => $filled];
        });
    }
}
