<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Order extends Model
{
    protected $fillable = [
        'client_id',
        'driver_id',
        'status',
        'pickup_address',
        'destination_address',
        'budget',
        'details'
    ];

    public function client()
    {
        return $this->belongsTo(Client::class, 'client_id');
    }

    public function driver()
    {
        return $this->belongsTo(Driver::class, 'driver_id');
    }

    public function getFullNameAttribute(): string
    {
        return "{$this->first_name} {$this->last_name}";
    }
}
