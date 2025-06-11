<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Driver extends Model
{
    protected $fillable = [
        'user_id',
        'full_name',
        'status',
        'license_number',
        'license_photo',
        'telegram_id',
        'car_model',
        'car_photo',
        'country',
        'city'
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
