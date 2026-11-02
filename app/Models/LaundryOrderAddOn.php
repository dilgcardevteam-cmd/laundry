<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['laundry_order_id', 'add_on_id', 'name', 'quantity', 'price', 'line_total'])]
class LaundryOrderAddOn extends Model
{
    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'price' => 'decimal:2',
            'line_total' => 'decimal:2',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(LaundryOrder::class, 'laundry_order_id');
    }

    public function addOn(): BelongsTo
    {
        return $this->belongsTo(AddOn::class)->withTrashed();
    }
}
