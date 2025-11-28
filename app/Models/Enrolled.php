<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Enrolled extends Model
{
    protected $connection = 'main_db';
    protected $table = 'tblenroled';
    protected $primaryKey = 'enroledid';
    public $timestamps = false;

    protected $fillable = [
        'traineeid',
        'courseid',
        'dateregistered',
        'datecompleted',
        'status'
    ];

    public function course()
    {
        return $this->belongsTo(Course::class, 'courseid');
    }

    public function trainee()
    {
        return $this->belongsTo(Trainee::class, 'traineeid');
    }

    public function schedule()
    {
        return $this->belongsTo(Schedule::class, 'scheduleid');
    }
}
