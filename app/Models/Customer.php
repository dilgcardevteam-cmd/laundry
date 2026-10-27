<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['code', 'name', 'contact_number', 'email', 'notes', 'status'])]
class Customer extends Model
{
    use SoftDeletes;

    public function orders()
    {
        return $this->hasMany(LaundryOrder::class);
    }
}
