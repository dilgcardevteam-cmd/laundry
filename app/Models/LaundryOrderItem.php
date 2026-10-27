<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['laundry_order_id', 'service_id', 'service_name', 'pricing_method', 'quantity', 'rate', 'minimum_charge', 'additional_charge', 'line_total'])]
class LaundryOrderItem extends Model
{
    protected function casts(): array
    {
        return ['quantity' => 'decimal:3', 'rate' => 'decimal:2', 'minimum_charge' => 'decimal:2', 'additional_charge' => 'decimal:2', 'line_total' => 'decimal:2'];
    }

    public function order()
    {
        return $this->belongsTo(LaundryOrder::class, 'laundry_order_id');
    }

    public function service()
    {
        return $this->belongsTo(Service::class);
    }
}
