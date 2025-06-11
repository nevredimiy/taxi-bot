<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Client extends Model
{
    protected $fillable = [
        'user_id',
        'full_name',
        'phone',
        'telegram_id'
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
