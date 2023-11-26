<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class School extends Model
{
    use HasFactory;
    public function User()
    {
        return $this->hasOne(User::class,'id','user_id');
    }
    public function Organization()
    {
        return $this->hasOne(User::class,'id','organization_id');
    }
}
