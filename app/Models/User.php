<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

use Filament\Models\Contracts\FilamentUser;

class User extends Authenticatable implements FilamentUser
{
    use HasFactory, Notifiable, HasApiTokens;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'telegram_id'
    ];

    public function drivers()
    {
        return $this->hasMany(Driver::class, 'id');
    }

    public function driver()
    {
        return $this->hasOne(Driver::class);
    }

    public function clients()
    {
        return $this->hasMany(Client::class, 'id');
    }

    public function client()
    {
        return $this->hasOne(Client::class);
    }

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    // public function canAccessPanel(\Filament\Panel $panel): bool
    // {
    //     return $this->email === 'admin@example.com'; // подставь свой email
    // }
    public function canAccessPanel(\Filament\Panel $panel): bool
    {
        // Вариант: разрешить только администраторам
        return $this->role === 'admin'; // или любая твоя проверка
    }
}
