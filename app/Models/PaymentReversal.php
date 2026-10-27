<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['payment_id', 'amount', 'reason', 'created_by', 'reversed_at'])]
class PaymentReversal extends Model
{
    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'reversed_at' => 'datetime'];
    }

    public function payment()
    {
        return $this->belongsTo(Payment::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
