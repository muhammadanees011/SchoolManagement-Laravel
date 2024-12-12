<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserCart extends Model
{
    use HasFactory;
    protected $table="user_cart";
    public function ShopItem()
    {
        return $this->hasOne(ShopItem::class,'id','shop_item_id');
    }

    public function Installment()
    {
        return $this->hasOne(MyInstallments::class,'id','installment_id');
    }
}
