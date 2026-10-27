<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['laundry_order_id', 'method', 'amount', 'cash_tendered', 'reference_number', 'verification_status', 'received_by', 'paid_at'])]
class Payment extends Model
{
    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'cash_tendered' => 'decimal:2', 'paid_at' => 'datetime'];
    }

    public function order()
    {
        return $this->belongsTo(LaundryOrder::class, 'laundry_order_id');
    }

    public function receiver()
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    public function reversals()
    {
        return $this->hasMany(PaymentReversal::class);
    }
}
