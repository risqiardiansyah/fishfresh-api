<?php

namespace App\Http\Repositories;

use App\Http\Resources\UserResource;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class UserRepository
{
    public function __construct()
    {
    }

    public function getProfile()
    {
        $user = Auth::user();
        $data = new UserResource($user);
        return $data;
    }

    public function editProfile($request)
    {
        $users_code = Auth::user()->users_code;
        $data = [
            'name' => $request->name,
            'no_telp' => $request->no_telp,
        ];
        DB::table('users')->where('users_code', $users_code)->update($data);
        $user = DB::table('users')->where('users_code', $users_code)->first();
        $data = new UserResource($user);
        return $data;
    }

    public function changePassword($request)
    {
        $users_code = Auth::user()->users_code;
        DB::table('users')
            ->where('users_code', $users_code)
            ->update(['password' => Hash::make($request->password)]);

        return ['success' => true];
    }


  public function generateSecretKey( $user, $params)
  {
    $users_code = $user->users_code;
    $data = [
      'users_auth_merchant_code' => generateFiledCode('TRD'),
      'users_code' => $users_code,
      'users_auth_key' => $params,
      'users_auth_secret' => '',
      'users_auth_signature' => ''
    ];
    DB::table('users_auth_merchant')->insert($data);
    $result = [
      'secret_key' => $params,
      'message' => 'Secret key berhasil dibuat',
    ];
    return $result;
  }

}
