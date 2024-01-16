<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StudentResource extends JsonResource
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
            'student_id' => $this->student_id,
            'upn' => $this->upn,
            'mifare_id' => $this->mifare_id,
            'purse_type' => $this->purse_type,
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
