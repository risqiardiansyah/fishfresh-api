<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class PinResource extends JsonResource
{
    public function toArray($request)
    {
        $data = [
            'users_pin' => $this->users_pin,
            'users_pin_attempts' => $this->users_pin_attempts,
        ];

        return $data;
    }
}
