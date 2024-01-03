<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Parents extends Model
{
    use HasFactory;
    protected $table="student_parent";

    public function User()
    {
        return $this->hasOne(User::class,'id','parent_id');
    }
    public function Student()
    {
        return $this->hasOne(User::class,'id','student_id');
    }
}
