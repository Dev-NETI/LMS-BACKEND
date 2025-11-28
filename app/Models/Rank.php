<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Rank extends Model
{
    protected $connection = 'main_db';
    protected $table = 'tblrank';
    protected $primaryKey = 'id';
    public $timestamps = false;
}
