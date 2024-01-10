<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ShopItem extends Model
{
    use HasFactory;

    public function schoolShop()
    {
        return $this->belongsTo(SchoolShop::class);
    }

    public function Attribute()
    {
        return $this->hasOne(Attribute::class,'id','attribute_id');
    }
}
