<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\ApiController;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class AlamatController extends ApiController
{
    public function validateThis($request, $rules = array())
    {
        return Validator::make($request->all(), $rules, getCustomMessages());
    }
    public function getAlamat(Request $request)
    {
        $data = DB::table('users_alamat')
            ->where('user_id', Auth::user()->id)
            ->get();

        foreach ($data as $value) {
            $shops = getUsersShops();
            if ($shops == false) {
                $value->alamat_toko = null;
            }
        }

        if ($data) {
            return $this->sendResponse(0, 'Success', $data);
        } else {
            return $this->sendError(2, 'Error !', []);
        }
    }

    public function addAlamat(Request $request)
    {
        Log::info('test', $request->all());
        $id = $request->id;
        $kota = $request->kota;
        $alamat = $request->alamat;
        $alamat_toko = $request->alamat_toko;

        $data = [
            'kota' => $kota,
            'alamat' => $alamat,
            'alamat_toko' => $alamat_toko
        ];

        if (!empty($id)) {
            DB::table('users_alamat')
                ->where('id', $id)
                ->update($data);
        } else {
            $data['user_id'] = Auth::user()->id;

            DB::table('users_alamat')
                ->insert($data);
        }

        return $this->sendResponse(0, 'Success', $data);
    }

    public function getEducation(Request $request)
    {
        $limit = $request->limit ?? 10;
        $offset = $request->offset ?? 0;

        $data = DB::table('education')
            ->leftJoin('users', 'users.id', '=', 'education.user_id')
            ->limit($limit)
            ->offset($offset)
            ->orderBy('created_at','desc')
            ->get(['users.name', 'users.is_admin', 'education.*']);

        foreach ($data as $value) {
            if (!empty($value->image)) {
                $value->image = asset('storage/' . $value->image);
            } else {
                $value->image = 'https://curie.pnnl.gov/sites/default/files/default_images/default-image_0.jpeg';
            }

            $value->created_at = Carbon::parse($value->created_at)->locale('id')->format('d F Y');
        }

        return $this->sendResponse(0, 'Success', $data);
    }

    public function addEducation(Request $request)
    {
        $rules = [
            'image' => 'required',
            'title' => 'required',
            'link' => 'required'
        ];

        $validator = $this->validateThis($request, $rules);
        if ($validator->fails()) {
            return $this->sendError(
                1,
                validationMessage($validator->errors())
            );
        }

        $id = $request->id;
        $title = $request->title;
        $image = $request->image;
        $link = $request->link;
        $user_id = Auth::user()->id;

        if (!filter_var($link, FILTER_VALIDATE_URL)) {
            return $this->sendError(
                1,
                "Link tidak valid !"
            );
        }

        $data = [
            'title' => $title,
            'link' => $link,
            'user_id' => $user_id
        ];

        if (!empty($image)) {
            $image = uploadFotoWithFileNameApi($image, 'EDU');

            $data['image'] = $image;
        }

        if (!empty($id)) {
            DB::table('education')
                ->where('id', $id)
                ->update($data);
        } else {
            $data['user_id'] = Auth::user()->id;

            DB::table('education')
                ->insert($data);
        }

        return $this->sendResponse(0, 'Success', $data);
    }
}
