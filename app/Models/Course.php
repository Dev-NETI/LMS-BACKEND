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

    public function schedule()
    {
        return $this->hasMany(Schedule::class, 'courseid', 'courseid');
    }

    public function coursetype()
    {
        return $this->belongsTo(CourseType::class, 'coursetypeid', 'coursetypeid');
    }

    public function modeofdelivery()
    {
        return $this->belongsTo(ModeOfDelivery::class, 'modeofdeliveryid');
    }
}
