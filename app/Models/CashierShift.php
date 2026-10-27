<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['user_id', 'opened_at', 'closed_at', 'opening_cash', 'closing_cash', 'expected_cash', 'notes'])]
class CashierShift extends Model
{
    protected function casts(): array
    {
        return ['opened_at' => 'datetime', 'closed_at' => 'datetime', 'opening_cash' => 'decimal:2', 'closing_cash' => 'decimal:2', 'expected_cash' => 'decimal:2'];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
