<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MyInstallmentsRescource extends JsonResource
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
            'name' => $this->shopItems->name,
            'buyer_name' => $this->user->first_name.' '.$this->user->last_name,
            'buyer_email' => $this->user->email,
            'product_type' => $this->shopItems->product_type,
            'image' => $this->shopItems->image,
            'detail' => $this->shopItems->detail,
            'price' => $this->shopItems->price,
            'installment_no' => $this->installment_no,
            'installment_amount' => $this->installment_amount,
            'payment_status' => $this->payment_status,
            'created_at' => $this->created_at,
        ];
    }
}
