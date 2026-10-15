<?php

namespace App\Models;

class SalesOrderItem extends LineItem
{
    public function salesOrder()
    {
        return $this->belongsTo(SalesOrder::class);
    }
}
