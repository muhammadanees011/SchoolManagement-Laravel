<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TripParticipantResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->user->id,
            'first_name' => $this->user->first_name,
            'last_name' => $this->user->last_name,
            'email' => $this->user->email,
            'phone' => $this->user->phone,
            'role' => $this->user->role,
            'student_id' => $this->student->student_id,
            'stage' => $this->student->stage,
            'school' => $this->student->school->title,
            'transaction_id' => $this->transaction_id,
            'participation_status' => $this->participation_status,
            'payment_status' => $this->payment_status,
        ];
    }
}
