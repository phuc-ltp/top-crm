<?php

namespace App\Policies;

class SupplierPolicy extends CrmPolicy
{
    protected string $module = 'suppliers';

    protected bool $ownership = false;
}
