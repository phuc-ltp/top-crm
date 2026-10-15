<?php

namespace App\Policies;

class ProductPolicy extends CrmPolicy
{
    protected string $module = 'products';

    protected bool $ownership = false;
}