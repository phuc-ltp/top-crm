<?php

namespace App\Support;

use App\Models\Lead;
use App\Models\LeadStage;
use App\Support\Tenancy\CurrentOrganization;

/**
 * Bộ nhớ đệm giai đoạn lead theo tổ chức trong một yêu cầu (scoped singleton).
 * Tự làm mới khi giai đoạn được lưu / xóa (LeadStage::booted).
 */
class LeadStages
{
    /** @var array<int, \Illuminate\Support\Collection<int, LeadStage>> */
    private array $cache = [];

    public function all()
    {
        $orgId = (int) app(CurrentOrganization::class)->id();

        return $this->cache[$orgId] ??= LeadStage::query()->orderBy('position')->orderBy('id')->get();
    }

    /** [key => [tên, màu]]; tổ chức chưa có cấu hình → giá trị mặc định. */
    public function map(): array
    {
        $stages = $this->all();

        return $stages->isNotEmpty()
            ? $stages->mapWithKeys(fn ($s) => [$s->key => [$s->name, $s->color]])->all()
            : Lead::STATUSES;
    }

    public function isQualified(?string $key): bool
    {
        $stage = $this->all()->firstWhere('key', $key);

        return $stage ? $stage->is_qualified : $key === 'qualified';
    }

    public function flush(): void
    {
        $this->cache = [];
    }
}
