<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class SocialContactResource extends JsonResource
{
    public function toArray($request)
    {
        if (filter_var($this->social_contact_image, FILTER_VALIDATE_URL)) {
            $social_contact_image = $this->social_contact_image;
        } else {
            // $social_contact_image = ($this->social_contact_image == null ? asset('storage/img/default.png') : asset('storage/socialimg/' . $this->social_contact_image));
            $social_contact_image = getImage($this->social_contact_image, true);
        }
        return [
            'social_contact_code' => $this->social_contact_code,
            'social_contact_url' => $this->social_contact_url,
            'social_contact_image' => $social_contact_image,
            'social_contact_image_ori' => $this->social_contact_image,
            'social_contact_name' => $this->social_contact_name,
        ];
    }
}
