<?php

namespace App\Models;

class InvoiceItem extends LineItem
{
    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }
}
