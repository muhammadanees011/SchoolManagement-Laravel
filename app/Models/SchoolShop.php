<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SchoolShop extends Model
{
    use HasFactory;

    public function shopItems()
    {
        return $this->hasMany(ShopItem::class,'shop_id','id');
    }
}
