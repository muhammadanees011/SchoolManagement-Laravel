<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StaffResource extends JsonResource
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
            'staff_id' => $this->staff_id,
            'upn' => $this->upn,
            'mifare_id' => $this->mifare_id,
            'site' => $this->site,
            'created_at' => (string) $this->created_at,
            'updated_at' => (string) $this->updated_at,
            'user' => [
                'id' => $this->user->id,
                'first_name' => $this->user->first_name,
                'last_name' => $this->user->last_name,
                'email' => $this->user->email,
                'phone' => $this->user->phone,
                'role' => $this->user->role,
                'status' => $this->user->status,
            ],
            'school' => [
                'id' => $this->school->id,
                'organization_id' => $this->school->organization_id,
                'name' => $this->school->title,
                'email' => $this->school->email,
                'phone' => $this->school->phone,
            ],
        ];
    }
}
