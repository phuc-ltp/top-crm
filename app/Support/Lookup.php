<?php

namespace App\Support;

use App\Models\Campaign;
use App\Models\Customer;
use App\Models\Department;
use App\Models\LeadSource;
use App\Models\PipelineStage;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Tag;
use App\Models\User;

/** Danh sách lựa chọn cho các ô select trong form và bộ lọc. */
class Lookup
{
    public static function users(bool $activeOnly = true): array
    {
        return User::inOrganization()->when($activeOnly, fn ($q) => $q->active())->orderBy('name')->pluck('name', 'id')->all();
    }

    public static function sources(): array
    {
        return LeadSource::where('is_active', true)->orderBy('name')->pluck('name', 'id')->all();
    }

    public static function tags(): array
    {
        return Tag::orderBy('name')->pluck('name', 'id')->all();
    }

    public static function departments(): array
    {
        return Department::orderBy('name')->pluck('name', 'id')->all();
    }

    public static function stages(): array
    {
        // "Pipeline · Giai đoạn" khi tổ chức có nhiều pipeline
        $stages = PipelineStage::with('pipeline:id,name,is_default,position')->get()
            ->sortBy(fn ($s) => [$s->pipeline?->is_default ? 0 : 1, $s->pipeline?->position, $s->pipeline_id, $s->position]);
        $many = $stages->pluck('pipeline_id')->unique()->count() > 1;

        return $stages->mapWithKeys(fn ($s) => [$s->id => ($many ? __($s->pipeline?->name).' · ' : '').__($s->name)])->all();
    }

    public static function categories(): array
    {
        return ProductCategory::orderBy('name')->pluck('name', 'id')->all();
    }

    public static function campaigns(): array
    {
        return Campaign::orderByDesc('start_date')->pluck('name', 'id')->all();
    }

    /** Khách hàng mà người dùng được phép thấy. */
    public static function customers(User $user): array
    {
        return Customer::visibleTo($user)->orderBy('name')->limit(1000)->get(['id', 'code', 'name'])
            ->mapWithKeys(fn ($c) => [$c->id => $c->code.' — '.$c->name])->all();
    }

    public static function products()
    {
        return Product::active()->orderBy('name')->orderBy('variant_name')->get(['id', 'sku', 'name', 'variant_name', 'price', 'unit']);
    }
}
