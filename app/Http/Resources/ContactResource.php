<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ContactResource extends JsonResource
{
    public function toArray($request)
    {
        if (filter_var($this->contact_image, FILTER_VALIDATE_URL)) {
            $contact_image = $this->contact_image;
        } else {
            // $contact_image = ($this->contact_image == null ? asset('storage/img/default.png') : asset('storage/socialimg/' . $this->social_contact_image));
            $contact_image = getImage($this->contact_image, true);
        }
        return [
            'contact_code' => $this->contact_code,
            'contact_url' => $this->contact_url,
            'contact_image' => $contact_image,
            'contact_image_ori' => $this->contact_image,
            'contact_name' => $this->contact_name,
        ];
    }
}
