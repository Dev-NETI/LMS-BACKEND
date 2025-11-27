<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Schedule extends Model
{
    protected $connection = 'main_db';
    protected $table = 'tblcourseschedule';
    protected $primaryKey = 'scheduleid';
    public $timestamps = false;

    protected $fillable = [
        'batchno',
        'startdateformat',
        'enddateformat'
    ];
}
