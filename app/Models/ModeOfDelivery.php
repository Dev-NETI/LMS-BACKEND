<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ModeOfDelivery extends Model
{
    protected $connection = 'main_db';
    protected $table = 'tblmodeofdelivery';
    public $timestamps = false;
}
