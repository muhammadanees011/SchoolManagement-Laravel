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

    public function Cart()
    {
        return $this->hasOne(UserCart::class,'trip_id','id');
    }

    public function participants()
    {
        return $this->hasMany(TripParticipant::class,'trip_id','id');    
    }
}
