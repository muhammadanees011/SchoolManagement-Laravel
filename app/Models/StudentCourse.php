<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StudentCourse extends Model
{
    use HasFactory;
    protected $table='student_course';

    public function student()
    {
        return $this->hasOne(Student::class,'student_id','StudentID');
    }
}
