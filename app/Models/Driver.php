<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Driver extends Model
{
    protected $fillable = [
        'user_id',
        'first_name',
        'last_name',
        'status',
        'license_number',
        'car_model',
        'country',
        'city',
        'telegram_id',
        'license_photo',
        'car_photo'
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
