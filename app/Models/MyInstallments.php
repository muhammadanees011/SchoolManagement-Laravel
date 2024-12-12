<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MyInstallments extends Model
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

    public function purchase()
    {
        return $this->hasOne(MyPurchase::class,'id','purchases_id');
    }
}
