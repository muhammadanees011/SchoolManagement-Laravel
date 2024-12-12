<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MyPurchase extends Model
{
    use HasFactory;

    public function shopItems()
    {
        return $this->hasOne(ShopItem::class,'id','shop_item_id');
    }

    public function user()
    {
        return $this->hasOne(User::class,'id','user_id');
    }

    public function installments()
    {
        return $this->hasMany(MyInstallments::class,'purchases_id');
    }
}
