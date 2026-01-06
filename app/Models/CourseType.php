<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CourseType extends Model
{
    protected $connection = 'main_db';
    protected $table = 'tblcoursetype';
    protected $primaryKey = 'coursetypeid';
    public $timestamps = false;
}
