<?php

namespace App\Models\Scopes;

use App\Support\Tenancy\CurrentOrganization;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Chỉ trả về bản ghi của tổ chức đang làm việc. Không xác định được tổ chức thì không trả về gì
 * (đóng chặn mặc định), thay vì lộ dữ liệu của mọi tổ chức.
 */
class OrganizationScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $id = app(CurrentOrganization::class)->id();

        if ($id === null) {
            $builder->whereRaw('1 = 0');

            return;
        }

        $builder->where($model->qualifyColumn('organization_id'), $id);
    }
}
