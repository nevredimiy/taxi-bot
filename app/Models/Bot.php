<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Bot extends Model
{
    protected $fillable = [
        'bot_id',
        'token',
        'active',
        'webhook_url'
    ];
}
