<?php

namespace App\Http\Repositories;

use App\Http\Resources\ContactResource;
use Illuminate\Support\Facades\DB;

class ContactRepository
{
    public function __construct()
    {
    }

    // GET All Contact
    public function dataContact()
    {
        $about = DB::table('about')->first([
            'body as about',
            'meta_title',
            'meta_desc',
            'meta_keyword',
            'maintenance_mode',
            'logo',
            'favicon',
            'footer_logo',
            'faq_link',
            'promo_link',
            'tentang_kami_link',
            'sk_link',
            'kebijakan_privasi_link',
            'partnership_link'
        ]);
        $about->logo = getImage($about->logo);
        $about->favicon = getImage($about->favicon);
        $about->footer_logo = getImage($about->footer_logo);

        $data = DB::table('contact')->where('isActive', 1)->get();
        $data = ContactResource::collection($data);

        $about->contact = $data;
        $about->meta_desc = strip_tags($about->meta_desc);
        $about->meta_keyword = strip_tags($about->meta_keyword);
        $about->meta_title = strip_tags($about->meta_title);
        
        return $about;
    }

    // Detail Social Contact

    public function getDetailContact($code)
    {
        $data = DB::table('contact')->where('contact_code', $code)->first();
        $data = new ContactResource($data);

        return $data;
    }

    // Edit Social Contact
    public function editContact($request)
    {
        try {
            $data = [
                'contact_url' => $request->contact_url,
            ];
            if (!empty($request->contact_image)) {
                $data['contact_image'] = uploadFotoWithFileName($request->contact_image, 'IC', 'socialimg');
            }

            DB::table('contact')->where('contact_code', $request->contact_code)->update($data);

            return ['success' => true, 'data' => $data];
        } catch (\Exception $e) {
            return ['success' => false, 'msg' => $e->getMessage()];
        }
    }
}
