<?php

namespace App\Models;

class QuotationItem extends LineItem
{
    public function quotation()
    {
        return $this->belongsTo(Quotation::class);
    }
}
