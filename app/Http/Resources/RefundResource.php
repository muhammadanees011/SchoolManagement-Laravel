<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RefundResource extends JsonResource
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
            'purchase_id' => $this->purchase->id,
            'name' => $this->purchase->shopItems->name,
            'product_type' => $this->purchase->shopItems->product_type,
            'image' => $this->purchase->shopItems->image,
            'detail' => $this->purchase->shopItems->detail,
            'price' => $this->purchase->shopItems->price,
            'total_price' => $this->purchase->total_price,
            'amount_paid' => $this->purchase->amount_paid,
            'payment_card' => $this->purchase->payment_card,
            'payment_plan' => $this->purchase->shopItems->payment_plan,
            'refund_status' => $this->purchase->refund_status,
            'payment_status' => $this->purchase->payment_status,
            'created_at' => $this->created_at,
        ];
    }
}
