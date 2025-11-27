<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Course extends Model
{
    protected $connection = 'main_db';
    protected $table = 'tblcourses';
    protected $primaryKey = 'courseid';
    public $timestamps = false;

    protected $fillable = [
        'coursetitle',
        'coursedescription',
        'courseduration',
        'coursetype',
        'courseimage',
        'coursestatus'
    ];
}
