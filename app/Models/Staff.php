<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Staff extends Model
{
    use HasFactory;
    public function User()
    {
        return $this->hasOne(User::class,'id','user_id');
    }
    public function School()
    {
        return $this->hasOne(School::class,'id','school_id');
    }
}
