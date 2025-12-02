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


    public function rank()
    {
        return $this->belongsTo(Rank::class, 'rank_id', 'rankid');
    }

    public function formatName()
    {
        $middleInitial = $this->m_name ? strtoupper(substr($this->m_name, 0, 1)) . '. ' : '';

        return trim(
            strtoupper($this->f_name) . ' ' .
                $middleInitial .
                strtoupper($this->l_name) . ' ' .
                strtoupper($this->suffix)
        );
    }

    public function formatContactNumber()
    {
        if (!$this->contact_num) {
            return 'N/A';
        }

        return '+63' . $this->contact_num;
    }
}
