<?php

namespace App\Support\Tenancy;

use Illuminate\Database\Eloquent\Model;
use Spatie\Permission\Contracts\PermissionsTeamResolver;

/**
 * Cho spatie/laravel-permission biết "team" hiện tại = tổ chức đang làm việc,
 * để vai trò/quyền luôn được tra cứu đúng tổ chức (kể cả trong job, lệnh và test).
 */
class OrganizationTeamResolver implements PermissionsTeamResolver
{
    public function getPermissionsTeamId(): int|string|null
    {
        return app(CurrentOrganization::class)->id();
    }

    public function setPermissionsTeamId($id): void
    {
        app(CurrentOrganization::class)->set($id instanceof Model ? (int) $id->getKey() : ($id === null ? null : (int) $id));
    }
}
