<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\MyInstallmentsRescource;

class AdminInstallmentsResource extends JsonResource
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
            'amount_paid' => $this->amount_paid,
            'purchase_date' => $this->created_at,
            'installments' => MyInstallmentsRescource::collection($this->whenLoaded('installments')),
        ];
    }
}
