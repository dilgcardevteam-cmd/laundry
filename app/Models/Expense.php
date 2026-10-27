<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['category', 'description', 'amount', 'expense_date', 'created_by'])]
class Expense extends Model
{
    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'expense_date' => 'date'];
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
