<?php

namespace App\Http\Repositories;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class PinRepository
{
    public function createPin($data, $users_code)
    {
        $data = [
          'users_pin' => Hash::make($data->pin),
          'users_code' => $users_code,
          'users_pin_code' => generateFiledCode('PIN')
        ];
        $result = DB::table('users_pin')->insert($data);
        DB::table('users')->where('users_code', $users_code)->update(['isSetPin' => 1]);
        return ['success' => $result];
    }

    public function updatePin($data)
    {
        $users_code = Auth::user()->users_code;
        DB::table('users_pin')
            ->where('users_code', $users_code)
            ->update(['users_pin' => Hash::make($data->pin)]);
        return ['success' => true];
    }

}
