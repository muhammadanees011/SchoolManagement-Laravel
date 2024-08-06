<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ShopItem extends Model
{
    use HasFactory;
    protected $casts = [
        'attributes' => 'json',
        'limit_colleges' => 'json',
        'limit_courses' => 'json',
        'visibility_options' => 'json',
    ];

    public function schoolShop()
    {
        return $this->belongsTo(SchoolShop::class);
    }

    public function Attribute()
    {
        return $this->hasOne(Attribute::class,'id','attribute_id');
    }

    public function payment()
    {
        return  $this->hasOne(PaymentPlan::class,'shop_item_id','id');
    }
}
