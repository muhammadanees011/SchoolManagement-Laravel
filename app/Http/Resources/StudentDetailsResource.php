<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StudentDetailsResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'student_id' => $this->student_id,
            'first_name' => $this->user->first_name,
            'last_name' => $this->user->last_name,
            'email' => $this->user->email,
            'phone' => $this->user->phone,
            'upn' => $this->upn,
            'mifare_id' => $this->mifare_id,
            'purse_type' => $this->purse_type,
            'site' => $this->site,
            'school' => $this->school ? $this->school->title:''
        ];
    }
}
