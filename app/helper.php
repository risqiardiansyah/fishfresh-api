<?php

use App\Http\Resources\Conversation;
use App\Http\Resources\ProductRating;
use App\Http\Resources\User;
use App\Http\Resources\Voucher;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Intervention\Image\ImageManagerStatic as Image;
use \FCM as firebase;

if (!function_exists('getUsersDetail')) {
    function getUsersDetail($users_code)
    {
        $user = DB::table('users')->where('users_code', $users_code)->first();
        return new User($user);
    }
}

if (!function_exists('generateFiledCode')) {
    function generateFiledCode($code)
    {
        $result = $code . date('y') . date('m') . date('d') . date('H') . date('i') . date('s') . mt_rand(10, 99999999); // 2301021201591000000000

        return $result;
    }
}

/*
 *  Encode base64 image and save to Storage
 */
if (!function_exists('uploadFotoWithFileName')) {
    function uploadFotoWithFileName($base64Data, $file_prefix_name, $dir = '')
    {
        $file_name = generateFiledCode($file_prefix_name) . '.png';

        //Check if storage map exists
        $storageDir = Storage::disk('public')->getDriver()->getAdapter()->getPathPrefix() . $dir;
        // return $storageDir;
        if (!is_dir($storageDir)) {
            mkdir($storageDir, 0777, true);
        }

        $insert_image = Storage::disk('public')->put($dir . '/' . $file_name, normalizeAndDecodeBase64Photo($base64Data));

        if ($insert_image) {
            return $file_name;
        }

        return false;
    }

    function normalizeAndDecodeBase64Photo($base64Data)
    {
        $replaceList = array(
            'data:image/jpeg;base64,',
            'data:image/jpg;base64,',
            'data:image/png;base64,',
            '[protected]',
            '[removed]',
        );
        $base64Data = str_replace($replaceList, '', $base64Data);

        return base64_decode($base64Data);
    }
}

if (!function_exists('uploadFotoWithFileNameMusers')) {
    function uploadFotoWithFileNameMusers($base64Data, $file_prefix_name, $dir = '')
    {
        $file_name = generateFiledCode($file_prefix_name) . '.png';
        if (isset(Auth::user()->musers_code)) {
            $dir = 'MUSERS/' . Auth::user()->musers_code;
        }

        //Check if storage map exists
        $storageDir = Storage::disk('public')->getDriver()->getAdapter()->getPathPrefix() . $dir;
        // return $storageDir;
        if (!is_dir($storageDir)) {
            mkdir($storageDir, 0777, true);
        }

        $insert_image = Storage::disk('public')->put($dir . '/' . $file_name, normalizeAndDecodeBase64Photos($base64Data));

        if ($insert_image) {
            return $file_name;
        }

        return false;
    }

    function normalizeAndDecodeBase64Photos($base64Data)
    {
        $replaceList = array(
            'data:image/jpeg;base64,',
            'data:image/jpg;base64,',
            'data:image/png;base64,',
            '[protected]',
            '[removed]',
        );
        $base64Data = str_replace($replaceList, '', $base64Data);

        return base64_decode($base64Data);
    }
}

if (!function_exists('uploadFotoWithFileNameApi')) {
    function uploadFotoWithFileNameApi($base64Data, $file_prefix_name)
    {
        $file_name = generateFiledCode($file_prefix_name) . '.png';
        // dd($file_name);

        $insert_image = Storage::disk('public')->put($file_name, normalizeAndDecodeBase64PhotoApi($base64Data));

        if ($insert_image) {
            return $file_name;
        }

        return false;
    }

    function normalizeAndDecodeBase64PhotoApi($base64Data)
    {
        $replaceList = array(
            'data:image/jpeg;base64,',
            '/^data:image\/\w+;/^name=\/\w+;base64,/',
            'data:image/jpeg;base64,',
            'data:image/jpg;base64,',
            'data:image/png;base64,',
            'data:image/webp;base64,',
            '[protected]',
            '[removed]',
        );
        $exploded = explode(',', $base64Data);
        if (!isset($exploded[1])) {
            $exploded[1] = null;
        }

        $base64 = $exploded[1];
        // dd($base64);
        $base64Data = str_replace($replaceList, '', $base64Data);

        return base64_decode($base64);
    }
}

if (!function_exists('uploadPdfFile')) {
    function uploadPdfFile($file, $title, $dir = '')
    {
        $fileNameToStore = generateFiledCode($title) . '.pdf';
        $path = Storage::disk('public')->put($dir . '/' . $fileNameToStore, normalizeAndDecodeBase64FilePdfApi($file));

        if ($path) {
            return $fileNameToStore;
        }

        return false;
    }

    function normalizeAndDecodeBase64FilePdfApi($base64Data)
    {
        $replaceList = array(
            'data:application/pdf;base64,',
            '[protected]',
            '[removed]',
        );
        $exploded = explode(',', $base64Data);
        if (!isset($exploded[1])) {
            $exploded[1] = null;
        }

        $base64 = $exploded[1];
        // dd($base64);
        $base64Data = str_replace($replaceList, '', $base64Data);

        return base64_decode($base64);
    }
}

if (!function_exists('uploadFiles')) {
    function uploadFiles($file, $title, $ext, $dir = '')
    {
        $fileNameToStore = generateFiledCode($title) . '.' . $ext;
        $path = Storage::disk('public')->put($dir . '/' . $fileNameToStore, normalizeAndDecodeBase64FileApi($file));

        if ($path) {
            return $fileNameToStore;
        }

        return false;
    }

    function normalizeAndDecodeBase64FileApi($base64Data)
    {
        $replaceList = array(
            'data:@file/octet-stream;base64,',
            'data:@file/msword;base64,',
            'data:@file/vnd.oasis.opendocument.text;base64,',
            'data:@file/vnd.openxmlformats-officedocument.wordprocessingml.document;base64,',
            'data:@file/vnd.openxmlformats-officedocument.spreadsheetml.sheet;base64,',
            '[protected]',
            '[removed]',
        );
        $exploded = explode(',', $base64Data);
        if (!isset($exploded[1])) {
            $exploded[1] = null;
        }

        $base64 = $exploded[1];
        // dd($base64);
        $base64Data = str_replace($replaceList, '', $base64Data);

        return base64_decode($base64);
    }
}

if (!function_exists('validationMessage')) {
    function validationMessage($validation)
    {
        $validate = $validation->messages();
        foreach ($validate as $key => $value) {
            $validate[$key] = $validate[$key][0];
        }

        return (object) $validate;
    }
}

if (!function_exists('validationMessageMobile')) {
    function validationMessageMobile($validation)
    {
        $validate = collect($validation)->flatten();

        return $validate->values()->all();
    }
}

if (!function_exists('validationMessage')) {
    function validateThis($request, $rules = array())
    {
        return Validator::make($request->all(), $rules);
    }
}

if (!function_exists('checkVerificationEmail')) {
    function checkVerificationEmail($hash, $email)
    {
        $where = ['hash' => $hash, 'email' => $email];
        $verify = DB::table('verification_email')->where($where)->orderBy('expires', 'DESC')->first();

        if (!empty($verify)) {
            $checkExpires = (strtotime($verify->expires) <= strtotime('now') ? false : true);

            if (!$checkExpires) {
                return ['success' => false, 'msg' => 'Token Sudah Tidak Berlaku'];
            } else {
                DB::table('verification_email')->where($where)->update(['verified_at' => now()]);
                return ['success' => true];
            }
        }
        return ['success' => false, 'msg' => 'Token Tidak Ditemukan'];
    }
}

if (!function_exists('checkVerificationByEmail')) {
    function checkVerificationByEmail($code, $email)
    {
        $where = ['email' => $email];
        $verify = DB::table('verification_email')->where($where)->orderBy('expires', 'DESC')->first();

        if ($verify) {
            $checkExpires = ($verify->expires <= now() ? false : true);

            if (!$checkExpires) {

                return "TOKEN_EXPIRED";
            } else if ($code != $verify->code) {
                return "CODE_NOT_MATCH";
            } else {
                DB::table('verification_email')->where($where)->update(['verified_at' => now()]);
                return true;
            }
        }
    }
}

if (!function_exists('checkVerificationReset')) {
    function checkVerificationReset($token, $code, $email)
    {
        $where = ['token' => $token, 'email' => $email];
        $verify = DB::table('verification_reset')->where($where)->first();

        if ($verify) {
            $checkExpires = ($verify->expires <= now() ? false : true);

            if (!$checkExpires) {

                return "TOKEN_EXPIRED";
            } else if ($code != $verify->code) {
                return "CODE_NOT_MATCH";
            } else {
                DB::table('verification_email')->where($where)->update(['verified_at' => now()]);
            }
        }
    }
}

if (!function_exists('generateOrderCode')) {
    function generateOrderCode($code)
    {
        $result = $code . date('y') . date('m') . date('d') . date('H') . date('i') . date('s') . mt_rand(10, 99999999);

        return $result;

        // try {
        //     $check = DB::table('transaction')->orderBy('created_at', 'DESC')->first();
        //     if (!empty($check)) {
        //         $code = str_replace('VOG', '', $check->transaction_code);
        //         $lastCode = (int)$code;
        //         $nextCode = sprintf('%10d', $lastCode);
        //     } else {
        //         $nextCode = sprintf('%10d', '1');
        //     }

        //     return 'VOG'.$nextCode;

        // } catch (\Exception $e) {
        //     return 'VOG'.sprintf('%10d', "1");
        // }
        // $result = $code . date('y') . date('m') . date('d') . date('H') . date('i') . date('s') . mt_rand(10, 99999999);

        // return $result;
    }
}

if (!function_exists('generatePasscode')) {
    function generatePasscode($length = 5)
    {
        $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $charactersLength = strlen($characters);
        $randomString = '';
        for ($i = 0; $i < $length; $i++) {
            $randomString .= $characters[rand(0, $charactersLength - 1)];
        }

        return $randomString;
    }
}

if (!function_exists('setHistorySubscription')) {
    function setHistorySubscription($data)
    {
        unset($data['idx']);
        unset($data['created_at']);
        if ($data['status'] == 2) {
            $data['status'] = 0;
        }
        DB::table('users_subscription_history')->insert($data);
    }
}

if (!function_exists('allowedExtension')) {
    function allowedExtension()
    {
        return [
            'png',
            'jpg',
            'jpeg',
            'jfif',
            'svg',
            'gif',
            'webp',
        ];
    }
}

if (!function_exists('checkSubscritionUsers')) {
    function checkSubscritionUsers($desa_code)
    {
        $subs = DB::table('users_subscription')->where('desa_code', $desa_code)->first();
        $trans = DB::table('transaction')->where('transaction_code', $subs->transaction_code)->first();

        if (($subs->status == 0 || $subs->status == 2) && ($trans->transaction_status == 'EXPIRED' || $trans->transaction_status == 'PENDING')) {
            return ['success' => false, 'msg' => 'Access Denied, Please complete payment'];
        }

        return ['success' => true];
    }
}

if (!function_exists('checkBalancePPOB')) {
    function checkBalancePPOB($amount)
    {
        $url = 'api/check-balance';

        $data = callAPIPPOB('POST', $url, [], 'bl');

        if (isset($data->balance)) {
            if ($data->balance >= $amount) {
                return true;
            }
        }
        return false;
    }
}

if (!function_exists('checkOperatorPulsa')) {
    function checkOperatorPulsa($request)
    {
        $url = 'api/check-operator';

        $body = [
            'customer_id' => $request->customer_id,
        ];

        $data = callAPIPPOB('POST', $url, $body, 'op');

        return $data;
    }
}

if (!function_exists('getAccessToken')) {
    function getAccessToken()
    {
        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, env('URL_ACCESS_TOKEN_SHIPDEO'));
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt(
            $ch,
            CURLOPT_POSTFIELDS,
            'client_id=' . env('CLIENT_ID_SHIPDEO') . '&client_secret=' . env('CLIENT_SECRET_SHIPDEO') . '&grant_type=' . env('GRANT_TYPE_SHIPDEO')
        );
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/x-www-form-urlencoded'));

        // receive server response ...
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $server_output = curl_exec($ch);

        curl_close($ch);

        $res = json_decode($server_output);

        if (isset($res->accessToken)) {
            DB::table('settings')->update(['shipdeo_access_token' => $res->accessToken]);
        }

        return $res;
    }
}

if (!function_exists('getSavedAccessToken')) {
    function getSavedAccessToken()
    {
        $res = DB::table('settings')->first();

        return $res->shipdeo_access_token;
    }
}

if (!function_exists('callAPI')) {
    function callAPI($method, $url, $data = [])
    {
        $authorization = "Authorization: Bearer " . getSavedAccessToken();

        $curl = curl_init();
        $ch = curl_init();

        $data = json_encode($data);

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt(
            $ch,
            CURLOPT_POSTFIELDS,
            $data
        );
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json', $authorization));

        // receive server response ...
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $server_output = curl_exec($ch);

        curl_close($ch);

        // further processing ....
        // if ($server_output == "OK") {
        return $server_output;
        // } else {

        // }

        // switch ($method) {
        //     case "POST":
        //         curl_setopt($curl, CURLOPT_POST, 1);

        //         if ($data)
        //             curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
        //         break;
        //     case "PUT":
        //         curl_setopt($curl, CURLOPT_PUT, 1);
        //         break;
        //     default:
        //         if ($data)
        //             $url = sprintf("%s?%s", $url, http_build_query($data));
        // }

        // Optional Authentication:
        // curl_setopt($curl, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
        // curl_setopt($curl, CURLOPT_USERPWD, "username:password");

        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);

        $result = curl_exec($curl);

        curl_close($curl);

        return $result;
    }
}

if (!function_exists('checkTVInternet')) {
    function checkTVInternet()
    {
        $url = 'api/v1/bill/check/internet';

        $body = [
            'commands' => 'pricelist-pasca',
            'status' => 'all',
        ];

        $internet = callAPIPPOBPostpaid('POST', $url, $body, 'pl', 'POSTPAID');

        $url = 'api/v1/bill/check/tv';

        $tv = callAPIPPOBPostpaid('POST', $url, $body, 'pl', 'POSTPAID');

        $data = array_merge($internet->pasca, $tv->pasca);

        for ($i = 0; $i < count($data); $i++) {
            $upd = [
                'status' => $data[$i]->status,
                'fee' => $data[$i]->fee,
                'komisi' => $data[$i]->komisi,
            ];
            DB::table('ppob_internet_tv')->where('code', $data[$i]->code)->update($upd);
        }

        return DB::table('ppob_internet_tv')->get();
    }
}

if (!function_exists('PPOBTranslateCode')) {
    function PPOBTranslateCode($code, $data = [])
    {
        $response_code = [
            "00" => ["PAYMENT / INQUIRY SUCCESS", true],
            "01" => ["INVOICE HAS BEEN PAID", false],
            "02" => ["BILL UNPAID", false],
            "03" => ["INVALID REF ID Failed"],
            "04" => ["BILLING ID EXPIRED", false],
            "05" => ["UNDEFINED ERROR", false],
            "06" => ["INQUIRY ID NOT FOUND", false],
            "07" => ["TRANSACTION FAILED", false],
            "08" => ["BILLING ID BLOCKED", false],
            "09" => ["INQUIRY FAILED", false],
            "10" => ["BILL IS NOT AVAILABLE", false],
            "11" => ["DUPLICATE REF ID	Failed"],
            "13" => ["CUSTOMER NUMBER BLOCKED", false],
            "14" => ["INCORRECT DESTINATION NUMBER", false],
            "15" => ["NUMBER THAT YOU ENTERED IS NOT SUPPORTED", false],
            "16" => ["NUMBER DOESN'T MATCH THE OPERATOR", false],
            "17" => ["BALANCE NOT ENOUGH", false],
            "20" => ["PRODUCT UNREGISTERED", false],
            "30" => ["PAYMENT HAVE TO BE DONE VIA COUNTER / PDAM", false],
            "31" => ["TRANSACTION REJECTED DUE TO EXCEEDING MAXIMAL TOTAL BILL ALLOWED", false],
            "32" => ["TRANSACTION FAILED, PLEASE PAY BILL OF ALL PERIOD", false],
            "33" => ["TRANSACTION CAN'T BE PROCESS, PLEASE TRY AGAIN LATER", false],
            "34" => ["BILL HAS BEEN PAID", false],
            "35" => ["TRANSACTION REJECTED DUE TO ANOTHER UNPAID ARREAR", false],
            "36" => ["EXCEEDING DUE DATE, PLEASE PAY IN THE COUNTER / PDAM", false],
            "37" => ["PAYMENT FAILED", false],
            "38" => ["PAYMENT FAILED, PLEASE DO ANOTHER REQUEST", false],
            "39" => ["PENDING / TRANSACTION IN PROCESS", false],
            "40" => ["TRANSACTION REJECTED DUE TO ALL OR ONE OF THE ARREAR/INVOICE HAS BEEN PAID", false],
            "41" => ["CAN'T BE PAID IN COUNTER, PLEASE PAY TO THE CORRESPONDING COMPANY", false],
            "42" => ["PAYMENT REQUEST HAVEN'T BEEN RECEIEVED", false],
            "91" => ["DATABASE CONNECTION ERROR", false],
            "92" => ["GENERAL ERROR", false],
            "93" => ["INVALID AMOUNT", false],
            "94" => ["SERVICE HAS EXPIRED", false],
            "100" => ["INVALID SIGNATURE", false],
            "101" => ["INVALID COMMAND", false],
            "102" => ["INVALID IP ADDRESS", false],
            "103" => ["TIMEOUT", false],
            "105" => ["MISC ERROR / BILLER SYSTEM ERROR", false],
            "106" => ["PRODUCT IS TEMPORARILY OUT OF SERVICE", false],
            "107" => ["XML FORMAT ERROR", false],
            "108" => ["SORRY, YOUR ID CAN'T BE USED FOR THIS PRODUCT TRANSACTION", false],
            "109" => ["SYSTEM CUT OFF", false],
            "110" => ["SYSTEM UNDER MAINTENANCE", false],
            "117" => ["PAGE NOT FOUND", false],
            "201" => ["UNDEFINED RESPONSE CODE", false],
        ];

        $data = $response_code[$code];

        return ['success' => $data[1], 'msg' => $data[0], 'data' => $data];
    }
}

if (!function_exists('PPOBTranslateType')) {
    function PPOBTranslateType($type)
    {
        $response_type = [
            "internet" => "Internet & TV Kabel",
            "tv" => "Internet & TV Kabel",
            "hp" => "Pasca Bayar",
            "bpjs" => "BPJS",
            "pdam" => "PDAM",
            "pln" => "Tagihan Listrik / PLN",
        ];

        $data = isset($response_type[$type]) ? $response_type[$type] : 'Lainnya';

        return $data;
    }
}

if (!function_exists('callAPIPPOB')) {
    function callAPIPPOB($method, $url, $data = [], $prefix = 'pl')
    {
        $url = env('URL_PREPAID_PPOB') . $url;

        $curl = curl_init();
        $ch = curl_init();

        $body = [
            'username' => env('USERNAME_PPOB'),
            'sign' => md5(env('USERNAME_PPOB') . env('API_KEY_PPOB') . $prefix),
        ];

        $data = array_merge($body, $data);

        $data = json_encode($data);

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt(
            $ch,
            CURLOPT_POSTFIELDS,
            $data
        );
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $server_output = curl_exec($ch);

        curl_close($ch);

        $res = json_decode($server_output);
        // dd($res . $url);
        if (str_contains($res->data->message, 'SUCCESS') || str_contains($res->data->message, 'PROCESS')) {
            return $res->data;
        } else {
            return $res;
        }
    }
}

if (!function_exists('callAPIPPOBPostpaid')) {
    function callAPIPPOBPostpaid($method, $url, $data = [], $prefix = 'pl')
    {
        $url = env('URL_POSTPAID_PPOB') . $url;

        $curl = curl_init();
        $ch = curl_init();

        $body = [
            'username' => env('USERNAME_PPOB'),
            'sign' => md5(env('USERNAME_PPOB') . env('API_KEY_PPOB') . $prefix),
        ];

        $data = array_merge($body, $data);

        $data = json_encode($data);

        // dd($data);

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt(
            $ch,
            CURLOPT_POSTFIELDS,
            $data
        );
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $server_output = curl_exec($ch);

        curl_close($ch);

        $res = json_decode($server_output);
        // dd($res);
        if (isset($res->data->response_code) && $res->data->response_code == '00') {
            return $res->data;
        } else if (isset($res->data->pasca) && count($res->data->pasca) > 0) {
            return $res->data;
        } else {
            return $res;
        }
    }
}

if (!function_exists('getMainAddressUser')) {
    function getMainAddressUser($users_code)
    {
        try {
            $data = DB::table('users_address')->where('users_code', Auth::user()->users_code)->where('is_main', 1)->first();
            if (empty($data)) {
                $data = DB::table('users_address')->where('users_code', Auth::user()->users_code)->first();
            }

            return $data->address_code;
        } catch (\Exception $e) {
            return "";
        }
    }
}

if (!function_exists('getShippingRates')) {
    function getShippingRates($toko_address_code, $cust_address_code, $toko_code, $shipping = [], $cart = [])
    {
        $toko = DB::table('users_toko')->where('toko_code', $toko_code)->first();
        $toko_address = DB::table('users_address')->where('address_code', $toko_address_code)->first();
        $cust_address = DB::table('users_address')->where('address_code', $cust_address_code)->first();

        $items = [];
        for ($i = 0; $i < count($cart); $i++) {
            $product = DB::table('product')->where('product_code', $cart[$i]->product_code)->first();
            if (!empty($cart[$i]->price_code)) {
                $price = DB::table('product_variation_price')->where('product_code', $cart[$i]->product_code)->where('price_code', $cart[$i]->price_code)->first();
                $price = $price->price;
            } else {
                $price = $product->product_price;
            }
            $prod = (object) [
                "name" => $product->product_name,
                "description" => $product->product_desc,
                "weight" => $product->product_weight,
                "weight_uom" => "gram",
                "qty" => $cart[$i]->qty,
                "value" => $cart[$i]->qty * $price,
                "width" => $product->product_width,
                "height" => $product->product_height,
                "length" => $product->product_length,
                "dimension_uom" => "cm",
            ];

            array_push($items, $prod);
        }

        $authorization = "Authorization: Bearer " . getSavedAccessToken();

        $ch = curl_init();

        $data = (object) [
            "couriers" => $shipping,
            "is_cod" => $toko->is_cod ? true : false,
            "origin_city_code" => substr($toko_address->city_code, 0, 5),
            "origin_city_name" => $toko_address->city_name,
            "origin_subdistrict_code" => $toko_address->subdistrict_code,
            "origin_subdistrict_name" => $toko_address->subdistrict_name,
            "destination_city_code" => substr($cust_address->city_code, 0, 5),
            "destination_city_name" => $cust_address->city_name,
            "destination_subdistrict_code" => $cust_address->subdistrict_code,
            "destination_subdistrict_name" => $cust_address->subdistrict_name,
            "items" => $items,

        ];
        $data = json_encode($data);

        $url = env('SHIPDEO_URL') . '/v1/couriers/pricing';

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt(
            $ch,
            CURLOPT_POSTFIELDS,
            $data
        );
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json', $authorization));

        // receive server response ...
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $server_output = curl_exec($ch);

        curl_close($ch);

        $res = json_decode($server_output);

        return $res->data;
    }
}

if (!function_exists('generateAirwaybill')) {
    function generateAirwaybill($data)
    {
        $authorization = "Authorization: Bearer " . getSavedAccessToken();

        $ch = curl_init();

        $data = json_encode($data);

        $url = env('SHIPDEO_URL') . '/v1/couriers/orders';

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt(
            $ch,
            CURLOPT_POSTFIELDS,
            $data
        );
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json', $authorization));

        // receive server response ...
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $server_output = curl_exec($ch);

        curl_close($ch);

        $res = json_decode($server_output);

        if (isset($res->data)) {
            Log::INFO('AWB PROCESS  => ' . $server_output);
            return $res->data;
        } else {
            Log::error('AWB PROCESS ERROR => ' . $server_output);
            return (object) [];
        }
    }
}

if (!function_exists('arrangeDelivery')) {
    function arrangeDelivery($id, $delivery_type)
    {
        $authorization = "Authorization: Bearer " . getSavedAccessToken();
        $ch = curl_init();
        $data = (object) ["delivery_type" => $delivery_type];
        $data = json_encode($data);

        $url = env('SHIPDEO_URL') . '/v1/couriers/orders/' . $id;

        curl_setopt($ch, CURLOPT_URL, $url);
        // curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PATCH');
        curl_setopt(
            $ch,
            CURLOPT_POSTFIELDS,
            $data
        );
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json', $authorization));

        // receive server response ...
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $server_output = curl_exec($ch);

        curl_close($ch);

        $res = json_decode($server_output);

        if ($res->success) {
            return $res;
        } else {
            Log::error('ARRANGE DELIVERY ERROR => ' . $server_output);
            dd($res);
            return (object) [
                'success' => false,
                'data' => $server_output,
            ];
        }
    }
}

if (!function_exists('getTrackingData')) {
    function getTrackingData($airwaybill, $courier = 'jnt')
    {
        $authorization = "Authorization: Bearer " . getSavedAccessToken();

        $ch = curl_init();

        $data = (object) ["waybill" => $airwaybill, 'courier_code' => $courier];
        $data = json_encode($data);

        $url = env('SHIPDEO_URL') . '/v1/couriers/waybill';

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt(
            $ch,
            CURLOPT_POSTFIELDS,
            $data
        );
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json', $authorization));

        // receive server response ...
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $server_output = curl_exec($ch);

        curl_close($ch);

        $res = json_decode($server_output);
        // dd($res);
        if (count($res->errors) == 0) {
            return $res->data;
        } else {
            Log::error('GET TRACKING ERROR => ' . $server_output);
            // dd($res);
            return (object) [
                'success' => false,
                'data' => $server_output,
            ];
        }
    }
}

if (!function_exists('cancelOrder')) {
    function cancelOrder($_id, $reason = '-')
    {
        $authorization = "Authorization: Bearer " . getSavedAccessToken();

        $ch = curl_init();

        $data = (object) ['cancel_reason' => $reason];
        $data = json_encode($data);

        $url = env('SHIPDEO_URL') . '/v1/couriers/orders/cancel-confirm/' . $_id;

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "DELETE");
        curl_setopt(
            $ch,
            CURLOPT_POSTFIELDS,
            $data
        );
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json', $authorization));

        // receive server response ...
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $server_output = curl_exec($ch);

        curl_close($ch);

        $res = json_decode($server_output);
        if (count($res->errors) == 0) {
            return $res->data;
        } else {
            Log::error('CANCEL ORDER ERROR => ' . $server_output);
            // dd($res);
            return (object) [
                'success' => false,
                'data' => $server_output,
            ];
        }
    }
}

if (!function_exists('addNotifAdmin')) {
    function addNotifAdmin($title, $desc, $type, $unique_code = '')
    {
        try {
            $data = [
                'notif_code' => generateFiledCode('NOTIF'),
                'notif_title' => $title,
                'notif_desc' => $desc,
                'notif_type' => $type,
                'unique_code' => $unique_code,
            ];

            DB::table('notification_admin')->insert($data);

            return true;
        } catch (\Exception $e) {
            return false;
        }
    }
}

if (!function_exists('getVoucher')) {
    function getVoucher($code, $time_type, $type = 2)
    {
        $date = date('Y-m-d');
        if ($type == 2) {
            $voucher = DB::table('voucher')
                ->where('voucher_code', $code)
                ->whereDate('start_date', '<=', $date)
                ->whereDate('end_date', '>=', $date)
                ->first();
        } else {
            $voucher = DB::table('voucher')
                ->where('voucher_code', $code)
                ->whereDate('start_date', '<=', $date)
                ->whereDate('end_date', '>=', $date)
                ->first();
            $disc = DB::table('voucher_discount')
                ->where('voucher_code', $code)
                ->where('type', $time_type)
                ->first();

            if (!empty($disc)) {
                $voucher->discount = $disc->discount;
            }
        }

        return $voucher;
    }
}

if (!function_exists('setVoucherUsed')) {
    function setVoucherUsed($code)
    {
        $data = [
            'voucher_code' => $code,
            'desa_code' => Auth::user()->desa_code,
        ];
        DB::table('voucher_used')->insert($data);

        return true;
    }
}

if (!function_exists('paginateManual')) {
    function paginateManual($items, $perPage = 5, $page = null, $options = [])
    {
        $page = $page ?: (Paginator::resolveCurrentPage() ?: 1);
        $items = $items instanceof Collection ? $items : Collection::make($items);
        $itemsData = $items->forPage($page, $perPage);
        $itemsData = $itemsData->toArray();
        sort($itemsData);
        $data = new LengthAwarePaginator($itemsData, $items->count(), $perPage, $page, $options);

        return $data;
    }
}

if (!function_exists('checkEmailRegisterUser')) {
    function checkEmailRegisterUser($email)
    {
        $user = DB::table('users')->where('email', $email)->first();
        if (!empty($user)) {
            return ['reject' => true];
        }

        return ['reject' => false];
    }
}

if (!function_exists('getProvince')) {
    function getProvince($code, $type = 'object')
    {
        // $data = DB::table('master_province')->where('province_code', $code)->first(['province_code', 'province_name']);
        $data = DB::table('master_location')->where('province_code', $code)->first(['province_code', 'province_name']);
        if ($type == 'name' && !empty($data)) {
            return $data->province_name;
        }

        return $data;
    }
}

if (!function_exists('getCity')) {
    function getCity($code, $type = 'object')
    {
        // $data = DB::table('master_city')
        $data = DB::table('master_location')
            ->where('city_code', 'LIKE', '%' . $code . '%')
            ->groupBy('city_code')
            ->first(['city_code', 'city_name']);
        if ($type == 'name' && !empty($data)) {
            return $data->city_name;
        }

        return $data;
    }
}

if (!function_exists('getSubdistrict')) {
    function getSubdistrict($code, $type = 'object')
    {
        // $data = DB::table('master_subdistrict')
        $data = DB::table('master_location')
            ->where('subdistrict_code', $code)
            ->first(['subdistrict_name', 'subdistrict_code']);
        if ($type == 'name' && !empty($data)) {
            return $data->subdistrict_name;
        }

        return $data;
    }
}

if (!function_exists('getVariationProduct')) {
    function getVariationProduct($code)
    {
        $data = DB::table('product_variation_base')->where('product_code', $code)->get(['base_code', 'base_name']);
        for ($i = 0; $i < count($data); $i++) {
            $variation = DB::table('product_variation')->where('base_code', $data[$i]->base_code)->get(['idx', 'variation_code', 'variation_name', 'img']);
            for ($x = 0; $x < count($variation); $x++) {
                $variation[$x]->img_old = $variation[$x]->img;
                $variation[$x]->img = ($variation[$x]->img == null ? "" : asset('storage/product/variation/' . $variation[$x]->img));
            }

            $data[$i]->list = $variation;
        }

        return $data;
    }
}

if (!function_exists('getVariationPrice')) {
    function getVariationPrice($product_code)
    {
        try {
            $product = DB::table('product')->where('product_code', $product_code)->first();
            $result = [];
            $base = DB::table('product_variation_base')->where('product_code', $product_code)->orderBy('order', 'asc')->get(['base_code', 'base_name']);
            for ($i = 0; $i < count($base); $i++) {
                if (count($base) == 2) {
                    $variation1 = DB::table('product_variation')->where('product_code', $product_code)->where('base_code', $base[$i]->base_code)->get();
                    for ($x = 0; $x < count($variation1); $x++) {
                        $variation2 = DB::table('product_variation')->where('product_code', $product_code)->where('base_code', '!=', $base[$i]->base_code)->get();
                        for ($y = 0; $y < count($variation2); $y++) {
                            $variation_price = DB::table('product_variation_price')->where('product_code', $product_code)->where('variation_code_1', $variation1[$x]->variation_code)->where('variation_code_2', $variation2[$y]->variation_code)->first();
                            if ($variation_price) {
                                $discount = (($product->discount / 100) * $variation_price->price);
                                $price_after = $variation_price->price - $discount;

                                $data = (object) [
                                    'idx' => $variation_price->idx,
                                    'price_code' => $variation_price->price_code,
                                    'variation_code_1' => $variation_price->variation_code_1,
                                    'variation_code_2' => $variation_price->variation_code_2,
                                    'variation_name' => $variation1[$x]->variation_name . ' ' . $variation2[$y]->variation_name,
                                    'price' => $variation_price->price,
                                    'price_before' => $variation_price->price,
                                    'price_after' => $price_after,
                                    'stock' => $variation_price->stock,
                                ];
                                array_push($result, $data);
                            }
                        }
                    }
                } else {
                    $variation = DB::table('product_variation')->where('product_code', $product_code)->where('base_code', $base[$i]->base_code)->get();
                    for ($x = 0; $x < count($variation); $x++) {
                        $variation_price = DB::table('product_variation_price')->where('product_code', $product_code)->where('variation_code_1', $variation[$x]->variation_code)->first();
                        if ($variation_price) {
                            $discount = (($product->discount / 100) * $variation_price->price);
                            $price_after = $variation_price->price - $discount;

                            $data = (object) [
                                'idx' => $variation_price->idx,
                                'price_code' => $variation_price->price_code,
                                'variation_code_1' => $variation_price->variation_code_1,
                                'variation_code_2' => $variation_price->variation_code_2,
                                'variation_name' => $variation[$x]->variation_name,
                                'price' => $variation_price->price,
                                'price_before' => $variation_price->price,
                                'price_ater' => $price_after,
                                'stock' => $variation_price->stock,
                            ];
                            array_push($result, $data);
                        }
                    }
                }
            }

            return $result;
        } catch (\Exception $e) {
            // dd($e->getMessage() . ' == ' . $e->getLine());
            return [];
        }
    }
}

if (!function_exists('getLastSeenProduct')) {
    function getLastSeenProduct($users_code)
    {
        $result = [];
        $data = DB::table('product_last_seen')->where('users_code', $users_code)->limit(10)->get();
        for ($i = 0; $i < count($data); $i++) {
            array_push($result, $data[$i]->category_code);
        }

        return $result;
    }
}

if (!function_exists('setLastSeenProduct')) {
    function setLastSeenProduct($product)
    {
        try {
            $data = [
                'users_code' => Auth::user()->users_code,
                'product_code' => $product->product_code,
                'category_code' => $product->category_code,
            ];
            DB::table('product_last_seen')->insert($data);

            return true;
        } catch (\Exception $e) {
            return false;
        }
    }
}

if (!function_exists('getReviewRatingProduct')) {
    function getReviewRatingProduct($product_code, $limit = 5, $rev = false)
    {
        try {
            $data = (object) [
                'rating' => 0,
                'total' => 0,
            ];
            $rating = DB::table('product_rating')->where('product_code', $product_code)->avg('rating');
            $total = DB::table('product_rating')->where('product_code', $product_code)->count();
            if ($rev) {
                $review = DB::table('product_rating')->where('product_code', $product_code)->limit($limit)->get();
                $data->review = ProductRating::collection($review);
            }
            $data->rating = substr($rating, 0, 3);
            $data->total = $total;

            return $data;
        } catch (\Exception $e) {
            return $data = (object) [
                'rating' => 0,
                'total' => 0,
            ];
        }
    }
}

if (!function_exists('getWishlistProduct')) {
    function getWishlistProduct($img = false, $collection_code = '')
    {
        try {
            $result = [];
            $data = DB::table('product_wishlist')->where('users_code', Auth::user()->users_code);
            if (!empty($collection_code) && $collection_code != 'all') {
                $data = $data->where('collection_code', $collection_code);
            }
            $data = $data->groupBy('product_code')->get(['product_code']);
            for ($i = 0; $i < count($data); $i++) {
                array_push($result, $data[$i]->product_code);
            }

            if (!$img) {
                return $result;
            }

            $resimg = [];
            for ($i = 0; $i < count($result); $i++) {
                $img = DB::table('product_img')->where('product_code', $result[$i])->orderBy('created_at', 'DESC')->first();
                if (!empty($img)) {
                    array_push($resimg, $img->img);
                }
            }

            return $resimg;
        } catch (\Exception $e) {
            return [];
        }
    }
}

if (!function_exists('getWishlistThumbnail')) {
    function getWishlistThumbnail($img = [], $wcode = null)
    {
        // try {
        $base = public_path() . '/storage/img/base.png';

        $newimg = Image::make($base);
        $newimg->resize(1000, 1000);

        if (count($img) <= 4) {
            for ($i = 0; $i < count($img); $i++) {
                $insert = public_path() . '/storage/product/' . $img[$i];

                $newimgg = Image::make($insert);
                $newimgg->fit(1000);
                $newimgg->resize(986, 986, function ($constraint) {
                    $constraint->aspectRatio();
                    $constraint->upsize();
                });
                $newimg->insert($newimgg, 'top-left', 5, 5);
            }
        } else {
            for ($i = 0; $i < count($img); $i++) {
                $insert = public_path() . '/storage/product/' . $img[$i];

                $newimgg = Image::make($insert);
                $newimgg->fit(500);
                $newimgg->resize(493, 493, function ($constraint) {
                    $constraint->aspectRatio();
                    $constraint->upsize();
                });

                switch ($i) {
                    case 0:
                        $newimg->insert($newimgg, 'top-left', 5, 5);
                        break;
                    case 1:
                        $newimg->insert($newimgg, 'top-right', 5, 5);
                        break;
                    case 2:
                        $newimg->insert($newimgg, 'bottom-left', 5, 5);
                        break;
                    case 3:
                        $newimg->insert($newimgg, 'bottom-right', 5, 5);
                    default:
                        break;
                }
            }
        }

        $newimg->save(public_path() . '/storage/wishlist/' . $wcode . '.jpg');

        return asset('storage/wishlist/' . $newimg->basename);
    }
}

if (!function_exists('getCourierToko')) {
    function getCourierToko($toko_code)
    {
        try {
            $result = [];
            $data = DB::table('users_toko_courier')->where('toko_code', $toko_code)->get();
            for ($i = 0; $i < count($data); $i++) {
                array_push($result, $data[$i]->courier_code);
            }

            return $result;
        } catch (\Exception $e) {
            return [];
        }
    }
}

if (!function_exists('getEmailByUsersCode')) {
    function getEmailByUsersCode($users_code)
    {
        try {
            $user = DB::table('users')->where('users_code', $users_code)->first();

            return $user->email;
        } catch (\Exception $e) {
            return "";
        }
    }
}

if (!function_exists('getUniqueOrderCode')) {
    function getUniqueOrderCode($toko_code, $status)
    {
        try {
            if (!empty($status)) {
                $newstatus = getAliasesOrderStatus($status, 'orders');
            } else {
                $newstatus = [
                    'ENTRY',
                    'WAIT_ARRANGED',
                    'CONFIRMED',
                    'ON_PROGRESS',
                    'PICKED',
                    'DELIVERED',
                    'DONE',
                    'CANCELLED',
                    'CANCEL_REQUEST',
                ];
            }

            $result = [];
            $data = DB::table('orders_shipping')
                ->leftJoin('orders as o', 'o.order_code', '=', 'orders_shipping.orders_code');
            if (!empty($toko_code)) {
                $data = $data->where('orders_shipping.toko_code', $toko_code);
            } else if (isset(Auth::user()->users_code)) {
                $data = $data->where('orders_shipping.users_code', Auth::user()->users_code);
            }
            $data = $data->whereIn('orders_shipping.status', $newstatus)
                ->orderBy('created_at', 'DESC')
                ->get(['orders_shipping.*']);

            for ($i = 0; $i < count($data); $i++) {
                array_push($result, $data[$i]);
            }

            return $result;
        } catch (\Exception $e) {
            return [];
        }
    }
}

if (!function_exists('getAliasesOrderStatus')) {
    function getAliasesOrderStatus($status, $from = 'shipment')
    {
        // waiting_payment,waiting_shipment,on_delivery,delivered,canceled
        // dd($status);
        if ($from == 'shipment') {
            if ($status == 'ENTRY') {
                $status = 'waiting_payment';
            }
            if ($status == 'WAIT_ARRANGED') {
                $status = 'waiting_shipment';
            }
            if ($status == 'CONFIRMED' || $status == 'ON_PROGRESS' || $status == 'PICKED') {
                $status = 'on_delivery';
            }
            if ($status == 'DELIVERED' || $status == 'DONE') {
                $status = 'delivered';
            }
            if ($status == 'CANCEL_REQUEST' || $status == 'CANCELLED') {
                $status = 'canceled';
            }
        } else {
            if ($status == 'waiting_payment') {
                $status = ['ENTRY'];
            }
            if ($status == 'waiting_shipment') {
                $status = ['WAIT_ARRANGED'];
            }
            if ($status == 'on_delivery') {
                $status = ['CONFIRMED', 'ON_PROGRESS', 'PICKED'];
            }
            if ($status == 'delivered') {
                $status = ['DELIVERED', 'DONE'];
            }
            if ($status == 'canceled') {
                $status = ['CANCELLED', 'CANCEL_REQUEST'];
            }
        }

        return $status;
    }
}

if (!function_exists('getProductCodeAll')) {
    function getProductCodeAll($toko_code)
    {
        $data = [];
        $product = DB::table('product')->where('toko_code', $toko_code)->get();
        for ($i = 0; $i < count($product); $i++) {
            array_push($data, $product[$i]->product_code);
        }

        return $data;
    }
}

if (!function_exists('sortData')) {
    function sortData($data)
    {
        usort($data, function ($a, $b) {
            return strcmp($b->created_at, $a->created_at);
        });

        return $data;
    }
}

if (!function_exists('getConversation')) {
    function getConversation($group_code, $order = 'ASC')
    {
        try {
            $deleted = DB::table('messages_deleted')->where('deleted_from', Auth::user()->users_code)->where('type', 'message')->where('messages_group_code', $group_code)->first();

            $data = DB::table('messages')
                ->where('messages_group_code', $group_code);
            if (!empty($deleted)) {
                $data = $data->where('created_at', '>=', $deleted->created_at);
            }
            $data = $data->orderBy('created_at', $order)->get();
            $all = Conversation::collection($data);

            return $all;
        } catch (\Exception $e) {
            // dd($e->getMessage());
            return false;
        }
    }
}

if (!function_exists('getProductSold')) {
    function getProductSold($product_code, $type = '')
    {
        try {
            $count = 0;
            if ($type == 'toko') {
                $data = DB::table('orders_products')->where('toko_code', $product_code)->get();
            } else {
                $data = DB::table('orders_products')->where('product_code', $product_code)->get();
            }

            for ($i = 0; $i < count($data); $i++) {
                $order = DB::table('orders')->where('order_code', $data[$i]->orders_code)->whereNotIn('order_status', ['canceled', 'waiting_shipment'])->exists();
                if ($order) {
                    $count += $data[$i]->qty;
                }
            }

            return $count;
        } catch (\Exception $e) {
            return 0;
        }
    }
}

if (!function_exists('updateUsersBalance')) {
    function updateUsersBalance($users_code, $balance)
    {
        try {
            $check = DB::table('users_balance')->where('users_code', $users_code)->first();
            if (!empty($check)) {
                Log::info('UUU SALDO ' . ($balance + $check->users_balance));
                $balance_code = $check->users_balance_code;
                DB::table('users_balance')->where('users_code', $users_code)->update(['users_balance' => $check->users_balance + $balance]);
                $users_balance = $check->users_balance + $balance;
            } else {
                $balance_code = generateFiledCode('BLN');
                $data = [
                    'users_balance_code' => $balance_code,
                    'users_code' => $users_code,
                    'users_balance' => $balance,
                ];
                DB::table('users_balance')->insert($data);
                $users_balance = $balance;
            }

            return ['success' => true, 'users_balance' => $users_balance];
        } catch (\Exception $e) {
            return ['success' => false];
        }
    }
}

if (!function_exists('updateTokoBalance')) {
    function updateTokoBalance($toko_code, $balance)
    {
        DB::beginTransaction();
        try {
            $check = DB::table('users_toko_balance')->where('toko_code', $toko_code)->first();
            if (!empty($check)) {
                $balance_code = $check->balance_code;
                DB::table('users_toko_balance')->where('toko_code', $toko_code)->update(['balance' => $check->balance + $balance]);
            } else {
                $balance_code = generateFiledCode('BLN');
                $data = [
                    'balance_code' => $balance_code,
                    'toko_code' => $toko_code,
                    'balance' => $balance,
                ];
                DB::table('users_toko_balance')->insert($data);
            }

            DB::commit();

            return ['success' => true, 'balance_code' => $balance_code];
        } catch (\Exception $e) {
            DB::rollBack();
            return ['success' => false];
        }
    }
}

if (!function_exists('checkUsersBalance')) {
    function checkUsersBalance($users_code)
    {
        DB::beginTransaction();
        try {
            $check = DB::table('users_balance')->where('users_code', $users_code)->first(['balance_code', 'balance']);
            if (empty($check)) {
                $balance_code = generateFiledCode('BLN');
                $data = [
                    'balance_code' => $balance_code,
                    'users_code' => $users_code,
                    'balance' => 0,
                ];
                DB::table('users_balance')->insert($data);

                $check = DB::table('users_balance')->where('users_code', $users_code)->first(['balance_code', 'balance']);
            }

            DB::commit();

            return $check;
        } catch (\Exception $e) {
            DB::rollBack();
            return [];
        }
    }
}

if (!function_exists('checkTokoBalance')) {
    function checkTokoBalance($toko_code)
    {
        DB::beginTransaction();
        try {
            $check = DB::table('users_toko_balance')
                ->where('toko_code', $toko_code)
                ->first(['balance_code', 'balance']);
            if (empty($check)) {
                $balance_code = generateFiledCode('BLN');
                $data = [
                    'balance_code' => $balance_code,
                    'toko_code' => $toko_code,
                    'balance' => 0,
                ];
                DB::table('users_toko_balance')->insert($data);

                $check = DB::table('users_toko_balance')->where('toko_code', $toko_code)->first(['balance_code', 'balance']);
            }

            DB::commit();

            return $check;
        } catch (\Exception $e) {
            DB::rollBack();
            return [];
        }
    }
}

if (!function_exists('existsInArray')) {
    function existsInArray($entry, $array)
    {
        try {
            foreach ($array as $compare) {
                if ($compare->province_code == $entry->province_code) {
                    return true;
                }
            }
            return false;
        } catch (\Exception $e) {
            // dd($array, $entry);
            return false;
        }
    }
}

if (!function_exists('addClicked')) {
    function addClicked($product_code)
    {
        try {
            $product = DB::table('product')->where('product_code', $product_code)->first();
            if (!empty($product)) {
                DB::table('product')->where('product_code', $product_code)->update(['clicked' => $product->clicked + 1]);
            }
        } catch (\Exception $e) {
            // dd($array, $entry);
            return false;
        }
    }
}

if (!function_exists('saveNotification')) {
    function saveNotification($params, $fcm = false)
    {
        DB::beginTransaction();
        try {
            $data = [
                'notification_code' => generateFiledCode('NOTIF'),
                'order_code' => isset($params['order_code']) ? $params['order_code'] : '',
                'toko_code' => isset($params['toko_code']) ? $params['toko_code'] : '',
                'notification_to' => isset($params['notification_to']) ? $params['notification_to'] : 'global',
                'notification_from' => isset($params['notification_from']) ? $params['notification_from'] : 'system',
                'notification_title' => $params['notification_title'],
                'notification_desc' => $params['notification_desc'],
                'notification_link' => isset($params['notification_link']) ? $params['notification_link'] : '/',
                'notification_type' => $params['notification_type'],
            ];
            DB::table('notification')->insert($data);

            DB::commit();

            if ($fcm) {
                $message = [
                    'title' => $params['notification_title'],
                    'body' => $params['notification_desc'],
                    'order_code' => isset($params['order_code']) ? $params['order_code'] : '',
                    'toko_code' => isset($params['toko_code']) ? $params['toko_code'] : '',
                    'link' => isset($params['notification_link']) ? $params['notification_link'] : '/',
                ];
                pushFcmNotif($params['notification_to'], $message);
            }

            return true;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("NOTIF ERROR => " . $e->getMessage());
            dd($e->getMessage());
            return false;
        }
    }
}

if (!function_exists('pushFcmNotif')) {
    function pushFcmNotif($users_code, $message) // PUSH FCM TO USERS WITH ALL CONDITION

    {
        $token_fcm = DB::table('fcm_messages')->orderBy('created_at', 'DESC')->where('users_code', $users_code)->first();

        if (!$token_fcm) {
            return true;
        }
        $order_code = '';
        if (isset($message['order_code'])) {
            $order_code = $message['order_code'];
        }
        $toko_code = '';
        if (isset($message['toko_code'])) {
            $toko_code = $message['toko_code'];
        }

        $url = 'https://fcm.googleapis.com/fcm/send';
        $headers = array(
            'Authorization: key=' . env('FCM_KEY'),
            'Content-Type: application/json',
        );

        $fields = [
            "to" => $token_fcm->token_fcm,
            "notification" => [
                "title" => $message['title'],
                "body" => $message['body'],
                "icon" => "appicon",
                "sound" => "default",
            ],
            "data" => [
                "page" => $message['link'],
                "params" => (object) [
                    "order_code" => $order_code,
                    "toko_code" => $toko_code,
                ],
            ],
        ];

        // Open connection
        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($fields));

        $result = curl_exec($ch);
        if ($result === false) {
            // dd('Curl failed: ' . curl_error($ch));
            return false;
        }
        curl_close($ch);
        // dd($result);
        return true;
    }
}

if (!function_exists('getUserToko')) {
    function getUserToko($toko_code)
    {
        $toko = DB::table('users_toko')->where('toko_code', $toko_code)->first();

        return $toko->users_code;
    }
}

if (!function_exists('checkVoucherRules')) {
    function checkVoucherRules($kode_promo, $payment_method = '', $order_amount = '')
    {
        $now = date('Y-m-d');

        $voucher = DB::table('vouchers')
            ->where('vouchers_redeem_code', $kode_promo)
            ->where('isActive', 1)
            // ->where('voucher_discount_max', '>', 0)
            ->first();
        if (empty($voucher)) {
            return ['success' => false, 'msg' => 'Kode voucher tidak valid !', 'code' => '04'];
        }
        if ($voucher->voucher_discount_max <= 0) {
            return ['success' => false, 'msg' => 'Voucher telah habis digunakan !', 'code' => '00'];
        }
        if ($now < $voucher->voucher_start) {
            return ['success' => false, 'msg' => 'Voucher belum bisa digunakan !', 'code' => '05'];
        }
        if ($now > $voucher->voucher_end) {
            return ['success' => false, 'msg' => 'Voucher kadaluarsa !', 'code' => '04'];
        }
        if (!empty($payment_method) && $voucher->con_payment_method && !str_contains($voucher->payment_method, $payment_method)) {
            return ['success' => false, 'msg' => 'Voucher tidak bisa digunakan untuk metode pembayaran ini !', 'code' => '01'];
        }
        if (!empty($order_amount) && $order_amount < $voucher->voucher_discount) {
            return ['success' => false, 'msg' => 'Tidak mencukupi minimum pembelian !', 'code' => '03'];
        }

        $result = [
            'vouchers_redeem_code' => $voucher->vouchers_redeem_code,
            'vouchers_title' => $voucher->vouchers_title,
            'vouchers_discount' => $voucher->voucher_discount,
            'payment_method' => $voucher->payment_method
        ];
        return ['success' => true, 'data' => $result];
    }
}

if (!function_exists('decreaseMaxVoucherUsed')) {
    function decreaseMaxVoucherUsed($kode_promo)
    {
        $voucher = DB::table('vouchers')
            ->where('vouchers_redeem_code', $kode_promo)
            ->first();
        if (!empty($voucher)) {
            DB::table('vouchers')->where('vouchers_redeem_code', $kode_promo)->update(['voucher_discount_max' => ($voucher->voucher_discount_max - 1)]);
        }

        return ['success' => true];
    }
}

if (!function_exists('detailVoucher')) {
    function detailVoucher($code)
    {
        $data = DB::table('voucher')->where('voucher_code', $code)->first();

        return $data;
    }
}

if (!function_exists('addAdStats')) {
    function addAdStats($code, $column)
    {
        $data = DB::table('product_ads')
            ->where('product_code', $code)
            ->where('status_ads', 'A')
            ->orderBy('created_at', 'DESC')
            ->limit(1)
            ->first();
        if (!empty($data) && Auth::user()->users_code != $data->users_code) {
            DB::table('product_ads')->where('product_code', $code)->orderBy('created_at', 'DESC')->limit(1)->update([$column => $data->$column + 1]);
        }

        return $data;
    }
}

if (!function_exists('my_array_unique')) {
    function my_array_unique($array, $keep_key_assoc = false)
    {
        $duplicate_keys = array();
        $tmp = array();

        foreach ($array as $item) {
            if ($item->name) {
                if (!in_array($item->name, $duplicate_keys)) {
                    array_push($tmp, $item);
                    array_push($duplicate_keys, $item->name);
                }
            }
        }

        // dd($tmp);

        return $tmp;
    }
}

if (!function_exists('searchWhere')) {
    function searchWhere($data, $column = '', $search = '', $exact = false)
    {
        if (!empty($search)) {
            if (!$exact) {
                return $data->where($column, 'LIKE', '%' . $search . '%');
            }

            return $data->where($column, $search);
        }

        return $data;
    }
}

if (!function_exists('getNextCodeMasterLocation')) {
    function getNextCodeMasterLocation($column = '')
    {
        $data = DB::table('master_location')
            ->orderBy($column, "DESC")
            ->first();

        $res = explode('.', $data->$column);

        return $res[count($res) - 1] + 1;
    }
}

if (!function_exists('gameCodeApigames')) {
    function gameCodeApigames()
    {
        return ['mobilelegend', 'freefire', 'higgs'];
    }
}

if (!function_exists('checkBalanceUser')) {
    function checkBalanceUser($users_code, $nominal = 0)
    {

        $balance = DB::table('users_balance')->where('users_code', $users_code)->first();

        if (empty($balance)) {
            return ['success' => false, 'msg' => 'Saldo tidak ditemukan'];
        }
        if ($balance->users_balance < $nominal) {
            return ['success' => false, 'msg' => 'Saldo tidak cukup'];
        }

        return ['success' => true, 'data' => $balance];
    }
}

if (!function_exists('getEnablePayment')) {
    function getEnablePayment($pm_code = '')
    {
        $pm_code = str_replace('-', '_', $pm_code);
        $pm = [
            'credit_card',
            'gopay',
            'cimb_clicks',
            'bca_klikbca',
            'bca_klikpay',
            'bri_epay',
            'telkomsel_cash',
            'echannel',
            'permata_va',
            'other_va',
            'bca_va',
            'bni_va',
            'bri_va',
            'indomaret',
            'danamon_online',
            'akulaku',
            'shopeepay',
            'alfamart'
        ];
        if (empty($pm_code)) {
            return $pm;
        }
        if (in_array($pm_code, $pm)) {
            return [$pm_code];
        }

        return $pm;
    }
}

if (!function_exists('getImage')) {
    function getImage($file, $icon = false)
    {
        if (empty($file)) {
            if ($icon) {
                return asset('/images/icon.png');
            }
            return asset('/images/default.png');
        }
        return env('ADMIN_DOMAIN') . $file;
    }
}

if (!function_exists('clean')) {
    function clean($string)
    {
        $string = strtolower(str_replace(' ', '-', $string));

        return preg_replace('/[^A-Za-z0-9\-]/', '', $string);
    }
}

if (!function_exists('saveLog')) {
    function saveLog($from, $unique_code, $response, $notes = '')
    {
        $data = [
            'from' => $from,
            'unique_code' => $unique_code,
            'response' => json_encode($response),
            'notes' => $notes,
        ];

        $result = DB::table('data_log')->insert($data);
        return true;
    }
}

if (!function_exists('getStockHold')) {
    function getStockHold($code)
    {
        $transaction = DB::table('transaction')
            ->leftJoin('transaction_detail as td', 'transaction.transaction_code', '=', 'td.transaction_code')
            ->wherein('transaction.status', ['pending', 'waiting'])
            ->where('td.item_code', $code)
            ->sum('qty');

        return $transaction;
    }
}

if (!function_exists('makeFields')) {
    function makeFields($all_fields)
    {
        $fields = '';
        foreach ($all_fields as $value) {
            $fields .= $value;
        }

        return $fields;
    }
}

if (!function_exists('makeFieldsApigames')) {
    function makeFieldsApigames($all_fields)
    {
        $fields = '';
        foreach ($all_fields as $value) {
            $fields .= $value->value;
        }

        return $fields;
    }
}

// Khusus untuk fields pengecekan games ke validation.vogaon.com
if (!function_exists('arrangeFields')) {
    function arrangeFields($fields, $game_code)
    {
        $newFields = [];
        foreach ($fields as $key => $value) {
            $dbfi = DB::table('fields')
                ->where('name', $key)
                ->where('game_code', $game_code)
                ->first('name_other');

            $newFields[$dbfi->name_other ?? $key] = $value;
        }
        Log::alert($newFields);
        return $newFields;
    }
}

if (!function_exists('getIdGamesAttributeBridge')) {
    function getIdGamesAttributeBridge($transaction_code)
    {
        $result = [];
        $group = [];
        $details = DB::table('transaction_detail')->where('transaction_code', $transaction_code)->get();

        foreach ($details as $detail) {
            $gid = getIdGamesAttribute($detail);

            $result = array_merge($group, $gid);
        }

        return $result;
    }
}

if (!function_exists('getUsersShops')) {
    function getUsersShops()
    {
        $user_id = Auth::user()->id;

        $shops = DB::table('users_shops')->where('user_id', $user_id)->first();

        if (empty($shops)) {
            return false;
        }

        return $shops;
    }
}

if (!function_exists('getUsersShopsByShopsId')) {
    function getUsersShopsByShopsId($id)
    {
        $shops = DB::table('users_shops')->where('id', $id)->first(['id as users_shops_id', 'user_id', 'shop_name', 'shop_location', 'status']);

        if (empty($shops)) {
            return false;
        }

        $loc = DB::table('users_alamat')->where('user_id', $shops->user_id)->where('alamat_toko', 'Y')->first();

        $shops->shop_location = substr($shops->shop_location, 0, 15);
        if (isset($loc)) {
            $shops->shop_location = $loc->kota;
        }

        return $shops;
    }
}

if (!function_exists('getIdGamesAttribute')) {
    function getIdGamesAttribute($transaction_detail)
    {
        if (!isset($transaction_detail->userid)) {
            return [];
        }

        $userid = $transaction_detail->userid;
        $game_code = $transaction_detail->game_code;
        $transaction_code = $transaction_detail->transaction_code;

        $result = [];

        if (!empty($transaction_detail->username) && $transaction_detail->username != '#') {
            $result = [
                (object)[
                    'display_name' => 'Username',
                    'name' => 'username',
                    'type' => 'string',
                    'username' => $transaction_detail->username,
                    'value' => $transaction_detail->username,
                ]
            ];
        }

        $tgameid = DB::table('transaction_game_id')
            ->where('transaction_code', $transaction_code)
            ->where('game_code', $game_code)
            ->get([
                'transaction_game_id.fields_name as name',
                'value'
            ]);

        if (count($tgameid) > 0) {
            foreach ($tgameid as $value) {
                $fl = DB::table('fields')
                    ->where('game_code', $game_code)
                    ->where('name', $value->name)
                    ->first();
                $df = (object)[
                    'display_name' => $fl->display_name,
                    'name' => 'userid',
                    'type' => 'string',
                    'userid' => $value->value,
                    'value' => $value->value,

                    // 'title' => strtoupper($fl->display_name),
                    // 'value' => $value->value,
                    // 'v' => $value->value,
                ];

                array_push($result, $df);
            }
        } else {
            $fields = DB::table('fields')->where('game_code', $transaction_detail->game_code)->get();
            if (isset($userid[0]) && $userid[0] == '-') {
                $userid = ltrim($userid, '-');
            }
            $new_userid = explode('-', $userid);
            // if (count($new_userid) > 1) {
            foreach ($fields as $key => $value) {
                switch ($value->type) {
                    case 'dropdown':
                        $fields_data = DB::table('fields_data')
                            ->where('game_code', $transaction_detail->game_code)
                            ->where('value', $new_userid[$key] ?? '-')
                            ->first();
                        $field = (object)[
                            'display_name' => $value->display_name,
                            'name' => 'userid',
                            'type' => 'string',
                            'username' => $fields_data->name ?? '-',
                            'value' => $fields_data->name ?? '-',

                            // 'title' => strtoupper($value->display_name),
                            // 'value' => $fields_data->name ?? '-',
                        ];
                        break;

                    default:
                        $field = (object)[
                            'display_name' => 'userid',
                            'name' => 'userid',
                            'type' => 'string',
                            'userid' => $transaction_detail->userid,
                            'value' => $transaction_detail->userid,

                            // 'title' => strtoupper($value->display_name),
                            // 'value' => $new_userid[$key] ?? '-',
                        ];
                        break;
                }

                array_push($result, $field);
            }
        }

        // } else {
        //     $fd = (object)[
        //         'title' => 'USERID',
        //         'value' => $userid,
        //     ];

        //     array_push($result, $fd);
        // }

        return $result;
    }
}

if (!function_exists('getFeeList')) {
    function getFeeList(bool $total = false)
    {
        if ($total) {
            return 1000;
        } else {
            return [
                (object)[
                    'id' => 1,
                    'fee' => (float)1000.00,
                    'title' => 'Biaya Layanan'
                ]
            ];
        }
    }
}

if (!function_exists('getShopWallet')) {
    function updateShopBalance($shop_id, $addbalance)
    {   
        $wallet = DB::table('users_shops_balance')
            ->where('shop_id', $shop_id)
            ->first();
        if (empty($wallet)) {
            DB::table('users_shops_balance')
                ->insert([
                    'shop_id'=> $shop_id,
                    'balance'=> $addbalance,
                ]);

            $wallet = DB::table('users_shops_balance')
                ->where('shop_id', $shop_id)
                ->first();

            return $wallet;
        } else {
            DB::table('users_shops_balance')
                ->where('shop_id', $shop_id)
                ->update([
                    'balance'=> $wallet->balance + $addbalance,
                ]);
        }
    }
}

if (!function_exists('getCustomMessages')) {
    function getCustomMessages()
    {
        return  [
            /*
            |---------------------------------------------------------------------------------------
            | Baris Bahasa untuk Validasi
            |---------------------------------------------------------------------------------------
            |
            | Baris bahasa berikut ini berisi standar pesan kesalahan yang digunakan oleh
            | kelas validasi. Beberapa aturan mempunyai banyak versi seperti aturan 'size'.
            | Jangan ragu untuk mengoptimalkan setiap pesan yang ada di sini.
            |
            */

            'accepted'        => ':attribute harus diterima.',
            'active_url'      => ':attribute bukan URL yang valid.',
            'after'           => ':attribute harus berisi tanggal setelah :date.',
            'after_or_equal'  => ':attribute harus berisi tanggal setelah atau sama dengan :date.',
            'alpha'           => ':attribute hanya boleh berisi huruf.',
            'alpha_dash'      => ':attribute hanya boleh berisi huruf, angka, strip, dan garis bawah.',
            'alpha_num'       => ':attribute hanya boleh berisi huruf dan angka.',
            'array'           => ':attribute harus berisi sebuah array.',
            'before'          => ':attribute harus berisi tanggal sebelum :date.',
            'before_or_equal' => ':attribute harus berisi tanggal sebelum atau sama dengan :date.',
            'between'         => [
                'numeric' => ':attribute harus bernilai antara :min sampai :max.',
                'file'    => ':attribute harus berukuran antara :min sampai :max kilobita.',
                'string'  => ':attribute harus berisi antara :min sampai :max karakter.',
                'array'   => ':attribute harus memiliki :min sampai :max anggota.',
            ],
            'boolean'        => ':attribute harus bernilai true atau false',
            'confirmed'      => 'Konfirmasi :attribute tidak cocok.',
            'date'           => ':attribute bukan tanggal yang valid.',
            'date_equals'    => ':attribute harus berisi tanggal yang sama dengan :date.',
            'date_format'    => ':attribute tidak cocok dengan format :format.',
            'different'      => ':attribute dan :other harus berbeda.',
            'digits'         => ':attribute harus terdiri dari :digits angka.',
            'digits_between' => ':attribute harus terdiri dari :min sampai :max angka.',
            'dimensions'     => ':attribute tidak memiliki dimensi gambar yang valid.',
            'distinct'       => ':attribute memiliki nilai yang duplikat.',
            'email'          => ':attribute harus berupa alamat surel yang valid.',
            'ends_with'      => ':attribute harus diakhiri salah satu dari berikut: :values',
            'exists'         => ':attribute yang dipilih tidak valid.',
            'file'           => ':attribute harus berupa sebuah berkas.',
            'filled'         => ':attribute harus memiliki nilai.',
            'gt'             => [
                'numeric' => ':attribute harus bernilai lebih besar dari :value.',
                'file'    => ':attribute harus berukuran lebih besar dari :value kilobita.',
                'string'  => ':attribute harus berisi lebih besar dari :value karakter.',
                'array'   => ':attribute harus memiliki lebih dari :value anggota.',
            ],
            'gte' => [
                'numeric' => ':attribute harus bernilai lebih besar dari atau sama dengan :value.',
                'file'    => ':attribute harus berukuran lebih besar dari atau sama dengan :value kilobita.',
                'string'  => ':attribute harus berisi lebih besar dari atau sama dengan :value karakter.',
                'array'   => ':attribute harus terdiri dari :value anggota atau lebih.',
            ],
            'image'    => ':attribute harus berupa gambar.',
            'in'       => ':attribute yang dipilih tidak valid.',
            'in_array' => ':attribute tidak ada di dalam :other.',
            'integer'  => ':attribute harus berupa bilangan bulat.',
            'ip'       => ':attribute harus berupa alamat IP yang valid.',
            'ipv4'     => ':attribute harus berupa alamat IPv4 yang valid.',
            'ipv6'     => ':attribute harus berupa alamat IPv6 yang valid.',
            'json'     => ':attribute harus berupa JSON string yang valid.',
            'lt'       => [
                'numeric' => ':attribute harus bernilai kurang dari :value.',
                'file'    => ':attribute harus berukuran kurang dari :value kilobita.',
                'string'  => ':attribute harus berisi kurang dari :value karakter.',
                'array'   => ':attribute harus memiliki kurang dari :value anggota.',
            ],
            'lte' => [
                'numeric' => ':attribute harus bernilai kurang dari atau sama dengan :value.',
                'file'    => ':attribute harus berukuran kurang dari atau sama dengan :value kilobita.',
                'string'  => ':attribute harus berisi kurang dari atau sama dengan :value karakter.',
                'array'   => ':attribute harus tidak lebih dari :value anggota.',
            ],
            'max' => [
                'numeric' => ':attribute maskimal bernilai :max.',
                'file'    => ':attribute maksimal berukuran :max kilobita.',
                'string'  => ':attribute maskimal berisi :max karakter.',
                'array'   => ':attribute maksimal terdiri dari :max anggota.',
            ],
            'mimes'     => ':attribute harus berupa berkas berjenis: :values.',
            'mimetypes' => ':attribute harus berupa berkas berjenis: :values.',
            'min'       => [
                'numeric' => ':attribute minimal bernilai :min.',
                'file'    => ':attribute minimal berukuran :min kilobita.',
                'string'  => ':attribute minimal berisi :min karakter.',
                'array'   => ':attribute minimal terdiri dari :min anggota.',
            ],
            'not_in'               => ':attribute yang dipilih tidak valid.',
            'not_regex'            => 'Format :attribute tidak valid.',
            'numeric'              => ':attribute harus berupa angka.',
            'password'             => 'Kata sandi salah.',
            'present'              => ':attribute wajib ada.',
            'regex'                => 'Format :attribute tidak valid.',
            'required'             => ':attribute wajib diisi.',
            'required_if'          => ':attribute wajib diisi bila :other adalah :value.',
            'required_unless'      => ':attribute wajib diisi kecuali :other memiliki nilai :values.',
            'required_with'        => ':attribute wajib diisi bila terdapat :values.',
            'required_with_all'    => ':attribute wajib diisi bila terdapat :values.',
            'required_without'     => ':attribute wajib diisi bila tidak terdapat :values.',
            'required_without_all' => ':attribute wajib diisi bila sama sekali tidak terdapat :values.',
            'same'                 => ':attribute dan :other harus sama.',
            'size'                 => [
                'numeric' => ':attribute harus berukuran :size.',
                'file'    => ':attribute harus berukuran :size kilobyte.',
                'string'  => ':attribute harus berukuran :size karakter.',
                'array'   => ':attribute harus mengandung :size anggota.',
            ],
            'starts_with' => ':attribute harus diawali salah satu dari berikut: :values',
            'string'      => ':attribute harus berupa string.',
            'timezone'    => ':attribute harus berisi zona waktu yang valid.',
            'unique'      => ':attribute sudah digunakan.',
            'uploaded'    => ':attribute gagal diunggah.',
            'url'         => 'Format :attribute tidak valid.',
            'uuid'        => ':attribute harus merupakan UUID yang valid.',

            /*
            |---------------------------------------------------------------------------------------
            | Baris Bahasa untuk Validasi Kustom
            |---------------------------------------------------------------------------------------
            |
            | Di sini Anda dapat menentukan pesan validasi untuk atribut sesuai keinginan dengan menggunakan 
            | konvensi "attribute.rule" dalam penamaan barisnya. Hal ini mempercepat dalam menentukan
            | baris bahasa kustom yang spesifik untuk aturan atribut yang diberikan.
            |
            */

            'custom' => [
                'attribute-name' => [
                    'rule-name' => 'custom-message',
                ],
            ],

            /*
            |---------------------------------------------------------------------------------------
            | Kustom Validasi Atribut
            |---------------------------------------------------------------------------------------
            |
            | Baris bahasa berikut digunakan untuk menukar 'placeholder' atribut dengan sesuatu yang
            | lebih mudah dimengerti oleh pembaca seperti "Alamat Surel" daripada "surel" saja.
            | Hal ini membantu kita dalam membuat pesan menjadi lebih ekspresif.
            |
            */

            'attributes' => [],
        ];
    }
}
