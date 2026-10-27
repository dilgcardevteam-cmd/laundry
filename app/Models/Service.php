<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['name', 'slug', 'description', 'pricing_method', 'rate', 'minimum_weight', 'minimum_charge', 'additional_charge', 'estimated_processing_hours', 'is_active'])]
class Service extends Model
{
    use SoftDeletes;

    protected function casts(): array
    {
        return ['rate' => 'decimal:2', 'minimum_weight' => 'decimal:3', 'minimum_charge' => 'decimal:2', 'additional_charge' => 'decimal:2', 'is_active' => 'boolean'];
    }

    public function orderItems()
    {
        return $this->hasMany(LaundryOrderItem::class);
    }
}
