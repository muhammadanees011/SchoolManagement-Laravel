<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Refund extends Model
{
    use HasFactory;

    public function purchase()
    {
        return $this->hasOne(MyPurchase::class,'id','purchase_id');
    }
}
