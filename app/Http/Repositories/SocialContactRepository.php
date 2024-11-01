<?php

namespace App\Http\Repositories;

use App\Http\Resources\SocialContactResource;
use Illuminate\Support\Facades\DB;

class SocialContactRepository
{
    public function __construct()
    {
    }

    // GET All Social Contact
    public function dataSocialContact()
    {
        $data = DB::table('social_contact')->where('isActive', 1)->get();
        $data = SocialContactResource::collection($data);

        return $data;
    }

    // Detail Social Contact

    public function getDetailSocialContact($code)
    {
        $data = DB::table('social_contact')->where('social_contact_code', $code)->first();
        $data = new SocialContactResource($data);

        return $data;
    }

    // Edit Social Contact
    public function editSocialContact($request)
    {
        try {
            $data = [
                'social_contact_url' => $request->social_contact_url,
            ];
            if (!empty($request->social_contact_image)) {
                $data['social_contact_image'] = uploadFotoWithFileName($request->social_contact_image, 'IC', 'socialimg');
            }

            DB::table('social_contact')->where('social_contact_code', $request->social_contact_code)->update($data);

            return ['success' => true, 'data' => $data];
        } catch (\Exception $e) {
            return ['success' => false, 'msg' => $e->getMessage()];
        }
    }

    
}
