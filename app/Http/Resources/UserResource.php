<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
   public function toArray($request)
    {
        // if (filter_var($this->users_profile_pic, FILTER_VALIDATE_URL)) {
        //     $pict = $this->users_profile_pic;
        // } else {
        //     // $pict = ($this->users_profile_pic == null ? asset('storage/img/profile.png') : asset('storage/profile/' . $this->users_profile_pic));
        //     $pict = getImage($this->users_profile_pic);
        // }
        $pict = 'https://i.ibb.co/KrkMWTJ/member.png';
        if ($this->memberType == 2) {
            $pict = 'https://i.ibb.co/hcWdx3J/reseller.png';
        }

        $data = [
            'name' => $this->name,
            'users_code' => $this->users_code,
            'email' => $this->email,
            'no_telp' => $this->no_telp,
            'profile_pict' => $pict,
            'profile_pict_ori' => $this->users_profile_pic,
            'email_verification_status' => $this->email_verification_status,
            'memberType' => $this->memberType,
            'created_at' => $this->created_at,
        ];

        return $data;
    }
}
