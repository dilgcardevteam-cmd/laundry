<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['laundry_order_id', 'previous_status', 'new_status', 'changed_by', 'remarks', 'changed_at'])]
class LaundryStatusHistory extends Model
{
    protected function casts(): array
    {
        return ['changed_at' => 'datetime'];
    }

    public function order()
    {
        return $this->belongsTo(LaundryOrder::class, 'laundry_order_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
