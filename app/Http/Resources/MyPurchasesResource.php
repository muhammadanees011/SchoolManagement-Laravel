<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MyPurchasesResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'shop_item_id' => $this->shopItems->id,
            'shop_id' => $this->shopItems->shop_id,
            'buyer_name' => $this->user->first_name.' '.$this->user->last_name,
            'buyer_email' => $this->user->email,
            'name' => $this->shopItems->name,
            'product_type' => $this->shopItems->product_type,
            'image' => $this->shopItems->image,
            'detail' => $this->shopItems->detail,
            'price' => $this->shopItems->price,
            'total_price' => $this->total_price,
            'amount_paid' => $this->amount_paid,
            'payment_plan' => $this->shopItems->payment_plan,
            'refund_status' => $this->refund_status,
            'payment_status' => $this->payment_status,
            'created_at' => $this->created_at,
        ];
    }
}
