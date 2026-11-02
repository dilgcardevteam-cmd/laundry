<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['order_code', 'customer_id', 'tracking_token_hash', 'subtotal', 'discount', 'additional_charges', 'total_amount', 'total_paid', 'outstanding_balance', 'payment_status', 'laundry_status', 'received_at', 'expected_completion_at', 'released_at', 'cashier_id', 'instructions', 'remarks'])]
class LaundryOrder extends Model
{
    use SoftDeletes;

    protected function casts(): array
    {
        return ['subtotal' => 'decimal:2', 'discount' => 'decimal:2', 'additional_charges' => 'decimal:2', 'total_amount' => 'decimal:2', 'total_paid' => 'decimal:2', 'outstanding_balance' => 'decimal:2', 'received_at' => 'datetime', 'expected_completion_at' => 'datetime', 'released_at' => 'datetime'];
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function cashier()
    {
        return $this->belongsTo(User::class, 'cashier_id');
    }

    public function items()
    {
        return $this->hasMany(LaundryOrderItem::class);
    }

    public function addOns(): HasMany
    {
        return $this->hasMany(LaundryOrderAddOn::class);
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    public function statusHistory()
    {
        return $this->hasMany(LaundryStatusHistory::class);
    }

    public static function formatTrackingCode(string $token): string
    {
        return 'LND-'.implode('-', str_split(strtoupper($token), 4));
    }
}
