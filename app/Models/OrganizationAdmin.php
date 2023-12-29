<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrganizationAdmin extends Model
{
    use HasFactory;
    public function Admin(){
        return $this->hasOne(User::class,'id','user_id');
    }
    public function organization(){
        return $this->hasOne(Organization::class,'id','organization_id');
    }
}
