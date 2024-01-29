<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserLegacyPermission extends Model
{
    use HasFactory;

    public function permission(){
        return $this->hasOne(Permission::class,'id','permission_id');
    }
}
