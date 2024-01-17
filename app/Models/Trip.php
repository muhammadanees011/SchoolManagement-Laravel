<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Trip extends Model
{
    use HasFactory;
    protected $casts = [
        'attributes' => 'json',
    ];

    public function Organization()
    {
        return $this->hasOne(Organization::class,'id','organization_id');
    }
}
