<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\ApiController;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use App\Mail\ResetPassword;
use App\Mail\RegisterUser;

class AuthController extends ApiController
{

    public function __construct()
    {
        // $this->userRepo = $userRepo;
    }

    public function validateThis($request, $rules = array())
    {
        return Validator::make($request->all(), $rules, getCustomMessages());
    }

    // Login user
    public function login(Request $request)
    {

        $rules = [
            'email' => 'required',
            'password' => 'required',
        ];

        $validator = $this->validateThis($request, $rules);
        if ($validator->fails()) {
            return $this->sendError(1, 'Params not complete', validationMessage($validator->errors()));
        }

        $credentials = array(
            'email' => $request->email,
            'password' => $request->password,
        );

        $user = User::where('email', $request->email)->first();
        if (empty($user)) {
            return $this->sendError(2, "Akun anda belum terdaftar !", (object) array());
        }

        if (!Auth::attempt($credentials)) {
            return $this->errorAuth(2, "Email atau password yang anda masukkan salah", (object) array());
        }

        $success = Auth::user();
        $success['token'] = Auth::user()->createToken(Auth::guard()->user()->email)->plainTextToken;
        $success['name'] = Auth::guard()->user()->name;
        $success['gender'] = Auth::guard()->user()->gender;
        $success['email'] = Auth::guard()->user()->email;
        $success['phone'] = Auth::guard()->user()->phone;

        return $this->sendResponse(0, "Login Success", $success);
    }

    // Register
    public function register(Request $request)
    {
        $rules = [
            'name' => 'required',
            'gender' => 'required',
            'email' => 'required|email|unique:users',
            'phone' => 'required',
            'password' => 'required|min:6',
            'password_confirmation' => 'required|same:password',
        ];
        
        $validator = $this->validateThis($request, $rules);
        if ($validator->fails()) {
            return $this->sendError(
                1,
                validationMessage($validator->errors())
            );
        }

        $data = [
            'name' => $request->name,
            'gender' => $request->gender,
            'email' => $request->email,
            'phone' => $request->phone,
            'password' => Hash::make($request->password),
        ];

        $dataLogin = ['email' => $request->email, 'password' => $request->password];

        DB::table('users')->insert($data);

        Auth::attempt($dataLogin);
        $success = Auth::user();
        $success['token'] = Auth::user()->createToken('auth_token')->plainTextToken;

        $success['name'] = Auth::user()->name;
        $success['gender'] = Auth::user()->gender;
        $success['email'] = Auth::user()->email;
        $success['phone'] = Auth::user()->phone;

        return $this->sendResponse(0, "Success", $success);
    }

    // Logout
    public function logout()
    {
        if (Auth::check()) {
            Auth::user()->tokens->each(function ($token) {
                $token->delete();
            });
            return $this->sendResponse(0, "Logout berhasil.", (object) array());
        } else {
            return $this->sendError(2, "Logout gagal.", (object) array());
        }
    }

    public function getProfile(Request $request)
    {
        $success = Auth::user();
        $success['name'] = Auth::user()->name;
        $success['gender'] = Auth::user()->gender;
        $success['email'] = Auth::user()->email;
        $success['phone'] = Auth::user()->phone;
        $success['hasShops'] = false;
        $success['shops'] = (object)[];

        $shops = DB::table('users_shops')->where('user_id', Auth::user()->id)->first();
        if (!empty($shops)) {
            $shops->alamat = '-';
            $alamatToko = DB::table('users_alamat')
                ->where('user_id', Auth::user()->id)
                ->where('alamat_toko', 'Y')
                ->first();
            if (!empty($alamatToko)) {
                $shops->alamat = $alamatToko->kota;
            }

            $success['hasShops'] = true;
            $success['shops'] = $shops;
        }

        return $this->sendResponse(0, "Registration Success", $success);
    }

    // Forgot password
    public function forgot_password(Request $request)
    {
        $rules = [
            'email' => 'required|email|exists:users,email',
        ];

        $validator = $this->validateThis($request, $rules);
        if ($validator->fails()) {
            return $this->sendError(1, 'Email tidak terdaftar');
        }

        $email = $request->email;
        $user = User::where('email', $email)->first();

        if ($user->isActive == 0) {
            return $this->sendError(2, "Akun anda telah di-nonaktifkan. Silahkan contact admin", (object) array());
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        DB::table('password_resets')
            ->updateOrInsert(
                ['email' => $email],
                ['token' => $token, 'created_at' => Carbon::now()]
            );

        // link reset password
        $app_url = env('EMAIL_DOMAIN');
        $url = $app_url . '/forgot-password/next?token=' . $token;

        $data = [
            'user' => $user->name,
            'title' => 'Pulihkan Kata Sandi',
            'url' => $url
        ];

        Mail::to($email)->send(new ResetPassword($data));

        return $this->sendResponse(0, 'Link tautan reset kata sandi telah dikirim melalui email Anda');
    }

    // check token
    public function checkTokenReset(Request $request)
    {
        $rules = [
            'token' => 'required',
        ];

        $validator = $this->validateThis($request, $rules);
        if ($validator->fails()) {
            return $this->sendError(1, 'Params not complete', validationMessage($validator->errors()));
        }

        $tokenData = DB::table('password_resets')
            ->where('token', $request->token)
            ->first();

        if (!$tokenData) {
            return $this->sendError(2, 'Invalid token');
        }

        $tokenCreatedAt = Carbon::parse($tokenData->created_at);
        if ($tokenCreatedAt->addMinutes(30)->isPast()) {
            return $this->sendError(3, 'Token has expired');
        }

        return $this->sendResponse(0, 'Token is valid');
    }

    // Reset password
    public function forgot_password_next(Request $request)
    {
        $rules = [
            'password' => 'required|min:6|confirmed',
            'password_confirmation' => 'required|same:password',
        ];

        $validator = $this->validateThis($request, $rules);
        if ($validator->fails()) {
            return $this->sendError(1, 'Params not complete', validationMessage($validator->errors()));
        }

        $tokenData = DB::table('password_resets')
            ->where('token', $request->query('token'))
            ->first();

        if (!$tokenData) {
            return $this->sendError(2, 'Invalid token');
        }

        $user = User::where('email', $tokenData->email)->first();
        if (!$user) {
            return $this->sendError(1, 'Error');
        }

        $tokenCreatedAt = Carbon::parse($tokenData->created_at);
        if ($tokenCreatedAt->addMinutes(30)->isPast()) {
            return $this->sendError(3, 'Token has expired');
        }

        // update password
        $user->update([
            'password' => Hash::make($request->password),
        ]);
        // delete token
        DB::table('password_resets')->where('email', $user->email)->delete();

        return $this->sendResponse(0, "Kata sandi berhasil diubah");
    }
}
