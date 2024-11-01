<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\ApiController;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class ShopsController extends ApiController
{
    public function validateThis($request, $rules = array())
    {
        return Validator::make($request->all(), $rules, getCustomMessages());
    }

    public function requestShops(Request $request)
    {
        $rules = [
            'shop_name' => 'required',
            'shop_location' => 'required',
            'ktp' => 'required',
            'rekening_no' => 'required',
            'rekening_name' => 'required'
        ];

        $validator = $this->validateThis($request, $rules);
        if ($validator->fails()) {
            return $this->sendError(
                1,
                validationMessage($validator->errors())
            );
        }

        $user_id = Auth::user()->id;
        $shop_name = $request->shop_name;
        $shop_location = $request->shop_location;
        $ktp = $request->ktp;
        $rekening_no = $request->rekening_no;
        $rekening_name = $request->rekening_name;

        if (!empty($ktp)) {
            $ktp = uploadFotoWithFileNameApi($ktp, 'KTP');
        }

        $data = [
            'user_id' => $user_id,
            'shop_name' => $shop_name,
            'shop_location' => $shop_location,
            'ktp' => $ktp,
            'rekening_no' => $rekening_no,
            'rekening_name' => $rekening_name,
        ];

        $check = DB::table('users_shops')->where('user_id', $user_id)->exists();

        if ($check) {
            DB::table('users_shops')->where('user_id', $user_id)->update($data);
        } else {
            DB::table('users_shops')->insert($data);
        }


        return $this->sendResponse(0, 'Success', $data);
    }

    public function getJenis(Request $request)
    {
        $data = DB::table('jenis')->get(['title as text', 'id as value']);

        return $this->sendResponse(0, 'Success', $data);
    }
}
