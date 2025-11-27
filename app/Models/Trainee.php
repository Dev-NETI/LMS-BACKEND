<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class Trainee extends Authenticatable
{
    use Notifiable, HasApiTokens;

    protected $connection = 'main_db';
    protected $table = 'tbltraineeaccount';
    protected $primaryKey = 'traineeid';
    public $timestamps = true;

    protected $fillable = [
        'username',
        'password',
        'email',
        'f_name',
        'l_name',
        'is_active'
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'password' => 'hashed',
    ];

    public function getAuthIdentifierName()
    {
        return 'email';
    }

    public function getAuthPassword()
    {
        return $this->password;
    }
}
