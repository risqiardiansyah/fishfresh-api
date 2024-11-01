<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class BalanceResource extends JsonResource
{
    public function toArray($request)
    {
        $data = [
            'users_balance_code' => $this->users_balance_code,
            'users_code' => $this->users_code,
            'users_balance' => $this->users_balance
        ];

        return $data;
    }
}
